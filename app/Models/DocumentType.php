<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use App\Enums\ProcurementStage;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ProcurementStage $stage
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read DocumentTemplate|null $activeTemplate
 * @property-read int|null $templates_count
 * @property-read int|null $procurement_documents_count
 */
#[Fillable(['code', 'name', 'stage', 'description', 'sort_order', 'is_active'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

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
            'is_active' => 'boolean',
        ];
    }
}
