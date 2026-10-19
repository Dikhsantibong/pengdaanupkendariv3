<?php

namespace Tests\Feature\Procurements;

use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The total row of the Berita Acara Negosiasi adds up every item's unit
 * price times its quantity, before and after negotiation.
 */
class BaNegosiasiTotalTest extends TestCase
{
    use RefreshDatabase;

    protected Procurement $procurement;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        $this->author = User::factory()->administrator()->create();
        // The procurement's own values differ from the items, so a total
        // that read them instead of the items would show.
        $this->procurement = Procurement::factory()->create([
            'hpe_value' => 999000000,
            'value_after_negotiation' => 888000000,
        ]);

        $this->procurement->contractItems()->createMany([
            ['sort_order' => 1, 'description' => 'Laptop', 'volume' => 2, 'unit' => 'Unit', 'price_before' => 25000000, 'unit_price' => 22500000],
            ['sort_order' => 2, 'description' => 'Monitor', 'volume' => 4, 'unit' => 'Unit', 'price_before' => 5000000, 'unit_price' => 4500000],
            ['sort_order' => 3, 'description' => 'Kabel', 'volume' => 1.5, 'unit' => 'Roll', 'price_before' => 333333, 'unit_price' => 300001],
        ]);
    }

    /**
     * The words of a body, one space wherever a tag stood.
     */
    protected function words(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/<[^>]+>/', ' ', $html)));
    }

    public function test_the_sppl_total_is_price_times_quantity_summed(): void
    {
        $type = DocumentType::query()->where('code', 'ba-negosiasi-sppl')->firstOrFail();
        $text = $this->words(app(DocumentGenerator::class)->generate($this->procurement, $type, $this->author)->rendered_body);

        // 2 x 25.000.000 + 4 x 5.000.000 + 1,5 x 333.333 = 70.500.000 (rounded)
        // 2 x 22.500.000 + 4 x 4.500.000 + 1,5 x 300.001 = 63.450.002 (rounded)
        $this->assertStringContainsString('TOTAL HARGA 70.500.000 63.450.002', $text);
        $this->assertStringNotContainsString('999.000.000', $text);
        $this->assertStringNotContainsString('888.000.000', $text);
    }

    public function test_a_group_heading_is_merged_across_the_sppl_table(): void
    {
        $this->procurement->contractItems()->update(['group_name' => 'Material & Tools Tangki 50 KL']);
        $type = DocumentType::query()->where('code', 'ba-negosiasi-sppl')->firstOrFail();
        $generator = app(DocumentGenerator::class);

        $document = $generator->generate($this->procurement, $type, $this->author);

        // Its letter under NO, its name over the other seven columns.
        $this->assertMatchesRegularExpression(
            '#<tr><td[^>]*>A</td><td colspan="7"[^>]*>Material &amp; Tools Tangki 50 KL</td></tr>#',
            $document->rendered_body,
        );

        // Saved from the editor, the merged heading still groups the items.
        $parsed = $generator->parseNegotiationItems($document->rendered_body);
        $this->assertCount(3, $parsed);
        $this->assertSame('Material & Tools Tangki 50 KL', $parsed[0]['group']);
        $this->assertSame('Laptop', $parsed[0]['name']);
    }

    public function test_an_edited_ba_negosiasi_follows_the_rincian_item_when_opened(): void
    {
        $type = DocumentType::query()->where('code', 'ba-negosiasi-sppl')->firstOrFail();
        $generator = app(DocumentGenerator::class);
        $document = $generator->generate($this->procurement, $type, $this->author);

        // Edited by hand, then an item's price changes.
        $document->forceFill(['revision' => 3, 'rendered_body' => $document->rendered_body.'<p>Catatan manual</p>'])->save();
        $this->procurement->contractItems()->where('description', 'Laptop')->update(['unit_price' => 20000000]);

        $generator->refreshDocument($document->refresh());

        $text = $this->words($document->refresh()->rendered_body);
        // 2 x 20.000.000 + 4 x 4.500.000 + 1,5 x 300.001 = 58.450.002 (rounded)
        $this->assertStringContainsString('TOTAL HARGA 70.500.000 58.450.002', $text);
        $this->assertStringContainsString('Catatan manual', $text);
    }

    public function test_the_spk_and_pj_total_is_price_times_quantity_summed(): void
    {
        $type = DocumentType::query()->where('code', 'ba-negosiasi')->firstOrFail();
        $text = $this->words(app(DocumentGenerator::class)->generate($this->procurement, $type, $this->author)->rendered_body);

        // The rows: quantity, unit, offer, negotiated price, amount.
        $this->assertStringContainsString('1 Laptop 2 Unit 25.000.000 22.500.000 45.000.000', $text);
        $this->assertStringContainsString('2 Monitor 4 Unit 5.000.000 4.500.000 18.000.000', $text);
        // Sub Total = sum of negotiated price x quantity; PPN 12%; then the total.
        $this->assertStringContainsString('Sub Total 63.450.002', $text);
        $this->assertStringContainsString('PPN 7.614.000', $text);
        $this->assertStringContainsString('Total Harga Akhir 71.064.002', $text);
    }
}
