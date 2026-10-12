<?php

use App\Models\Procurement;
use App\Services\ProcurementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The SPK now carries the partner's bank account (Pasal 5) and the warranty
 * period (Pasal 9), so SPK and PJ procurements get the "Rekening Pelaksana"
 * and "Masa Garansi" steps too. Procurements already running pick the two
 * steps up straight away.
 */
return new class extends Migration
{
    /**
     * The steps switched on for the formats.
     *
     * @var array<int, string>
     */
    private const STEPS = ['Rekening Pelaksana', 'Masa Garansi'];

    /**
     * The contract number formats gaining the steps.
     *
     * @var array<int, string>
     */
    private const FORMATS = ['SPK', 'PJ'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $itemIds = DB::table('checklist_items')->whereIn('name', self::STEPS)->pluck('id');
        $formatIds = DB::table('contract_number_formats')->whereIn('code', self::FORMATS)->pluck('id');

        if ($itemIds->isEmpty() || $formatIds->isEmpty()) {
            return;
        }

        DB::table('checklist_item_format_exclusions')
            ->whereIn('checklist_item_id', $itemIds)
            ->whereIn('contract_number_format_id', $formatIds)
            ->delete();

        $service = app(ProcurementService::class);

        Procurement::query()
            ->whereIn('contract_number_format_id', $formatIds)
            ->each(fn (Procurement $procurement) => $service->syncChecklists($procurement));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $itemIds = DB::table('checklist_items')->whereIn('name', self::STEPS)->pluck('id');
        $formatIds = DB::table('contract_number_formats')->whereIn('code', self::FORMATS)->pluck('id');

        foreach ($itemIds as $itemId) {
            foreach ($formatIds as $formatId) {
                DB::table('checklist_item_format_exclusions')->insertOrIgnore([
                    'checklist_item_id' => $itemId,
                    'contract_number_format_id' => $formatId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
