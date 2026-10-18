<?php

use App\Services\DocumentTemplateLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amendments no longer end with a TL Pengadaan approval: once the TL and
 * the Asman agree, the request goes to the execution side (Laksdan), who
 * numbers it (in sequence across every contract), dates it, picks the
 * articles that change from the contract and issues the document.
 *
 * - who issued it is recorded;
 * - the "approve amendment" right is retired;
 * - the amendment template's narrative follows the contract's wording.
 *
 * Requests already waiting on TL Pengadaan now wait on Laksdan; issued
 * ones stay issued.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procurement_amendments', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('reviewed_by');
            $table->foreignId('issued_by')->nullable()->after('issued_at')->constrained('users')->nullOnDelete();
        });

        DB::table('role_permissions')->where('permission', 'procurement.approve-amendment')->delete();
        Cache::forget('access-rights.grants');

        if (DB::table('document_templates')->whereNotNull('library_key')->exists()) {
            app(DocumentTemplateLibrary::class)->sync(keys: ['kontrak.amandemen']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('role_permissions')->insertOrIgnore([
            'role' => 'team_leader_pengadaan',
            'permission' => 'procurement.approve-amendment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('access-rights.grants');

        Schema::table('procurement_amendments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn('issued_at');
        });
    }
};
