<?php

namespace App\Support;

use App\Models\Procurement;
use App\Models\ProcurementPartnerCandidate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The data of the tenders' own Berita Acara (Penunjukan Langsung, Lelang
 * Terbuka 1 Tahap 1 / 2 Sampul): the qualification sheets (Lampiran 1-3),
 * the opening of the envelopes, the negotiation, the Berita Acara Hasil
 * Pengadaan, and the winner proposed, set and announced.
 *
 * The winner is the calon mitra picked as the executor, else the cheapest
 * offer. The evaluation marks (M, LULUS, MEMENUHI) are the usual outcome and
 * can be corrected in the document editor.
 */
class LelangDocuments extends TenderDocuments
{
    /**
     * The administrative qualification asked for (Lampiran 1).
     *
     * @var list<string>
     */
    public const ADMINISTRATION_DOCUMENTS = [
        'Surat Pernyataan Minat Untuk Mengikuti Pengadaan;',
        'Asli Surat Pernyataan diantaranya : 1. Kesanggupan Mematuhi dan Memenuhi Semua Ketentuan yang ditetapkan dalam Dokumen Rencana Kerja dan Syarat-Syarat; 2. Perusahaan yang di pimpin tidak dalam keadaan bangkrut; 3. Direktur perusahaan tidak dalam pengawasan pengadilan dan tidak sedang menjalani sanksi pidana; 4. Tidak akan menuntut ganti rugi dalam bentuk apapun jika proses Pengadaan Barang/Jasa dilakukan penghentian atau Pengadaan Barang/Jasa dinyatakan Gagal atau Pemutusan Perjanjian/Kontrak oleh Pengguna Barang/Jasa (sesuai Lampiran 5).',
        'Pakta Integritas (sesuai Lampiran-6);',
        'Copy Dokumen kepemilikan workshop beserta peralatan kerja.',
        'Dalam hal Penyedia Barang/Jasa berbentuk persekutuan usaha/konsorsium maka Penyedia Barang/Jasa wajib mempunyai dan melampirkan perjanjian kerjasama operasi/kemitraan/konsorsium yang memuat representasi persekutuan dan pihak yang mewakili persekutuan yang disahkan oleh notaris.',
        'Terdaftar sebagai Daftar Penyedia Perusahaan (DPP) PT PLN NP dibuktikan dengan melampirkan copy TDPP. Bagi Calon Penyedia Barang/Jasa yang belum terdaftar di DPP (Daftar Penyedia Perusahaan) PT PLN NP wajib menyampaikan syarat Administrasi sebagai berikut: i. Copy Akte Pendirian Perusahaan atau Copy legalitas perusahaan di negara domisili, berikut perubahan-perubahannya (apabila ada perubahan); ii. Copy Surat Izin Usaha Perdagangan (SIUP) dan atau Nomor Induk Berusaha (NIB) yang masih berlaku dengan kualifikasi sub bidang (KBLI) sesuai dengan pekerjaan yang dilelang; iii. Copy Surat Keterangan Domisili Perusahaan; iv. Daftar Susunan Pemilik Saham; v. Daftar Susunan Pengurus Perusahaan; vi. Copy NPWP dan PKP; vii. Asli Surat Referensi Bank.',
        'NIB beserta lampiran KBLI yang berkaitan dengan pekerjaan atau SIUP yang masih berlaku.',
    ];

    /**
     * The financial qualification asked for (Lampiran 2); %s is the year
     * before the document's.
     *
     * @var list<string>
     */
    public const FINANCE_DOCUMENTS = [
        'Copy Laporan keuangan tahun %s yang telah diaudit oleh akuntan publik dan atau dapat berupa hasil rating/pemeringkatan dari DunBradstreet atau lembaga pemeringkat keuangan yang setara.',
        'Copy Rekening koran perusahaan minimal 3 (tiga) bulan terakhir tahun berjalan.',
        'Copy bukti pelunasan kewajiban pajak tahun terakhir (SPT/PPh), (Tahun terakhir).',
        'Copy laporan bulanan PPh Pasal 29 atau Pasal 25 atau Pasal 23 atau PPN sekurang-kurangnya 3 (tiga) bulan terakhir.',
    ];

