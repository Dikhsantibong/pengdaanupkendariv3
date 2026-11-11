<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Data Master step added by hand to one procurement that would otherwise
 * skip it.
 *
 * @property int $id
 * @property int $procurement_id
 * @property int $checklist_item_id
 * @property int|null $added_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChecklistItem $checklistItem
 */
#[Fillable(['procurement_id', 'checklist_item_id', 'added_by'])]
class ProcurementStepAddition extends Model
{
    /**
     * The procurement the step is added to.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * The Data Master step added.
     *
     * @return BelongsTo<ChecklistItem, $this>
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class)->withTrashed();
    }
}
