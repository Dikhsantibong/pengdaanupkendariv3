<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\AmendmentFileKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\ReviewPlanningRequest;
use App\Http\Requests\Procurements\UpdateAmendmentRequest;
use App\Http\Requests\Procurements\UploadAmendmentFileRequest;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementAmendmentFile;
use App\Services\AmendmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Amandemen tab: the proposer drafts and sends an amendment, their TL,
 * the division's Asman and TL Pengadaan decide on it.
 */
class AmendmentController extends Controller
{
    public function __construct(protected AmendmentService $amendments) {}

    /**
     * Start the next amendment.
     */
    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('startAmendment', $procurement);

        $amendment = $this->amendments->start($procurement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Amandemen {$amendment->roman()} dibuat. Lengkapi dokumen dan perubahannya, lalu ajukan."]);

        return back();
    }

    /**
     * Save the numbers, dates and changed articles.
     */
    public function update(UpdateAmendmentRequest $request, Procurement $procurement, ProcurementAmendment $amendment): RedirectResponse
    {
        $this->belongs($procurement, $amendment);
        $this->authorize('updateAmendment', [$procurement, $amendment]);

        /** @var array<int, array{article: string, original_text: string, new_text: string}> $changes */
        $changes = $request->validated('changes', []);

        $this->amendments->update($amendment, $request->safe()->except('changes'), $changes);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Amandemen tersimpan.']);

        return back();
    }

    /**
     * File one of the four papers.
     */
    public function upload(UploadAmendmentFileRequest $request, Procurement $procurement, ProcurementAmendment $amendment): RedirectResponse
    {
        $this->belongs($procurement, $amendment);
        $this->authorize('updateAmendment', [$procurement, $amendment]);

        $kind = AmendmentFileKind::from($request->string('kind')->value());

        $this->amendments->attach($amendment, $kind, $request->file('file'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$kind->label()} tersimpan."]);

        return back();
    }

    /**
     * Open a filed paper.
     */
    public function file(Procurement $procurement, ProcurementAmendment $amendment, ProcurementAmendmentFile $file): StreamedResponse
    {
        $this->belongs($procurement, $amendment);
        abort_unless($file->procurement_amendment_id === $amendment->id, 404);
        $this->authorize('view', $procurement);

        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->response($file->path, $file->file_name, [
            'Content-Type' => $file->mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Send the amendment for approval.
     */
    public function submit(Request $request, Procurement $procurement, ProcurementAmendment $amendment): RedirectResponse
    {
        $this->belongs($procurement, $amendment);
        $this->authorize('updateAmendment', [$procurement, $amendment]);

        $missing = $amendment->load(['files', 'changedArticles'])->missing();

        if ($missing !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Lengkapi dulu: '.implode(', ', $missing).'.']);

            return back();
        }

        try {
            $this->amendments->submit($amendment, $request->user());
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Amandemen {$amendment->roman()} diajukan untuk persetujuan."]);

        return back();
    }

    /**
     * Approve or return the amendment at its current level.
     */
    public function decide(ReviewPlanningRequest $request, Procurement $procurement, ProcurementAmendment $amendment): RedirectResponse
    {
        $this->belongs($procurement, $amendment);
        $this->authorize('decideAmendment', [$procurement, $amendment]);

        $approved = $request->boolean('approved');

        $this->amendments->decide($amendment, $request->user(), $approved, $request->string('note')->trim()->value() ?: null);

        Inertia::flash('toast', [
            'type' => $approved ? 'success' : 'warning',
            'message' => $approved ? 'Amandemen disetujui.' : 'Amandemen dikembalikan ke pengusul.',
        ]);

        return back();
    }

    /**
     * An amendment is only reached through its own procurement.
     */
    protected function belongs(Procurement $procurement, ProcurementAmendment $amendment): void
    {
        abort_unless($amendment->procurement_id === $procurement->id, 404);
    }
}
