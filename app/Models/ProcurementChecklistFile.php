<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file uploaded on a checklist step that has no document of its own, such
 * as a Penyerahan or Pembayaran requirement.
 *
 * @property int $id
 * @property int $procurement_checklist_id
 * @property string $path
 * @property string $file_name
 * @property string|null $mime
 * @property int|null $size
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['path', 'file_name', 'mime', 'size', 'uploaded_by'])]
class ProcurementChecklistFile extends Model
{
    /**
     * The checklist row the file evidences.
     *
     * @return BelongsTo<ProcurementChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ProcurementChecklist::class, 'procurement_checklist_id');
    }

    /**
     * Who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
