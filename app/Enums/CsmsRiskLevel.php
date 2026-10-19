<?php

namespace App\Enums;

/**
 * The CSMS risk level of a job, stated by the user who proposes it: it sets
 * how far the partner's safety management is checked.
 */
enum CsmsRiskLevel: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Extreme = 'extreme';

    /**
     * The name shown in forms and documents.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Moderate => 'Moderat',
            self::High => 'High',
            self::Extreme => 'Extreme',
        };
    }

    /**
     * The levels as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $level): array => ['value' => $level->value, 'label' => $level->label()],
            self::cases(),
        );
    }
}
