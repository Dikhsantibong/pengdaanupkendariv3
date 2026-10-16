<?php

namespace App\Enums;

/**
 * Where a contract amendment stands: drafted by the proposer, decided by
 * their TL, the division's Asman, then TL Pengadaan.
 */
enum AmendmentState: string
{
    case Draft = 'draft';
    case MenungguPersetujuanTl = 'menunggu_persetujuan_tl';
    case MenungguPersetujuanAsman = 'menunggu_persetujuan_asman';
    case MenungguTlPengadaan = 'menunggu_tl_pengadaan';
    case Disetujui = 'disetujui';
    case Dikembalikan = 'dikembalikan';

    /**
     * The human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::MenungguPersetujuanTl => 'Menunggu Persetujuan TL',
            self::MenungguPersetujuanAsman => 'Menunggu Persetujuan Asman',
            self::MenungguTlPengadaan => 'Menunggu Persetujuan TL Pengadaan',
            self::Disetujui => 'Disetujui',
            self::Dikembalikan => 'Dikembalikan',
        };
    }

    /**
     * Whether the amendment waits on someone's decision.
     */
    public function isWaiting(): bool
    {
        return in_array($this, [self::MenungguPersetujuanTl, self::MenungguPersetujuanAsman, self::MenungguTlPengadaan], true);
    }

    /**
     * Whether the proposer may still change it.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Dikembalikan], true);
    }
}
