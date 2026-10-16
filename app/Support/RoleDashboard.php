<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\UserRole;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the dashboard shows to someone who does not oversee every
 * procurement: only the figures and jobs their role works with.
 *
 * The layout follows the role (a proposer sees their proposals, a PIC their
 * own assignments, Keuangan its invoices), while every figure is drawn from
 * the procurements the user may see, so a right granted or taken away on the
 * Hak Akses screen is reflected here as everywhere else.
 *
 * @phpstan-type Card array{key: string, label: string, value: int|float, format: string, hint: string|null, tone: string}
 * @phpstan-type Section array{0: list<Card>, 1: Builder<Procurement>, 2: string}
 */
class RoleDashboard
{
    /**
     * The journey of a job, in order.
     *
     * @var array<string, string>
     */
    public const STAGES = [
        'usulan' => 'Usulan',
        'perencanaan' => 'Perencanaan',
        'pelaksanaan' => 'Pelaksanaan',
        'keuangan' => 'Keuangan',
        'selesai' => 'Selesai',
    ];

    /**
     * How many jobs the list shows.
     */
    private const JOB_LIMIT = 8;

    /**
     * Which dashboard a user gets.
     *
     * Overseeing every procurement earns the management dashboard whatever
     * the role is called; otherwise the role decides.
     */
    public static function profile(User $user): string
    {
        if ($user->hasPermission(Permission::ViewAllProcurements)) {
            return 'manajemen';
        }

        return match ($user->role) {
            UserRole::Pengusul => 'pengusul',
            UserRole::DireksiPekerjaan => 'direksi',
            UserRole::PicPerencana => 'perencana',
            UserRole::PicPelaksana => 'pelaksana',
            UserRole::Keuangan => 'keuangan',
            default => 'umum',
        };
    }

    /**
     * The role's own dashboard content.
     *
     * @return array{cards: list<Card>, stages: list<array{key: string, label: string, total: int}>, jobs: list<array<string, mixed>>, jobsTitle: string}
     */
    public static function for(User $user, string $profile): array
    {
        $visible = fn (): Builder => Procurement::query()->visibleTo($user);

        [$cards, $jobs, $jobsTitle] = match ($profile) {
            'pengusul' => self::proposer($user),
            'direksi' => self::director($user, $visible),
            'perencana' => self::planner($user),
            'pelaksana' => self::executor($user),
            'keuangan' => self::finance($visible),
            default => self::general($visible),
        };

        return [
            'cards' => $cards,
            'stages' => self::stages($profile === 'pengusul'
                ? Procurement::query()->where('created_by', $user->id)
                : $visible()),
            'jobs' => self::jobs($jobs),
            'jobsTitle' => $jobsTitle,
        ];
    }

    /**
     * The proposer's own proposals and where each one stands.
     *
     * @return Section
     */
    protected static function proposer(User $user): array
    {
        $mine = fn (): Builder => Procurement::query()->where('created_by', $user->id);

        $cards = [
            self::card('total', 'Usulan Saya', $mine()->count(), 'Seluruh pekerjaan yang Anda usulkan'),
            self::card('waiting', 'Menunggu Persetujuan', $mine()->whereIn('proposal_state', [
                ProposalState::MenungguPersetujuanTl->value,
                ProposalState::MenungguPersetujuan->value,
            ])->count(), 'Di TL atau Asman bidang', 'amber'),
            self::card('returned', 'Dikembalikan', $mine()->where('proposal_state', ProposalState::Dikembalikan->value)->count(), 'Perlu Anda perbaiki lalu ajukan ulang', 'red'),
            self::card('running', 'Sedang Diproses', $mine()->where('proposal_state', ProposalState::Disetujui->value)->whereNull('completed_at')->count(), 'Disetujui dan dikerjakan bagian pengadaan', 'sky'),
            self::card('done', 'Selesai', $mine()->whereNotNull('completed_at')->count(), 'Sudah dibayar dan ditutup', 'emerald'),
        ];

        // A team leader also follows the proposals of their staff.
        if ($user->leadsTeam()) {
            $cards[] = self::card('team', 'Usulan Tim Menunggu Anda', Procurement::query()
                ->where('proposal_state', ProposalState::MenungguPersetujuanTl->value)
                ->whereIn('created_by', User::query()->select('id')->where('team_leader_id', $user->id))
                ->count(), 'Perlu persetujuan Anda sebagai TL', 'violet');
        }

        return [$cards, $mine()->latest('updated_at'), 'Usulan Saya'];
    }

