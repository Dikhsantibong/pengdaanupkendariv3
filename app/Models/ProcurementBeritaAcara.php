<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Berita Acara number and date recorded for a single document type on a
 * procurement's "Proses SMART SCM & Berita Acara" step.
 *
 * @property int $id
 * @property int $procurement_id
 * @property int $document_type_id
 * @property string|null $ba_number
 * @property CarbonImmutable|null $ba_date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['document_type_id', 'ba_number', 'ba_date'])]
class ProcurementBeritaAcara extends Model
{
    /**
     * The procurement this BA record belongs to.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    /**
     * The document type this BA number and date belong to.
     *
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class)->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ba_date' => 'date',
        ];
    }
}
