<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The files uploaded on a checklist step that has no document of its own:
 * every Penyerahan and Pembayaran requirement (surat jalan, kwitansi,
 * faktur pajak, ...) is evidenced by an upload rather than a bare tick.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_checklist_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('file_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_checklist_files');
    }
};
