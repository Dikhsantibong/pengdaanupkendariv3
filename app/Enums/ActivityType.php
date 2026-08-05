<?php

namespace App\Enums;

enum ActivityType: string
{
    case Dibuat = 'dibuat';
    case Diperbarui = 'diperbarui';
    case StatusDiubah = 'status_diubah';
    case PicDitunjuk = 'pic_ditunjuk';
    case ChecklistDiperbarui = 'checklist_diperbarui';
    case PerencanaanDiajukan = 'perencanaan_diajukan';
    case PerencanaanDisetujui = 'perencanaan_disetujui';
    case PerencanaanDitolak = 'perencanaan_ditolak';
    case DokumenDigenerate = 'dokumen_digenerate';
    case PengadaanSelesai = 'pengadaan_selesai';

    /**
     * Get the human readable label for the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dibuat => 'Pengadaan Dibuat',
            self::Diperbarui => 'Data Diperbarui',
            self::StatusDiubah => 'Status Diubah',
            self::PicDitunjuk => 'PIC Ditunjuk',
            self::ChecklistDiperbarui => 'Checklist Diperbarui',
            self::PerencanaanDiajukan => 'Perencanaan Diajukan',
            self::PerencanaanDisetujui => 'Perencanaan Disetujui',
            self::PerencanaanDitolak => 'Perencanaan Ditolak',
            self::DokumenDigenerate => 'Dokumen Digenerate',
            self::PengadaanSelesai => 'Pengadaan Selesai',
        };
    }
}
