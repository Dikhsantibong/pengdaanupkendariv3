<?php

namespace Tests\Feature\Procurements;

use App\Enums\ChecklistInput;
use App\Enums\PlanningApprovalState;
use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use App\Support\TenderBeritaAcara;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The "Proses SMART SCM & Berita Acara" step asks for the number and date of
 * each document on the step, falling back to one step-level pair if no
 * documents are attached.
 */
class SmartScmBeritaAcaraTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_step_asks_for_a_ba_number_and_date_per_document(): void
    {
        [$procurement, $executor, $step] = $this->step();

        $this->assertSame(ChecklistInput::BeritaAcara, $step->checklistItem->input_kind);

        $types = $step->checklistItem
            ->load('documentTypes.selectionMethods')
            ->documentTypesFor($procurement)
            ->values();

        $this->assertCount(7, $types);

        $this->actingAs($executor)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page->where('checklists.pelaksanaan', fn ($rows) => collect($rows)
                ->contains(fn (array $row): bool => $row['id'] === $step->id
                    && $row['input']['kind'] === 'berita_acara'
                    && $row['input']['is_filled'] === false
                    && count($row['input']['values']['berita_acaras']) === $types->count()
                    && $row['input']['values']['berita_acaras'][0]['document_type_id'] === $types[0]->id)));

        // Validation rejects incomplete pairs, invalid dates, and foreign document types.
        $foreignTypeId = DocumentType::query()->where('code', 'tor')->value('id');

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'berita_acaras' => [
                    ['document_type_id' => $types[0]->id, 'ba_number' => '0001/SCM/612/UPKD/2026', 'ba_date' => ''],
                    ['document_type_id' => $types[1]->id, 'ba_number' => '', 'ba_date' => 'bukan tanggal'],
                ],
            ])
            ->assertSessionHasErrors(['berita_acaras.0.ba_date', 'berita_acaras.1.ba_number', 'berita_acaras.1.ba_date']);

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'berita_acaras' => [
                    ['document_type_id' => $foreignTypeId, 'ba_number' => '0001/TOR/2026', 'ba_date' => '2026-11-10'],
                ],
            ])
            ->assertSessionHasErrors(['berita_acaras.0.document_type_id']);

        // Partial progress saves cleanly so users can fill BAs one by one.
        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'berita_acaras' => [
                    ['document_type_id' => $types[0]->id, 'ba_number' => '0001/SCM/612/UPKD/2026', 'ba_date' => '2026-11-08'],
                    ['document_type_id' => $types[1]->id, 'ba_number' => '', 'ba_date' => ''],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(ChecklistInput::BeritaAcara->isFilledOn($procurement->refresh(), $step->checklistItem));

        // Now fill every document on the step, each with its own number and date.
        $entries = $types->map(fn (DocumentType $type, int $index): array => [
            'document_type_id' => $type->id,
            'ba_number' => sprintf('%04d/BA/612/UPKD/2026', $index + 1),
            'ba_date' => sprintf('2026-11-%02d', 10 + $index),
        ])->all();

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'berita_acaras' => $entries,
            ])
            ->assertSessionHasNoErrors();

        $procurement->refresh()->load('beritaAcaras');

        $this->assertTrue(ChecklistInput::BeritaAcara->isFilledOn($procurement, $step->checklistItem));
        $this->assertSame('0002/BA/612/UPKD/2026', $procurement->beritaAcaraFor($types[1]->id)?->ba_number);
        $this->assertSame('2026-11-11', $procurement->beritaAcaraFor($types[1]->id)?->ba_date?->toDateString());
        $this->assertSame('0004/BA/612/UPKD/2026', $procurement->beritaAcaraFor($types[3]->id)?->ba_number);

        // Generating/printing each document resolves to its own BA number & date.
        $generator = app(DocumentGenerator::class);

        $firstValues = $generator->placeholderValues($procurement, null, $types[1]->id);
        $fourthValues = $generator->placeholderValues($procurement, null, $types[3]->id);

        $this->assertSame('0002/BA/612/UPKD/2026', $firstValues['nomor_ba_smart_scm']);
        $this->assertSame('11 November 2026', $firstValues['tanggal_ba_smart_scm']);
        $this->assertSame('0004/BA/612/UPKD/2026', $fourthValues['nomor_ba_smart_scm']);
        $this->assertSame('13 November 2026', $fourthValues['tanggal_ba_smart_scm']);

        $template = DocumentTemplate::factory()->for($types[3], 'documentType')->create([
            'body' => '<p>Nomor {{nomor_ba_smart_scm}} tanggal {{tanggal_ba_smart_scm}}</p>',
            'is_active' => true,
        ]);

        $rendered = $generator->render($template, $procurement);
        $this->assertStringContainsString('0004/BA/612/UPKD/2026', $rendered);
        $this->assertStringContainsString('13 November 2026', $rendered);
    }

    public function test_the_step_falls_back_to_single_ba_number_and_date_when_it_has_no_documents(): void
    {
        [$procurement, $executor, $step] = $this->step();

        $step->checklistItem->documentTypes()->detach();

        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $step]), ['is_completed' => true])
            ->assertSessionHasErrors(['is_completed' => 'Lengkapi isian nomor & tanggal BA (Proses SMART SCM & Berita Acara) sebelum menandai tahapan ini selesai.']);

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), ['smart_scm_ba_number' => '', 'smart_scm_ba_date' => 'bukan tanggal'])
            ->assertSessionHasErrors(['smart_scm_ba_number', 'smart_scm_ba_date']);

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'smart_scm_ba_number' => '0012/BAPP/612/UPKD/2026',
                'smart_scm_ba_date' => '2026-11-10',
            ])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame('0012/BAPP/612/UPKD/2026', $procurement->smart_scm_ba_number);
        $this->assertSame('2026-11-10', $procurement->smart_scm_ba_date?->toDateString());
        $this->assertTrue(ChecklistInput::BeritaAcara->isFilledOn($procurement, $step->checklistItem));

        $values = app(DocumentGenerator::class)->placeholderValues($procurement);
        $this->assertSame('0012/BAPP/612/UPKD/2026', $values['nomor_ba_smart_scm']);
        $this->assertSame('10 November 2026', $values['tanggal_ba_smart_scm']);
    }

    /**
     * An SPK job in execution with its Proses SMART SCM step.
     *
     * @return array{0: Procurement, 1: User, 2: ProcurementChecklist}
     */
    protected function step(): array
    {
        $this->seed(MasterDataSeeder::class);

        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            'executor_id' => $executor->id,
            'planning_approval_state' => PlanningApprovalState::Disetujui,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        $step = $procurement->checklists()
            ->whereHas('checklistItem', fn ($query) => $query->where('name', TenderBeritaAcara::STEP_NAME))
            ->with('checklistItem')
            ->firstOrFail();

        return [$procurement, $executor, $step];
    }
}
