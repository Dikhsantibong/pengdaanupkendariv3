<?php

use App\Models\Procurement;
use App\Services\DocumentGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The contract's price list becomes the procurement's Rincian Item: the
     * volume and the unit price before negotiation join the unit price
     * after it, which is not known yet while planning. The items already
     * typed into a BA Negosiasi table are read into it once.
     */
    public function up(): void
    {
        Schema::table('procurement_contract_items', function (Blueprint $table) {
            $table->decimal('volume', 15, 3)->default(1)->after('part_number');
            $table->decimal('price_before', 20, 2)->nullable()->after('unit');
            $table->decimal('unit_price', 20, 2)->nullable()->change();
        });

        $this->readNegotiationTables();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('procurement_contract_items')->whereNull('unit_price')->update(['unit_price' => 0]);

        Schema::table('procurement_contract_items', function (Blueprint $table) {
            $table->dropColumn(['volume', 'price_before']);
            $table->decimal('unit_price', 20, 2)->nullable(false)->change();
        });
    }

    /**
     * Each procurement without items yet takes them from its BA Negosiasi.
     */
    protected function readNegotiationTables(): void
    {
        $generator = app(DocumentGenerator::class);

        $documents = DB::table('procurement_documents')
            ->join('document_types', 'document_types.id', '=', 'procurement_documents.document_type_id')
            ->whereIn('document_types.code', ['ba-negosiasi-sppl', 'ba-negosiasi'])
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('procurement_contract_items')
                ->whereColumn('procurement_contract_items.procurement_id', 'procurement_documents.procurement_id'))
            ->orderByDesc('procurement_documents.id')
            ->get(['procurement_documents.procurement_id', 'procurement_documents.rendered_body']);

        $now = now();

        foreach ($documents->unique('procurement_id') as $document) {
            if (! Procurement::withTrashed()->whereKey($document->procurement_id)->exists()) {
                continue;
            }

            $rows = [];

            foreach ($generator->parseNegotiationItems((string) $document->rendered_body) as $index => $item) {
                $rows[] = [
                    'procurement_id' => $document->procurement_id,
                    'sort_order' => $index + 1,
                    'group_name' => $item['group'],
                    'description' => mb_substr($item['name'], 0, 500),
                    'part_number' => null,
                    'volume' => (float) str_replace(',', '.', (string) preg_replace('/[^0-9.,]/', '', $item['volume'])) ?: 1,
                    'unit' => mb_substr($item['satuan'], 0, 50),
                    'price_before' => $item['price_before'],
                    'unit_price' => $item['price_after'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('procurement_contract_items')->insert($rows);
            }
        }
    }
};
