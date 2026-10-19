<?php

namespace Database\Seeders;

use App\Enums\TeamLeaderField;
use App\Enums\UnitManagerLevel;
use App\Models\TargetUnit;
use App\Models\UnitManager;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The managers and team leaders of the Unit Layanan under UP Kendari, kept on
 * Data Master → Manager Unit.
 *
 * A Manager UL and the TL of each bidang hold every unit of their Unit
 * Layanan; a TL OPHAR holds one. The units are the rows of Data Master → Unit
 * Tujuan, and a unit not listed there is left out.
 *
 * They are listed after the rows already there, so the Manager UP Kendari who
 * signs the documents stays first. Only missing rows and links are added:
 * whatever an administrator has changed or deleted is kept when this runs
 * again.
 */
class UnitLayananManagerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Each Unit Layanan: the units it holds and its officials, by name.
     *
     * @var array<string, array{units: list<string>, officials: array<string, array{level: UnitManagerLevel, field?: TeamLeaderField, unit?: string}>}>
     */
    public const UNITS = [
        'ULPLTD WUA WUA' => [
            'units' => ['PLTD Wua Wua', 'PLTD Langara'],
            'officials' => [
                'ABDUL RAHMAN KADIR' => ['level' => UnitManagerLevel::ManagerUl],
                'SURYADI PRATAMA' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::Pemeliharaan],
                'SUHARTADI' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::Operasi],
                'INDRA MULYADI' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTD Langara'],
                'CHOIRI ASTA GANDHI' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::K3l],
            ],
        ],
        'ULPLTD POASIA' => [
            'units' => ['PLTD Poasia', 'PLTD Poasia Containerized'],
            'officials' => [
                'ZULKIFLIN' => ['level' => UnitManagerLevel::ManagerUl],
                'MUH ISYAK' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::Pemeliharaan],
                'ADI YUSUF SANJANI' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::Operasi],
                'MUH AMIN' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::K3l],
            ],
        ],
        'ULPLTDG KOLAKA' => [
            'units' => ['PLTD Kolaka', 'PLTD Lanipa', 'PLTM Sabilambo', 'PLTM Mikuasi', 'PLTG Kolaka'],
            'officials' => [
                'SUKRI PENSI' => ['level' => UnitManagerLevel::ManagerUl],
                'I PUTU AGUS SAPUTRA' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::Pemeliharaan],
                'FRANS BONGGA' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::Operasi],
                'PANGERAN' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::K3l],
                'BAHARUDIN' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTM Sabilambo'],
                'ANDRIAWAN AMIR' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTD Lanipa'],
                'DIKA ANGGARA' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTM Mikuasi'],
                'RIZAL JHON' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTG Kolaka'],
            ],
        ],
        'ULPLTD BAU BAU' => [
            'units' => ['PLTD Bau Bau', 'PLTD Raha', 'PLTD Ereke', 'PLTD Wangi Wangi', 'PLTD Winning', 'PLTM Rongi', 'PLTD Pasar Wajo'],
            'officials' => [
                'RASYID' => ['level' => UnitManagerLevel::ManagerUl],
                'ARIF RAHMAD SIDIQ DUGRO' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::Operasi],
                'ATA TAYEB' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::Pemeliharaan],
                'ZUL SHAFAR SARIAH' => ['level' => UnitManagerLevel::Tl, 'field' => TeamLeaderField::K3l],
                'SUWANDY PRASATIA' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTD Raha'],
                'AHMAD RAMADHAN' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTD Ereke'],
                'MUH RAMLY HALIM' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTD Wangi Wangi'],
                'ASFAR ADRIN ASLI' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTD Winning'],
                'ZUL FIKAR' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTM Rongi'],
                'SAKIRI' => ['level' => UnitManagerLevel::TlOphar, 'unit' => 'PLTD Pasar Wajo'],
            ],
        ],
    ];

    /**
     * Add the missing Unit Layanan officials and the units they hold.
     */
    public function run(): void
    {
        $sortOrder = (int) UnitManager::withTrashed()->max('sort_order');

        foreach (self::UNITS as $unitLayanan => ['officials' => $officials]) {
            foreach ($officials as $name => $rank) {
                UnitManager::withTrashed()->firstOrCreate(
                    ['name' => $name],
                    [
                        'position' => UnitManager::positionFor($rank['level'], $rank['field'] ?? null, $rank['unit'] ?? null),
                        'level' => $rank['level'],
                        'field' => $rank['field'] ?? null,
                        'description' => $unitLayanan,
                        'sort_order' => ++$sortOrder,
                        'is_active' => true,
                    ],
                );
            }
        }

        $this->arrange();
    }

    /**
     * Link the listed officials to the units they hold.
     *
     * An official already holding a unit, or given another rank by an
     * administrator, is left as it is. A TL OPHAR's jabatan is written from
     * the name the unit has on Unit Tujuan.
     */
    public function arrange(): void
    {
        $targetUnits = TargetUnit::query()
            ->get(['id', 'name'])
            ->keyBy(fn (TargetUnit $unit): string => mb_strtolower($unit->name));

        foreach (self::UNITS as ['units' => $held, 'officials' => $officials]) {
            foreach ($officials as $name => $rank) {
                $official = UnitManager::query()->where('name', $name)->first();

                if ($official === null || $official->level !== $rank['level'] || $official->targetUnits()->exists()) {
                    continue;
                }

                $names = $rank['level']->holdsOneUnit() ? [$rank['unit'] ?? ''] : $held;
                $links = [];

                foreach ($names as $unitName) {
                    $unit = $targetUnits->get(mb_strtolower($unitName));

                    if ($unit !== null) {
                        $links[$unit->id] = ['sort_order' => count($links) + 1];
                    }
                }

                if ($links === []) {
                    continue;
                }

                $official->targetUnits()->sync($links);

                if ($rank['level']->holdsOneUnit()) {
                    $official->update([
                        'position' => UnitManager::positionFor($rank['level'], unit: $targetUnits->get(mb_strtolower($rank['unit'] ?? ''))?->name),
                    ]);
                }
            }
        }
    }
}
