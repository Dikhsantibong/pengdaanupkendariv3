<?php

namespace App\Models;

use App\Enums\DeliveryOrderState;
use Carbon\CarbonImmutable;
use Database\Factories\ProcurementDeliveryOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Delivery Order (DO) called off under a KHS contract.
 *
 * Requested by the proposer (Nota Dinas usulan and the lines wanted),
 * approved by their TL and Asman, given its Nota Dinas DO by Rendan and
 * issued by Laksdan.
 *
 * @property int $id
 * @property int $procurement_id
 * @property int $sequence
 * @property DeliveryOrderState $state
 * @property string|null $request_memo_number
 * @property CarbonImmutable|null $request_memo_date
 * @property string|null $request_file_path
 * @property string|null $request_file_name
 * @property string|null $request_file_mime
 * @property int|null $request_file_size
 * @property string|null $do_memo_number
 * @property CarbonImmutable|null $do_memo_date
 * @property int|null $contract_number_format_id
 * @property string|null $number
 * @property CarbonImmutable|null $order_date
 * @property CarbonImmutable|null $contract_date
 * @property string|null $vendor_code
 * @property string|null $work_description
 * @property string|null $delivery_place
 * @property int|null $delivery_days
 * @property int|null $created_by
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $tl_reviewed_at
 * @property int|null $tl_reviewed_by
 * @property CarbonImmutable|null $director_reviewed_at
 * @property int|null $director_reviewed_by
 * @property CarbonImmutable|null $forwarded_at
 * @property int|null $forwarded_by
 * @property CarbonImmutable|null $issued_at
 * @property int|null $issued_by
 * @property string|null $review_note
 * @property int $revision
 * @property int|null $procurement_document_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Procurement $procurement
 */
#[Fillable([
    'request_memo_number',
    'request_memo_date',
    'do_memo_number',
    'do_memo_date',
    'number',
    'order_date',
    'contract_date',
    'vendor_code',
    'work_description',
    'delivery_place',
    'delivery_days',
])]
class ProcurementDeliveryOrder extends Model
{
    /** @use HasFactory<ProcurementDeliveryOrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'state' => 'draft',
        'revision' => 0,
    ];

    /**
     * The procurement whose contract the DO is called off under.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    /**
     * Who requested the DO.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * The lines called off.
     *
     * @return HasMany<ProcurementDeliveryOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProcurementDeliveryOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The DO document drawn from its template.
     *
     * @return BelongsTo<ProcurementDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'procurement_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teamLeaderReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tl_reviewed_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function directorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_reviewed_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function forwarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    /**
     * The total of every line, before VAT.
     */
    public function total(): float
    {
        return round($this->items->sum(fn (ProcurementDeliveryOrderItem $item): float => $item->total()), 2);
    }

    /**
     * What the proposer still owes before the request can be sent.
     *
     * @return array<int, string>
     */
    public function missingRequest(): array
    {
        return array_values(array_filter([
            blank($this->request_memo_number) ? 'Nomor Nota Dinas Usulan' : null,
            $this->request_memo_date === null ? 'Tanggal Nota Dinas Usulan' : null,
            $this->request_file_path === null ? 'Unggahan dokumen Nota Dinas Usulan' : null,
            $this->items->isEmpty() ? 'Minimal satu item dari daftar harga kontrak' : null,
        ]));
    }

    /**
     * What Rendan still owes before handing it to Laksdan.
     *
     * @return array<int, string>
     */
    public function missingMemo(): array
    {
        return array_values(array_filter([
            blank($this->do_memo_number) ? 'Nomor Nota Dinas DO' : null,
            $this->do_memo_date === null ? 'Tanggal Nota Dinas DO' : null,
        ]));
    }

    /**
     * What Laksdan still owes before the DO is issued.
     *
     * @return array<int, string>
     */
    public function missingIssue(): array
    {
        return array_values(array_filter([
            blank($this->number) ? 'Nomor DO' : null,
            $this->order_date === null ? 'Tanggal DO' : null,
            blank($this->work_description) ? 'Uraian Pekerjaan' : null,
            blank($this->delivery_place) ? 'Tempat Penyerahan' : null,
            $this->delivery_days === null ? 'Waktu Penyerahan (hari)' : null,
            $this->items->isEmpty() ? 'Minimal satu item' : null,
        ]));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => DeliveryOrderState::class,
            'sequence' => 'integer',
            'revision' => 'integer',
            'delivery_days' => 'integer',
            'request_file_size' => 'integer',
            'request_memo_date' => 'date',
            'do_memo_date' => 'date',
            'order_date' => 'date',
            'contract_date' => 'date',
            'submitted_at' => 'datetime',
            'tl_reviewed_at' => 'datetime',
            'director_reviewed_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }
}
