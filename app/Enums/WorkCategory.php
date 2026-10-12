<?php

namespace App\Enums;

/**
 * Whether a contract buys goods or services.
 *
 * Which contract number formats ask for it is master data (Format No
 * Kontrak), and so is which template each category uses (Template Dokumen):
 * an SPK for goods and an SPK for services can carry different wording.
 */
enum WorkCategory: string
{
    case Barang = 'barang';
    case Jasa = 'jasa';

    /**
     * The name shown in forms and documents.
     */
    public function label(): string
    {
        return match ($this) {
            self::Barang => 'Barang',
            self::Jasa => 'Jasa',
        };
    }

    /**
     * The categories as select options.
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
