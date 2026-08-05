<?php

namespace Tests\Feature\Procurements;

use App\Models\BudgetSource;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementMethod;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Covers metode pengadaan and sumber anggaran end to end: the form options, the
 * list filters, the generated documents and the master data screens.
 */
class ProcurementClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_create_form_offers_the_active_methods_and_budget_sources(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        ProcurementMethod::factory()->create(['name' => 'Surat Pesanan']);
        ProcurementMethod::factory()->create(['name' => 'SPK']);
        ProcurementMethod::factory()->inactive()->create(['name' => 'Metode Lama']);
        BudgetSource::factory()->create(['name' => 'AO', 'code' => 'AO']);
        BudgetSource::factory()->create(['name' => 'AI', 'code' => 'AI']);

        $this->actingAs($teamLeader)
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('procurements/create')
                ->has('options.procurementMethods', 2)
                ->has('options.budgetSources', 2),
            );
    }

    public function test_the_list_can_be_filtered_by_method_and_budget_source(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        $tender = ProcurementMethod::factory()->create(['name' => 'Tender']);
        $investment = BudgetSource::factory()->create(['name' => 'AI', 'code' => 'AI']);

        $matching = Procurement::factory()->create([
            'procurement_method_id' => $tender->id,
            'budget_source_id' => $investment->id,
        ]);
        Procurement::factory()->count(2)->create();

        $this->actingAs($teamLeader)
            ->get(route('procurements.index', ['procurement_method_id' => $tender->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('procurements.data', 1)
                ->where('procurements.data.0.number', $matching->number)
                ->where('procurements.data.0.procurement_method', 'Tender')
                ->where('procurements.data.0.budget_source', 'AI'),
            );

        $this->actingAs($teamLeader)
            ->get(route('procurements.index', ['budget_source_id' => $investment->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('procurements.data', 1));
    }

    public function test_generated_documents_carry_the_method_and_budget_source(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        $procurement = Procurement::factory()->create([
            'procurement_method_id' => ProcurementMethod::factory()->create(['name' => 'SPK'])->id,
            'budget_source_id' => BudgetSource::factory()->create(['name' => 'AO', 'code' => 'AO'])->id,
        ]);

        $documentType = DocumentType::factory()->create();
        DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'body' => '<p>{{metode_pengadaan}} / {{sumber_anggaran}}</p>',
        ]);

        $this->actingAs($teamLeader)
            ->post(route('procurements.documents.store', $procurement), [
                'document_type_id' => $documentType->id,
            ])
            ->assertRedirect();

        $document = ProcurementDocument::query()->firstOrFail();

        $this->assertStringContainsString('SPK', $document->rendered_body);
        $this->assertStringContainsString('AO', $document->rendered_body);
        $this->assertStringNotContainsString('{{metode_pengadaan}}', $document->rendered_body);
    }

    public function test_administrator_can_manage_the_new_master_data(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('master-data.procurement-methods.store'), [
                'name' => 'Penunjukan Langsung',
                'code' => 'penunjukan-langsung',
                'description' => null,
                'sort_order' => 4,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('procurement_methods', [
            'code' => 'penunjukan-langsung',
            'name' => 'Penunjukan Langsung',
        ]);

        $this->actingAs($administrator)
            ->post(route('master-data.budget-sources.store'), [
                'name' => 'AKI',
                'code' => 'aki',
                'description' => 'Anggaran Kerja Investasi',
                'sort_order' => 3,
                'is_active' => true,
            ])
            ->assertRedirect();

        // The controller upper-cases budget codes because they appear on documents.
        $this->assertDatabaseHas('budget_sources', ['code' => 'AKI']);
    }

    public function test_deactivating_a_method_keeps_existing_procurements_readable(): void
    {
        $administrator = User::factory()->administrator()->create();
        $method = ProcurementMethod::factory()->create(['name' => 'Surat Pesanan']);
        $procurement = Procurement::factory()->create(['procurement_method_id' => $method->id]);

        $this->actingAs($administrator)
            ->delete(route('master-data.procurement-methods.destroy', $method))
            ->assertRedirect();

        $this->assertSoftDeleted($method);
        $this->assertSame('Surat Pesanan', $procurement->refresh()->procurementMethod->name);
    }

    public function test_non_administrators_cannot_reach_the_new_master_data(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)
            ->get(route('master-data.procurement-methods.index'))
            ->assertForbidden();

        $this->actingAs($teamLeader)
            ->get(route('master-data.budget-sources.index'))
            ->assertForbidden();
    }

    public function test_the_seeded_reference_data_matches_the_specification(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(
            ['Surat Pesanan', 'SPK', 'Tender'],
            ProcurementMethod::query()->active()->ordered()->pluck('name')->all(),
        );

        $this->assertSame(
            ['AO', 'AI'],
            BudgetSource::query()->active()->ordered()->pluck('name')->all(),
        );
    }
}
