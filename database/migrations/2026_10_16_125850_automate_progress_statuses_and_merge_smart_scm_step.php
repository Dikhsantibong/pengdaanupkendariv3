<?php

use App\Enums\SystemStatus;
use App\Models\Procurement;
use App\Services\ProgressStatusResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The progress status is no longer typed in: the system sets it from where
 * each job stands (App\Services\ProgressStatusResolver).
 *
 * - Every status the system sets is a row of the Status Progres master data
 *   with a fixed `system_key`; its name can still be changed there.
 * - The statuses that were only ever set by hand are switched off, kept for
 *   the history of the jobs that carried them.
 * - The "Proses SMART SCM" and "Berita Acara" execution steps become one
 *   step that files the SMART SCM document and every Berita Acara.
 * - Every job then takes the status it should have; cancelled jobs stay
 *   cancelled.
 */
return new class extends Migration
{
    /**
     * The hand-set statuses with a system meaning, by slug.
     *
     * @var array<string, string>
     */
    private const KNOWN = [
        'penyusunan-rks' => 'penyusunan_rks',
        'inisiasi-laksdan' => 'inisiasi_laksdan',
        'batal' => 'batal',
        'selesai' => 'selesai',
    ];

    /**
     * The hand-set statuses with no place in the automatic journey.
     *
     * @var array<int, string>
     */
    private const RETIRED = ['pending', 'kelengkapan-dokumen', 'penawaran-harga', 'disposisi-ams', 'proses-validasi'];

    private const SMART_SCM = 'Proses SMART SCM';

    private const BERITA_ACARA = 'Berita Acara';

    private const MERGED = 'Proses SMART SCM & Berita Acara';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('progress_statuses', function (Blueprint $table) {
            $table->string('system_key', 50)->nullable()->unique()->after('slug');
        });

        // Only an installation already running jobs is converted here; a
        // fresh one gets its statuses from MasterDataSeeder.
        $existing = DB::table('procurements')->exists();

        if ($existing) {
            $this->seedStatuses();
        }

        $this->mergeSmartScmStep();

        if ($existing) {
            $resolver = app(ProgressStatusResolver::class);
            $resolver->forget();

            Procurement::withTrashed()->orderBy('id')->chunkById(100, function ($procurements) use ($resolver): void {
                foreach ($procurements as $procurement) {
                    $resolver->sync($procurement);
                }
            });
        }
    }

    /**
     * Give each system status its row, reusing the hand-set ones that mean
     * the same, and switch the rest off.
     */
    private function seedStatuses(): void
    {
        foreach (self::KNOWN as $slug => $key) {
            DB::table('progress_statuses')->where('slug', $slug)->whereNull('system_key')->limit(1)->update(['system_key' => $key]);
        }

        foreach (SystemStatus::cases() as $index => $status) {
            $values = [
                'category' => $status->category()->value,
                'sort_order' => $index + 1,
                'is_active' => true,
                'updated_at' => now(),
            ];

            $updated = DB::table('progress_statuses')->where('system_key', $status->value)->update($values);

            if ($updated === 0 && ! DB::table('progress_statuses')->where('system_key', $status->value)->exists()) {
                $slug = Str::slug($status->label());

                DB::table('progress_statuses')->insert([
                    ...$values,
                    'name' => DB::table('progress_statuses')->where('name', $status->label())->exists()
                        ? $status->label().' (Otomatis)'
                        : $status->label(),
                    'slug' => DB::table('progress_statuses')->where('slug', $slug)->exists() ? $slug.'-otomatis' : $slug,
                    'system_key' => $status->value,
                    'is_default' => false,
                    'created_at' => now(),
                ]);
            }
        }

        DB::table('progress_statuses')
            ->whereNull('system_key')
            ->whereIn('slug', self::RETIRED)
            ->update(['is_active' => false, 'is_default' => false, 'sort_order' => 900, 'updated_at' => now()]);

        // A new job starts here until the system works its status out.
        DB::table('progress_statuses')->update(['is_default' => false]);
        DB::table('progress_statuses')->where('system_key', SystemStatus::InisiasiRendan->value)->update(['is_default' => true]);
    }

    /**
     * Fold the Berita Acara step into the SMART SCM step.
     */
    private function mergeSmartScmStep(): void
    {
        $item = fn (string $name) => DB::table('checklist_items')
            ->whereNull('procurement_id')
            ->whereNull('deleted_at')
            ->where('stage', 'pelaksanaan')
            ->where('name', $name)
            ->first();

        $smartScm = $item(self::SMART_SCM);
        $beritaAcara = $item(self::BERITA_ACARA);

        if ($smartScm === null || $beritaAcara === null) {
            return;
        }

        DB::transaction(function () use ($smartScm, $beritaAcara): void {
            // The Berita Acara documents join the SMART SCM document.
            $order = (int) DB::table('checklist_item_document_type')->where('checklist_item_id', $smartScm->id)->max('sort_order');

            $links = DB::table('checklist_item_document_type')->where('checklist_item_id', $beritaAcara->id)->orderBy('sort_order')->get();

            foreach ($links as $link) {
                DB::table('checklist_item_document_type')->insertOrIgnore([
                    'checklist_item_id' => $smartScm->id,
                    'document_type_id' => $link->document_type_id,
                    'sort_order' => ++$order,
                    'is_alternative' => $link->is_alternative,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('checklist_items')->where('id', $smartScm->id)->update([
                'name' => self::MERGED,
                'description' => $smartScm->description ?? 'Dokumen SMART SCM dan seluruh Berita Acara.',
                'updated_at' => now(),
            ]);

            // Each job keeps one step: finished only when both halves were,
            // except for jobs already past execution, whose history stays.
            $rows = DB::table('procurement_checklists')->where('checklist_item_id', $beritaAcara->id)->get();

            foreach ($rows as $row) {
                $merged = DB::table('procurement_checklists')
                    ->where('procurement_id', $row->procurement_id)
                    ->where('checklist_item_id', $smartScm->id)
                    ->first();

                if ($merged === null) {
                    DB::table('procurement_checklists')->where('id', $row->id)->update(['checklist_item_id' => $smartScm->id]);

                    continue;
                }

                $handedOver = DB::table('procurements')->where('id', $row->procurement_id)->whereNotNull('finance_submitted_at')->exists();

                if (! $handedOver && $merged->is_completed && ! $row->is_completed) {
                    DB::table('procurement_checklists')->where('id', $merged->id)->update([
                        'is_completed' => false,
                        'completed_at' => null,
                        'completed_by' => null,
                        'updated_at' => now(),
                    ]);
                }

                DB::table('procurement_checklists')->where('id', $row->id)->delete();
            }

            DB::table('checklist_items')->where('id', $beritaAcara->id)->update([
                'is_active' => false,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * The hand-set statuses come back; the merged step stays merged (its
     * ticks cannot be split again).
     */
    public function down(): void
    {
        DB::table('progress_statuses')->whereIn('slug', self::RETIRED)->update(['is_active' => true]);

        Schema::table('progress_statuses', function (Blueprint $table) {
            $table->dropUnique(['system_key']);
            $table->dropColumn('system_key');
        });
    }
};
