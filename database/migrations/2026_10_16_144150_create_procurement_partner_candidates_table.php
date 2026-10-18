<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several partner candidates (calon mitra) per procurement.
 *
 * The planning PIC lists the candidates and their offers; in execution the
 * "Pelaksana" step (formerly "Rekening Pelaksana") picks the one that does
 * the work, whose details then fill the contract documents, and asks for
 * its bank account.
 *
 * The single partner a procurement already carries becomes its candidate,
 * picked, so every document keeps reading the same.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_partner_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('director_name')->nullable();
            $table->text('address')->nullable();
            $table->string('quotation_number')->nullable();
            $table->date('quotation_date')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('procurements', function (Blueprint $table) {
            $table->foreignId('partner_candidate_id')->nullable()->after('partner_address')
                ->constrained('procurement_partner_candidates')->nullOnDelete();
        });

        DB::table('procurements')
            ->whereNotNull('partner_name')
            ->where('partner_name', '!=', '')
            ->orderBy('id')
            ->get(['id', 'partner_name', 'partner_director_name', 'partner_address', 'quotation_number', 'quotation_date'])
            ->each(function (object $procurement): void {
                $candidateId = DB::table('procurement_partner_candidates')->insertGetId([
                    'procurement_id' => $procurement->id,
                    'name' => $procurement->partner_name,
                    'director_name' => $procurement->partner_director_name,
                    'address' => $procurement->partner_address,
                    'quotation_number' => $procurement->quotation_number,
                    'quotation_date' => $procurement->quotation_date,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('procurements')->where('id', $procurement->id)->update(['partner_candidate_id' => $candidateId]);
            });

        // The step now picks the partner before asking for its account.
        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Rekening Pelaksana')
            ->update(['name' => 'Pelaksana', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->where('stage', 'pelaksanaan')
            ->where('name', 'Pelaksana')
            ->update(['name' => 'Rekening Pelaksana']);

        Schema::table('procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_candidate_id');
        });

        Schema::dropIfExists('procurement_partner_candidates');
    }
};
