<?php

namespace App\Enums;

/**
 * The eight ordered segments that compose a UP Kendari COA code.
 */
enum CoaSegment: string
{
    case Segment1 = 'segment_1';
    case Segment2 = 'segment_2';
    case Segment3 = 'segment_3';
    case Segment4 = 'segment_4';
    case Segment5 = 'segment_5';
    case Segment6 = 'segment_6';
    case Segment7 = 'segment_7';
    case ExpenseElement = 'expense_element';

    /**
     * Full display label with segment number.
     */
    public function label(): string
    {
        return match ($this) {
            self::Segment1 => 'Segment 1 · Struktur Bisnis',
            self::Segment2 => 'Segment 2 · District',
            self::Segment3 => 'Segment 3 · Fungsi',
            self::Segment4 => 'Segment 4 · Lokasi',
            self::Segment5 => 'Segment 5 · Unit',
            self::Segment6 => 'Segment 6 · Sub Unit',
            self::Segment7 => 'Segment 7 · Aktivitas',
            self::ExpenseElement => 'Expense Element',
        };
    }

    /**
     * Short display label used in form labels and tables.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Segment1 => 'Struktur Bisnis',
            self::Segment2 => 'District',
            self::Segment3 => 'Fungsi',
            self::Segment4 => 'Lokasi',
            self::Segment5 => 'Unit',
            self::Segment6 => 'Sub Unit',
            self::Segment7 => 'Aktivitas',
            self::ExpenseElement => 'Expense Element',
        };
    }

    /**
     * Ordered option payload for select dropdowns and filter tabs.
     *
     * @return array<int, array{value: string, label: string, shortLabel: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $segment): array => [
                'value' => $segment->value,
                'label' => $segment->label(),
                'shortLabel' => $segment->shortLabel(),
            ],
            self::cases(),
        );
    }
}
