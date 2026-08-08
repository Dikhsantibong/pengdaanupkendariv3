<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-sheet signing link handed to an assessor over WhatsApp.
 *
 * The assessor has no account, so the token in the link is the whole of the
 * authorisation. It is therefore long, scoped to a single sheet of a single
 * assessment, expiring, revocable, and spent once the sheet is submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_assessment_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_form_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            // Captured on the public page, and printed on the sheet.
            $table->string('assessor_name')->nullable();
            $table->string('signature_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['vendor_assessment_id', 'assessment_form_id'],
                'vendor_assessment_invitation_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_assessment_invitations');
    }
};
