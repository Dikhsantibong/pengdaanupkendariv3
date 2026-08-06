<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One signed scan filed against a generated document.
 *
 * A document may carry several: pages are often scanned in batches, or
 * photographed one at a time.
 *
 * @property int $id
 * @property int $procurement_document_id
 * @property string $path
 * @property string $file_name
 * @property string|null $mime
 * @property int|null $size
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['path', 'file_name', 'mime', 'size', 'uploaded_by'])]
class ProcurementDocumentUpload extends Model
{
    /**
     * The generated document this scan belongs to.
     *
     * @return BelongsTo<ProcurementDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'procurement_document_id');
    }

    /**
     * The user who uploaded this scan.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
