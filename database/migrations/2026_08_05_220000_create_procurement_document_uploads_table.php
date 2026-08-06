<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the signed scan of a document into a table of its own.
 *
 * A signed berita acara rarely arrives as one file: the pages are scanned in
 * batches, or photographed one at a time. Holding a single path on the
 * document allowed only one, and silently replaced it on the next upload.
 * Existing uploads are carried over before the old columns go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_document_uploads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_document_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('file_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('procurement_document_id');
        });

        $existing = DB::table('procurement_documents')
            ->whereNotNull('signed_path')
            ->get([
                'id',
                'signed_path',
                'signed_file_name',
                'signed_mime',
                'signed_size',
                'signed_uploaded_by',
                'signed_uploaded_at',
            ]);

        foreach ($existing as $row) {
            DB::table('procurement_document_uploads')->insert([
                'procurement_document_id' => $row->id,
                'path' => $row->signed_path,
                'file_name' => $row->signed_file_name ?? basename((string) $row->signed_path),
                'mime' => $row->signed_mime,
                'size' => $row->signed_size,
                'uploaded_by' => $row->signed_uploaded_by,
                'created_at' => $row->signed_uploaded_at ?? now(),
                'updated_at' => $row->signed_uploaded_at ?? now(),
            ]);
        }

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

    public function down(): void
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

        // Only the first upload of each document fits back into the columns.
        $first = DB::table('procurement_document_uploads')
            ->orderBy('id')
            ->get()
            ->unique('procurement_document_id');

        foreach ($first as $upload) {
            DB::table('procurement_documents')
                ->where('id', $upload->procurement_document_id)
                ->update([
                    'signed_path' => $upload->path,
                    'signed_file_name' => $upload->file_name,
                    'signed_mime' => $upload->mime,
                    'signed_size' => $upload->size,
                    'signed_uploaded_by' => $upload->uploaded_by,
                    'signed_uploaded_at' => $upload->created_at,
                ]);
        }

        Schema::dropIfExists('procurement_document_uploads');
    }
};
