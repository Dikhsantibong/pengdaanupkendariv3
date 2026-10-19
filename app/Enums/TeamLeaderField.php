<?php

namespace App\Enums;

/**
 * The bidang a TL of a Unit Layanan leads across its units.
 */
enum TeamLeaderField: string
{
    case Pemeliharaan = 'pemeliharaan';
    case Operasi = 'operasi';
    case K3l = 'k3l';

    /**
     * The name printed after "TL".
     */
    public function label(): string
    {
        return match ($this) {
            self::Pemeliharaan => 'PEMELIHARAAN',
            self::Operasi => 'OPERASI',
            self::K3l => 'K3L',
        };
    }

    /**
     * The bidang a stored "TL ..." jabatan names, if it is one of these.
     */
    public static function fromPosition(string $position): ?self
    {
        $bidang = trim((string) preg_replace('/^TL\s+/u', '', mb_strtoupper(trim($position))));

        foreach (self::cases() as $field) {
            if ($field->label() === $bidang) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The bidang as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $field): array => ['value' => $field->value, 'label' => $field->label()],
            self::cases(),
        );
    }
}