    /**
     * The technical qualification asked for (Lampiran 3); %s is the CSMS
     * risk level of the job.
     *
     * @var list<string>
     */
    public const TECHNICAL_TENDER_DOCUMENTS = [
        'Asli Surat Pernyataan Kesanggupan Memenuhi Persyaratan Teknis/Term of Reference (TOR) (sesuai Lampiran 12).',
        'Spesifikasi Barang/Jasa yang ditawarkan (sesuai Lampiran 2), sesuai dengan spesifikasi barang/jasa yang dimintakan penawaran (sesuai Lampiran 1).',
        'Surat Pernyataan Bermaterai Rp. 10.000,- (Sepuluh Ribu Rupiah) yang menyatakan bahwa Siap Bertanggung Jawab melakukan penggantian dan menanggung semua biaya jika ditemukan adanya ketidaksesuaian spesifikasi atau kerusakan/cacat pada material yang disuplai.',
        'Surat Pernyataan Bermaterai Rp. 10.000,- (Sepuluh Ribu Rupiah) yang menyatakan Mematuhi Aturan K3L di Lingkungan PT PLN NP (sesuai Lampiran 11).',
        'Copy sertifikat CSMS yang diterbitkan oleh PLN Nusantara Power dengan kriteria risiko minimal %s.',
        'Copy Perjanjian/Kontrak/Delivery Order pekerjaan sejenis yang pernah dilaksanakan kurun waktu 2 (dua) tahun terakhir.',
        'Copy Sertifikat Kompetensi/Keahlian personil dalam bidang pekerjaan.',
        'Copy surat keterangan kepemilikan workshop beserta daftar peralatan kerja.',
    ];

    /**
     * The tenders' placeholder values.
     *
     * @return array<string, string>
     */
    public static function awardValues(Procurement $procurement, CarbonInterface $issuedAt): array
    {
        $procurement->loadMissing('selectionMethod');
        $candidates = $procurement->partnerCandidates()->get();
        $ranked = self::ranked($candidates);
        $winner = $candidates->firstWhere('id', $procurement->partner_candidate_id) ?? $ranked->first();

        $hpe = (float) $procurement->hpe_value;
        $hps = $hpe + round($hpe * 0.12);
        $nego = $procurement->value_after_negotiation === null ? null : (float) $procurement->value_after_negotiation;
        $negoTotal = $nego === null ? null : $nego + round($nego * 0.12);
        $offer = $winner?->quotation_value === null ? null : (float) $winner->quotation_value;

        $csms = $procurement->csms_risk_level?->label() ?? self::BLANK;

        return [
            'nama_pemenang' => $winner === null ? ($procurement->partner_name ?: self::BLANK) : mb_strtoupper($winner->name),
            'alamat_pemenang' => $winner->address ?? $procurement->partner_address ?? self::BLANK,
            'direktur_pemenang' => $winner->director_name ?? $procurement->partner_director_name ?? self::BLANK,
            'nilai_penawaran_pemenang' => self::rupiah($offer),
            'nilai_negosiasi_pemenang' => self::rupiah($negoTotal),
            'nilai_negosiasi_pemenang_terbilang' => $negoTotal === null ? self::BLANK : Str::title(IndonesianNumber::spellRupiah($negoTotal)),
            'persen_hps_pemenang' => self::share($negoTotal ?? $offer, $hps),
            'metode_penyampaian' => self::submission($procurement),
            'tabel_evaluasi_administrasi' => self::sheet($candidates, self::ADMINISTRATION_DOCUMENTS, 'Kesimpulan Evaluasi Administrasi', 'LULUS', false),
            'tabel_evaluasi_keuangan' => self::sheet(
                $candidates,
                array_map(fn (string $document): string => sprintf($document, (int) $issuedAt->format('Y') - 1), self::FINANCE_DOCUMENTS),
                'Kesimpulan Evaluasi Keuangan',
                'MEMENUHI',
                true,
            ),
            'tabel_evaluasi_teknis_lelang' => self::sheet(
                $candidates,
                array_map(fn (string $document): string => sprintf($document, $csms), self::TECHNICAL_TENDER_DOCUMENTS),
                'Kesimpulan Evaluasi Teknis',
                'MEMENUHI',
                true,
            ),
            'tabel_nilai_penawaran' => self::offers($candidates),
            'tabel_hasil_evaluasi_sampul_1' => self::envelopeOne($candidates),
            'tabel_pengumuman_pemenang' => self::announcement($candidates, $winner, $procurement),
            'tabel_nominasi' => self::nominees($ranked, $winner, $negoTotal),
            'tabel_negosiasi_penawaran' => self::negotiation($winner, $offer, $negoTotal, $procurement),
            'tabel_negosiasi_hps' => self::negotiationAgainstHps($winner, $offer, $negoTotal, $hps, $procurement),
            // Offers include tax, so they are set against the HPS with PPN.
            'tabel_urutan_harga_hps' => self::ranking($ranked, $hps),
        ];
    }

