<?php

namespace App\Enums;

/**
 * Where a Delivery Order (DO) of a KHS contract stands.
 *
 * The proposer requests it, their TL and the division's Asman agree, the
 * planning side (Rendan) adds its Nota Dinas DO and hands it to the
 * execution side (Laksdan), who dates, numbers and issues it.
 */
enum DeliveryOrderState: string
{
    case Draft = 'draft';
    case MenungguPersetujuanTl = 'menunggu_persetujuan_tl';
    case MenungguPersetujuanAsman = 'menunggu_persetujuan_asman';
    case DiRendan = 'di_rendan';
    case DiLaksdan = 'di_laksdan';
    case Terbit = 'terbit';
    case Dikembalikan = 'dikembalikan';

    /**
     * The human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft Pengusul',
            self::MenungguPersetujuanTl => 'Menunggu Persetujuan TL',
            self::MenungguPersetujuanAsman => 'Menunggu Persetujuan Asman',
            self::DiRendan => 'Di Rendan (Nota Dinas DO)',
            self::DiLaksdan => 'Di Laksdan (Penerbitan DO)',
            self::Terbit => 'DO Terbit',
            self::Dikembalikan => 'Dikembalikan ke Pengusul',
        };
    }

    /**
     * Whether the proposer may still change the request.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Dikembalikan], true);
    }

    /**
     * Whether it waits on the TL or Asman's decision.
     */
    public function awaitsApproval(): bool
    {
        return in_array($this, [self::MenungguPersetujuanTl, self::MenungguPersetujuanAsman], true);
    }
}
