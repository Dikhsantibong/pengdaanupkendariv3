<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One time the administrator worked as another account.
 *
 * @property int $id
 * @property int|null $impersonator_id
 * @property int|null $impersonated_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 */
#[Fillable(['impersonator_id', 'impersonated_id', 'ip_address', 'user_agent', 'started_at', 'ended_at'])]
class ImpersonationLog extends Model
{
    /**
     * The administrator who switched.
     *
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id')->withTrashed();
    }

    /**
     * The account worked as.
     *
     * @return BelongsTo<User, $this>
     */
    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_id')->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
