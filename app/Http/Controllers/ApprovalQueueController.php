<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementDeliveryOrder;
use App\Models\User;
use App\Support\ContractRequestQueue;
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
     * The queues of contract requests, open to whoever takes part in them
     * (TL, Asman, Rendan, Laksdan): their steps are not one right but the
     * people around the job, see ContractRequestQueue.
     *
     * @var array<string, string>
     */
    protected const REQUEST_QUEUES = [
        'amendment' => 'Amandemen',
        'delivery-order' => 'Delivery Order',
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

        foreach (array_keys(self::REQUEST_QUEUES) as $kind) {
            if (ContractRequestQueue::involvesIn($user, $kind)) {
                $queues[] = $kind;
            }
        }

        $queue = in_array($request->string('queue')->value(), $queues, true)
            ? $request->string('queue')->value()
            : ($queues[0] ?? null);
        $view = $request->string('view')->value() === 'decided' ? 'decided' : 'waiting';

        if ($queue !== null && isset(self::REQUEST_QUEUES[$queue])) {
            return Inertia::render('approvals/index', [
                ...$this->queueTabs($user, $queues),
                'queue' => $queue,
                'view' => $view,
                'procurements' => null,
                'requests' => $this->requests($user, $queue, $view),
                'filters' => ProcurementFilters::current($request),
                'options' => MasterDataOptions::forFilters(),
            ]);
        }

        $procurements = $queue === null
            ? null
            : ProcurementFilters::apply($this->queueQuery($user, $queue, $view), $request)
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'targetUnits', 'progressStatus', 'planner', 'executor', 'checklists'])
                ->paginate(15)
                ->withQueryString();

        return Inertia::render('approvals/index', [
            ...$this->queueTabs($user, $queues),
            'queue' => $queue,
            'view' => $view,
            'procurements' => $procurements === null ? null : ProcurementResource::collection($procurements),
            'requests' => null,
            'filters' => ProcurementFilters::current($request),
            'options' => MasterDataOptions::forFilters(),
        ]);
    }

    /**
     * The queue tabs with how many wait in each.
     *
     * @param  list<string>  $queues
     * @return array{queues: list<array{key: string, label: string, waiting: int}>}
     */
    protected function queueTabs(User $user, array $queues): array
    {
        return ['queues' => array_map(fn (string $key): array => [
            'key' => $key,
            'label' => self::QUEUES[$key]['label'] ?? self::REQUEST_QUEUES[$key],
            'waiting' => match ($key) {
                'amendment' => ContractRequestQueue::amendmentsAwaiting($user)->count(),
                'delivery-order' => ContractRequestQueue::deliveryOrdersAwaiting($user)->count(),
                default => $this->queueQuery($user, $key, 'waiting')->count(),
            },
        ], $queues)];
    }

    /**
     * One page of amendments or Delivery Orders: those waiting on the user
     * (oldest submission first), or those the user already decided.
     *
     * @return array{data: list<array<string, mixed>>, links: array<int, mixed>, meta: array<string, mixed>}
     */
    protected function requests(User $user, string $queue, string $view): array
    {
        $amendments = $queue === 'amendment';
        $query = match (true) {
            $amendments && $view === 'waiting' => ContractRequestQueue::amendmentsAwaiting($user)->orderBy('submitted_at')->orderBy('id'),
            $amendments => ContractRequestQueue::amendmentsDecided($user),
            $view === 'waiting' => ContractRequestQueue::deliveryOrdersAwaiting($user)->orderBy('submitted_at')->orderBy('id'),
            default => ContractRequestQueue::deliveryOrdersDecided($user),
        };

        $page = $query
            ->with(['procurement.workDirector', 'creator'])
            ->paginate(15)
            ->withQueryString();

        return [
            'data' => array_values(array_map(fn (ProcurementAmendment|ProcurementDeliveryOrder $request): array => [
                'id' => $request->id,
                'title' => $request instanceof ProcurementAmendment
                    ? 'Amandemen '.$request->roman()
                    : 'DO ke-'.$request->sequence,
                'number' => $request->number,
                'state' => $request->state->value,
                'state_label' => $request->state->label(),
                'proposer' => $request->creator?->name,
                'submitted_at' => $request->submitted_at?->toIso8601String(),
                'updated_at' => $request->updated_at?->toIso8601String(),
                'procurement' => [
                    'id' => $request->procurement->id,
                    'number' => $request->procurement->number,
                    'name' => $request->procurement->name,
                    'work_director' => $request->procurement->workDirector->name,
                ],
                // Decided on the job's own tab, as from its notification.
                'url' => route('procurements.show', [
                    $request->procurement,
                    'tab' => $request instanceof ProcurementAmendment ? 'amandemen' : 'do',
                ]),
            ], $page->items())),
            'links' => $page->linkCollection()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ];
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
