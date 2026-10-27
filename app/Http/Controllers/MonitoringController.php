<?php

namespace App\Http\Controllers;

use App\Concerns\GroupsByPartner;
use App\Enums\AmendmentState;
use App\Enums\DeliveryOrderState;
use App\Enums\FinanceSection;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\StatusCategory;
use App\Http\Resources\ProcurementResource;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementDeliveryOrder;
use App\Models\ProgressStatus;
use App\Services\ProgressStatusResolver;
use App\Support\FilterPeriod;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
use App\Support\RoleDashboard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The monitoring boards. Each opens on one folder per partner (spelling and
 * spacing aside), the partner busiest most recently first; a folder lists
 * the work done with that partner.
 */
class MonitoringController extends Controller
{
    use GroupsByPartner;

    /**
     * Show the progress monitoring board.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $partner = $this->partner($request);
        $stageKey = $request->string('stage')->trim()->value();
        $activeStage = array_key_exists($stageKey, RoleDashboard::STAGES) ? $stageKey : null;

        $filtered = ProcurementFilters::apply(Procurement::query()->visibleTo($request->user()), $request);

        $baseScope = Procurement::query()
            ->visibleTo($request->user())
            ->when($partner !== null, fn (Builder $query) => $this->ofPartner($query, $partner));

        $stageCounts = RoleDashboard::stages(clone $baseScope);

        if ($activeStage !== null) {
            $filtered = $this->ofStage($filtered, $activeStage);
        }

        $counts = (clone $baseScope)
            ->selectRaw('progress_status_id, count(*) as total')
            ->groupBy('progress_status_id')
            ->pluck('total', 'progress_status_id');

        $resolver = app(ProgressStatusResolver::class);
        $trackerSource = $partner === null
            ? clone $filtered
            : $this->ofPartner(clone $filtered, $partner);

        return Inertia::render('monitoring/index', [
            'folders' => $partner !== null ? null : $this->folders(
                (clone $filtered)->reorder()
                    ->select(['id as procurement_id', 'partner_name', 'updated_at'])
                    ->selectRaw('CASE WHEN progress_status_id IN (SELECT id FROM progress_statuses WHERE category = ?) THEN 1 ELSE 0 END as done', [StatusCategory::Selesai->value]),
            ),
            'procurements' => $partner === null ? null : ProcurementResource::collection(
                $this->ofPartner(clone $filtered, $partner)
                    ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'targetUnits', 'progressStatus', 'planner', 'executor', 'checklists'])
                    ->orderBy('progress_status_id')
                    ->latest('created_at')
                    ->paginate(25)
                    ->withQueryString(),
            ),
            'tracker' => $trackerSource
                ->with([
                    'workDirector',
                    'targetUnit',
                    'targetUnits',
                    'contractNumberFormat',
                    'procurementMethod',
                    'progressStatus',
                    'planner:id,name',
                    'executor:id,name',
                    'creator:id,name',
                    'checklists.checklistItem',
                    'statusHistories',
                ])
                ->latest('updated_at')
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Procurement $procurement): array => $this->trackingItem($procurement, $resolver)),
            'stages' => $stageCounts,
            'activeStage' => $activeStage,
            'partner' => $partner === null ? null : $this->partnerPayload($partner),
            'filters' => ProcurementFilters::current($request),
            'options' => MasterDataOptions::forFilters(),
            'statusBoard' => ProgressStatus::query()->active()->ordered()->get()
                ->map(fn (ProgressStatus $status): array => [
                    'id' => $status->id,
                    'name' => $status->name,
                    'category' => $status->category->value,
                    'stage' => $this->statusStage($status->system_key),
                    'total' => (int) ($counts[$status->id] ?? 0),
                ])->all(),
            'categories' => StatusCategory::options(),
        ]);
    }

    /**
     * The partners under contract now, each with the contracts running with
     * them: a contract number given, and the work neither finished nor
     * cancelled.
     */
    public function contracts(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $partner = $this->partner($request);
        $search = $request->string('search')->trim()->value();
        $format = $request->integer('contract_number_format_id');
        $division = $request->integer('work_director_id');

        $running = FilterPeriod::apply(Procurement::query(), $request)
            ->visibleTo($request->user())
            ->whereNotNull('partner_name')
            ->whereRaw("TRIM(partner_name) <> ''")
            // An internal registration number is not a contract yet.
            ->where('number', 'not like', 'PGD/%')
            ->where('number', 'not like', 'USL/%')
            ->whereNotIn('progress_status_id', ProgressStatus::query()
                ->whereIn('category', [StatusCategory::Selesai->value, StatusCategory::Batal->value])
                ->select('id'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('number', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('partner_name', 'like', "%{$search}%")))
            ->when($format > 0, fn (Builder $query) => $query->where('contract_number_format_id', $format))
            ->when($division > 0, fn (Builder $query) => $query->where('work_director_id', $division));

        $contracts = null;

        if ($partner !== null) {
            $contracts = $this->ofPartner($running, $partner)
                ->with(['workDirector', 'contractNumberFormat', 'progressStatus'])
                ->latest('updated_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Procurement $procurement): array => [
                    'id' => $procurement->id,
                    'number' => $procurement->number,
                    'name' => $procurement->name,
                    'format' => $procurement->contractNumberFormat?->code,
                    'division' => $procurement->workDirector->name,
                    'value' => $procurement->value_after_negotiation === null ? null : (float) $procurement->value_after_negotiation,
                    'start_date' => $procurement->execution_start_date?->toDateString(),
                    'end_date' => $procurement->executionEndDate()?->toDateString(),
                    'status' => $procurement->progressStatus->name,
                    'url' => route('procurements.show', $procurement),
                ]);
        }

        return Inertia::render('monitoring/contracts', [
            'folders' => $partner !== null ? null : $this->folders(
                (clone $running)->reorder()
                    ->select(['id as procurement_id', 'partner_name', 'updated_at'])
                    ->selectRaw('0 as done'),
            ),
            'contracts' => $contracts,
            'partner' => $partner === null ? null : $this->partnerPayload($partner),
            'filters' => [
                'search' => $search ?: null,
                'contract_number_format_id' => $format ?: null,
                'work_director_id' => $division ?: null,
                'mitra' => $partner,
                ...FilterPeriod::current($request),
            ],
            'options' => [
                ...MasterDataOptions::forFilters(),
                'formats' => ContractNumberFormat::query()->forContracts()->ordered()->get()
                    ->map(fn (ContractNumberFormat $item): array => ['value' => $item->id, 'label' => $item->code])
                    ->all(),
            ],
        ]);
    }

