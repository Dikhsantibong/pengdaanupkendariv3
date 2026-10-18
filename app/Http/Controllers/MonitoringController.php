<?php

namespace App\Http\Controllers;

use App\Enums\AmendmentState;
use App\Enums\DeliveryOrderState;
use App\Enums\StatusCategory;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementDeliveryOrder;
use App\Models\ProgressStatus;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    /**
     * Show the progress monitoring board.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $procurements = ProcurementFilters::apply(
            Procurement::query()
                ->visibleTo($request->user())
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'targetUnits', 'progressStatus', 'planner', 'executor', 'checklists']),
            $request,
        )
            ->orderBy('progress_status_id')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = Procurement::query()
            ->visibleTo($request->user())
            ->selectRaw('progress_status_id, count(*) as total')
            ->groupBy('progress_status_id')
            ->pluck('total', 'progress_status_id');

        return Inertia::render('monitoring/index', [
            'procurements' => ProcurementResource::collection($procurements),
            'filters' => ProcurementFilters::current($request),
            'options' => MasterDataOptions::forFilters(),
            'statusBoard' => ProgressStatus::query()->active()->ordered()->get()
                ->map(fn (ProgressStatus $status): array => [
                    'id' => $status->id,
                    'name' => $status->name,
                    'category' => $status->category->value,
                    'total' => (int) ($counts[$status->id] ?? 0),
                ])->all(),
            'categories' => StatusCategory::options(),
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

        $amendments = $this->scoped(ProcurementAmendment::query(), $request)
            ->when($state !== null, fn (Builder $query) => $query->where('state', $state?->value))
            ->with(['procurement.workDirector', 'creator', 'document'])
            ->latest('updated_at')
            ->latest('id')
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
            ]);

        return Inertia::render('monitoring/amendments', [
            'amendments' => $amendments,
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

        $orders = $this->scoped(ProcurementDeliveryOrder::query(), $request)
            ->when($state !== null, fn (Builder $query) => $query->where('state', $state?->value))
            ->with(['procurement.workDirector', 'creator', 'document', 'items'])
            ->latest('updated_at')
            ->latest('id')
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
            ]);

        return Inertia::render('monitoring/delivery-orders', [
            'orders' => $orders,
            'summary' => $this->summary(ProcurementDeliveryOrder::query(), $request, collect(DeliveryOrderState::cases())
                ->map(fn (DeliveryOrderState $state): array => ['value' => $state->value, 'label' => $state->label()])->all()),
            'filters' => $this->filters($request, $state?->value),
            'options' => MasterDataOptions::forFilters(),
        ]);
    }

    /**
     * Limit amendments or DOs to the procurements the user may see, and to
     * the search (its own number, or the procurement's number or name) and
     * the division asked for.
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

        return $query
            ->whereIn('procurement_id', Procurement::query()->visibleTo($request->user())->select('id'))
            ->when($search !== '', fn (Builder $inner) => $inner->where(fn (Builder $match) => $match
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('procurement', fn (Builder $procurement) => $procurement
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('partner_name', 'like', "%{$search}%"))))
            ->when($division > 0, fn (Builder $inner) => $inner->whereHas('procurement', fn (Builder $procurement) => $procurement->where('work_director_id', $division)));
    }

    /**
     * How many there are in each state, under the search and division
     * filters (the state filter itself is left out, so every card counts).
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
     * @return array{search: string|null, state: string|null, work_director_id: int|null}
     */
    protected function filters(Request $request, ?string $state): array
    {
        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'state' => $state,
            'work_director_id' => $request->integer('work_director_id') ?: null,
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
}
