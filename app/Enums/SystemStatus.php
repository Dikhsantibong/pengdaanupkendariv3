<?php

namespace App\Enums;

/**
 * The progress statuses the system sets by itself, in journey order.
 *
 * Each one is a row of the Status Progres master data, found by its
 * `system_key`: its name can be changed there, its meaning cannot. Only
 * Batal is still chosen by hand (and lifted again by hand).
 */
enum SystemStatus: string
{
    case PenyusunanTor = 'penyusunan_tor';
    case DraftNodisUsulan = 'draft_nodis_usulan';
    case ApprovalUsulan = 'approval_usulan';
    case RevisiUsulan = 'revisi_usulan';
    case InisiasiRendan = 'inisiasi_rendan';
    case PenyusunanRks = 'penyusunan_rks';
    case MenungguPenawaran = 'menunggu_penawaran';
    case DraftNodisPengadaan = 'draft_nodis_pengadaan';
    case DraftNodisLaksdan = 'draft_nodis_laksdan';
    case ApprovalIcc = 'approval_icc';
    case RevisiPerencanaan = 'revisi_perencanaan';
    case InisiasiLaksdan = 'inisiasi_laksdan';
    case PenyusunanHps = 'penyusunan_hps';
    case ProsesSmartScm = 'proses_smart_scm';
    case PenyusunanKontrak = 'penyusunan_kontrak';
    case PenyusunanSuratPesanan = 'penyusunan_surat_pesanan';
    case ApprovalTlPengadaan = 'approval_tl_pengadaan';
    case RevisiPelaksanaan = 'revisi_pelaksanaan';
    case Terkontrak = 'terkontrak';
    case Selesai = 'selesai';
    case Batal = 'batal';

    /**
     * The name the status is created with.
     */
    public function label(): string
    {
        return match ($this) {
            self::PenyusunanTor => 'Penyusunan TOR',
            self::DraftNodisUsulan => 'Draft Nodis Usulan',
            self::ApprovalUsulan => 'Approval Usulan',
            self::RevisiUsulan => 'Revisi Usulan',
            self::InisiasiRendan => 'Inisiasi Rendan',
            self::PenyusunanRks => 'Penyusunan RKS',
            self::MenungguPenawaran => 'Menunggu Penawaran',
            self::DraftNodisPengadaan => 'Draft Nodis ke Pengadaan',
            self::DraftNodisLaksdan => 'Draft Nota Dinas ke Laksdan',
            self::ApprovalIcc => 'Menunggu Approval ICC',
            self::RevisiPerencanaan => 'Revisi Perencanaan',
            self::InisiasiLaksdan => 'Inisiasi Laksdan',
            self::PenyusunanHps => 'Penyusunan HPS',
            self::ProsesSmartScm => 'Proses SMART SCM',
            self::PenyusunanKontrak => 'Penyusunan Kontrak',
            self::PenyusunanSuratPesanan => 'Penyusunan Surat Pesanan',
            self::ApprovalTlPengadaan => 'Menunggu Approval TL Pengadaan',
            self::RevisiPelaksanaan => 'Revisi Pelaksanaan',
            self::Terkontrak => 'Terkontrak',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    /**
     * The category the status counts under on dashboards and reports.
     */
    public function category(): StatusCategory
    {
        return match ($this) {
            self::PenyusunanTor, self::DraftNodisUsulan,
            self::ApprovalUsulan, self::RevisiUsulan => StatusCategory::Pending,
            self::Selesai => StatusCategory::Selesai,
            self::Batal => StatusCategory::Batal,
            default => StatusCategory::Berjalan,
        };
    }

    /**
     * When the status applies, shown on the Status Progres master data.
     */
    public function rule(): string
    {
        return match ($this) {
            self::PenyusunanTor => 'Usulan: TOR belum diunggah bertanda tangan.',
            self::DraftNodisUsulan => 'Usulan: TOR sudah diunggah, Nota Dinas Usulan belum.',
            self::ApprovalUsulan => 'Usulan menunggu persetujuan TL / Asman.',
            self::RevisiUsulan => 'Usulan dikembalikan ke pengusul.',
            self::InisiasiRendan => 'Usulan disetujui, menunggu TL ICC menunjuk PIC Perencana.',
            self::PenyusunanRks => 'SPK/PJ: RKS, UPB, HPE belum digenerate atau RAB belum diunggah.',
            self::MenungguPenawaran => 'SPK/PJ: Penawaran harga belum diunggah.',
            self::DraftNodisPengadaan => 'SPK/PJ: Nota Dinas ke Pengadaan belum diunggah / belum diajukan.',
            self::DraftNodisLaksdan => 'SPPL: PIC ditunjuk, dokumen rendan belum lengkap / belum diajukan.',
            self::ApprovalIcc => 'Perencanaan menunggu persetujuan TL ICC.',
            self::RevisiPerencanaan => 'Perencanaan dikembalikan ke PIC Perencana.',
            self::InisiasiLaksdan => 'Perencanaan disetujui, menunggu TL Pengadaan menunjuk PIC Pelaksana.',
            self::PenyusunanHps => 'SPK/PJ: Penyusunan HPS belum diunggah.',
            self::ProsesSmartScm => 'SPK/PJ: dokumen SMART SCM dan seluruh Berita Acara belum lengkap.',
            self::PenyusunanKontrak => 'SPK/PJ: kontrak, jaminan, dan tahapan akhir pelaksanaan.',
            self::PenyusunanSuratPesanan => 'SPPL: BA Negosiasi, Surat Pesanan, dan tahapan pelaksanaan.',
            self::ApprovalTlPengadaan => 'Pelaksanaan menunggu persetujuan TL Pengadaan.',
            self::RevisiPelaksanaan => 'Pelaksanaan dikembalikan ke PIC Pelaksana.',
            self::Terkontrak => 'Pelaksanaan disetujui TL Pengadaan; proses Penyerahan dan Pembayaran.',
            self::Selesai => 'Pembayaran dicatat Keuangan, pengadaan ditutup.',
            self::Batal => 'Dipilih manual lewat tombol Batalkan Pengadaan.',
        };
    }
}
