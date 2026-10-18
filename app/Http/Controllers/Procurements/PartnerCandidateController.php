<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\PartnerCandidateRequest;
use App\Models\Procurement;
use App\Models\ProcurementPartnerCandidate;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The partner candidates (calon mitra) the planning PIC lists. The one
 * picked in the "Pelaksana" step carries its details into the procurement.
 */
class PartnerCandidateController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Add a candidate.
     */
    public function store(PartnerCandidateRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('updatePlanningIdentity', $procurement);

        // An SPPL is a direct appointment: one partner only.
        $direct = $procurement->isDirectAppointment();

        if ($direct && $procurement->partnerCandidates()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'SPPL ditunjuk langsung: cukup satu calon mitra.']);

            return back();
        }

        $candidate = $procurement->partnerCandidates()->create([
            ...$request->safe()->except('quotation_file'),
            'sort_order' => (int) $procurement->partnerCandidates()->max('sort_order') + 1,
        ]);

        $this->storeQuotation($candidate, $request->file('quotation_file'));

        // ... and that partner is the executor straight away.
        if ($direct) {
            $procurement->forceFill(['partner_candidate_id' => $candidate->id, ...$candidate->partnerFields()])->save();
        }

        $this->log($request, $procurement, "Calon mitra {$candidate->name} ditambahkan.");

        Inertia::flash('toast', ['type' => 'success', 'message' => "Calon mitra {$candidate->name} ditambahkan."]);

        return back();
    }

    /**
     * Correct a candidate; the picked one's details follow on the procurement.
     */
    public function update(PartnerCandidateRequest $request, Procurement $procurement, ProcurementPartnerCandidate $candidate): RedirectResponse
    {
        abort_unless($candidate->procurement_id === $procurement->id, 404);
        $this->authorize('updatePlanningIdentity', $procurement);

        $candidate->update($request->safe()->except('quotation_file'));

        $this->storeQuotation($candidate, $request->file('quotation_file'));

        if ($procurement->partner_candidate_id === $candidate->id) {
            $procurement->forceFill($candidate->partnerFields())->save();
        }

        $this->log($request, $procurement, "Calon mitra {$candidate->name} diperbarui.");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calon mitra diperbarui.']);

        return back();
    }

    /**
     * Remove a candidate that has not been picked.
     */
    public function destroy(Request $request, Procurement $procurement, ProcurementPartnerCandidate $candidate): RedirectResponse
    {
        abort_unless($candidate->procurement_id === $procurement->id, 404);
        $this->authorize('updatePlanningIdentity', $procurement);

        if ($procurement->partner_candidate_id === $candidate->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "{$candidate->name} sudah dipilih sebagai pelaksana dan tidak dapat dihapus."]);

            return back();
        }

        $candidate->delete();

        if ($candidate->quotation_file_path !== null) {
            Storage::disk('local')->delete($candidate->quotation_file_path);
        }

        $this->log($request, $procurement, "Calon mitra {$candidate->name} dihapus.");

        Inertia::flash('toast', ['type' => 'success', 'message' => "Calon mitra {$candidate->name} dihapus."]);

        return back();
    }

    /**
     * Show a candidate's scanned quotation, inline so it can be read in place.
     */
    public function quotation(Procurement $procurement, ProcurementPartnerCandidate $candidate): StreamedResponse
    {
        abort_unless($candidate->procurement_id === $procurement->id, 404);
        $this->authorize('view', $procurement);
        abort_if($candidate->quotation_file_path === null, 404);

        return Storage::disk('local')->response(
            $candidate->quotation_file_path,
            $candidate->quotation_file_name,
            ['Content-Type' => $candidate->quotation_file_mime ?? 'application/octet-stream'],
            'inline',
        );
    }

    /**
     * File a candidate's scanned quotation, replacing the previous one.
     *
     * The stored name never reuses what the browser reported, so a crafted
     * file name cannot steer where the file lands.
     */
    protected function storeQuotation(ProcurementPartnerCandidate $candidate, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        $path = $file->storeAs(
            'penawaran-calon-mitra/'.$candidate->procurement_id,
            'penawaran-'.$candidate->id.'-'.Str::random(10).($extension === '' ? '' : '.'.$extension),
            'local',
        );

        $previous = $candidate->quotation_file_path;

        $candidate->forceFill([
            'quotation_file_path' => $path,
            'quotation_file_name' => $file->getClientOriginalName(),
            'quotation_file_mime' => $file->getMimeType(),
            'quotation_file_size' => $file->getSize(),
        ])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }
    }

    protected function log(Request $request, Procurement $procurement, string $description): void
    {
        $this->procurements->recordActivity($procurement, $request->user(), ActivityType::Diperbarui, $description);
    }
}
