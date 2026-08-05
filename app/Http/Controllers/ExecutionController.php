<?php

namespace App\Http\Controllers;

use App\Enums\PlanningApprovalState;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExecutionController extends Controller
{
    /**
     * Show the procurements whose planning has been approved.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $procurements = ProcurementFilters::apply(
            Procurement::query()
                ->visibleTo($request->user())
                ->where('planning_approval_state', PlanningApprovalState::Disetujui->value)
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor', 'checklists']),
            $request,
        )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('execution/index', [
            'procurements' => ProcurementResource::collection($procurements),
            'filters' => ProcurementFilters::current($request),
            'options' => MasterDataOptions::forFilters(),
        ]);
    }
}
