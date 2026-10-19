<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementPartnerCandidate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The data of the tender's Berita Acara (Aanwijzing, Lampiran BAPP,
 * Evaluasi Teknis, Evaluasi Harga, Hasil Evaluasi, Klarifikasi): the calon
 * mitra as the participants, their offers ranked by price, the officials
 * who sign, and the day written out.
 *
 * The evaluation marks (A, L, M, LULUS, MEMENUHI) are the usual outcome and
 * can be corrected in the document editor.
 */
class TenderDocuments
{
    /**
     * A line to write on, where a value is not known.
     */
    protected const BLANK = '.........................';

    /**
     * The documents asked for in the technical evaluation, as on the form.
     *
     * @var list<string>
     */
    public const TECHNICAL_DOCUMENTS = [
        'Spesifikasi Barang/Jasa yang ditawarkan (Lampiran-1)',
        'Surat Penawaran (Lampiran-3)',
        'Surat Pernyataan (Lampiran-5)',
        'Surat Pengantar (Lampiran-4)',
        'Pakta Integritas (Lampiran-6)',
        'Rekaman SBU (Sertifikat Badan Usaha) atau Nomor Induk Berusaha (NIB)',
        'Rekaman NPWP dan Surat Pengukuhan Pengusaha kena Pajak (SPPKP)',
        'Formulir Kuisioner Integrity Due Diligence Mitra Berbentuk Badan Usaha yang ditandatangani oleh Direktur Perusahaan/Badan Usaha.',
        'Rekaman SPT Tahunan dan Resi Pajak Bulanan/Masa 3 Bulan Terakhir',
        'Copy sertifikat CSMS PT PLN Nusantara Power dengan kualifikasi Low Risk.',
        'Terdaftar dalam Daftar Penyedia Perusahaan (DPP) PT Nusantara Power dalam website smartscm.plnnusantarapower.co.id dengan melampirkan copy tanda DPP.',
        'Daftar Rincian Harga (lampiran-2). Yang dapat dipertanggungjawabkan kerahasiannya.',
    ];

    /**
     * The placeholder values of the tender's Berita Acara.
     *
     * @return array<string, string>
     */
    public static function values(Procurement $procurement, CarbonInterface $issuedAt): array
    {
        $candidates = $procurement->partnerCandidates()->get();
        $ranked = self::ranked($candidates);
        $hpe = (float) $procurement->hpe_value;

        return [
            'hari_dokumen' => $issuedAt->translatedFormat('l'),
            'tanggal_dokumen_terbilang' => Str::title(IndonesianNumber::spell((int) $issuedAt->format('j'))),
            'bulan_dokumen' => $issuedAt->translatedFormat('F'),
            'tahun_dokumen_terbilang' => Str::title(IndonesianNumber::spell((int) $issuedAt->format('Y'))),
            'tanggal_rks' => self::rksDate($procurement),
            // How the partner is chosen, as the planning PIC set it.
            'metode_pemilihan' => $procurement->selectionMethod->name ?? 'Pengadaan Langsung',
            'nama_pejabat_pelaksana' => self::official($procurement->executionReviewer, UserRole::TeamLeaderPengadaan),
            'nama_pejabat_perencana' => self::official($procurement->planningReviewer, UserRole::TeamLeaderIcc),
            'jumlah_peserta' => self::count($candidates->count()),
            'daftar_peserta' => self::list($candidates),
            'daftar_peserta_ttd' => self::signingParticipants($candidates),
            'tabel_peserta_penawaran' => self::offerLetters($candidates),
            'tabel_pembukaan_penawaran' => self::opening($candidates),
            'tabel_evaluasi_teknis' => self::technical($candidates),
            'tabel_evaluasi_harga' => self::price($candidates, $ranked),
            'tabel_pemasukan_penawaran' => self::submissions($candidates),
            'tabel_hasil_administrasi' => self::results($candidates, ''),
            'tabel_hasil_keuangan' => self::results($candidates, ''),
            'tabel_hasil_teknis' => self::results($candidates, 'LULUS'),
            'tabel_hasil_penawaran' => self::results($candidates, 'LULUS'),
            'tabel_urutan_harga' => self::ranking($ranked, $hpe),
        ];
    }

