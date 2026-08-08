<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signing link for one assessor sheet.
 *
 * @property int $id
 * @property int $vendor_assessment_id
 * @property int $assessment_form_id
 * @property string $token
 * @property string|null $recipient_name
 * @property string|null $recipient_phone
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $assessor_name
 * @property string|null $signature_path
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'assessment_form_id',
    'token',
    'recipient_name',
    'recipient_phone',
    'expires_at',
    'opened_at',
    'submitted_at',
    'revoked_at',
    'assessor_name',
    'signature_path',
    'created_by',
])]
class VendorAssessmentInvitation extends Model
{
    /**
     * The assessment this link belongs to.
     *
     * @return BelongsTo<VendorAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(VendorAssessment::class, 'vendor_assessment_id');
    }

    /**
     * The sheet the holder of this link may score.
     *
     * @return BelongsTo<AssessmentForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(AssessmentForm::class, 'assessment_form_id')->withTrashed();
    }

    /**
     * Whether the link still opens the sheet for scoring.
     *
     * Spent once submitted: a signed sheet is a record, not a draft. The
     * administrator issues a fresh link when a correction is needed.
     */
    public function isOpen(): bool
    {
        if ($this->revoked_at !== null || $this->submitted_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Why the link will not open, in words the assessor can act on.
     */
    public function closedReason(): ?string
    {
        if ($this->revoked_at !== null) {
            return 'Tautan ini sudah dibatalkan oleh administrator.';
        }

        if ($this->submitted_at !== null) {
            return 'Penilaian pada tautan ini sudah dikirim dan tidak dapat diubah lagi.';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'Tautan ini sudah kedaluwarsa. Silakan minta tautan baru kepada administrator.';
        }

        return null;
    }

    /**
     * The public address of this link.
     */
    public function url(): string
    {
        return route('assessment-signing.show', $this->token);
    }

    /**
     * A wa.me address that opens WhatsApp with the message ready to send.
     *
     * Nothing is sent from the server: the administrator presses send in their
     * own WhatsApp, which keeps the unit's number and consent in their hands.
     */
    public function whatsappUrl(string $project, string $vendor): string
    {
        $lines = [
            'Yth. '.($this->recipient_name ?? 'Bapak/Ibu').',',
            '',
            'Mohon kesediaannya mengisi Formulir Penilaian Kinerja Penyedia Barang dan Jasa:',
            'Pekerjaan: '.$project,
            'Penyedia: '.$vendor,
            'Lembar: '.$this->form->name,
            '',
            'Silakan buka tautan berikut untuk mengisi nilai dan tanda tangan:',
            $this->url(),
            '',
            'Terima kasih.',
            'PT PLN Nusantara Power UP Kendari',
        ];

        $phone = preg_replace('/\D+/', '', (string) $this->recipient_phone);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode(implode("\n", $lines));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'opened_at' => 'datetime',
            'submitted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
