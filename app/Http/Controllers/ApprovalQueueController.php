<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Models\User;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalQueueController extends Controller
{
    /**
     * The approval queues, each held by whoever holds its review right:
     * planning submissions for TL ICC, execution submissions for TL Pengadaan.
     *
     * @var array<string, array{label: string, permission: Permission}>
     */
    protected const QUEUES = [
        'planning' => ['label' => 'Perencanaan', 'permission' => Permission::ReviewPlanning],
        'execution' => ['label' => 'Pelaksanaan', 'permission' => Permission::ReviewExecution],
    ];

    /**
     * Show only the queues the user decides on, one at a time, filtered and
     * paginated on the server so a long queue stays light.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        /** @var User $user */
        $user = $request->user();

        $queues = array_keys(array_filter(
            self::QUEUES,
            fn (array $queue): bool => $user->hasPermission($queue['permission']),
        ));

        $queue = in_array($request->string('queue')->value(), $queues, true)
            ? $request->string('queue')->value()
            : ($queues[0] ?? null);
        $view = $request->string('view')->value() === 'decided' ? 'decided' : 'waiting';

        $procurements = $queue === null
            ? null
            : ProcurementFilters::apply($this->queueQuery($user, $queue, $view), $request)
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'targetUnits', 'progressStatus', 'planner', 'executor', 'checklists'])
                ->paginate(15)
                ->withQueryString();

        return Inertia::render('approvals/index', [
            'queues' => array_map(fn (string $key): array => [
                'key' => $key,
                'label' => self::QUEUES[$key]['label'],
                'waiting' => $this->queueQuery($user, $key, 'waiting')->count(),
            ], $queues),
            'queue' => $queue,
            'view' => $view,
            'procurements' => $procurements === null ? null : ProcurementResource::collection($procurements),
            'filters' => ProcurementFilters::current($request),
            'options' => MasterDataOptions::forFilters(),
        ]);
    }

    /**
     * The procurements of one queue the user may see: those waiting for the
     * decision (oldest submission first), or those already decided (latest
     * decision first).
     *
     * @return Builder<Procurement>
     */
    protected function queueQuery(User $user, string $queue, string $view): Builder
    {
        $query = Procurement::query()->visibleTo($user);
        $decided = [PlanningApprovalState::Disetujui->value, PlanningApprovalState::Ditolak->value];

        if ($queue === 'planning') {
            return $view === 'waiting'
                ? $query->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                    ->orderBy('planning_submitted_at')->orderBy('id')
                : $query->whereIn('planning_approval_state', $decided)
                    ->whereNotNull('planning_reviewed_at')
                    ->latest('planning_reviewed_at')->latest('id');
        }

        return $view === 'waiting'
            ? $query->where('execution_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                ->whereNull('finance_submitted_at')
                ->orderBy('execution_submitted_at')->orderBy('id')
            : $query->whereIn('execution_approval_state', $decided)
                ->whereNotNull('execution_reviewed_at')
                ->latest('execution_reviewed_at')->latest('id');
    }
}
