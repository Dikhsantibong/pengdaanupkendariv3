<?php

namespace App\Http\Controllers;

use App\Enums\PlanningApprovalState;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalQueueController extends Controller
{
    /**
     * Show the procurements waiting for a team leader decision.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $procurements = Procurement::query()
            ->visibleTo($request->user())
            ->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
            ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor', 'checklists'])
            ->orderBy('planning_submitted_at')
            ->paginate(15);

        $decided = Procurement::query()
            ->visibleTo($request->user())
            ->whereIn('planning_approval_state', [
                PlanningApprovalState::Disetujui->value,
                PlanningApprovalState::Ditolak->value,
            ])
            ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor'])
            ->latest('planning_reviewed_at')
            ->limit(10)
            ->get();

        return Inertia::render('approvals/index', [
            'procurements' => ProcurementResource::collection($procurements),
            'recentDecisions' => ProcurementResource::collection($decided)->resolve(),
        ]);
    }
}
