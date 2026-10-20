<?php

namespace App\Support;

/**
 * The Berita Acara of the "Proses SMART SCM & Berita Acara" step, per
 * selection method.
 *
 * Pengadaan Langsung keeps the set it always had. Penunjukan Langsung and the
 * open tenders (Lelang Terbuka 1 Tahap 1 Sampul / 2 Sampul) have their own
 * versions of several of them, and papers of their own: the opening of each
 * envelope, the evaluation sheets, the negotiation, the Berita Acara Hasil
 * Pengadaan, the proposal, setting and announcement of the winner and the
 * SPPBJ. A document with no method listed is asked for under every method.
 * Which method a document belongs to is master data: an administrator
 * changes it on Data Master → Jenis Dokumen.
 */
class TenderBeritaAcara
{
    /**
     * The method a procurement goes by while the planning PIC has not
     * picked one.
     */
    public const DEFAULT_METHOD = 'pengadaan-langsung';

    /**
     * The tender methods: every method but Pengadaan Langsung.
     *
     * @var list<string>
     */
    public const TENDER = [
        'penunjukan-langsung',
        'lelang-terbuka-1-tahap-1-sampul',
        'lelang-terbuka-1-tahap-2-sampul',
    ];

    /**
     * The tender with two envelopes.
     *
     * @var list<string>
     */
    public const TWO_ENVELOPES = ['lelang-terbuka-1-tahap-2-sampul'];

    /**
     * The tender's own documents, by code: their name.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'ba-aanwijzing-lelang' => 'Berita Acara Pemberian Penjelasan (Aanwijzing) - Lelang',
        'ba-pembukaan-sampul-1' => 'Berita Acara Pembukaan Penawaran Sampul 1',
        'ba-evaluasi-administrasi' => 'Evaluasi Kualifikasi Administrasi (Lampiran 1)',
        'ba-evaluasi-keuangan' => 'Evaluasi Kualifikasi Keuangan (Lampiran 2)',
        'ba-evaluasi-teknis-lelang' => 'Evaluasi Kualifikasi Teknis (Lampiran 3) - Lelang',
        'pengumuman-evaluasi-sampul-1' => 'Pengumuman Hasil Evaluasi Sampul 1',
        'ba-pembukaan-sampul-2' => 'Berita Acara Pembukaan Penawaran Sampul 2',
        'ba-negosiasi-penawaran' => 'Berita Acara Negosiasi Penawaran',
        'ba-hasil-pengadaan' => 'Berita Acara Hasil Pengadaan',
        'usulan-calon-pemenang' => 'Nota Dinas Usulan Calon Pemenang',
        'ba-penetapan-pemenang' => 'Berita Acara Penetapan Pemenang',
        'pengumuman-pemenang' => 'Pengumuman Pemenang',
        'sppbj' => 'Surat Penunjukan Penyedia Barang/Jasa (SPPBJ)',
    ];

    /**
     * The methods each document is asked for under; a document not listed is
     * asked for under every method.
     *
     * @var array<string, list<string>>
     */
    public const METHODS = [
        // Pengadaan Langsung's own, which the tenders have a version of.
        'ba-aanwijzing' => [self::DEFAULT_METHOD],
        'ba-evaluasi-teknis' => [self::DEFAULT_METHOD],
        // The tenders'.
        'ba-aanwijzing-lelang' => self::TENDER,
        'ba-evaluasi-administrasi' => self::TENDER,
        'ba-evaluasi-keuangan' => self::TENDER,
        'ba-evaluasi-teknis-lelang' => self::TENDER,
        'ba-negosiasi-penawaran' => self::TENDER,
        'ba-hasil-pengadaan' => self::TENDER,
        'usulan-calon-pemenang' => self::TENDER,
        'ba-penetapan-pemenang' => self::TENDER,
        'pengumuman-pemenang' => self::TENDER,
        'sppbj' => self::TENDER,
        // The two envelopes'.
        'ba-pembukaan-sampul-1' => self::TWO_ENVELOPES,
        'pengumuman-evaluasi-sampul-1' => self::TWO_ENVELOPES,
        'ba-pembukaan-sampul-2' => self::TWO_ENVELOPES,
    ];

    /**
     * The documents of the "Proses SMART SCM & Berita Acara" step, in the
     * order the procurement runs through them.
     *
     * @var list<string>
     */
    public const STEP = [
        'proses-smart-scm',
        'ba-aanwijzing',
        'ba-aanwijzing-lelang',
        'ba-pembukaan-sampul-1',
        'lampiran-bapp',
        'ba-evaluasi-administrasi',
        'ba-evaluasi-keuangan',
        'ba-evaluasi-teknis',
        'ba-evaluasi-teknis-lelang',
        'ba-evaluasi-harga',
        'ba-hasil-evaluasi',
        'pengumuman-evaluasi-sampul-1',
        'ba-pembukaan-sampul-2',
        'ba-klarifikasi',
        'ba-negosiasi-penawaran',
        'ba-hasil-pengadaan',
        'usulan-calon-pemenang',
        'ba-penetapan-pemenang',
        'pengumuman-pemenang',
        'sppbj',
    ];

    /**
     * The name of the step they are filed on.
     */
    public const STEP_NAME = 'Proses SMART SCM & Berita Acara';
}
