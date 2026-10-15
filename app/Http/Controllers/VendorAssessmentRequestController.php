<?php

namespace App\Http\Controllers;

use App\Models\VendorAssessmentRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Housekeeping of the invoice detail requests sent in by vendors.
 */
class VendorAssessmentRequestController extends Controller
{
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
