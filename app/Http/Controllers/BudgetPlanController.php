<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\BudgetPlanRequest;
use App\Models\BudgetPlan;
use App\Models\WorkDirector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        $query = BudgetPlan::query()
            ->when($workDirectorId > 0, fn (Builder $query) => $query->where('work_director_id', $workDirectorId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    foreach (['prk_induk', 'prk_induk_description', 'sub_program_description', 'wo_number', 'pr_number'] as $column) {
                        $query->orWhere($column, 'like', $like);
                    }
                });
            });

        $plans = (clone $query)->with(['updater:id,name', 'workDirector:id,name'])->orderBy('prk_induk')->orderBy('id')->get();

        return Inertia::render('budget-plans/index', [
            'plans' => $plans->map(fn (BudgetPlan $plan): array => [
                'id' => $plan->id,
                'prk_induk' => $plan->prk_induk,
                'work_director_id' => $plan->work_director_id,
                'work_director' => $plan->workDirector?->name,
                'prk_induk_description' => $plan->prk_induk_description,
                'sub_program_description' => $plan->sub_program_description,
                'ai_plan_value' => $plan->ai_plan_value === null ? null : (float) $plan->ai_plan_value,
                'aki_plan_value' => $plan->aki_plan_value === null ? null : (float) $plan->aki_plan_value,
                'wo_number' => $plan->wo_number,
                'pr_number' => $plan->pr_number,
                'updated_by' => $plan->updater?->name,
                'updated_at' => $plan->updated_at?->toIso8601String(),
            ])->all(),
            'totals' => [
                'count' => $plans->count(),
                'ai' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->ai_plan_value),
                'aki' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->aki_plan_value),
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
}
