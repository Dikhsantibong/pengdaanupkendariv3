<?php

namespace App\Http\Controllers;

use App\Enums\BudgetType;
use App\Enums\Permission;
use App\Http\Requests\BudgetPlanRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetSource;
use App\Models\WorkDirector;
use App\Support\BudgetPlanExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The budget plan (Perencanaan Anggaran): the Anggaran role enters each PRK
 * with its planned AI (Nilai RAB) and AKI or AO (Pagu & Realisasi) and its WO and PR numbers;
 * whoever holds the menu right reads it.
 */
class BudgetPlanController extends Controller
{
    /**
     * Show the budget plan, filtered by budget type, bidang, and search.
     */
    public function index(Request $request): Response
    {
        [$budgetTypes, $active] = $this->resolveSelection($request);
        $type = BudgetType::tryFrom($active['budget_type']) ?? BudgetType::Investasi;
        $search = trim((string) $request->query('cari', ''));
        $workDirectorId = (int) $request->query('bidang', 0);

        $plans = $this->filtered($request, $active)
            ->with(['updater:id,name', 'workDirector:id,name', 'budgetSource:id,code,name,description'])
            ->get();

        $totals = $type === BudgetType::Investasi
            ? [
                'count' => $plans->count(),
                'ai' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->ai_plan_value),
                'aki' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->aki_plan_value),
                'po' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->po_value),
                'invoice' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->invoice_value),
            ]
            : [
                'count' => $plans->count(),
                'pagu' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->pagu_value),
                'realisasi' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) $plan->realization_value),
                'sisa_pagu' => (float) $plans->sum(fn (BudgetPlan $plan): float => (float) ($plan->remaining_pagu_value ?? ((float) $plan->pagu_value - (float) $plan->realization_value))),
            ];

        return Inertia::render('budget-plans/index', [
            'plans' => $plans->map(fn (BudgetPlan $plan): array => [
                'id' => $plan->id,
                'budget_type' => $plan->budget_type?->value ?? BudgetType::Investasi->value,
                'budget_source_id' => $plan->budget_source_id,
                'district' => $plan->district,
                'prk_induk' => $plan->prk_induk,
                'work_director_id' => $plan->work_director_id,
                'work_director' => $plan->workDirector?->name,
                'prk_induk_description' => $plan->prk_induk_description,
                'sub_program_description' => $plan->sub_program_description,
                'pagu_value' => $plan->pagu_value === null ? null : (float) $plan->pagu_value,
                'remaining_pagu_value' => $plan->remaining_pagu_value === null ? null : (float) $plan->remaining_pagu_value,
                'disburse_plan' => $plan->disburse_plan,
                'realization_value' => $plan->realization_value === null ? null : (float) $plan->realization_value,
                'notes' => $plan->notes,
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
            'totals' => $totals,
            'activeType' => $active['value'],
            'budgetTypes' => $budgetTypes,
            'filters' => [
                'cari' => $search,
                'bidang' => $workDirectorId > 0 ? $workDirectorId : null,
                'jenis' => $active['value'],
            ],
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
        $data = $this->syncBudgetClassification($request->validated());

        BudgetPlan::query()->create([
            ...$data,
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
        $data = $this->syncBudgetClassification($request->validated());

        $budgetPlan->update([
            ...$data,
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
        [, $active] = $this->resolveSelection($request);
        $type = BudgetType::tryFrom($active['budget_type']) ?? BudgetType::Investasi;
        $path = tempnam(sys_get_temp_dir(), 'anggaran');

        $export->write(
            $this->filtered($request, $active)->with('workDirector:id,name')->get(),
            $path,
            $type,
            $active['name'],
        );

        return response()
            ->download($path, 'perencanaan-anggaran-'.strtolower($active['code']).'-'.now()->format('Ymd-His').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Build the budget type options from Master Data Sumber Anggaran and resolve
     * which tab is currently active.
     *
     * @return array{
     *     0: list<array{value: string, budget_source_id: int|null, budget_type: string, code: string, name: string, label: string, layout: string}>,
     *     1: array{value: string, budget_source_id: int|null, budget_type: string, code: string, name: string, label: string, layout: string}
     * }
     */
    private function resolveSelection(Request $request): array
    {
        /** @var Collection<int, BudgetSource> $sources */
        $sources = BudgetSource::query()->active()->ordered()->get();

        $options = $sources->isNotEmpty()
            ? $sources->map(function (BudgetSource $source): array {
                $type = BudgetPlan::typeForSource($source);

                return [
                    'value' => (string) $source->id,
                    'budget_source_id' => $source->id,
                    'budget_type' => $type->value,
                    'code' => $source->code,
                    'name' => $source->name,
                    'label' => $source->description
                        ? "{$source->name} — ".rtrim($source->description, '.')
                        : $source->name,
                    'layout' => $type === BudgetType::Investasi ? 'investasi' : 'operasi',
                ];
            })->values()->all()
            : array_map(fn (BudgetType $t): array => [
                'value' => $t->value,
                'budget_source_id' => null,
                'budget_type' => $t->value,
                'code' => $t->value,
                'name' => $t->label(),
                'label' => $t->label(),
                'layout' => $t === BudgetType::Investasi ? 'investasi' : 'operasi',
            ], BudgetType::cases());

        $requested = trim((string) $request->query('jenis', ''));
        $matched = null;

        if ($requested !== '') {
            $lower = strtolower($requested);

            $matched = collect($options)->first(fn (array $opt): bool => $opt['value'] === $requested
                || strtolower($opt['code']) === $lower
                || $opt['budget_type'] === $lower);

            if ($matched === null && BudgetType::tryFrom($lower) !== null) {
                $fallback = BudgetType::from($lower);
                $matched = [
                    'value' => $fallback->value,
                    'budget_source_id' => null,
                    'budget_type' => $fallback->value,
                    'code' => $fallback->value,
                    'name' => $fallback->label(),
                    'label' => $fallback->label(),
                    'layout' => $fallback === BudgetType::Investasi ? 'investasi' : 'operasi',
                ];
            }
        }

        $active = $matched
            ?? collect($options)->first(fn (array $opt): bool => $opt['budget_type'] === BudgetType::Investasi->value)
            ?? $options[0];

        return [$options, $active];
    }

    /**
     * Keep budget_source_id and budget_type in sync when saving a PRK.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncBudgetClassification(array $data): array
    {
        if (! empty($data['budget_source_id'])) {
            $source = BudgetSource::query()->find((int) $data['budget_source_id']);

            if ($source !== null) {
                $data['budget_type'] = BudgetPlan::typeForSource($source)->value;

                return $data;
            }
        }

        if (empty($data['budget_type'])) {
            $data['budget_type'] = BudgetType::Investasi->value;
        }

        $matchingSource = BudgetSource::query()->active()->ordered()->get()
            ->first(fn (BudgetSource $source): bool => BudgetPlan::typeForSource($source)->value === $data['budget_type']);

        if ($matchingSource !== null) {
            $data['budget_source_id'] = $matchingSource->id;
        }

        return $data;
    }

    /**
     * The plan narrowed by budget source/type, bidang, and search over its text columns.
     *
     * @param  array{value: string, budget_source_id: int|null, budget_type: string, code: string, name: string, label: string, layout: string}|null  $active
     * @return Builder<BudgetPlan>
     */
    private function filtered(Request $request, ?array $active = null): Builder
    {
        if ($active === null) {
            [, $active] = $this->resolveSelection($request);
        }

        $type = BudgetType::tryFrom($active['budget_type']) ?? BudgetType::Investasi;
        $sourceId = $active['budget_source_id'];
        $search = trim((string) $request->query('cari', ''));
        $workDirectorId = (int) $request->query('bidang', 0);

        return BudgetPlan::query()
            ->where(function (Builder $query) use ($sourceId, $type): void {
                if ($sourceId !== null) {
                    $query->where('budget_source_id', $sourceId)
                        ->orWhere(function (Builder $legacy) use ($type): void {
                            $legacy->whereNull('budget_source_id');

                            if ($type === BudgetType::Investasi) {
                                $legacy->where(function (Builder $inv): void {
                                    $inv->where('budget_type', BudgetType::Investasi->value)
                                        ->orWhereNull('budget_type');
                                });
                            } else {
                                $legacy->where('budget_type', $type->value);
                            }
                        });

                    return;
                }

                if ($type === BudgetType::Investasi) {
                    $query->where('budget_type', BudgetType::Investasi->value)
                        ->orWhereNull('budget_type');
                } else {
                    $query->where('budget_type', $type->value);
                }
            })
            ->when($workDirectorId > 0, fn (Builder $query) => $query->where('work_director_id', $workDirectorId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    foreach (['prk_induk', 'prk_induk_description', 'sub_program_description', 'wo_number', 'pr_number', 'po_number', 'supplier', 'invoice_number', 'disburse_plan', 'notes'] as $column) {
                        $query->orWhere($column, 'like', $like);
                    }
                });
            })
            ->orderBy('prk_induk')
            ->orderBy('id');
    }
}
