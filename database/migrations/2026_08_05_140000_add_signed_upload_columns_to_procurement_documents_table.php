<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holds the scan of a document after it has been printed and signed.
 *
 * A berita acara only becomes evidence once it carries wet signatures, so the
 * generated draft and the signed copy that comes back from the printer both
 * have to live on the same record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table): void {
            $table->string('signed_path')->nullable()->after('edited_at');
            $table->string('signed_file_name')->nullable()->after('signed_path');
            $table->string('signed_mime')->nullable()->after('signed_file_name');
            $table->unsignedBigInteger('signed_size')->nullable()->after('signed_mime');
            $table->foreignId('signed_uploaded_by')->nullable()->after('signed_size')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('signed_uploaded_at')->nullable()->after('signed_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signed_uploaded_by');
            $table->dropColumn([
                'signed_path',
                'signed_file_name',
                'signed_mime',
                'signed_size',
                'signed_uploaded_at',
            ]);
        });
    }
};
