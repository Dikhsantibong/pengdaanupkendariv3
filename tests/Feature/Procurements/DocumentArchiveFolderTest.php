<?php

namespace Tests\Feature\Procurements;

use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The document archive files every document in one folder per contract
 * format (SPK, PJ, SPPL), with the procurements lacking a format apart.
 */
class DocumentArchiveFolderTest extends TestCase
{
    use RefreshDatabase;

    protected User $teamLeader;

    protected DocumentType $documentType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teamLeader = User::factory()->teamLeader()->create();
        $this->documentType = DocumentType::factory()->create();
        DocumentTemplate::factory()->create(['document_type_id' => $this->documentType->id]);
    }

    public function test_documents_are_filed_in_one_folder_per_contract_format(): void
    {
        $spk = ContractNumberFormat::factory()->create(['code' => 'SPK', 'sort_order' => 1]);
        $sppl = ContractNumberFormat::factory()->create(['code' => 'SPPL', 'sort_order' => 2]);

        $this->generateFor(Procurement::factory()->create(['contract_number_format_id' => $spk->id]));
        $this->generateFor(Procurement::factory()->create(['contract_number_format_id' => $spk->id]));
        $spplDocument = $this->generateFor(Procurement::factory()->create(['contract_number_format_id' => $sppl->id]));
        $this->generateFor(Procurement::factory()->create(['contract_number_format_id' => null]));

        $this->actingAs($this->teamLeader)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Without a folder every procurement is listed, as before.
                ->has('groups.data', 4)
                ->has('folders', 3)
                ->where('folders.0.key', 'SPK')
                ->where('folders.0.count', 2)
                ->where('folders.1.key', 'SPPL')
                ->where('folders.1.count', 1)
                ->where('folders.2.key', 'lainnya')
                ->where('folders.2.count', 1));

        $this->actingAs($this->teamLeader)
            ->get(route('documents.index', ['folder' => 'SPPL']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.folder', 'SPPL')
                ->has('groups.data', 1)
                ->where('groups.data.0.document_count', 1));

        // The SPPL work's folder opens on its own page with that document.
        $this->actingAs($this->teamLeader)
            ->get(route('documents.procurement', [ProcurementDocument::query()->findOrFail($spplDocument)->procurement_id, 'folder' => 'SPPL']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('documents/procurement')
                ->has('documents', 1)
                ->where('documents.0.id', $spplDocument)
                ->where('back.folder', 'SPPL'));

        $this->actingAs($this->teamLeader)
            ->get(route('documents.index', ['folder' => 'lainnya']))
            ->assertInertia(fn (Assert $page) => $page->has('groups.data', 1));
    }

    public function test_folders_only_count_the_documents_the_user_may_see(): void
    {
        $spk = ContractNumberFormat::factory()->create(['code' => 'SPK']);
        $planner = User::factory()->planner()->create();

        $this->generateFor(Procurement::factory()->plannedBy($planner)->create(['contract_number_format_id' => $spk->id]));
        $this->generateFor(Procurement::factory()->create(['contract_number_format_id' => $spk->id]));

        $this->actingAs($planner)
            ->get(route('documents.index', ['folder' => 'SPK']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('folders.0.count', 1)
                ->has('groups.data', 1));
    }

    public function test_documents_are_grouped_per_procurement(): void
    {
        $first = Procurement::factory()->create(['name' => 'Pengadaan Trafo']);
        $second = Procurement::factory()->create(['name' => 'Pengadaan Pompa']);
        $otherType = DocumentType::factory()->create();
        DocumentTemplate::factory()->create(['document_type_id' => $otherType->id]);

        $this->generateFor($first);
        $this->generateFor($first, $otherType);
        $this->generateFor($second);

        $this->actingAs($this->teamLeader)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups.data', 2)
                ->where('groups.data', fn ($groups) => collect($groups)
                    ->mapWithKeys(fn (array $group): array => [$group['name'] => $group['document_count']])
                    ->sortKeys()
                    ->all() === ['Pengadaan Pompa' => 1, 'Pengadaan Trafo' => 2]));

        // A search on the work's name keeps only that work.
        $this->actingAs($this->teamLeader)
            ->get(route('documents.index', ['search' => 'Trafo']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups.data', 1)
                ->where('groups.data.0.id', $first->id)
                ->where('groups.data.0.document_count', 2));

        // The type filter keeps only the matching documents in each group.
        $this->actingAs($this->teamLeader)
            ->get(route('documents.index', ['document_type_id' => $otherType->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups.data', 1)
                ->where('groups.data.0.document_count', 1));
    }

    public function test_a_work_folder_lists_its_documents_newest_first(): void
    {
        $procurement = Procurement::factory()->create();
        $otherType = DocumentType::factory()->create();
        DocumentTemplate::factory()->create(['document_type_id' => $otherType->id]);

        $first = $this->generateFor($procurement);
        $this->travel(1)->minute();
        $second = $this->generateFor($procurement, $otherType);

        $this->actingAs($this->teamLeader)
            ->get(route('documents.procurement', $procurement))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('procurement.id', $procurement->id)
                ->where('documents', fn ($documents) => collect($documents)->pluck('id')->all() === [$second, $first])
                ->where('back', []));

        // The archive's type filter narrows the folder too.
        $this->actingAs($this->teamLeader)
            ->get(route('documents.procurement', [$procurement, 'document_type_id' => $otherType->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 1)
                ->where('documents.0.id', $second));
    }

    public function test_a_work_folder_is_closed_to_who_may_not_see_the_work(): void
    {
        $procurement = Procurement::factory()->create();
        $this->generateFor($procurement);

        $this->actingAs(User::factory()->planner()->create())
            ->get(route('documents.procurement', $procurement))
            ->assertForbidden();
    }

    public function test_the_preview_opens_the_pdf_inline(): void
    {
        $procurement = Procurement::factory()->create();
        $document = $this->generateFor($procurement);

        $response = $this->actingAs($this->teamLeader)
            ->get(route('procurements.documents.show', [$procurement, $document, 'preview' => 1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_an_empty_retired_format_has_no_folder(): void
    {
        ContractNumberFormat::factory()->create(['code' => 'SPK']);
        ContractNumberFormat::factory()->inactive()->create(['code' => 'LAMA']);

        $this->actingAs($this->teamLeader)
            ->get(route('documents.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('folders', 1)
                ->where('folders.0.key', 'SPK'));
    }

    /**
     * Generate one archived document for the procurement.
     */
    protected function generateFor(Procurement $procurement, ?DocumentType $documentType = null): int
    {
        $this->actingAs($this->teamLeader)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => ($documentType ?? $this->documentType)->id,
            ])
            ->assertSessionHasNoErrors();

        return (int) $procurement->documents()->latest('id')->value('id');
    }
}
