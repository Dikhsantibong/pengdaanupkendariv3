<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\UpdatePlanningIdentityRequest;
use App\Models\ContractType;
use App\Models\Procurement;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlanningIdentityController extends Controller
{
    public function __construct(
        protected ProcurementService $procurements,
        protected DocumentGenerator $generator,
    ) {}

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

        $oldPlaceholders = $this->generator->placeholderValues($procurement);

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

        if ($request->has('target_completion_date')) {
            $changes[] = $this->applyTargetDate($procurement, $request->date('target_completion_date')?->toImmutable()->startOfDay());
        }

        $changes = array_filter($changes);

        if ($changes === []) {
            return back();
        }

        $procurement->save();

        $this->generator->syncDocumentsOnProcurementUpdate($procurement, $oldPlaceholders);

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::Diperbarui,
            implode(' ', $changes),
            [
                'contract_type_id' => $procurement->contract_type_id,
                'manager_memo_number' => $procurement->manager_memo_number,
                'target_completion_date' => $procurement->target_completion_date?->toDateString(),
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

        $contractType = $contractTypeId === null ? null : ContractType::query()->findOrFail($contractTypeId);

        $procurement->contract_type_id = $contractTypeId;

        // The jenis kontrak decides the barang/jasa document format of the
        // formats that ask for one, exactly as on the procurement form.
        if ($procurement->contractNumberFormat?->uses_work_category === true) {
            $procurement->work_category = $contractType?->work_category;
        }

        $current = $contractType === null ? 'kosong' : $contractType->name;

        return $previous === null
            ? "Jenis kontrak ditetapkan: {$current}."
            : "Jenis kontrak diubah dari {$previous} menjadi {$current}.";
    }

    /**
     * Set the target completion date, returning what changed.
     *
     * It drives the target column, the S-curve and the overdue warnings.
     */
    protected function applyTargetDate(Procurement $procurement, ?CarbonImmutable $date): ?string
    {
        if ($procurement->target_completion_date?->toDateString() === $date?->toDateString()) {
            return null;
        }

        $procurement->target_completion_date = $date;

        return $date === null
            ? 'Target selesai dikosongkan.'
            : 'Target selesai ditetapkan: '.$date->translatedFormat('d F Y').'.';
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
