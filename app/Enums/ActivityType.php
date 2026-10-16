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
            self::PelaksanaanDitolak, self::DiajukanKeKeuangan => 'Pelaksanaan',
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
            self::PelaksanaanDisetujui => 'disetujui',
            self::UsulanDikembalikan, self::PerencanaanDitolak, self::PelaksanaanDitolak => 'dikembalikan',
            self::PicDitunjuk => 'ditunjuk',
            self::Dibuat => 'dibuat',
            self::PembayaranSelesai, self::PengadaanSelesai => 'selesai',
            default => 'diajukan',
        };
    }
}
