<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\AssignPicRequest;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementService;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PicAssignmentController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Show the PIC appointment board.
     */
    public function index(Request $request): Response
    {
        $this->authorize('assign-pic');

        /** @var User $user */
        $user = $request->user();
        $assignsPlanner = $user->hasPermission(Permission::AssignPlanner);
        $assignsExecutor = $user->hasPermission(Permission::AssignExecutor);

        $procurements = ProcurementFilters::apply(
            Procurement::query()
                ->visibleTo($user)
                // Nobody is appointed to a proposal its division has not
                // approved; an executor only once the planning is approved.
                ->proposalApproved()
                ->when(! $assignsPlanner, fn ($query) => $query
                    ->where('planning_approval_state', PlanningApprovalState::Disetujui->value))
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'targetUnits', 'progressStatus', 'planner', 'executor'])
                ->when(
                    $request->boolean('unassigned'),
                    fn ($query) => $query->where(function ($inner) use ($assignsPlanner, $assignsExecutor): void {
                        if ($assignsPlanner) {
                            $inner->whereNull('planner_id');
                        }

                        if ($assignsExecutor) {
                            $inner->orWhere(fn ($executor) => $executor
                                ->whereNull('executor_id')
                                ->where('planning_approval_state', PlanningApprovalState::Disetujui->value));
                        }
                    }),
                ),
            $request,
        )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('pic-assignments/index', [
            'procurements' => ProcurementResource::collection($procurements),
            'filters' => [
                ...ProcurementFilters::current($request),
                'unassigned' => $request->boolean('unassigned'),
            ],
            'options' => [
                ...MasterDataOptions::forFilters(),
                'planners' => MasterDataOptions::planners(),
                'executors' => MasterDataOptions::executors(),
            ],
            // Which column of the board the user fills in.
            'can' => [
                'assignPlanner' => $assignsPlanner,
                'assignExecutor' => $assignsExecutor,
            ],
        ]);
    }

    /**
     * Appoint the planning and execution PICs of a procurement.
     *
     * Each PIC is checked on its own right: a value sent unchanged needs no
     * right, so a form showing both pickers works for either team leader.
     */
    public function update(AssignPicRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('assignPic', $procurement);

        $plannerId = $request->has('planner_id') ? ($request->integer('planner_id') ?: null) : $procurement->planner_id;
        $executorId = $request->has('executor_id') ? ($request->integer('executor_id') ?: null) : $procurement->executor_id;

        if ($plannerId !== $procurement->planner_id) {
            $this->authorize('assignPlanner', $procurement);
        }

        if ($executorId !== $procurement->executor_id) {
            $this->authorize('assignExecutor', $procurement);
        }

        $this->procurements->assignPic($procurement, $request->user(), $plannerId, $executorId);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penunjukan PIC diperbarui.']);

        return back();
    }
}
