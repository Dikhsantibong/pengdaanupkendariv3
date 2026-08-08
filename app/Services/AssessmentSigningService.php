<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\User;
use App\Models\VendorAssessment;
use App\Models\VendorAssessmentInvitation;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Issues and redeems the signing links handed to assessors over WhatsApp.
 */
class AssessmentSigningService
{
    /**
     * Where drawn signatures are kept on the private disk.
     */
    private const DIRECTORY = 'assessment-signatures';

    /**
     * How long a freshly issued link stays usable.
     */
    private const LIFETIME_DAYS = 14;

    /**
     * Issue a link for one sheet, replacing any link issued before.
     *
     * Re-issuing mints a new token so the previous one stops working, which is
     * what makes "send them a new link" a real revocation and not just a
     * second door into the same sheet.
     */
    public function issue(
        VendorAssessment $assessment,
        AssessmentForm $form,
        User $author,
        ?string $recipientName,
        ?string $recipientPhone,
    ): VendorAssessmentInvitation {
        return DB::transaction(function () use (
            $assessment,
            $form,
            $author,
            $recipientName,
            $recipientPhone,
        ): VendorAssessmentInvitation {
            $existing = $assessment->invitations()
                ->where('assessment_form_id', $form->id)
                ->first();

            $signature = $existing?->signature_path;

            $existing?->delete();

            return $assessment->invitations()->create([
                'assessment_form_id' => $form->id,
                'token' => $this->freshToken(),
                'recipient_name' => $recipientName,
                'recipient_phone' => $this->normalisePhone($recipientPhone),
                'expires_at' => now()->addDays(self::LIFETIME_DAYS),
                // A signature already collected stays with the sheet; only the
                // way in is replaced.
                'signature_path' => $signature,
                'assessor_name' => $existing?->assessor_name,
                'created_by' => $author->id,
            ]);
        });
    }

    /**
     * Withdraw a link without issuing a replacement.
     */
    public function revoke(VendorAssessmentInvitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);
    }

    /**
     * Record the levels and signature submitted through a link.
     *
     * @param  array<int, array{aspect_id: int, level: int|null}>  $scores
     */
    public function submit(
        VendorAssessmentInvitation $invitation,
        string $assessorName,
        array $scores,
        ?string $signatureDataUri,
    ): void {
        DB::transaction(function () use ($invitation, $assessorName, $scores, $signatureDataUri): void {
            $assessment = $invitation->assessment;

            foreach ($scores as $row) {
                $score = $assessment->scores()
                    ->where('assessment_form_id', $invitation->assessment_form_id)
                    ->where('assessment_aspect_id', $row['aspect_id'])
                    ->first();

                $score?->update([
                    'level' => $row['level'],
                    'scored_at' => now(),
                ]);
            }

            $path = $signatureDataUri === null
                ? $invitation->signature_path
                : $this->storeSignature($invitation, $signatureDataUri);

            $invitation->update([
                'assessor_name' => $assessorName,
                'signature_path' => $path,
                'submitted_at' => now(),
            ]);

            // The printed sheet carries whoever actually signed it.
            $invitation->form->update(['assessor_name' => $assessorName]);
        });
    }

    /**
     * Note that the assessor has opened the link.
     */
    public function markOpened(VendorAssessmentInvitation $invitation): void
    {
        if ($invitation->opened_at === null) {
            $invitation->update(['opened_at' => now()]);
        }
    }

    /**
     * The disk the signatures are kept on.
     */
    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * A token long enough that guessing it is not a threat.
     */
    protected function freshToken(): string
    {
        do {
            $token = Str::random(64);
        } while (VendorAssessmentInvitation::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * Put an Indonesian mobile number into the form wa.me expects.
     */
    protected function normalisePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '62')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /**
     * Decode a drawn signature and put it on the private disk.
     *
     * Only a PNG data URI is accepted, and it is re-encoded through the image
     * library rather than trusted, so nothing but pixels can be stored.
     */
    protected function storeSignature(VendorAssessmentInvitation $invitation, string $dataUri): ?string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUri, $matches)) {
            return $invitation->signature_path;
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || $binary === '') {
            return $invitation->signature_path;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return $invitation->signature_path;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image, null, 9);
        $clean = (string) ob_get_clean();
        imagedestroy($image);

        $path = self::DIRECTORY.'/'.$invitation->vendor_assessment_id
            .'/'.$invitation->assessment_form_id.'-'.Str::random(10).'.png';

        $this->disk()->put($path, $clean);

        if ($invitation->signature_path !== null) {
            $this->disk()->delete($invitation->signature_path);
        }

        return $path;
    }
}
