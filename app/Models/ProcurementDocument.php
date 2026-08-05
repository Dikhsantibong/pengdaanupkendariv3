<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $procurement_id
 * @property int $document_type_id
 * @property int|null $document_template_id
 * @property string $title
 * @property string $file_name
 * @property int $template_version
 * @property int $revision
 * @property string $rendered_body
 * @property int|null $generated_by
 * @property int|null $edited_by
 * @property CarbonImmutable $generated_at
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DocumentType $documentType
 */
#[Fillable([
    'document_type_id',
    'document_template_id',
    'title',
    'file_name',
    'template_version',
    'revision',
    'rendered_body',
    'generated_by',
    'edited_by',
    'generated_at',
    'edited_at',
])]
class ProcurementDocument extends Model
{
    /**
     * The procurement this document was generated for.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * The type of document that was generated.
     *
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class)->withTrashed();
    }

    /**
     * The template snapshot used at generation time.
     *
     * @return BelongsTo<DocumentTemplate, $this>
     */
    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class)->withTrashed();
    }

    /**
     * The user who generated this document.
     *
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * The user who last corrected this document by hand.
     *
     * @return BelongsTo<User, $this>
     */
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * Whether the document has been corrected since it was generated.
     */
    public function isEdited(): bool
    {
        return $this->revision > 0;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }
}
