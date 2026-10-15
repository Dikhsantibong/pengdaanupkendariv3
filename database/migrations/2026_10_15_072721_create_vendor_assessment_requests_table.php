<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoice details sent in by vendors to request their assessment.
     *
     * Every vendor fills them in through the same fixed link printed in the
     * contract; each submission is one request, which the administrator
     * then opens as an assessment.
     */
    public function up(): void
    {
        Schema::create('vendor_assessment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->string('vendor_name');
            $table->string('contract_number');
            $table->date('contract_date');
            $table->string('term');
            $table->decimal('invoice_amount', 18, 2);
            $table->string('bastp_number');
            $table->date('bastp_date');
            $table->boolean('has_penalty');
            $table->string('submitter_name');
            $table->string('submitter_phone', 30)->nullable();
            $table->foreignId('vendor_assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_assessment_requests');
    }
};
