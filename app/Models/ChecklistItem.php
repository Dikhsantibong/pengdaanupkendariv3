<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use App\Enums\ChecklistInput;
use App\Enums\FinanceSection;
use App\Enums\ProcurementStage;
use App\Enums\WorkCategory;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $procurement_id
 * @property ProcurementStage $stage
 * @property FinanceSection|null $section
 * @property WorkCategory|null $work_category
 * @property string $name
 * @property string|null $description
 * @property bool $is_optional
 * @property ChecklistInput|null $input_kind
 * @property int|null $input_contract_number_format_id The only contract format asked the input; null for every format.
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $procurement_checklists_count
 */
#[Fillable(['procurement_id', 'stage', 'section', 'work_category', 'name', 'description', 'is_optional', 'input_kind', 'input_contract_number_format_id', 'sort_order', 'is_active'])]
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The procurement this item belongs to alone, when it was read from that
     * procurement's own contract.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * Limit the query to the master data items every procurement shares,
     * leaving out the ones read from a single procurement's contract.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('procurement_id');
    }

    /**
     * The per-procurement checklist rows derived from this item.
     *
     * @return HasMany<ProcurementChecklist, $this>
     */
    public function procurementChecklists(): HasMany
    {
        return $this->hasMany(ProcurementChecklist::class);
    }

    /**
     * The documents this step produces, in the order they are worked through.
     *
     * @return BelongsToMany<DocumentType, $this>
     */
    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class)
            ->withPivot('sort_order', 'is_alternative')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * The documents still missing before this step may be ticked.
     *
     * Every required document must be signed. Alternative documents form one
     * choice: a single signed copy among them is enough.
     *
     * @param  callable(int): bool  $isSigned  Whether a document type is signed.
     * @param  Procurement|null  $procurement  The job, so a document of another contract format is not asked for.
     * @return array<int, string> Names of what is missing, ready to show.
     */
    public function missingDocuments(callable $isSigned, ?Procurement $procurement = null): array
    {
        $missing = [];
        $types = $procurement === null ? $this->documentTypes : $this->documentTypesFor($procurement);

        $required = $types->reject(fn (DocumentType $type): bool => $type->isAlternative());
        $alternatives = $types->filter(fn (DocumentType $type): bool => $type->isAlternative());

        foreach ($required as $type) {
            if (! $isSigned($type->id)) {
                $missing[] = $type->name;
            }
        }

        if ($alternatives->isNotEmpty() && $alternatives->doesntContain(fn (DocumentType $type): bool => $isSigned($type->id))) {
            $missing[] = 'salah satu dari '.$alternatives->pluck('name')->implode(' / ');
        }

        return $missing;
    }

    /**
     * The documents this step asks for on one procurement, leaving out
     * those that belong to another contract format.
     *
     * @return Collection<int, DocumentType>
     */
    public function documentTypesFor(?Procurement $procurement): Collection
    {
        return $this->documentTypes
            ->filter(fn (DocumentType $type): bool => $type->appliesTo($procurement))
            ->values();
    }

    /**
     * Whether this step has to be evidenced by signed documents.
     */
    public function requiresDocument(): bool
    {
        return $this->documentTypes->isNotEmpty();
    }

    /**
     * The procurement methods this item is deliberately switched off for.
     *
     * @return BelongsToMany<ProcurementMethod, $this>
     */
    public function excludedProcurementMethods(): BelongsToMany
    {
        return $this->belongsToMany(
            ProcurementMethod::class,
            'checklist_item_method_exclusions',
        )->withTimestamps();
    }

    /**
     * The contract number formats that skip this step.
     *
     * Exclusion rather than inclusion, like the methods: a new format or a
     * new step applies everywhere until it is explicitly switched off.
     *
     * @return BelongsToMany<ContractNumberFormat, $this>
     */
    public function excludedContractNumberFormats(): BelongsToMany
    {
        return $this->belongsToMany(
            ContractNumberFormat::class,
            'checklist_item_format_exclusions',
        )->withTimestamps();
    }

    /**
     * Limit the query to the steps that apply to a contract number format.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForContractNumberFormat(Builder $query, ?int $formatId): Builder
    {
        if ($formatId === null) {
            return $query;
        }

        return $query->whereDoesntHave(
            'excludedContractNumberFormats',
            fn (Builder $formats): Builder => $formats->whereKey($formatId),
        );
    }

    /**
     * Limit the query to the steps that apply to goods or to services.
     *
     * A step without a category applies to both. A procurement whose
     * category is not known yet gets only those: a goods-only or
     * services-only step cannot be told apart for it.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForWorkCategory(Builder $query, WorkCategory|string|null $category): Builder
    {
        if ($category === null) {
            return $query->whereNull('work_category');
        }

        $value = $category instanceof WorkCategory ? $category->value : $category;

        return $query->where(fn (Builder $inner): Builder => $inner
            ->whereNull('work_category')
            ->orWhere('work_category', $value));
    }

    /**
     * Limit the query to a single procurement stage.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForStage(Builder $query, ProcurementStage $stage): Builder
    {
        return $query->where('stage', $stage->value);
    }

    /**
     * Limit the query to the items that apply to a procurement method.
     *
     * An item applies to every method unless it has been excluded from that
     * one, so a procurement without a method keeps the full checklist.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForProcurementMethod(Builder $query, ?int $procurementMethodId): Builder
    {
        if ($procurementMethodId === null) {
            return $query;
        }

        return $query->whereDoesntHave(
            'excludedProcurementMethods',
            fn (Builder $methods): Builder => $methods->whereKey($procurementMethodId),
        );
    }

    /**
     * The input this step asks of a procurement.
     *
     * A step shared by several contract formats can ask its input of one of
     * them alone, such as the Surat Pesanan step asking the contract number
     * of an SPPL only (SPK and PJ give theirs on the Kontrak step).
     */
    public function inputFor(?Procurement $procurement): ?ChecklistInput
    {
        if ($this->input_contract_number_format_id === null || $procurement === null) {
            return $this->input_kind;
        }

        return (int) $procurement->contract_number_format_id === $this->input_contract_number_format_id
            ? $this->input_kind
            : null;
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
            'section' => FinanceSection::class,
            'work_category' => WorkCategory::class,
            'is_optional' => 'boolean',
            'input_kind' => ChecklistInput::class,
            'is_active' => 'boolean',
        ];
    }
}
