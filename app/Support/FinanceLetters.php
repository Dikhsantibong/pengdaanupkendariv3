<?php

namespace App\Support;

use App\Enums\ProcurementStage;
use App\Enums\TeamLeaderField;
use App\Enums\UnitManagerLevel;
use App\Enums\WorkCategory;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementContractItem;
use App\Models\UnitManager;
use App\Services\ContractRequirementReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The letters the partner writes on its own letterhead for the Keuangan
 * stage: the Surat Jalan and the Surat Pernyataan Garansi Material handed
 * over with the goods, the Surat Pengantar Penyerahan Pekerjaan asking for
 * the inspection, the Berita Acara Pemeriksaan / Penyelesaian
 * Pekerjaan signed with the unit that received it, and the Surat
 * Permohonan Pembayaran.
 *
 * They are not generated as documents of the procurement. Their step offers
 * the template, filled in with the contract's data, to download as a Word
 * file; the partner puts its kop on it, signs it, and the signed letter is
 * uploaded back on the same step. The wording is an ordinary template on
 * Data Master → Template Dokumen.
 */
class FinanceLetters
{
    /**
     * A line to write on, where a value is not known.
     */
    protected const BLANK = '.........................';

    /**
     * Each letter by its document type code: its name, and the Keuangan
     * steps it is the paper of, matched on the step's name.
     *
     * @var array<string, array{name: string, step: string}>
     */
    public const LETTERS = [
        'surat-jalan' => [
            'name' => 'Surat Jalan',
            'step' => '/surat\s+jalan/i',
        ],
        // The cover letter handing the finished work over for inspection.
        'surat-pengantar-penyerahan' => [
            'name' => 'Surat Pengantar Penyerahan Pekerjaan',
            'step' => '/(surat\s+(pengantar\s+)?penyerahan\s+pekerjaan|serah\s+terima\s+pekerjaan)/i',
        ],
        // Of the goods, the work or the service, as the step names it.
        'berita-acara-pemeriksaan' => [
            'name' => 'Berita Acara Pemeriksaan Pekerjaan',
            'step' => '/berita\s+acara\s+(pemeriksaan|penyelesaian)/i',
        ],
        // A material's guarantee; one for the work done is another letter.
        'surat-pernyataan-garansi' => [
            'name' => 'Surat Pernyataan Garansi Material',
            'step' => '/surat\s+(pernyataan\s+)?garansi(?!\s+pekerjaan)/i',
        ],
        'surat-permohonan-pembayaran' => [
            'name' => 'Surat Permohonan Pembayaran',
            'step' => '/permohonan\s+pembayaran/i',
        ],
    ];

    /**
     * The document type codes of the letters.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::LETTERS);
    }

    /**
     * The letter a step is evidenced by, if any: a Keuangan step finished by
     * its own upload, named after one of the letters.
     */
    public static function codeFor(ProcurementChecklist $checklist): ?string
    {
        if ($checklist->stage !== ProcurementStage::Keuangan || ! $checklist->needsOwnUpload()) {
            return null;
        }

        foreach (self::LETTERS as $code => $letter) {
            if (preg_match($letter['step'], $checklist->checklistItem->name) === 1) {
                return $code;
            }
        }

        return null;
    }

    /**
     * The template a procurement's letter is written from.
     */
    public static function template(Procurement $procurement, string $code): ?DocumentTemplate
    {
        $typeId = DocumentType::query()->where('code', $code)->value('id');

        if ($typeId === null) {
            return null;
        }

        return DocumentTemplate::resolveFor(
            (int) $typeId,
            $procurement->procurement_method_id,
            $procurement->work_category,
            $procurement->contract_number_format_id,
        );
    }

    /**
     * The contract the letters refer to (its kind, number and date), the
     * guarantee period, who receives the goods and what is sent, and the
     * title of the Berita Acara the step asks for, with a line to write on
     * for what is not known yet.
     *
     * @return array<string, string>
     */
    public static function values(Procurement $procurement, ?ProcurementChecklist $checklist = null): array
    {
        $procurement->loadMissing(['contractNumberFormat', 'targetUnit', 'contractItems']);
        $contract = app(ContractRequirementReader::class)->contractDocument($procurement);
        $months = $procurement->warranty_months;
        $report = self::reportTitle($procurement, $checklist);

        return [
            'jenis_dokumen_kontrak_judul' => match ($procurement->contractNumberFormat?->code) {
                'PJ' => 'Surat Perjanjian',
                'SPPL' => 'Surat Pesanan',
                default => 'Surat Perintah Kerja (SPK)',
            },
            // The partner's own details, to fill in by hand while not known.
            'nama_mitra' => self::orBlank($procurement->partner_name),
            'alamat_perusahaan' => self::orBlank($procurement->partner_address),
            'nama_bank' => self::orBlank($procurement->bank_name),
            'nomor_rekening' => self::orBlank($procurement->bank_account_number),
            'nama_pemilik_rekening' => self::orBlank($procurement->bank_account_holder),
            'nomor_kontrak' => $procurement->hasContractNumber() ? (string) $procurement->number : self::BLANK,
            ...($procurement->value_after_negotiation === null ? [
                'nilai_setelah_nego_total_angka' => self::BLANK,
                'nilai_setelah_nego_total_terbilang' => self::BLANK,
            ] : []),
            'tanggal_kontrak' => $contract?->generated_at?->translatedFormat('d F Y') ?? self::BLANK,
            'masa_garansi_bulan' => $months === null ? '........' : (string) $months,
            'masa_garansi_bulan_terbilang' => $months === null ? '........' : IndonesianNumber::spell((int) $months),
            ...self::receiver($procurement),
            'tabel_surat_jalan' => self::itemTable($procurement),
            'judul_berita_acara' => $report,
            'nama_berita_acara' => Str::title(mb_strtolower($report)),
        ];
    }

