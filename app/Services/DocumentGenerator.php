<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\AmendmentFileKind;
use App\Enums\ProcurementStage;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementAmendmentChange;
use App\Models\ProcurementContractItem;
use App\Models\ProcurementDeliveryOrder;
use App\Models\ProcurementDeliveryOrderItem;
use App\Models\ProcurementDocument;
use App\Models\UnitManager;
use App\Models\User;
use App\Models\VendorAssessmentRequest;
use App\Support\IndonesianNumber;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentGenerator
{
    public function __construct(
        protected ProcurementService $procurements,
        protected ItemTableRenderer $itemTables,
    ) {}

    /**
     * The document types whose item table is drawn from the Rincian Item.
     *
     * @var list<string>
     */
    public const ITEM_TABLE_TYPES = [
        'ba-negosiasi-sppl', 'ba-negosiasi', 'purchase-order', 'surat-pesanan',
        'spk', 'hpe', 'upb', 'penyusunan-hps', 'penawaran', 'lampiran-spk',
    ];

    /**
     * The placeholders every template may reference, with a human readable label.
     *
     * @return array<string, string>
     */
    public static function placeholderCatalog(): array
    {
        return [
            'nomor_pengadaan' => 'Nomor pengadaan internal',
            'nama_pengadaan' => 'Nama/judul pekerjaan',
            'nama_mitra' => 'Nama calon mitra / pelaksana pekerjaan',
            'kode_rekanan_mitra' => 'Kode rekanan (vendor) mitra pelaksana',
            'nama_direktur' => 'Nama direktur calon mitra',
            'alamat_mitra' => 'Alamat calon mitra / pelaksana (default: DI TEMPAT bila kosong)',
            'alamat_perusahaan' => 'Alamat perusahaan calon mitra',
            'direksi_pekerjaan' => 'Direksi pekerjaan',
            'unit_tujuan' => 'Unit tujuan (seluruh unit, dipisah koma)',
            'metode_pengadaan' => 'Metode pengadaan',
            'sumber_anggaran' => 'Sumber anggaran (kode, mis. AO)',
            'sumber_anggaran_keterangan' => 'Kepanjangan sumber anggaran, mis. Anggaran Operasi',
            'jenis_kontrak' => 'Jenis kontrak, mis. KHS atau Lumsum',
            'kategori_pekerjaan' => 'Kategori kontrak: Barang atau Jasa',
            'nomor_rks' => 'Nomor RKS dari No Kontrak, mis. 017.RKS-PJ/612/UPKD/2026',
            'nama_manager' => 'Nama Manager UP Kendari',
            'nomor_surat_penawaran' => 'Nomor surat penawaran',
            'tanggal_surat_penawaran' => 'Tanggal surat penawaran',
            'nomor_nota_dinas_manager' => 'Nomor nota dinas ke manager',
            'nomor_pr_ro' => 'Nomor PR/PO (diketik manual)',
            'nomor_pr_po' => 'Nomor PR/PO (diketik manual)',
            'nomor_prk' => 'Nomor PRK',
            'nomor_nota_dinas_usulan' => 'Nomor nota dinas usulan',
            'tanggal_nota_dinas_usulan' => 'Tanggal nota dinas usulan',
            'nomor_nota_dinas_icc' => 'Nomor nota dinas ke manager',
            'tanggal_nota_dinas_icc' => 'Tanggal nota dinas ke manager',
            'tanggal_nota_dinas_manager' => 'Tanggal nota dinas ke manager',
            'nomor_coa' => 'Nomor COA',
            'nomor_wo' => 'Nomor WO',
            'nomor_sr' => 'Nomor SR',
            'nilai_hpe' => 'Nilai sebelum nego / HPE (format Rupiah)',
            'nilai_hpe_angka' => 'Nilai HPE dalam angka tanpa simbol',
            'nilai_hpe_terbilang' => 'Nilai HPE dieja dalam huruf',
            'nilai_hpe_dpp' => 'Nilai DPP HPE (11/12, format Rupiah)',
            'nilai_hpe_dpp_angka' => 'Nilai DPP HPE dalam angka tanpa simbol',
            'nilai_hpe_ppn' => 'Nilai PPN 12% HPE (format Rupiah)',
            'nilai_hpe_ppn_angka' => 'Nilai PPN 12% HPE dalam angka tanpa simbol',
            'nilai_hpe_total' => 'Total HPE termasuk PPN (format Rupiah)',
            'nilai_hpe_total_angka' => 'Total HPE termasuk PPN dalam angka tanpa simbol',
            'nilai_hpe_total_terbilang' => 'Total HPE termasuk PPN dieja dalam huruf',
            'nilai_setelah_nego' => 'Nilai setelah nego (format Rupiah)',
            'nilai_setelah_nego_angka' => 'Nilai setelah nego dalam angka tanpa simbol',
            'nilai_setelah_nego_terbilang' => 'Nilai setelah nego dieja dalam huruf',
            'nilai_setelah_nego_dpp' => 'Nilai DPP setelah nego (11/12, format Rupiah)',
            'nilai_setelah_nego_dpp_angka' => 'Nilai DPP setelah nego dalam angka tanpa simbol',
            'nilai_setelah_nego_ppn' => 'Nilai PPN 12% setelah nego (format Rupiah)',
            'nilai_setelah_nego_ppn_angka' => 'Nilai PPN 12% setelah nego dalam angka tanpa simbol',
            'nilai_setelah_nego_total' => 'Total setelah nego termasuk PPN (format Rupiah)',
            'nilai_setelah_nego_total_angka' => 'Total setelah nego termasuk PPN dalam angka tanpa simbol',
            'nilai_setelah_nego_total_terbilang' => 'Total setelah nego termasuk PPN dieja dalam huruf',
            'tanggal_mulai_pelaksanaan' => 'Tanggal mulai pelaksanaan',
            'jangka_waktu_hari' => 'Jangka waktu pelaksanaan (hari)',
            'jangka_waktu_hari_terbilang' => 'Jangka waktu pelaksanaan dieja dalam huruf',
            'tanggal_selesai_pelaksanaan' => 'Tanggal akhir pelaksanaan (dihitung otomatis)',
            'masa_garansi_bulan' => 'Masa garansi (bulan)',
            'masa_garansi_bulan_terbilang' => 'Masa garansi dieja dalam huruf (mis. tiga)',
            'nomor_rekening' => 'Nomor rekening pelaksana',
            'nama_bank' => 'Nama bank pelaksana',
            'nama_pemilik_rekening' => 'Nama pemilik rekening',
            'status_progres' => 'Status progres saat ini',
            'pic_perencana' => 'Nama PIC perencana',
            'pic_pelaksana' => 'Nama PIC pelaksana',
            'nomor_ba_nego' => 'Nomor Berita Acara Negosiasi',
            'target_penyelesaian' => 'Target penyelesaian pengadaan',
            'tanggal_dokumen' => 'Tanggal dokumen digenerate',
            'tahun' => 'Tahun berjalan',
            'tautan_penilaian_vendor' => 'Tautan tetap untuk vendor mengisi rincian tagihan (request penilaian vendor)',
            // Filled only on the amendment document (tab Amandemen).
            'amandemen_ke' => 'Amandemen: urutan dalam angka romawi (I, II, ...)',
            'nomor_amandemen' => 'Amandemen: nomor amandemen (format Amd)',
            'jenis_dokumen_kontrak' => 'Amandemen: jenis kontrak yang diubah (SURAT PERJANJIAN / SURAT PERINTAH KERJA / SURAT PESANAN)',
            'jenis_dokumen_kontrak_judul' => 'Amandemen: jenis kontrak yang diubah, huruf judul',
            'nomor_kontrak' => 'Amandemen: nomor kontrak yang diubah',
            'tanggal_kontrak' => 'Amandemen: tanggal kontrak yang diubah',
            'tanggal_amandemen' => 'Amandemen: tanggal amandemen',
            'tanggal_amandemen_angka' => 'Amandemen: tanggal amandemen (dd-mm-yyyy)',
            'hari_amandemen' => 'Amandemen: nama hari tanggal amandemen',
            'tanggal_amandemen_terbilang' => 'Amandemen: tanggal amandemen dalam kata',
            'bulan_amandemen' => 'Amandemen: nama bulan amandemen',
            'tahun_amandemen_terbilang' => 'Amandemen: tahun amandemen dalam kata',
            'tanggal_notulen' => 'Amandemen: tanggal notulen rapat pembahasan',
            'nomor_nota_dinas_amandemen' => 'Amandemen: nomor nota dinas amandemen',
            'tanggal_nota_dinas_amandemen' => 'Amandemen: tanggal nota dinas amandemen',
            'nomor_surat_permohonan_amandemen' => 'Amandemen: nomor surat permohonan amandemen',
            'tanggal_surat_permohonan_amandemen' => 'Amandemen: tanggal surat permohonan amandemen',
            'daftar_perubahan' => 'Amandemen: daftar perubahan pasal (Semula berbunyi / Menjadi)',
            'butir_surat_permohonan' => 'Amandemen: butir dasar "Surat Permohonan Amandemen" dari mitra (kosong bila tidak diunggah)',
            // Filled only on the Delivery Order document (tab DO, kontrak KHS).
            'nomor_do' => 'DO: nomor Delivery Order (format DO)',
            'tanggal_do' => 'DO: tanggal Delivery Order',
            'kode_rekanan' => 'DO: kode rekanan (vendor)',
            'uraian_pekerjaan_do' => 'DO: uraian pekerjaan',
            'tempat_penyerahan_do' => 'DO: tempat penyerahan pekerjaan',
            'waktu_penyerahan_do' => 'DO: waktu penyerahan (hari kalender)',
            'waktu_penyerahan_do_terbilang' => 'DO: waktu penyerahan dieja dalam huruf',
            'rincian_do' => 'DO: baris tabel rincian barang / pekerjaan',
            'total_do' => 'DO: total nilai DO (angka)',
            'total_do_terbilang' => 'DO: total nilai DO dieja dalam huruf',
            'nomor_nota_dinas_usulan_do' => 'DO: nomor nota dinas usulan dari pengusul',
            'tanggal_nota_dinas_usulan_do' => 'DO: tanggal nota dinas usulan dari pengusul',
            'nomor_nota_dinas_do' => 'DO: nomor nota dinas DO dari Rendan',
            'tanggal_nota_dinas_do' => 'DO: tanggal nota dinas DO dari Rendan',
            'checklist_perencanaan' => 'Daftar checklist tahap perencanaan',
            'checklist_pelaksanaan' => 'Daftar checklist tahap pelaksanaan',
        ];
    }

    /**
     * Build the placeholder values for a procurement.
     *
     * @return array<string, string>
     */
    public function placeholderValues(Procurement $procurement, ?CarbonInterface $issuedAt = null): array
    {
        // The issue date of a document stays the day it was generated, even
        // when the document is later rebuilt to follow changed data.
        $issuedAt ??= now();

        $procurement->loadMissing([
            'workDirector',
            'targetUnit',
            'procurementMethod',
            'budgetSource',
            'contractType',
            'targetUnits',
            'progressStatus',
            'planner',
            'executor',
            'checklists.checklistItem',
        ]);

        $hpe = (float) $procurement->hpe_value;
        $hpeDpp = (float) round($hpe * 11 / 12);
        $hpePpn = (float) round($hpe * 0.12);
        $hpeTotal = $hpe + $hpePpn;

        $nego = $procurement->value_after_negotiation !== null ? (float) $procurement->value_after_negotiation : null;
        $negoDpp = $nego !== null ? (float) round($nego * 11 / 12) : null;
        $negoPpn = $nego !== null ? (float) round($nego * 0.12) : null;
        $negoTotal = $nego !== null ? $nego + $negoPpn : null;

        $activeManager = UnitManager::query()
            ->where('is_active', true)
            ->ordered()
            ->first();
        $managerName = $activeManager?->name ?? 'MANAGER UP KENDARI';

        $partnerAddress = ! empty(trim((string) $procurement->partner_address))
            ? trim((string) $procurement->partner_address)
            : null;

        return [
            'nomor_pengadaan' => $procurement->number,
            'nama_pengadaan' => $procurement->name,
            'nama_mitra' => $procurement->partner_name ?? '-',
            'kode_rekanan_mitra' => $procurement->partner_vendor_code ?: '-',
            'nama_direktur' => $procurement->partner_director_name ?: ($procurement->bank_account_holder ?: '-'),
            'alamat_mitra' => $partnerAddress ?? 'DI TEMPAT',
            'alamat_perusahaan' => $partnerAddress ?? '-',
            'direksi_pekerjaan' => $procurement->workDirector?->name ?? '-',
            'nama_manager' => $managerName,
            'unit_tujuan' => $procurement->targetUnitNames(),
            'metode_pengadaan' => $procurement->procurement_method_id === null
                ? '-'
                : ($procurement->procurementMethod?->name ?? '-'),
            'sumber_anggaran' => $procurement->budget_source_id === null
                ? '-'
                : ($procurement->budgetSource?->name ?? '-'),
            'sumber_anggaran_keterangan' => $procurement->budget_source_id === null
                ? '-'
                : rtrim($procurement->budgetSource?->description ?? $procurement->budgetSource?->name ?? '-', '.'),
            'kategori_pekerjaan' => $procurement->work_category?->label() ?? '-',
            'nomor_rks' => $this->rksNumber($procurement->number),
            'jenis_kontrak' => $procurement->contract_type_id === null
                ? '-'
                : ($procurement->contractType?->name ?? '-'),
            'nomor_nota_dinas_manager' => $procurement->icc_memo_number ?? $procurement->proposal_memo_number ?? $procurement->manager_memo_number ?? '-',
            // Kept under its old key too, so templates written for PR/RO still fill in.
            'nomor_pr_ro' => $procurement->pr_po_number ?? '-',
            'nomor_pr_po' => $procurement->pr_po_number ?? '-',
            'nomor_prk' => $procurement->prk_number ?? '-',
            'nomor_nota_dinas_usulan' => $procurement->proposal_memo_number ?? '-',
            'tanggal_nota_dinas_usulan' => $procurement->proposal_memo_date?->translatedFormat('d F Y') ?? '-',
            'nomor_nota_dinas_icc' => $procurement->icc_memo_number ?? $procurement->manager_memo_number ?? '-',
            'tanggal_nota_dinas_icc' => $procurement->icc_memo_date?->translatedFormat('d F Y') ?? '-',
            'tanggal_nota_dinas_manager' => $procurement->icc_memo_date?->translatedFormat('d F Y') ?? $procurement->proposal_memo_date?->translatedFormat('d F Y') ?? '-',
            'nomor_surat_penawaran' => $procurement->quotation_number ?? '-',
            'tanggal_surat_penawaran' => $procurement->quotation_date?->translatedFormat('d F Y') ?? '-',
            'nomor_coa' => $procurement->coa_number ?? '-',
            'nomor_wo' => $procurement->wo_number ?? '-',
            'nomor_sr' => $procurement->sr_number ?? '-',
            'nilai_hpe' => 'Rp '.number_format($hpe, 2, ',', '.'),
            'nilai_hpe_angka' => number_format($hpe, 0, ',', '.'),
            'nilai_hpe_terbilang' => Str::ucfirst(IndonesianNumber::spellRupiah($hpe)),
            'nilai_hpe_dpp' => 'Rp '.number_format($hpeDpp, 0, ',', '.'),
            'nilai_hpe_dpp_angka' => number_format($hpeDpp, 0, ',', '.'),
            'nilai_hpe_ppn' => 'Rp '.number_format($hpePpn, 0, ',', '.'),
            'nilai_hpe_ppn_angka' => number_format($hpePpn, 0, ',', '.'),
            'nilai_hpe_total' => 'Rp '.number_format($hpeTotal, 0, ',', '.'),
            'nilai_hpe_total_angka' => number_format($hpeTotal, 0, ',', '.'),
            'nilai_hpe_total_terbilang' => Str::title(IndonesianNumber::spellRupiah($hpeTotal)),
            'nilai_setelah_nego' => $nego === null
                ? '-'
                : 'Rp '.number_format($nego, 2, ',', '.'),
            'nilai_setelah_nego_angka' => $nego === null
                ? '-'
                : number_format($nego, 0, ',', '.'),
            'nilai_setelah_nego_terbilang' => $nego === null
                ? '-'
                : Str::title(IndonesianNumber::spellRupiah($nego)),
            'nilai_setelah_nego_dpp' => $negoDpp === null
                ? '-'
                : 'Rp '.number_format($negoDpp, 0, ',', '.'),
            'nilai_setelah_nego_dpp_angka' => $negoDpp === null
                ? '-'
                : number_format($negoDpp, 0, ',', '.'),
            'nilai_setelah_nego_ppn' => $negoPpn === null
                ? '-'
                : 'Rp '.number_format($negoPpn, 0, ',', '.'),
            'nilai_setelah_nego_ppn_angka' => $negoPpn === null
                ? '-'
                : number_format($negoPpn, 0, ',', '.'),
            'nilai_setelah_nego_total' => $negoTotal === null
                ? '-'
                : 'Rp '.number_format($negoTotal, 0, ',', '.'),
            'nilai_setelah_nego_total_angka' => $negoTotal === null
                ? '-'
                : number_format($negoTotal, 0, ',', '.'),
            'nilai_setelah_nego_total_terbilang' => $negoTotal === null
                ? '-'
                : Str::title(IndonesianNumber::spellRupiah($negoTotal)),
            'tanggal_mulai_pelaksanaan' => $procurement->execution_start_date?->translatedFormat('d F Y') ?? '-',
            'jangka_waktu_hari' => $procurement->execution_duration_days === null
                ? '-'
                : (string) $procurement->execution_duration_days,
            'jangka_waktu_hari_terbilang' => $procurement->execution_duration_days === null
                ? '-'
                : IndonesianNumber::spell((int) $procurement->execution_duration_days),
            'tanggal_selesai_pelaksanaan' => $procurement->executionEndDate()?->translatedFormat('d F Y') ?? '-',
            'masa_garansi_bulan' => $procurement->warranty_months === null
                ? '-'
                : (string) $procurement->warranty_months,
            'masa_garansi_bulan_terbilang' => $procurement->warranty_months === null
                ? '-'
                : IndonesianNumber::spell((int) $procurement->warranty_months),
            'nomor_rekening' => $procurement->bank_account_number ?? '-',
            'nama_bank' => $procurement->bank_name ?? '-',
            'nama_pemilik_rekening' => $procurement->bank_account_holder ?? '-',
            'status_progres' => $procurement->progressStatus->name,
            'pic_perencana' => $procurement->planner_id === null ? '-' : $procurement->planner->name ?? '-',
            'pic_pelaksana' => $procurement->executor_id === null ? '-' : $procurement->executor->name ?? '-',
            'nomor_ba_nego' => $procurement->number,
            'target_penyelesaian' => $procurement->target_completion_date === null
                ? '-'
                : $procurement->target_completion_date->translatedFormat('d F Y'),
            'tanggal_dokumen' => $issuedAt->translatedFormat('d F Y'),
            'tahun' => $issuedAt->format('Y'),
            'tautan_penilaian_vendor' => VendorAssessmentRequest::formUrl(),
            'checklist_perencanaan' => $this->checklistMarkup($procurement, ProcurementStage::Perencanaan),
            'checklist_pelaksanaan' => $this->checklistMarkup($procurement, ProcurementStage::Pelaksanaan),
        ];
    }

    /**
     * Render a template body against a procurement without persisting it.
     */
    public function render(DocumentTemplate $template, Procurement $procurement, ?CarbonInterface $issuedAt = null): string
    {
        $values = $this->placeholderValues($procurement, $issuedAt);

        $body = $this->fillPlaceholders($template->body, $values);

        $typeCode = $template->documentType?->code ?? $template->documentType()->value('code');

        // The Rincian Item, once filled in, draws the item table.
        if ($procurement->contractItems()->exists()) {
            return $this->applyItemTables($body, $typeCode, $procurement);
        }

        if (in_array($typeCode, ['purchase-order', 'surat-pesanan'])) {
            $baNegoDoc = $procurement->documents()
                ->whereHas('documentType', fn ($q) => $q->whereIn('code', ['ba-negosiasi-sppl', 'ba-negosiasi']))
                ->first();

            if ($baNegoDoc !== null) {
                $items = $this->parseNegotiationItems($baNegoDoc->rendered_body);
                if (! empty($items)) {
                    $body = $this->syncItemsIntoSuratPesanan($body, $items, $procurement);
                }
            }
        }

        return $body;
    }

    /**
     * The RKS number derived from a contract number.
     *
     * KDD017.PJ/612/UPKD/2026 becomes 017.RKS-PJ/612/UPKD/2026; a number of
     * another shape is used as it is.
     */
    public function rksNumber(?string $contractNumber): string
    {
        $number = trim((string) $contractNumber);

        if (preg_match('/^[A-Za-z]*(\d+)\.([A-Za-z]+)\/(.+)$/', $number, $parts)) {
            return $parts[1].'.RKS-'.strtoupper($parts[2]).'/'.$parts[3];
        }

        return $number !== '' ? $number : '-';
    }

    /**
     * Placeholders frozen at generation: the issue date and year of a document
     * must not move on every day it is reopened.
     */
    protected const FROZEN_FIELDS = ['tanggal_dokumen', 'tahun'];

    /**
     * Fill the placeholders of a template body.
     *
     * In running text each value is wrapped in a live field
     * (<span data-field data-value>) so the document can follow later changes
     * to the procurement while keeping every hand edit around it. Values
     * inside tags, <style>/<title> blocks, multi-line markup and the frozen
     * fields are written plainly.
     *
     * @param  array<string, string>  $values
     */
    public function fillPlaceholders(string $body, array $values): string
    {
        $parts = preg_split('/(<[^>]*>)/', $body, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $body;
        }

        $rawBlock = null;
        $filled = '';

        foreach ($parts as $part) {
            $isTag = str_starts_with($part, '<');

            if ($isTag && $rawBlock === null && preg_match('/^<(style|script|title|textarea)\b/i', $part, $open)) {
                $rawBlock = strtolower($open[1]);
            } elseif ($isTag && $rawBlock !== null && preg_match('/^<\/'.$rawBlock.'\s*>/i', $part)) {
                $rawBlock = null;
            }

            $live = ! $isTag && $rawBlock === null;

            $filled .= preg_replace_callback(
                '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
                function (array $matches) use ($values, $live): string {
                    $key = strtolower($matches[1]);

                    if (! array_key_exists($key, $values)) {
                        return $matches[0];
                    }

                    $value = (string) $values[$key];

                    if (! $live || in_array($key, self::FROZEN_FIELDS, true) || str_contains($value, '<')) {
                        return $value;
                    }

                    return $this->liveField($key, $value);
                },
                $part,
            ) ?? $part;
        }

        return $filled;
    }

    /**
     * Bring the live fields of a document body up to date.
     *
     * A field is only rewritten while it still reads exactly what the system
     * put there; once someone has typed over it, the hand edit wins.
     *
     * @param  array<string, string>  $values
     */
    public function refreshLiveFields(string $body, array $values): string
    {
        if (! str_contains($body, 'data-field=')) {
            return $body;
        }

        return preg_replace_callback(
            '/<span\b([^>]*\bdata-field="([a-z0-9_]+)"[^>]*)>(.*?)<\/span>/is',
            function (array $matches) use ($values): string {
                [$whole, $attributes, $key, $content] = $matches;

                if (! array_key_exists($key, $values)
                    || ! preg_match('/\bdata-value="([^"]*)"/', $attributes, $stored)) {
                    return $whole;
                }

                $value = (string) $values[$key];

                if ($this->plainText($content) !== $this->plainText($stored[1])
                    || $this->plainText($content) === $this->plainText($value)) {
                    return $whole;
                }

                return $this->liveField($key, $value);
            },
            $body,
        ) ?? $body;
    }

    /**
     * Bring one archived document up to date with the procurement data.
     *
     * An unedited document is simply rendered again from its template; an
     * edited one only has its untouched live fields refreshed. Returns
     * whether the stored body changed.
     *
     * @param  array<string, string>|null  $values
     */
    public function refreshDocument(ProcurementDocument $document, ?array $values = null): bool
    {
        if ($document->document_template_id === null || $this->isFrozen($document)) {
            return false;
        }

        $document->loadMissing(['procurement', 'documentTemplate']);

        $values ??= $this->placeholderValues($document->procurement);
        $unedited = (int) $document->revision === 0;
        $template = $unedited ? $this->currentTemplateFor($document) : $document->documentTemplate;

        // A long document (an RKS runs to 40+ pages) is not rebuilt on every
        // look: the editor polls, so the work is skipped while the data, the
        // template and the document itself are unchanged since the last pass.
        $cacheKey = 'document-live:'.$document->getKey();
        $fingerprint = fn (): string => sha1(implode('|', [
            json_encode($values),
            $template?->getKey(),
            $template?->updated_at?->getTimestamp(),
            $document->updated_at?->getTimestamp(),
            $document->revision,
            strlen($document->rendered_body),
        ]));

        if (Cache::get($cacheKey) === $fingerprint()) {
            return false;
        }

        if ($unedited && $template !== null) {
            $body = $this->renderFor($document, $template, $document->generated_at);
        } else {
            $body = $this->refreshLiveFields($document->rendered_body, $values);
        }

        $switched = $unedited && $template !== null && $template->getKey() !== $document->document_template_id;
        $changed = $body !== $document->rendered_body || $switched;

        if ($changed) {
            $this->storeRebuilt($document, $body, $switched ? $template : null);
        }

        Cache::put($cacheKey, $fingerprint(), now()->addDay());

        return $changed;
    }

    /**
     * Whether a document must no longer follow data or template changes.
     *
     * Once its signed copy has been uploaded, the stored body has to keep
     * matching the paper that was signed.
     */
    public function isFrozen(ProcurementDocument $document): bool
    {
        return $document->relationLoaded('signedUploads')
            ? $document->signedUploads->isNotEmpty()
            : $document->signedUploads()->exists();
    }

    /**
     * The template an unedited document should be built from right now.
     *
     * Nobody has touched an unedited document, so it follows whichever
     * template now fits the procurement best (a newly installed PJ or goods
     * template, say), falling back to the one it was generated with.
     */
    protected function currentTemplateFor(ProcurementDocument $document): ?DocumentTemplate
    {
        // An uploaded document was never built from a template.
        if ($document->document_template_id === null) {
            return null;
        }

        $procurement = $document->procurement;

        return DocumentTemplate::resolveFor(
            $document->document_type_id,
            $procurement->procurement_method_id,
            $procurement->work_category,
            $procurement->contract_number_format_id,
        ) ?? $document->documentTemplate;
    }

    /**
     * Store a rebuilt body, moving the document onto a new template if given.
     */
    protected function storeRebuilt(ProcurementDocument $document, string $body, ?DocumentTemplate $template): void
    {
        $document->rendered_body = $body;

        if ($template !== null) {
            $document->document_template_id = $template->id;
            $document->template_version = $template->version;
            $document->setRelation('documentTemplate', $template);
        }

        $document->save();
    }

    /**
     * The markup of one live field.
     */
    protected function liveField(string $key, string $value): string
    {
        $stored = htmlspecialchars($this->plainText($value), ENT_QUOTES, 'UTF-8');

        return '<span data-field="'.$key.'" data-value="'.$stored.'">'.$value.'</span>';
    }

    /**
     * The visible text of a fragment, for comparing field contents.
     */
    protected function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $text)));
    }

    /**
     * Render a template for an archived document: an amendment document
     * also takes the values of its amendment.
     */
    protected function renderFor(ProcurementDocument $document, DocumentTemplate $template, ?CarbonInterface $issuedAt = null): string
    {
        // A Delivery Order document also takes the values of its DO.
        $order = $document->procurement_delivery_order_id === null
            ? null
            : ProcurementDeliveryOrder::query()->find($document->procurement_delivery_order_id);

        if ($order !== null) {
            return $this->renderDeliveryOrder($template, $order, $issuedAt);
        }

        $amendment = $document->procurement_amendment_id === null
            ? null
            : ProcurementAmendment::query()->find($document->procurement_amendment_id);

        return $amendment === null
            ? $this->render($template, $document->procurement, $issuedAt)
            : $this->renderAmendment($template, $amendment, $issuedAt);
    }

    /**
     * Render an amendment template: the procurement's values and the
     * amendment's own.
     */
    public function renderAmendment(DocumentTemplate $template, ProcurementAmendment $amendment, ?CarbonInterface $issuedAt = null): string
    {
        $values = [
            ...$this->placeholderValues($amendment->procurement, $issuedAt),
            ...$this->amendmentValues($amendment),
        ];

        return $this->fillPlaceholders($template->body, $values);
    }

    /**
     * The placeholder values of an amendment.
     *
     * The articles typed in by the proposer are escaped before they become
     * part of the document.
     *
     * @return array<string, string>
     */
    public function amendmentValues(ProcurementAmendment $amendment): array
    {
        $amendment->loadMissing(['procurement.contractNumberFormat', 'changedArticles', 'files']);
        $procurement = $amendment->procurement;
        $date = $amendment->amendment_date ?? CarbonImmutable::now();
        $format = $procurement->contractNumberFormat?->code;

        $changes = $amendment->changedArticles->values()->map(
            fn (ProcurementAmendmentChange $change): string => '<li><p class="ketentuan">Mengubah ketentuan '.$this->amendedArticle($change->article).' :</p>'
                .'<p class="label"><b>Semula Berbunyi :</b></p><div class="isi">'.$this->amendmentText($change->original_text).'</div>'
                .'<p class="label"><b>Menjadi :</b></p><div class="isi">'.$this->amendmentText($change->new_text).'</div></li>',
        )->implode('');

        return [
            'amandemen_ke' => $amendment->roman(),
            'amandemen_ke_angka' => (string) $amendment->sequence,
            'nomor_amandemen' => $amendment->number ?: '-',
            'jenis_dokumen_kontrak' => match ($format) {
                'PJ' => 'SURAT PERJANJIAN',
                'SPPL' => 'SURAT PESANAN',
                default => 'SURAT PERINTAH KERJA',
            },
            'jenis_dokumen_kontrak_judul' => match ($format) {
                'PJ' => 'Surat Perjanjian',
                'SPPL' => 'Surat Pesanan',
                default => 'Surat Perintah Kerja',
            },
            'nomor_kontrak' => $procurement->number,
            'tanggal_kontrak' => $amendment->contract_date?->translatedFormat('d F Y') ?? '-',
            'tanggal_amandemen' => $date->translatedFormat('d F Y'),
            'tanggal_amandemen_angka' => $date->format('d-m-Y'),
            'hari_amandemen' => $date->translatedFormat('l'),
            'tanggal_amandemen_terbilang' => Str::title(IndonesianNumber::spell((int) $date->format('j'))),
            'bulan_amandemen' => $date->translatedFormat('F'),
            'tahun_amandemen_terbilang' => Str::title(IndonesianNumber::spell((int) $date->format('Y'))),
            'tanggal_notulen' => $amendment->meeting_date?->translatedFormat('d F Y') ?? '-',
            'nomor_nota_dinas_amandemen' => $amendment->memo_number ?? '-',
            'tanggal_nota_dinas_amandemen' => $amendment->memo_date?->translatedFormat('d F Y') ?? '-',
            'nomor_surat_permohonan_amandemen' => $amendment->request_number ?? '-',
            'tanggal_surat_permohonan_amandemen' => $amendment->request_date?->translatedFormat('d F Y') ?? '-',
            'daftar_perubahan' => $changes === '' ? '<li>-</li>' : $changes,
            // The partner's request letter is optional: cited only when filed.
            'butir_surat_permohonan' => $amendment->files->contains('kind', AmendmentFileKind::SuratPermohonan)
                ? '<li>Surat Permohonan Amandemen dari '.e((string) ($procurement->partner_name ?: 'PIHAK KEDUA')).'.</li>'
                : '',
        ];
    }

    /**
     * The article an amendment changes, as the amendment cites it: the
     * article in bold capitals, its ayat after it ("PASAL 8 HARGA PEKERJAAN
     * Ayat 1"). Escaped, being typed in.
     */
    protected function amendedArticle(string $article): string
    {
        if (preg_match('/^(.*?)(\s+Ayat\s+\S+)$/iu', trim($article), $match) === 1) {
            return '<b>'.e(Str::upper($match[1])).'</b>'.e($match[2]);
        }

        return '<b>'.e(Str::upper(trim($article))).'</b>';
    }

    /**
     * The wording of an article in an amendment, laid out as in the
     * contract: a numbered ayat ("1. ...") with its text hanging beside the
     * number, its list items indented under it ("   a. ..."). Each line is
     * escaped, being typed in.
     */
    protected function amendmentText(string $text): string
    {
        $rows = '';
        $numbered = false;

        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            if (trim($raw) === '') {
                continue;
            }

            $indented = preg_match('/^\s+/u', $raw) === 1;
            $line = trim($raw);
            $marker = null;

            if (preg_match('/^((?:\d+|[a-z]|[ivxlc]+)\.|\(\d+\)|-)\s+(.*)$/u', $line, $match) === 1) {
                [$marker, $line] = [$match[1], $match[2]];
            }

            $body = e($line);

            if ($indented) {
                $rows .= '<tr><td class="n"></td>'.($marker === null
                    ? '<td colspan="2">'.$body.'</td>'
                    : '<td class="n">'.e($marker).'</td><td>'.$body.'</td>').'</tr>';

                continue;
            }

            if ($marker !== null) {
                $numbered = true;
                $rows .= '<tr><td class="n">'.e($marker).'</td><td colspan="2">'.$body.'</td></tr>';

                continue;
            }

            // A plain line: under the ayat's text when it is numbered.
            $rows .= $numbered
                ? '<tr><td class="n"></td><td colspan="2">'.$body.'</td></tr>'
                : '<tr><td colspan="3">'.$body.'</td></tr>';
        }

        return $rows === '' ? '-' : '<table class="teks">'.$rows.'</table>';
    }

    /**
     * Draw (or redraw) the document of an amendment from its template.
     *
     * Redrawn while it is unsigned, so the latest wording of the request is
     * what the approvers read; a signed one stays as it was signed.
     */
    public function generateAmendment(ProcurementAmendment $amendment, User $author): ProcurementDocument
    {
        $amendment->loadMissing('procurement');
        $procurement = $amendment->procurement;
        $documentType = DocumentType::query()->where('code', 'amandemen-kontrak')->first();

        $template = $documentType === null ? null : DocumentTemplate::resolveFor(
            $documentType->id,
            $procurement->procurement_method_id,
            $procurement->work_category,
            $procurement->contract_number_format_id,
        );

        if ($documentType === null || $template === null) {
            throw new RuntimeException('Template aktif untuk dokumen Amandemen Kontrak belum tersedia.');
        }

        $document = $amendment->procurement_document_id === null
            ? null
            : ProcurementDocument::query()->find($amendment->procurement_document_id);

        if ($document !== null && $this->isFrozen($document)) {
            return $document;
        }

        $title = "Amandemen {$amendment->roman()} - {$procurement->name}";
        $values = [
            'document_type_id' => $documentType->id,
            'document_template_id' => $template->id,
            'title' => $title,
            'file_name' => Str::slug("{$procurement->number}-amandemen-{$amendment->sequence}").'.html',
            'template_version' => $template->version,
            'rendered_body' => $this->renderAmendment($template, $amendment),
            'revision' => 0,
            'edited_by' => null,
            'edited_at' => null,
            'generated_by' => $author->id,
            'generated_at' => now(),
        ];

        if ($document === null) {
            $document = new ProcurementDocument($values);
            $document->procurement_id = $procurement->id;
            $document->procurement_amendment_id = $amendment->id;
            $document->save();

            $amendment->forceFill(['procurement_document_id' => $document->id])->save();
        } else {
            $document->forceFill($values)->save();
        }

        $this->procurements->recordActivity(
            $procurement,
            $author,
            ActivityType::DokumenDigenerate,
            "Dokumen {$title} digenerate.",
            ['document_type' => 'amandemen-kontrak', 'amendment_id' => $amendment->id],
        );

        return $document;
    }

    /**
     * Render a Delivery Order template: the procurement's values, the
     * contract it is called off under, and the DO's own.
     */
    public function renderDeliveryOrder(DocumentTemplate $template, ProcurementDeliveryOrder $order, ?CarbonInterface $issuedAt = null): string
    {
        return $this->fillPlaceholders($template->body, [
            ...$this->placeholderValues($order->procurement, $issuedAt),
            ...$this->deliveryOrderValues($order),
        ]);
    }

    /**
     * The placeholder values of a Delivery Order.
     *
     * Everything typed in by the proposer is escaped before it becomes part
     * of the document. Lines sharing a heading are listed under it (A, B,
     * ...), numbered from one within each heading.
     *
     * @return array<string, string>
     */
    public function deliveryOrderValues(ProcurementDeliveryOrder $order): array
    {
        $order->loadMissing(['procurement.contractNumberFormat', 'items']);
        $procurement = $order->procurement;
        $format = $procurement->contractNumberFormat?->code;
        $money = fn (float $value): string => number_format($value, 0, ',', '.');
        $quantity = fn (string $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $cell = 'border:1px solid #000;padding:2px 4px;';
        $blank = '<td style="'.$cell.'"></td>';

        $rows = '';
        $groups = $order->items->groupBy(fn (ProcurementDeliveryOrderItem $item): string => (string) $item->group_name);
        $letter = 'A';

        foreach ($groups as $heading => $items) {
            $heading = (string) $heading;

            if ($heading !== '') {
                if ($rows !== '') {
                    $rows .= '<tr><td style="'.$cell.'height:12px;"></td>'.str_repeat($blank, 6).'</tr>';
                }

                $rows .= '<tr><td style="'.$cell.'text-align:center;font-weight:bold;">'.$letter.'</td>'
                    .'<td colspan="6" style="'.$cell.'font-weight:bold;">'.e($heading).'</td></tr>';
                $letter++;
            }

            foreach ($items->values() as $index => $item) {
                $rows .= '<tr>'
                    .'<td style="'.$cell.'text-align:center;">'.($index + 1).'</td>'
                    .'<td style="'.$cell.'">'.e($item->description).'</td>'
                    .'<td style="'.$cell.'text-align:center;">'.e($item->part_number ?? '').'</td>'
                    .'<td style="'.$cell.'text-align:center;">'.$quantity($item->quantity).'</td>'
                    .'<td style="'.$cell.'text-align:center;">'.e($item->unit).'</td>'
                    .'<td style="'.$cell.'text-align:right;">'.$money((float) $item->unit_price).'</td>'
                    .'<td style="'.$cell.'text-align:right;">'.$money($item->total()).'</td>'
                    .'</tr>';
            }
        }

        $total = $order->total();
        $days = $order->delivery_days;

        return [
            'nomor_do' => $order->number ?: '-',
            'tanggal_do' => $order->order_date?->translatedFormat('d F Y') ?? '-',
            // The DO's own code, else the partner's from the calon mitra.
            'kode_rekanan' => $order->vendor_code ?: ($procurement->partner_vendor_code ?: '-'),
            'uraian_pekerjaan_do' => $order->work_description ?: $procurement->name,
            'tempat_penyerahan_do' => $order->delivery_place ?: '-',
            'waktu_penyerahan_do' => $days === null ? '-' : (string) $days,
            'waktu_penyerahan_do_terbilang' => $days === null ? '-' : IndonesianNumber::spell($days),
            'rincian_do' => $rows === '' ? '<tr><td colspan="7" style="'.$cell.'text-align:center;">-</td></tr>' : $rows,
            'total_do' => $money($total),
            'total_do_terbilang' => Str::title(IndonesianNumber::spellRupiah($total)),
            'nomor_nota_dinas_usulan_do' => $order->request_memo_number ?: '-',
            'tanggal_nota_dinas_usulan_do' => $order->request_memo_date?->translatedFormat('d F Y') ?? '-',
            'nomor_nota_dinas_do' => $order->do_memo_number ?: '-',
            'tanggal_nota_dinas_do' => $order->do_memo_date?->translatedFormat('d F Y') ?? '-',
            // The contract the DO is called off under.
            'nomor_kontrak' => $procurement->number,
            'tanggal_kontrak' => $order->contract_date?->translatedFormat('d F Y') ?? '-',
            'jenis_dokumen_kontrak_judul' => $format === 'PJ' ? 'Surat Perjanjian' : 'Surat Perintah Kerja',
        ];
    }

    /**
     * Draw (or redraw) the document of a Delivery Order from its template.
     *
     * Redrawn while it is unsigned, so the latest lines are what the
     * approvers read; a signed one stays as it was signed.
     */
    public function generateDeliveryOrder(ProcurementDeliveryOrder $order, User $author): ProcurementDocument
    {
        $order->loadMissing('procurement');
        $procurement = $order->procurement;
        $documentType = DocumentType::query()->where('code', 'delivery-order')->first();

        $template = $documentType === null ? null : DocumentTemplate::resolveFor(
            $documentType->id,
            $procurement->procurement_method_id,
            $procurement->work_category,
            $procurement->contract_number_format_id,
        );

        if ($documentType === null || $template === null) {
            throw new RuntimeException('Template aktif untuk dokumen Delivery Order (DO) belum tersedia.');
        }

        $document = $order->procurement_document_id === null
            ? null
            : ProcurementDocument::query()->find($order->procurement_document_id);

        if ($document !== null && $this->isFrozen($document)) {
            return $document;
        }

        $title = "Delivery Order {$order->number} - {$procurement->name}";
        $values = [
            'document_type_id' => $documentType->id,
            'document_template_id' => $template->id,
            'title' => Str::limit($title, 250, ''),
            'file_name' => Str::slug("{$procurement->number}-delivery-order-{$order->sequence}").'.html',
            'template_version' => $template->version,
            'rendered_body' => $this->renderDeliveryOrder($template, $order),
            'revision' => 0,
            'edited_by' => null,
            'edited_at' => null,
            'generated_by' => $author->id,
            'generated_at' => now(),
        ];

        if ($document === null) {
            $document = new ProcurementDocument($values);
            $document->procurement_id = $procurement->id;
            $document->procurement_delivery_order_id = $order->id;
            $document->save();

            $order->forceFill(['procurement_document_id' => $document->id])->save();
        } else {
            $document->forceFill($values)->save();
        }

        $this->procurements->recordActivity(
            $procurement,
            $author,
            ActivityType::DokumenDigenerate,
            "Dokumen {$title} digenerate.",
            ['document_type' => 'delivery-order', 'delivery_order_id' => $order->id],
        );

        return $document;
    }

    /**
     * Generate and archive a document for a procurement.
     */
    public function generate(Procurement $procurement, DocumentType $documentType, User $author): ProcurementDocument
    {
        $template = DocumentTemplate::resolveFor(
            $documentType->id,
            $procurement->procurement_method_id,
            $procurement->work_category,
            $procurement->contract_number_format_id,
        );

        if ($template === null) {
            throw new RuntimeException("Template aktif untuk dokumen {$documentType->name} belum tersedia.");
        }

        $document = $procurement->documents()->create([
            'document_type_id' => $documentType->id,
            'document_template_id' => $template->id,
            'title' => "{$documentType->nameFor($procurement)} - {$procurement->name}",
            'file_name' => Str::slug("{$procurement->number}-{$documentType->code}").'.html',
            'template_version' => $template->version,
            'rendered_body' => $this->render($template, $procurement),
            'generated_by' => $author->id,
            'generated_at' => now(),
        ]);

        $this->procurements->recordActivity(
            $procurement,
            $author,
            ActivityType::DokumenDigenerate,
            "Dokumen {$documentType->name} digenerate.",
            ['document_type' => $documentType->code, 'template_version' => $template->version],
        );

        return $document;
    }

    /**
     * Save a hand corrected body onto an archived document.
     *
     * The body is the document: whatever an authorised user leaves here is
     * what gets printed, so the template is not consulted again.
     */
    public function saveEdit(
        ProcurementDocument $document,
        User $editor,
        string $title,
        string $body,
    ): ProcurementDocument {
        $document->title = $title;
        $document->rendered_body = $body;
        $document->revision = $document->revision + 1;
        $document->edited_by = $editor->id;
        $document->edited_at = now();
        $document->save();

        $document->loadMissing('documentType');
        if (in_array($document->documentType?->code, ['ba-negosiasi-sppl', 'ba-negosiasi'])) {
            $this->syncNegotiationToProcurementAndSuratPesanan($document->procurement, $body);
        }

        $this->procurements->recordActivity(
            $document->procurement,
            $editor,
            ActivityType::DokumenDiedit,
            "Dokumen {$document->title} diperbaiki (revisi {$document->revision}).",
            ['document_id' => $document->id, 'revision' => $document->revision],
        );

        return $document;
    }

    /**
     * Synchronize negotiation items and totals from BA Negosiasi to the procurement
     * and any existing Surat Pesanan document.
     */
    public function syncNegotiationToProcurementAndSuratPesanan(Procurement $procurement, string $baNegoBody): void
    {
        $items = $this->parseNegotiationItems($baNegoBody);
        if (empty($items)) {
            return;
        }

        // The table typed in the editor becomes the Rincian Item, and every
        // item table follows it.
        $this->importNegotiationItems($procurement, $items);
        $this->syncItemDocuments($procurement);
    }

    /**
     * Draw the item table of a document body from the Rincian Item: the
     * Surat Pesanan's rows, or the table the renderer knows for the type.
     * Unsaved items may be given, to preview the table they make.
     *
     * @param  Collection<int, ProcurementContractItem>|null  $items
     */
    public function applyItemTables(string $body, ?string $typeCode, Procurement $procurement, ?Collection $items = null): string
    {
        $items ??= $procurement->contractItems()->get();

        if ($items->isEmpty()) {
            return $body;
        }

        if (in_array($typeCode, ['purchase-order', 'surat-pesanan'], true)) {
            // Only once the prices after negotiation are known.
            if ($items->every(fn (ProcurementContractItem $item): bool => $item->unit_price === null)) {
                return $body;
            }

            return $this->syncItemsIntoSuratPesanan($body, $this->suratPesananItems($items), $procurement);
        }

        return $this->itemTables->apply($body, $typeCode, $items);
    }

    /**
     * Whether a document holds an item table drawn from the Rincian Item.
     */
    public function hasItemTable(ProcurementDocument $document): bool
    {
        $code = $document->documentType->code;

        return in_array($code, self::ITEM_TABLE_TYPES, true)
            && $this->itemTables->hasTable($document->rendered_body, $code);
    }

    /**
     * Bring every item table of a procurement's documents in line with its
     * Rincian Item, and the value after negotiation with its total.
     */
    public function syncItemDocuments(Procurement $procurement): void
    {
        $items = $procurement->contractItems()->get();

        if ($items->isEmpty()) {
            return;
        }

        if ($items->contains(fn (ProcurementContractItem $item): bool => $item->unit_price !== null)) {
            $totalAfter = $items->sum(fn (ProcurementContractItem $item): float => $item->totalAfter());

            if ($totalAfter > 0 && (float) $procurement->value_after_negotiation !== $totalAfter) {
                $procurement->value_after_negotiation = (string) $totalAfter;
                $procurement->save();
            }
        }

        $documents = $procurement->documents()
            ->with('documentType')
            ->whereHas('documentType', fn ($query) => $query->whereIn('code', self::ITEM_TABLE_TYPES))
            ->get();

        foreach ($documents as $document) {
            $body = $this->applyItemTables($document->rendered_body, $document->documentType->code, $procurement);

            if ($body !== $document->rendered_body) {
                $document->rendered_body = $body;
                $document->save();
            }
        }
    }

    /**
     * The Rincian Item as the Surat Pesanan's rows.
     *
     * @param  Collection<int, ProcurementContractItem>  $items
     * @return array<int, array{no: int, name: string, volume: string, satuan: string, price_before: float, total_before: float, price_after: float, total_after: float, group: string|null}>
     */
    protected function suratPesananItems(Collection $items): array
    {
        return $items->sortBy('sort_order')->values()->map(fn (ProcurementContractItem $item, int $index): array => [
            'no' => $index + 1,
            'name' => e($item->description),
            'volume' => rtrim(rtrim(number_format((float) $item->volume, 3, ',', '.'), '0'), ','),
            'satuan' => e($item->unit),
            'price_before' => (float) $item->price_before,
            'total_before' => $item->totalBefore(),
            'price_after' => (float) $item->unit_price,
            'total_after' => $item->totalAfter(),
            'group' => filled($item->group_name) ? trim((string) $item->group_name) : null,
        ])->all();
    }

    /**
     * Take the items read from a BA Negosiasi table as the Rincian Item.
     *
     * The lines keep their place: the n-th line read updates the n-th item
     * (so a Delivery Order line still points at it and its part number
     * stays), new lines are added and lines no longer there are removed.
     *
     * @param  array<int, array{no: int, name: string, volume: string, satuan: string, price_before: float, total_before: float, price_after: float, total_after: float, group?: string|null}>  $parsed
     */
    public function importNegotiationItems(Procurement $procurement, array $parsed): void
    {
        $existing = $procurement->contractItems()->get()->values();
        $kept = [];

        foreach (array_values($parsed) as $index => $line) {
            $volume = (float) str_replace(',', '.', (string) preg_replace('/[^0-9.,]/', '', $line['volume']));

            $values = [
                'sort_order' => $index + 1,
                'group_name' => $line['group'] ?? null,
                'description' => mb_substr($line['name'], 0, 500),
                'volume' => $volume > 0 ? $volume : 1,
                'unit' => mb_substr($line['satuan'], 0, 50),
                'price_before' => $line['price_before'] > 0 ? $line['price_before'] : null,
                'unit_price' => $line['price_after'] > 0 ? $line['price_after'] : null,
            ];

            $item = $existing->get($index);

            if ($item instanceof ProcurementContractItem) {
                $item->update($values);
                $kept[] = $item->id;
            } else {
                $kept[] = $procurement->contractItems()->create($values)->id;
            }
        }

        $procurement->contractItems()->whereNotIn('id', $kept)->delete();
    }

    /**
     * Parse item rows from a BA Negosiasi rendered HTML body.
     *
     * A row with no price at all is a group heading ("Material & Tools
     * Tangki 50 KL"), even when a volume was typed into it: it names the
     * items below it and is never an item of its own.
     *
     * @return array<int, array{no: int, name: string, volume: string, satuan: string, price_before: float, total_before: float, price_after: float, total_after: float, group: string|null}>
     */
    public function parseNegotiationItems(string $html): array
    {
        if (! str_contains($html, 'HARGA SEBELUM NEGO') && ! str_contains($html, 'TOTAL HARGA')) {
            return [];
        }

        if (! preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rowMatches)) {
            return [];
        }

        $items = [];
        $itemIndex = 1;
        $group = null;

        foreach ($rowMatches[1] as $rowContent) {
            if (! preg_match_all('/<td([^>]*)>(.*?)<\/td>/is', $rowContent, $tdMatches)) {
                continue;
            }

            $attrs = $tdMatches[1];
            $cells = $tdMatches[2];

            // Item rows have exactly 8 <td> elements and no colspan
            if (count($cells) !== 8) {
                continue;
            }

            $hasColspan = false;
            foreach ($attrs as $attr) {
                if (stripos($attr, 'colspan') !== false) {
                    $hasColspan = true;
                    break;
                }
            }

            if ($hasColspan) {
                continue;
            }

            // A line break typed inside the cell separates words, not glues them.
            $nameHtml = (string) preg_replace('/<br\s*\/?>|<\/(p|div)>/i', ' ', $cells[1]);
            $name = trim(html_entity_decode(strip_tags($nameHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $name = trim((string) preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $name)));

            // No price in any of the four price columns: a group heading
            // ("A | Fabrikasi Material", or one typed with a volume of 1 and
            // prices of 0) or a blank spacer row. Layout, not an item.
            $hasPrice = collect([4, 5, 6, 7])->contains(
                fn (int $index): bool => trim((string) preg_replace('/[^0-9]/', '', strip_tags($cells[$index])), '0') !== '',
            );

            if (! $hasPrice) {
                if ($name !== '' && $name !== '&nbsp;') {
                    $group = $name;
                }

                continue;
            }

            if ($name === '' || $name === '&nbsp;') {
                $name = 'Item Pengadaan '.$itemIndex;
            }

            $volRaw = trim(strip_tags($cells[2]));
            $volDigits = preg_replace('/[^0-9.,]/', '', $volRaw);
            $vol = (float) str_replace(',', '.', $volDigits);
            if ($vol <= 0) {
                $vol = 1.0;
            }

            $satuan = trim(html_entity_decode(strip_tags($cells[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $satuan = trim(str_replace("\xc2\xa0", ' ', $satuan));
            if ($satuan === '' || $satuan === '&nbsp;') {
                $satuan = 'Lot';
            }

            $priceBeforeDigits = preg_replace('/[^0-9]/', '', strip_tags($cells[4]));
            $priceBefore = (float) ($priceBeforeDigits !== '' ? $priceBeforeDigits : 0);

            $totalBeforeDigits = preg_replace('/[^0-9]/', '', strip_tags($cells[5]));
            $totalBefore = (float) ($totalBeforeDigits !== '' ? $totalBeforeDigits : ($vol * $priceBefore));

            $priceAfterDigits = preg_replace('/[^0-9]/', '', strip_tags($cells[6]));
            $priceAfter = (float) ($priceAfterDigits !== '' ? $priceAfterDigits : 0);

            $totalAfterDigits = preg_replace('/[^0-9]/', '', strip_tags($cells[7]));
            $totalAfter = (float) ($totalAfterDigits !== '' ? $totalAfterDigits : ($vol * $priceAfter));

            $items[] = [
                'no' => $itemIndex++,
                'name' => $name,
                'volume' => $volRaw !== '' && $volRaw !== '&nbsp;' ? $volRaw : (string) $vol,
                'satuan' => $satuan,
                'price_before' => $priceBefore,
                'total_before' => $totalBefore,
                'price_after' => $priceAfter,
                'total_after' => $totalAfter,
                'group' => $group,
            ];
        }

        return $items;
    }

    /**
     * Generate the tbody HTML for Surat Pesanan item table with multiple items and totals.
     *
     * @param  array<int, array{no: int, name: string, volume: string, satuan: string, price_before: float, total_before: float, price_after: float, total_after: float, group?: string|null}>  $items
     */
    public function generateSuratPesananTbody(array $items, Procurement $procurement, ?string $existingDeadline = null): string
    {
        $deadline = $existingDeadline ?: ($procurement->execution_start_date && $procurement->execution_duration_days
            ? $procurement->execution_start_date->copy()->addDays($procurement->execution_duration_days - 1)->translatedFormat('d F Y')
            : '{{tanggal_selesai_pelaksanaan}}');

        $prkDisplay = ! empty(trim((string) $procurement->prk_number))
            ? trim((string) $procurement->prk_number)
            : '-';

        // A group heading row (A, B, …) opens each named group; its items are
        // numbered from 1 again, and the deadline spans every row.
        $rows = [];
        $group = null;
        $letter = 'A';
        $number = 0;

        foreach ($items as $item) {
            $itemGroup = $item['group'] ?? null;

            if ($itemGroup !== null && $itemGroup !== $group) {
                $rows[] = ['heading' => true, 'letter' => (string) $letter++, 'name' => $itemGroup, 'no' => 0, 'item' => null];
                $number = 0;
            }

            $group = $itemGroup;
            $rows[] = ['heading' => false, 'letter' => '', 'name' => '', 'no' => $group === null ? $item['no'] : ++$number, 'item' => $item];
        }

        $rowCount = count($rows);
        $totalAfter = 0;
        $itemRowsHtml = '';
        $cell = 'border: 1px solid #000; vertical-align: middle;';

        foreach ($rows as $index => $row) {
            $deadlineTd = '';
            if ($index === 0) {
                $rowspan = $rowCount > 1 ? " rowspan=\"{$rowCount}\"" : '';
                $deadlineTd = "\n                        <td{$rowspan} style=\"border: 1px solid #000; padding: 8px 6px; text-align: center; vertical-align: middle; font-weight: bold;\">{$deadline}</td>";
            }

            $item = $row['item'];

            if ($item === null) {
                $name = e($row['name']);
                $itemRowsHtml .= <<<HTML

                    <tr>
                        <td style="{$cell} padding: 8px 4px; text-align: center; font-weight: bold;">{$row['letter']}</td>
                        <td colspan="5" style="{$cell} padding: 8px 6px; font-weight: bold;">{$name}</td>{$deadlineTd}
                    </tr>
HTML;

                continue;
            }

            $priceFormatted = number_format($item['price_after'], 0, ',', '.');
            $totalFormatted = number_format($item['total_after'], 0, ',', '.');
            $totalAfter += $item['total_after'];

            $itemRowsHtml .= <<<HTML

                    <tr>
                        <td style="border: 1px solid #000; padding: 8px 4px; text-align: center; vertical-align: middle;">{$row['no']}</td>
                        <td style="border: 1px solid #000; padding: 8px 6px; vertical-align: middle;">{$item['name']}</td>
                        <td style="border: 1px solid #000; padding: 8px 4px; text-align: center; vertical-align: middle;">{$item['volume']}</td>
                        <td style="border: 1px solid #000; padding: 8px 4px; text-align: center; vertical-align: middle;">{$item['satuan']}</td>
                        <td style="border: 1px solid #000; padding: 8px 6px; text-align: right; vertical-align: middle;">Rp {$priceFormatted}</td>
                        <td style="border: 1px solid #000; padding: 8px 6px; text-align: right; vertical-align: middle;">Rp {$totalFormatted}</td>{$deadlineTd}
                    </tr>
HTML;
        }

        $totalAfterFormatted = number_format($totalAfter, 0, ',', '.');
        $terbilang = IndonesianNumber::spellRupiah($totalAfter);

        return <<<HTML
<tbody>{$itemRowsHtml}
                    <tr>
                        <td colspan="7" style="border: 1px solid #000; padding: 6px 8px; font-size: 8.5pt;">
                            <b>NOTE :</b> {$prkDisplay}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" style="border: 1px solid #000; padding: 6px 8px; vertical-align: middle; font-size: 8pt; line-height: 1.35;">
                            <b>PERHATIAN :</b><br>
                            Harga adalah sebelum Pajak Pertambahan Nilai (PPN)
                        </td>
                        <td style="border: 1px solid #000; padding: 6px 8px; text-align: center; vertical-align: middle; font-weight: bold;">TOTAL</td>
                        <td style="border: 1px solid #000; padding: 6px 8px; text-align: right; vertical-align: middle; font-weight: bold;">Rp {$totalAfterFormatted}</td>
                        <td style="border: 1px solid #000; padding: 6px 8px; background-color: #fafafa;"></td>
                    </tr>
                    <tr>
                        <td colspan="7" style="border: 1px solid #000; padding: 6px 8px; font-weight: bold; font-size: 8.5pt;">
                            Terbilang : <span style="font-weight: normal; font-style: italic;">{$terbilang}</span>
                        </td>
                    </tr>
                </tbody>
HTML;
    }

    /**
     * Synchronize items into an existing or template Surat Pesanan document body.
     *
     * @param  array<int, array{no: int, name: string, volume: string, satuan: string, price_before: float, total_before: float, price_after: float, total_after: float, group?: string|null}>  $items
     */
    public function syncItemsIntoSuratPesanan(string $suratPesananHtml, array $items, Procurement $procurement): string
    {
        if (empty($items)) {
            return $suratPesananHtml;
        }

        // Try to extract existing deadline from the first item row if already formatted with date
        $existingDeadline = null;
        if (preg_match('/<td[^>]*font-weight:\s*bold;?[^>]*>\s*(?:<span[^>]*>)?\s*([0-9]{1,2}\s+[A-Za-z]+\s+[0-9]{4})\s*(?:<\/span>)?\s*<\/td>/i', $suratPesananHtml, $m)) {
            $existingDeadline = trim($m[1]);
        }

        $newTbody = $this->generateSuratPesananTbody($items, $procurement, $existingDeadline);

        // Replace the tbody that contains PERHATIAN in Surat Pesanan
        $pattern = '/<tbody>(?:(?!<\/tbody>).)*?PERHATIAN\s*:.*?<\/tbody>/is';
        if (preg_match($pattern, $suratPesananHtml)) {
            $suratPesananHtml = preg_replace($pattern, $newTbody, $suratPesananHtml);
        }

        // Also clean up thead so column headers never wrap into cut words (e.g. NOMO R, VOLU ME, SATUA N)
        $headerPattern = '/<thead>.*?<\/thead>/is';
        $colItemHeader = str_contains($suratPesananHtml, 'URAIAN PEKERJAAN JASA')
            ? 'URAIAN PEKERJAAN JASA /<br>SPESIFIKASI'
            : 'NAMA BARANG SPESIFIKASI/<br>PART NUMBER';
        $colDeadlineHeader = str_contains($suratPesananHtml, 'PENYELESAIAN')
            ? 'BATAS WAKTU /<br>PENYELESAIAN<br>JASA'
            : 'BATAS WAKTU /<br>PENYERAHAN<br>BARANG / JASA';

        $cleanThead = <<<HTML
<thead>
                    <tr style="text-align: center; font-weight: bold; background-color: #ffffff;">
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 4%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">NO</th>
                        <th style="border: 1px solid #000; padding: 6px 6px; width: 35%; vertical-align: middle;">{$colItemHeader}</th>
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 8%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">VOLUME</th>
                        <th style="border: 1px solid #000; padding: 6px 2px; width: 8%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">SATUAN</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 14%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">HARGA SATUAN</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 15%; vertical-align: middle; white-space: nowrap; font-size: 8pt;">JUMLAH HARGA</th>
                        <th style="border: 1px solid #000; padding: 6px 4px; width: 16%; vertical-align: middle; font-size: 7.5pt; line-height: 1.25;">{$colDeadlineHeader}</th>
                    </tr>
                </thead>
HTML;

        if (preg_match($headerPattern, $suratPesananHtml)) {
            $suratPesananHtml = preg_replace($headerPattern, $cleanThead, $suratPesananHtml);
        }

        // Fixed layout makes the browser and the PDF keep the column widths
        // above (a narrow NO column) instead of sharing out the spare room.
        $suratPesananHtml = (string) preg_replace_callback(
            '/<table([^>]*?)style="([^"]*)"([^>]*)>(\s*<thead>)/i',
            fn (array $match): string => str_contains($match[2], 'table-layout')
                ? $match[0]
                : '<table'.$match[1].'style="'.rtrim($match[2], '; ').'; table-layout: fixed;"'.$match[3].'>'.$match[4],
            $suratPesananHtml,
        );

        return $suratPesananHtml;
    }

    /**
     * Rebuild a document from its template using the current procurement data.
     *
     * The escape hatch for the other half of the problem: when the wording was
     * fine but the data pulled into the document was wrong, fix the
     * procurement and pull the values through again. Manual corrections made
     * since the last generate are discarded, which is the point.
     */
    public function regenerate(ProcurementDocument $document, User $editor): ProcurementDocument
    {
        $document->loadMissing(['procurement', 'documentType']);

        $template = DocumentTemplate::resolveFor(
            $document->document_type_id,
            $document->procurement->procurement_method_id,
            $document->procurement->work_category,
            $document->procurement->contract_number_format_id,
        );

        if ($template === null) {
            throw new RuntimeException(
                "Template aktif untuk dokumen {$document->documentType->name} belum tersedia."
            );
        }

        $document->document_template_id = $template->id;
        $document->template_version = $template->version;
        $document->rendered_body = $this->renderFor($document, $template);
        $document->revision = 0;
        $document->edited_by = null;
        $document->edited_at = null;
        $document->generated_by = $editor->id;
        $document->generated_at = now();
        $document->save();

        $this->procurements->recordActivity(
            $document->procurement,
            $editor,
            ActivityType::DokumenDigenerate,
            "Dokumen {$document->title} dimuat ulang dari template.",
            ['document_id' => $document->id, 'template_version' => $template->version],
        );

        return $document;
    }

    /**
     * Synchronize existing generated documents when procurement fields change,
     * without requiring the user to manually trigger "Muat Ulang dari Template".
     *
     * @param  array<string, string>  $oldPlaceholders
     */
    public function syncDocumentsOnProcurementUpdate(Procurement $procurement, array $oldPlaceholders): void
    {
        $procurement->refresh();
        $newPlaceholders = $this->placeholderValues($procurement);
        $documents = $procurement->documents()->with(['documentType', 'documentTemplate'])->get();

        if ($documents->isEmpty()) {
            return;
        }

        $changed = [];
        foreach ($newPlaceholders as $key => $newValue) {
            $oldValue = $oldPlaceholders[$key] ?? null;
            if ($oldValue !== null && $oldValue !== $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        foreach ($documents as $document) {
            // A signed document is a record of what was signed: it stays put.
            if ($this->isFrozen($document)) {
                continue;
            }

            $body = $document->rendered_body;
            $newBody = $body;

            // 1. If unedited revision (revision === 0) and template is available, re-render cleanly
            $template = (int) $document->revision === 0 ? $this->currentTemplateFor($document) : null;

            if ($template !== null) {
                $candidate = $this->renderFor($document, $template, $document->generated_at);
                $switched = $template->getKey() !== $document->document_template_id;

                if ($candidate !== $body || $switched) {
                    $this->storeRebuilt($document, $candidate, $switched ? $template : null);

                    continue;
                }
            }

            // 2. Documents with live fields refresh exactly those fields and
            // nothing else; the text-matching fallback below is only for
            // documents generated before live fields existed.
            if (str_contains($body, 'data-field=')) {
                $this->refreshDocument($document, $newPlaceholders);

                continue;
            }

            // 3. Expand any unexpanded placeholders like {{key}}
            foreach ($newPlaceholders as $key => $val) {
                $pattern = '/\{\{\s*'.preg_quote($key, '/').'\s*\}\}/i';
                if (preg_match($pattern, $newBody)) {
                    $newBody = preg_replace($pattern, $val, $newBody);
                }
            }

            // 4. For any changed placeholders, replace old values if old was non-trivial
            foreach ($changed as $key => $diff) {
                $old = trim($diff['old']);
                $new = trim($diff['new']);
                if ($old !== '' && $old !== '-' && $old !== $new && str_contains($newBody, $old)) {
                    $newBody = str_replace($old, $new, $newBody);
                }
            }

            // If alamat_mitra is populated and document has legacy "DI TEMPAT" under KEPADA, replace it
            if ($newPlaceholders['alamat_mitra'] !== 'DI TEMPAT' && str_contains($newBody, 'DI TEMPAT')) {
                $newBody = preg_replace(
                    '/(<div style="font-weight:\s*bold;">KEPADA<\/div>\s*<div[^>]*>.*?<\/div>\s*<div[^>]*>)DI TEMPAT(<\/div>)/is',
                    '$1'.e($newPlaceholders['alamat_mitra']).'$2',
                    $newBody,
                );
            }

            if ($newBody !== $body) {
                $document->rendered_body = $newBody;
                $document->save();
            }
        }
    }

    /**
     * Wrap a rendered document body in a printable HTML shell.
     *
     * The PDF renderer paginates with its own page box, so the on-screen page
     * simulation is dropped and a font the PDF engine actually embeds is used.
     */
    public function printableHtml(ProcurementDocument $document, bool $forPdf = false): string
    {
        $body = $document->rendered_body;

        if (preg_match('/<html[\s>]/i', $body)) {
            if ($forPdf) {
                return str_ireplace(
                    ['font-family: "Times New Roman", Times, serif;', "font-family: 'Times New Roman', Times, serif;"],
                    "font-family: 'DejaVu Serif', serif;",
                    $body,
                );
            }

            return $body;
        }

        $title = e($document->title);

        $fontStack = $forPdf
            ? "'DejaVu Serif', serif"
            : "'Times New Roman', Times, serif";

        $bodyBox = $forPdf
            ? 'margin: 0; padding: 0;'
            : 'max-width: 210mm; margin: 0 auto; padding: 16mm 14mm;';

        $stylesheet = self::documentStylesheet($fontStack, $bodyBox);

        return <<<HTML
        <!doctype html>
        <html lang="id">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>{$title}</title>
        <style>{$stylesheet}</style>
        </head>
        <body>{$document->rendered_body}</body>
        </html>
        HTML;
    }

    /**
     * The paper stylesheet shared by generated documents and the template editor.
     *
     * The template editor draws the same rules on its page so what the
     * administrator lays out is what the generated document looks like.
     */
    public static function documentStylesheet(
        string $fontStack = "'Times New Roman', Times, serif",
        string $bodyBox = '',
    ): string {
        return <<<CSS
            /* The document is paper, never themed: force a light page so a
               browser in dark mode does not render dark text on dark. */
            :root { color-scheme: only light; }
            html, body { background: #fff; }
            @page { size: A4 portrait; margin: 20mm 15mm; }
            body { font-family: {$fontStack}; font-size: 10pt; color: #111; line-height: 1.45; text-align: justify; {$bodyBox} }
            @media print {
                @page { size: A4 portrait; margin: 20mm 15mm; }
                body { max-width: none; margin: 0; padding: 0; }
            }
            h1 { font-size: 14pt; text-align: center; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4pt; }
            h2 { font-size: 12pt; text-transform: uppercase; border-bottom: 1px solid #111; padding-bottom: 2pt; margin-top: 18pt; }
            h3 { font-size: 11pt; margin: 12pt 0 4pt; }
            h4 { font-size: 10pt; margin: 10pt 0 4pt; }
            p { margin: 4pt 0; }
            table { width: 100%; max-width: 100%; border-collapse: collapse; margin: 8pt 0; font-size: 10pt; }
            td, th { border: 1px solid #444; padding: 4pt 6pt; vertical-align: top; word-break: break-word; }
            th { background: #f0f0f0; text-align: left; }
            ol, ul { margin: 4pt 0 4pt 18pt; padding: 0; }
            li { margin: 2pt 0; }

            /* Cover page */
            .cover { text-align: center; page-break-after: always; padding-top: 40pt; }
            .cover .org { font-size: 15pt; font-weight: bold; line-height: 1.3; }
            .cover .doc-title { font-size: 14pt; font-weight: bold; margin-top: 48pt; line-height: 1.4; }
            .cover .doc-meta { margin-top: 24pt; font-size: 12pt; }
            .cover .doc-meta table { width: auto; margin: 0 auto; }
            .cover .doc-meta td { border: none; padding: 2pt 6pt; text-align: left; }
            .cover .subject { margin-top: 48pt; font-size: 12pt; font-weight: bold; line-height: 1.5; }
            .cover .footer { margin-top: 96pt; font-size: 12pt; font-weight: bold; }

            /* Structure */
            .bab { page-break-before: always; }
            .bab-heading { text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; border: none; margin: 0 0 18pt; }
            .lampiran { page-break-before: always; }
            .lampiran-head { width: 100%; margin-bottom: 12pt; }
            .lampiran-head td { border: none; padding: 1pt 0; font-size: 10.5pt; }
            .toc { list-style: none; margin-left: 0; }
            .toc li { margin: 3pt 0; }
            .toc ul { list-style: none; margin-left: 16pt; }

            /* Signatures */
            .signature { margin-top: 28pt; width: 100%; page-break-inside: avoid; }
            .signature td { border: none; text-align: center; vertical-align: top; padding: 6pt 8pt; }
            .signature .role { font-size: 11pt; }
            .signature .space { height: 56pt; }
            .signature .name { font-weight: bold; text-decoration: underline; }
            .fill { letter-spacing: .5pt; }
            .note { font-size: 10pt; font-style: italic; }

            /* Template editor blocks */
            table.plain td, table.plain th { border: none; background: none; }
            .page-break { page-break-after: always; height: 0; }
        CSS;
    }

    /**
     * Build an HTML list of the checklist rows for a stage.
     */
    protected function checklistMarkup(Procurement $procurement, ProcurementStage $stage): string
    {
        $rows = $procurement->checklists
            ->where('stage', $stage)
            ->sortBy(fn ($checklist): int => $checklist->checklistItem->sort_order);

        if ($rows->isEmpty()) {
            return '<p>-</p>';
        }

        $items = $rows->map(function ($checklist): string {
            $mark = $checklist->is_completed ? '&#10003;' : '&ndash;';

            return '<li>['.$mark.'] '.e($checklist->checklistItem->name).'</li>';
        })->implode('');

        return '<ul>'.$items.'</ul>';
    }
}
