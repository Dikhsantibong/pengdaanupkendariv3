<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a checklist step to the document it produces.
 *
 * Only some steps result in a document that has to be drafted, printed,
 * signed and filed. Those carry a document type here; the rest stay plain
 * ticks. Nullable on purpose: a step without a document type is a step with
 * no paperwork attached, not a misconfigured one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->foreignId('document_type_id')->nullable()->after('stage')
                ->constrained('document_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_type_id');
        });
    }
};