    /**
     * A value as typed, or a line to write it on.
     */
    protected static function orBlank(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? self::BLANK : $value;
    }

    /**
     * The name a downloaded letter is saved under.
     */
    public static function fileName(Procurement $procurement, string $code): string
    {
        $number = $procurement->hasContractNumber() ? ' '.str_replace(['/', '\\'], '-', (string) $procurement->number) : '';

        return self::LETTERS[$code]['name'].$number.'.doc';
    }

    /**
     * Who receives the goods at the procurement's unit, from Data Master →
     * Manager Unit: the Manager UL holding the unit, and the TL OPHAR of the
     * unit, else the TL Pemeliharaan of its Unit Layanan.
     *
     * @return array{unit_penerima: string, up_penerima: string, nama_penerima: string, nama_manager_penerima: string}
     */
    protected static function receiver(Procurement $procurement): array
    {
        $unit = $procurement->targetUnit;

        $holders = UnitManager::query()
            ->active()
            ->whereHas('targetUnits', fn (Builder $query): Builder => $query->where('target_units.id', $unit->id))
            ->ordered()
            ->get();

        $managerUl = $holders->first(fn (UnitManager $official): bool => $official->level === UnitManagerLevel::ManagerUl);
        $receiver = $holders->first(fn (UnitManager $official): bool => $official->level === UnitManagerLevel::TlOphar)
            ?? $holders->first(fn (UnitManager $official): bool => $official->level === UnitManagerLevel::Tl && $official->field === TeamLeaderField::Pemeliharaan);

        return [
            'unit_penerima' => mb_strtoupper($unit->name),
            'up_penerima' => $managerUl === null ? self::BLANK : trim('MANAGER '.($managerUl->description ?: 'UL')),
            'nama_penerima' => $receiver->name ?? self::BLANK,
            'nama_manager_penerima' => $managerUl->name ?? self::BLANK,
        ];
    }

    /**
     * The title of the Berita Acara: of the goods or of the work, as the
     * step names it, else as the contract's category has it.
     */
    protected static function reportTitle(Procurement $procurement, ?ProcurementChecklist $checklist): string
    {
        $step = (string) $checklist?->checklistItem->name;

        return match (true) {
            preg_match('/pemeriksaan\s+barang/i', $step) === 1 => 'BERITA ACARA PEMERIKSAAN BARANG',
            preg_match('/pemeriksaan\s+(pekerjaan|jasa)/i', $step) === 1 => 'BERITA ACARA PEMERIKSAAN PEKERJAAN',
            preg_match('/penyelesaian\s+pekerjaan/i', $step) === 1 => 'BERITA ACARA PENYELESAIAN PEKERJAAN',
            $procurement->work_category === WorkCategory::Barang => 'BERITA ACARA PEMERIKSAAN BARANG',
            default => 'BERITA ACARA PENYELESAIAN PEKERJAAN',
        };
    }

    /**
     * The goods sent, from the Rincian Item: one row per item under its
     * group's heading, or rows to write on while the items are not listed.
     */
    protected static function itemTable(Procurement $procurement): string
    {
        $cell = 'border: 1px solid #000000; padding: 2pt 4pt;';
        $head = '<tr>'
            .'<th style="'.$cell.' width: 6%; text-align: center;">No.</th>'
            .'<th style="'.$cell.' text-align: center;">Nama Barang</th>'
            .'<th style="'.$cell.' width: 18%; text-align: center;">Part Number</th>'
            .'<th style="'.$cell.' width: 8%; text-align: center;">Vol.</th>'
            .'<th style="'.$cell.' width: 8%; text-align: center;">Sat.</th>'
            .'<th style="'.$cell.' width: 16%; text-align: center;">Keterangan</th>'
            .'</tr>';

        $rows = '';
        $group = null;
        $number = 0;

        foreach ($procurement->contractItems as $item) {
            if ($item->group_name !== null && $item->group_name !== $group) {
                $group = $item->group_name;
                $rows .= '<tr><td style="'.$cell.'"></td><td colspan="5" style="'.$cell.' font-weight: bold;">'.e($group).'</td></tr>';
            }

            $rows .= '<tr>'
                .'<td style="'.$cell.' text-align: center;">'.++$number.'.</td>'
                .'<td style="'.$cell.'">'.e($item->description).'</td>'
                .'<td style="'.$cell.' text-align: center;">'.e((string) $item->part_number).'</td>'
                .'<td style="'.$cell.' text-align: center;">'.self::volume($item).'</td>'
                .'<td style="'.$cell.' text-align: center;">'.e($item->unit).'</td>'
                .'<td style="'.$cell.'"></td>'
                .'</tr>';
        }

        if ($rows === '') {
            foreach (range(1, 5) as $row) {
                $rows .= '<tr><td style="'.$cell.' text-align: center;">'.$row.'.</td>'
                    .str_repeat('<td style="'.$cell.'">&nbsp;</td>', 5).'</tr>';
            }
        }

        return '<table class="surat-items" style="width: 100%; border-collapse: collapse; margin: 6pt 0;">'.$head.$rows.'</table>';
    }

    /**
     * A volume without trailing zeros: 2, 2,5, 0,125.
     */
    protected static function volume(ProcurementContractItem $item): string
    {
        return rtrim(rtrim(number_format((float) $item->volume, 3, ',', '.'), '0'), ',');
    }
}
