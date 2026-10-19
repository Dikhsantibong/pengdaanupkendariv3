<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use Database\Factories\SelectionMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A metode pengadaan: how the partner is chosen (Penunjukan Langsung,
 * Pengadaan Langsung, Lelang Terbuka ...). Picked by the planning PIC and
 * printed on the documents; the templates and steps still follow the format.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $procurements_count
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'is_active'])]
class SelectionMethod extends Model
{
    /** @use HasFactory<SelectionMethodFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The procurements carried out with this method.
     *
     * @return HasMany<Procurement, $this>
     */
    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