    /**
     * The Direksi Pekerjaan's division: proposals to decide and its jobs.
     *
     * @param  callable(): Builder<Procurement>  $visible
     * @return Section
     */
    protected static function director(User $user, callable $visible): array
    {
        $division = fn (): Builder => $visible()->where('work_director_id', $user->work_director_id ?? 0);

        return [[
            self::card('waiting', 'Usulan Menunggu Anda', $division()
                ->where('proposal_state', ProposalState::MenungguPersetujuan->value)
                ->count(), 'Sudah disetujui TL, menunggu persetujuan Asman', 'violet'),
            self::card('running', 'Pengadaan Bidang Berjalan', $division()->proposalApproved()->whereNull('completed_at')->count(), 'Di perencanaan, pelaksanaan, atau keuangan', 'sky'),
            self::card('done', 'Selesai', $division()->whereNotNull('completed_at')->count(), 'Sudah dibayar dan ditutup', 'emerald'),
            self::card('value', 'Nilai HPE Berjalan', (float) $division()->proposalApproved()->whereNull('completed_at')->sum('hpe_value'), 'Pengadaan bidang yang belum selesai', 'primary', 'currency'),
        ], $division()->whereNull('completed_at')->latest('updated_at'), 'Pengadaan Bidang Anda'];
    }

    /**
     * The planning PIC's own assignments.
     *
     * @return Section
     */
    protected static function planner(User $user): array
    {
        $mine = fn (): Builder => Procurement::query()->where('planner_id', $user->id);

        return [[
            self::card('todo', 'Perlu Dilengkapi', $mine()->inProgress()
                ->where('planning_approval_state', PlanningApprovalState::BelumDiajukan->value)
                ->count(), 'Checklist perencanaan belum diajukan', 'sky'),
            self::card('waiting', 'Menunggu Approval ICC', $mine()
                ->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                ->count(), 'Sudah diajukan, menunggu keputusan', 'amber'),
            self::card('returned', 'Dikembalikan', $mine()
                ->where('planning_approval_state', PlanningApprovalState::Ditolak->value)
                ->count(), 'Perbaiki sesuai catatan lalu ajukan ulang', 'red'),
            self::card('approved', 'Disetujui', $mine()
                ->where('planning_approval_state', PlanningApprovalState::Disetujui->value)
                ->count(), 'Diteruskan ke pelaksanaan', 'emerald'),
        ], $mine()->whereNull('completed_at')
            ->orderByRaw('case when planning_approval_state = ? then 0 when planning_approval_state = ? then 1 else 2 end', [
                PlanningApprovalState::Ditolak->value,
                PlanningApprovalState::BelumDiajukan->value,
            ])
            ->latest('updated_at'), 'Pengadaan yang Anda Rencanakan'];
    }

