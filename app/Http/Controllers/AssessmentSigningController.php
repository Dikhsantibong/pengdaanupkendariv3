<?php

namespace App\Http\Controllers;

use App\Http\Requests\VendorAssessments\SubmitSignedAssessmentRequest;
use App\Models\VendorAssessmentInvitation;
use App\Services\AssessmentSigningService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page an assessor reaches from the WhatsApp link.
 *
 * Deliberately unauthenticated: the assessors are unit staff without accounts
 * in this system. The token in the address is the only authorisation, so every
 * action here is scoped to the single sheet that token names, and nothing else
 * about the procurement is exposed.
 */
class AssessmentSigningController extends Controller
{
    public function __construct(protected AssessmentSigningService $signing) {}

    /**
     * Show the sheet to score, or explain why the link no longer opens.
     */
    public function show(string $token): Response
    {
        $invitation = $this->resolve($token);

        $invitation->load(['assessment.scores', 'form.aspects']);

        if (! $invitation->isOpen()) {
            return Inertia::render('assessment-signing/closed', [
                // A submitted sheet is a success, not a failure, and the page
                // says so rather than reading like a broken link.
                'state' => match (true) {
                    $invitation->submitted_at !== null => 'submitted',
                    $invitation->revoked_at !== null => 'revoked',
                    default => 'expired',
                },
                'reason' => $invitation->closedReason(),
                'form' => $invitation->form->name,
                'project' => $invitation->assessment->project,
                'vendorName' => $invitation->assessment->vendor_name,
                'assessorName' => $invitation->assessor_name,
                'submittedAt' => $invitation->submitted_at?->toDateTimeString(),
            ]);
        }

        $this->signing->markOpened($invitation);

        $scores = $invitation->assessment->scoresForForm($invitation->assessment_form_id);

        return Inertia::render('assessment-signing/show', [
            'token' => $invitation->token,
            'assessment' => [
                'project' => $invitation->assessment->project,
                'vendor_name' => $invitation->assessment->vendor_name,
                'po_number' => $invitation->assessment->po_number,
                'po_date' => $invitation->assessment->po_date?->toDateString(),
                'bastp_date' => $invitation->assessment->bastp_date?->toDateString(),
                'form_number' => $invitation->assessment->form_number,
                'revision_number' => $invitation->assessment->revision_number,
                'place' => $invitation->assessment->place,
            ],
            'form' => [
                'name' => $invitation->form->name,
                'assessor_title' => $invitation->form->assessor_title,
                'assessor_options' => $invitation->form->assessor_options ?? [],
            ],
            'assessorName' => $invitation->assessor_name
                ?? $invitation->recipient_name
                ?? $invitation->form->assessor_name,
            'expiresAt' => $invitation->expires_at?->toDateTimeString(),
            'aspects' => $invitation->form->aspects
                ->map(function ($aspect) use ($scores): array {
                    $score = $scores->firstWhere('assessment_aspect_id', $aspect->id);

                    return [
                        'aspect_id' => $aspect->id,
                        'name' => $aspect->name,
                        'preamble' => $aspect->preamble,
                        'indicators' => $aspect->indicators,
                        'sort_order' => $aspect->sort_order,
                        'level' => $score?->level,
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    /**
     * Record the levels and the signature drawn on the page.
     */
    public function store(SubmitSignedAssessmentRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->resolve($token);

        abort_unless($invitation->isOpen(), 410, $invitation->closedReason() ?? 'Tautan tidak berlaku.');

        /** @var array<int, array{aspect_id: int, level: int|null}> $scores */
        $scores = $request->validated('scores');

        $this->signing->submit(
            $invitation,
            $request->string('assessor_name')->trim()->value(),
            $scores,
            $request->string('signature')->value() ?: null,
        );

        return to_route('assessment-signing.show', $invitation->token);
    }

    /**
     * Find the link behind a token, or stop with a plain 404.
     */
    protected function resolve(string $token): VendorAssessmentInvitation
    {
        return VendorAssessmentInvitation::query()
            ->where('token', $token)
            ->firstOr(fn () => abort(404));
    }
}