    /**
     * The catalog entries of these placeholders, for the template editor.
     *
     * @return array<string, string>
     */
    public static function awardCatalog(): array
    {
        return [
            'nama_pemenang' => 'Lelang: nama pemenang (calon mitra terpilih, atau penawaran termurah)',
            'alamat_pemenang' => 'Lelang: alamat pemenang',
            'direktur_pemenang' => 'Lelang: direktur pemenang',
            'nilai_penawaran_pemenang' => 'Lelang: nilai penawaran pemenang (mis. 2.274.589.800,00)',
            'nilai_negosiasi_pemenang' => 'Lelang: harga setelah negosiasi termasuk PPN',
            'nilai_negosiasi_pemenang_terbilang' => 'Lelang: harga setelah negosiasi dieja',
            'persen_hps_pemenang' => 'Lelang: perbandingan harga pemenang terhadap HPS (mis. 97,50%)',
            'metode_penyampaian' => 'Lelang: metode penyampaian (Satu Tahap Satu/Dua Sampul)',
            'tabel_evaluasi_administrasi' => 'Lelang: tabel evaluasi kualifikasi administrasi (Lampiran 1)',
            'tabel_evaluasi_keuangan' => 'Lelang: tabel evaluasi kualifikasi keuangan (Lampiran 2)',
            'tabel_evaluasi_teknis_lelang' => 'Lelang: tabel evaluasi kualifikasi teknis (Lampiran 3)',
            'tabel_nilai_penawaran' => 'Lelang: tabel nilai penawaran peserta',
            'tabel_hasil_evaluasi_sampul_1' => 'Lelang: tabel hasil evaluasi sampul 1 (administrasi, teknis, keuangan)',
            'tabel_pengumuman_pemenang' => 'Lelang: tabel pengumuman pemenang',
            'tabel_nominasi' => 'Lelang: tabel nominasi pemenang',
            'tabel_negosiasi_penawaran' => 'Lelang: tabel harga sebelum dan setelah negosiasi',
            'tabel_negosiasi_hps' => 'Lelang: tabel harga negosiasi terhadap HPS',
            'tabel_urutan_harga_hps' => 'Lelang: urutan harga penawaran terhadap HPS termasuk PPN',
        ];
    }

    /**
     * How the offers are handed in, after the method.
     */
    protected static function submission(Procurement $procurement): string
    {
        return match ($procurement->selectionMethod?->code) {
            'lelang-terbuka-1-tahap-2-sampul' => 'Satu Tahap Dua Sampul',
            'lelang-terbuka-1-tahap-1-sampul' => 'Satu Tahap Satu Sampul',
            default => $procurement->selectionMethod->name ?? 'Pengadaan Langsung',
        };
    }

    /**
     * An amount as the tender papers print it (2.274.589.800,00), or a line.
     */
    protected static function rupiah(?float $value): string
    {
        return $value === null ? self::BLANK : number_format($value, 2, ',', '.');
    }

    /**
     * A price against the HPS, as a percentage with two decimals.
     */
    protected static function share(?float $value, float $hps): string
    {
        return $value === null || $hps <= 0 ? self::BLANK : number_format($value / $hps * 100, 2, ',', '.').'%';
    }

