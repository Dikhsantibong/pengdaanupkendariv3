<?php

namespace App\Enums;

/**
 * Where the payment of a job stands: filed by a Keuangan staff member, then
 * approved by their TL (TL Keuangan) and by the Asman of that TL's division
 * (Asman Business Support). The Asman's approval records the payment and
 * closes the job.
 */
enum PaymentApprovalState: string
{
    case BelumDiajukan = 'belum_diajukan';
    case MenungguTl = 'menunggu_tl';
    case MenungguAsman = 'menunggu_asman';
    case Disetujui = 'disetujui';
    case Dikembalikan = 'dikembalikan';

    /**
     * Get the human readable label for the state.
     */
    public function label(): string
    {
        return match ($this) {
            self::BelumDiajukan => 'Belum Diajukan',
            self::MenungguTl => 'Menunggu Persetujuan TL Keuangan',
            self::MenungguAsman => 'Menunggu Persetujuan Asman',
            self::Disetujui => 'Disetujui, Lunas',
            self::Dikembalikan => 'Dikembalikan',
        };
    }

    /**
     * Whether the payment waits on a decision.
     */
    public function isAwaiting(): bool
    {
        return in_array($this, [self::MenungguTl, self::MenungguAsman], true);
    }

    /**
     * Whether the Keuangan staff may still fill in and submit it.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::BelumDiajukan, self::Dikembalikan], true);
    }
}
