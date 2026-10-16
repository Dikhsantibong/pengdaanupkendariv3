<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One article an amendment changes: what it said and what it says now.
 *
 * @property int $id
 * @property int $procurement_amendment_id
 * @property int $sort_order
 * @property string $article
 * @property string $original_text
 * @property string $new_text
 */
#[Fillable(['sort_order', 'article', 'original_text', 'new_text'])]
class ProcurementAmendmentChange extends Model
{
    /**
     * The amendment this change belongs to.
     *
     * @return BelongsTo<ProcurementAmendment, $this>
     */
    public function amendment(): BelongsTo
    {
        return $this->belongsTo(ProcurementAmendment::class, 'procurement_amendment_id');
    }
}