    /**
     * A qualification sheet: each document asked for, met (M) or not, one
     * column per calon mitra, closed by its conclusion.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     * @param  list<string>  $documents
     */
    protected static function sheet(Collection $candidates, array $documents, string $conclusionLabel, string $conclusion, bool $remarks): string
    {
        $columns = self::orBlank($candidates);
        [$head, $marks] = self::evaluationColumns($columns);

        $html = $head;

        foreach ($documents as $index => $document) {
            $html .= '<tr><td class="c">'.chr(97 + $index).'.</td><td>'.e($document).'</td>'.$marks.'</tr>';
        }

        if ($remarks) {
            $html .= '<tr><td></td><td class="c"><b>Keterangan Lainnya</b></td>'.str_repeat('<td colspan="2"></td>', count($columns)).'</tr>';
        }

        $html .= '<tr><td></td><td class="c"><b>'.e($conclusionLabel).'</b></td>'.str_repeat('<td class="c" colspan="2"><b>'.e($conclusion).'</b></td>', count($columns)).'</tr>';

        return $html.'</table>';
    }

    /**
     * The offers opened: each calon mitra and its offer.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function offers(Collection $candidates): string
    {
        $rows = self::each(self::orBlank($candidates), fn (?ProcurementPartnerCandidate $candidate, int $index): string => '<tr><td class="c">'.($index + 1).'</td><td>'.self::name($candidate).'</td>'
            .'<td class="r">'.($candidate?->quotation_value === null ? '' : self::rupiah((float) $candidate->quotation_value)).'</td></tr>');

        return '<table class="ba-grid"><tr><th class="c" style="width:7%">No</th><th class="c">Penyedia Barang/Jasa</th><th class="c" style="width:32%">Nilai Penawaran</th></tr>'.$rows.'</table>';
    }

    /**
     * The first envelope's results: administration, technical and finance.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function envelopeOne(Collection $candidates): string
    {
        $rows = self::each(self::orBlank($candidates), fn (?ProcurementPartnerCandidate $candidate, int $index): string => '<tr><td class="c">'.($index + 1).'</td><td class="c">'.self::name($candidate).'</td>'
            .str_repeat('<td class="c">'.($candidate === null ? '' : 'LULUS').'</td>', 3)
            .'<td class="c">'.($candidate === null ? '' : 'SAMPUL 2').'</td></tr>');

        return '<table class="ba-grid"><tr><th class="c" style="width:6%">NO</th><th class="c">NAMA PESERTA</th><th class="c" style="width:16%">HASIL ADMINISTRASI</th><th class="c" style="width:13%">HASIL TEKNIS</th><th class="c" style="width:14%">HASIL KEUANGAN</th><th class="c" style="width:17%">KETERANGAN</th></tr>'.$rows.'</table>';
    }

    /**
     * The winner announced: every participant's results, the winner going on
     * to the objection period.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $candidates
     */
    protected static function announcement(Collection $candidates, ?ProcurementPartnerCandidate $winner, Procurement $procurement): string
    {
        $twoEnvelopes = in_array($procurement->selectionMethod?->code, TenderBeritaAcara::TWO_ENVELOPES, true);
        $results = $twoEnvelopes ? 2 : 1;

        $head = '<tr><th class="c" style="width:6%">NO</th><th class="c">NAMA PESERTA</th>'
            .($twoEnvelopes
                ? '<th class="c" style="width:14%">HASIL EVALUASI SAMPUL 1</th><th class="c" style="width:14%">HASIL EVALUASI SAMPUL 2</th>'
                : '<th class="c" style="width:18%">HASIL EVALUASI</th>')
            .'<th class="c" style="width:16%">STATUS</th><th class="c" style="width:18%">KETERANGAN</th></tr>';

        $rows = self::each(self::orBlank($candidates), function (?ProcurementPartnerCandidate $candidate, int $index) use ($winner, $results): string {
            $won = $candidate !== null && $winner !== null && $candidate->is($winner);

            return '<tr><td class="c">'.($index + 1).'</td><td class="c">'.self::name($candidate).'</td>'
                .str_repeat('<td class="c">'.($candidate === null ? '' : 'LULUS').'</td>', $results)
                .'<td class="c">'.($won ? 'PEMENANG' : ($candidate === null ? '' : '-')).'</td>'
                .'<td class="c">'.($won ? 'LANJUT KE MASA SANGGAH' : ($candidate === null ? '' : '-')).'</td></tr>';
        });

        return '<table class="ba-grid">'.$head.$rows.'</table>';
    }

