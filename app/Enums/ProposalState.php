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
    /** Waiting for the proposer's team leader. */
    case MenungguPersetujuanTl = 'menunggu_persetujuan_tl';

    /** Waiting for the division's Direksi Pekerjaan (its Asman). */
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Dikembalikan = 'dikembalikan';
    case Disetujui = 'disetujui';

    /**
     * The name shown on screen.
     */
    public function label(): string
    {
        return match ($this) {
            self::MenungguPersetujuanTl => 'Menunggu Persetujuan TL',
            self::MenungguPersetujuan => 'Menunggu Persetujuan Asman',
            self::Dikembalikan => 'Dikembalikan ke Pengusul',
            self::Disetujui => 'Disetujui Direksi Pekerjaan',
        };
    }
}
