<?php

namespace App\Models;

use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_type_id
 * @property int|null $procurement_method_id
 * @property string $name
 * @property int $version
 * @property string $body
 * @property array<int, string>|null $placeholders
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read DocumentType $documentType
 * @property-read ProcurementMethod|null $procurementMethod
 * @property-read int|null $procurement_documents_count
 */
#[Fillable(['document_type_id', 'procurement_method_id', 'name', 'version', 'body', 'placeholders', 'is_active'])]
class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The document type this template renders.
     *
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * The procurement method this template is written for, if any.
     *
     * @return BelongsTo<ProcurementMethod, $this>
     */
    public function procurementMethod(): BelongsTo
    {
        return $this->belongsTo(ProcurementMethod::class)->withTrashed();
    }

    /**
     * Find the template to use for a document type and procurement method.
     *
     * A template written for the exact method wins over the general fallback,
     * and the highest version wins within each of those groups.
     */
    public static function resolveFor(int $documentTypeId, ?int $procurementMethodId): ?self
    {
        return self::query()
            ->active()
            ->where('document_type_id', $documentTypeId)
            ->where(function (Builder $query) use ($procurementMethodId): void {
                $query->whereNull('procurement_method_id');

                if ($procurementMethodId !== null) {
                    $query->orWhere('procurement_method_id', $procurementMethodId);
                }
            })
            ->orderByRaw('procurement_method_id is null')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * The document type ids that resolve to a template for a given method.
     *
     * Answers `resolveFor() !== null` for every document type at once, so a
     * screen listing them does not run one query per type.
     *
     * @return array<int, int>
     */
    public static function documentTypeIdsResolvableFor(?int $procurementMethodId): array
    {
        return self::query()
            ->active()
            ->where(function (Builder $query) use ($procurementMethodId): void {
                $query->whereNull('procurement_method_id');

                if ($procurementMethodId !== null) {
                    $query->orWhere('procurement_method_id', $procurementMethodId);
                }
            })
            ->distinct()
            ->pluck('document_type_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * The documents rendered from this template.
     *
     * @return HasMany<ProcurementDocument, $this>
     */
    public function procurementDocuments(): HasMany
    {
        return $this->hasMany(ProcurementDocument::class);
    }

    /**
     * Limit the query to templates that may be used for generation.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