    /**
     * The nominees, the winner first, then the other offers by price.
     *
     * @param  Collection<int, ProcurementPartnerCandidate>  $ranked
     */
    protected static function nominees(Collection $ranked, ?ProcurementPartnerCandidate $winner, ?float $negoTotal): string
    {
        $ordered = $winner === null
            ? $ranked
            : collect([$winner])->merge($ranked->reject(fn (ProcurementPartnerCandidate $candidate): bool => $candidate->is($winner)))->values();

        $rows = self::each(self::orBlank($ordered), function (?ProcurementPartnerCandidate $candidate, int $index) use ($winner, $negoTotal): string {
            $price = $candidate !== null && $winner !== null && $candidate->is($winner) && $negoTotal !== null
                ? $negoTotal
                : ($candidate?->quotation_value === null ? null : (float) $candidate->quotation_value);

            return '<tr><td class="c">'.($index + 1).'</td><td>Nominator ke-'.($index + 1).'</td><td>'.self::name($candidate).'</td>'
                .'<td>'.self::BLANK.'</td><td>'.($price === null ? '' : 'Rp. '.number_format($price, 0, ',', '.')).'</td></tr>';
        });

        return '<table class="ba-grid"><tr><th class="c" style="width:6%">No</th><th class="c" style="width:16%">Nominator</th><th class="c">Nama Peserta</th><th class="c" style="width:20%">NPWP</th><th class="c" style="width:22%">Harga Koreksi Aritmatik</th></tr>'.$rows.'</table>';
    }

    /**
     * The winner's price before and after the negotiation.
     */
    protected static function negotiation(?ProcurementPartnerCandidate $winner, ?float $offer, ?float $negoTotal, Procurement $procurement): string
    {
        $name = $winner === null ? e($procurement->partner_name ?: self::BLANK) : self::name($winner);

        return '<table class="ba-grid"><tr><th class="c" rowspan="2">Nama Perusahaan</th><th class="c" colspan="2">Penawaran Harga</th></tr>'
            .'<tr><th class="c" style="width:26%">Sebelum Negosiasi<br>(+ PPN)</th><th class="c" style="width:26%">Setelah Negosiasi<br>(+ PPN)</th></tr>'
            .'<tr><td>'.$name.'</td><td class="r" style="white-space: nowrap;">Rp '.self::rupiah($offer).'</td><td class="r" style="white-space: nowrap;">Rp '.self::rupiah($negoTotal).'</td></tr></table>';
    }

    /**
     * The winner's offer and negotiated price against the HPS.
     */
    protected static function negotiationAgainstHps(?ProcurementPartnerCandidate $winner, ?float $offer, ?float $negoTotal, float $hps, Procurement $procurement): string
    {
        $name = $winner === null ? e($procurement->partner_name ?: self::BLANK) : self::name($winner);

        return '<table class="ba-grid" style="font-size: 9pt;"><tr><th class="c" style="width:6%; white-space: nowrap;">No</th><th class="c">Nama Perusahaan</th><th class="c" style="width:24%">Harga Penawaran</th><th class="c" style="width:24%">Harga Negosiasi</th><th class="c" style="width:15%">Perbandingan Thd HPS</th></tr>'
            .'<tr><td class="c">1</td><td>'.$name.'</td><td class="r" style="white-space: nowrap;">Rp '.self::rupiah($offer).'</td><td class="r" style="white-space: nowrap;">Rp '.self::rupiah($negoTotal).'</td><td class="c">'.self::share($negoTotal ?? $offer, $hps).'</td></tr></table>';
    }
}
