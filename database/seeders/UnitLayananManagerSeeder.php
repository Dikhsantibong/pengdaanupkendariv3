<?php

namespace Database\Seeders;

use App\Models\UnitManager;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The managers and team leaders of the Unit Layanan under UP Kendari, kept on
 * Data Master → Manager Unit.
 *
 * They are listed after the rows already there, so the Manager UP Kendari who
 * signs the documents stays first. Only missing rows are added: whatever an
 * administrator has changed or deleted is kept when this runs again.
 */
class UnitLayananManagerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Each unit's officials, as name => position.
     *
     * @var array<string, array<string, string>>
     */
    public const UNITS = [
        'ULPLTD WUA WUA' => [
            'ABDUL RAHMAN KADIR' => 'MANAGER UL',
            'SURYADI PRATAMA' => 'TL PEMELIHARAAN',
            'SUHARTADI' => 'TL OPERASI',
            'INDRA MULYADI' => 'TL OPHAR PLTD LANGARA',
            'CHOIRI ASTA GANDHI' => 'TL K3L',
        ],
        'ULPLTD POASIA' => [
            'ZULKIFLIN' => 'MANAGER UL',
            'MUH ISYAK' => 'TL PEMELIHARAAN',
            'ADI YUSUF SANJANI' => 'TL OPERASI',
            'MUH AMIN' => 'TL K3L',
        ],
        'ULPLTDG KOLAKA' => [
            'SUKRI PENSI' => 'MANAGER UL',
            'I PUTU AGUS SAPUTRA' => 'TL PEMELIHARAAN',
            'FRANS BONGGA' => 'TL OPERASI',
            'PANGERAN' => 'TL K3L',
            'BAHARUDIN' => 'TL OPHAR PLTM SABILAMBO',
            'ANDRIAWAN AMIR' => 'TL OPHAR PLTD LANIPA NIPA',
            'DIKA ANGGARA' => 'TL OPHAR PLTM MIKUASI',
            'RIZAL JHON' => 'TL OPHAR PLTG KOLAKA',
        ],
        'ULPLTD BAU BAU' => [
            'RASYID' => 'MANAGER UL',
            'ARIF RAHMAD SIDIQ DUGRO' => 'TL OPERASI',
            'ATA TAYEB' => 'TL PEMELIHARAAN',
            'ZUL SHAFAR SARIAH' => 'TL K3L',
            'SUWANDY PRASATIA' => 'TL OPHAR PLTD RAHA',
            'AHMAD RAMADHAN' => 'TL OPHAR PLTD EREKE',
            'MUH RAMLY HALIM' => 'TL OPHAR PLTD WANGI WANGI',
            'ASFAR ADRIN ASLI' => 'TL OPHAR PLTM WINNING',
            'ZUL FIKAR' => 'TL OPHAR PLTM RONGI',
            'SAKIRI' => 'TL OPHAR PLTD PASARWAJO',
        ],
    ];

    /**
     * Add the missing Unit Layanan officials.
     */
    public function run(): void
    {
        $sortOrder = (int) UnitManager::withTrashed()->max('sort_order');

        foreach (self::UNITS as $unit => $officials) {
            foreach ($officials as $name => $position) {
                UnitManager::withTrashed()->firstOrCreate(
                    ['name' => $name],
                    [
                        'position' => $position,
                        'description' => $unit,
                        'sort_order' => ++$sortOrder,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
