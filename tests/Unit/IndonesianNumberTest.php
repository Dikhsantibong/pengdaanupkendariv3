<?php

namespace Tests\Unit;

use App\Support\IndonesianNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IndonesianNumberTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_it_spells_numbers_in_indonesian(int $value, string $expected): void
    {
        $this->assertSame($expected, IndonesianNumber::spell($value));
    }

    /**
     * @return array<int, array{0: int, 1: string}>
     */
    public static function numbers(): array
    {
        return [
            [0, 'nol'],
            [7, 'tujuh'],
            [11, 'sebelas'],
            [12, 'dua belas'],
            [19, 'sembilan belas'],
            [20, 'dua puluh'],
            [21, 'dua puluh satu'],
            [100, 'seratus'],
            [101, 'seratus satu'],
            [175, 'seratus tujuh puluh lima'],
            [200, 'dua ratus'],
            [999, 'sembilan ratus sembilan puluh sembilan'],
            [1_000, 'seribu'],
            [1_001, 'seribu satu'],
            [2_500, 'dua ribu lima ratus'],
            [45_000_000, 'empat puluh lima juta'],
            [1_250_000_000, 'satu miliar dua ratus lima puluh juta'],
            [2_270_000_000, 'dua miliar dua ratus tujuh puluh juta'],
            [1_000_000_000_000, 'satu triliun'],
        ];
    }

    public function test_it_appends_the_currency_word(): void
    {
        $this->assertSame('seratus ribu rupiah', IndonesianNumber::spellRupiah(100_000));
    }

    public function test_it_ignores_the_fractional_part(): void
    {
        $this->assertSame('dua ribu rupiah', IndonesianNumber::spellRupiah(2_000.75));
    }

    public function test_it_handles_negative_values(): void
    {
        $this->assertSame('minus lima ratus', IndonesianNumber::spell(-500));
    }
}
