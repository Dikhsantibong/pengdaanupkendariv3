<?php

namespace App\Http\Controllers;

use App\Http\Requests\VendorAssessments\SubmitAssessmentRequestDetailsRequest;
use App\Models\VendorAssessmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page a vendor reaches from the request link printed in the contract.
 *
 * Deliberately unauthenticated and fixed: every vendor opens the same address
 * and each submission becomes one request on the assessment page. Nothing
 * already stored is shown here.
 */
class AssessmentRequestFormController extends Controller
{
    /**
     * Show the invoice details form, or the thank-you after a submission.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('assessment-signing/request', [
            'submitted' => $request->session()->get('assessment_request_submitted'),
        ]);
    }

    /**
     * Record the invoice details as a new request.
     */
    public function store(SubmitAssessmentRequestDetailsRequest $details): RedirectResponse
    {
        $request = VendorAssessmentRequest::query()->create($details->validated());

        return to_route('assessment-requests.show')->with('assessment_request_submitted', [
            'project' => $request->project,
            'vendor_name' => $request->vendor_name,
            'submitted_at' => $request->created_at?->toDateTimeString(),
        ]);
    }
}
