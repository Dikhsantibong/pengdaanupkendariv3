<?php

namespace App\Enums;

enum ProcurementStage: string
{
    case Perencanaan = 'perencanaan';
    case Pelaksanaan = 'pelaksanaan';

    /**
     * Get the human readable label for the stage.
     */
    public function label(): string
    {
        return match ($this) {
            self::Perencanaan => 'Perencanaan',
            self::Pelaksanaan => 'Pelaksanaan',
        };
    }

    /**
     * Get every stage as a selectable option.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $stage): array => ['value' => $stage->value, 'label' => $stage->label()],
            self::cases(),
        );
    }
}
