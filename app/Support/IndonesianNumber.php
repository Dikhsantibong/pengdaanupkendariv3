<?php

namespace App\Support;

/**
 * Spells a whole number out in Indonesian, for the "terbilang" lines that
 * official procurement documents require.
 */
class IndonesianNumber
{
    /**
     * The digits below twelve that have their own word.
     *
     * @var array<int, string>
     */
    private const UNITS = [
        'nol', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    /**
     * The scale words, from thousand upwards.
     *
     * @var array<int, array{0: int, 1: string}>
     */
    private const SCALES = [
        [1_000_000_000_000, 'triliun'],
        [1_000_000_000, 'miliar'],
        [1_000_000, 'juta'],
        [1_000, 'ribu'],
    ];

    /**
     * Spell a number, e.g. 1250 becomes "seribu dua ratus lima puluh".
     */
    public static function spell(int|float $value): string
    {
        $number = (int) floor(abs($value));
        $words = self::compose($number);

        return $value < 0 ? 'minus '.$words : $words;
    }

    /**
     * Spell an amount of rupiah, e.g. "seribu rupiah".
     */
    public static function spellRupiah(int|float $value): string
    {
        return self::spell($value).' rupiah';
    }

    /**
     * Write a positive number in roman numerals, e.g. 4 becomes "IV".
     */
    public static function roman(int $value): string
    {
        $numerals = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90,
            'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $roman = '';

        foreach ($numerals as $numeral => $amount) {
            while ($value >= $amount) {
                $roman .= $numeral;
                $value -= $amount;
            }
        }

        return $roman;
    }

    /**
     * Recursively build the words for a positive number.
     */
    protected static function compose(int $number): string
    {
        if ($number < 12) {
            return self::UNITS[$number];
        }

        if ($number < 20) {
            return self::compose($number - 10).' belas';
        }

        if ($number < 100) {
            return self::compose(intdiv($number, 10)).' puluh'.self::remainder($number % 10);
        }

        if ($number < 200) {
            return 'seratus'.self::remainder($number % 100);
        }

        if ($number < 1_000) {
            return self::compose(intdiv($number, 100)).' ratus'.self::remainder($number % 100);
        }

        foreach (self::SCALES as [$scale, $label]) {
            if ($number < $scale) {
                continue;
            }

            $prefix = $scale === 1_000 && intdiv($number, $scale) === 1
                ? 'seribu'
                : self::compose(intdiv($number, $scale)).' '.$label;

            return $prefix.self::remainder($number % $scale);
        }

        return self::UNITS[0];
    }

    /**
     * Append the remaining part of a number when there is one.
     */
    protected static function remainder(int $remainder): string
    {
        return $remainder === 0 ? '' : ' '.self::compose($remainder);
    }
}
