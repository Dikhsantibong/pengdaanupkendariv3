<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementContractItem;
use App\Models\ProcurementDocument;
use App\Models\ProcurementPartnerCandidate;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Evaluasi Harga (formerly the RAB step): each calon mitra's unit price
 * per item, filled in the Rincian Item, compared against the HPE.
 */
class EvaluasiHargaTest extends TestCase
{
    use RefreshDatabase;

    protected User $planner;

    protected Procurement $procurement;

    protected ProcurementPartnerCandidate $green;

    protected ProcurementPartnerCandidate $sultra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        $this->planner = User::factory()->planner()->create();
        $this->procurement = Procurement::factory()->plannedBy($this->planner)->create();
        $this->green = $this->procurement->partnerCandidates()->create(['name' => 'Mitra Hijau. PT', 'sort_order' => 1]);
        $this->sultra = $this->procurement->partnerCandidates()->create(['name' => 'Sultra Alam Perkasa. PT', 'sort_order' => 2]);
    }

    public function test_the_rab_step_is_now_the_generated_evaluasi_harga(): void
    {
        $type = DocumentType::query()->where('code', 'rab')->firstOrFail();

        $this->assertSame('Evaluasi Harga', $type->name);
        $this->assertFalse($type->upload_only);

        $step = ChecklistItem::query()->forStage(ProcurementStage::Perencanaan)->where('name', 'Evaluasi Harga')->sole();
        $this->assertTrue($step->documentTypes()->whereKey($type->id)->exists());
        $this->assertFalse(ChecklistItem::query()->where('name', 'RAB (Rencana Anggaran Biaya)')->exists());

        $body = $this->evaluation()->rendered_body;
        $this->assertStringContainsString('EVALUASI HARGA PERKIRAAN ENJINIRING', $body);
        $this->assertStringContainsString('Harga Lalu', $body);
        $this->assertStringContainsString('RKAP Budget', $body);
    }

    public function test_the_editor_gives_each_calon_mitra_a_price_column(): void
    {
        $document = $this->evaluation();

        $this->actingAs($this->planner)
            ->get(route('procurements.documents.edit', [$this->procurement, $document]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('itemPartners', [
                    ['id' => $this->green->id, 'name' => 'Mitra Hijau. PT'],
                    ['id' => $this->sultra->id, 'name' => 'Sultra Alam Perkasa. PT'],
                ])
                ->where('itemLayout', 'rab')
                // No item yet: the work's own line, as the template prints it.
                ->where('items.0.description', $this->procurement->name)
                ->where('items.0.id', null)
                ->where('items.0.unit', 'LOT'));
    }

    public function test_the_quotes_are_kept_and_drawn_in_their_columns(): void
    {
        $document = $this->evaluation();
        $foreign = Procurement::factory()->create()->partnerCandidates()->create(['name' => 'Mitra Lain', 'sort_order' => 1]);

        $this->save($document, [
            ['id' => null, 'kind' => 'item', 'description' => "BIAYA PEMUSNAHAN (B105D)\nHASIL SEPARASI OIL TRAP", 'volume' => 1, 'unit' => 'Kg', 'price_before' => null, 'unit_price' => null,
                'quotes' => [$this->green->id => 8500, $this->sultra->id => 7500, $foreign->id => 1]],
            ['id' => null, 'kind' => 'group', 'description' => 'Biaya Transportasi Darat'],
            ['id' => null, 'kind' => 'item', 'description' => 'PLTD Raha', 'part_number' => 'SC-7', 'volume' => 1, 'unit' => 'Ls', 'price_before' => 12500000, 'unit_price' => null,
                'previous_po_number' => 'PO-4471', 'previous_po_date' => '12 Jan 2025', 'previous_supplier' => 'PT Lama', 'previous_price' => 11000000,
                'quotes' => [$this->green->id => 13000000, $this->sultra->id => null]],
        ])->assertSessionHasNoErrors();

        [$first, $second] = $this->procurement->contractItems()->with('quotations')->get()->all();

        // Only this job's calon mitra; a blank price is no quote.
        $this->assertEquals(['8500.00', '7500.00'], $first->quotations->sortBy('procurement_partner_candidate_id')->pluck('unit_price')->all());
        $this->assertCount(1, $second->quotations);
        // No HPE price of its own: the lowest quote. One typed in is kept.
        $this->assertEquals(7500, (float) $first->price_before);
        $this->assertEquals(12500000, (float) $second->price_before);

        $body = $document->refresh()->rendered_body;
        $this->assertStringContainsString('<th rowspan="2">MITRA HIJAU. PT</th><th rowspan="2">SULTRA ALAM PERKASA. PT</th>', $body);
        $this->assertStringContainsString('<td>0,00</td><td>8.500,00</td><td>7.500,00</td><td class="right">7.500,00</td>', $body);
        $this->assertStringContainsString("BIAYA PEMUSNAHAN (B105D)<br>\nHASIL SEPARASI OIL TRAP", $body);
        $this->assertStringContainsString('<td><b>Biaya Transportasi Darat</b></td>', $body);
        $this->assertStringContainsString('<td>SC-7</td>', $body);
        // The last purchase under "Harga"; none prints 0,00 (above).
        $this->assertStringContainsString('<td>PO-4471</td><td>12 Jan 2025</td><td>PT Lama</td><td>11.000.000,00</td><td>13.000.000,00</td><td></td><td class="right">12.500.000,00</td>', $body);
        // The template's empty rows, one cell per column.
        $this->assertSame(2, substr_count($body, '<tr class="blank"><td>&nbsp;</td>'.str_repeat('<td></td>', 9).'</tr>'));

        // Saved from another editor (no quotes sent), the quotes stay.
        $hpe = app(DocumentGenerator::class)->generate($this->procurement, DocumentType::query()->where('code', 'hpe')->firstOrFail(), $this->planner);
        $rows = collect($this->procurement->contractItems()->get())->map(fn (ProcurementContractItem $item): array => [
            'id' => $item->id, 'kind' => 'item', 'description' => $item->description, 'volume' => 1, 'unit' => $item->unit,
            'price_before' => $item->price_before, 'unit_price' => null,
        ])->all();
        $this->save($hpe, $rows)->assertSessionHasNoErrors();
        $this->assertSame(3, ProcurementContractItem::query()->withCount('quotations')->get()->sum('quotations_count'));
        // ... and so does the last purchase.
        $this->assertSame('PO-4471', ProcurementContractItem::query()->where('description', 'PLTD Raha')->value('previous_po_number'));
    }

    public function test_a_new_calon_mitra_gets_its_column_in_the_document(): void
    {
        $document = $this->evaluation();
        $this->save($document, [
            ['id' => null, 'kind' => 'item', 'description' => 'Oli', 'volume' => 1, 'unit' => 'Ltr', 'quotes' => [$this->green->id => 100]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->planner)
            ->post(route('procurements.partner-candidates.store', $this->procurement), ['name' => 'Bumi Raya. CV'])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('<th rowspan="2">BUMI RAYA. CV</th>', $document->refresh()->rendered_body);
    }

    public function test_the_preview_draws_the_unsaved_quotes(): void
    {
        $document = $this->evaluation();

        $this->actingAs($this->planner)
            ->postJson(route('procurements.documents.items-preview', [$this->procurement, $document]), [
                'rows' => [
                    ['id' => null, 'kind' => 'item', 'description' => 'Oli', 'volume' => 2, 'unit' => 'Ltr', 'quotes' => [$this->green->id => 300, $this->sultra->id => 250]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('body', fn (string $body): bool => str_contains($body, '<td>300,00</td><td>250,00</td><td class="right">250,00</td>'));
    }

    protected function evaluation(): ProcurementDocument
    {
        return app(DocumentGenerator::class)->generate($this->procurement, DocumentType::query()->where('code', 'rab')->firstOrFail(), $this->planner);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function save(ProcurementDocument $document, array $rows): TestResponse
    {
        return $this->actingAs($this->planner)
            ->put(route('procurements.documents.update', [$this->procurement, $document]), [
                'title' => $document->title,
                'body' => $document->rendered_body,
                'item_rows' => $rows,
            ]);
    }
}
