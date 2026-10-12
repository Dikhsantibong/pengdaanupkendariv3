<?php

namespace App\Models;

use App\Enums\WorkCategory;
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
 * @property int|null $contract_number_format_id
 * @property WorkCategory|null $work_category
 * @property string|null $library_key
 * @property string|null $library_checksum
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
 * @property-read ContractNumberFormat|null $contractNumberFormat
 * @property-read int|null $procurement_documents_count
 */
#[Fillable(['document_type_id', 'procurement_method_id', 'contract_number_format_id', 'work_category', 'library_key', 'library_checksum', 'name', 'version', 'body', 'placeholders', 'is_active'])]
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
     * The contract number format this template is written for, if any.
     *
     * @return BelongsTo<ContractNumberFormat, $this>
     */
    public function contractNumberFormat(): BelongsTo
    {
        return $this->belongsTo(ContractNumberFormat::class)->withTrashed();
    }

    /**
     * Find the template to use for a document type and procurement method.
     *
     * A template written for the exact method wins over the general fallback,
     * and the highest version wins within each of those groups.
     */
    public static function resolveFor(
        int $documentTypeId,
        ?int $procurementMethodId,
        WorkCategory|string|null $workCategory = null,
        ?int $contractNumberFormatId = null,
    ): ?self {
        return self::query()
            ->active()
            ->where('document_type_id', $documentTypeId)
            ->where(function (Builder $query) use ($procurementMethodId): void {
                $query->whereNull('procurement_method_id');

                if ($procurementMethodId !== null) {
                    $query->orWhere('procurement_method_id', $procurementMethodId);
                }
            })
            ->forWorkCategory($workCategory)
            ->forContractNumberFormat($contractNumberFormatId)
            // The contract format and goods/services category decide the
            // document's shape, so they weigh more than the method.
            ->orderByRaw('contract_number_format_id is null')
            ->orderByRaw('work_category is null')
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
    public static function documentTypeIdsResolvableFor(
        ?int $procurementMethodId,
        WorkCategory|string|null $workCategory = null,
        ?int $contractNumberFormatId = null,
    ): array {
        return self::query()
            ->active()
            ->where(function (Builder $query) use ($procurementMethodId): void {
                $query->whereNull('procurement_method_id');

                if ($procurementMethodId !== null) {
                    $query->orWhere('procurement_method_id', $procurementMethodId);
                }
            })
            ->forWorkCategory($workCategory)
            ->forContractNumberFormat($contractNumberFormatId)
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
     * Limit the query to templates usable for a contract number format.
     *
     * A template without a format serves every format; one limited to a
     * format is never offered to the others.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForContractNumberFormat(Builder $query, ?int $contractNumberFormatId): Builder
    {
        return $query->where(function (Builder $inner) use ($contractNumberFormatId): void {
            $inner->whereNull('contract_number_format_id');

            if ($contractNumberFormatId !== null) {
                $inner->orWhere('contract_number_format_id', $contractNumberFormatId);
            }
        });
    }

    /**
     * Limit the query to templates usable for a goods or services contract.
     *
     * A template without a category serves both. A procurement without a
     * category (one whose format does not ask for it) may use any template,
     * the general ones first.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForWorkCategory(Builder $query, WorkCategory|string|null $workCategory): Builder
    {
        $category = $workCategory instanceof WorkCategory ? $workCategory->value : $workCategory;

        if ($category === null) {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->whereNull('work_category')
            ->orWhere('work_category', $category));
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
            'work_category' => WorkCategory::class,
        ];
    }
}