    /**
     * The catalog entries of these placeholders, for the template editor.
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            'hari_dokumen' => 'BA: nama hari tanggal dokumen (mis. Senin)',
            'tanggal_dokumen_terbilang' => 'BA: tanggal dokumen dieja (mis. Empat Belas)',
            'bulan_dokumen' => 'BA: bulan tanggal dokumen (mis. September)',
            'tahun_dokumen_terbilang' => 'BA: tahun dokumen dieja (mis. Dua Ribu Dua Puluh Enam)',
            'tanggal_rks' => 'BA: tanggal RKS digenerate',
            'metode_pemilihan' => 'BA: metode pengadaan pilihan Rendan (bawaan: Pengadaan Langsung)',
            'nama_pejabat_pelaksana' => 'BA: Pejabat Pelaksana Pengadaan (TL Pengadaan)',
            'nama_pejabat_perencana' => 'BA: Pejabat Perencana Pengadaan (TL ICC)',
            'jumlah_peserta' => 'BA: jumlah calon mitra, mis. 3 (Tiga)',
            'daftar_peserta' => 'BA: daftar nama calon mitra (bernomor)',
            'daftar_peserta_ttd' => 'BA: kolom tanda tangan peserta (calon mitra)',
            'tabel_peserta_penawaran' => 'BA Klarifikasi: tabel peserta dan nomor surat penawaran',
            'tabel_pembukaan_penawaran' => 'Lampiran BAPP: tabel kelengkapan penawaran per calon mitra',
            'tabel_evaluasi_teknis' => 'BA Evaluasi Teknis: tabel dokumen per calon mitra',
            'tabel_evaluasi_harga' => 'BA Evaluasi Harga: tabel hasil evaluasi penawaran per calon mitra',
            'tabel_pemasukan_penawaran' => 'BA Hasil Evaluasi: tabel pemasukan dan pembukaan penawaran',
            'tabel_hasil_administrasi' => 'BA Hasil Evaluasi: tabel hasil evaluasi administrasi',
            'tabel_hasil_keuangan' => 'BA Hasil Evaluasi: tabel hasil evaluasi keuangan',
            'tabel_hasil_teknis' => 'BA Hasil Evaluasi: tabel hasil evaluasi teknis',
            'tabel_hasil_penawaran' => 'BA Hasil Evaluasi: tabel hasil evaluasi penawaran',
            'tabel_urutan_harga' => 'BA Hasil Evaluasi: tabel urutan harga penawaran',
        ];
    }

    /**
     * The calon mitra by their offer, the cheapest first; one without an
     * offer comes last.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     * @return Collection<int, ProcurementPartnerCandidate>
     */
    protected static function ranked(Collection $candidates): Collection
    {
        return $candidates
            ->sortBy(fn (ProcurementPartnerCandidate $candidate): array => [
                $candidate->quotation_value === null ? 1 : 0,
                (float) $candidate->quotation_value,
                $candidate->sort_order,
            ])
            ->values();
    }

    /**
     * Who signs for an office: the one who reviewed the job, else the active
     * holder of the role.
     */
    protected static function official(?User $reviewer, UserRole $role): string
    {
        if ($reviewer !== null && $reviewer->role === $role) {
            return $reviewer->name;
        }

        return User::query()->active()->withRole([$role])->orderBy('name')->value('name') ?? self::BLANK;
    }

    /**
     * The day the RKS was drawn, if it was.
     */
    protected static function rksDate(Procurement $procurement): string
    {
        $type = DocumentType::query()->where('code', 'rks')->value('id');
        $document = $type === null ? null : $procurement->documentFor((int) $type);

        return $document?->generated_at?->translatedFormat('d F Y') ?? self::BLANK;
    }

    /**
     * A count as the documents write it: 3 (Tiga).
     */
    protected static function count(int $count): string
    {
        return $count.' ('.Str::title(IndonesianNumber::spell($count)).')';
    }

