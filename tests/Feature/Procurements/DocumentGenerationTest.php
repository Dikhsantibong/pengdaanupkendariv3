<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_pic_can_generate_a_document_from_the_active_template(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create(['name' => 'Overhaul Mesin']);

        $documentType = DocumentType::factory()->create(['name' => 'RKS', 'code' => 'rks']);
        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'version' => 1,
            'body' => '<h1>{{nama_pengadaan}}</h1><p>{{unit_tujuan}}</p>',
        ]);

        $this->actingAs($planner)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $documentType->id,
            ])
            ->assertRedirect();

        $document = ProcurementDocument::query()->firstOrFail();

        $this->assertSame($procurement->id, $document->procurement_id);
        $this->assertSame(1, $document->template_version);
        $this->assertStringContainsString('Overhaul Mesin', $document->rendered_body);
        $this->assertStringContainsString($procurement->targetUnit->name, $document->rendered_body);
        $this->assertStringNotContainsString('{{nama_pengadaan}}', $document->rendered_body);
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => 'dokumen_digenerate',
        ]);
    }

    public function test_generating_fails_gracefully_when_no_active_template_exists(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $documentType = DocumentType::factory()->create();

        $this->actingAs($planner)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $documentType->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('procurement_documents', 0);
    }

    public function test_archived_documents_keep_the_template_used_at_generation_time(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $documentType = DocumentType::factory()->create();
        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'version' => 1,
            'body' => '<p>Versi lama {{nama_pengadaan}}</p>',
        ]);

        $this->actingAs($planner)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        $document = ProcurementDocument::query()->firstOrFail();
        $originalBody = $document->rendered_body;

        DocumentTemplate::query()->update(['is_active' => false]);
        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'version' => 2,
            'body' => '<p>Versi baru {{nama_pengadaan}}</p>',
        ]);

        $this->assertSame($originalBody, $document->refresh()->rendered_body);
        $this->assertStringContainsString('Versi lama', $document->rendered_body);
    }

    public function test_document_can_be_downloaded_by_an_assigned_pic_only(): void
    {
        $planner = User::factory()->planner()->create();
        $outsider = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();

        $documentType = DocumentType::factory()->create();
        DocumentTemplate::factory()->create(['document_type_id' => $documentType->id]);

        $this->actingAs($planner)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        $document = ProcurementDocument::query()->firstOrFail();

        $this->actingAs($planner)
            ->get(route('procurements.documents.show', [$procurement, $document]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($outsider)
            ->get(route('procurements.documents.show', [$procurement, $document]))
            ->assertForbidden();
    }

    public function test_document_archive_is_scoped_to_the_current_user(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create();
        $otherProcurement = Procurement::factory()->create();

        $documentType = DocumentType::factory()->create();
        DocumentTemplate::factory()->create(['document_type_id' => $documentType->id]);

        $this->actingAs($planner)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        $teamLeader = User::factory()->teamLeader()->create();
        $this->actingAs($teamLeader)->post(route('procurements.documents.store', $otherProcurement), [
            'document_type_id' => $documentType->id,
        ]);

        $this->actingAs($planner)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('documents.data', 1));

        $this->actingAs($teamLeader)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('documents.data', 2));
    }
}
