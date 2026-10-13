<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProcurementAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supporting file sent with a work proposal (nota dinas, TOR draft, photo).
 *
 * @property int $id
 * @property int $procurement_id
 * @property string $path
 * @property string $file_name
 * @property string|null $mime
 * @property int|null $size
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Procurement $procurement
 * @property-read User|null $uploader
 */
#[Fillable(['path', 'file_name', 'mime', 'size', 'uploaded_by'])]
class ProcurementAttachment extends Model
{
    /** @use HasFactory<ProcurementAttachmentFactory> */
    use HasFactory;

    /**
     * The procurement the file belongs to.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
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
     * Whether the browser can show the file inline.
     */
    public function isPreviewable(): bool
    {
        return in_array($this->mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }
}
