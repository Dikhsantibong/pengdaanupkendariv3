<?php

namespace App\Services;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\ProposalState;
use App\Enums\SystemStatus;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementDocument;
use App\Models\ProgressStatus;
use Illuminate\Support\Collection;

/**
 * Sets the progress status of a procurement from where it actually stands,
 * so nobody has to keep it up to date by hand.
 *
 * The status is read from the job's state and its documents, in journey
 * order: the proposal (TOR, Nota Dinas, approval), the planning (PIC, the
 * planning papers, ICC's approval), the execution (PIC, HPS, SMART SCM and
 * Berita Acara, the contract, TL Pengadaan's approval), then Keuangan.
 * An SPPL follows its own, shorter path.
 *
 * Batal is the only status chosen by hand; a cancelled job keeps it until
 * it is restored. A status switched off on the master data is not set,
 * the job keeps the status it had.
 *
 * The status is written with a plain query, never through save(), so
 * setting it does not fire the procurement's events (which redraw its
 * documents) and cannot loop.
 */
class ProgressStatusResolver
{
    /**
     * The planning papers an SPK or PJ drafts before its offer comes in.
     *
     * @var array<int, string>
     */
    private const PLANNING_DRAFTS = ['rks', 'upb', 'hpe'];

    /**
     * The active system statuses, by key, once read.
     *
     * @var array<string, int>|null
     */
    private ?array $statusIds = null;

    /**
     * Bring the procurement's status in line with where it stands.
     */
    public function sync(Procurement $procurement): void
    {
        if ($procurement->getKey() === null) {
            return;
        }

        $ids = $this->statusIds();

        // A cancelled job stays cancelled until someone restores it.
        if (isset($ids[SystemStatus::Batal->value]) && (int) $procurement->progress_status_id === $ids[SystemStatus::Batal->value]) {
            return;
        }

        $this->apply($procurement, $this->resolve($procurement));
    }

    /**
     * Lift a cancellation: the job takes the status it would have now.
     */
    public function restore(Procurement $procurement): void
    {
        $this->apply($procurement, $this->resolve($procurement));
    }

    /**
     * Cancel the job.
     */
    public function cancel(Procurement $procurement): void
    {
        $this->apply($procurement, SystemStatus::Batal);
    }

    /**
     * Whether the job is cancelled.
     */
    public function isCancelled(Procurement $procurement): bool
    {
        $id = $this->statusIds()[SystemStatus::Batal->value] ?? null;

        return $id !== null && (int) $procurement->progress_status_id === $id;
    }

    /**
     * Whether the automatic statuses are set up at all.
     */
    public function isAvailable(): bool
    {
        return $this->statusIds() !== [];
    }

    /**
     * Where the procurement stands now.
     */
    public function resolve(Procurement $procurement): SystemStatus
    {
        if ($procurement->completed_at !== null || $procurement->payment_completed_at !== null) {
            return SystemStatus::Selesai;
        }

        if ($procurement->finance_submitted_at !== null) {
            return SystemStatus::Terkontrak;
        }

        $documents = $this->documents($procurement);

        if ($procurement->planning_approval_state === PlanningApprovalState::Disetujui) {
            return $this->execution($procurement, $documents);
        }

        if ($procurement->proposal_submitted_at !== null && $procurement->proposal_state !== ProposalState::Disetujui) {
            return match (true) {
                $procurement->proposal_state === ProposalState::Dikembalikan => SystemStatus::RevisiUsulan,
                ! $this->signed($documents, 'tor') => SystemStatus::PenyusunanTor,
                ! $this->signed($documents, 'nota-dinas-usulan') => SystemStatus::DraftNodisUsulan,
                default => SystemStatus::ApprovalUsulan,
            };
        }

        return $this->planning($procurement, $documents);
    }

    /**
     * The planning stage, once the proposal is approved.
     *
     * @param  Collection<string, bool>  $documents
     */
    protected function planning(Procurement $procurement, Collection $documents): SystemStatus
    {
        if ($procurement->planning_approval_state === PlanningApprovalState::MenungguPersetujuan) {
            return SystemStatus::ApprovalIcc;
        }

        if ($procurement->planning_approval_state === PlanningApprovalState::Ditolak) {
            return SystemStatus::RevisiPerencanaan;
        }

        if ($procurement->planner_id === null) {
            return SystemStatus::InisiasiRendan;
        }

        if ($this->isSuratPesanan($procurement)) {
            return SystemStatus::DraftNodisLaksdan;
        }

        $required = $this->requiredDocuments($procurement, ProcurementStage::Perencanaan);

        foreach (self::PLANNING_DRAFTS as $code) {
            if ($required->contains($code) && ! $documents->has($code)) {
                return SystemStatus::PenyusunanRks;
            }
        }

        if ($required->contains('rab') && ! $this->signed($documents, 'rab')) {
            return SystemStatus::PenyusunanRks;
        }

        if ($required->contains('penawaran') && ! $this->signed($documents, 'penawaran')) {
            return SystemStatus::MenungguPenawaran;
        }

        return SystemStatus::DraftNodisPengadaan;
    }

