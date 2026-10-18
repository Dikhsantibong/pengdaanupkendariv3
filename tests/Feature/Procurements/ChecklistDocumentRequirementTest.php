<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\BeritaAcaraTemplateSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SpplDocumentTemplateSeeder;
use Database\Seeders\StandardDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A checklist step that produces a document cannot be ticked until the signed
 * copy of that document has been filed against the procurement.
 */
class ChecklistDocumentRequirementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The planning steps that must carry a document.
     *
     * @return array<int, string>
     */
    public static function planningSteps(): array
    {
        return [
            'Nota Dinas Usulan',
            'TOR / KAK',
            'RAB (Rencana Anggaran Biaya)',
            // Penawaran: each calon mitra carries its own quotation scan.
            'CSMS (Sertifikat)',
            'Nota Dinas ke Pengadaan',
            'HPE (Harga Perkiraan Engineer)',
            'UPB',
            'RKS (Rencana Kerja dan Syarat)',
        ];
    }

    /**
     * The execution steps that must carry a document.
     *
     * @return array<int, string>
     */
    public static function executionSteps(): array
    {
        return [
            'Penyusunan HPS',
            'Proses SMART SCM & Berita Acara',
            'Penyusunan Kontrak',
            'Jaminan Bank',
            'Kontrak',
            // Amandemen has its own tab now (procurements.amendments.*).
            'Masa Pemeliharaan',
            // SPPL.
            'BA Negosiasi',
            'Surat Pesanan',
            'Lampiran Surat Pesanan',
        ];
    }

    public function test_exactly_the_listed_steps_carry_a_document(): void
    {
        $this->seed(MasterDataSeeder::class);

        foreach (self::planningSteps() as $name) {
            $item = ChecklistItem::query()
                ->forStage(ProcurementStage::Perencanaan)
                ->where('name', $name)
                ->firstOrFail();

            $this->assertTrue($item->requiresDocument(), "[{$name}] seharusnya punya dokumen.");
        }

        foreach (self::executionSteps() as $name) {
            $item = ChecklistItem::query()
                ->forStage(ProcurementStage::Pelaksanaan)
                ->where('name', $name)
                ->firstOrFail();

            $this->assertTrue($item->requiresDocument(), "[{$name}] seharusnya punya dokumen.");
        }

        // Everything else carries no paperwork: a plain tick, or a step that
        // asks for data (the SPPL rekening, rentang waktu and garansi).
        $withoutDocument = ChecklistItem::query()
            ->whereDoesntHave('documentTypes')
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'Checklist Perencanaan',
            'Evaluasi Dokumen',
            'Inisiasi SMART SCM',
            'Masa Garansi',
            'PR / RO',
            'Pelaksana',
            // The calon mitra carry their own quotation scans.
            'Penawaran',
            'Rentang Waktu',
            'Rentang Waktu Pelaksanaan',
            // Keuangan checks the invoice papers by hand.
            'Verifikasi Dokumen Tagihan',
        ], $withoutDocument);
    }

    public function test_a_step_cannot_be_ticked_before_the_signed_document_is_filed(): void
    {
        [$procurement, $planner, $checklist] = $this->planningStep('HPE (Harga Perkiraan Engineer)');

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertSessionHasErrors('is_completed');

        $this->assertFalse($checklist->refresh()->is_completed);
    }

    public function test_generating_the_document_is_not_enough_on_its_own(): void
    {
        [$procurement, $planner, $checklist, $documentType] = $this->planningStep('HPE (Harga Perkiraan Engineer)');

        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
            'body' => '<p>{{nama_pengadaan}}</p>',
        ]);

        $this->actingAs($planner)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        $this->assertDatabaseCount('procurement_documents', 1);

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertSessionHasErrors('is_completed');

        $this->assertFalse($checklist->refresh()->is_completed);
    }

    public function test_the_step_can_be_ticked_once_the_signed_scan_is_uploaded(): void
    {
        Storage::fake('local');

        [$procurement, $planner, $checklist, $documentType] = $this->planningStep('HPE (Harga Perkiraan Engineer)');

        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'procurement_method_id' => null,
            'body' => '<p>{{nama_pengadaan}}</p>',
        ]);

        $this->actingAs($planner)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $documentType->id,
        ]);

        $document = ProcurementDocument::query()->firstOrFail();

        $this->actingAs($planner)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [UploadedFile::fake()->create('tor-ttd.pdf', 100, 'application/pdf')]],
        );

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($checklist->refresh()->is_completed);
    }

    public function test_a_step_without_a_document_is_ticked_freely(): void
    {
        [$procurement, $planner] = $this->planningStep('HPE (Harga Perkiraan Engineer)');

        $plain = ChecklistItem::query()
            ->active()
            ->forStage(ProcurementStage::Perencanaan)
            ->whereDoesntHave('documentTypes')
            // A step asking for data is not a plain tick.
            ->whereNull('input_kind')
            ->firstOrFail();

        $checklist = $procurement->checklists()
            ->where('checklist_item_id', $plain->id)
            ->firstOrFail();

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($checklist->refresh()->is_completed);
    }

    public function test_an_optional_step_is_ticked_without_its_document(): void
    {
        [$procurement, $planner, $checklist] = $this->planningStep('CSMS (Sertifikat)');

        $this->assertTrue($checklist->checklistItem->is_optional);
        $this->assertTrue($checklist->checklistItem->requiresDocument());

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($checklist->refresh()->is_completed);
    }

    public function test_unticking_a_step_never_needs_a_document(): void
    {
        [$procurement, $planner, $checklist] = $this->planningStep('HPE (Harga Perkiraan Engineer)');

        $checklist->update(['is_completed' => true, 'completed_at' => now()]);

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($checklist->refresh()->is_completed);
    }

    public function test_a_signed_document_of_another_type_does_not_satisfy_the_step(): void
    {
        Storage::fake('local');

        [$procurement, $planner, $checklist] = $this->planningStep('HPE (Harga Perkiraan Engineer)');

        $otherType = DocumentType::query()->where('code', 'upb')->firstOrFail();
        DocumentTemplate::factory()->create([
            'document_type_id' => $otherType->id,
            'procurement_method_id' => null,
            'body' => '<p>lain</p>',
        ]);

        $this->actingAs($planner)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $otherType->id,
        ]);

        $other = ProcurementDocument::query()->firstOrFail();

        $this->actingAs($planner)->post(
            route('procurements.documents.signed.store', [$procurement, $other]),
            ['files' => [UploadedFile::fake()->create('rab.pdf', 100, 'application/pdf')]],
        );

        $this->actingAs($planner)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertSessionHasErrors('is_completed');
    }

    public function test_the_detail_screen_marks_which_steps_need_a_document(): void
    {
        [$procurement, $planner] = $this->planningStep('HPE (Harga Perkiraan Engineer)');

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(function ($page) {
                $rows = collect($page->toArray()['props']['checklists']['perencanaan']);

                $hpe = $rows->firstWhere('name', 'HPE (Harga Perkiraan Engineer)');
                $plain = $rows->firstWhere('name', 'Inisiasi SMART SCM');

                $this->assertNull($rows->firstWhere('name', 'Checklist Perencanaan'));

                $this->assertCount(1, $hpe['documents']);
                $this->assertFalse($hpe['documents'][0]['is_signed']);
                $this->assertSame('HPE (Harga Perkiraan Engineer)', $hpe['documents'][0]['type_name']);
                $this->assertSame([], $plain['documents']);
            });
    }

    public function test_an_administrator_can_take_the_document_requirement_off_a_step(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Perencanaan)
            ->where('name', 'CSMS (Sertifikat)')
            ->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('master-data.checklist-items.update', $item), [
                'stage' => ProcurementStage::Perencanaan->value,
                'name' => $item->name,
                'description' => $item->description,
                'is_optional' => $item->is_optional,
                'sort_order' => $item->sort_order,
                'is_active' => true,
                'document_type_ids' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($item->refresh()->documentTypes->isEmpty());
        $this->assertFalse($item->requiresDocument());
    }

    public function test_the_smart_scm_step_requires_its_document_and_all_six_berita_acara(): void
    {
        $this->seed(MasterDataSeeder::class);

        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Pelaksanaan)
            ->where('name', 'Proses SMART SCM & Berita Acara')
            ->firstOrFail();

        $this->assertSame([
            'proses-smart-scm',
            'ba-aanwijzing',
            'lampiran-bapp',
            'ba-evaluasi-teknis',
            'ba-evaluasi-harga',
            'ba-hasil-evaluasi',
            'ba-klarifikasi',
        ], $item->documentTypes->pluck('code')->all());
    }

    public function test_the_kontrak_step_asks_for_the_spk_and_its_articles(): void
    {
        $this->seed(MasterDataSeeder::class);

        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Pelaksanaan)
            ->where('name', 'Kontrak')
            ->firstOrFail();

        // The SPK table, then its Syarat & Ketentuan (Pasal), asked for
        // under the SPK format only.
        $this->assertSame(['spk', 'spk-pasal'], $item->documentTypes->pluck('code')->all());
        $this->assertSame(
            ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            $item->documentTypes->firstWhere('code', 'spk-pasal')?->contract_number_format_id,
        );
    }

    public function test_a_multi_document_step_needs_every_signed_copy(): void
    {
        Storage::fake('local');

        $this->seed(MasterDataSeeder::class);
        $this->seed(BeritaAcaraTemplateSeeder::class);

        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->executedBy($executor)->create([
            'procurement_method_id' => null,
            'planning_approval_state' => PlanningApprovalState::Disetujui,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Pelaksanaan)
            ->where('name', 'Proses SMART SCM & Berita Acara')
            ->firstOrFail();

        $checklist = $procurement->checklists()
            ->where('checklist_item_id', $item->id)
            ->firstOrFail();

        // File all but the last, then check the step is still refused and the
        // message names the one that is missing.
        foreach ($item->documentTypes->slice(0, -1) as $type) {
            // The SMART SCM document is only ever uploaded.
            if ($type->upload_only) {
                $this->actingAs($executor)->post(route('procurements.documents.upload', $procurement), [
                    'document_type_id' => $type->id,
                    'files' => [UploadedFile::fake()->create('scm.pdf', 50, 'application/pdf')],
                ]);

                continue;
            }

            $this->actingAs($executor)->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $type->id,
            ]);

            $document = ProcurementDocument::query()
                ->where('document_type_id', $type->id)
                ->firstOrFail();

            $this->actingAs($executor)->post(
                route('procurements.documents.signed.store', [$procurement, $document]),
                ['files' => [UploadedFile::fake()->create('ttd.pdf', 50, 'application/pdf')]],
            );
        }

        $response = $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertSessionHasErrors('is_completed');

        $this->assertStringContainsString(
            $item->documentTypes->last()->name,
            session('errors')->first('is_completed'),
        );

        // File the last one and the step goes through.
        $last = $item->documentTypes->last();

        $this->actingAs($executor)->post(route('procurements.documents.store', $procurement), [
            'document_type_id' => $last->id,
        ]);

        $document = ProcurementDocument::query()
            ->where('document_type_id', $last->id)
            ->firstOrFail();

        $this->actingAs($executor)->post(
            route('procurements.documents.signed.store', [$procurement, $document]),
            ['files' => [UploadedFile::fake()->create('nego.pdf', 50, 'application/pdf')]],
        );

        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $checklist]), [
                'is_completed' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($checklist->refresh()->is_completed);

        unset($response);
    }

    public function test_every_linked_document_type_has_a_working_template(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(BeritaAcaraTemplateSeeder::class);
        $this->seed(KontrakTemplateSeeder::class);
        $this->seed(StandardDocumentTemplateSeeder::class);
        $this->seed(SpplDocumentTemplateSeeder::class);

        // Uploaded documents are filed as scans and need no template.
        $linked = DocumentType::query()
            ->whereHas('checklistItems')
            ->where('upload_only', false)
            ->get();

        $this->assertGreaterThan(0, $linked->count());

        // A template may serve one contract format (the SPK table, the
        // Surat Perjanjian); any format having one is enough.
        $formats = [null, ...ContractNumberFormat::query()->pluck('id')->all()];

        foreach ($linked as $type) {
            $this->assertTrue(
                collect($formats)->contains(fn (?int $format): bool => DocumentTemplate::resolveFor($type->id, null, null, $format) !== null),
                "Jenis dokumen [{$type->code}] belum punya template aktif.",
            );
        }
    }

    /**
     * A planning step of a procurement, ready to be ticked.
     *
     * @return array{0: Procurement, 1: User, 2: ProcurementChecklist, 3: DocumentType}
     */
    protected function planningStep(string $name): array
    {
        $this->seed(MasterDataSeeder::class);

        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create([
            'procurement_method_id' => null,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        $item = ChecklistItem::query()
            ->forStage(ProcurementStage::Perencanaan)
            ->where('name', $name)
            ->firstOrFail();

        $checklist = $procurement->checklists()
            ->where('checklist_item_id', $item->id)
            ->firstOrFail();

        return [$procurement, $planner, $checklist, $item->documentTypes->firstOrFail()];
    }
}
