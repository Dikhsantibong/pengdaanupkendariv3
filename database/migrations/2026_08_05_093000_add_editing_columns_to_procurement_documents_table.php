<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a generated document was corrected by hand.
 *
 * A generated body is a draft: wording and the data pulled in from master data
 * still have to be checked before the document leaves the unit, so the audit
 * trail has to show who last touched it and how many times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table): void {
            $table->foreignId('edited_by')->nullable()->after('generated_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('generated_at');
            $table->unsignedInteger('revision')->default(0)->after('template_version');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('edited_by');
            $table->dropColumn(['edited_at', 'revision']);
        });
    }
};