    /**
     * Every contract amendment the user may see, newest first: where each
     * one stands, filtered and paginated on the server.
     */
    public function amendments(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $state = AmendmentState::tryFrom($request->string('state')->value());
        $partner = $this->partner($request);

        $scoped = $this->scoped(ProcurementAmendment::query(), $request)
            ->when($state !== null, fn (Builder $query) => $query->where('state', $state?->value));

        return Inertia::render('monitoring/amendments', [
            'folders' => $partner !== null ? null : $this->folders($this->folderRows(clone $scoped, 'procurement_amendments', AmendmentState::Disetujui->value)),
            'amendments' => $partner === null ? null : $this->byWork($scoped)
                ->with(['procurement.workDirector', 'creator', 'document'])
                ->paginate(20)
                ->withQueryString()
                ->through(fn (ProcurementAmendment $amendment): array => [
                    'id' => $amendment->id,
                    'title' => "Amandemen {$amendment->roman()}",
                    'number' => $amendment->number,
                    'state' => $amendment->state->value,
                    'state_label' => $amendment->state->label(),
                    'procurement' => $this->procurementSummary($amendment->procurement),
                    'requested_by' => $amendment->creator?->name,
                    'memo_number' => $amendment->memo_number,
                    'submitted_at' => $amendment->submitted_at?->toDateTimeString(),
                    'issued_at' => $amendment->issued_at?->toDateTimeString(),
                    'document_url' => $amendment->document === null ? null
                        : route('procurements.documents.show', [$amendment->procurement_id, $amendment->document, 'preview' => 1]),
                    'url' => route('procurements.show', [$amendment->procurement_id, 'tab' => 'amandemen']),
                ]),
            'partner' => $partner === null ? null : $this->partnerPayload($partner),
            'summary' => $this->summary(ProcurementAmendment::query(), $request, collect(AmendmentState::cases())
                ->map(fn (AmendmentState $state): array => ['value' => $state->value, 'label' => $state->label()])->all()),
            'filters' => $this->filters($request, $state?->value),
            'options' => MasterDataOptions::forFilters(),
        ]);
    }

