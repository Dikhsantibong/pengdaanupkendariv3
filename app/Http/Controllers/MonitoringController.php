<?php

namespace App\Http\Controllers;

use App\Enums\StatusCategory;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
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
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor', 'checklists']),
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
}
