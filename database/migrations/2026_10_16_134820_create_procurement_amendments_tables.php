<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contract amendments (Amandemen), on a tab of their own.
 *
 * The proposer files the meeting minutes, the attendance list, the Nota
 * Dinas and the request letter, lists the articles that change, and sends
 * it to their TL, the division's Asman and then TL Pengadaan. The amendment
 * document is drawn from its template, numbered on its own "Amd" format.
 *
 * The old "Amandemen" upload step of the execution checklist gives way to
 * the tab and is switched off (its ticks stay as history).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->foreignId('contract_number_format_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 100)->nullable();
            $table->string('state', 40)->default('draft');
            $table->date('amendment_date')->nullable();
            $table->date('contract_date')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('memo_number')->nullable();
            $table->date('memo_date')->nullable();
            $table->string('request_number')->nullable();
            $table->date('request_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('tl_reviewed_at')->nullable();
            $table->foreignId('tl_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('director_reviewed_at')->nullable();
            $table->foreignId('director_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('procurement_document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['procurement_id', 'sequence']);
        });

        Schema::create('procurement_amendment_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_amendment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('article');
            $table->text('original_text');
            $table->text('new_text');
            $table->timestamps();
        });

        Schema::create('procurement_amendment_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_amendment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('path');
            $table->string('file_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['procurement_amendment_id', 'kind']);
        });

        Schema::table('contract_number_formats', function (Blueprint $table) {
            $table->boolean('for_amendment')->default(false)->after('uses_work_category');
        });

        // The documents drawn for one amendment belong to it.
        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->foreignId('procurement_amendment_id')->nullable()->after('procurement_id')
                ->constrained()->nullOnDelete();
        });

        $this->seedExisting();
    }

    /**
     * Bring an installation already in use along: the Amd number format, the
     * approval right, the document type and template, and the old step.
     */
    private function seedExisting(): void
    {
        if (DB::table('contract_number_formats')->exists() && ! DB::table('contract_number_formats')->where('for_amendment', true)->exists()) {
            DB::table('contract_number_formats')->insert([
                'code' => 'Amd',
                'name' => 'Amandemen Kontrak',
                'prefix' => 'KDD',
                'unit_segment' => '612/UPKD',
                'sequence_length' => 3,
                'starting_sequence' => 1,
                'uses_work_category' => false,
                'for_amendment' => true,
                'description' => 'Nomor amandemen kontrak, contoh KDD054.Amd/612/UPKD/2026.',
                'sort_order' => 99,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('role_permissions')->insertOrIgnore([
            'role' => 'team_leader_pengadaan',
            'permission' => 'procurement.approve-amendment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('access-rights.grants');

        if (DB::table('document_types')->exists() && ! DB::table('document_types')->where('code', 'amandemen-kontrak')->exists()) {
            DB::table('document_types')->insert([
                'code' => 'amandemen-kontrak',
                'name' => 'Amandemen Kontrak',
                'stage' => 'pelaksanaan',
                'sort_order' => (int) DB::table('document_types')->max('sort_order') + 1,
                'upload_only' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['kontrak.amandemen']);
        }

        // The tab replaces the old upload step.
        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Amandemen')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Amandemen')
            ->update(['is_active' => true]);

        DB::table('role_permissions')->where('permission', 'procurement.approve-amendment')->delete();
        Cache::forget('access-rights.grants');

        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_amendment_id');
        });

        // The amendment format would otherwise be offered as a contract format.
        DB::table('contract_number_formats')
            ->where('for_amendment', true)
            ->whereNotIn('id', DB::table('procurements')->whereNotNull('contract_number_format_id')->select('contract_number_format_id'))
            ->delete();

        Schema::table('contract_number_formats', function (Blueprint $table) {
            $table->dropColumn('for_amendment');
        });

        Schema::dropIfExists('procurement_amendment_files');
        Schema::dropIfExists('procurement_amendment_changes');
        Schema::dropIfExists('procurement_amendments');
    }
};
