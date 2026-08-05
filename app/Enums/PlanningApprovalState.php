<?php

namespace App\Enums;

enum PlanningApprovalState: string
{
    case BelumDiajukan = 'belum_diajukan';
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    /**
     * Get the human readable label for the approval state.
     */
    public function label(): string
    {
        return match ($this) {
            self::BelumDiajukan => 'Belum Diajukan',
            self::MenungguPersetujuan => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    /**
     * Get every approval state as a selectable option.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $state): array => ['value' => $state->value, 'label' => $state->label()],
            self::cases(),
        );
    }
}
