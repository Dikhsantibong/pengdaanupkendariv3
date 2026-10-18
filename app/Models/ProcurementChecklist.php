<?php

namespace App\Models;

use App\Enums\ProcurementStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $procurement_id
 * @property int $checklist_item_id
 * @property ProcurementStage $stage
 * @property bool $is_completed
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChecklistItem $checklistItem
 * @property-read Collection<int, ProcurementChecklistFile> $files
 */
#[Fillable(['procurement_id', 'checklist_item_id', 'stage', 'is_completed', 'completed_at', 'completed_by', 'notes'])]
class ProcurementChecklist extends Model
{
    /**
     * The procurement this checklist row belongs to.
     *
     * @return BelongsTo<Procurement, $this>
     */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /**
     * The master checklist item this row is derived from.
     *
     * @return BelongsTo<ChecklistItem, $this>
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class)->withTrashed();
    }

    /**
     * The files uploaded on this step, for a step without a document of its
     * own (every Penyerahan and Pembayaran requirement).
     *
     * @return HasMany<ProcurementChecklistFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProcurementChecklistFile::class)->oldest('id');
    }

    /**
     * Whether this step is evidenced by files uploaded on it: a Keuangan
     * step (Penyerahan / Pembayaran) that has no document of its own.
     */
    public function needsOwnUpload(): bool
    {
        return $this->stage === ProcurementStage::Keuangan
            && ! $this->checklistItem->requiresDocument();
    }

    /**
     * The user who ticked this checklist row.
     *
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ProcurementStage::class,
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
