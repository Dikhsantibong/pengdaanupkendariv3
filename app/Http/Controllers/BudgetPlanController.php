<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\BudgetPlanRequest;
use App\Models\BudgetPlan;
use App\Models\WorkDirector;
use App\Support\BudgetPlanExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The budget plan (Perencanaan Anggaran): the Anggaran role enters each PRK
 * with its planned AI (Nilai RAB) and AKI and its WO and PR numbers; whoever
 * holds the menu right reads it.
 */
class BudgetPlanController extends Controller
{
    /**
     * Show the budget plan, filtered by a search over its text columns.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('cari', ''));
        $workDirectorId = (int) $request->query('bidang', 0);

        $plans = $this->filtered($request)->with(['updater:id,name', 'workDirector:id,name'])->get();

        return Inertia::render('budget-plans/index', [
            'plans' => $plans->map(fn (BudgetPlan $plan): array => [
                'id' => $plan->id,
                'district' => $plan->district,
                'prk_induk' => $plan->prk_induk,
                'work_director_id' => $plan->work_director_id,
                'work_director' => $plan->workDirector?->name,
                'prk_induk_description' => $plan->prk_induk_description,
                'sub_program_description' => $plan->sub_program_description,
                'ai_plan_value' => $plan->ai_plan_value === null ? null : (float) $plan->ai_plan_value,
                'aki_plan_value' => $plan->aki_plan_value === null ? null : (float) $plan->aki_plan_value,
                'wo_number' => $plan->wo_number,
                'pr_number' => $plan->pr_number,
                'po_stock_type' => $plan->po_stock_type,
                'po_number' => $plan->po_number,
                'allocation' => $plan->allocation,
                'po_item' => $plan->po_item,
                'supplier' => $plan->supplier,
                'po_date' => $plan->po_date?->toDateString(),
                'po_value' => $plan->po_value === null ? null : (float) $plan->po_value,
                'delivery_date' => $plan->delivery_date?->toDateString(),
                'contract_progress' => $plan->contract_progress,
                'physical_progress' => $plan->physical_progress,
                'invoice_value' => $plan->invoice_value === null ? null : (float) $plan->invoice_value,
                'invoice_number' => $plan->invoice_number,
                'payment_dates' => $plan->payment_dates,
                'payment_proof_number' => $plan->payment_proof_number,
                'payment_progress' => $plan->payment_progress,
                'updated_by' => $plan->updater?->name,
                'updated_at' => $plan->updated_at?->toIso8601String(),
            ])->all(),
            'totals' => [
                'count' => $plans->count(),
                'ai' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->ai_plan_value),
                'aki' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->aki_plan_value),
                'po' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->po_value),
                'invoice' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->invoice_value),
            ],
            'filters' => ['cari' => $search, 'bidang' => $workDirectorId > 0 ? $workDirectorId : null],
            'workDirectors' => WorkDirector::query()->active()->ordered()->get()
                ->map(fn (WorkDirector $director): array => ['value' => $director->id, 'label' => $director->name])
                ->all(),
            'canManage' => $request->user()->hasPermission(Permission::ManageBudgetPlans),
        ]);
    }

    /**
     * Add a row to the budget plan.
     */
    public function store(BudgetPlanRequest $request): RedirectResponse
    {
        BudgetPlan::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perencanaan anggaran ditambahkan.']);

        return back();
    }

    /**
     * Change a row of the budget plan.
     */
    public function update(BudgetPlanRequest $request, BudgetPlan $budgetPlan): RedirectResponse
    {
        $budgetPlan->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perencanaan anggaran diperbarui.']);

        return back();
    }

    /**
     * Remove a row of the budget plan.
     */
    public function destroy(BudgetPlan $budgetPlan): RedirectResponse
    {
        $budgetPlan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perencanaan anggaran dihapus.']);

        return back();
    }

    /**
     * Download the plan, as filtered on screen, as the Anggaran Excel sheet.
     */
    public function export(Request $request, BudgetPlanExport $export): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'anggaran');

        $export->write($this->filtered($request)->with('workDirector:id,name')->get(), $path);

        return response()
            ->download($path, 'perencanaan-anggaran-'.now()->format('Ymd-His').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * The plan narrowed by bidang and by a search over its text columns.
     *
     * @return Builder<BudgetPlan>
     */
    private function filtered(Request $request): Builder
    {
        $search = trim((string) $request->query('cari', ''));
        $workDirectorId = (int) $request->query('bidang', 0);

        return BudgetPlan::query()
            ->when($workDirectorId > 0, fn (Builder $query) => $query->where('work_director_id', $workDirectorId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    foreach (['prk_induk', 'prk_induk_description', 'sub_program_description', 'wo_number', 'pr_number', 'po_number', 'supplier', 'invoice_number'] as $column) {
                        $query->orWhere($column, 'like', $like);
                    }
                });
            })
            ->orderBy('prk_induk')
            ->orderBy('id');
    }
}
