<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ProcurementStage;
use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Keuangan stage: executed jobs are handed over, their invoice papers
 * checked, the partner paid and the procurement closed.
 */
class FinanceController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * The jobs waiting for payment, and those paid recently.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $base = fn () => Procurement::query()
            ->visibleTo($user)
            ->whereNotNull('finance_submitted_at')
            ->with(['workDirector', 'executor', 'financeSubmitter', 'paymentCompleter', 'checklists.checklistItem']);

        $search = $request->string('search')->trim()->value();
        $filter = fn ($query) => $search === ''
            ? $query
            : $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%")
                ->orWhere('partner_name', 'like', "%{$search}%"));

        $waiting = $filter($base()->whereNull('payment_completed_at'))->oldest('finance_submitted_at')->get();
        $paid = $filter($base()->whereNotNull('payment_completed_at'))->latest('payment_completed_at')->limit(30)->get();

        return Inertia::render('finance/index', [
            'waiting' => $waiting->map(fn (Procurement $procurement): array => $this->row($procurement))->values()->all(),
            'paid' => $paid->map(fn (Procurement $procurement): array => $this->row($procurement))->values()->all(),
            'summary' => [
                'waiting' => $waiting->count(),
                'waitingValue' => $waiting->sum(fn (Procurement $procurement): float => $this->payableValue($procurement)),
                'paidThisMonth' => $base()->where('payment_completed_at', '>=', now()->startOfMonth())->count(),
            ],
            'filters' => ['search' => $search === '' ? null : $search],
        ]);
    }

    /**
     * Hand the executed job to Keuangan.
     */
    public function submit(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('submitToFinance', $procurement);

        $this->procurements->submitToFinance($procurement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengadaan diserahkan ke Keuangan untuk proses pembayaran.']);

        return back();
    }

    /**
     * Record the payment and close the job.
     */
    public function complete(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('completePayment', $procurement);

        $this->procurements->completePayment($procurement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pembayaran {$procurement->name} selesai, pengadaan ditutup."]);

        return back();
    }

    /**
     * What is owed to the partner: the negotiated value when there is one.
     */
    protected function payableValue(Procurement $procurement): float
    {
        return (float) ($procurement->value_after_negotiation ?? $procurement->hpe_value);
    }

    /**
     * One job on the Keuangan board.
     *
     * @return array<string, mixed>
     */
    protected function row(Procurement $procurement): array
    {
        $steps = $procurement->checklists->where('stage', ProcurementStage::Keuangan);
        $done = $steps->where('is_completed', true)->count();

        return [
            'id' => $procurement->id,
            'number' => $procurement->number,
            'name' => $procurement->name,
            'partner_name' => $procurement->partner_name,
            'division' => $procurement->workDirector->name,
            'executor' => $procurement->executor?->name,
            'value' => $this->payableValue($procurement),
            'submitted_at' => $procurement->finance_submitted_at?->toIso8601String(),
            'submitted_by' => $procurement->financeSubmitter?->name,
            'paid_at' => $procurement->payment_completed_at?->toIso8601String(),
            'paid_by' => $procurement->paymentCompleter?->name,
            'steps_done' => $done,
            'steps_total' => $steps->count(),
        ];
    }
}
