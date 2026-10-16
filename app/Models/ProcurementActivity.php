<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $procurement_id
 * @property int|null $user_id
 * @property ActivityType $type
 * @property string $description
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['procurement_id', 'user_id', 'type', 'description', 'meta'])]
class ProcurementActivity extends Model
{
    /**
     * The procurement this activity belongs to.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * The user who triggered this activity.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * The step of the journey this activity belongs to in the approval
     * history, or null for a routine edit.
     *
     * Appointing the PIC Pelaksana belongs to execution, the PIC Perencana
     * to planning. Newer entries say which in their meta; older ones only in
     * their wording.
     */
    public function approvalStage(): ?string
    {
        if ($this->type === ActivityType::PicDitunjuk) {
            $pic = $this->meta['pic'] ?? (str_contains($this->description, 'PIC Pelaksana') ? 'pelaksana' : 'perencana');

            return $pic === 'pelaksana' ? 'Pelaksanaan' : 'Perencanaan';
        }

        return $this->type->approvalStage();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'meta' => 'array',
        ];
    }
}
