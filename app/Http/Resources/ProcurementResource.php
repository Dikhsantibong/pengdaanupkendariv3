<?php

namespace App\Http\Resources;

use App\Enums\ProcurementStage;
use App\Models\Procurement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Procurement
 */
class ProcurementResource extends JsonResource
{
    /**
     * Transform the procurement into the row shape used by the data tables.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'work_director' => $this->workDirector->name,
            'target_unit' => $this->targetUnit->name,
            'procurement_method' => $this->procurement_method_id === null
                ? null
                : $this->procurementMethod->name,
            'budget_source' => $this->budget_source_id === null
                ? null
                : $this->budgetSource->name,
            'pr_ro_number' => $this->prRoNumber?->number,
            'prk_number' => $this->prk_number,
            'hpe_value' => (float) $this->hpe_value,
            'status' => [
                'id' => $this->progressStatus->id,
                'name' => $this->progressStatus->name,
                'category' => $this->progressStatus->category->value,
            ],
            'planner' => $this->planner?->only(['id', 'name']),
            'executor' => $this->executor?->only(['id', 'name']),
            'planning_approval_state' => $this->planning_approval_state->value,
            'planning_approval_label' => $this->planning_approval_state->label(),
            'planning_progress' => $this->whenLoaded('checklists', fn () => $this->checklistProgress(ProcurementStage::Perencanaan)),
            'execution_progress' => $this->whenLoaded('checklists', fn () => $this->checklistProgress(ProcurementStage::Pelaksanaan)),
            'target_completion_date' => $this->target_completion_date?->toDateString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
