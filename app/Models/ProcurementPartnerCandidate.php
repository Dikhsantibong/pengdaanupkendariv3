<?php

namespace App\Models;

use App\Services\ProgressStatusResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A partner candidate (calon mitra) the planning PIC listed, with its offer.
 * One of them is picked as the executor in the "Pelaksana" step.
 *
 * @property int $id
 * @property int $procurement_id
 * @property string $name
 * @property string|null $director_name
 * @property string|null $address
 * @property string|null $quotation_number
 * @property CarbonImmutable|null $quotation_date
 * @property string|null $quotation_value
 * @property string|null $quotation_file_path
 * @property string|null $quotation_file_name
 * @property string|null $quotation_file_mime
 * @property int|null $quotation_file_size
 * @property int $sort_order
 */
#[Fillable(['name', 'director_name', 'address', 'quotation_number', 'quotation_date', 'quotation_value', 'sort_order'])]
class ProcurementPartnerCandidate extends Model
{
    /**
     * An offer listed, completed or removed can move the job's status
     * (Menunggu Penawaran).
     */
    protected static function booted(): void
    {
        $sync = function (ProcurementPartnerCandidate $candidate): void {
            $procurement = Procurement::withTrashed()->whereKey($candidate->procurement_id)->first();

            if ($procurement !== null) {
                app(ProgressStatusResolver::class)->sync($procurement);
            }
        };

        static::saved($sync);
        static::deleted($sync);
    }

    /**
     * The procurement the candidate offered for.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    /**
     * What the planning PIC still has to fill in for this candidate before
     * the "Penawaran" step can be ticked.
     *
     * @return array<int, string>
     */
    public function missing(): array
    {
        $blank = fn (mixed $value): bool => $value === null || trim((string) $value) === '';

        return array_keys(array_filter([
            'nama direktur' => $blank($this->director_name),
            'alamat perusahaan' => $blank($this->address),
            'nomor surat penawaran' => $blank($this->quotation_number),
            'tanggal surat penawaran' => $this->quotation_date === null,
            'nilai total penawaran' => $this->quotation_value === null,
            'berkas penawaran harga' => $this->quotation_file_path === null,
        ]));
    }

    /**
     * The partner fields of a procurement this candidate fills when picked.
     *
     * @return array<string, mixed>
     */
    public function partnerFields(): array
    {
        return [
            'partner_name' => $this->name,
            'partner_director_name' => $this->director_name,
            'partner_address' => $this->address,
            'quotation_number' => $this->quotation_number,
            'quotation_date' => $this->quotation_date?->toDateString(),
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'quotation_value' => 'decimal:2',
            'quotation_file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
