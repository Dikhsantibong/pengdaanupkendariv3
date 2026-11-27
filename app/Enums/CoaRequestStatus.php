<?php

namespace App\Enums;

/**
 * Lifecycle state of a COA activation request.
 */
enum CoaRequestStatus: string
{
    case Draft = 'draft';
    case Pending = 'menunggu_aktivasi';
    case Active = 'aktif';

    /**
     * Human readable Indonesian label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu Aktivasi',
            self::Active => 'Aktif',
        };
    }

    /**
     * Option payload for status filters.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
