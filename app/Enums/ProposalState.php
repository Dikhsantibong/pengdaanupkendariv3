<?php

namespace App\Enums;

/**
 * Where a work proposal stands with the Direksi Pekerjaan of its division.
 *
 * A proposal only reaches planning once approved. Procurements registered
 * directly by procurement staff count as approved from the start.
 */
enum ProposalState: string
{
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Dikembalikan = 'dikembalikan';
    case Disetujui = 'disetujui';

    /**
     * The name shown on screen.
     */
    public function label(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'Menunggu Persetujuan Direksi Pekerjaan',
            self::Dikembalikan => 'Dikembalikan ke Pengusul',
            self::Disetujui => 'Disetujui Direksi Pekerjaan',
        };
    }
}
