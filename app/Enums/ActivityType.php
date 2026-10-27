<?php

namespace App\Enums;

enum ActivityType: string
{
    case Dibuat = 'dibuat';
    case UsulanDiajukan = 'usulan_diajukan';
    case UsulanDisetujuiTl = 'usulan_disetujui_tl';
    case UsulanDisetujui = 'usulan_disetujui';
    case UsulanDikembalikan = 'usulan_dikembalikan';
    case Diperbarui = 'diperbarui';
    case StatusDiubah = 'status_diubah';
    case PicDitunjuk = 'pic_ditunjuk';
    case ChecklistDiperbarui = 'checklist_diperbarui';
    case PerencanaanDiajukan = 'perencanaan_diajukan';
    case PerencanaanDisetujui = 'perencanaan_disetujui';
    case PerencanaanDitolak = 'perencanaan_ditolak';
    case DokumenDigenerate = 'dokumen_digenerate';
    case DokumenDiedit = 'dokumen_diedit';
    case DokumenDitandatangani = 'dokumen_ditandatangani';
    case PelaksanaanDiajukan = 'pelaksanaan_diajukan';
    case PelaksanaanDisetujui = 'pelaksanaan_disetujui';
    case PelaksanaanDitolak = 'pelaksanaan_ditolak';
    case DiajukanKeKeuangan = 'diajukan_ke_keuangan';
    case AmandemenDiajukan = 'amandemen_diajukan';
    case AmandemenDisetujuiTl = 'amandemen_disetujui_tl';
    case AmandemenDisetujuiAsman = 'amandemen_disetujui_asman';
    case AmandemenDisetujui = 'amandemen_disetujui';
    case AmandemenDikembalikan = 'amandemen_dikembalikan';
    case AmandemenTerbit = 'amandemen_terbit';
    case DoDiajukan = 'do_diajukan';
    case DoDisetujuiTl = 'do_disetujui_tl';
    case DoDisetujuiAsman = 'do_disetujui_asman';
    case DoDiteruskanLaksdan = 'do_diteruskan_laksdan';
    case DoTerbit = 'do_terbit';
    case DoDikembalikan = 'do_dikembalikan';
    case PenyerahanDiajukan = 'penyerahan_diajukan';
    case PenyerahanDisetujui = 'penyerahan_disetujui';
    case PenyerahanDikembalikan = 'penyerahan_dikembalikan';
    case PembayaranDiajukan = 'pembayaran_diajukan';
    case PembayaranDisetujuiTl = 'pembayaran_disetujui_tl';
    case PembayaranDikembalikan = 'pembayaran_dikembalikan';
    case PembayaranSelesai = 'pembayaran_selesai';
    case PengadaanSelesai = 'pengadaan_selesai';

