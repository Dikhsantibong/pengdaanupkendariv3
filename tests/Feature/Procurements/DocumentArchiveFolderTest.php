<?php

namespace Tests\Feature\Procurements;

use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
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
                // Without a folder every document is listed, as before.
                ->has('documents.data', 4)
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
                ->has('documents.data', 1)
                ->where('documents.data.0.id', $spplDocument));

        $this->actingAs($this->teamLeader)
            ->get(route('documents.index', ['folder' => 'lainnya']))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1));
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
                ->has('documents.data', 1));
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
    protected function generateFor(Procurement $procurement): int
    {
        $this->actingAs($this->teamLeader)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $this->documentType->id,
            ])
            ->assertSessionHasNoErrors();

        return (int) $procurement->documents()->latest('id')->value('id');
    }
}
