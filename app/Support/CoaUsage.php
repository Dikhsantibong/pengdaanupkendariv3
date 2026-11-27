<?php

namespace App\Support;

use App\Models\Procurement;

/**
 * Where each COA code is used: the procurements (usulan pekerjaan included)
 * whose "Nomor COA" lists it. A procurement keeps its COA numbers as one
 * comma separated list, so a code is matched as a whole entry, ignoring case.
 */
class CoaUsage
{
    /**
     * The COA codes in a procurement's "Nomor COA" list.
     *
     * @return list<string>
     */
    public static function codesOf(?string $coaNumber): array
    {
        $parts = preg_split('/[,;\n]+/', (string) $coaNumber) ?: [];

        return array_values(array_unique(array_filter(
            array_map(fn (string $part): string => mb_strtoupper(trim($part)), $parts),
            fn (string $part): bool => $part !== '',
        )));
    }

    /**
     * The procurements using each code, keyed by the upper-cased code.
     *
     * @param  list<string>|null  $codes  Only these codes; every code when null.
     * @return array<string, list<array{id: int, number: string, name: string}>>
     */
    public static function map(?array $codes = null): array
    {
        $wanted = $codes === null ? null : array_flip(array_map('mb_strtoupper', $codes));

        if ($wanted === []) {
            return [];
        }

        $map = [];

        Procurement::query()
            ->whereNotNull('coa_number')
            ->where('coa_number', '!=', '')
            ->orderByDesc('id')
            ->select(['id', 'number', 'name', 'coa_number'])
            ->each(function (Procurement $procurement) use (&$map, $wanted): void {
                foreach (self::codesOf($procurement->coa_number) as $code) {
                    if ($wanted !== null && ! isset($wanted[$code])) {
                        continue;
                    }

                    $map[$code][] = [
                        'id' => $procurement->id,
                        'number' => (string) $procurement->number,
                        'name' => (string) $procurement->name,
                    ];
                }
            });

        return $map;
    }

    /**
     * How many procurements use each code, keyed by the upper-cased code.
     *
     * @param  list<string>|null  $codes
     * @return array<string, int>
     */
    public static function counts(?array $codes = null): array
    {
        return array_map('count', self::map($codes));
    }

    /**
     * Replace a code with another in every procurement that lists it, so a COA
     * corrected by Keuangan stays in step with the usulan that use it.
     *
     * @return int The procurements changed.
     */
    public static function rename(string $from, string $to): int
    {
        $from = mb_strtoupper(trim($from));
        $to = trim($to);

        if ($from === '' || $to === '' || $from === mb_strtoupper($to)) {
            return 0;
        }

        $changed = 0;

        Procurement::query()
            ->withTrashed()
            ->where('coa_number', 'like', '%'.$from.'%')
            ->select(['id', 'coa_number'])
            ->each(function (Procurement $procurement) use ($from, $to, &$changed): void {
                $parts = array_map('trim', preg_split('/[,;\n]+/', (string) $procurement->coa_number) ?: []);

                if (! in_array($from, array_map('mb_strtoupper', $parts), true)) {
                    return;
                }

                $renamed = array_values(array_unique(array_filter(
                    array_map(fn (string $part): string => mb_strtoupper($part) === $from ? $to : $part, $parts),
                    fn (string $part): bool => $part !== '',
                )));

                // A quiet update: only the reference number changes.
                Procurement::query()->withTrashed()->whereKey($procurement->id)->update(['coa_number' => implode(', ', $renamed)]);
                $changed++;
            });

        return $changed;
    }
}
