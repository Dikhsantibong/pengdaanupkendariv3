<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets one checklist step produce several documents.
 *
 * Berita Acara alone yields six separate documents and Kontrak yields three,
 * so a single foreign key cannot describe the step any more. Every existing
 * single link is carried over before the old column goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_item_document_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['checklist_item_id', 'document_type_id'], 'checklist_document_type_unique');
        });

        $existing = DB::table('checklist_items')
            ->whereNotNull('document_type_id')
            ->get(['id', 'document_type_id']);

        foreach ($existing as $row) {
            DB::table('checklist_item_document_type')->insert([
                'checklist_item_id' => $row->id,
                'document_type_id' => $row->document_type_id,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->foreignId('document_type_id')->nullable()->after('stage')
                ->constrained('document_types')->nullOnDelete();
        });

        $links = DB::table('checklist_item_document_type')
            ->orderBy('sort_order')
            ->get(['checklist_item_id', 'document_type_id']);

        foreach ($links as $link) {
            DB::table('checklist_items')
                ->where('id', $link->checklist_item_id)
                ->update(['document_type_id' => $link->document_type_id]);
        }

        Schema::dropIfExists('checklist_item_document_type');
    }
};
