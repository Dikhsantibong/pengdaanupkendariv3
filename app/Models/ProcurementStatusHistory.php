<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One progress status a procurement entered, when, and (when someone's
 * action caused it) by whom. The name is kept as it was then, since a
 * status may be renamed on the master data later.
 *
 * @property int $id
 * @property int $procurement_id
 * @property int|null $progress_status_id
 * @property string $status_name
 * @property string|null $system_key
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['procurement_id', 'progress_status_id', 'status_name', 'system_key', 'user_id'])]
class ProcurementStatusHistory extends Model
{
    /**
     * Entries are written once and never updated.
     */
    public const UPDATED_AT = null;

    /**
     * Record that a procurement entered a status, by whoever is signed in.
     */
    public static function record(int $procurementId, int $statusId): void
    {
        $status = ProgressStatus::withTrashed()->find($statusId);

        if ($status === null) {
            return;
        }

        self::query()->create([
            'procurement_id' => $procurementId,
            'progress_status_id' => $status->id,
            'status_name' => $status->name,
            'system_key' => $status->system_key,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * The procurement.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    /**
     * Whose action led into the status, if anyone's.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
