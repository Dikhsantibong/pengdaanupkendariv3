<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\FinanceSection;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\UserRole;
use App\Models\BudgetSource;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Demo data that walks the whole journey of a job, for seeing the
 * application with a realistic amount of work in it.
 *
 * Every job starts as a staff proposal and goes as far as its track says:
 * waiting for the TL or the Asman, returned, waiting for a planning PIC,
 * planned, submitted to ICC, returned by ICC, executed, ready to hand over,
 * at Keuangan (Penyerahan and Pembayaran partly checked), paid and closed,
 * or cancelled. Each step is logged with a backdated time, so the Riwayat
 * Approval tab, the dashboards of every role and the boards all show a
 * believable history. Jobs that reach execution get their real contract
 * drawn from its template (SPK Barang, SPK Jasa, Surat Perjanjian or
 * Lampiran Surat Pesanan), so their delivery and payment requirements are
 * read from it.
 *
 * Not part of DatabaseSeeder. After `php artisan migrate --seed`, run:
 *
 *     php artisan db:seed --class=DemoProcurementSeeder
 *
 * Running it again first removes the demo jobs it made before (recognised by
 * their note), so it can be re-run to get a fresh set; nothing else is
 * touched.
 */
class DemoProcurementSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The note that marks a demo job, so a re-run can find and replace it.
     */
    public const MARKER = 'Data demo (DemoProcurementSeeder).';

    /**
     * How many jobs stand at each point of the journey.
     *
     * @var array<string, int>
     */
    private const TRACKS = [
        'usulan_tl' => 5,
        'usulan_asman' => 5,
        'usulan_kembali' => 3,
        'perlu_pic' => 4,
        'penyusunan' => 8,
        'menunggu_icc' => 6,
        'ditolak_icc' => 4,
        'pelaksanaan' => 12,
        'siap_serah' => 4,
        'keuangan' => 8,
        'selesai' => 10,
        'batal' => 3,
    ];

    /**
     * The work the demo jobs are about.
     *
     * @var array<int, string>
     */
    private const WORK = [
        'Overhaul Mesin Diesel Unit 3',
        'Pengadaan Spare Part Turbocharger',
        'Rehabilitasi Sistem Pendingin Utama',
        'Pemeliharaan Rutin Generator Unit 1',
        'Penggantian Panel Kontrol MV',
        'Pengadaan Pelumas Mesin',
        'Perbaikan Instalasi Bahan Bakar',
        'Kalibrasi Instrumentasi dan Proteksi',
        'Jasa Cleaning Boiler',
        'Peremajaan Sistem Grounding',
        'Pengadaan Material Pemeliharaan Trafo',
        'Jasa Inspeksi Keandalan Pembangkit',
        'Pengadaan APD dan Perlengkapan K3',
        'Perbaikan Jalan Akses Area Pembangkit',
        'Pengadaan Sistem Monitoring Emisi',
        'Pemeliharaan Sistem Proteksi Kebakaran',
        'Pengadaan Genset Cadangan Portable',
        'Jasa Pengelolaan Limbah B3',
        'Pengadaan Baterai Bank Unit Isolated',
        'Perbaikan Dermaga Suplai Bahan Bakar',
        'Pengadaan Alat Ukur Laboratorium',
        'Modernisasi Sistem SCADA Pembangkit',
        'Pengadaan Pompa Air Pendingin Cadangan',
        'Jasa Konsultansi Studi Kelayakan',
        'Pengadaan Filter Udara Mesin',
        'Penggantian Radiator Unit 2',
        'Jasa Pengecatan Tangki Timbun',
        'Pengadaan Kabel Kontrol dan Power',
        'Perbaikan Atap Gedung Sentral',
        'Pengadaan Sparepart Governor',
        'Jasa Uji Minyak Trafo',
        'Pengadaan Seragam dan Wearpack',
        'Pemeliharaan AC Ruang Kontrol',
        'Pengadaan Bearing dan Seal Pompa',
        'Jasa Pembersihan Saluran Drainase',
        'Pengadaan Lampu Penerangan Area',
    ];

    /**
     * The contracts the demo jobs are let under, in turn.
     *
     * @var array<int, array{format: string, category: string|null, method: string}>
     */
    private const CONTRACTS = [
        ['format' => 'SPK', 'category' => 'barang', 'method' => 'spk'],
        ['format' => 'SPK', 'category' => 'jasa', 'method' => 'spk'],
        ['format' => 'PJ', 'category' => 'barang', 'method' => 'tender'],
        ['format' => 'SPPL', 'category' => null, 'method' => 'surat-pesanan'],
        ['format' => 'PJ', 'category' => 'jasa', 'method' => 'tender'],
        ['format' => 'SPK', 'category' => 'jasa', 'method' => 'spk'],
    ];

    /**
     * The partners the contracts are signed with.
     *
     * @var array<int, array{name: string, director: string, address: string, bank: string}>
     */
    private const PARTNERS = [
        ['name' => 'PT Sinar Teknik Sulawesi', 'director' => 'Andi Mappaturung', 'address' => 'Jl. Malik Raya No. 21, Kendari', 'bank' => 'Bank Mandiri'],
        ['name' => 'CV Mandiri Jaya Abadi', 'director' => 'Hasanuddin', 'address' => 'Jl. Saranani No. 8, Kendari', 'bank' => 'Bank BRI'],
        ['name' => 'PT Karya Energi Nusantara', 'director' => 'Rudy Hartono', 'address' => 'Jl. Pettarani No. 112, Makassar', 'bank' => 'Bank BNI'],
        ['name' => 'PT Patria Power Service', 'director' => 'Yohanes Saputra', 'address' => 'Jl. Raya Bekasi Km 21, Jakarta Timur', 'bank' => 'Bank Mandiri'],
        ['name' => 'CV Bumi Anoa Teknik', 'director' => 'La Ode Hamid', 'address' => 'Jl. Bunggasi No. 45, Kendari', 'bank' => 'Bank Sultra'],
        ['name' => 'PT Mitra Kelistrikan Indonesia', 'director' => 'Siti Rahmawati', 'address' => 'Jl. Gatot Subroto Kav. 9, Jakarta', 'bank' => 'Bank BCA'],
    ];

    /**
     * The progress statuses, by slug.
     *
     * @var Collection<string, ProgressStatus>
     */
    protected Collection $statuses;

    /**
     * The demo jobs made so far, for the summary.
     *
     * @var array<string, int>
     */
    protected array $made = [];

    public function __construct(
        protected ProcurementService $procurements,
        protected DocumentGenerator $documents,
    ) {}

    /**
     * Lay out the demo jobs.
     */
    public function run(): void
    {
        $proposers = User::query()->active()->withRole([UserRole::Pengusul])
            ->whereNotNull('work_director_id')->with('teamLeader')->get();
        $planners = User::query()->active()->withRole([UserRole::PicPerencana])->get();
        $executors = User::query()->active()->withRole([UserRole::PicPelaksana])->get();
        $finance = User::query()->active()->withRole([UserRole::Keuangan])->get();
        // The seeded accounts have no TL ICC; the administrator reviews then.
        $administrator = User::query()->withRole([UserRole::Administrator])->first();
        $iccLead = User::query()->withRole([UserRole::TeamLeaderIcc])->first() ?? $administrator;
        $procurementLead = User::query()->withRole([UserRole::TeamLeaderPengadaan])->first() ?? $administrator;

        if ($proposers->isEmpty() || $planners->isEmpty() || $executors->isEmpty() || $finance->isEmpty()
            || $iccLead === null || $procurementLead === null
            || ContractNumberFormat::query()->count() === 0) {
            throw new RuntimeException('Jalankan `php artisan migrate --seed` (termasuk DivisionUserSeeder) sebelum data demo.');
        }

        $this->removePreviousDemo();

        $this->statuses = ProgressStatus::query()->get()->keyBy('slug');

        $index = 0;

        foreach (self::TRACKS as $track => $count) {
            for ($i = 0; $i < $count; $i++, $index++) {
                $this->makeJob($track, $index, [
                    'proposer' => $proposers[$index % $proposers->count()],
                    'planner' => $planners[$index % $planners->count()],
                    'executor' => $executors[$index % $executors->count()],
                    'finance' => $finance[$index % $finance->count()],
                    'icc' => $iccLead,
                    'procurement' => $procurementLead,
                ]);

                $this->made[$track] = ($this->made[$track] ?? 0) + 1;
            }
        }

        $this->command->info("Data demo pengadaan dibuat: {$index} pengadaan.");
        $this->command->table(['Posisi', 'Jumlah'], collect($this->made)->map(fn (int $n, string $track): array => [$track, $n])->values()->all());
    }

    /**
     * Take one job as far as its track goes.
     *
     * @param  array{proposer: User, planner: User, executor: User, finance: User, icc: User, procurement: User}  $people
     */
    protected function makeJob(string $track, int $index, array $people): void
    {
        $proposer = $people['proposer'];
        $teamLeader = $proposer->teamLeader;
        $asman = User::query()->withRole([UserRole::DireksiPekerjaan])
            ->where('work_director_id', $proposer->work_director_id)->first() ?? $people['procurement'];

        $at = CarbonImmutable::now()->subDays($this->age($track, $index))->setTime(8 + $index % 7, ($index * 13) % 60);
        $units = TargetUnit::query()->active()->get();
        $work = self::WORK[$index % count(self::WORK)];
        $unit = $units[$index % $units->count()];

        // 1. The staff member proposes the work.
        $procurement = new Procurement([
            'name' => $index < count(self::WORK) ? $work : "{$work} {$unit->name}",
            'work_director_id' => $proposer->work_director_id,
            'target_unit_id' => $unit->id,
            'budget_source_id' => BudgetSource::query()->active()->ordered()->get()[$index % 2]->id ?? null,
            'hpe_value' => fake()->numberBetween(25, 3_500) * 1_000_000,
            'prk_number' => sprintf('PRK-%03d/%s', 100 + $index, $at->format('Y')),
            'progress_status_id' => $this->status('inisiasi-laksdan'),
            'target_completion_date' => $at->addDays(90 + ($index % 5) * 30)->toDateString(),
            'notes' => self::MARKER,
        ]);
        $procurement->number = $this->usulanNumber($at);
        $procurement->created_by = $proposer->id;
        $procurement->proposal_submitted_at = $at;
        $procurement->proposal_state = $teamLeader !== null ? ProposalState::MenungguPersetujuanTl : ProposalState::MenungguPersetujuan;
        $procurement->created_at = $at;
        $procurement->updated_at = $at;
        $procurement->save();
        $procurement->targetUnits()->sync([$unit->id => ['sort_order' => 1]]);

        $this->log($procurement, $proposer, ActivityType::Dibuat, "Pengadaan {$procurement->number} dibuat.", $at);
        $this->log($procurement, $proposer, ActivityType::UsulanDiajukan, 'Usulan pekerjaan diajukan ke '
            .($teamLeader !== null ? "TL {$teamLeader->name}" : 'Direksi Pekerjaan (Asman)').' untuk disetujui.', $at->addMinutes(10));
        $this->draw($procurement, ['tor', 'nota-dinas-usulan'], $proposer, $at->addMinutes(5));

        if ($track === 'usulan_tl' && $teamLeader !== null) {
            return;
        }

        // 2. The team leader agrees and passes it to the Asman.
        $at = $at->addDay();

        if ($teamLeader !== null) {
            $procurement->forceFill([
                'proposal_state' => ProposalState::MenungguPersetujuan,
                'proposal_tl_reviewed_at' => $at,
                'proposal_tl_reviewed_by' => $teamLeader->id,
            ])->save();
            $this->log($procurement, $teamLeader, ActivityType::UsulanDisetujuiTl, 'Usulan disetujui TL dan diteruskan ke Asman.', $at);
        }

        if (in_array($track, ['usulan_tl', 'usulan_asman'], true)) {
            return;
        }

        // 3. The Asman decides.
        $at = $at->addDay();

        if ($track === 'usulan_kembali') {
            $note = 'Lengkapi spesifikasi teknis dan estimasi volume pada TOR.';
            $procurement->forceFill([
                'proposal_state' => ProposalState::Dikembalikan,
                'proposal_reviewed_at' => $at,
                'proposal_reviewed_by' => $asman->id,
                'proposal_review_note' => $note,
            ])->save();
            $this->log($procurement, $asman, ActivityType::UsulanDikembalikan, "Usulan pekerjaan dikembalikan ke pengusul. Catatan: {$note}", $at);

            return;
        }

        $procurement->forceFill([
            'proposal_state' => ProposalState::Disetujui,
            'proposal_reviewed_at' => $at,
            'proposal_reviewed_by' => $asman->id,
        ])->save();
        $this->log($procurement, $asman, ActivityType::UsulanDisetujui, 'Usulan disetujui Asman dan diteruskan ke bagian pengadaan.', $at);
        $this->procurements->syncChecklists($procurement);

        if ($track === 'perlu_pic') {
            return;
        }

        // 4. Procurement appoints the planning PIC and fills in the contract.
        $at = $at->addDays(2);
        $contract = self::CONTRACTS[$index % count(self::CONTRACTS)];
        $format = ContractNumberFormat::query()->where('code', $contract['format'])->firstOrFail();

        $procurement->forceFill([
            'planner_id' => $people['planner']->id,
            'contract_number_format_id' => $format->id,
            'work_category' => $contract['category'],
            'contract_type_id' => $this->contractType($contract['category']),
            'procurement_method_id' => ProcurementMethod::query()->where('code', $contract['method'])->value('id'),
            'progress_status_id' => $this->status('penyusunan-rks'),
        ])->save();
        $procurement->number = $this->procurements->nextNumber($format);
        $procurement->save();

        $this->log($procurement, $people['procurement'], ActivityType::PicDitunjuk, "PIC Perencana diubah menjadi {$people['planner']->name}.", $at, ['pic' => 'perencana']);
        $this->procurements->syncChecklists($procurement);

        if ($track === 'penyusunan' || $track === 'batal') {
            $this->tick($procurement, ProcurementStage::Perencanaan, fake()->randomFloat(2, 0.2, 0.7), $at, $people['planner']);

            if ($track === 'batal') {
                $procurement->forceFill(['progress_status_id' => $this->status('batal')])->save();
            }

            return;
        }

        // 5. The planner completes the checklist and submits it to ICC.
        $this->tick($procurement, ProcurementStage::Perencanaan, 1.0, $at, $people['planner']);
        $at = $at->addDays(14 + $index % 10);

        $procurement->forceFill([
            'planning_approval_state' => PlanningApprovalState::MenungguPersetujuan,
            'planning_submitted_at' => $at,
            'progress_status_id' => $this->status('kelengkapan-dokumen'),
        ])->save();
        $this->log($procurement, $people['planner'], ActivityType::PerencanaanDiajukan, 'Dokumen perencanaan diajukan untuk persetujuan.', $at);

        if ($track === 'menunggu_icc') {
            return;
        }

        // Some plans are returned once before they pass.
        $returnedOnce = $track === 'ditolak_icc' || $index % 3 === 0;

        if ($returnedOnce) {
            $at = $at->addDays(3);
            $note = 'RKS dan HPE perlu disesuaikan dengan spesifikasi terbaru.';
            $procurement->forceFill([
                'planning_approval_state' => PlanningApprovalState::Ditolak,
                'planning_reviewed_at' => $at,
                'planning_reviewed_by' => $people['icc']->id,
                'planning_review_note' => $note,
                'progress_status_id' => $this->status('penyusunan-rks'),
            ])->save();
            $this->log($procurement, $people['icc'], ActivityType::PerencanaanDitolak, "Dokumen perencanaan dikembalikan untuk revisi. Catatan: {$note}", $at);

            if ($track === 'ditolak_icc') {
                return;
            }

            $at = $at->addDays(4);
            $procurement->forceFill([
                'planning_approval_state' => PlanningApprovalState::MenungguPersetujuan,
                'planning_submitted_at' => $at,
                'planning_revision' => 1,
            ])->save();
            $this->log($procurement, $people['planner'], ActivityType::PerencanaanDiajukan, 'Dokumen perencanaan diajukan ulang (revisi ke-1).', $at);
        }

        // 6. ICC approves; the execution PIC takes over.
        $at = $at->addDays(2 + $index % 4);
        $partner = self::PARTNERS[$index % count(self::PARTNERS)];
        $start = $at->addDays(7);

        $procurement->forceFill([
            'planning_approval_state' => PlanningApprovalState::Disetujui,
            'planning_reviewed_at' => $at,
            'planning_reviewed_by' => $people['icc']->id,
            'planning_review_note' => null,
            'executor_id' => $people['executor']->id,
            'progress_status_id' => $this->status($index % 2 === 0 ? 'penawaran-harga' : 'disposisi-ams'),
            'partner_name' => $partner['name'],
            'partner_director_name' => $partner['director'],
            'partner_address' => $partner['address'],
            'quotation_number' => sprintf('%03d/SPH/%s/%s', 40 + $index, strtoupper(substr($partner['name'], 3, 3)), $at->format('Y')),
            'quotation_date' => $at->subDays(5)->toDateString(),
            'value_after_negotiation' => round((float) $procurement->hpe_value * fake()->randomFloat(2, 0.86, 0.97), -3),
            'execution_start_date' => $start->toDateString(),
            'execution_duration_days' => [30, 45, 60, 90][$index % 4],
            'warranty_months' => [6, 12, 18][$index % 3],
            'bank_name' => $partner['bank'],
            'bank_account_number' => (string) (1_250_000_000 + $index * 7_919),
            'bank_account_holder' => $partner['name'],
            'target_completion_date' => $start->addDays([30, 45, 60, 90][$index % 4] + 14)->toDateString(),
        ])->save();
        $this->log($procurement, $people['icc'], ActivityType::PerencanaanDisetujui, 'Dokumen perencanaan disetujui.', $at);
        $this->log($procurement, $people['procurement'], ActivityType::PicDitunjuk, "PIC Pelaksana diubah menjadi {$people['executor']->name}.", $at->addHour(), ['pic' => 'pelaksana']);

        // The planning papers and the contract itself, from their templates.
        $this->draw($procurement, ['rks', 'hpe', 'upb'], $people['planner'], $at->subDays(10));
        $this->draw($procurement, [$contract['format'] === 'SPPL' ? 'lampiran-sp' : 'spk'], $people['executor'], $at->addDays(2));
        $this->procurements->syncChecklists($procurement);

        if ($track === 'pelaksanaan') {
            $this->tick($procurement, ProcurementStage::Pelaksanaan, fake()->randomFloat(2, 0.2, 0.8), $start, $people['executor']);

            return;
        }

        // 7. Execution done.
        $this->tick($procurement, ProcurementStage::Pelaksanaan, 1.0, $start, $people['executor']);

        if ($track === 'siap_serah') {
            return;
        }

        // 8. Handed to Keuangan.
        $at = CarbonImmutable::parse($procurement->target_completion_date)->min(CarbonImmutable::now()->subDays(2 + $index % 9));
        $procurement->forceFill([
            'finance_submitted_at' => $at,
            'finance_submitted_by' => $people['executor']->id,
            'progress_status_id' => $this->status('disposisi-ams'),
        ])->save();
        $this->log($procurement, $people['executor'], ActivityType::DiajukanKeKeuangan, 'Pelaksanaan selesai, pengadaan diserahkan ke Keuangan untuk proses pembayaran.', $at);
        $this->procurements->syncChecklists($procurement);

        if ($track === 'keuangan') {
            $this->tick($procurement, ProcurementStage::Keuangan, fake()->randomFloat(2, 0.4, 1.0), $at, $people['finance'], FinanceSection::Penyerahan);
            $this->tick($procurement, ProcurementStage::Keuangan, fake()->randomFloat(2, 0.0, 0.6), $at->addDay(), $people['finance'], FinanceSection::Pembayaran);

            return;
        }

        // 9. Paid and closed.
        $this->tick($procurement, ProcurementStage::Keuangan, 1.0, $at, $people['finance']);
        $paidAt = $at->addDays(5 + $index % 10)->min(CarbonImmutable::now()->subHour());

        $procurement->forceFill([
            'payment_completed_at' => $paidAt,
            'payment_completed_by' => $people['finance']->id,
            'completed_at' => $paidAt,
            'progress_status_id' => $this->status('selesai'),
        ])->save();
        $this->log($procurement, $people['finance'], ActivityType::PembayaranSelesai, 'Pembayaran ke mitra selesai, pengadaan ditutup.', $paidAt);
    }

    /**
     * Tick a share of a stage's checklist, the timestamps spread after a start.
     */
    protected function tick(
        Procurement $procurement,
        ProcurementStage $stage,
        float $ratio,
        CarbonImmutable $from,
        User $by,
        ?FinanceSection $section = null,
    ): void {
        $rows = $procurement->checklists()
            ->where('stage', $stage->value)
            ->with('checklistItem')
            ->get()
            ->filter(fn ($row): bool => $section === null || $row->checklistItem->section === $section)
            ->sortBy(fn ($row): int => $row->checklistItem->sort_order)
            ->values();

        $done = (int) round($rows->count() * $ratio);

        foreach ($rows as $position => $row) {
            if ($position >= $done) {
                break;
            }

            $at = $from->addHours(($position + 1) * 6)->min(CarbonImmutable::now()->subMinutes(5));

            $row->forceFill([
                'is_completed' => true,
                'completed_at' => $at,
                'completed_by' => $by->id,
                'updated_at' => $at,
            ])->save();
        }
    }

    /**
     * Draw documents from their templates; one without a template is skipped.
     *
     * The document and its history entry are dated when the step happened,
     * not when the seeder ran, so the job's timeline stays in order.
     *
     * @param  array<int, string>  $codes
     */
    protected function draw(Procurement $procurement, array $codes, User $author, CarbonImmutable $at): void
    {
        foreach ($codes as $code) {
            $type = DocumentType::query()->active()->where('code', $code)->first();

            if ($type === null) {
                continue;
            }

            try {
                $document = $this->documents->generate($procurement->refresh(), $type, $author);
            } catch (Throwable) {
                // No template for this contract: the demo goes on without it.
                continue;
            }

            $document->forceFill(['generated_at' => $at, 'created_at' => $at, 'updated_at' => $at])->save();

            $procurement->activities()
                ->where('type', ActivityType::DokumenDigenerate->value)
                ->latest('id')
                ->first()
                ?->forceFill(['created_at' => $at, 'updated_at' => $at])
                ->save();
        }

        $procurement->unsetRelation('documents');
    }

    /**
     * Append a backdated history entry.
     *
     * @param  array<string, mixed>|null  $meta
     */
    protected function log(Procurement $procurement, ?User $actor, ActivityType $type, string $description, CarbonImmutable $at, ?array $meta = null): void
    {
        $procurement->activities()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'description' => $description,
            'meta' => $meta,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /**
     * Remove the demo jobs of an earlier run, and only those.
     */
    protected function removePreviousDemo(): void
    {
        $previous = Procurement::withTrashed()->where('notes', self::MARKER)->get();

        foreach ($previous as $procurement) {
            $procurement->forceDelete();
        }

        if ($previous->isNotEmpty()) {
            $this->command->warn("{$previous->count()} data demo sebelumnya dihapus.");
        }
    }

    /**
     * A free proposal number in the month the proposal was made.
     */
    protected function usulanNumber(CarbonImmutable $at): string
    {
        $prefix = 'USL/'.$at->format('Y/m').'/';
        $sequence = 1;

        while (Procurement::withTrashed()->where('number', $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT))->exists()) {
            $sequence++;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * The contract type matching a goods/services category, if there is one.
     */
    protected function contractType(?string $category): ?int
    {
        $types = ContractType::query()->get();

        $match = $category === null ? null : $types->first(fn (ContractType $type): bool => strcasecmp($type->name, $category) === 0);

        return ($match ?? $types->first())?->id;
    }

    /**
     * The id of a progress status by its slug, falling back to the first one.
     */
    protected function status(string $slug): int
    {
        return (int) ($this->statuses->get($slug) ?? $this->statuses->first())->id;
    }

    /**
     * How long ago a job on a track was proposed.
     */
    protected function age(string $track, int $index): int
    {
        return match ($track) {
            'usulan_tl', 'usulan_asman', 'usulan_kembali' => 2 + $index % 12,
            'perlu_pic' => 10 + $index % 10,
            'penyusunan', 'batal' => 30 + $index % 20,
            'menunggu_icc', 'ditolak_icc' => 50 + $index % 25,
            'pelaksanaan' => 90 + $index % 40,
            'siap_serah' => 130 + $index % 20,
            'keuangan' => 160 + $index % 30,
            default => 200 + ($index % 15) * 10,
        };
    }
}
