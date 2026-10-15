<?php

namespace App\Enums;

/**
 * The two halves of the Keuangan stage, each shown on its own tab.
 *
 * Penyerahan holds the papers the partner hands over with the goods or the
 * work; Pembayaran holds the papers the payment request has to carry. Both
 * lists come from the contract's own articles on delivery and payment.
 */
enum FinanceSection: string
{
    case Penyerahan = 'penyerahan';
    case Pembayaran = 'pembayaran';

    /**
     * The name shown on the tab and in forms.
     */
    public function label(): string
    {
        return match ($this) {
            self::Penyerahan => 'Penyerahan',
            self::Pembayaran => 'Pembayaran',
        };
    }

    /**
     * The sections as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $section): array => ['value' => $section->value, 'label' => $section->label()],
            self::cases(),
        );
    }
}
