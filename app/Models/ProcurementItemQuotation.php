<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The unit price one calon mitra quoted for one item of the Rincian Item,
 * printed in that mitra's column of the Evaluasi Harga.
 *
 * @property int $id
 * @property int $procurement_contract_item_id
 * @property int $procurement_partner_candidate_id
 * @property string $unit_price
 */
#[Fillable(['procurement_partner_candidate_id', 'unit_price'])]
class ProcurementItemQuotation extends Model
{
    /**
     * The item quoted for.
     *
     * @return BelongsTo<ProcurementContractItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcurementContractItem::class, 'procurement_contract_item_id');
    }

    /**
     * The calon mitra that quoted.
     *
     * @return BelongsTo<ProcurementPartnerCandidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProcurementPartnerCandidate::class, 'procurement_partner_candidate_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
        ];
    }
}
