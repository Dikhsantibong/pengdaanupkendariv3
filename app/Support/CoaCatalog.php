<?php

namespace App\Support;

use App\Enums\CoaSegment;
use App\Models\CoaSegmentOption;
use Illuminate\Support\Str;

/**
 * Reference catalog and code composer for UP Kendari Chart of Accounts (COA).
 *
 * Options live in the `coa_segment_options` master data table so administrators
 * can maintain them from the Data Master screen while default reference codes
 * remain seedable consistently.
 */
class CoaCatalog
{
    /**
     * Default Segment 2 (District) code for UP Kendari.
     */
    public const DEFAULT_DISTRICT_CODE = 'KD';

    /**
     * Concatenate the eight COA segments into the final COA code string.
     */
    public static function composeCode(
        string $segment1,
        string $segment2,
        string $segment3,
        string $segment4,
        string $segment5,
        string $segment6,
        string $segment7,
        string $expenseElement,
    ): string {
        return Str::upper(implode('', [
            trim($segment1),
            trim($segment2),
            trim($segment3),
            trim($segment4),
            trim($segment5),
            trim($segment6),
            trim($segment7),
            trim($expenseElement),
        ]));
    }

    /**
     * Active segment options grouped by segment key for the Request COA form.
     *
     * @return array{
     *     options: array<string, array<int, array{code: string, name: string, description: string|null}>>,
     *     defaultSegment2: array{code: string, name: string}
     * }
     */
    public static function formOptions(): array
    {
        $grouped = [];

        foreach (CoaSegment::cases() as $segment) {
            $grouped[$segment->value] = [];
        }

        $records = CoaSegmentOption::query()
            ->active()
            ->ordered()
            ->get();

        foreach ($records as $record) {
            $grouped[$record->segment->value][] = [
                'code' => $record->code,
                'name' => $record->name,
                'description' => $record->description,
            ];
        }

        $firstDistrict = $grouped[CoaSegment::Segment2->value][0] ?? [
            'code' => self::DEFAULT_DISTRICT_CODE,
            'name' => 'UPDK KENDARI',
        ];

        return [
            'options' => $grouped,
            'defaultSegment2' => [
                'code' => $firstDistrict['code'],
                'name' => $firstDistrict['name'],
            ],
        ];
    }

    /**
     * Lookup map of `segment => [code => name]`, including inactive and
     * soft-deleted master options so historical requests retain readable names.
     *
     * @return array<string, array<string, string>>
     */
    public static function labelMap(): array
    {
        $map = [];

        foreach (CoaSegment::cases() as $segment) {
            $map[$segment->value] = [];
        }

        CoaSegmentOption::withTrashed()
            ->get(['segment', 'code', 'name'])
            ->each(function (CoaSegmentOption $option) use (&$map): void {
                $map[$option->segment->value][$option->code] = $option->name;
            });

        return $map;
    }

