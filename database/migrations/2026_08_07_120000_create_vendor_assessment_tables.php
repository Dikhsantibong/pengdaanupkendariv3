<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulir Penilaian Kinerja Penyedia Barang dan Jasa (SMT-FM-DAN-02.02).
 *
 * One assessment covers a single procurement and is scored on several forms,
 * each signed by a different assessor. The aspects and the forms are reference
 * data so the unit can revise the official form without a code change; the
 * scores live per assessment, per form, per aspect, which is what lets the
 * master recap average an aspect across everyone who scored it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_aspects', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            // The sentence above the lettered indicators, when the form has one.
            $table->text('preamble')->nullable();
            $table->json('indicators');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assessment_forms', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            // Printed under "Penilai," on the signature block.
            $table->string('assessor_title');
            $table->string('assessor_name')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assessment_form_aspect', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_aspect_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['assessment_form_id', 'assessment_aspect_id'],
                'assessment_form_aspect_unique',
            );
        });

        Schema::create('vendor_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_id')->nullable()->constrained()->nullOnDelete();
            // The header fields of the official form. Prefilled from the
            // procurement where possible, but editable: a PO number and vendor
            // are agreed outside this system.
            $table->string('project');
            $table->string('po_number')->nullable();
            $table->date('po_date')->nullable();
            $table->string('vendor_name');
            $table->string('form_number')->default('SMT-FM-DAN-02.02');
            $table->string('revision_number')->default('03');
            $table->date('form_date')->nullable();
            $table->string('place')->default('Kendari');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('procurement_id');
        });

        Schema::create('vendor_assessment_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_aspect_id')->constrained()->cascadeOnDelete();
            // Null until the assessor fills it in, so an unscored row is
            // distinguishable from a deliberate low score.
            $table->unsignedTinyInteger('level')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['vendor_assessment_id', 'assessment_form_id', 'assessment_aspect_id'],
                'vendor_assessment_score_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_assessment_scores');
        Schema::dropIfExists('vendor_assessments');
        Schema::dropIfExists('assessment_form_aspect');
        Schema::dropIfExists('assessment_forms');
        Schema::dropIfExists('assessment_aspects');
    }
};
