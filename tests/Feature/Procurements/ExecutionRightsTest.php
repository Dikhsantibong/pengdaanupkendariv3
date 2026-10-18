<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The execution is worked on by its PIC and overseen by TL Pengadaan. TL ICC,
 * like a planning PIC, only follows it: no ticking, no data, no documents.
 */
class ExecutionRightsTest extends TestCase
{
    use RefreshDatabase;

    protected User $planner;

    protected User $executor;

    protected Procurement $procurement;

    protected DocumentType $executionType;

    protected DocumentType $planningType;

    protected DocumentType $executionUpload;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->executionType = DocumentType::factory()->create(['code' => 'kontrak-uji']);
        $this->planningType = DocumentType::factory()->create(['code' => 'rks-uji']);
        $this->executionUpload = DocumentType::factory()->create(['code' => 'jaminan-uji', 'upload_only' => true]);

        foreach ([$this->executionType, $this->planningType] as $type) {
            DocumentTemplate::factory()->create(['document_type_id' => $type->id, 'procurement_method_id' => null]);
        }

        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->create(['name' => 'Kontrak Uji'])
            ->documentTypes()->attach([$this->executionType->id, $this->executionUpload->id]);
        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->create(['name' => 'RKS Uji'])
            ->documentTypes()->attach($this->planningType->id);

        $this->planner = User::factory()->planner()->create();
        $this->executor = User::factory()->executor()->create();
        $this->procurement = Procurement::factory()
            ->planningApproved()
            ->plannedBy($this->planner)
            ->executedBy($this->executor)
            ->create(['procurement_method_id' => null]);

        app(ProcurementService::class)->syncChecklists($this->procurement);
    }

    public function test_tl_icc_cannot_change_anything_in_execution(): void
    {
        $icc = User::factory()->teamLeaderIcc()->create();
        $step = $this->executionStep();

        $this->actingAs($icc)
            ->get(route('procurements.show', $this->procurement))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.updateExecutionChecklist', false)
                ->where('can.manageExecutionDocuments', false));

        $this->actingAs($icc)
            ->put(route('procurements.checklists.update', [$this->procurement, $step]), ['is_completed' => true])
            ->assertForbidden();

        $this->actingAs($icc)
            ->post(route('procurements.documents.store', $this->procurement), ['document_type_id' => $this->executionType->id])
            ->assertForbidden();

        $this->actingAs($icc)
            ->post(route('procurements.documents.upload', $this->procurement), [
                'document_type_id' => $this->executionUpload->id,
                'files' => [UploadedFile::fake()->create('kontrak.pdf', 20, 'application/pdf')],
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->procurement->documents()->count());
    }

    public function test_tl_icc_cannot_touch_an_execution_document_drawn_by_others(): void
    {
        $this->actingAs($this->executor)
            ->post(route('procurements.documents.store', $this->procurement), ['document_type_id' => $this->executionType->id])
            ->assertSessionHasNoErrors();

        $document = $this->procurement->documents()->sole();
        $icc = User::factory()->teamLeaderIcc()->create();

        $this->actingAs($icc)
            ->get(route('procurements.documents.edit', [$this->procurement, $document]))
            ->assertForbidden();

        $this->actingAs($icc)
            ->post(route('procurements.documents.signed.store', [$this->procurement, $document]), [
                'files' => [UploadedFile::fake()->create('ttd.pdf', 20, 'application/pdf')],
            ])
            ->assertForbidden();

        // Reading it stays open.
        $this->actingAs($icc)
            ->get(route('procurements.documents.show', [$this->procurement, $document, 'format' => 'html']))
            ->assertOk();
    }

    public function test_the_planning_pic_cannot_handle_execution_documents_either(): void
    {
        $this->actingAs($this->planner)
            ->post(route('procurements.documents.store', $this->procurement), ['document_type_id' => $this->executionType->id])
            ->assertForbidden();
    }

    public function test_the_executor_and_tl_pengadaan_work_on_execution(): void
    {
        foreach ([$this->executor, User::factory()->teamLeaderPengadaan()->create()] as $user) {
            $this->assertTrue($user->can('updateExecutionChecklist', $this->procurement));
            $this->assertTrue($user->can('manageExecutionDocuments', $this->procurement));
        }

        $this->actingAs($this->executor)
            ->post(route('procurements.documents.store', $this->procurement), ['document_type_id' => $this->executionType->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->procurement->documents()->count());
    }

    public function test_tl_icc_still_handles_planning_documents(): void
    {
        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->post(route('procurements.documents.store', $this->procurement), ['document_type_id' => $this->planningType->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->procurement->documents()->count());
    }

    protected function executionStep(): ProcurementChecklist
    {
        return $this->procurement->checklists()->where('stage', ProcurementStage::Pelaksanaan->value)->firstOrFail();
    }
}
