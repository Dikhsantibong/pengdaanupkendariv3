<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use App\Enums\ProcurementStage;
use App\Enums\WorkCategory;
use App\Support\DocumentTypeVariants;
use App\Support\TenderBeritaAcara;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ProcurementStage $stage
 * @property bool $upload_only
 * @property int|null $contract_number_format_id Only asked for under this contract format; null for every format.
 * @property string|null $family The code the documents of this kind share (rks, spk, ...); a variant's differs from its own code.
 * @property int|null $procurement_method_id Only asked for under this procurement method; null for every method.
 * @property WorkCategory|null $work_category Only asked for goods or services; null for both.
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read DocumentTemplate|null $activeTemplate
 * @property-read int|null $templates_count
 * @property-read int|null $procurement_documents_count
 * @property-read Pivot|null $pivot The checklist link, when loaded through a step.
 */
#[Fillable(['code', 'family', 'name', 'stage', 'upload_only', 'contract_number_format_id', 'procurement_method_id', 'work_category', 'description', 'sort_order', 'is_active'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * A new type is a family of its own unless it is made a variant.
     */
    protected static function booted(): void
    {
        static::creating(function (DocumentType $type): void {
            // Migrations older than the family column create types too.
            if (self::hasFamilyColumn()) {
                $type->family ??= $type->code;
            }
        });
    }

    /** Whether the table has the family column yet, asked once. */
    private static ?bool $hasFamily = null;

    private static function hasFamilyColumn(): bool
    {
        return self::$hasFamily ??= Schema::hasColumn('document_types', 'family');
    }

    /**
     * Forget whether the column is there, for a schema that changes (tests
     * migrating afresh).
     */
    public static function forgetSchema(): void
    {
        self::$hasFamily = null;
    }

    /**
     * The code the documents of this kind share, which the application
     * reads to know what a document is (an RKS, an SPK, ...), whichever of
     * its variants it was drawn as.
     */
    public function familyCode(): string
    {
        return $this->family ?? $this->code;
    }

    /**
     * Limit the query to the documents of some families, whichever variant;
     * a type created without its family (seeded quietly) counts by its code.
     *
     * @param  Builder<static>  $query
     * @param  string|list<string>  $families
     * @return Builder<static>
     */
    public function scopeInFamily(Builder $query, string|array $families): Builder
    {
        $families = (array) $families;

        return $query->where(fn (Builder $inner) => $inner
            ->whereIn('family', $families)
            ->orWhere(fn (Builder $unset) => $unset->whereNull('family')->whereIn('code', $families)));
    }

    /**
     * The variant of this document's family the procurement is asked for.
     */
    public function variantFor(?Procurement $procurement): self
    {
        return DocumentTypeVariants::variantFor($this, $procurement);
    }

    /**
     * The procurement method this document belongs to, if only one.
     *
     * @return BelongsTo<ProcurementMethod, $this>
     */
    public function procurementMethod(): BelongsTo
    {
        return $this->belongsTo(ProcurementMethod::class)->withTrashed();
    }

    /**
     * Whether this document is one of a step's alternatives, of which a
     * single signed copy is enough. Only meaningful when loaded through a
     * checklist step.
     */
    public function isAlternative(): bool
    {
        return (bool) $this->pivot?->getAttribute('is_alternative');
    }

    /**
     * The selection methods this document is asked for under; none means
     * every method.
     *
     * @return BelongsToMany<SelectionMethod, $this>
     */
    public function selectionMethods(): BelongsToMany
    {
        return $this->belongsToMany(SelectionMethod::class)->withTimestamps();
    }

    /**
     * The contract format this document belongs to, if only one.
     *
     * @return BelongsTo<ContractNumberFormat, $this>
     */
    public function contractNumberFormat(): BelongsTo
    {
        return $this->belongsTo(ContractNumberFormat::class)->withTrashed();
    }

    /**
     * Whether a step asks for this document on the procurement: always,
     * unless the document belongs to one contract format only (the SPK's
     * Syarat & Ketentuan (Pasal), for instance, which a PJ already carries
     * in its Surat Perjanjian), or to some selection methods only (a
     * tender's Berita Acara). A procurement whose method is not picked yet
     * goes by Pengadaan Langsung.
     */
    public function appliesTo(?Procurement $procurement): bool
    {
        $format = $this->contract_number_format_id === null
            || ($procurement !== null && (int) $procurement->contract_number_format_id === $this->contract_number_format_id);

        // A variant of a family belongs to its procurement method and to
        // goods or services; a job whose category is not known yet may take
        // either, as its template could.
        $method = $this->procurement_method_id === null
            || ($procurement !== null && (int) $procurement->procurement_method_id === $this->procurement_method_id);
        $category = $this->work_category === null
            || $procurement?->work_category === null
            || $procurement->work_category === $this->work_category;

        if (! $method || ! $category) {
            return false;
        }

        if (! $format || $this->selectionMethods->isEmpty()) {
            return $format;
        }

        $method = $procurement === null
            ? TenderBeritaAcara::DEFAULT_METHOD
            : ($procurement->selectionMethod->code ?? TenderBeritaAcara::DEFAULT_METHOD);

        return $this->selectionMethods->contains('code', $method);
    }

    /**
     * The name this document goes by on one procurement.
     *
     * The "Kontrak" step asks for the SPK document type on both the SPK and
     * the PJ format, but under the PJ format the contract drawn is a Surat
     * Perjanjian, so it is called that.
     */
    public function nameFor(?Procurement $procurement): string
    {
        if ($this->familyCode() === 'spk' && $procurement?->contractNumberFormat?->code === 'PJ') {
            return 'Surat Perjanjian (PJ)';
        }

        // A variant is named apart in the master data only; the document
        // keeps the name it always had.
        return DocumentTypeVariants::titleOf($this) ?? $this->name;
    }

    /**
     * Every template ever registered for this document type.
     *
     * @return HasMany<DocumentTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }

    /**
     * The template currently used when generating this document type.
     *
     * @return HasOne<DocumentTemplate, $this>
     */
    public function activeTemplate(): HasOne
    {
        return $this->hasOne(DocumentTemplate::class)
            ->where('is_active', true)
            ->latestOfMany('version');
    }

    /**
     * The checklist steps that produce this document.
     *
     * @return BelongsToMany<ChecklistItem, $this>
     */
    public function checklistItems(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistItem::class)->withTimestamps();
    }

    /**
     * The documents generated for this type.
     *
     * @return HasMany<ProcurementDocument, $this>
     */
    public function procurementDocuments(): HasMany
    {
        return $this->hasMany(ProcurementDocument::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ProcurementStage::class,
            'work_category' => WorkCategory::class,
            'upload_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
