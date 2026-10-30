<?php

namespace App\Http\Controllers;

use App\Enums\FinanceSection;
use App\Enums\Permission;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Http\Resources\ProcurementResource;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementChecklistFile;
use App\Models\ProcurementDeliveryOrder;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\ProcurementService;
use App\Support\ContractRequestQueue;
use App\Support\MasterDataOptions;
use App\Support\PaymentQueue;
use App\Support\ProcurementFilters;
use App\Support\ProposalDocuments;
use App\Support\ProposalQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalQueueController extends Controller
{
    /**
     * The approval queues, each held by whoever holds its review right:
     * planning submissions for TL ICC, execution submissions for TL Pengadaan.
     *
     * @var array<string, array{label: string, permission: Permission}>
     */
    protected const QUEUES = [
        'planning' => ['label' => 'Perencanaan', 'permission' => Permission::ReviewPlanning],
        'execution' => ['label' => 'Pelaksanaan', 'permission' => Permission::ReviewExecution],
        'handover' => ['label' => 'Penyerahan', 'permission' => Permission::ReviewHandover],
    ];

    /**
     * The payment queue, open to its deciders (TL Keuangan, the Asman):
     * see PaymentQueue.
     */
    protected const PAYMENT_QUEUE = 'payment';

    /**
     * The work proposal queue, open to the proposer's TL and the division's
     * Direksi Pekerjaan: see ProposalQueue.
     */
    protected const PROPOSAL_QUEUE = 'proposal';

    /**
     * The queues of contract requests, open to whoever takes part in them
     * (TL, Asman, Rendan, Laksdan): their steps are not one right but the
     * people around the job, see ContractRequestQueue.
     *
     * @var array<string, string>
     */
    protected const REQUEST_QUEUES = [
        'amendment' => 'Amandemen',
        'delivery-order' => 'Delivery Order',
    ];

    /**
     * Show only the queues the user decides on, one at a time, filtered and
     * paginated on the server so a long queue stays light.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        /** @var User $user */
        $user = $request->user();

        // The proposals come first: the journey starts there.
        $queues = [
            ...(ProposalQueue::involves($user) ? [self::PROPOSAL_QUEUE] : []),
            ...array_keys(array_filter(
                self::QUEUES,
                fn (array $queue): bool => $user->hasPermission($queue['permission']),
            )),
        ];

        if (PaymentQueue::involves($user)) {
            $queues[] = self::PAYMENT_QUEUE;
        }

        foreach (array_keys(self::REQUEST_QUEUES) as $kind) {
            if (ContractRequestQueue::involvesIn($user, $kind)) {
                $queues[] = $kind;
            }
        }

        $queue = in_array($request->string('queue')->value(), $queues, true)
            ? $request->string('queue')->value()
            : ($queues[0] ?? null);
        $view = $request->string('view')->value() === 'decided' ? 'decided' : 'waiting';

        if ($queue !== null && isset(self::REQUEST_QUEUES[$queue])) {
            return Inertia::render('approvals/index', [
                ...$this->queueTabs($user, $queues),
                'queue' => $queue,
                'view' => $view,
                'procurements' => null,
                'requests' => $this->requests($user, $queue, $view),
                'filters' => ProcurementFilters::current($request),
                'options' => MasterDataOptions::forFilters(),
            ]);
        }

        $procurements = $queue === null
            ? null
            : ProcurementFilters::apply($this->queueQuery($user, $queue, $view), $request)
                ->with([
                    'workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'targetUnits', 'progressStatus', 'planner', 'executor',
                    'checklists.checklistItem', 'checklists.files',
                    'documents.documentType', 'documents.signedUploads',
                    'handoverSubmitter', 'paymentSubmitter', 'paymentTeamLeaderReviewer',
                    'creator', 'attachments', 'documents.documentType', 'documents.signedUploads',
                ])
                ->paginate(15)
                ->withQueryString();

        // Read before the resource wraps the page's models.
        $reviews = $procurements === null
            ? []
            : collect($procurements->items())
                ->mapWithKeys(fn (Procurement $procurement): array => [$procurement->id => $this->review($user, $queue, $procurement)])
                ->all();

        return Inertia::render('approvals/index', [
            ...$this->queueTabs($user, $queues),
            'queue' => $queue,
            'view' => $view,
            'procurements' => $procurements === null ? null : ProcurementResource::collection($procurements),
            // What the reviewer reads before deciding, keyed by procurement.
            'reviews' => $reviews,
            'requests' => null,
            'filters' => ProcurementFilters::current($request),
            'options' => MasterDataOptions::forFilters(),
        ]);
    }

    /**
     * One submission as the reviewer sees it: who sent it and when, the
     * previous note, the papers of its stage to preview, and whether the
     * user decides it now.
     *
     * @return array<string, mixed>
     */
    protected function review(User $user, string $queue, Procurement $procurement): array
    {
        [$submittedAt, $submittedBy, $revision, $note, $ability] = match ($queue) {
            self::PROPOSAL_QUEUE => [$procurement->proposal_submitted_at, $procurement->creator?->name, 0, $procurement->proposal_review_note, 'approveProposal'],
            'planning' => [$procurement->planning_submitted_at, $procurement->planner?->name, $procurement->planning_revision, $procurement->planning_review_note, 'reviewPlanning'],
            'execution' => [$procurement->execution_submitted_at, $procurement->executor?->name, $procurement->execution_revision, $procurement->execution_review_note, 'reviewExecution'],
            'handover' => [$procurement->handover_submitted_at, $procurement->handoverSubmitter?->name, $procurement->handover_revision, $procurement->handover_review_note, 'reviewHandover'],
            default => [$procurement->payment_submitted_at, $procurement->paymentSubmitter?->name, $procurement->payment_revision, $procurement->payment_review_note, 'decidePayment'],
        };

        return [
            'submitted_at' => $submittedAt?->toIso8601String(),
            'submitted_by' => $submittedBy,
            'revision' => $revision,
            'note' => $note,
            'value' => (float) ($procurement->value_after_negotiation ?? $procurement->hpe_value),
            'partner_name' => $procurement->partner_name,
            'payment_state' => $queue === self::PAYMENT_QUEUE ? $procurement->payment_approval_state->value : null,
            'tl_reviewer' => $queue === self::PAYMENT_QUEUE ? $procurement->paymentTeamLeaderReviewer?->name : null,
            // A proposal: whether this decision is the last (straight to planning).
            'proposal_state' => $queue === self::PROPOSAL_QUEUE ? $procurement->proposal_state->value : null,
            'approve_is_final' => $queue === self::PROPOSAL_QUEUE
                && ($procurement->proposal_state === ProposalState::MenungguPersetujuan
                    || app(ProcurementService::class)->teamLeaderApprovalIsFinal($procurement, $user)),
            'can_decide' => $user->can($ability, $procurement),
            'papers' => $this->papers($queue, $procurement),
        ];
    }

    /**
     * The papers filed for the stage under review, each previewable: the
     * validated uploads of the stage's documents (or the generated draft
     * while none is uploaded), or the files of the Keuangan tab.
     *
     * @return array<int, array{name: string, url: string, is_image: bool}>
     */
    protected function papers(string $queue, Procurement $procurement): array
    {
        // A proposal: its TOR and Nota Dinas Usulan, then its supporting files.
        if ($queue === self::PROPOSAL_QUEUE) {
            $papers = collect(ProposalDocuments::payload($procurement))
                ->flatMap(fn (array $document): array => $document['uploads'] === []
                    ? ($document['preview_url'] === null ? [] : [[
                        'name' => $document['type_name'].' (draf)',
                        'url' => $document['preview_url'],
                        'is_image' => false,
                    ]])
                    : array_map(fn (array $upload): array => [
                        'name' => $document['type_name'].' · '.$upload['file_name'],
                        'url' => $upload['url'],
                        'is_image' => $upload['is_image'],
                    ], $document['uploads']));

            return $papers
                ->concat(array_map(fn (array $file): array => [
                    'name' => 'Pendukung · '.$file['file_name'],
                    'url' => $file['url'],
                    'is_image' => $file['is_image'],
                ], ProposalDocuments::supporting($procurement)))
                ->values()
                ->all();
        }

        if (in_array($queue, ['handover', self::PAYMENT_QUEUE], true)) {
            $section = $queue === 'handover' ? FinanceSection::Penyerahan : FinanceSection::Pembayaran;

            return $procurement->checklists
                ->filter(fn (ProcurementChecklist $checklist): bool => $checklist->financeSection() === $section)
                ->flatMap(fn (ProcurementChecklist $checklist) => $checklist->files->map(fn (ProcurementChecklistFile $file): array => [
                    'name' => $checklist->checklistItem->name.' · '.$file->file_name,
                    'url' => route('procurements.checklists.files.show', [$procurement, $checklist, $file]),
                    'is_image' => str_starts_with((string) $file->mime, 'image/'),
                ]))
                ->values()
                ->all();
        }

        $stage = $queue === 'planning' ? ProcurementStage::Perencanaan : ProcurementStage::Pelaksanaan;

        return $procurement->documents
            ->filter(fn (ProcurementDocument $document): bool => $document->documentType->stage === $stage)
            ->flatMap(function (ProcurementDocument $document) use ($procurement): array {
                $name = $document->documentType->name;

                if ($document->signedUploads->isEmpty()) {
                    return $document->document_template_id === null ? [] : [[
                        'name' => $name.' (draf)',
                        'url' => route('procurements.documents.show', [$procurement, $document, 'preview' => 1]),
                        'is_image' => false,
                    ]];
                }

                return $document->signedUploads->map(fn ($upload): array => [
                    'name' => $name.' · '.$upload->file_name,
                    'url' => route('procurements.documents.signed.show', [$procurement, $document, $upload, 'preview' => 1]),
                    'is_image' => str_starts_with((string) $upload->mime, 'image/'),
                ])->all();
            })
            ->values()
            ->all();
    }

    /**
     * The queue tabs with how many wait in each.
     *
     * @param  list<string>  $queues
     * @return array{queues: list<array{key: string, label: string, waiting: int}>}
     */
    protected function queueTabs(User $user, array $queues): array
    {
        return ['queues' => array_map(fn (string $key): array => [
            'key' => $key,
            'label' => self::QUEUES[$key]['label'] ?? self::REQUEST_QUEUES[$key] ?? ($key === self::PROPOSAL_QUEUE ? 'Usulan Pekerjaan' : 'Pembayaran'),
            'waiting' => match ($key) {
                'amendment' => ContractRequestQueue::amendmentsAwaiting($user)->count(),
                'delivery-order' => ContractRequestQueue::deliveryOrdersAwaiting($user)->count(),
                default => $this->queueQuery($user, $key, 'waiting')->count(),
            },
        ], $queues)];
    }

    /**
     * One page of amendments or Delivery Orders: those waiting on the user
     * (oldest submission first), or those the user already decided.
     *
     * @return array{data: list<array<string, mixed>>, links: array<int, mixed>, meta: array<string, mixed>}
     */
    protected function requests(User $user, string $queue, string $view): array
    {
        $amendments = $queue === 'amendment';
        $query = match (true) {
            $amendments && $view === 'waiting' => ContractRequestQueue::amendmentsAwaiting($user)->orderBy('submitted_at')->orderBy('id'),
            $amendments => ContractRequestQueue::amendmentsDecided($user),
            $view === 'waiting' => ContractRequestQueue::deliveryOrdersAwaiting($user)->orderBy('submitted_at')->orderBy('id'),
            default => ContractRequestQueue::deliveryOrdersDecided($user),
        };

        $page = $query
            ->with(['procurement.workDirector', 'creator'])
            ->paginate(15)
            ->withQueryString();

        return [
            'data' => array_values(array_map(fn (ProcurementAmendment|ProcurementDeliveryOrder $request): array => [
                'id' => $request->id,
                'title' => $request instanceof ProcurementAmendment
                    ? 'Amandemen '.$request->roman()
                    : 'DO ke-'.$request->sequence,
                'number' => $request->number,
                'state' => $request->state->value,
                'state_label' => $request->state->label(),
                'proposer' => $request->creator?->name,
                'submitted_at' => $request->submitted_at?->toIso8601String(),
                'updated_at' => $request->updated_at?->toIso8601String(),
                'procurement' => [
                    'id' => $request->procurement->id,
                    'number' => $request->procurement->number,
                    'name' => $request->procurement->name,
                    'work_director' => $request->procurement->workDirector->name,
                ],
                // Decided on the job's own tab, as from its notification.
                'url' => route('procurements.show', [
                    $request->procurement,
                    'tab' => $request instanceof ProcurementAmendment ? 'amandemen' : 'do',
                ]),
            ], $page->items())),
            'links' => $page->linkCollection()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ];
    }

    /**
     * The procurements of one queue the user may see: those waiting for the
     * decision (oldest submission first), or those already decided (latest
     * decision first).
     *
     * @return Builder<Procurement>
     */
    protected function queueQuery(User $user, string $queue, string $view): Builder
    {
        if ($queue === self::PROPOSAL_QUEUE) {
            return $view === 'waiting'
                ? ProposalQueue::awaiting($user)->oldest('proposal_submitted_at')->orderBy('id')
                : ProposalQueue::decided($user)->latest('proposal_reviewed_at')->latest('proposal_tl_reviewed_at')->latest('id');
        }

        if ($queue === self::PAYMENT_QUEUE) {
            return $view === 'waiting'
                ? PaymentQueue::awaiting($user)->orderBy('payment_submitted_at')->orderBy('id')
                : PaymentQueue::decided($user)->latest('payment_reviewed_at')->latest('payment_tl_reviewed_at')->latest('id');
        }

        $query = Procurement::query()->visibleTo($user);
        $decided = [PlanningApprovalState::Disetujui->value, PlanningApprovalState::Ditolak->value];

        if ($queue === 'handover') {
            return $view === 'waiting'
                ? $query->where('handover_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                    ->whereNull('payment_completed_at')
                    ->orderBy('handover_submitted_at')->orderBy('id')
                : $query->whereIn('handover_approval_state', $decided)
                    ->whereNotNull('handover_reviewed_by')
                    ->latest('handover_reviewed_at')->latest('id');
        }

        if ($queue === 'planning') {
            return $view === 'waiting'
                ? $query->where('planning_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                    ->orderBy('planning_submitted_at')->orderBy('id')
                : $query->whereIn('planning_approval_state', $decided)
                    ->whereNotNull('planning_reviewed_at')
                    ->latest('planning_reviewed_at')->latest('id');
        }

        return $view === 'waiting'
            ? $query->where('execution_approval_state', PlanningApprovalState::MenungguPersetujuan->value)
                ->whereNull('finance_submitted_at')
                ->orderBy('execution_submitted_at')->orderBy('id')
            : $query->whereIn('execution_approval_state', $decided)
                ->whereNotNull('execution_reviewed_at')
                ->latest('execution_reviewed_at')->latest('id');
    }
}