    /**
     * Every Delivery Order of the KHS contracts the user may see, newest
     * first, with its value: filtered and paginated on the server.
     */
    public function deliveryOrders(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $state = DeliveryOrderState::tryFrom($request->string('state')->value());
        $partner = $this->partner($request);

        $scoped = $this->scoped(ProcurementDeliveryOrder::query(), $request)
            ->when($state !== null, fn (Builder $query) => $query->where('state', $state?->value));

        return Inertia::render('monitoring/delivery-orders', [
            'folders' => $partner !== null ? null : $this->folders($this->folderRows(clone $scoped, 'procurement_delivery_orders', DeliveryOrderState::Terbit->value)),
            'orders' => $partner === null ? null : $this->byWork($scoped)
                ->with(['procurement.workDirector', 'creator', 'document', 'items'])
                ->paginate(20)
                ->withQueryString()
                ->through(fn (ProcurementDeliveryOrder $order): array => [
                    'id' => $order->id,
                    'title' => "DO ke-{$order->sequence}",
                    'number' => $order->number,
                    'state' => $order->state->value,
                    'state_label' => $order->state->label(),
                    'procurement' => $this->procurementSummary($order->procurement),
                    'requested_by' => $order->creator?->name,
                    'order_date' => $order->order_date?->toDateString(),
                    'items_count' => $order->items->count(),
                    'total' => $order->total(),
                    'submitted_at' => $order->submitted_at?->toDateTimeString(),
                    'issued_at' => $order->issued_at?->toDateTimeString(),
                    'document_url' => $order->document === null ? null
                        : route('procurements.documents.show', [$order->procurement_id, $order->document, 'preview' => 1]),
                    'url' => route('procurements.show', [$order->procurement_id, 'tab' => 'do']),
                ]),
            'partner' => $partner === null ? null : $this->partnerPayload($partner),
            'summary' => $this->summary(ProcurementDeliveryOrder::query(), $request, collect(DeliveryOrderState::cases())
                ->map(fn (DeliveryOrderState $state): array => ['value' => $state->value, 'label' => $state->label()])->all()),
            'filters' => $this->filters($request, $state?->value),
            'options' => MasterDataOptions::forFilters(),
        ]);
    }

    /**
     * Amendments or DOs listed per work: the work most recently busy first,
     * each work's own in their order.
     *
     * @template TModel of ProcurementAmendment|ProcurementDeliveryOrder
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function byWork(Builder $query): Builder
    {
        return $query->orderByDesc('procurement_id')->orderBy('sequence');
    }

    /**
     * The rows an amendment or DO folder is counted from: its work's partner,
     * the work, when it last moved, and whether it is done.
     *
     * @template TModel of ProcurementAmendment|ProcurementDeliveryOrder
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function folderRows(Builder $query, string $table, string $doneState): Builder
    {
        return $query->reorder()
            ->select(['procurement_id', 'updated_at'])
            ->addSelect(['partner_name' => Procurement::withTrashed()
                ->select('partner_name')
                ->whereColumn('procurements.id', "{$table}.procurement_id")
                ->limit(1)])
            ->selectRaw('CASE WHEN state = ? THEN 1 ELSE 0 END as done', [$doneState]);
    }

    /**
     * Limit amendments or DOs to the procurements the user may see, and to
     * the search (its own number, or the procurement's number or name), the
     * division, the partner folder and the period asked for.
     *
     * @template TModel of ProcurementAmendment|ProcurementDeliveryOrder
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function scoped(Builder $query, Request $request): Builder
    {
        $search = $request->string('search')->trim()->value();
        $division = $request->integer('work_director_id');
        $partner = $this->partner($request);

        return FilterPeriod::apply($query, $request)
            ->whereIn('procurement_id', Procurement::query()->visibleTo($request->user())->select('id'))
            ->when($search !== '', fn (Builder $inner) => $inner->where(fn (Builder $match) => $match
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('procurement', fn (Builder $procurement) => $procurement
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('partner_name', 'like', "%{$search}%"))))
            ->when($division > 0, fn (Builder $inner) => $inner->whereHas('procurement', fn (Builder $procurement) => $procurement->where('work_director_id', $division)))
            ->when($partner !== null, fn (Builder $inner) => $inner->whereHas('procurement', fn (Builder $procurement) => $this->ofPartner($procurement, (string) $partner)));
    }

    /**
     * How many there are in each state, under the search, division, partner
     * and period filters (the state filter itself is left out, so every card
     * counts).
     *
     * @template TModel of ProcurementAmendment|ProcurementDeliveryOrder
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, array{value: string, label: string}>  $states
     * @return array<int, array{value: string, label: string, total: int}>
     */
    protected function summary(Builder $query, Request $request, array $states): array
    {
        $counts = $this->scoped($query, $request)
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        return array_map(fn (array $state): array => [
            ...$state,
            'total' => (int) ($counts[$state['value']] ?? 0),
        ], $states);
    }

