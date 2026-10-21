<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\VendorAssessmentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The invoice details a vendor sent in through the fixed request link, from
 * which the administrator opens a vendor assessment.
 *
 * @property int $id
 * @property string $project
 * @property string $vendor_name
 * @property string|null $work_director_name The Direksi Pekerjaan named by the vendor, by name only.
 * @property string $contract_number
 * @property CarbonImmutable $contract_date
 * @property string $term
 * @property string $invoice_amount
 * @property string $bastp_number
 * @property CarbonImmutable $bastp_date
 * @property bool $has_penalty
 * @property string $submitter_name
 * @property string|null $submitter_phone
 * @property int|null $vendor_assessment_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'project',
    'vendor_name',
    'work_director_name',
    'contract_number',
    'contract_date',
    'term',
    'invoice_amount',
    'bastp_number',
    'bastp_date',
    'has_penalty',
    'submitter_name',
    'submitter_phone',
    'vendor_assessment_id',
])]
class VendorAssessmentRequest extends Model
{
    /** @use HasFactory<VendorAssessmentRequestFactory> */
    use HasFactory;

    /**
     * The assessment opened from these details, once there is one.
     *
     * @return BelongsTo<VendorAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(VendorAssessment::class, 'vendor_assessment_id');
    }

    /**
     * The one public address every vendor fills their details in at.
     *
     * It never changes, so it can be printed in the contract.
     */
    public static function formUrl(): string
    {
        return route('assessment-requests.show');
    }

    /**
     * Where the request stands, for the list on the assessment page.
     */
    public function status(): string
    {
        return $this->vendor_assessment_id === null ? 'submitted' : 'processed';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_date' => 'date',
            'bastp_date' => 'date',
            'invoice_amount' => 'decimal:2',
            'has_penalty' => 'boolean',
        ];
    }
}
