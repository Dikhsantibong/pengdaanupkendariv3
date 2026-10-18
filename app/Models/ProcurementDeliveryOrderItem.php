<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a Delivery Order: what is called off, how many, at what price.
 *
 * @property int $id
 * @property int $procurement_delivery_order_id
 * @property int|null $procurement_contract_item_id
 * @property int $sort_order
 * @property string|null $group_name
 * @property string $description
 * @property string|null $part_number
 * @property string $quantity
 * @property string $unit
 * @property string $unit_price
 */
#[Fillable(['procurement_contract_item_id', 'sort_order', 'group_name', 'description', 'part_number', 'quantity', 'unit', 'unit_price'])]
class ProcurementDeliveryOrderItem extends Model
{
    /**
     * The Delivery Order the line belongs to.
     *
     * @return BelongsTo<ProcurementDeliveryOrder, $this>
     */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementDeliveryOrder::class, 'procurement_delivery_order_id');
    }

    /**
     * The line total: quantity times unit price.
     */
    public function total(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
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
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }
}
