<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\ProcurementStage;
use App\Http\Requests\Procurements\StoreProcurementRequest;
use App\Http\Requests\Procurements\UpdateProcurementRequest;
use App\Http\Resources\ProcurementResource;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\ProcurementService;
use App\Support\MasterDataOptions;
use App\Support\ProcurementFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    public function __construct(protected ProcurementService $procurements) {}

    /**
     * Show every procurement the current user is allowed to see.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Procurement::class);

        $procurements = ProcurementFilters::apply(
            Procurement::query()
                ->visibleTo($request->user())
                ->with(['workDirector', 'targetUnit', 'procurementMethod', 'budgetSource', 'prRoNumber', 'progressStatus', 'planner', 'executor']),
            $request,
        )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('procurements/index', [
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

        return Inertia::render('procurements/create', [
            'options' => MasterDataOptions::forProcurementForm(),
            // One suggestion per format, so switching between SPK and PJ fills
            // in the right running number without another round trip.
            'nextNumbers' => $this->procurements->nextNumbersByFormat(),
        ]);
    }

    /**
     * Register a new procurement.
     */
    public function store(StoreProcurementRequest $request): RedirectResponse
    {
        $this->authorize('create', Procurement::class);

        $procurement = $this->procurements->create($request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pengadaan {$procurement->number} berhasil dibuat.",
        ]);

        return to_route('procurements.show', $procurement);
    }

    /**
     * Show the full detail of a procurement.
     */
    public function show(Request $request, Procurement $procurement): Response
    {
        $this->authorize('view', $procurement);

        /** @var User $user */
        $user = $request->user();

        $procurement->load([
            'workDirector',
            'targetUnit',
            'procurementMethod',
            'budgetSource',
            'contractType',
            'prRoNumber',
            'progressStatus',
            'planner',
            'executor',
            'planningReviewer',
            'creator',
            'checklists.checklistItem.documentTypes',
            'checklists.completedBy',
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
                'planning_review_note' => $procurement->planning_review_note,
                'planning_submitted_at' => $procurement->planning_submitted_at?->toDateTimeString(),
                'planning_reviewed_at' => $procurement->planning_reviewed_at?->toDateTimeString(),
                'planning_reviewer' => $procurement->planningReviewer?->name,
                'created_by' => $procurement->creator?->name,
                // Named so the planning PIC can see exactly what is holding the
                // submission back instead of just finding the button missing.
                'pending_required_planning' => $procurement->pendingRequiredPlanningChecklists()
                    ->map(fn ($checklist): string => $checklist->checklistItem->name)
                    ->all(),
                'is_planner' => $procurement->planner_id === $user->id,
                'planner_name' => $procurement->planner?->name,
                'planning_revision' => $procurement->planning_revision,
            ],
            'checklists' => [
                'perencanaan' => $this->checklistPayload($procurement, ProcurementStage::Perencanaan),
                'pelaksanaan' => $this->checklistPayload($procurement, ProcurementStage::Pelaksanaan),
            ],
            'documents' => $procurement->documents
                ->sortByDesc('generated_at')
                ->values()
                ->map(fn ($document): array => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'type' => $document->documentType->name,
                    'template_version' => $document->template_version,
                    'revision' => $document->revision,
                    'generated_by' => $document->generatedBy?->name,
                    'generated_at' => $document->generated_at->toDateTimeString(),
                    'edited_by' => $document->editedBy?->name,
                    'edited_at' => $document->edited_at?->toDateTimeString(),
                    'uploads' => self::uploadPayload($document),
                ]),
            'activities' => $procurement->activities
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($activity): array => [
                    'id' => $activity->id,
                    'type' => $activity->type->value,
                    'type_label' => $activity->type->label(),
                    'description' => $activity->description,
                    'user' => $activity->user?->name,
                    'created_at' => $activity->created_at?->toDateTimeString(),
                ]),
            'options' => MasterDataOptions::forProcurementDetail($procurement),
            'can' => [
                'update' => $user->can('update', $procurement),
                'assignPic' => $user->can('assignPic', $procurement),
                'updateStatus' => $user->can('updateStatus', $procurement),
                'updatePlanningIdentity' => $user->can('updatePlanningIdentity', $procurement),
                'updatePlanningChecklist' => $user->can('updatePlanningChecklist', $procurement),
                'updateExecutionChecklist' => $user->can('updateExecutionChecklist', $procurement),
                'submitPlanning' => $user->can('submitPlanning', $procurement),
                'reviewPlanning' => $user->can('reviewPlanning', $procurement),
                'revertPlanningRejection' => $user->can('revertPlanningRejection', $procurement),
                'complete' => $user->can('complete', $procurement),
                'generateDocument' => $user->can('generateDocument', $procurement),
            ],
        ]);
    }

    /**
     * Show the procurement edit form.
     */
    public function edit(Procurement $procurement): Response
    {
        $this->authorize('update', $procurement);

        return Inertia::render('procurements/edit', [
            'procurement' => [
                'id' => $procurement->id,
                'number' => $procurement->number,
                'contract_number_format_id' => $procurement->contract_number_format_id,
                'name' => $procurement->name,
                'work_director_id' => $procurement->work_director_id,
                'target_unit_id' => $procurement->target_unit_id,
                'procurement_method_id' => $procurement->procurement_method_id,
                'budget_source_id' => $procurement->budget_source_id,
                'pr_ro_number_id' => $procurement->pr_ro_number_id,
                'prk_number' => $procurement->prk_number,
                'hpe_value' => (float) $procurement->hpe_value,
                'progress_status_id' => $procurement->progress_status_id,
                'target_completion_date' => $procurement->target_completion_date?->toDateString(),
                'notes' => $procurement->notes,
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

        $procurement->fill($request->safe()->except('number'));

        // The number is not mass assignable, so a correction is applied here
        // rather than through fill(). A blank field leaves the number alone.
        $corrected = $request->string('number')->trim()->value();

        if ($corrected !== '') {
            $procurement->number = $corrected;
        }

        $procurement->save();

        // The method decides which checklist steps apply, so a change to it has
        // to be reflected on the checklist straight away.
        $this->procurements->syncChecklists($procurement);

        $this->procurements->recordActivity(
            $procurement,
            $request->user(),
            ActivityType::Diperbarui,
            'Data pengadaan diperbarui.',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data pengadaan diperbarui.']);

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
        );

        return $procurement->checklists
            ->where('stage', $stage)
            ->sortBy(fn ($checklist): int => $checklist->checklistItem->sort_order)
            ->values()
            ->map(function ($checklist) use ($procurement, $resolvable): array {
                $item = $checklist->checklistItem;

                // Empty for steps that are plain ticks, which is what tells the
                // panel whether to offer the document actions at all.
                $documents = [];

                foreach ($item->documentTypes as $type) {
                    $documents[] = $this->checklistDocumentPayload($procurement, $type, $resolvable);
                }

                return [
                    'id' => $checklist->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'is_optional' => $item->is_optional,
                    'is_completed' => $checklist->is_completed,
                    'notes' => $checklist->notes,
                    'completed_by' => $checklist->completedBy?->name,
                    'completed_at' => $checklist->completed_at?->toDateTimeString(),
                    'documents' => $documents,
                ];
            })
            ->all();
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
            'type_name' => $type->name,
            'id' => $document?->id,
            'title' => $document?->title,
            'is_signed' => $document?->isSigned() ?? false,
            'uploads' => $document === null ? [] : self::uploadPayload($document),
            'has_template' => in_array($type->id, $resolvable, true),
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
