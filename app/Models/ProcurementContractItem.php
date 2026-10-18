<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a KHS contract's price list: a material or service with its
 * unit price, from which every Delivery Order draws its lines.
 *
 * @property int $id
 * @property int $procurement_id
 * @property int $sort_order
 * @property string|null $group_name
 * @property string $description
 * @property string|null $part_number
 * @property string $unit
 * @property string $unit_price
 */
#[Fillable(['sort_order', 'group_name', 'description', 'part_number', 'unit', 'unit_price'])]
class ProcurementContractItem extends Model
{
    /**
     * The procurement whose contract lists the item.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }
}
