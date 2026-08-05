<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\BudgetSource;
use App\Models\ChecklistItem;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use App\Services\DocumentGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Demo data for evaluating the application and the public boards.
 *
 * This seeder is intentionally NOT part of DatabaseSeeder. Run it explicitly:
 *
 *     php artisan db:seed --class=DemoProcurementSeeder
 *
 * Every record it creates carries the "PGD/" numbering of a normal procurement,
 * so remove them with:
 *
 *     php artisan db:wipe && php artisan migrate --seed
 */
class DemoProcurementSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The work packages the demo data is built from.
     *
     * @var array<int, array{name: string, track: string}>
     */
    private const WORK_PACKAGES = [
        ['name' => 'Overhaul Mesin Diesel Unit 3', 'track' => 'selesai'],
        ['name' => 'Pengadaan Spare Part Turbocharger', 'track' => 'selesai'],
        ['name' => 'Rehabilitasi Sistem Pendingin Utama', 'track' => 'selesai'],
        ['name' => 'Pemeliharaan Rutin Generator Unit 1', 'track' => 'pelaksanaan'],
        ['name' => 'Penggantian Panel Kontrol MV', 'track' => 'pelaksanaan'],
        ['name' => 'Pengadaan Pelumas Mesin Tahun Berjalan', 'track' => 'pelaksanaan'],
        ['name' => 'Perbaikan Instalasi Bahan Bakar', 'track' => 'pelaksanaan'],
        ['name' => 'Kalibrasi Instrumentasi dan Proteksi', 'track' => 'pelaksanaan'],
        ['name' => 'Pengadaan Jasa Cleaning Boiler', 'track' => 'pelaksanaan'],
        ['name' => 'Peremajaan Sistem Grounding', 'track' => 'menunggu'],
        ['name' => 'Pengadaan Material Pemeliharaan Trafo', 'track' => 'menunggu'],
        ['name' => 'Jasa Inspeksi Keandalan Pembangkit', 'track' => 'menunggu'],
        ['name' => 'Pengadaan APD dan Perlengkapan K3', 'track' => 'ditolak'],
        ['name' => 'Perbaikan Jalan Akses Area Pembangkit', 'track' => 'ditolak'],
        ['name' => 'Pengadaan Sistem Monitoring Emisi', 'track' => 'penyusunan'],
        ['name' => 'Pemeliharaan Sistem Proteksi Kebakaran', 'track' => 'penyusunan'],
        ['name' => 'Pengadaan Genset Cadangan Portable', 'track' => 'penyusunan'],
        ['name' => 'Jasa Pengelolaan Limbah B3', 'track' => 'penyusunan'],
        ['name' => 'Pengadaan Baterai Bank Unit Isolated', 'track' => 'inisiasi'],
        ['name' => 'Perbaikan Dermaga Suplai Bahan Bakar', 'track' => 'inisiasi'],
        ['name' => 'Pengadaan Alat Ukur Laboratorium', 'track' => 'inisiasi'],
        ['name' => 'Modernisasi Sistem SCADA Pembangkit', 'track' => 'inisiasi'],
        ['name' => 'Pengadaan Pompa Air Pendingin Cadangan', 'track' => 'batal'],
        ['name' => 'Jasa Konsultansi Studi Kelayakan Ekspansi', 'track' => 'batal'],
    ];

    /**
     * Seed a realistic spread of procurements across both stages.
     */
    public function run(): void
    {
        $directors = WorkDirector::query()->active()->get();
        $units = TargetUnit::query()->active()->get();
        $planners = User::query()->active()->withRole([UserRole::PicPerencana])->get();
        $executors = User::query()->active()->withRole([UserRole::PicPelaksana])->get();
        $teamLeader = User::query()->withRole([UserRole::TeamLeader])->first();

        if ($directors->isEmpty() || $units->isEmpty() || $planners->isEmpty() || $executors->isEmpty()) {
            throw new RuntimeException('Jalankan DatabaseSeeder terlebih dahulu sebelum menambahkan data demo.');
        }

        $planningItems = ChecklistItem::query()->active()->forStage(ProcurementStage::Perencanaan)->ordered()->get();
        $executionItems = ChecklistItem::query()->active()->forStage(ProcurementStage::Pelaksanaan)->ordered()->get();

        foreach (self::WORK_PACKAGES as $index => $package) {
            $this->createProcurement(
                $package,
                $index,
                $directors,
                $units,
                $planners,
                $executors,
                $teamLeader,
                $planningItems,
                $executionItems,
            );
        }

        $this->command->info('Data demo pengadaan berhasil dibuat: '.count(self::WORK_PACKAGES).' pengadaan.');
    }

    /**
     * Create a single demo procurement following its track.
     *
     * @param  array{name: string, track: string}  $package
     * @param  Collection<int, WorkDirector>  $directors
     * @param  Collection<int, TargetUnit>  $units
     * @param  Collection<int, User>  $planners
     * @param  Collection<int, User>  $executors
     * @param  Collection<int, ChecklistItem>  $planningItems
     * @param  Collection<int, ChecklistItem>  $executionItems
     */
    protected function createProcurement(
        array $package,
        int $index,
        Collection $directors,
        Collection $units,
        Collection $planners,
        Collection $executors,
        ?User $teamLeader,
        Collection $planningItems,
        Collection $executionItems,
    ): void {
        $track = $package['track'];
        $createdAt = CarbonImmutable::now()->subDays($this->ageForTrack($track, $index));
        $target = $this->targetFor($track, $createdAt, $index);

        $methods = ProcurementMethod::query()->active()->ordered()->get();
        $budgetSources = BudgetSource::query()->active()->ordered()->get();

        $procurement = new Procurement([
            'name' => $package['name'],
            'work_director_id' => $directors[$index % $directors->count()]->id,
            'target_unit_id' => $units[($index * 3) % $units->count()]->id,
            'procurement_method_id' => $methods[$index % max(1, $methods->count())]->id ?? null,
            'budget_source_id' => $budgetSources[$index % max(1, $budgetSources->count())]->id ?? null,
            'prk_number' => sprintf('ND-%03d/PRK/%s', $index + 11, $createdAt->format('Y')),
            'hpe_value' => fake()->numberBetween(45, 4_800) * 1_000_000,
            'progress_status_id' => $this->statusFor($track)->id,
            'target_completion_date' => $index % 11 === 0 ? null : $target->toDateString(),
            'notes' => null,
        ]);

        $procurement->number = sprintf('PGD/%s/%04d', $createdAt->format('Y/m'), $index + 1);
        $procurement->planner_id = $planners[$index % $planners->count()]->id;
        $procurement->created_by = $teamLeader?->id;
        $procurement->created_at = $createdAt;
        $procurement->updated_at = $createdAt;
        $procurement->save();

        $this->log($procurement, $teamLeader, ActivityType::Dibuat, "Pengadaan {$procurement->number} dibuat.", $createdAt);
        $this->log($procurement, $teamLeader, ActivityType::PicDitunjuk, 'PIC Perencana ditunjuk.', $createdAt->addHours(3));

        $planningRatio = $this->planningRatio($track);
        $planningWindow = $this->window($createdAt, $track === 'inisiasi' ? $createdAt->addDays(30) : $target);

        $this->fillChecklist(
            $procurement,
            $planningItems,
            ProcurementStage::Perencanaan,
            $planningRatio,
            $planningWindow,
            $procurement->planner_id,
        );

        if (in_array($track, ['menunggu', 'ditolak', 'pelaksanaan', 'selesai'], true)) {
            $submittedAt = $createdAt->addDays(fake()->numberBetween(25, 60));

            $procurement->planning_approval_state = PlanningApprovalState::MenungguPersetujuan;
            $procurement->planning_submitted_at = $submittedAt;

            $this->log($procurement, $procurement->planner, ActivityType::PerencanaanDiajukan, 'Dokumen perencanaan diajukan untuk persetujuan.', $submittedAt);

            if ($track === 'ditolak') {
                $reviewedAt = $submittedAt->addDays(3);
                $procurement->planning_approval_state = PlanningApprovalState::Ditolak;
                $procurement->planning_reviewed_at = $reviewedAt;
                $procurement->planning_reviewed_by = $teamLeader?->id;
                $procurement->planning_review_note = 'RKS dan HPE perlu disesuaikan dengan spesifikasi terbaru.';

                $this->log($procurement, $teamLeader, ActivityType::PerencanaanDitolak, 'Dokumen perencanaan ditolak.', $reviewedAt);
            }

            if (in_array($track, ['pelaksanaan', 'selesai'], true)) {
                $reviewedAt = $submittedAt->addDays(fake()->numberBetween(2, 8));

                $procurement->planning_approval_state = PlanningApprovalState::Disetujui;
                $procurement->planning_reviewed_at = $reviewedAt;
                $procurement->planning_reviewed_by = $teamLeader?->id;
                $procurement->executor_id = $executors[$index % $executors->count()]->id;

                $this->log($procurement, $teamLeader, ActivityType::PerencanaanDisetujui, 'Dokumen perencanaan disetujui.', $reviewedAt);
                $this->log($procurement, $teamLeader, ActivityType::PicDitunjuk, 'PIC Pelaksana ditunjuk.', $reviewedAt->addHour());

                $this->fillChecklist(
                    $procurement,
                    $executionItems,
                    ProcurementStage::Pelaksanaan,
                    $track === 'selesai' ? 1.0 : fake()->randomFloat(2, 0.15, 0.85),
                    $this->window($reviewedAt, $target),
                    $procurement->executor_id,
                );
            }
        }

        if ($track === 'selesai') {
            $completedAt = $target->subDays(fake()->numberBetween(0, 12));
            $procurement->completed_at = $completedAt;

            $this->log($procurement, $teamLeader, ActivityType::PengadaanSelesai, 'Pengadaan dinyatakan selesai.', $completedAt);
        }

        $procurement->save();

        if (in_array($track, ['pelaksanaan', 'selesai'], true)) {
            $this->generateDocuments($procurement, $teamLeader);
        }
    }

    /**
     * Tick a share of a stage's checklist, spreading the timestamps over a window.
     *
     * @param  Collection<int, ChecklistItem>  $items
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $window
     */
    protected function fillChecklist(
        Procurement $procurement,
        Collection $items,
        ProcurementStage $stage,
        float $ratio,
        array $window,
        ?int $userId,
    ): void {
        $completedCount = (int) round($items->count() * $ratio);
        $span = max(1, $window['start']->diffInHours($window['end']));

        foreach ($items->values() as $position => $item) {
            $isCompleted = $position < $completedCount;
            $completedAt = null;

            if ($isCompleted) {
                $offset = (int) round(($position + 1) / max(1, $completedCount + 1) * $span);
                $completedAt = $window['start']->addHours($offset);
            }

            $procurement->checklists()->create([
                'checklist_item_id' => $item->id,
                'stage' => $stage,
                'is_completed' => $isCompleted,
                'completed_at' => $completedAt,
                'completed_by' => $isCompleted ? $userId : null,
                'created_at' => $window['start'],
                'updated_at' => $completedAt ?? $window['start'],
            ]);
        }
    }

    /**
     * Generate the planning documents for a procurement.
     */
    protected function generateDocuments(Procurement $procurement, ?User $author): void
    {
        if ($author === null) {
            return;
        }

        $types = DocumentType::query()
            ->active()
            ->whereIn('code', ['nota-dinas-usulan', 'rks', 'hpe'])
            ->get();

        foreach ($types as $type) {
            if (! $type->activeTemplate()->exists()) {
                continue;
            }

            app(DocumentGenerator::class)->generate($procurement->refresh(), $type, $author);
        }
    }

    /**
     * Append a backdated activity entry.
     */
    protected function log(
        Procurement $procurement,
        ?User $actor,
        ActivityType $type,
        string $description,
        CarbonImmutable $at,
    ): void {
        $procurement->activities()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'description' => $description,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /**
     * A sensible completion window for a stage.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    protected function window(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $now = CarbonImmutable::now();
        $limit = $end->greaterThan($now) ? $now : $end;

        return [
            'start' => $start,
            'end' => $limit->greaterThan($start) ? $limit : $start->addDays(7),
        ];
    }

    /**
     * How old a procurement on a given track should be.
     */
    protected function ageForTrack(string $track, int $index): int
    {
        return match ($track) {
            'selesai' => 250 + $index * 5,
            'pelaksanaan' => 130 + $index * 4,
            'menunggu' => 70 + $index * 2,
            'ditolak' => 60 + $index,
            'penyusunan' => 40 + $index,
            'batal' => 90 + $index,
            default => 12 + $index,
        };
    }

    /**
     * The target completion date of a track.
     *
     * Running procurements are spread around today so the boards show a mix of
     * on-schedule, due-soon and overdue work.
     */
    protected function targetFor(string $track, CarbonImmutable $createdAt, int $index): CarbonImmutable
    {
        $spread = [-38, -9, 7, 12, 52, 96];

        return match ($track) {
            'selesai' => $createdAt->addDays(fake()->numberBetween(120, 200)),
            'pelaksanaan' => CarbonImmutable::now()->addDays($spread[$index % count($spread)]),
            'batal' => $createdAt->addDays(fake()->numberBetween(80, 140)),
            default => CarbonImmutable::now()->addDays(fake()->numberBetween(18, 150)),
        };
    }

    /**
     * How much of the planning checklist a track has completed.
     */
    protected function planningRatio(string $track): float
    {
        return match ($track) {
            'selesai', 'pelaksanaan', 'menunggu' => 1.0,
            'ditolak' => 0.85,
            'penyusunan' => fake()->randomFloat(2, 0.45, 0.75),
            'batal' => 0.3,
            default => fake()->randomFloat(2, 0.08, 0.35),
        };
    }

    /**
     * The progress status a track should sit on.
     */
    protected function statusFor(string $track): ProgressStatus
    {
        $slug = match ($track) {
            'selesai' => 'proses-validasi',
            'pelaksanaan' => 'disposisi-ams',
            'menunggu' => 'kelengkapan-dokumen',
            'ditolak' => 'penyusunan-rks',
            'penyusunan' => 'penyusunan-rks',
            'batal' => 'batal',
            default => 'inisiasi-laksdan',
        };

        return ProgressStatus::query()->where('slug', $slug)->firstOrFail();
    }
}
