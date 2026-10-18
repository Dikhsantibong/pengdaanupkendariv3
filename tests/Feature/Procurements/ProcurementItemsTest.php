<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementContractItem;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\ItemTableRenderer;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Rincian Item, kept in the document editor: the item tables of the
 * documents are drawn from it instead of being typed cell by cell.
 */
class ProcurementItemsTest extends TestCase
{
    use RefreshDatabase;

    protected User $executor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        $this->executor = User::factory()->executor()->create();
    }

    public function test_a_group_row_typed_with_a_volume_is_a_heading_not_an_item(): void
    {
        $html = '<table><thead><tr><th>HARGA SEBELUM NEGO</th></tr></thead><tbody>'
            .'<tr><td>1</td><td>Material &amp; Tools Tangki 50 KL</td><td>1</td><td></td><td></td><td>0</td><td></td><td>0</td></tr>'
            .'<tr><td>2</td><td>- Sarung tangan</td><td>40</td><td>Pcs</td><td>25.000</td><td>1.000.000</td><td>23.750</td><td>950.000</td></tr>'
            .'<tr><td>3</td><td>- Masker</td><td>4</td><td>Dos</td><td>50.000</td><td>200.000</td><td>47.500</td><td>190.000</td></tr>'
            .'<tr><td colspan="4">TOTAL HARGA</td><td colspan="2">1.200.000</td><td colspan="2">1.140.000</td></tr>'
            .'</tbody></table>';

        $items = app(DocumentGenerator::class)->parseNegotiationItems($html);

        $this->assertCount(2, $items);
        $this->assertSame(['- Sarung tangan', '- Masker'], array_column($items, 'name'));
        $this->assertSame(['Material & Tools Tangki 50 KL', 'Material & Tools Tangki 50 KL'], array_column($items, 'group'));
        $this->assertEquals(1140000, array_sum(array_column($items, 'total_after')));
    }

    public function test_saving_the_items_redraws_every_item_table(): void
    {
        [$procurement, $baNego, $suratPesanan] = $this->spplWithDocuments();

        $this->saveFromEditor($procurement, $baNego, $this->rows())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, $procurement->contractItems()->count());
        $this->assertSame('Material & Tools Tangki 50 KL', $procurement->contractItems()->value('group_name'));
        $this->assertEquals(1140000, (float) $procurement->refresh()->value_after_negotiation);

        // The BA Negosiasi: a bold lettered heading, the items numbered under
        // it, and the totals worked out.
        $body = $baNego->refresh()->rendered_body;
        $this->assertStringContainsString('font-weight: bold;">A</td>', $body);
        $this->assertStringContainsString('Material &amp; Tools Tangki 50 KL', $body);
        $this->assertStringContainsString('Sarung tangan', $body);
        $this->assertStringContainsString('>23.750<', $body);
        $this->assertStringContainsString('>1.200.000<', $body);
        $this->assertStringContainsString('>1.140.000<', $body);
        $this->assertStringContainsString('HARGA SEBELUM NEGO', $body);

        // The heading never counts as an item on the Surat Pesanan.
        $sp = $suratPesanan->refresh()->rendered_body;
        $this->assertStringContainsString('Material &amp; Tools Tangki 50 KL', $sp);
        $this->assertStringContainsString('Rp 23.750', $sp);
        $this->assertStringContainsString('Rp 1.140.000', $sp);
        $this->assertStringNotContainsString('Rp 0<', $sp);

        // Reading the redrawn table back gives the same items.
        $this->assertSame(
            ['Sarung tangan', 'Masker'],
            array_column(app(DocumentGenerator::class)->parseNegotiationItems($body), 'name'),
        );
    }

    public function test_saving_again_keeps_the_items_rows(): void
    {
        [$procurement, $baNego] = $this->spplWithDocuments();

        $this->saveFromEditor($procurement, $baNego, $this->rows());
        $ids = $procurement->contractItems()->pluck('id')->all();

        $rows = $this->rows();
        $rows[1]['id'] = $ids[0];
        $rows[2]['id'] = $ids[1];
        $rows[2]['unit_price'] = 45000;

        $this->saveFromEditor($procurement, $baNego->refresh(), $rows);

        $this->assertSame($ids, $procurement->contractItems()->pluck('id')->all());
        $this->assertEquals(45000, (float) $procurement->contractItems()->whereKey($ids[1])->value('unit_price'));
    }

    public function test_only_who_works_on_the_stage_keeps_the_items(): void
    {
        $planner = User::factory()->planner()->create();
        $planning = Procurement::factory()->plannedBy($planner)->create();

        $this->assertTrue($planner->can('manageItems', $planning));
        $this->assertFalse($this->executor->can('manageItems', $planning));

        // Once planning is approved the executor keeps them, not the planner.
        $approved = Procurement::factory()->planningApproved()->plannedBy($planner)->executedBy($this->executor)->create();

        $this->assertFalse($planner->can('manageItems', $approved));
        $this->assertTrue($this->executor->can('manageItems', $approved));
        $this->assertFalse(User::factory()->executor()->create()->can('manageItems', $approved));

        // Once at Keuangan nobody changes them any more.
        $approved->forceFill(['finance_submitted_at' => now()])->save();
        $this->assertFalse($this->executor->can('manageItems', $approved));
    }

    public function test_the_editor_opens_on_the_rows_of_the_document_table(): void
    {
        [$procurement, $baNego] = $this->spplWithDocuments();

        $this->actingAs($this->executor)
            ->get(route('procurements.documents.edit', [$procurement, $baNego]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('procurements/document-editor')
                ->where('canManageItems', true)
                ->has('items', 1)
                // Read from the table, not saved yet.
                ->where('items.0.id', null)
                ->where('items.0.description', $procurement->name)
                ->where('items.0.unit', 'Lot'));

        // A document without an item table has no Rincian Item tab.
        $tor = DocumentType::query()->where('code', 'tor')->firstOrFail();
        $torDocument = app(DocumentGenerator::class)->generate($procurement, $tor, $this->executor);

        $this->actingAs($this->executor)
            ->get(route('procurements.documents.edit', [$procurement, $torDocument]))
            ->assertInertia(fn (Assert $page) => $page->where('items', null));
    }

    public function test_a_document_of_articles_keeps_its_editor_untouched(): void
    {
        [$procurement, $baNego] = $this->spplWithDocuments();
        $articlesOnly = '<div class="pasal">PASAL 1</div><table><tr><td>PIHAK KESATU</td><td>PIHAK KEDUA</td></tr></table>';

        // A Surat Perjanjian is an "spk" too, but of articles only: no item
        // table, so no Rincian Item and the table tools stay.
        $contract = $baNego->replicate();
        $contract->fill(['document_type_id' => DocumentType::query()->where('code', 'spk')->value('id'), 'rendered_body' => $articlesOnly])->save();

        $articles = $baNego->replicate();
        $articles->fill(['document_type_id' => DocumentType::query()->where('code', 'spk-pasal')->value('id'), 'rendered_body' => $articlesOnly])->save();

        foreach ([$contract, $articles] as $document) {
            $this->actingAs($this->executor)
                ->get(route('procurements.documents.edit', [$procurement, $document]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('items', null));
        }
    }

    public function test_the_draft_table_follows_the_rows_as_they_are_typed(): void
    {
        [$procurement, $baNego] = $this->spplWithDocuments();
        $before = $baNego->rendered_body;
        $draft = str_replace('HARGA PEMBAYARAN LANGSUNG', 'HARGA PEMBAYARAN LANGSUNG (DRAF)', $before);

        $response = $this->actingAs($this->executor)
            ->postJson(route('procurements.documents.items-preview', [$procurement, $baNego]), ['rows' => $this->rows(), 'body' => $draft])
            ->assertOk();

        // The unsaved draft (its text edit kept) with the table redrawn.
        $html = (string) $response->json('body');
        $this->assertStringContainsString('HARGA PEMBAYARAN LANGSUNG (DRAF)', $html);
        $this->assertStringContainsString('Sarung tangan', $html);
        $this->assertStringContainsString('>1.140.000<', $html);

        $this->assertSame(0, $procurement->contractItems()->count());
        $this->assertSame($before, $baNego->refresh()->rendered_body);
    }

    public function test_a_table_typed_in_the_editor_becomes_the_items(): void
    {
        [$procurement, $baNego] = $this->spplWithDocuments();
        $generator = app(DocumentGenerator::class);

        $typed = '<table><thead><tr><th>HARGA SEBELUM NEGO</th></tr></thead><tbody>'
            .'<tr><td>A</td><td>Pekerjaan Pengecatan</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>'
            .'<tr><td>1</td><td>Cat dasar</td><td>4</td><td>Kg</td><td>375.000</td><td>1.500.000</td><td>356.250</td><td>1.425.000</td></tr>'
            .'<tr><td colspan="4">TOTAL HARGA</td><td colspan="2">1.500.000</td><td colspan="2">1.425.000</td></tr>'
            .'</tbody></table>';

        $generator->saveEdit($baNego, $this->executor, $baNego->title, $typed);

        $item = $procurement->contractItems()->sole();
        $this->assertSame('Pekerjaan Pengecatan', $item->group_name);
        $this->assertSame('Cat dasar', $item->description);
        $this->assertEquals(356250, (float) $item->unit_price);
        $this->assertEquals(1425000, (float) $procurement->refresh()->value_after_negotiation);
    }

    public function test_the_hpe_spk_and_standard_tables_are_drawn_in_their_own_style(): void
    {
        $renderer = app(ItemTableRenderer::class);
        $items = new Collection([
            new ProcurementContractItem(['sort_order' => 1, 'group_name' => 'Material', 'description' => 'Sarung tangan', 'part_number' => 'SC-01', 'volume' => 40, 'unit' => 'Pcs', 'price_before' => 25000, 'unit_price' => 23750]),
            new ProcurementContractItem(['sort_order' => 2, 'group_name' => 'Material', 'description' => 'Masker', 'part_number' => null, 'volume' => 4, 'unit' => 'Dos', 'price_before' => 50000, 'unit_price' => 47500]),
        ]);

        // HPE: a row per item at its amount before negotiation, then Jumlah.
        $hpe = $renderer->apply((string) file_get_contents(resource_path('document-templates/pj/hpe.html')), 'hpe', $items);
        $this->assertStringContainsString('<td>SC-01</td>', $hpe);
        $this->assertStringContainsString('<td class="right">1.000.000,00</td>', $hpe);
        $this->assertStringContainsString('<td class="right">1.200.000,00</td><td></td></tr>', $hpe);
        $this->assertSame(1, substr_count($hpe, 'class="total"'));

        // SPK: "A. group" and "- item" rows under the work's own row, its
        // deadline spanning them all; the JUMLAH/PPN/TOTAL rows stay.
        $spk = $renderer->apply((string) file_get_contents(resource_path('document-templates/kontrak/spk.html')), 'spk', $items);
        $this->assertStringContainsString('A. Material', $spk);
        $this->assertStringContainsString('- Sarung tangan', $spk);
        $this->assertStringContainsString('Rp 950.000', $spk);
        $this->assertStringContainsString('rowspan="4"', $spk);
        $this->assertStringContainsString('>JUMLAH<', $spk);
        $this->assertStringNotContainsString('............', substr($spk, (int) strpos($spk, 'BATAS WAKTU/ PENYERAHAN'), 4000));

        // HPS: before negotiation, closed by Sub Total, PPN, Total and words.
        $hps = $renderer->apply((string) file_get_contents(resource_path('document-templates/standar/penyusunan-hps.html')), 'penyusunan-hps', $items);
        $this->assertStringContainsString('<td><b>Sub Total</b></td>', str_replace(' colspan="5"', '', $hps));
        $this->assertStringContainsString('<b>Total HPS</b>', $hps);
        $this->assertStringContainsString('<td>1.344.000</td>', $hps);
        $this->assertStringContainsString('satu juta tiga ratus empat puluh empat ribu', mb_strtolower($hps));

        // Nothing to draw yet: the template's table stays as it is.
        $empty = new Collection([new ProcurementContractItem(['sort_order' => 1, 'description' => 'X', 'volume' => 1, 'unit' => 'Lot'])]);
        $template = (string) file_get_contents(resource_path('document-templates/pj/hpe.html'));
        $this->assertSame($template, $renderer->apply($template, 'hpe', $empty));
    }

    /**
     * Save the document from its editor with the Rincian Item rows.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function saveFromEditor(Procurement $procurement, ProcurementDocument $document, array $rows): TestResponse
    {
        return $this->actingAs($this->executor)
            ->put(route('procurements.documents.update', [$procurement, $document]), [
                'title' => $document->title,
                'body' => $document->rendered_body,
                'item_rows' => $rows,
            ]);
    }

    /**
     * An SPPL job in execution with its BA Negosiasi and Surat Pesanan drawn.
     *
     * @return array{0: Procurement, 1: ProcurementDocument, 2: ProcurementDocument}
     */
    protected function spplWithDocuments(): array
    {
        $procurement = Procurement::factory()->planningApproved()->executedBy($this->executor)->create([
            'execution_start_date' => now(),
            'execution_duration_days' => 15,
        ]);

        $generator = app(DocumentGenerator::class);

        return [
            $procurement,
            $generator->generate($procurement, DocumentType::query()->where('code', 'ba-negosiasi-sppl')->firstOrFail(), $this->executor),
            $generator->generate($procurement, DocumentType::query()->where('code', 'surat-pesanan')->firstOrFail(), $this->executor),
        ];
    }

    /**
     * A group and its two items, as the editor's grid sends them.
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(): array
    {
        return [
            ['id' => null, 'kind' => 'group', 'description' => 'Material & Tools Tangki 50 KL'],
            ['id' => null, 'kind' => 'item', 'description' => 'Sarung tangan', 'part_number' => null, 'volume' => 40, 'unit' => 'Pcs', 'price_before' => 25000, 'unit_price' => 23750],
            ['id' => null, 'kind' => 'item', 'description' => 'Masker', 'part_number' => null, 'volume' => 4, 'unit' => 'Dos', 'price_before' => 50000, 'unit_price' => 47500],
        ];
    }
}
