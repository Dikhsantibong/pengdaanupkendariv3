<?php

namespace App\Http\Controllers;

use App\Models\VendorAssessment;
use App\Models\VendorAssessmentRequest;
use App\Services\AssessmentSigningService;
use App\Services\VendorAssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Housekeeping of the invoice detail requests sent in by vendors.
 */
class VendorAssessmentRequestController extends Controller
{
    /**
     * The form date the Buat Formulir Penilaian page fills in: the date of
     * the printed form's revision.
     */
    public const FORM_DATE = '2024-06-10';

    /**
     * Open the assessment straight from a request and ready a signing link
     * for every sheet, each to its default assessor, so all that is left is
     * pressing send in WhatsApp.
     *
     * A request already dealt with leads to its assessment instead.
     */
    public function process(
        Request $request,
        VendorAssessmentRequest $assessmentRequest,
        VendorAssessmentService $assessments,
        AssessmentSigningService $signing,
    ): RedirectResponse {
        $opened = $assessmentRequest->vendor_assessment_id === null
            ? null
            : VendorAssessment::query()->find($assessmentRequest->vendor_assessment_id);

        if ($opened !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Request ini sudah diproses sebelumnya.']);

            return to_route('vendor-assessments.show', $opened);
        }

        [$assessment, $links] = DB::transaction(function () use ($request, $assessmentRequest, $assessments, $signing): array {
            $assessment = $assessments->create([
                ...Arr::except($assessments->defaultsFrom(null, $assessmentRequest), ['vendor_assessment_request_id']),
                'form_date' => self::FORM_DATE,
            ], $request->user());

            $assessmentRequest->update(['vendor_assessment_id' => $assessment->id]);

            $links = $signing->issueForEverySheet($assessment, $request->user(), $assessmentRequest->work_director_name);

            return [$assessment, $links];
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Penilaian dibuat dan {$links} tautan siap dikirim lewat WhatsApp.",
        ]);

        return to_route('vendor-assessments.show', $assessment);
    }

    /**
     * Drop a request from the list, such as a duplicate or a mistaken one.
     *
     * An assessment already opened from it is left untouched.
     */
    public function destroy(VendorAssessmentRequest $assessmentRequest): RedirectResponse
    {
        $assessmentRequest->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request dihapus dari daftar.']);

        return back();
    }
}
