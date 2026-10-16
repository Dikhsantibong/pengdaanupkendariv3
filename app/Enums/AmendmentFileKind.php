<?php

namespace App\Enums;

/**
 * The four papers an amendment request files.
 */
enum AmendmentFileKind: string
{
    case NotulenRapat = 'notulen_rapat';
    case DaftarHadir = 'daftar_hadir';
    case NotaDinas = 'nota_dinas';
    case SuratPermohonan = 'surat_permohonan';

    /**
     * The name shown on the tab.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotulenRapat => 'Notulen Rapat',
            self::DaftarHadir => 'Daftar Hadir',
            self::NotaDinas => 'Nota Dinas Amandemen',
            self::SuratPermohonan => 'Surat Permohonan Amandemen',
        };
    }
}
