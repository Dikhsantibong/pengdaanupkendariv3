<?php

namespace App\Enums;

enum ActivityType: string
{
    case Dibuat = 'dibuat';
    case UsulanDiajukan = 'usulan_diajukan';
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
    case PengadaanSelesai = 'pengadaan_selesai';

    /**
     * Get the human readable label for the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dibuat => 'Pengadaan Dibuat',
            self::UsulanDiajukan => 'Usulan Diajukan',
            self::UsulanDisetujui => 'Usulan Disetujui Direksi Pekerjaan',
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
            self::PengadaanSelesai => 'Pengadaan Selesai',
        };
    }
}
