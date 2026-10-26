<?php

namespace App\Http\Controllers;

use App\Concerns\GroupsByPartner;
use App\Enums\ActivityType;
use App\Enums\AmendmentFileKind;
use App\Enums\AmendmentState;
use App\Enums\DeliveryOrderState;
use App\Enums\FinanceSection;
use App\Enums\Permission;
use App\Enums\ProcurementStage;
use App\Enums\StatusCategory;
use App\Http\Requests\Procurements\StoreProcurementRequest;
use App\Http\Requests\Procurements\UpdateProcurementRequest;
use App\Http\Resources\ProcurementResource;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementDeliveryOrder;
use App\Models\ProcurementDocument;
use App\Models\ProcurementStatusHistory;
use App\Models\User;
use App\Services\AmendmentService;
use App\Services\ContractRequirementReader;
use App\Services\DeliveryOrderService;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use App\Services\ProgressStatusResolver;
use App\Support\FinanceLetters;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
use App\Support\ProposalDocuments;
use App\Support\RoleDashboard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProcurementController extends Controller
{
    use GroupsByPartner;

    public function __construct(
        protected ProcurementService $procurements,
        protected DocumentGenerator $generator,
    ) {}

    /**
     * Show every procurement the current user is allowed to see.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $partner = $this->partner($request);
        $filtered = ProcurementFilters::apply(Procurement::query()->visibleTo($request->user()), $request);

        // The list: the open folder's work, or every match while searching
        // across the folders.
        $procurements = (clone $filtered)
            ->when($partner !== null, fn (Builder $query) => $this->ofPartner($query, $partner))
            ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'targetUnits', 'progressStatus', 'planner', 'executor'])
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('procurements/index', [
            'folders' => $partner !== null ? null : $this->folders(
                (clone $filtered)->reorder()
                    ->select(['id as procurement_id', 'partner_name', 'updated_at'])
                    ->selectRaw('CASE WHEN progress_status_id IN (SELECT id FROM progress_statuses WHERE category = ?) THEN 1 ELSE 0 END as done', [StatusCategory::Selesai->value]),
            ),
            'partner' => $partner === null ? null : $this->partnerPayload($partner),
            'procurements' => ProcurementResource::collection($procurements),
            'filters' => ProcurementFilters::current($request),
            'options' => MasterDataOptions::forFilters(),
        ]);
    }

    /**
     * Show the initial procurement input form.
     */
    public function create(): Response
    {
        $this->authorize('create', Procurement::class);

        /** @var User $user */
        $user = request()->user();

        return Inertia::render('procurements/create', [
            'proposal' => $this->proposalContext($user),
            'options' => MasterDataOptions::forProcurementForm(),
            // One suggestion per format, so switching between SPK and PJ fills
            // in the right running number without another round trip.
            'nextNumbers' => $this->procurements->nextNumbersByFormat(),
            // Appointing the planner is TL ICC's, so the field only shows to
            // whoever holds that right.
            'withPlanner' => $user->hasPermission(Permission::AssignPlanner),
        ]);
    }

    /**
     * Register a new procurement.
     */
    public function store(StoreProcurementRequest $request): RedirectResponse
    {
        $this->authorize('create', Procurement::class);

        $procurement = $this->procurements->create(
            $request->safe()->except(['attachments', 'tor_file', 'memo_file']),
            $request->user(),
        );

        $this->procurements->attachFiles($procurement, $request->file('attachments', []), $request->user());

        // A proposal comes with its TOR, drafted from the template for the
        // proposer to complete and the Direksi Pekerjaan to review.
        if ($this->proposalContext($request->user())['enabled']) {
            $this->generateProposalTor($procurement, $request->user());

            app(ProposalDocuments::class)->file(
                $procurement,
                $request->file('tor_file'),
                $request->file('memo_file'),
                $request->user(),
            );
        }

        // A proposal waits on the Direksi Pekerjaan: show it where it is followed.
        if (! $procurement->isProposalApproved()) {
            // It only reaches the TL / Asman once nothing is missing.
            $missing = $procurement->refresh()->missingProposalRequirements();

            Inertia::flash('toast', $missing === []
                ? ['type' => 'success', 'message' => 'Usulan pekerjaan terkirim untuk disetujui.']
                : ['type' => 'warning', 'message' => 'Usulan tersimpan, tetapi belum diteruskan ke TL / Asman. Lengkapi dulu: '.implode(', ', $missing).'.']);

            return to_route('proposals.show', $procurement);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengadaan {$procurement->number} berhasil dibuat.",
        ]);

        return to_route('procurements.show', $procurement);
    }

    /**
     * Draft the TOR of a new proposal.
     *
     * A missing template does not stop the proposal: the TOR can still be
     * generated later on the planning checklist.
     */
    protected function generateProposalTor(Procurement $procurement, User $author): void
    {
        $tor = DocumentType::query()->where('code', 'tor')->first();

        if ($tor === null) {
            return;
        }

        try {
            $this->generator->generate($procurement, $tor, $author);
        } catch (RuntimeException $exception) {
            report($exception);
        }
    }

    /**
     * How the form should behave for a user who may only propose work.
     *
     * @return array{enabled: bool, workDirectorId: int|null, division: string|null}
     */
    protected function proposalContext(User $user): array
    {
        return [
            'enabled' => ! $user->hasPermission(Permission::CreateProcurement)
                && $user->hasPermission(Permission::ProposeWork),
            'workDirectorId' => $user->work_director_id,
            'division' => $user->workDirector?->name,
        ];
    }

    /**
     * Show the full detail of a procurement.
     */
    public function show(Request $request, Procurement $procurement): Response
    {
        $this->authorize('view', $procurement);

        /** @var User $user */
        $user = $request->user();

        $this->procurements->syncChecklists($procurement);

        $procurement->load([
            'workDirector',
            'targetUnit',
            'procurementMethod',
            'budgetSource',
            'contractType',
            'selectionMethod',
            'targetUnits',
            'progressStatus',
            'planner',
            'executor',
            'planningReviewer',
            'executionReviewer',
            'creator',
            'financeSubmitter',
            'paymentCompleter',
            'checklists.checklistItem.documentTypes.selectionMethods',
            'selectionMethod',
            'checklists.completedBy',
            'checklists.files.uploader',
            'documents.documentType',
            'documents.generatedBy',
            'documents.editedBy',
            'documents.signedUploads.uploadedBy',
            'activities.user',
        ]);

        return Inertia::render('procurements/show', [
            'procurement' => (new ProcurementResource($procurement))->resolve(),
            'detail' => [
                'notes' => $procurement->notes,
                // The status is set by the system; this says why it is the one shown.
                'status_rule' => $procurement->progressStatus->systemStatus()?->rule(),
                // Where the job stands on its way to Terkontrak, and every
                // status it entered.
                'status_journey' => app(ProgressStatusResolver::class)->journey($procurement),
                'status_history' => $procurement->statusHistories()
                    ->with('user:id,name')
                    ->reorder()
                    ->latest('created_at')
                    ->latest('id')
                    ->limit(30)
                    ->get()
                    ->map(fn (ProcurementStatusHistory $entry): array => [
                        'id' => $entry->id,
                        'name' => $entry->status_name,
                        'key' => $entry->system_key,
                        'by' => $entry->user?->name,
                        'at' => $entry->created_at?->toIso8601String(),
                    ])
                    ->all(),
                'planning_review_note' => $procurement->planning_review_note,
                'planning_submitted_at' => $procurement->planning_submitted_at?->toDateTimeString(),
                'planning_reviewed_at' => $procurement->planning_reviewed_at?->toDateTimeString(),
                'planning_reviewer' => $procurement->planningReviewer?->name,
                'created_by' => $procurement->creator?->name,
                // Named so the planning PIC can see exactly what is holding the
                // submission back instead of just finding the button missing.
                'pending_required_planning' => [
                    ...array_map(fn (string $name): string => "{$name} (data identitas)", $procurement->missingProposalData()),
                    ...array_map(fn (string $name): string => "{$name} (tab dokumen pengguna)", ProposalDocuments::missing($procurement)),
                    ...$procurement->pendingRequiredPlanningChecklists()
                        ->map(fn ($checklist): string => $checklist->checklistItem->name)
                        ->all(),
                ],
                'is_planner' => $procurement->planner_id === $user->id,
                'planner_name' => $procurement->planner?->name,
                'planning_revision' => $procurement->planning_revision,
                // What procurement still has to fill in before a proposed job
                // can have its planning documents drawn and submitted.
                'missing_planning_data' => $procurement->missingPlanningData(),
                // The proposal's identity data, required before planning is
                // submitted (filled in through Edit Identitas Pengadaan).
                'missing_identity_data' => $procurement->missingProposalData(),
                // The TOR and Nota Dinas Usulan, shown on a tab of their own
                // named after whoever proposed or registered the work.
                'proposal_documents' => [
                    'proposer' => $procurement->creator->name ?? 'Pengusul',
                    'documents' => ProposalDocuments::payload($procurement),
                    // Optional supporting documents; the proposer adds and
                    // removes them until the proposal is approved.
                    'supporting' => ProposalDocuments::supporting($procurement),
                    'can_manage_supporting' => $request->user()->can('reviseProposal', $procurement),
                ],
                'finance' => [
                    'submitted_at' => $procurement->finance_submitted_at?->toDateTimeString(),
                    'submitted_by' => $procurement->financeSubmitter?->name,
                    'paid_at' => $procurement->payment_completed_at?->toDateTimeString(),
                    'paid_by' => $procurement->paymentCompleter?->name,
                    'pending_execution' => $procurement->pendingRequiredChecklists(ProcurementStage::Pelaksanaan)
                        ->map(fn ($checklist): string => $checklist->checklistItem->name)
                        ->all(),
                    // The execution PIC's submission to TL Pengadaan, which
                    // stands between execution and Keuangan.
                    'execution_approval' => [
                        'state' => $procurement->execution_approval_state->value,
                        'label' => $procurement->execution_approval_state->label(),
                        'submitted_at' => $procurement->execution_submitted_at?->toDateTimeString(),
                        'reviewed_at' => $procurement->execution_reviewed_at?->toDateTimeString(),
                        'reviewer' => $procurement->executionReviewer?->name,
                        'note' => $procurement->execution_review_note,
                        'revision' => $procurement->execution_revision,
                        'is_executor' => $procurement->executor_id === $user->id,
                        'executor_name' => $procurement->executor?->name,
                    ],
                    'pending_finance' => $procurement->pendingRequiredChecklists(ProcurementStage::Keuangan)
                        ->map(fn ($checklist): string => $checklist->checklistItem->name)
                        ->all(),
                    // The Penyerahan and Pembayaran requirements follow the
                    // contract, so they wait for what decides which contract.
                    // Per tab: the contract the requirements were read from,
                    // or null when the template's list stands in for it.
                    'requirements_source' => $this->requirementsSource($procurement),
                    'requirements_waiting_for' => array_values(array_filter([
                        $procurement->contract_number_format_id === null ? 'format kontrak' : null,
                        $procurement->contractNumberFormat?->uses_work_category && $procurement->work_category === null
                            ? 'kategori pekerjaan (barang / jasa)'
                            : null,
                    ])),
                ],
            ],
            'checklists' => [
                'perencanaan' => $this->checklistPayload($procurement, ProcurementStage::Perencanaan),
                'pelaksanaan' => $this->checklistPayload($procurement, ProcurementStage::Pelaksanaan),
                'keuangan' => $this->checklistPayload($procurement, ProcurementStage::Keuangan),
            ],
            'documents' => $procurement->documents
                ->sortByDesc('generated_at')
                ->values()
                ->map(fn ($document): array => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'type' => $document->documentType->name,
                    'upload_only' => $document->documentType->upload_only,
                    'template_version' => $document->template_version,
                    'revision' => $document->revision,
                    'generated_by' => $document->generatedBy?->name,
                    'generated_at' => $document->generated_at->toDateTimeString(),
                    'edited_by' => $document->editedBy?->name,
                    'edited_at' => $document->edited_at?->toDateTimeString(),
                    'uploads' => self::uploadPayload($document),
                ]),
            // The decisions and hand-overs from the proposal to the payment,
            // oldest first, so the table reads as the job's journey. Routine
            // edits stay in the activity log but are not part of it.
            'approvalHistory' => [
                'rows' => $procurement->activities
                    ->filter(fn ($activity): bool => $activity->approvalStage() !== null)
                    ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                    ->values()
                    ->map(fn ($activity): array => [
                        'id' => $activity->id,
                        'stage' => $activity->approvalStage(),
                        'step' => $activity->type->label(),
                        'outcome' => $activity->type->approvalOutcome(),
                        'description' => $activity->description,
                        'user' => $activity->user?->name,
                        'user_role' => $activity->user?->role?->label(),
                        'created_at' => $activity->created_at?->toDateTimeString(),
                    ])
                    ->all(),
                // What the job waits for now, shown as the table's last row.
                'waiting' => $procurement->completed_at === null ? RoleDashboard::note($procurement) : null,
            ],
            // The calon mitra listed in planning; the picked one is the executor.
            // An SPPL is a direct appointment: one partner, picked at once.
            'directAppointment' => $procurement->isDirectAppointment(),
            'partnerCandidates' => $procurement->partnerCandidates()->get()
                ->map(fn ($candidate): array => [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'vendor_code' => $candidate->vendor_code,
                    'director_name' => $candidate->director_name,
                    'address' => $candidate->address,
                    'quotation_number' => $candidate->quotation_number,
                    'quotation_date' => $candidate->quotation_date?->toDateString(),
                    'quotation_value' => $candidate->quotation_value === null ? null : (float) $candidate->quotation_value,
                    'quotation_file' => $candidate->quotation_file_path === null ? null : [
                        'name' => $candidate->quotation_file_name,
                        'size' => $candidate->quotation_file_size,
                        'url' => route('procurements.partner-candidates.quotation', [$procurement, $candidate]),
                    ],
                    'missing' => $candidate->missing(),
                    'is_selected' => $candidate->id === $procurement->partner_candidate_id,
                ])
                ->all(),
            // The Amandemen tab: every amendment of the contract, oldest first.
            'amendments' => $this->amendmentsPayload($procurement, $user),
            // The contract's articles, which Laksdan picks the changed ones
            // from; only read while an amendment waits on Laksdan.
            'amendmentArticles' => $procurement->amendments()->where('state', AmendmentState::MenungguTlPengadaan->value)->exists()
                ? app(AmendmentService::class)->contractArticles($procurement)
                : [],
            // The DO tab, shown for a KHS SPK / PJ only: the contract's price
            // list and every Delivery Order called off under it.
            'deliveryOrders' => $procurement->usesDeliveryOrders() ? $this->deliveryOrdersPayload($procurement, $user) : null,            'options' => MasterDataOptions::forProcurementDetail($procurement),
            // Given in the identity: the number once given, and the running
            // one the format offers.
            'contractNumber' => [
                'value' => $procurement->hasContractNumber() ? $procurement->number : null,
                'suggested' => $procurement->contractNumberFormat === null
                    ? null
                    : $this->procurements->nextNumber($procurement->contractNumberFormat),
            ],
            'can' => [
                'updateContractNumber' => $user->can('updateContractNumber', $procurement),
                'startAmendment' => $user->can('startAmendment', $procurement),
                'startDeliveryOrder' => $user->can('startDeliveryOrder', $procurement),
                'manageContractItems' => $user->can('manageContractItems', $procurement),                'update' => $user->can('update', $procurement),
                'assignPic' => $user->can('assignPic', $procurement),
                'assignPlanner' => $user->can('assignPlanner', $procurement),
                'assignExecutor' => $user->can('assignExecutor', $procurement),
                'updateStatus' => $user->can('updateStatus', $procurement),
                'updatePlanningIdentity' => $user->can('updatePlanningIdentity', $procurement),
                'updatePlanningChecklist' => $user->can('updatePlanningChecklist', $procurement),
                'updateExecutionChecklist' => $user->can('updateExecutionChecklist', $procurement),
                'updateFinanceChecklist' => $user->can('updateFinanceChecklist', $procurement),
                'submitExecution' => $user->can('submitExecution', $procurement),
                'reviewExecution' => $user->can('reviewExecution', $procurement),
                'completePayment' => $user->can('completePayment', $procurement),
                'submitPlanning' => $user->can('submitPlanning', $procurement),
                'reviewPlanning' => $user->can('reviewPlanning', $procurement),
                'revertPlanningRejection' => $user->can('revertPlanningRejection', $procurement),
                'complete' => $user->can('complete', $procurement),
                'generateDocument' => $user->can('generateDocument', $procurement),
                // TL ICC and the planning PIC only follow the execution.
                'manageExecutionDocuments' => $user->can('manageExecutionDocuments', $procurement),
                'editDocument' => $user->can('editDocument', $procurement),
            ],
        ]);
    }

    /**
     * The DO tab: the KHS contract's price list and its Delivery Orders,
     * newest last.
     *
     * @return array{available: bool, contract_items: array<int, array<string, mixed>>, orders: array<int, array<string, mixed>>}
     */
    protected function deliveryOrdersPayload(Procurement $procurement, User $user): array
    {
        $orders = $procurement->deliveryOrders()
            ->with(['items', 'creator', 'teamLeaderReviewer', 'directorReviewer', 'forwarder', 'issuer', 'document'])
            ->get();

        return [
            // Whether the contract document exists, which DOs wait for.
            'available' => app(DeliveryOrderService::class)->available($procurement),
            'contract_items' => $procurement->contractItems()->get()
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'group_name' => $item->group_name,
                    'description' => $item->description,
                    'part_number' => $item->part_number,
                    'unit' => $item->unit,
                    'unit_price' => (float) $item->unit_price,
                ])->all(),
            'orders' => $orders->map(fn (ProcurementDeliveryOrder $order): array => [
                'id' => $order->id,
                'sequence' => $order->sequence,
                'state' => $order->state->value,
                'state_label' => $order->state->label(),
                'request_memo_number' => $order->request_memo_number,
                'request_memo_date' => $order->request_memo_date?->toDateString(),
                'request_file' => $order->request_file_path === null ? null : [
                    'name' => $order->request_file_name,
                    'url' => route('procurements.delivery-orders.request-file', [$procurement, $order]),
                ],
                'do_memo_number' => $order->do_memo_number,
                'do_memo_date' => $order->do_memo_date?->toDateString(),
                'number' => $order->number,
                'order_date' => $order->order_date?->toDateString(),
                'contract_date' => $order->contract_date?->toDateString(),
                'vendor_code' => $order->vendor_code,
                'work_description' => $order->work_description,
                'delivery_place' => $order->delivery_place,
                'delivery_days' => $order->delivery_days,
                'created_by' => $order->creator?->name,
                'review_note' => $order->review_note,
                'revision' => $order->revision,
                'total' => $order->total(),
                'items' => $order->items->map(fn ($item): array => [
                    'contract_item_id' => $item->procurement_contract_item_id,
                    'group_name' => $item->group_name,
                    'description' => $item->description,
                    'part_number' => $item->part_number,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => (float) $item->unit_price,
                    'total' => $item->total(),
                ])->values()->all(),
                'steps' => array_values(array_filter([
                    $order->tl_reviewed_at === null ? null : ['label' => 'Disetujui TL', 'by' => $order->teamLeaderReviewer?->name, 'at' => $order->tl_reviewed_at->toDateTimeString()],
                    $order->director_reviewed_at === null ? null : ['label' => 'Disetujui Asman', 'by' => $order->directorReviewer?->name, 'at' => $order->director_reviewed_at->toDateTimeString()],
                    $order->forwarded_at === null ? null : ['label' => 'Diteruskan Rendan ke Laksdan', 'by' => $order->forwarder?->name, 'at' => $order->forwarded_at->toDateTimeString()],
                    $order->issued_at === null ? null : ['label' => 'DO diterbitkan Laksdan', 'by' => $order->issuer?->name, 'at' => $order->issued_at->toDateTimeString()],
                ])),
                'document' => $order->document === null ? null : [
                    'id' => $order->document->id,
                    'title' => $order->document->title,
                    'edit_url' => route('procurements.documents.edit', [$procurement, $order->document]),
                    'preview_url' => route('procurements.documents.show', [$procurement, $order->document, 'preview' => 1]),
                    'download_url' => route('procurements.documents.show', [$procurement, $order->document]),
                ],
                'missing' => match (true) {
                    $order->state->isEditable() => $order->missingRequest(),
                    $order->state === DeliveryOrderState::DiRendan => $order->missingMemo(),
                    $order->state === DeliveryOrderState::DiLaksdan => $order->missingIssue(),
                    default => [],
                },
                'can' => [
                    'request' => $user->can('updateDeliveryOrderRequest', [$procurement, $order]),
                    'decide' => $user->can('decideDeliveryOrder', [$procurement, $order]),
                    'forward' => $user->can('forwardDeliveryOrder', [$procurement, $order]),
                    'issue' => $user->can('issueDeliveryOrder', [$procurement, $order]),
                ],
            ])->all(),
        ];
    }

    /**
     * The amendments of the procurement's contract as the Amandemen tab
     * shows them.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function amendmentsPayload(Procurement $procurement, User $user): array
    {
        return $procurement->amendments()
            ->with(['changedArticles', 'files.uploadedBy', 'creator', 'teamLeaderReviewer', 'directorReviewer', 'reviewer', 'issuer', 'document'])
            ->get()
            ->map(fn (ProcurementAmendment $amendment): array => [
                'id' => $amendment->id,
                'sequence' => $amendment->sequence,
                'roman' => $amendment->roman(),
                'number' => $amendment->number,
                'state' => $amendment->state->value,
                'state_label' => $amendment->state->label(),
                'amendment_date' => $amendment->amendment_date?->toDateString(),
                'contract_date' => $amendment->contract_date?->toDateString(),
                'meeting_date' => $amendment->meeting_date?->toDateString(),
                'memo_number' => $amendment->memo_number,
                'memo_date' => $amendment->memo_date?->toDateString(),
                'request_number' => $amendment->request_number,
                'request_date' => $amendment->request_date?->toDateString(),
                'created_by' => $amendment->creator?->name,
                'submitted_at' => $amendment->submitted_at?->toDateTimeString(),
                'review_note' => $amendment->review_note,
                'revision' => $amendment->revision,
                'decisions' => array_values(array_filter([
                    $amendment->tl_reviewed_at === null ? null : ['level' => 'TL', 'by' => $amendment->teamLeaderReviewer?->name, 'at' => $amendment->tl_reviewed_at->toDateTimeString()],
                    $amendment->director_reviewed_at === null ? null : ['level' => 'Asman', 'by' => $amendment->directorReviewer?->name, 'at' => $amendment->director_reviewed_at->toDateTimeString()],
                    $amendment->reviewed_at === null ? null : ['level' => 'TL Pengadaan', 'by' => $amendment->reviewer?->name, 'at' => $amendment->reviewed_at->toDateTimeString()],
                    $amendment->issued_at === null ? null : ['level' => 'Diterbitkan Laksdan', 'by' => $amendment->issuer?->name, 'at' => $amendment->issued_at->toDateTimeString()],
                ])),
                'changes' => $amendment->changedArticles->map(fn ($change): array => [
                    'article' => $change->article,
                    'original_text' => $change->original_text,
                    'new_text' => $change->new_text,
                ])->values()->all(),
                // The papers asked for, plus an attendance list filed before
                // it stopped being asked for.
                'files' => collect(AmendmentFileKind::cases())
                    ->filter(fn (AmendmentFileKind $kind): bool => in_array($kind, AmendmentFileKind::asked(), true)
                        || $amendment->files->contains('kind', $kind))
                    ->sortBy(fn (AmendmentFileKind $kind): int => (int) array_search($kind, [...AmendmentFileKind::asked(), AmendmentFileKind::DaftarHadir], true))
                    ->values()
                    ->map(function (AmendmentFileKind $kind) use ($amendment, $procurement): array {
                        $file = $amendment->files->where('kind', $kind)->last();

                        return [
                            'kind' => $kind->value,
                            'label' => $kind->label(),
                            'required' => $kind->isRequired(),
                            'file' => $file === null ? null : [
                                'name' => $file->file_name,
                                'size' => $file->size,
                                'uploaded_by' => $file->uploadedBy?->name,
                                'uploaded_at' => $file->created_at?->toDateTimeString(),
                                'url' => route('procurements.amendments.file', [$procurement, $amendment, $file]),
                            ],
                        ];
                    })->all(),
                'document' => $amendment->document === null ? null : [
                    'id' => $amendment->document->id,
                    'title' => $amendment->document->title,
                    'edit_url' => route('procurements.documents.edit', [$procurement, $amendment->document]),
                    'preview_url' => route('procurements.documents.show', [$procurement, $amendment->document, 'preview' => 1]),
                ],
                'missing' => match (true) {
                    $amendment->state->isEditable() => $amendment->missingRequest(),
                    $amendment->state === AmendmentState::MenungguTlPengadaan => $amendment->missingIssue(),
                    default => [],
                },
                'can' => [
                    'update' => $user->can('updateAmendment', [$procurement, $amendment]),
                    'decide' => $user->can('decideAmendment', [$procurement, $amendment]),
                    'issue' => $user->can('issueAmendment', [$procurement, $amendment]),
                ],
            ])
            ->all();
    }

    /**
     * Show the procurement edit form.
     */
    public function edit(Procurement $procurement): Response
    {
        $this->authorize('update', $procurement);

        /** @var User $editor */
        $editor = request()->user();

        return Inertia::render('procurements/edit', [
            // A proposer revising their own proposal sees the proposal form.
            'proposal' => [
                ...$this->proposalContext($editor),
                'enabled' => ! $procurement->isProposalApproved() && $procurement->created_by === $editor->id
                    && ! $editor->hasPermission(Permission::CreateProcurement),
            ],
            'procurement' => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'contract_number_format_id' => $procurement->contract_number_format_id,
                'contract_type_id' => $procurement->contract_type_id,
                'csms_risk_level' => $procurement->csms_risk_level?->value,
                'tor_issue_date' => $procurement->tor_issue_date?->toDateString() ?? '',
                'name' => $procurement->name,
                'work_director_id' => $procurement->work_director_id,
                'target_unit_ids' => $procurement->servedUnits()->pluck('id')->all(),
                'budget_source_id' => $procurement->budget_source_id,
                'prk_number' => $procurement->prk_number ?? '',
                'proposal_memo_number' => $procurement->proposal_memo_number ?? '',
                'proposal_memo_date' => $procurement->proposal_memo_date?->toDateString() ?? '',
                'pr_po_number' => $procurement->pr_po_number ?? '',
                'coa_number' => $procurement->coa_number ?? '',
                'wo_number' => $procurement->wo_number ?? '',
                'sr_number' => $procurement->sr_number ?? '',
                'hpe_value' => (float) $procurement->hpe_value,
                'value_after_negotiation' => $procurement->value_after_negotiation === null
                    ? null
                    : (float) $procurement->value_after_negotiation,
                'notes' => $procurement->notes,
                'target_completion_date' => $procurement->target_completion_date?->toDateString() ?? '',
            ],
            'options' => MasterDataOptions::forProcurementForm(),
            'nextNumbers' => $this->procurements->nextNumbersByFormat(),
        ]);
    }

    /**
     * Update the identity data of a procurement.
     */
    public function update(UpdateProcurementRequest $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('update', $procurement);

        $oldPlaceholders = $this->generator->placeholderValues($procurement);

        $procurement->fill($request->safe()->except(['number', 'target_unit_ids']));

        // The number is not mass assignable, so a correction is applied here
        // rather than through fill(). A blank field leaves the number alone.
        $corrected = $request->string('number')->trim()->value();

        if ($corrected !== '') {
            $procurement->number = $corrected;
        }

        $procurement->save();

        /** @var array<int, int|string> $unitIds */
        $unitIds = $request->validated('target_unit_ids', []);

        $this->procurements->syncTargetUnits($procurement, $unitIds);

        // The method decides which checklist steps apply, so a change to it has
        // to be reflected on the checklist straight away.
        $this->procurements->syncChecklists($procurement);

        $this->generator->syncDocumentsOnProcurementUpdate($procurement, $oldPlaceholders);

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::Diperbarui,
            'Data pengadaan diperbarui.',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data pengadaan diperbarui.']);

        // A proposal still with its proposer goes back to the proposal page,
        // where it is resubmitted.
        if (! $procurement->isProposalApproved()) {
            return to_route('proposals.show', $procurement);
        }

        return to_route('procurements.show', $procurement);
    }

    /**
     * Archive a procurement.
     */
    public function destroy(Procurement $procurement): RedirectResponse
    {
        $this->authorize('delete', $procurement);

        $procurement->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengadaan diarsipkan.']);

        return to_route('procurements.index');
    }

    /**
     * Build the checklist payload for a single stage.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function checklistPayload(Procurement $procurement, ProcurementStage $stage): array
    {
        $resolvable = DocumentTemplate::documentTypeIdsResolvableFor(
            $procurement->procurement_method_id,
            $procurement->work_category,
            $procurement->contract_number_format_id,
        );

        // The partner's letters belong to Keuangan steps only.
        $letterTypes = $stage === ProcurementStage::Keuangan
            ? DocumentType::query()->whereIn('code', FinanceLetters::codes())->pluck('id', 'code')->all()
            : [];

        return $procurement->checklists
            ->where('stage', $stage)
            ->sortBy(fn ($checklist): int => $checklist->checklistItem->sort_order)
            ->values()
            ->map(function ($checklist) use ($procurement, $resolvable, $letterTypes): array {
                $item = $checklist->checklistItem;

                // Empty for steps that are plain ticks, which is what tells the
                // panel whether to offer the document actions at all.
                $documents = [];

                // A document of another contract format is not asked for.
                foreach ($item->documentTypesFor($procurement) as $type) {
                    $documents[] = $this->checklistDocumentPayload($procurement, $type, $resolvable);
                }

                return [
                    'id' => $checklist->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    // The Keuangan tab the step sits on; steps without one
                    // are shown under Pembayaran.
                    'section' => $item->section?->value,
                    'is_optional' => $item->is_optional,
                    'is_completed' => $checklist->is_completed,
                    'notes' => $checklist->notes,
                    'completed_by' => $checklist->completedBy?->name,
                    'completed_at' => $checklist->completed_at?->toDateTimeString(),
                    'documents' => $documents,
                    // A Penyerahan / Pembayaran requirement is finished by
                    // uploading its paper on the step itself.
                    'needs_upload' => $checklist->needsOwnUpload(),
                    // The partner's letter it is evidenced by, to download
                    // filled in, sign and upload back.
                    'template_url' => $this->letterUrl($procurement, $checklist, $letterTypes, $resolvable),
                    'files' => $checklist->needsOwnUpload()
                        ? $checklist->files->map(fn ($file): array => [
                            'id' => $file->id,
                            'file_name' => $file->file_name,
                            'size' => $file->size,
                            'uploaded_by' => $file->uploader?->name,
                            'uploaded_at' => $file->created_at?->toDateTimeString(),
                            'url' => route('procurements.checklists.files.show', [$procurement, $checklist, $file]),
                        ])->values()->all()
                        : [],
                    'input' => ($input = $item->inputFor($procurement)) === null ? null : [
                        'kind' => $input->value,
                        'label' => $input->label(),
                        'is_filled' => $input->isFilledOn($procurement),
                        'values' => $this->inputValues($procurement),
                    ],
                ];
            })
            ->all();
    }

    /**
     * Where to download the partner's letter a step is evidenced by, when
     * it is one of them and its template is in place.
     *
     * @param  array<string, int>  $letterTypes  Document type ids by code.
     * @param  array<int, int>  $resolvable  Document type ids with a template.
     */
    protected function letterUrl(Procurement $procurement, ProcurementChecklist $checklist, array $letterTypes, array $resolvable): ?string
    {
        $code = FinanceLetters::codeFor($checklist);

        if ($code === null || ! in_array($letterTypes[$code] ?? null, $resolvable, true)) {
            return null;
        }

        return route('procurements.checklists.template', [$procurement, $checklist]);
    }

    /**
     * Where each Keuangan tab's requirements come from: the title of the
     * contract they were read from, or null for the template's list.
     *
     * @return array<string, string|null>
     */
    protected function requirementsSource(Procurement $procurement): array
    {
        // Only requirements the contract still lists count; one it dropped
        // may linger as a ticked row of history.
        $readSections = [];

        foreach ($procurement->checklists->where('stage', ProcurementStage::Keuangan) as $checklist) {
            $item = $checklist->checklistItem;

            if ($item->procurement_id === $procurement->id && $item->is_active && $item->section !== null) {
                $readSections[$item->section->value] = true;
            }
        }

        $document = $readSections === []
            ? null
            : app(ContractRequirementReader::class)->contractDocument($procurement);

        $source = [];

        foreach (FinanceSection::cases() as $section) {
            $source[$section->value] = isset($readSections[$section->value]) ? ($document->title ?? 'kontrak') : null;
        }

        return $source;
    }

    /**
     * The current values of every step input, end date included.
     *
     * @return array<string, string|int|null>
     */
    protected function inputValues(Procurement $procurement): array
    {
        return [
            'execution_start_date' => $procurement->execution_start_date?->toDateString(),
            'execution_duration_days' => $procurement->execution_duration_days,
            'execution_end_date' => $procurement->executionEndDate()?->toDateString(),
            'warranty_months' => $procurement->warranty_months,
            'partner_candidate_id' => $procurement->partner_candidate_id,
            'bank_account_number' => $procurement->bank_account_number,
            'bank_name' => $procurement->bank_name,
            'bank_account_holder' => $procurement->bank_account_holder,
            'icc_memo_number' => $procurement->icc_memo_number,
            'icc_memo_date' => $procurement->icc_memo_date?->toDateString(),
            'rks_number' => $procurement->rks_number,
            'hpe_number' => $procurement->hpe_number,
            'upb_number' => $procurement->upb_number,
            // The contract number once given, and the running one to offer.
            'number' => $procurement->hasContractNumber() ? $procurement->number : null,
            'suggested_number' => $procurement->contractNumberFormat === null
                ? null
                : $this->procurements->nextNumber($procurement->contractNumberFormat),
        ];
    }

    /**
     * The state of one document a checklist step produces.
     *
     * @param  array<int, int>  $resolvable  Document type ids with a template.
     * @return array<string, mixed>
     */
    protected function checklistDocumentPayload(
        Procurement $procurement,
        DocumentType $type,
        array $resolvable,
    ): array {
        $document = $procurement->documentFor($type->id);

        return [
            'type_id' => $type->id,
            'type_name' => $type->nameFor($procurement),
            'id' => $document?->id,
            'title' => $document?->title,
            'is_signed' => $document?->isSigned() ?? false,
            'uploads' => $document === null ? [] : self::uploadPayload($document),
            'has_template' => in_array($type->id, $resolvable, true),
            // Uploaded rather than generated: the step offers only an upload.
            'upload_only' => $type->upload_only,
            // One of a set of alternatives; a single signed copy is enough.
            'is_alternative' => $type->isAlternative(),
        ];
    }

    /**
     * The signed scans filed against a document.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function uploadPayload(ProcurementDocument $document): array
    {
        return $document->signedUploads
            ->map(fn ($upload): array => [
                'id' => $upload->id,
                'file_name' => $upload->file_name,
                'size' => $upload->size,
                'uploaded_by' => $upload->uploadedBy?->name,
                'uploaded_at' => $upload->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }
}
