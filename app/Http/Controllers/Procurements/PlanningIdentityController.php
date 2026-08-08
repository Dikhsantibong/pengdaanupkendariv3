<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\UpdatePlanningIdentityRequest;
use App\Models\ContractType;
use App\Models\Procurement;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlanningIdentityController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Record the identity details the planning PIC supplies.
     *
     * Each control on the identity panel posts only its own field, so the
     * request is applied one key at a time and anything left out keeps its
     * current value.
     */
    public function update(UpdatePlanningIdentityRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('updatePlanningIdentity', $procurement);

        $changes = [];

        if ($request->has('contract_type_id')) {
            $changes[] = $this->applyContractType($procurement, $request->integer('contract_type_id') ?: null);
        }

        if ($request->has('manager_memo_number')) {
            $changes[] = $this->applyManagerMemoNumber(
                $procurement,
                $request->string('manager_memo_number')->trim()->value() ?: null,
            );
        }

        $changes = array_filter($changes);

        if ($changes === []) {
            return back();
        }

        $procurement->save();

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::Diperbarui,
            implode(' ', $changes),
            [
                'contract_type_id' => $procurement->contract_type_id,
                'manager_memo_number' => $procurement->manager_memo_number,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identitas pengadaan diperbarui.']);

        return back();
    }

    /**
     * Set the contract type, returning what changed for the audit trail.
     */
    protected function applyContractType(Procurement $procurement, ?int $contractTypeId): ?string
    {
        if ($procurement->contract_type_id === $contractTypeId) {
            return null;
        }

        $previous = $procurement->contract_type_id === null
            ? null
            : $procurement->contractType->name;

        $procurement->contract_type_id = $contractTypeId;

        $current = $contractTypeId === null
            ? 'kosong'
            : ContractType::query()->findOrFail($contractTypeId)->name;

        return $previous === null
            ? "Jenis kontrak ditetapkan: {$current}."
            : "Jenis kontrak diubah dari {$previous} menjadi {$current}.";
    }

    /**
     * Set the manager's memo number, returning what changed.
     */
    protected function applyManagerMemoNumber(Procurement $procurement, ?string $number): ?string
    {
        if ($procurement->manager_memo_number === $number) {
            return null;
        }

        $previous = $procurement->manager_memo_number;
        $procurement->manager_memo_number = $number;

        if ($number === null) {
            return 'Nomor nota dinas manager dikosongkan.';
        }

        return $previous === null
            ? "Nomor nota dinas manager ditetapkan: {$number}."
            : "Nomor nota dinas manager diubah dari {$previous} menjadi {$number}.";
    }
}
