<?php

namespace App\Enums;

/**
 * The rank of an official on Data Master → Manager Unit, highest first: the
 * Manager of UP Kendari, the Manager of a Unit Layanan holding several units,
 * a TL leading one bidang across several units, and a TL OPHAR holding one.
 */
enum UnitManagerLevel: string
{
    case Manager = 'manager';
    case ManagerUl = 'manager_ul';
    case Tl = 'tl';
    case TlOphar = 'tl_ophar';

    /**
     * The jabatan printed for this rank.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manager => 'MANAGER',
            self::ManagerUl => 'MANAGER UL',
            self::Tl => 'TL',
            self::TlOphar => 'TL OPHAR',
        };
    }

    /**
     * What the rank holds, shown beside it in the dropdown.
     */
    public function scope(): string
    {
        return match ($this) {
            self::Manager => 'jabatan tertinggi',
            self::ManagerUl => 'memegang beberapa unit',
            self::Tl => 'satu bidang di beberapa unit',
            self::TlOphar => 'memegang 1 unit',
        };
    }

    /**
     * Whether the rank holds several units.
     */
    public function holdsSeveralUnits(): bool
    {
        return $this === self::ManagerUl || $this === self::Tl;
    }

    /**
     * Whether the rank holds exactly one unit.
     */
    public function holdsOneUnit(): bool
    {
        return $this === self::TlOphar;
    }

    /**
     * The rank a stored jabatan was written for.
     */
    public static function fromPosition(string $position): self
    {
        $position = mb_strtoupper(trim($position));

        return match (true) {
            str_starts_with($position, 'MANAGER UL') => self::ManagerUl,
            str_starts_with($position, 'TL OPHAR') => self::TlOphar,
            str_starts_with($position, 'TL ') => self::Tl,
            default => self::Manager,
        };
    }

    /**
     * The ranks as select options, highest first.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $level): array => ['value' => $level->value, 'label' => $level->label().' — '.$level->scope()],
            self::cases(),
        );
    }
}
