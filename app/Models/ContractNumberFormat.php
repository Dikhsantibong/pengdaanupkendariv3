<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use Carbon\CarbonImmutable;
use Database\Factories\ContractNumberFormatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One kind of contract number, such as SPK or PJ.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $prefix
 * @property string $unit_segment
 * @property int $sequence_length
 * @property int $starting_sequence
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read int|null $procurements_count
 */
#[Fillable([
    'code',
    'name',
    'prefix',
    'unit_segment',
    'sequence_length',
    'starting_sequence',
    'description',
    'sort_order',
    'is_active',
])]
class ContractNumberFormat extends Model
{
    /** @use HasFactory<ContractNumberFormatFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * The procurements numbered under this format.
     *
     * @return HasMany<Procurement, $this>
     */
    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    /**
     * Build a contract number, as in KDD075.SPK/612/UPKD/2026.
     */
    public function compose(int $sequence, int $year): string
    {
        $padded = str_pad((string) $sequence, $this->sequence_length, '0', STR_PAD_LEFT);

        return $this->prefix.$padded.'.'.$this->code.'/'.$this->unit_segment.'/'.$year;
    }

    /**
     * An example of the shape, shown on the master data screen.
     *
     * Built from the starting sequence so the example reads like the next
     * number the unit will actually issue.
     */
    public function sample(): string
    {
        return $this->compose(max(1, $this->starting_sequence), (int) now()->format('Y'));
    }

    /**
     * Read the running sequence out of a number this format produced.
     *
     * Numbers may be corrected by hand, so anything that no longer matches the
     * shape is reported as no sequence rather than guessed at.
     */
    public function sequenceIn(string $number, int $year): ?int
    {
        $pattern = '/^'.preg_quote($this->prefix, '/').'(\d+)\.'
            .preg_quote($this->code.'/'.$this->unit_segment.'/'.$year, '/').'$/';

        return preg_match($pattern, $number, $matches) === 1 ? (int) $matches[1] : null;
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
            'sequence_length' => 'integer',
            'starting_sequence' => 'integer',
        ];
    }
}
