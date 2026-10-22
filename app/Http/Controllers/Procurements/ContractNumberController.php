<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\UpdateContractNumberRequest;
use App\Models\Procurement;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The contract number, given in the identity of the procurement by its
 * execution PIC or TL Pengadaan. The Lampiran Surat Pesanan step still asks
 * for it, and is satisfied once it is here.
 */
class ContractNumberController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Keep the contract number, without the spaces typed around it.
     */
    public function __invoke(UpdateContractNumberRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('updateContractNumber', $procurement);

        $number = trim($request->string('number')->value());
        $previous = $procurement->hasContractNumber() ? $procurement->number : null;

        if ($number !== $previous) {
            $procurement->forceFill(['number' => $number])->save();

            $this->procurements->recordActivity(
                $procurement,
                $request->user(),
                ActivityType::Diperbarui,
                $previous === null
                    ? "Nomor kontrak diisi: {$number}."
                    : "Nomor kontrak diubah dari {$previous} menjadi {$number}.",
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nomor kontrak tersimpan.']);

        return back();
    }
}
