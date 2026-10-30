<?php

namespace App\Http\Controllers\Procurements;

use App\Enums\ActivityType;
use App\Enums\Permission;
use App\Enums\ProposalState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\ProcurementAttachment;
use App\Models\User;
use App\Services\ProcurementService;
use App\Support\ProposalDocuments;
use App\Support\ProposalQueue;
use App\Support\UploadedFileType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The work proposal step: proposers follow their proposals, and the Direksi
 * Pekerjaan of each division reviews and approves them before planning.
 */
class ProposalController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * The proposals waiting on the user, and the user's own proposals.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $leadsDivision = $user->hasPermission(Permission::ApproveProposal);
        $leadsTeam = $user->leadsTeam();
        $canApprove = $leadsDivision || $leadsTeam || $user->role === UserRole::Administrator;

        $awaiting = $canApprove
            ? ProposalQueue::awaiting($user)
                ->with(['creator', 'workDirector', 'targetUnits', 'targetUnit'])
                ->withCount('attachments')
                ->oldest('proposal_submitted_at')
                ->get()
            : collect();

        $decided = $canApprove
            ? ProposalQueue::decided($user)
                ->with(['creator', 'workDirector', 'targetUnits', 'targetUnit'])
                ->withCount('attachments')
                ->latest('proposal_reviewed_at')
                ->limit(20)
                ->get()
            : collect();

        $mine = Procurement::query()
            ->where('created_by', $user->id)
            ->whereNotNull('proposal_submitted_at')
            ->with(['creator', 'workDirector', 'targetUnits', 'targetUnit', 'progressStatus'])
            ->withCount('attachments')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('proposals/index', [
            'canApprove' => $canApprove,
            'canPropose' => $user->can('create', Procurement::class),
            'division' => $user->workDirector?->name,
            'awaiting' => $awaiting->map(fn (Procurement $procurement): array => $this->row($procurement))->values()->all(),
            'decided' => $decided->map(fn (Procurement $procurement): array => $this->row($procurement))->values()->all(),
            'mine' => $mine->map(fn (Procurement $procurement): array => $this->row($procurement))->values()->all(),
        ]);
    }

    /**
     * Review one proposal: its details and supporting files side by side.
     */
    public function show(Request $request, Procurement $procurement): Response
    {
        $this->authorize('view', $procurement);

        $procurement->load([
            'creator.teamLeader', 'workDirector', 'targetUnits', 'targetUnit', 'procurementMethod',
            'budgetSource', 'proposalReviewer', 'proposalTeamLeaderReviewer', 'attachments.uploader',
            'activities' => fn ($query) => $query->whereIn('type', [
                ActivityType::Dibuat->value,
                ActivityType::UsulanDiajukan->value,
                ActivityType::UsulanDisetujuiTl->value,
                ActivityType::UsulanDisetujui->value,
                ActivityType::UsulanDikembalikan->value,
            ])->with('user')->latest('id'),
        ]);

        return Inertia::render('proposals/show', [
            'proposal' => [
                ...$this->row($procurement),
                'procurement_method' => $procurement->procurementMethod?->name,
                'budget_source' => $procurement->budgetSource?->name,
                'pr_number' => $procurement->pr_po_number,
                'coa_number' => $procurement->coa_number,
                'sr_number' => $procurement->sr_number,
                'wo_number' => $procurement->wo_number,
                'proposal_memo_number' => $procurement->proposal_memo_number,
                'proposal_memo_date' => $procurement->proposal_memo_date?->toDateString(),
                'notes' => $procurement->notes,
                'team_leader' => $procurement->creator?->teamLeader?->name,
                'tl_reviewed_by' => $procurement->proposalTeamLeaderReviewer?->name,
                'tl_reviewed_at' => $procurement->proposal_tl_reviewed_at?->toIso8601String(),
                'reviewed_by' => $procurement->proposal_state === ProposalState::Dikembalikan
                    && $procurement->proposal_reviewed_by === null
                    ? $procurement->proposalTeamLeaderReviewer?->name
                    : $procurement->proposalReviewer?->name,
                'reviewed_at' => $procurement->proposal_reviewed_at?->toIso8601String(),
                'review_note' => $procurement->proposal_review_note,
                // The TOR (drafted from its template, then uploaded signed) and
                // the Nota Dinas Usulan: the two documents a proposal files.
                'documents' => ProposalDocuments::payload($procurement),
                'missing_documents' => ProposalDocuments::missing($procurement),
                // Everything still owed; until it is in, the proposal stays
                // with the proposer instead of reaching the TL / Asman.
                'missing' => $procurement->missingProposalRequirements(),
                'is_held' => $procurement->isProposalHeld(),
                // Optional supporting documents beside the two above.
                'attachments' => ProposalDocuments::supporting($procurement),
                'history' => $procurement->activities->map(fn ($activity): array => [
                    'id' => $activity->id,
                    'label' => $activity->type->label(),
                    'description' => $activity->description,
                    'user' => $activity->user?->name,
                    'at' => $activity->created_at?->toIso8601String(),
                ])->all(),
            ],
            'can' => [
                'approve' => $request->user()->can('approveProposal', $procurement),
                // The TL's approval ends the round (K3 KAM, Lingkungan, or a
                // division with no Asman account): straight to planning.
                'approveIsFinal' => $procurement->proposal_state === ProposalState::MenungguPersetujuanTl
                    && $this->procurements->teamLeaderApprovalIsFinal($procurement, $request->user()),
                'revise' => $request->user()->can('reviseProposal', $procurement),
                'editDocuments' => $request->user()->can('editDocument', $procurement),
                'openProcurement' => $procurement->isProposalApproved(),
            ],
        ]);
    }

    /**
     * Approve a proposal or return it to the proposer.
     */
    public function decide(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('approveProposal', $procurement);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return'])],
            'note' => ['nullable', 'required_if:decision,return', 'string', 'max:2000'],
        ], [
            'note.required_if' => 'Tuliskan alasan agar pengusul tahu apa yang perlu diperbaiki.',
        ]);

        $approved = $validated['decision'] === 'approve';

        $this->procurements->decideProposal($procurement, $request->user(), $approved, $validated['note'] ?? null);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match (true) {
                ! $approved => "Usulan {$procurement->name} dikembalikan ke pengusul.",
                $procurement->isProposalApproved() => "Usulan {$procurement->name} disetujui dan diteruskan ke perencanaan.",
                default => "Usulan {$procurement->name} disetujui dan diteruskan ke Direksi Pekerjaan (Asman).",
            },
        ]);

        // Decided from the Approval menu: back to its queue.
        return $request->input('from') === 'approvals'
            ? back()
            : to_route('proposals.index');
    }

    /**
     * Send a returned proposal back for approval.
     */
    public function resubmit(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('reviseProposal', $procurement);

        abort_unless($procurement->proposal_state === ProposalState::Dikembalikan, 409);

        $this->procurements->resubmitProposal($procurement, $request->user());

        $missing = $procurement->refresh()->missingProposalRequirements();

        Inertia::flash('toast', $missing === []
            ? ['type' => 'success', 'message' => 'Usulan diajukan ulang untuk disetujui.']
            : ['type' => 'warning', 'message' => 'Usulan disimpan, tetapi belum diteruskan ke TL / Asman karena belum lengkap: '.implode(', ', $missing).'.']);

        return back();
    }

    /**
     * Add supporting documents to a proposal that is not approved yet.
     */
    public function attach(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('reviseProposal', $procurement);

        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:20480'],
        ]);

        $count = $this->procurements->attachFiles($procurement, $request->file('attachments', []), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$count} dokumen pendukung ditambahkan."]);

        return back();
    }

    /**
     * Remove a supporting file from a proposal that is not approved yet.
     */
    public function detach(Request $request, Procurement $procurement, ProcurementAttachment $attachment): RedirectResponse
    {
        $this->authorize('reviseProposal', $procurement);
        abort_unless($attachment->procurement_id === $procurement->id, 404);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen pendukung dihapus.']);

        return back();
    }

    /**
     * Stream a supporting file, inline so the reviewer can read it in place.
     */
    public function attachment(Procurement $procurement, ProcurementAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $procurement);
        abort_unless($attachment->procurement_id === $procurement->id, 404);

        return Storage::disk('local')->response(
            $attachment->path,
            $attachment->file_name,
            UploadedFileType::headers($attachment->mime),
            UploadedFileType::disposition($attachment->mime, $attachment->isPreviewable()),
        );
    }

    /**
     * The summary shown for a proposal in lists.
     *
     * @return array<string, mixed>
     */
    protected function row(Procurement $procurement): array
    {
        return [
            'id' => $procurement->id,
            'number' => $procurement->number,
            'name' => $procurement->name,
            'state' => $procurement->proposal_state->value,
            'state_label' => $procurement->isProposalHeld()
                ? 'Belum Lengkap (belum diteruskan)'
                : $procurement->proposal_state->label(),
            'is_held' => $procurement->isProposalHeld(),
            'division' => $procurement->workDirector->name,
            'proposer' => $procurement->creator?->name,
            'proposer_position' => $procurement->creator?->position,
            'target_units' => $procurement->targetUnitNames(),
            'prk_number' => $procurement->prk_number,
            'hpe_value' => (float) $procurement->hpe_value,
            'attachments_count' => (int) ($procurement->attachments_count ?? $procurement->attachments()->count()),
            'submitted_at' => $procurement->proposal_submitted_at?->toIso8601String(),
            'reviewed_at' => $procurement->proposal_reviewed_at?->toIso8601String(),
            'review_note' => $procurement->proposal_review_note,
        ];
    }
}
