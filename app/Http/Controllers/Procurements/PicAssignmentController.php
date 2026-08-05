<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\AssignPicRequest;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
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
        $this->authorize('create', Procurement::class);

        $procurements = ProcurementFilters::apply(
            Procurement::query()
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor'])
                ->when(
                    $request->boolean('unassigned'),
                    fn ($query) => $query->where(function ($inner): void {
                        $inner->whereNull('planner_id')->orWhereNull('executor_id');
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
                'planners' => MasterDataOptions::users(UserRole::PicPerencana),
                'executors' => MasterDataOptions::users(UserRole::PicPelaksana),
            ],
        ]);
    }

    /**
     * Appoint the planning and execution PICs of a procurement.
     */
    public function update(AssignPicRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('assignPic', $procurement);

        $this->procurements->assignPic(
            $procurement,
            $request->user(),
            $request->integer('planner_id') ?: null,
            $request->integer('executor_id') ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penunjukan PIC diperbarui.']);

        return back();
    }
}
