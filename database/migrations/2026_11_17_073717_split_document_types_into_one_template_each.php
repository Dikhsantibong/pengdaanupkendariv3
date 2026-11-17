<?php

use App\Models\DocumentType;
use App\Support\DocumentTypeVariants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One document type, one template.
 *
 * A document type may now belong to a procurement method and to goods or
 * services, as a template could, and names the family of documents it is
 * (its code, for every type until now). The RKS, the SPK and the SPK's Pasal,
 * which carried a template per method, format or category, are split into a
 * variant each (see DocumentTypeVariants), and the steps, templates and
 * documents drawn follow.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->string('family')->nullable()->after('code')->index();
            $table->foreignId('procurement_method_id')->nullable()->after('contract_number_format_id')
                ->constrained('procurement_methods')->nullOnDelete();
            $table->string('work_category')->nullable()->after('procurement_method_id');
        });

        // Types created from here on get their family as they are made.
        DocumentType::forgetSchema();

        DB::table('document_types')->whereNull('family')->update(['family' => DB::raw('code')]);

        DocumentTypeVariants::split();
    }

    /**
     * Reverse the migrations.
     *
     * The variants keep their templates and documents; only the columns go
     * when nothing depends on them any more.
     */
    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_method_id');
            $table->dropColumn(['family', 'work_category']);
        });
    }
};