    /**
     * Get the human readable label for the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dibuat => 'Pengadaan Dibuat',
            self::UsulanDiajukan => 'Usulan Diajukan',
            self::UsulanDisetujui => 'Usulan Disetujui Asman',
            self::UsulanDisetujuiTl => 'Usulan Disetujui TL',
            self::UsulanDikembalikan => 'Usulan Dikembalikan',
            self::Diperbarui => 'Data Diperbarui',
            self::StatusDiubah => 'Status Diubah',
            self::PicDitunjuk => 'PIC Ditunjuk',
            self::ChecklistDiperbarui => 'Checklist Diperbarui',
            self::PerencanaanDiajukan => 'Perencanaan Diajukan',
            self::PerencanaanDisetujui => 'Perencanaan Disetujui',
            self::PerencanaanDitolak => 'Perencanaan Ditolak',
            self::DokumenDigenerate => 'Dokumen Digenerate',
            self::DokumenDiedit => 'Dokumen Diedit',
            self::DokumenDitandatangani => 'Dokumen Bertanda Tangan Diunggah',
            self::PelaksanaanDiajukan => 'Pelaksanaan Diajukan',
            self::PelaksanaanDisetujui => 'Pelaksanaan Disetujui',
            self::PelaksanaanDitolak => 'Pelaksanaan Ditolak',
            self::DiajukanKeKeuangan => 'Diserahkan ke Keuangan',
            self::AmandemenDiajukan => 'Amandemen Diajukan',
            self::AmandemenDisetujuiTl => 'Amandemen Disetujui TL',
            self::AmandemenDisetujuiAsman => 'Amandemen Disetujui Asman',
            self::AmandemenDisetujui => 'Amandemen Disetujui TL Pengadaan',
            self::AmandemenDikembalikan => 'Amandemen Dikembalikan',
            self::AmandemenTerbit => 'Amandemen Terbit',
            self::DoDiajukan => 'Delivery Order Diajukan',
            self::DoDisetujuiTl => 'Delivery Order Disetujui TL',
            self::DoDisetujuiAsman => 'Delivery Order Disetujui Asman',
            self::DoDiteruskanLaksdan => 'Nota Dinas DO Diteruskan ke Laksdan',
            self::DoTerbit => 'Delivery Order Terbit',
            self::DoDikembalikan => 'Delivery Order Dikembalikan',
            self::PenyerahanDiajukan => 'Penyerahan Diajukan',
            self::PenyerahanDisetujui => 'Penyerahan Disetujui TL Pengadaan',
            self::PenyerahanDikembalikan => 'Penyerahan Dikembalikan',
            self::PembayaranDiajukan => 'Pembayaran Diajukan',
            self::PembayaranDisetujuiTl => 'Pembayaran Disetujui TL Keuangan',
            self::PembayaranDikembalikan => 'Pembayaran Dikembalikan',
            self::PembayaranSelesai => 'Pembayaran Selesai',
            self::PengadaanSelesai => 'Pengadaan Selesai',
        };
    }

    /**
     * The step of the job's journey the activity belongs to, when it is one
     * of the decisions and hand-overs that move a job from the proposal to
     * the payment; null for routine edits (data, status, checklist,
     * documents), which are not part of the approval history.
     */
    public function approvalStage(): ?string
    {
        return match ($this) {
            self::Dibuat, self::UsulanDiajukan, self::UsulanDisetujuiTl,
            self::UsulanDisetujui, self::UsulanDikembalikan => 'Usulan',
            self::PicDitunjuk, self::PerencanaanDiajukan,
            self::PerencanaanDisetujui, self::PerencanaanDitolak => 'Perencanaan',
            self::PelaksanaanDiajukan, self::PelaksanaanDisetujui,
            self::PelaksanaanDitolak, self::DiajukanKeKeuangan,
            self::AmandemenDiajukan, self::AmandemenDisetujuiTl, self::AmandemenDisetujuiAsman,
            self::AmandemenDisetujui, self::AmandemenDikembalikan, self::AmandemenTerbit,
            self::DoDiajukan, self::DoDisetujuiTl, self::DoDisetujuiAsman,
            self::DoDiteruskanLaksdan, self::DoTerbit, self::DoDikembalikan => 'Pelaksanaan',
            self::PenyerahanDiajukan, self::PenyerahanDisetujui,
            self::PenyerahanDikembalikan => 'Penyerahan',
            self::PembayaranDiajukan, self::PembayaranDisetujuiTl,
            self::PembayaranDikembalikan => 'Pembayaran',
            self::PembayaranSelesai, self::PengadaanSelesai => 'Keuangan',
            default => null,
        };
    }

    /**
     * What the step came to, for the result column of the approval history.
     */
    public function approvalOutcome(): string
    {
        return match ($this) {
            self::UsulanDisetujuiTl, self::UsulanDisetujui, self::PerencanaanDisetujui,
            self::PelaksanaanDisetujui, self::AmandemenDisetujuiTl, self::AmandemenDisetujuiAsman,
            self::AmandemenDisetujui, self::DoDisetujuiTl, self::DoDisetujuiAsman,
            self::PenyerahanDisetujui, self::PembayaranDisetujuiTl => 'disetujui',
            self::DoTerbit, self::AmandemenTerbit => 'selesai',
            self::UsulanDikembalikan, self::PerencanaanDitolak, self::PelaksanaanDitolak,
            self::AmandemenDikembalikan, self::DoDikembalikan,
            self::PenyerahanDikembalikan, self::PembayaranDikembalikan => 'dikembalikan',
            self::PicDitunjuk => 'ditunjuk',
            self::Dibuat => 'dibuat',
            self::PembayaranSelesai, self::PengadaanSelesai => 'selesai',
            default => 'diajukan',
        };
    }
}
