<?php

namespace App\Models;

use App\Enums\AmendmentFileKind;
use App\Enums\AmendmentState;
use App\Support\IndonesianNumber;
use Carbon\CarbonImmutable;
use Database\Factories\ProcurementAmendmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One amendment (Amandemen I, II, ...) of a procurement's contract.
 *
 * @property int $id
 * @property int $procurement_id
 * @property int $sequence
 * @property int|null $contract_number_format_id
 * @property string|null $number
 * @property AmendmentState $state
 * @property CarbonImmutable|null $amendment_date
 * @property CarbonImmutable|null $contract_date
 * @property CarbonImmutable|null $meeting_date
 * @property string|null $memo_number
 * @property CarbonImmutable|null $memo_date
 * @property string|null $request_number
 * @property CarbonImmutable|null $request_date
 * @property int|null $created_by
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $tl_reviewed_at
 * @property int|null $tl_reviewed_by
 * @property CarbonImmutable|null $director_reviewed_at
 * @property int|null $director_reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $review_note
 * @property int $revision
 * @property int|null $procurement_document_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Procurement $procurement
 */
#[Fillable([
    'number',
    'amendment_date',
    'contract_date',
    'meeting_date',
    'memo_number',
    'memo_date',
    'request_number',
    'request_date',
])]
class ProcurementAmendment extends Model
{
    /** @use HasFactory<ProcurementAmendmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'state' => 'draft',
        'revision' => 0,
    ];

    /**
     * The procurement whose contract is amended.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    /**
     * Who drew the amendment up.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * The articles that change.
     *
     * @return HasMany<ProcurementAmendmentChange, $this>
     */
    public function changedArticles(): HasMany
    {
        return $this->hasMany(ProcurementAmendmentChange::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The papers filed with the request.
     *
     * @return HasMany<ProcurementAmendmentFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProcurementAmendmentFile::class)->oldest('id');
    }

    /**
     * The amendment document drawn from its template.
     *
     * @return BelongsTo<ProcurementDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'procurement_document_id');
    }

    /**
     * The team leader, Asman and TL Pengadaan who decided.
     *
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /**
     * The roman numeral of the amendment: I, II, III ...
     */
    public function roman(): string
    {
        return IndonesianNumber::roman($this->sequence);
    }

    /**
     * The papers still missing before it can be sent.
     *
     * @return array<int, string>
     */
    public function missing(): array
    {
        $filed = $this->files
            ->map(fn (ProcurementAmendmentFile $file): string => $file->kind->value)
            ->unique()
            ->all();
        $missing = [];

        foreach (AmendmentFileKind::cases() as $kind) {
            if (! in_array($kind->value, $filed, true)) {
                $missing[] = $kind->label();
            }
        }

        $fields = [
            'Nomor Nota Dinas Amandemen' => $this->memo_number,
            'Tanggal Nota Dinas Amandemen' => $this->memo_date,
            'Nomor Surat Permohonan' => $this->request_number,
            'Tanggal Surat Permohonan' => $this->request_date,
        ];

        foreach ($fields as $label => $value) {
            if ($value === null || $value === '') {
                $missing[] = $label;
            }
        }

        if ($this->changedArticles->isEmpty()) {
            $missing[] = 'Minimal satu perubahan pasal';
        }

        return $missing;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => AmendmentState::class,
            'sequence' => 'integer',
            'revision' => 'integer',
            'amendment_date' => 'date',
            'contract_date' => 'date',
            'meeting_date' => 'date',
            'memo_date' => 'date',
            'request_date' => 'date',
            'submitted_at' => 'datetime',
            'tl_reviewed_at' => 'datetime',
            'director_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
