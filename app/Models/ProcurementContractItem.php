<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of a procurement's Rincian Item: a material or service with its
 * volume and unit price, before and after negotiation, under an optional
 * group heading. The item tables of the documents (BA Negosiasi, Surat
 * Pesanan, SPK, HPE, …) are drawn from these, and a KHS contract's Delivery
 * Orders draw their lines from them as its price list.
 *
 * @property int $id
 * @property int $procurement_id
 * @property int $sort_order
 * @property string|null $group_name
 * @property string $description
 * @property string|null $part_number
 * @property string $volume
 * @property string $unit
 * @property string|null $price_before
 * @property string|null $unit_price
 * @property string|null $previous_po_number
 * @property string|null $previous_po_date
 * @property string|null $previous_supplier
 * @property string|null $previous_price
 */
#[Fillable(['sort_order', 'group_name', 'description', 'part_number', 'volume', 'unit', 'price_before', 'unit_price', 'previous_po_number', 'previous_po_date', 'previous_supplier', 'previous_price'])]
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
     * The unit price each calon mitra quoted for the item.
     *
     * @return HasMany<ProcurementItemQuotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(ProcurementItemQuotation::class);
    }

    /**
     * The unit price a calon mitra quoted, if it did.
     */
    public function quotedBy(int $candidateId): ?string
    {
        return $this->quotations->firstWhere('procurement_partner_candidate_id', $candidateId)?->unit_price;
    }

    /**
     * The amount before negotiation: volume times the unit price.
     */
    public function totalBefore(): float
    {
        return round((float) $this->volume * (float) $this->price_before, 2);
    }

    /**
     * The amount after negotiation: volume times the unit price.
     */
    public function totalAfter(): float
    {
        return round((float) $this->volume * (float) $this->unit_price, 2);
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
            'volume' => 'decimal:3',
            'price_before' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'previous_price' => 'decimal:2',
        ];
    }
}