    /**
     * The execution stage, once ICC approved the planning.
     *
     * @param  Collection<string, bool>  $documents
     */
    protected function execution(Procurement $procurement, Collection $documents): SystemStatus
    {
        if ($procurement->executor_id === null) {
            return SystemStatus::InisiasiLaksdan;
        }

        if ($procurement->execution_approval_state === PlanningApprovalState::MenungguPersetujuan) {
            return SystemStatus::ApprovalTlPengadaan;
        }

        if ($procurement->execution_approval_state === PlanningApprovalState::Ditolak) {
            return SystemStatus::RevisiPelaksanaan;
        }

        if ($this->isSuratPesanan($procurement)) {
            return SystemStatus::PenyusunanSuratPesanan;
        }

        $steps = $this->executionSteps($procurement);

        if (! $this->stepDone($steps, $documents, 'penyusunan-hps')) {
            return SystemStatus::PenyusunanHps;
        }

        if (! $this->stepDone($steps, $documents, 'proses-smart-scm')) {
            return SystemStatus::ProsesSmartScm;
        }

        return SystemStatus::PenyusunanKontrak;
    }

    /**
     * Whether the job follows the SPPL path, let by Surat Pesanan.
     */
    protected function isSuratPesanan(Procurement $procurement): bool
    {
        $procurement->loadMissing(['contractNumberFormat', 'procurementMethod']);

        return $procurement->contractNumberFormat?->code === 'SPPL'
            || $procurement->procurementMethod?->code === 'surat-pesanan';
    }

    /**
     * Whether the step that files a document is done: every document it
     * asks for has a signed copy (or it was ticked). A step the job does not
     * go through counts as done.
     *
     * @param  Collection<int, array{completed: bool, required: array<int, string>}>  $steps
     * @param  Collection<string, bool>  $documents
     */
    protected function stepDone(Collection $steps, Collection $documents, string $code): bool
    {
        $step = $steps->first(fn (array $step): bool => in_array($code, $step['required'], true));

        if ($step === null || $step['completed']) {
            return true;
        }

        return collect($step['required'])->every(fn (string $required): bool => $this->signed($documents, $required));
    }

    /**
     * The execution steps of the job with the documents each one asks for.
     *
     * @return Collection<int, array{completed: bool, required: array<int, string>}>
     */
    protected function executionSteps(Procurement $procurement): Collection
    {
        return ProcurementChecklist::query()
            ->where('procurement_id', $procurement->getKey())
            ->where('stage', ProcurementStage::Pelaksanaan->value)
            ->with('checklistItem.documentTypes')
            ->get()
            ->map(fn (ProcurementChecklist $checklist): array => [
                'completed' => $checklist->is_completed,
                'required' => $checklist->checklistItem->documentTypes
                    ->reject(fn (DocumentType $type): bool => $type->isAlternative())
                    ->map(fn (DocumentType $type): string => $type->code)
                    ->values()
                    ->all(),
            ])
            ->values();
    }

    /**
     * The document codes a stage of the job asks for, through its checklist.
     *
     * @return Collection<int, string>
     */
    protected function requiredDocuments(Procurement $procurement, ProcurementStage $stage): Collection
    {
        return ProcurementChecklist::query()
            ->where('procurement_id', $procurement->getKey())
            ->where('stage', $stage->value)
            ->with('checklistItem.documentTypes')
            ->get()
            ->flatMap(fn (ProcurementChecklist $checklist) => $checklist->checklistItem->documentTypes->map(fn (DocumentType $type): string => $type->code))
            ->unique()
            ->values();
    }

    /**
     * The job's documents by type code: whether a signed copy is filed.
     *
     * @return Collection<string, bool>
     */
    protected function documents(Procurement $procurement): Collection
    {
        return ProcurementDocument::query()
            ->where('procurement_id', $procurement->getKey())
            ->join('document_types', 'document_types.id', '=', 'procurement_documents.document_type_id')
            ->select(['procurement_documents.id', 'document_types.code as type_code'])
            ->withExists('signedUploads')
            ->get()
            ->groupBy('type_code')
            ->map(fn (Collection $documents): bool => $documents->contains(fn (ProcurementDocument $document): bool => (bool) $document->getAttribute('signed_uploads_exists')));
    }

    /**
     * Whether a document of the type has a signed copy filed.
     *
     * @param  Collection<string, bool>  $documents
     */
    protected function signed(Collection $documents, string $code): bool
    {
        return $documents->get($code, false) === true;
    }

    /**
     * Write the status, quietly, when it changes.
     */
    protected function apply(Procurement $procurement, SystemStatus $status): void
    {
        $id = $this->statusIds()[$status->value] ?? null;

        if ($id === null || (int) $procurement->progress_status_id === $id) {
            return;
        }

        Procurement::withTrashed()->whereKey($procurement->getKey())->update(['progress_status_id' => $id]);

        $procurement->forceFill(['progress_status_id' => $id])->syncOriginalAttribute('progress_status_id');
        $procurement->unsetRelation('progressStatus');
    }

    /**
     * The active system statuses, by key.
     *
     * @return array<string, int>
     */
    public function statusIds(): array
    {
        return $this->statusIds ??= ProgressStatus::query()
            ->active()
            ->whereNotNull('system_key')
            ->pluck('id', 'system_key')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Forget the statuses read, after the master data changed.
     */
    public function forget(): void
    {
        $this->statusIds = null;
    }
}
