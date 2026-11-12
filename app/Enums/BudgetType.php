<?php

namespace App\Enums;

enum BudgetType: string
{
    case Investasi = 'investasi';
    case Operasi = 'operasi';
    case EnergiPrimer = 'energi_primer';

    /**
     * The human-readable label for the budget type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Investasi => 'Anggaran Investasi (AI)',
            self::Operasi => 'Anggaran Operasi (AO PEMELIHARAAN)',
            self::EnergiPrimer => 'Anggaran Pendukung Energi Primer',
        };
    }
}
