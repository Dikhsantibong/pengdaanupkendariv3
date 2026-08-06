<?php

namespace App\Support;

use App\Enums\PlanningApprovalState;
use App\Models\Procurement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies the shared list filters used by the procurement, monitoring and report screens.
 */
class ProcurementFilters
{
    /**
     * Apply the request filters onto a procurement query.
     *
     * @param  Builder<Procurement>  $query
     * @return Builder<Procurement>
     */
    public static function apply(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->string('search')->trim()->value(), function (Builder $builder, string $search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%")
                        ->orWhere('prk_number', 'like', "%{$search}%");
                });
            })
            ->when($request->integer('progress_status_id'), fn (Builder $builder, int $id) => $builder->where('progress_status_id', $id))
            ->when($request->integer('work_director_id'), fn (Builder $builder, int $id) => $builder->where('work_director_id', $id))
            ->when($request->integer('target_unit_id'), fn (Builder $builder, int $id) => $builder->where('target_unit_id', $id))
            ->when($request->integer('procurement_method_id'), fn (Builder $builder, int $id) => $builder->where('procurement_method_id', $id))
            ->when($request->integer('budget_source_id'), fn (Builder $builder, int $id) => $builder->where('budget_source_id', $id))
            ->when($request->integer('planner_id'), fn (Builder $builder, int $id) => $builder->where('planner_id', $id))
            ->when($request->integer('executor_id'), fn (Builder $builder, int $id) => $builder->where('executor_id', $id))
            ->when(
                PlanningApprovalState::tryFrom($request->string('approval_state')->value() ?: ''),
                fn (Builder $builder, PlanningApprovalState $state) => $builder->where('planning_approval_state', $state->value),
            );
    }

    /**
     * Get the filter values currently applied, ready to be sent to the client.
     *
     * @return array<string, string|int|null>
     */
    public static function current(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'progress_status_id' => $request->integer('progress_status_id') ?: null,
            'work_director_id' => $request->integer('work_director_id') ?: null,
            'target_unit_id' => $request->integer('target_unit_id') ?: null,
            'procurement_method_id' => $request->integer('procurement_method_id') ?: null,
            'budget_source_id' => $request->integer('budget_source_id') ?: null,
            'planner_id' => $request->integer('planner_id') ?: null,
            'executor_id' => $request->integer('executor_id') ?: null,
            'approval_state' => PlanningApprovalState::tryFrom(
                $request->string('approval_state')->value() ?: ''
            )?->value,
        ];
    }
}
