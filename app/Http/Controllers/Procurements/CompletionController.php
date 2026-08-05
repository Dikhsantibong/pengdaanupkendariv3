<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompletionController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Mark a procurement as finished.
     */
    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('complete', $procurement);

        $this->procurements->complete($procurement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengadaan ditandai selesai.']);

        return back();
    }
}
