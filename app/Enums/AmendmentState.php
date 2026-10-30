<?php

namespace App\Enums;

/**
 * Where a contract amendment stands: requested by the proposer, agreed by
 * their TL and the division's Asman, then numbered, dated and issued by the
 * execution side (Laksdan).
 *
 * The stored values predate that flow: "menunggu_tl_pengadaan" is the
 * request waiting with Laksdan, "disetujui" the amendment issued.
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
            // The Asman, or the TL leading K3 KAM / Lingkungan.
            self::MenungguPersetujuanAsman => 'Menunggu Persetujuan Direksi Pekerjaan',
            self::MenungguTlPengadaan => 'Di Laksdan (Penerbitan Amandemen)',
            self::Disetujui => 'Amandemen Terbit',
            self::Dikembalikan => 'Dikembalikan',
        };
    }

    /**
     * Whether the amendment waits on someone: the TL or Asman's decision,
     * or Laksdan issuing it.
     */
    public function isWaiting(): bool
    {
        return in_array($this, [self::MenungguPersetujuanTl, self::MenungguPersetujuanAsman, self::MenungguTlPengadaan], true);
    }

    /**
     * Whether it waits on the TL or Asman's decision.
     */
    public function awaitsApproval(): bool
    {
        return in_array($this, [self::MenungguPersetujuanTl, self::MenungguPersetujuanAsman], true);
    }

    /**
     * Whether the proposer may still change it.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Dikembalikan], true);
    }
}
