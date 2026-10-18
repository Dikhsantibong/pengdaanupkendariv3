<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use App\Enums\ProcurementStage;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ProcurementStage $stage
 * @property bool $upload_only
 * @property int|null $contract_number_format_id Only asked for under this contract format; null for every format.
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
#[Fillable(['code', 'name', 'stage', 'upload_only', 'contract_number_format_id', 'description', 'sort_order', 'is_active'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

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
     * in its Surat Perjanjian).
     */
    public function appliesTo(?Procurement $procurement): bool
    {
        return $this->contract_number_format_id === null
            || ($procurement !== null && (int) $procurement->contract_number_format_id === $this->contract_number_format_id);
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
        if ($this->code === 'spk' && $procurement?->contractNumberFormat?->code === 'PJ') {
            return 'Surat Perjanjian (PJ)';
        }

        return $this->name;
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
            'upload_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