    /**
     * The execution PIC's own assignments.
     *
     * @return Section
     */
    protected static function executor(User $user): array
    {
        $running = fn (): Builder => Procurement::query()
            ->where('executor_id', $user->id)
            ->where('planning_approval_state', PlanningApprovalState::Disetujui->value)
            ->whereNull('finance_submitted_at')
            ->whereNull('completed_at');

        $ready = $running()
            ->whereIn('execution_approval_state', [PlanningApprovalState::BelumDiajukan->value, PlanningApprovalState::Ditolak->value])
            ->whereDoesntHave('checklists', fn (Builder $checklists) => $checklists
                ->where('stage', ProcurementStage::Pelaksanaan->value)
                ->where('is_completed', false)
                ->whereHas('checklistItem', fn (Builder $item) => $item->where('is_optional', false)))
            ->count();

        $mine = fn (): Builder => Procurement::query()->where('executor_id', $user->id);

        return [[
            self::card('running', 'Pekerjaan Berjalan', $running()->count(), 'Planning disetujui, sedang Anda laksanakan', 'sky'),
            self::card('ready', 'Siap Diajukan', $ready, 'Tahapan wajib selesai, ajukan ke TL Pengadaan', 'emerald'),
            self::card('review', 'Menunggu Approval TL Pengadaan', $running()
                ->where('execution_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                ->count(), 'Sudah diajukan, menunggu keputusan', 'violet'),
            self::card('finance', 'Di Keuangan', $mine()->whereNotNull('finance_submitted_at')->whereNull('payment_completed_at')->count(), 'Menunggu pembayaran', 'amber'),
            self::card('done', 'Selesai', $mine()->whereNotNull('completed_at')->count(), 'Sudah dibayar dan ditutup', 'primary'),
        ], $mine()->whereNull('completed_at')
            ->orderByRaw('target_completion_date is null')
            ->orderBy('target_completion_date'), 'Pengadaan yang Anda Laksanakan'];
    }

    /**
     * Keuangan's invoices.
     *
     * @param  callable(): Builder<Procurement>  $visible
     * @return Section
     */
    protected static function finance(callable $visible): array
    {
        $waiting = fn (): Builder => $visible()->whereNotNull('finance_submitted_at')->whereNull('payment_completed_at');
        $paid = fn (): Builder => $visible()->whereNotNull('payment_completed_at');

        $waitingValue = (float) $waiting()->get(['value_after_negotiation', 'hpe_value'])
            ->sum(fn (Procurement $procurement): float => (float) ($procurement->value_after_negotiation ?? $procurement->hpe_value));

        return [[
            self::card('waiting', 'Menunggu Pembayaran', $waiting()->count(), 'Pelaksanaan disetujui TL Pengadaan', 'amber'),
            self::card('value', 'Nilai Tagihan Menunggu', $waitingValue, 'Nilai setelah nego (atau HPE)', 'primary', 'currency'),
            self::card('month', 'Dibayar Bulan Ini', $paid()->where('payment_completed_at', '>=', now()->startOfMonth())->count(), now()->translatedFormat('F Y'), 'emerald'),
            self::card('paid', 'Total Sudah Dibayar', $paid()->count(), 'Seluruh pengadaan lunas', 'sky'),
        ], $waiting()->oldest('finance_submitted_at'), 'Tagihan Menunggu Pembayaran'];
    }

    /**
     * Anyone else: the procurements they take part in.
     *
     * @param  callable(): Builder<Procurement>  $visible
     * @return Section
     */
    protected static function general(callable $visible): array
    {
        return [[
            self::card('total', 'Pengadaan Saya', $visible()->count(), 'Yang Anda usulkan atau tangani'),
            self::card('running', 'Berjalan', $visible()->whereNull('completed_at')->count(), null, 'sky'),
            self::card('done', 'Selesai', $visible()->whereNotNull('completed_at')->count(), null, 'emerald'),
        ], $visible()->whereNull('completed_at')->latest('updated_at'), 'Pengadaan Saya'];
    }

    /**
     * How many of the given jobs are at each stage of the journey.
     *
     * The same rules as Procurement::currentStage(), in SQL, so the
     * figures cost one query whatever the number of jobs.
     *
     * @param  Builder<Procurement>  $query
     * @return list<array{key: string, label: string, total: int}>
     */
    public static function stages(Builder $query): array
    {
        $counts = $query->toBase()
            ->selectRaw("case
                when completed_at is not null then 'selesai'
                when finance_submitted_at is not null then 'keuangan'
                when planning_approval_state = ? then 'pelaksanaan'
                when proposal_state = ? then 'perencanaan'
                else 'usulan' end as stage, count(*) as total", [
                PlanningApprovalState::Disetujui->value,
                ProposalState::Disetujui->value,
            ])
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stages = [];

        foreach (self::STAGES as $key => $label) {
            $stages[] = ['key' => $key, 'label' => $label, 'total' => (int) ($counts[$key] ?? 0)];
        }

        return $stages;
    }

    /**
     * The first jobs of a list, as the dashboard table shows them.
     *
     * @param  Builder<Procurement>  $query
     * @return list<array<string, mixed>>
     */
    protected static function jobs(Builder $query): array
    {
        // Division and status are required and kept even once retired
        // (withTrashed), so both are always there.
        return array_values($query
            ->with(['progressStatus', 'workDirector'])
            ->limit(self::JOB_LIMIT)
            ->get()
            ->map(fn (Procurement $procurement): array => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'name' => $procurement->name,
                'division' => $procurement->workDirector->name,
                'stage' => $procurement->currentStage(),
                'note' => self::note($procurement),
                'status' => [
                    'name' => $procurement->progressStatus->name,
                    'category' => $procurement->progressStatus->category->value,
                ],
                'target_date' => $procurement->target_completion_date?->toDateString(),
                'hpe_value' => (float) $procurement->hpe_value,
            ])
            ->all());
    }

    /**
     * One line on what a job is waiting for.
     */
    public static function note(Procurement $procurement): string
    {
        return match (true) {
            $procurement->completed_at !== null => 'Selesai',
            $procurement->payment_completed_at !== null => 'Sudah dibayar',
            $procurement->finance_submitted_at !== null => 'Menunggu pembayaran Keuangan',
            $procurement->planning_approval_state === PlanningApprovalState::Disetujui => match (true) {
                $procurement->executor_id === null => 'Menunggu penunjukan PIC Pelaksana oleh TL Pengadaan',
                $procurement->execution_approval_state === PlanningApprovalState::MenungguPersetujuan => 'Pelaksanaan menunggu approval TL Pengadaan',
                $procurement->execution_approval_state === PlanningApprovalState::Ditolak => 'Pelaksanaan dikembalikan untuk diperbaiki',
                default => 'Dalam pelaksanaan',
            },
            $procurement->planning_approval_state === PlanningApprovalState::MenungguPersetujuan => 'Perencanaan menunggu approval ICC',
            $procurement->planning_approval_state === PlanningApprovalState::Ditolak => 'Perencanaan dikembalikan untuk revisi',
            $procurement->proposal_state === ProposalState::Disetujui => $procurement->planner_id === null
                ? 'Menunggu penunjukan PIC Perencana oleh TL ICC'
                : 'Dalam perencanaan',
            $procurement->proposal_state === ProposalState::Dikembalikan => 'Usulan dikembalikan, perlu diperbaiki',
            $procurement->proposal_state === ProposalState::MenungguPersetujuanTl => 'Menunggu persetujuan TL',
            default => 'Menunggu persetujuan Asman',
        };
    }

    /**
     * One figure card.
     *
     * @return Card
     */
    protected static function card(string $key, string $label, int|float $value, ?string $hint = null, string $tone = 'primary', string $format = 'number'): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'hint' => $hint,
            'tone' => $tone,
        ];
    }
}
