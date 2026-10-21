<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Direksi Pekerjaan chosen on each assessment, and from when each name
 * of a sheet may be chosen at all.
 *
 * A Direksi Pekerjaan who took office later is not offered on work handed
 * over (BASTP) before then: `assessor_hidden_until` maps a name to the last
 * BASTP date it stays hidden on.
 */
return new class extends Migration
{
    /**
     * The Direksi Pekerjaan already known to have taken office later.
     *
     * @var array<string, string>
     */
    private const HIDDEN_UNTIL = [
        'WINANDO' => '2026-07-01',
        'TAUHID' => '2026-08-01',
        'TANWIR' => '2026-09-01',
    ];

    public function up(): void
    {
        Schema::table('vendor_assessments', function (Blueprint $table): void {
            $table->string('work_director_name')->nullable()->after('vendor_name');
        });

        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->json('assessor_hidden_until')->nullable()->after('assessor_options');
        });

        $form = DB::table('assessment_forms')->where('code', 'direksi-pekerjaan')->first();

        if ($form === null || $form->assessor_options === null) {
            return;
        }

        $hidden = [];

        foreach ((array) json_decode((string) $form->assessor_options, true) as $option) {
            $name = mb_strtoupper(trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $option)));

            foreach (self::HIDDEN_UNTIL as $prefix => $date) {
                if (str_starts_with($name, $prefix)) {
                    $hidden[$name] = $date;
                }
            }
        }

        if ($hidden !== []) {
            DB::table('assessment_forms')->where('id', $form->id)->update(['assessor_hidden_until' => json_encode($hidden)]);
        }
    }

    public function down(): void
    {
        Schema::table('vendor_assessments', function (Blueprint $table): void {
            $table->dropColumn('work_director_name');
        });

        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->dropColumn('assessor_hidden_until');
        });
    }
};
