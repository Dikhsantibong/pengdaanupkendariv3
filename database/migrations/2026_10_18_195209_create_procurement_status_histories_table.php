<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every progress status a procurement passes through, with when and (when
 * someone's action caused it) by whom: the history shown under Status
 * Progres.
 *
 * The automatic status used to change quietly, so for the jobs already
 * running the history is rebuilt from their recorded activity (proposal
 * submitted and approved, planning submitted and approved, execution
 * submitted and approved, payment, cancellation), closed by the status they
 * have now. The steps in between that left no trace (Penyusunan RKS, say)
 * have no date of their own.
 */
return new class extends Migration
{
    /**
     * The status an activity leads into.
     *
     * @var array<string, string>
     */
    private const ENTERED_BY = [
        'usulan_diajukan' => 'approval_usulan',
        'usulan_disetujui' => 'inisiasi_rendan',
        'usulan_dikembalikan' => 'revisi_usulan',
        'perencanaan_diajukan' => 'approval_icc',
        'perencanaan_disetujui' => 'inisiasi_laksdan',
        'perencanaan_ditolak' => 'revisi_perencanaan',
        'pelaksanaan_diajukan' => 'approval_tl_pengadaan',
        'pelaksanaan_disetujui' => 'terkontrak',
        'diajukan_ke_keuangan' => 'terkontrak',
        'pelaksanaan_ditolak' => 'revisi_pelaksanaan',
        'pembayaran_selesai' => 'selesai',
        'pengadaan_selesai' => 'selesai',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('progress_status_id')->nullable()->constrained()->nullOnDelete();
            // As named then: a status may be renamed on the master data later.
            $table->string('status_name');
            $table->string('system_key')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['procurement_id', 'created_at']);
        });

        $this->backfill();
    }

    /**
     * Rebuild the history of the jobs already running.
     */
    private function backfill(): void
    {
        $statuses = DB::table('progress_statuses')->get(['id', 'name', 'system_key']);
        $byKey = $statuses->whereNotNull('system_key')->keyBy('system_key');
        $byName = $statuses->keyBy('name');

        DB::table('procurements')
            ->whereNotNull('progress_status_id')
            ->select(['id', 'progress_status_id', 'updated_at', 'created_at'])
            ->orderBy('id')
            ->chunk(200, function ($procurements) use ($statuses, $byKey, $byName): void {
                $activities = DB::table('procurement_activities')
                    ->whereIn('procurement_id', $procurements->pluck('id'))
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get(['procurement_id', 'user_id', 'type', 'meta', 'created_at'])
                    ->groupBy('procurement_id');
                $rows = [];

                foreach ($procurements as $procurement) {
                    $entries = [];

                    foreach ($activities->get($procurement->id, collect()) as $activity) {
                        $status = isset(self::ENTERED_BY[$activity->type])
                            ? $byKey->get(self::ENTERED_BY[$activity->type])
                            : null;

                        // A cancellation, or its lifting, names the status it led to.
                        if ($activity->type === 'status_diubah') {
                            $meta = json_decode((string) $activity->meta, true);
                            $status = is_array($meta) && isset($meta['to']) ? $byName->get($meta['to']) : null;
                        }

                        if ($status !== null) {
                            $entries[] = [$status, $activity->user_id, $activity->created_at];
                        }
                    }

                    // Closed by where the job stands now.
                    $current = $statuses->firstWhere('id', $procurement->progress_status_id);

                    if ($current !== null && (end($entries) ?: [null])[0]?->id !== $current->id) {
                        $entries[] = [$current, null, $procurement->updated_at ?? $procurement->created_at];
                    }

                    $previous = null;

                    foreach ($entries as [$status, $userId, $at]) {
                        if ($previous === $status->id) {
                            continue;
                        }

                        $previous = $status->id;
                        $rows[] = [
                            'procurement_id' => $procurement->id,
                            'progress_status_id' => $status->id,
                            'status_name' => $status->name,
                            'system_key' => $status->system_key,
                            'user_id' => $userId,
                            'created_at' => $at,
                        ];
                    }
                }

                foreach (array_chunk($rows, 500) as $batch) {
                    DB::table('procurement_status_histories')->insert($batch);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_status_histories');
    }
};
