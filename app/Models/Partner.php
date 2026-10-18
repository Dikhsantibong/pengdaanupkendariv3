<?php

namespace App\Models;

use App\Concerns\MasterDataScopes;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A partner (mitra): a company that offered for or carried out a job. The
 * planners pick from these when listing partner candidates, and every new
 * name they or the vendor assessments enter is remembered here.
 *
 * @property int $id
 * @property string $name
 * @property string|null $vendor_code
 * @property string|null $director_name
 * @property string|null $address
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'vendor_code', 'director_name', 'address', 'sort_order', 'is_active'])]
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory, MasterDataScopes, SoftDeletes;

    /**
     * Remember a partner entered elsewhere: a new name is added, and a known
     * one only gains the details it still lacks, so what was corrected in the
     * master data is never overwritten.
     *
     * @param  array{vendor_code?: ?string, director_name?: ?string, address?: ?string}  $details
     */
    public static function remember(?string $name, array $details = []): void
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        if ($name === '' || $name === '-') {
            return;
        }

        $details = array_filter(
            array_map(fn (?string $value): ?string => $value === null || trim($value) === '' ? null : trim($value), $details),
            fn (?string $value): bool => $value !== null,
        );

        $partner = static::withTrashed()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($partner === null) {
            static::query()->create(['name' => $name, ...$details]);

            return;
        }

        foreach ($details as $field => $value) {
            if (blank($partner->getAttribute($field))) {
                $partner->setAttribute($field, $value);
            }
        }

        $partner->save();
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
            'is_active' => 'boolean',
        ];
    }
}
