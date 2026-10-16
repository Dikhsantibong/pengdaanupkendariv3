<?php

namespace App\Models;

use App\Enums\AmendmentFileKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One paper filed with an amendment request, kept on the private disk.
 *
 * @property int $id
 * @property int $procurement_amendment_id
 * @property AmendmentFileKind $kind
 * @property string $path
 * @property string $file_name
 * @property string|null $mime
 * @property int|null $size
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['kind', 'path', 'file_name', 'mime', 'size', 'uploaded_by'])]
class ProcurementAmendmentFile extends Model
{
    /**
     * The amendment this paper was filed with.
     *
     * @return BelongsTo<ProcurementAmendment, $this>
     */
    public function amendment(): BelongsTo
    {
        return $this->belongsTo(ProcurementAmendment::class, 'procurement_amendment_id');
    }

    /**
     * Who filed it.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AmendmentFileKind::class,
            'size' => 'integer',
        ];
    }
}
