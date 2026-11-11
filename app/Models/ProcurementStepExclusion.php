<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A step removed from one procurement by hand.
 *
 * The step stays in Data Master and on every other procurement; lifting the
 * exclusion brings it back on this one.
 *
 * @property int $id
 * @property int $procurement_id
 * @property int $checklist_item_id
 * @property int|null $removed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChecklistItem $checklistItem
 * @property-read User|null $remover
 */
#[Fillable(['procurement_id', 'checklist_item_id', 'removed_by'])]
class ProcurementStepExclusion extends Model
{
    /**
     * The procurement the step is removed from.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * The step removed.
     *
     * @return BelongsTo<ChecklistItem, $this>
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class)->withTrashed();
    }

    /**
     * Who removed it.
     *
     * @return BelongsTo<User, $this>
     */
    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by')->withTrashed();
    }
}