    /**
     * Seed the default UP Kendari COA segment reference data idempotently.
     */
    public static function seedDefaults(): void
    {
        $now = now();

        if (CoaSegmentOption::withTrashed()->count() === 0) {
            $rows = [];

            foreach (self::defaults() as $segment => $items) {
                foreach ($items as $index => [$code, $name]) {
                    $rows[] = [
                        'segment' => $segment,
                        'code' => Str::upper($code),
                        'name' => $name,
                        'description' => null,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            CoaSegmentOption::query()->insert($rows);

            return;
        }

        foreach (self::defaults() as $segment => $items) {
            foreach ($items as $index => [$code, $name]) {
                CoaSegmentOption::withTrashed()->updateOrCreate(
                    [
                        'segment' => $segment,
                        'code' => Str::upper($code),
                    ],
                    [
                        'name' => $name,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /**
     * Default UP Kendari COA segment options keyed by segment value.
     *
     * @return array<string, array<int, array{0: string, 1: string}>>
     */
    public static function defaults(): array
    {
        $units = [
            ['000', 'UNIT COMMON'],
        ];

        for ($number = 1; $number <= 24; $number++) {
            $units[] = [str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'UNIT '.$number];
        }

        $units = [
            ...$units,
            ['314', 'RENDAL OPR & PEMELIHARAAN'],
            ['306', 'RENDAL JASA OM'],
            ['309', 'ENERGI PRIMER'],
            ['326', 'LOGISTIK'],
            ['301', 'PENGELOLA SISTEM'],
            ['329', 'KEUANGAN'],
            ['323', 'K3 & KAM'],
            ['325', 'PENGADAAN'],
            ['322', 'LINGKUNGAN'],
            ['399', 'LAINNYA'],
            ['346', 'OPERASI'],
        ];

        return [
            CoaSegment::Segment1->value => [
                ['A', 'UPDK KENDARI'],
                ['B', 'ENABLER (SUPPORTING)'],
                ['C', 'JASA O & M'],
                ['D', 'ANAK PERUSAHAAN'],
            ],
            CoaSegment::Segment2->value => [
                ['KD', 'UPDK KENDARI'],
            ],
            CoaSegment::Segment3->value => [
                ['12', 'PLTU BATUBARA'],
                ['13', 'PLTU GAS'],
                ['14', 'PLTU MINYAK'],
                ['15', 'PLTG GAS'],
                ['16', 'PLTG MINYAK'],
                ['18', 'PLTD'],
                ['19', 'PLTMG'],
                ['21', 'PLTMH'],
                ['31', 'TATA USAHA/ADMINISTRASI'],
                ['36', 'GUDANG DAN PERSEDIAAN BAHAN'],
                ['37', 'BENGKEL'],
                ['38', 'LABORATORIUM'],
                ['39', 'WISMA DAN RUMAH DINAS'],
                ['40', 'SISTIM TELEKOMUNIKASI'],
                ['41', 'RUPA-RUPA JASA UMUM'],
                ['42', 'PENDIDIKAN DAN LATIHAN'],
                ['43', 'TEKNOLOGI INFORMASI'],
            ],
            CoaSegment::Segment4->value => [
                ['101', 'MANAGER UPDK'],
                ['104', 'ASS MAN OPR & PEMELIHARAAN 1'],
                ['151', 'ASS MAN OPR & PEMELIHARAAN 2'],
                ['102', 'ASS MAN ENGINEERING'],
                ['112', 'ASS MAN KEUANGAN & UMUM'],
                ['276', 'UPDK KENDARI'],
                ['277', 'BAUBAU'],
                ['278', 'KOLAKA'],
                ['279', 'POASIA'],
                ['280', 'WUA-WUA'],
                ['281', 'NII TANASA'],
                ['282', 'NII TANASA PJBS'],
                ['366', 'EREKE'],
                ['367', 'LADUMPI'],
                ['368', 'LANGARA'],
                ['369', 'LANIPA-NIPA'],
                ['372', 'MIKUASI'],
                ['373', 'RAHA'],
                ['374', 'RONGI'],
                ['375', 'SABILAMBO'],
                ['376', 'WANGI-WANGI'],
                ['377', 'WINNING'],
                ['378', 'PASARWAJO'],
                ['379', 'LAMBUYA'],
            ],
            CoaSegment::Segment5->value => $units,
            CoaSegment::Segment6->value => [
                ['01', 'TURBIN'],
                ['02', 'DIESEL'],
                ['03', 'GENERATOR'],
                ['04', 'BOILER'],
                ['05', 'HYDROGEN PLANT'],
                ['06', 'CHLORINATION PLANT'],
                ['07', 'WASTE WATER PLANT'],
                ['08', 'WATER TREATMENT PLANT'],
                ['11', 'FUEL OIL PLANT'],
                ['12', 'HEAVY VEHICLE'],
                ['13', 'LIGHT VEHICLE'],
                ['15', 'INFRASTRUCTURE'],
                ['16', 'OFFICE EQUIPMENT'],
                ['18', 'COMMON'],
                ['19', 'HOUSING'],
                ['20', 'OUTSIDE SUPPORTING PLANT'],
                ['21', 'DESALINATION PLANT'],
                ['22', 'ADMINISTRATIVE BUILDING'],
                ['23', 'WATER WAY SYSTEM'],
                ['28', 'BATTERY CHARGER'],
                ['29', 'CHARGE AIR SYSTEM'],
                ['30', 'TURBOCHARGER'],
                ['31', 'FUEL GAS SYSTEM'],
                ['32', 'COOLING GAS SYSTEM'],
                ['33', 'COMPRESSED AIR SYSTEM'],
                ['34', 'EXHAUST GAS SYSTEM'],
                ['35', 'FIRE FIGHTING'],
                ['37', 'MAIN TRAFO & AUX'],
                ['38', 'ENGINE'],
                ['99', 'LAINNYA'],
            ],
            CoaSegment::Segment7->value => [
                ['10', 'OPERASI'],
                ['11', 'OPERASI JASA O&M'],
                ['17', 'KIMIA & LABORATORIUM'],
                ['18', 'K3'],
                ['19', 'LINGKUNGAN'],
                ['20', 'PREVENTIVE MAINTENANCE'],
                ['21', 'PREDICTIVE MAINTENANCE'],
                ['22', 'CORRECTIVE MAINTENANCE'],
                ['23', 'EMERGENCY MAINTENANCE'],
                ['24', 'OVERHAUL / INSPECTION'],
                ['25', 'REPAIR / BREAKDOWN MAINTENANCE'],
                ['26', 'ENGINEERING / PROJECT / MODIFICATION'],
                ['00', 'ADMINISTRASI NON PEMBANGKIT'],
            ],
            CoaSegment::ExpenseElement->value => [
                ['E201', 'MATERIAL LISTRIK'],
                ['E202', 'MATERIAL UMUM'],
                ['E203', 'MATERIAL INSTRUMEN'],
                ['E204', 'MATERIAL MESIN'],
                ['F101/JBJSLN', 'JASA BANGUNAN SALURAN AIR & KELENGKAPAN HALAMAN'],
                ['F102/JBJSLN', 'JASA BANGUNAN & KELENGKAPAN HALAMAN'],
                ['F104/JBJSTK', 'JASA INSTALASI & MESIN TEKNIK'],
                ['F106/JBJSPU', 'PERLENGKAPAN UMUM'],
            ],
        ];
    }
}