    /**
     * @return array{search: string|null, state: string|null, work_director_id: int|null, mitra: string|null, year: int|null, month: int|null}
     */
    protected function filters(Request $request, ?string $state): array
    {
        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'state' => $state,
            'work_director_id' => $request->integer('work_director_id') ?: null,
            'mitra' => $this->partner($request),
            ...FilterPeriod::current($request),
        ];
    }

    /**
     * The procurement a row belongs to, as the monitoring tables show it.
     *
     * @return array{id: int, number: string, name: string, partner: string|null, division: string}
     */
    protected function procurementSummary(Procurement $procurement): array
    {
        return [
            'id' => $procurement->id,
            'number' => $procurement->number,
            'name' => $procurement->name,
            'partner' => $procurement->partner_name,
            'division' => $procurement->workDirector->name,
        ];
    }

    /**
     * Limit the query to procurements standing at a given lifecycle stage,
     * matching the rules of Procurement::currentStage() and RoleDashboard::stages().
     *
     * @param  Builder<Procurement>  $query
     * @return Builder<Procurement>
     */
    protected function ofStage(Builder $query, string $stage): Builder
    {
        return match ($stage) {
            'selesai' => $query->whereNotNull('completed_at'),
            'keuangan' => $query->whereNull('completed_at')
                ->whereNotNull('finance_submitted_at'),
            'pelaksanaan' => $query->whereNull('completed_at')
                ->whereNull('finance_submitted_at')
                ->where('planning_approval_state', PlanningApprovalState::Disetujui->value),
            'perencanaan' => $query->whereNull('completed_at')
                ->whereNull('finance_submitted_at')
                ->where('planning_approval_state', '!=', PlanningApprovalState::Disetujui->value)
                ->where('proposal_state', ProposalState::Disetujui->value),
            'usulan' => $query->whereNull('completed_at')
                ->whereNull('finance_submitted_at')
                ->where('planning_approval_state', '!=', PlanningApprovalState::Disetujui->value)
                ->where('proposal_state', '!=', ProposalState::Disetujui->value),
            default => $query,
        };
    }

    /**
     * Map a system status key to the macro lifecycle stage it belongs to.
     */
    protected function statusStage(?string $systemKey): string
    {
        return match ($systemKey) {
            'penyusunan_tor', 'draft_nodis_usulan', 'approval_usulan', 'revisi_usulan' => 'usulan',
            'inisiasi_rendan', 'penyusunan_rks', 'menunggu_penawaran', 'draft_nodis_pengadaan',
            'draft_nodis_laksdan', 'approval_icc', 'revisi_perencanaan' => 'perencanaan',
            'inisiasi_laksdan', 'penyusunan_hps', 'proses_smart_scm', 'penyusunan_kontrak',
            'penyusunan_surat_pesanan', 'approval_tl_pengadaan', 'revisi_pelaksanaan' => 'pelaksanaan',
            'terkontrak' => 'keuangan',
            'selesai', 'batal' => 'selesai',
            default => 'pelaksanaan',
        };
    }

    /**
     * Transform a procurement into a detailed visual tracking payload with
     * its lifecycle stages, checklist steps, and status journey.
     *
     * @return array<string, mixed>
     */
    protected function trackingItem(Procurement $procurement, ProgressStatusResolver $resolver): array
    {
        $stageSteps = function (ProcurementStage $stage, ?FinanceSection $section = null) use ($procurement): array {
            $rows = $procurement->checklists
                ->where('stage', $stage)
                ->filter(fn (ProcurementChecklist $row): bool => $row->checklistItem !== null)
                ->when($section !== null, fn ($collection) => $collection->filter(
                    fn (ProcurementChecklist $row): bool => ($row->checklistItem->section ?? FinanceSection::Pembayaran) === $section
                ))
                ->sortBy(fn (ProcurementChecklist $row): int => $row->checklistItem->sort_order)
                ->values();

            $required = $rows->filter(fn (ProcurementChecklist $row): bool => ! $row->checklistItem->is_optional);
            $requiredTotal = $required->count();
            $requiredCompleted = $required->where('is_completed', true)->count();

            $total = $rows->count();
            $completed = $rows->where('is_completed', true)->count();

            return [
                'completed' => $completed,
                'total' => $total,
                'required_completed' => $requiredCompleted,
                'required_total' => $requiredTotal,
                'percentage' => $requiredTotal > 0
                    ? (int) round(($requiredCompleted / $requiredTotal) * 100)
                    : ($total > 0 ? (int) round(($completed / $total) * 100) : 0),
                'steps' => $rows->map(fn (ProcurementChecklist $row): array => [
                    'id' => $row->id,
                    'name' => $row->checklistItem->name,
                    'is_completed' => $row->is_completed,
                    'is_optional' => $row->checklistItem->is_optional,
                    'completed_at' => $row->completed_at?->toDateString(),
                ])->all(),
            ];
        };

        $currentStage = $procurement->currentStage();
        $planningApproved = $procurement->isPlanningApproved();
        $handedOver = $procurement->finance_submitted_at !== null;
        $paid = $procurement->payment_completed_at !== null || $procurement->completed_at !== null;

        $planningData = $stageSteps(ProcurementStage::Perencanaan);
        $executionData = $stageSteps(ProcurementStage::Pelaksanaan);
        $handoverData = $stageSteps(ProcurementStage::Keuangan, FinanceSection::Penyerahan);
        $paymentData = $stageSteps(ProcurementStage::Keuangan, FinanceSection::Pembayaran);

        // Done once TL Pengadaan approved it: that is what opens Pembayaran.
        $handoverDone = $handedOver && ($paid || $procurement->isHandoverApproved());

        $journey = $resolver->journey($procurement);
        $journeyStepsCount = count($journey['steps']);
        $overallPercentage = match (true) {
            $procurement->isCompleted() => 100,
            $journey['position'] !== null && $journeyStepsCount > 0 => (int) min(100, round(($journey['position'] / $journeyStepsCount) * 100)),
            default => 0,
        };

        return [
            'id' => $procurement->id,
            'number' => $procurement->number,
            'has_contract_number' => $procurement->hasContractNumber(),
            'name' => $procurement->name,
            'format' => $procurement->contractNumberFormat?->code,
            'work_director' => $procurement->workDirector->name,
            'target_unit' => $procurement->targetUnitNames(),
            'partner_name' => $procurement->partner_name,
            'hpe_value' => (float) $procurement->hpe_value,
            'value_after_negotiation' => $procurement->value_after_negotiation === null
                ? null
                : (float) $procurement->value_after_negotiation,
            'target_completion_date' => $procurement->target_completion_date?->toDateString(),
            'created_at' => $procurement->created_at?->toDateString(),
            'updated_at' => $procurement->updated_at?->toDateTimeString(),
            'url' => route('procurements.show', $procurement),
            'status' => [
                'id' => $procurement->progressStatus->id,
                'name' => $procurement->progressStatus->name,
                'category' => $procurement->progressStatus->category->value,
                'rule' => $procurement->progressStatus->systemStatus()?->rule(),
            ],
            'current_stage' => $currentStage,
            'waiting_note' => RoleDashboard::note($procurement),
            'overall_percentage' => $overallPercentage,
            'pic' => [
                'proposer' => $procurement->creator?->name,
                'planner' => $procurement->planner?->name,
                'executor' => $procurement->executor?->name,
            ],
            'journey' => $journey,
            'stages' => [
                [
                    'key' => 'usulan',
                    'label' => 'Usulan',
                    'state' => $procurement->isProposalApproved()
                        ? 'done'
                        : ($currentStage['key'] === 'usulan' ? 'current' : 'todo'),
                    'summary' => $procurement->proposal_submitted_at === null
                        ? 'Langsung Pengadaan'
                        : $procurement->proposal_state->label(),
                    'pic' => $procurement->creator?->name,
                    'pic_role' => 'Pengusul',
                    'completed' => $procurement->isProposalApproved() ? 1 : 0,
                    'total' => 1,
                    'percentage' => $procurement->isProposalApproved() ? 100 : ($currentStage['key'] === 'usulan' ? 50 : 0),
                    'steps' => [],
                    'url' => route('procurements.show', [$procurement, 'tab' => $procurement->proposal_submitted_at !== null ? 'usulan' : 'perencanaan']),
                ],
                [
                    'key' => 'perencanaan',
                    'label' => 'Perencanaan',
                    'state' => $planningApproved
                        ? 'done'
                        : ($currentStage['key'] === 'perencanaan' ? 'current' : 'locked'),
                    'summary' => match (true) {
                        $planningApproved => 'Disetujui TL ICC',
                        ! $procurement->isProposalApproved() => 'Menunggu Usulan',
                        $procurement->planning_approval_state === PlanningApprovalState::MenungguPersetujuan => 'Menunggu Approval ICC',
                        $procurement->planning_approval_state === PlanningApprovalState::Ditolak => 'Revisi Perencanaan',
                        $planningData['required_total'] > 0 => "{$planningData['required_completed']}/{$planningData['required_total']} langkah wajib",
                        default => $procurement->planner === null ? 'Menunggu PIC Perencana' : 'Dalam Perencanaan',
                    },
                    'pic' => $procurement->planner?->name,
                    'pic_role' => 'PIC Perencana',
                    'completed' => $planningData['required_completed'],
                    'total' => $planningData['required_total'],
                    'percentage' => $planningApproved ? 100 : $planningData['percentage'],
                    'steps' => $planningData['steps'],
                    'url' => route('procurements.show', [$procurement, 'tab' => 'perencanaan']),
                ],
                [
                    'key' => 'pelaksanaan',
                    'label' => 'Pelaksanaan',
                    'state' => ! $planningApproved
                        ? 'locked'
                        : ($handedOver ? 'done' : 'current'),
                    'summary' => match (true) {
                        ! $planningApproved => 'Menunggu Perencanaan',
                        $handedOver => 'Disetujui TL Pengadaan',
                        $procurement->execution_approval_state === PlanningApprovalState::MenungguPersetujuan => 'Menunggu Approval TL',
                        $procurement->execution_approval_state === PlanningApprovalState::Ditolak => 'Revisi Pelaksanaan',
                        $executionData['required_total'] > 0 => "{$executionData['required_completed']}/{$executionData['required_total']} langkah wajib",
                        default => $procurement->executor === null ? 'Menunggu PIC Pelaksana' : 'Dalam Pelaksanaan',
                    },
                    'pic' => $procurement->executor?->name,
                    'pic_role' => 'PIC Pelaksana',
                    'completed' => $executionData['required_completed'],
                    'total' => $executionData['required_total'],
                    'percentage' => $handedOver ? 100 : (! $planningApproved ? 0 : $executionData['percentage']),
                    'steps' => $executionData['steps'],
                    'url' => route('procurements.show', [$procurement, 'tab' => 'pelaksanaan']),
                ],
                [
                    'key' => 'penyerahan',
                    'label' => 'Penyerahan',
                    'state' => ! $handedOver
                        ? 'locked'
                        : ($handoverDone ? 'done' : 'current'),
                    'summary' => match (true) {
                        ! $handedOver => 'Menunggu Terkontrak',
                        $handoverDone => 'Disetujui TL Pengadaan',
                        $procurement->isHandoverAwaitingApproval() => 'Menunggu TL Pengadaan',
                        $handoverData['required_total'] > 0 => "{$handoverData['required_completed']}/{$handoverData['required_total']} berkas",
                        default => 'Proses Penyerahan',
                    },
                    'pic' => $procurement->executor?->name,
                    'pic_role' => 'Penyerahan',
                    'completed' => $handoverData['required_completed'],
                    'total' => $handoverData['required_total'],
                    'percentage' => $handoverDone ? 100 : (! $handedOver ? 0 : $handoverData['percentage']),
                    'steps' => $handoverData['steps'],
                    'url' => route('procurements.show', [$procurement, 'tab' => 'penyerahan']),
                ],
                [
                    'key' => 'pembayaran',
                    'label' => 'Pembayaran',
                    // Locked until TL Pengadaan approves the hand-over.
                    'state' => $paid ? 'done' : ($handoverDone ? 'current' : 'locked'),
                    'summary' => match (true) {
                        $paid => 'Lunas / Selesai',
                        ! $handoverDone => 'Menunggu Penyerahan',
                        $procurement->payment_approval_state->isAwaiting() => $procurement->payment_approval_state->label(),
                        $paymentData['required_total'] > 0 => "{$paymentData['required_completed']}/{$paymentData['required_total']} berkas",
                        default => 'Proses Pembayaran',
                    },
                    'pic' => 'Keuangan',
                    'pic_role' => 'Unit Keuangan',
                    'completed' => $paymentData['required_completed'],
                    'total' => $paymentData['required_total'],
                    'percentage' => $paid ? 100 : (! $handedOver ? 0 : $paymentData['percentage']),
                    'steps' => $paymentData['steps'],
                    'url' => route('procurements.show', [$procurement, 'tab' => 'pembayaran']),
                ],
            ],
        ];
    }
}
