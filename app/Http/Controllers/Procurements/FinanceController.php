<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\FinanceSection;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two boards after the execution:
 *
 * - Penyerahan: jobs whose execution TL Pengadaan approved, where the
 *   execution PIC files the hand-over papers and submits them to TL
 *   Pengadaan.
 * - Pembayaran: jobs whose hand-over TL Pengadaan approved, where Keuangan
 *   files the payment papers and submits them to TL Keuangan and the Asman,
 *   whose approval records the payment.
 */
class FinanceController extends Controller
{
    /**
     * The Pembayaran board: jobs to pay, and those paid recently.
     */
    public function index(Request $request): Response
    {
        return $this->board(
            $request,
            FinanceSection::Pembayaran,
            fn (Builder $query) => $query->where('handover_approval_state', PlanningApprovalState::Disetujui->value),
        );
    }

    /**
     * The Penyerahan board: hand-overs to file or decide, and those approved
     * on to Pembayaran recently.
     */
    public function handover(Request $request): Response
    {
        return $this->board($request, FinanceSection::Penyerahan, fn (Builder $query) => $query);
    }

    /**
     * One board: the jobs still in its stage (oldest first), and the latest
     * that went through it.
     *
     * @param  callable(Builder<Procurement>): Builder<Procurement>  $scope
     */
    protected function board(Request $request, FinanceSection $section, callable $scope): Response
    {
        /** @var User $user */
        $user = $request->user();
        $payment = $section === FinanceSection::Pembayaran;

        $base = fn () => $scope(Procurement::query()
            ->visibleTo($user)
            ->whereNotNull('finance_submitted_at')
            ->with(['workDirector', 'executor', 'financeSubmitter', 'paymentCompleter', 'handoverReviewer', 'checklists.checklistItem']));

        $search = $request->string('search')->trim()->value();
        $filter = fn ($query) => $search === ''
            ? $query
            : $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('number', 'like', "%{$search}%")
                ->orWhere('partner_name', 'like', "%{$search}%"));

        $waiting = fn () => $payment
            ? $filter($base()->whereNull('payment_completed_at'))->oldest('handover_reviewed_at')->oldest('id')
            : $filter($base()->where('handover_approval_state', '!=', PlanningApprovalState::Disetujui->value)->whereNull('completed_at'))
                ->oldest('finance_submitted_at')->oldest('id');
        $done = fn () => $payment
            ? $filter($base()->whereNotNull('payment_completed_at'))->latest('payment_completed_at')->latest('id')
            : $filter($base()->where('handover_approval_state', PlanningApprovalState::Disetujui->value))->latest('handover_reviewed_at')->latest('id');

        // One list at a time, a page at a time: a long board stays short.
        $view = $request->string('view')->value() === 'done' ? 'done' : 'waiting';
        $rows = ($view === 'done' ? $done() : $waiting())
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Procurement $procurement): array => $this->row($procurement, $section));

        $open = $waiting()->get();

        return Inertia::render($payment ? 'finance/index' : 'handover/index', [
            'section' => $section->value,
            'view' => $view,
            'rows' => $rows,
            'counts' => [
                'waiting' => $open->count(),
                'done' => $done()->count(),
            ],
            'summary' => [
                'waiting' => $open->count(),
                // The decisions waiting on someone, apart from the papers still being filed.
                'awaitingDecision' => $open->filter(fn (Procurement $procurement): bool => $payment
                    ? $procurement->payment_approval_state->isAwaiting()
                    : $procurement->isHandoverAwaitingApproval())->count(),
                'waitingValue' => $open->sum(fn (Procurement $procurement): float => $this->payableValue($procurement)),
                'paidThisMonth' => $payment
                    ? $base()->where('payment_completed_at', '>=', now()->startOfMonth())->count()
                    : $base()->where('handover_approval_state', PlanningApprovalState::Disetujui->value)
                        ->where('handover_reviewed_at', '>=', now()->startOfMonth())->count(),
            ],
            'filters' => ['search' => $search === '' ? null : $search],
        ]);
    }

    /**
     * What is owed to the partner: the negotiated value when there is one.
     */
    protected function payableValue(Procurement $procurement): float
    {
        return (float) ($procurement->value_after_negotiation ?? $procurement->hpe_value);
    }

    /**
     * One job on a board, with the steps of that board's tab.
     *
     * @return array<string, mixed>
     */
    protected function row(Procurement $procurement, FinanceSection $section): array
    {
        $steps = $procurement->checklists
            ->where('stage', ProcurementStage::Keuangan)
            ->filter(fn (ProcurementChecklist $checklist): bool => $checklist->financeSection() === $section);
        $done = $steps->where('is_completed', true)->count();
        $payment = $section === FinanceSection::Pembayaran;
        $state = $payment ? $procurement->payment_approval_state : $procurement->handover_approval_state;

        return [
            'id' => $procurement->id,
            'number' => $procurement->number,
            'name' => $procurement->name,
            'partner_name' => $procurement->partner_name,
            'division' => $procurement->workDirector->name,
            'executor' => $procurement->executor?->name,
            'value' => $this->payableValue($procurement),
            // When it arrived on this board.
            'submitted_at' => ($payment ? $procurement->handover_reviewed_at : $procurement->finance_submitted_at)?->toIso8601String(),
            'submitted_by' => ($payment ? $procurement->handoverReviewer : $procurement->financeSubmitter)?->name,
            // When it left it: paid, or the hand-over approved.
            'paid_at' => ($payment ? $procurement->payment_completed_at : $procurement->handover_reviewed_at)?->toIso8601String(),
            'paid_by' => ($payment ? $procurement->paymentCompleter : $procurement->handoverReviewer)?->name,
            'state' => $state->value,
            'state_label' => $payment ? $procurement->payment_approval_state->label() : match ($procurement->handover_approval_state) {
                PlanningApprovalState::BelumDiajukan => 'Belum Diajukan',
                PlanningApprovalState::MenungguPersetujuan => 'Menunggu TL Pengadaan',
                PlanningApprovalState::Disetujui => 'Disetujui',
                PlanningApprovalState::Ditolak => 'Dikembalikan',
            },
            'steps_done' => $done,
            'steps_total' => $steps->count(),
        ];
    }
}