    /**
     * An offer in rupiah, blank when not known.
     */
    protected static function money(?string $value): string
    {
        return $value === null ? '' : 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    /**
     * The calon mitra, or three rows to write on when none is listed yet.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     * @return list<ProcurementPartnerCandidate|null>
     */
    protected static function orBlank(Collection $candidates): array
    {
        $rows = array_values($candidates->all());

        return $rows === [] ? [null, null, null] : $rows;
    }

    /**
     * The markup of every row, numbered from 0.
     *
     * @param  list<ProcurementPartnerCandidate|null>  $rows
     * @param  callable(?ProcurementPartnerCandidate, int): string  $row
     */
    protected static function each(array $rows, callable $row): string
    {
        return implode('', array_map($row, $rows, array_keys($rows)));
    }

    /**
     * A candidate's name, or a line to write it on.
     */
    protected static function name(?ProcurementPartnerCandidate $candidate): string
    {
        return $candidate === null ? self::BLANK : e(mb_strtoupper($candidate->name));
    }

    /**
     * The calon mitra one per line, numbered.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function list(Collection $candidates): string
    {
        $items = self::each(self::orBlank($candidates), fn (?ProcurementPartnerCandidate $candidate): string => '<li>'.self::name($candidate).'</li>');

        return '<ol>'.$items.'</ol>';
    }

    /**
     * The participants' signature column: each calon mitra with a line for
     * the name and for the signature.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function signingParticipants(Collection $candidates): string
    {
        $rows = self::each(self::orBlank($candidates), fn (?ProcurementPartnerCandidate $candidate, int $index): string => '<tr><td style="width:6%">'.($index + 1).'</td><td colspan="2"><b>'.self::name($candidate).'</b></td></tr>'
            .'<tr><td></td><td>NAMA '.self::BLANK.'</td><td style="width:30%">TTD ..........</td></tr>');

        return '<table class="ba-plain">'.$rows.'</table>';
    }

    /**
     * The Klarifikasi's list of participants and their offer letters.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function offerLetters(Collection $candidates): string
    {
        $rows = self::each(self::orBlank($candidates), fn (?ProcurementPartnerCandidate $candidate, int $index): string => '<tr><td class="c">'.($index + 1).'</td><td>'.self::name($candidate).'</td>'
            .'<td>'.e($candidate->quotation_number ?? '').'</td>'
            .'<td>'.e($candidate?->quotation_date?->translatedFormat('d F Y') ?? '').'</td></tr>');

        return '<table class="ba-grid"><tr><th class="c" style="width:6%; white-space: nowrap;">NO</th><th>NAMA PESERTA</th><th>NO SURAT PENAWARAN</th><th style="width:20%">TANGGAL SIMPAN</th></tr>'.$rows.'</table>';
    }

    /**
     * The Lampiran BAPP: the completeness of each offer, one column per calon
     * mitra.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function opening(Collection $candidates): string
    {
        $columns = self::orBlank($candidates);
        $span = count($columns);
        $marks = fn (string $mark): string => str_repeat('<td class="c">'.$mark.'</td>', $span);
        $blank = str_repeat('<td></td>', $span);

        // The participants share 45% of the width, none narrower than 12%.
        $width = max(12, intdiv(45, max(1, $span)));

        $html = '<table class="ba-grid"><tr><th class="c" style="width:6%; white-space: nowrap;">No</th><th class="c" colspan="2">PERSYARATAN KELENGKAPAN PENAWARAN</th>'
            .self::each($columns, fn (?ProcurementPartnerCandidate $candidate): string => '<th class="c" style="width:'.$width.'%; font-size: 9pt;">'.self::name($candidate).'</th>').'</tr>';

        $html .= '<tr><td class="c"><b>1</b></td><td colspan="2"><b>KELENGKAPAN DOKUMEN PELELANGAN</b></td>'.$blank.'</tr>';
        $html .= '<tr><td></td><td class="c" style="width:5%"><b>A.</b></td><td><b>DOKUMEN TEKNIS</b></td>'.$blank.'</tr>';

        foreach (self::TECHNICAL_DOCUMENTS as $index => $document) {
            $html .= '<tr><td></td><td class="c">'.chr(97 + $index).'.</td><td>'.e($document).'</td>'.$marks('A').'</tr>';
        }

        $html .= '<tr><td></td><td class="c"><b>B.</b></td><td><b>DOKUMEN KEWAJARAN HARGA</b></td>'.$blank.'</tr>';
        $html .= '<tr><td></td><td class="c">a.</td><td>Surat Penawaran Harga</td>'.$marks('A').'</tr>';
        $html .= '<tr><td class="c"><b>2</b></td><td colspan="2"><b>KESIMPULAN AKHIR</b></td>'.$marks('L').'</tr>';
        $html .= '<tr><td class="c"><b>3</b></td><td colspan="2"><b>PARAF PESERTA (L)</b></td>'.$blank.'</tr>';

        return $html.'</table>';
    }

    /**
     * The technical evaluation: each document asked for, met (M) or not, one
     * column per calon mitra.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function technical(Collection $candidates): string
    {
        $columns = self::orBlank($candidates);
        [$head, $marks] = self::evaluationColumns($columns);

        $html = $head;

        foreach (self::TECHNICAL_DOCUMENTS as $index => $document) {
            $html .= '<tr><td class="c">'.chr(97 + $index).'.</td><td>'.e($document).'</td>'.$marks.'</tr>';
        }

        $html .= '<tr><td></td><td class="c"><b>Keterangan Lainnya</b></td>'.str_repeat('<td colspan="2"></td>', count($columns)).'</tr>';
        $html .= '<tr><td></td><td class="c"><b>Kesimpulan Evaluasi Teknis</b></td>'.str_repeat('<td class="c" colspan="2"><b>MEMENUHI</b></td>', count($columns)).'</tr>';

        return $html.'</table>';
    }

    /**
     * The price evaluation: the offer letter met (M) or not, the conclusion
     * and each offer's place by price, one column per calon mitra.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     * @param  Collection<int, ProcurementPartnerCandidate>  $ranked
     */
    protected static function price(Collection $candidates, Collection $ranked): string
    {
        $columns = self::orBlank($candidates);
        $place = fn (?ProcurementPartnerCandidate $candidate): string => $candidate === null || $candidate->quotation_value === null
            ? ''
            : (string) ($ranked->search(fn (ProcurementPartnerCandidate $other): bool => $other->is($candidate)) + 1);

        [$head, $marks] = self::evaluationColumns($columns);

        return $head
            .'<tr><td class="c">a.</td><td>Surat Penawaran Harga</td>'.$marks.'</tr>'
            .'<tr><td></td><td class="c"><b>Kesimpulan Evaluasi Penawaran</b></td>'.str_repeat('<td class="c" colspan="2"><b>LULUS</b></td>', count($columns)).'</tr>'
            .'<tr><td></td><td class="c"><b>Urutan Harga Penawaran</b></td>'.self::each($columns, fn (?ProcurementPartnerCandidate $candidate): string => '<td class="c" colspan="2"><b>'.$place($candidate).'</b></td>').'</tr>'
            .'</table>';
    }

    /**
     * The head of an evaluation table (No, the document asked for, then each
     * participant over its Hasil Evaluasi) and a row's marks (a blank cell
     * and M for each participant), sized so the document reads on one or
     * two lines.
     *
     * @param  list<ProcurementPartnerCandidate|null>  $columns
     * @return array{0: string, 1: string}
     */
    protected static function evaluationColumns(array $columns): array
    {
        $count = max(1, count($columns));
        // No 5%, the document 40%, the participants share the rest.
        $share = intdiv(55, $count);
        $mark = 6;

        $head = '<table class="ba-grid"><tr><th class="c" rowspan="2" style="width:5%; white-space: nowrap;">No</th><th class="c" rowspan="2" style="width:40%">Dokumen Yang Diminta</th>'
            .self::each($columns, fn (?ProcurementPartnerCandidate $candidate): string => '<th class="c" colspan="2" style="font-size: 9pt;">'.self::name($candidate).'</th>').'</tr>'
            .'<tr>'.str_repeat('<th class="c" colspan="2">Hasil Evaluasi</th>', count($columns)).'</tr>';

        $marks = str_repeat('<td style="width:'.($share - $mark).'%"></td><td class="c" style="width:'.$mark.'%">M</td>', count($columns));

        return [$head, $marks];
    }

    /**
     * The offers submitted and opened: name, offer and completeness.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function submissions(Collection $candidates): string
    {
        $rows = self::each(self::orBlank($candidates), fn (?ProcurementPartnerCandidate $candidate, int $index): string => '<tr><td class="c">'.($index + 1).'</td><td>'.self::name($candidate).'</td>'
            .'<td class="r">'.self::money($candidate?->quotation_value).'</td><td class="c">'.($candidate === null ? '' : 'LENGKAP').'</td></tr>');

        return '<table class="ba-grid"><tr><th class="c" style="width:6%; white-space: nowrap;">No</th><th class="c">Nama Perusahaan</th><th class="c" style="width:24%">Harga Penawaran</th><th class="c" style="width:26%">Hasil Pembukaan Penawaran</th></tr>'.$rows.'</table>';
    }

    /**
     * One stage's results: each calon mitra with its outcome.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function results(Collection $candidates, string $outcome): string
    {
        $rows = self::each(self::orBlank($candidates), fn (?ProcurementPartnerCandidate $candidate, int $index): string => '<tr><td class="c">'.($index + 1).'</td><td>'.self::name($candidate).'</td>'
            .'<td class="c">'.($candidate === null ? '' : $outcome).'</td><td></td></tr>');

        return '<table class="ba-grid"><tr><th class="c" style="width:6%; white-space: nowrap;">No</th><th class="c">Nama Perusahaan</th><th class="c" style="width:22%">Hasil Evaluasi</th><th class="c" style="width:26%">Keterangan</th></tr>'.$rows.'</table>';
    }

    /**
     * The offers by price, the cheapest first, against the HPS.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $ranked
     */
    protected static function ranking(Collection $ranked, float $hpe): string
    {
        $rows = self::each(self::orBlank($ranked), function (?ProcurementPartnerCandidate $candidate, int $index) use ($hpe): string {
            $share = $candidate?->quotation_value === null || $hpe <= 0
                ? ''
                : number_format((float) $candidate->quotation_value / $hpe * 100, 2, ',', '.').'%';

            return '<tr><td class="c">'.($index + 1).'</td><td>'.self::name($candidate).'</td>'
                .'<td class="r">'.self::money($candidate?->quotation_value).'</td><td class="c">'.$share.'</td></tr>';
        });

        return '<table class="ba-grid"><tr><th class="c" style="width:10%; white-space: nowrap;">No Urut</th><th class="c">Nama Perusahaan</th><th class="c" style="width:26%">Harga Penawaran Terkoreksi</th><th class="c" style="width:22%">Perbandingan Thd HPS</th></tr>'.$rows.'</table>';
    }
}
