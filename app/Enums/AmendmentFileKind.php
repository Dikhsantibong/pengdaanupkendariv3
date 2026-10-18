<?php

namespace App\Enums;

/**
 * The papers an amendment request files: the Notulen and the Nota Dinas
 * usulan are required, the partner's request letter is optional. The
 * attendance list is no longer asked for; files already filed stay.
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
            self::NotaDinas => 'Dokumen Nota Dinas Usulan',
            self::SuratPermohonan => 'Surat Permohonan Mitra (opsional)',
        };
    }

    /**
     * Whether the request is held back until this paper is filed.
     */
    public function isRequired(): bool
    {
        return in_array($this, [self::NotulenRapat, self::NotaDinas], true);
    }

    /**
     * The papers the request asks for, in order.
     *
     * @return array<int, self>
     */
    public static function asked(): array
    {
        return [self::NotaDinas, self::NotulenRapat, self::SuratPermohonan];
    }
}
