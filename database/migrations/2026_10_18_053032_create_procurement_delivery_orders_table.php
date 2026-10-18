<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery Orders (DO) of a KHS contract, on a tab of their own.
 *
 * Under an SPK or PJ contract of the KHS kind, the work is called off in
 * deliveries:
 * - the proposer requests one (Nota Dinas usulan, its date and scan, and the
 *   lines wanted from the contract's price list), approved by their TL and
 *   the division's Asman;
 * - the planning side (Rendan) adds the Nota Dinas DO and its date;
 * - the execution side (Laksdan) dates it and issues the DO document,
 *   numbered on the "DO" format, in sequence across every KHS contract.
 *
 * The price list of the contract (material / jasa with their unit prices)
 * is kept as data, so each DO draws its lines from it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // The heading the item is listed under (MATERIAL MAK 8M 453 C).
            $table->string('group_name')->nullable();
            $table->string('description', 500);
            $table->string('part_number', 100)->nullable();
            $table->string('unit', 50);
            $table->decimal('unit_price', 20, 2);
            $table->timestamps();
        });

        Schema::create('procurement_delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('state', 40)->default('draft');
            // The proposer's request.
            $table->string('request_memo_number')->nullable();
            $table->date('request_memo_date')->nullable();
            $table->string('request_file_path')->nullable();
            $table->string('request_file_name')->nullable();
            $table->string('request_file_mime')->nullable();
            $table->unsignedBigInteger('request_file_size')->nullable();
            // Rendan's Nota Dinas DO.
            $table->string('do_memo_number')->nullable();
            $table->date('do_memo_date')->nullable();
            // Laksdan's DO.
            $table->foreignId('contract_number_format_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 100)->nullable();
            $table->date('order_date')->nullable();
            $table->date('contract_date')->nullable();
            $table->string('vendor_code', 100)->nullable();
            $table->string('work_description', 500)->nullable();
            $table->string('delivery_place', 500)->nullable();
            $table->unsignedSmallInteger('delivery_days')->nullable();
            // Who did what, when.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('tl_reviewed_at')->nullable();
            $table->foreignId('tl_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('director_reviewed_at')->nullable();
            $table->foreignId('director_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable();
            $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('procurement_document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['procurement_id', 'sequence']);
        });

        Schema::create('procurement_delivery_order_items', function (Blueprint $table) {
            $table->id();
            // Named here: the default names exceed MySQL's 64 characters.
            $table->foreignId('procurement_delivery_order_id')
                ->constrained(indexName: 'do_items_delivery_order_foreign')->cascadeOnDelete();
            // The contract price list line it was drawn from; its wording and
            // price are copied, so a later change to the list leaves it alone.
            $table->foreignId('procurement_contract_item_id')->nullable()
                ->constrained(indexName: 'do_items_contract_item_foreign')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('group_name')->nullable();
            $table->string('description', 500);
            $table->string('part_number', 100)->nullable();
            $table->decimal('quantity', 15, 2);
            $table->string('unit', 50);
            $table->decimal('unit_price', 20, 2);
            $table->timestamps();
        });

        Schema::table('contract_number_formats', function (Blueprint $table) {
            $table->boolean('for_delivery_order')->default(false)->after('for_amendment');
        });

        // The document drawn for one DO belongs to it.
        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->foreignId('procurement_delivery_order_id')->nullable()->after('procurement_amendment_id')
                ->constrained()->nullOnDelete();
        });

        $this->seedExisting();
    }

    /**
     * Bring an installation already in use along: the DO number format, the
     * document type and its template.
     */
    private function seedExisting(): void
    {
        if (DB::table('contract_number_formats')->exists() && ! DB::table('contract_number_formats')->where('for_delivery_order', true)->exists()) {
            DB::table('contract_number_formats')->insert([
                'code' => 'DO',
                'name' => 'Delivery Order',
                'prefix' => 'D',
                'unit_segment' => '612/UPKD',
                'sequence_length' => 5,
                'starting_sequence' => 1,
                'uses_work_category' => false,
                'for_amendment' => false,
                'for_delivery_order' => true,
                'description' => 'Nomor Delivery Order kontrak KHS, berurutan untuk semua kontrak KHS, contoh D26252.DO/612/UPKD/2026.',
                'sort_order' => 100,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('document_types')->exists() && ! DB::table('document_types')->where('code', 'delivery-order')->exists()) {
            DB::table('document_types')->insert([
                'code' => 'delivery-order',
                'name' => 'Delivery Order (DO)',
                'stage' => 'pelaksanaan',
                'sort_order' => (int) DB::table('document_types')->max('sort_order') + 1,
                'upload_only' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['kontrak.delivery-order']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_delivery_order_id');
        });

        DB::table('contract_number_formats')
            ->where('for_delivery_order', true)
            ->whereNotIn('id', DB::table('procurements')->whereNotNull('contract_number_format_id')->select('contract_number_format_id'))
            ->delete();

        Schema::table('contract_number_formats', function (Blueprint $table) {
            $table->dropColumn('for_delivery_order');
        });

        Schema::dropIfExists('procurement_delivery_order_items');
        Schema::dropIfExists('procurement_delivery_orders');
        Schema::dropIfExists('procurement_contract_items');
    }
};
