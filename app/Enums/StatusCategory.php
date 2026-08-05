<?php

namespace App\Enums;

enum StatusCategory: string
{
    case Pending = 'pending';
    case Batal = 'batal';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    /**
     * Get the human readable label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Batal => 'Batal',
            self::Berjalan => 'Berjalan',
            self::Selesai => 'Selesai',
        };
    }

    /**
     * Get every category as a selectable option.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}
