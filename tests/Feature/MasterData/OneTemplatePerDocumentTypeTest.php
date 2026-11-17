<?php

namespace Tests\Feature\MasterData;

use App\Enums\ProcurementStage;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Support\DocumentTypeVariants;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PjTemplateSeeder;
use Database\Seeders\RksSpkTemplateSeeder;
use Database\Seeders\RksTenderTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One document type, one template: the RKS, the SPK and its Pasal are split
 * into a variant per method, format or category, each with its own template,
 * and a job is asked for the variant that fits it, as the templates used to
 * be picked.
 */
class OneTemplatePerDocumentTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(KontrakTemplateSeeder::class);
        $this->seed(PjTemplateSeeder::class);
        $this->seed(RksSpkTemplateSeeder::class);
        $this->seed(RksTenderTemplateSeeder::class);
    }

    public function test_every_variant_carries_exactly_one_template(): void
    {
        foreach (DocumentTypeVariants::FAMILIES as $family => $variants) {
            foreach (array_keys($variants) as $code) {
                $type = DocumentType::query()->where('code', $code)->sole();

                $this->assertSame($family, $type->familyCode(), $code);
                $this->assertSame(1, $type->templates()->count(), $code);
            }
        }

        // No document type has more than one template.
        $this->assertSame(0, DocumentType::query()->withCount('templates')->get()->where('templates_count', '>', 1)->count());
    }

    public function test_a_job_is_asked_for_the_variant_that_fits_it(): void
    {
        $rks = ChecklistItem::query()->shared()->where('stage', ProcurementStage::Perencanaan)->where('name', 'RKS (Rencana Kerja dan Syarat)')->sole();
        $contract = ChecklistItem::query()->shared()->where('stage', ProcurementStage::Pelaksanaan)->where('name', 'Kontrak')->sole();

        $asked = fn (ChecklistItem $step, Procurement $procurement): array => $step->documentTypesFor($procurement)->pluck('code')->all();

        // The RKS: the PJ for services first, then the method (which the
        // format sets: SPK by SPK, Tender by PJ), else the general one.
        $this->assertSame(['rks-spk'], $asked($rks, $this->procurement('SPK', 'barang', 'spk')));
        $this->assertSame(['rks-spk'], $asked($rks, $this->procurement('SPK', 'jasa', 'spk')));
        $this->assertSame(['rks-pj-jasa'], $asked($rks, $this->procurement('PJ', 'jasa', 'tender')));
        $this->assertSame(['rks-tender'], $asked($rks, $this->procurement('PJ', 'barang', 'tender')));
        $this->assertSame(['rks'], $asked($rks, $this->procurement(null, null, null)));

        // The contract: the SPK with its Pasal per category, or the Surat Perjanjian.
        $this->assertSame(['spk', 'spk-pasal'], $asked($contract, $this->procurement('SPK', 'jasa', 'spk')));
        $this->assertSame(['spk', 'spk-pasal-barang'], $asked($contract, $this->procurement('SPK', 'barang', 'spk')));
        $this->assertSame(['spk-pj'], $asked($contract, $this->procurement('PJ', 'barang', 'tender')));
    }

    public function test_a_document_asked_by_its_family_is_drawn_as_the_fitting_variant(): void
    {
        $procurement = $this->procurement('PJ', 'jasa', 'tender');
        $administrator = User::factory()->administrator()->create();

        // Asked for by the family's own code, as every caller always has.
        $document = app(DocumentGenerator::class)->generate(
            $procurement,
            DocumentType::query()->where('code', 'rks')->sole(),
            $administrator,
        );

        $variant = DocumentType::query()->where('code', 'rks-pj-jasa')->sole();
        $this->assertSame($variant->id, $document->document_type_id);
        $this->assertSame($variant->templates()->sole()->id, $document->document_template_id);
        // Named and filed as it always was.
        $this->assertStringStartsWith('RKS (Rencana Kerja dan Syarat) - ', $document->title);
        $this->assertStringEndsWith('-rks.html', $document->file_name);

        $contract = app(DocumentGenerator::class)->generate(
            $procurement,
            DocumentType::query()->where('code', 'spk')->sole(),
            $administrator,
        );
        $this->assertSame('spk-pj', $contract->documentType->code);
        $this->assertStringStartsWith('Surat Perjanjian (PJ) - ', $contract->title);
    }

    public function test_a_second_template_for_a_document_type_is_refused(): void
    {
        $type = DocumentType::query()->where('code', 'rks-spk')->sole();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('master-data.document-templates.store'), [
                'document_type_id' => $type->id,
                'procurement_method_id' => null,
                'contract_number_format_id' => null,
                'work_category' => null,
                'name' => 'RKS Kedua',
                'body' => '<p>Isi lain</p>',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('document_type_id');

        $this->assertSame(1, $type->templates()->count());

        // A new template goes to a document type without one.
        $this->actingAs($administrator)
            ->get(route('master-data.document-templates.create'))
            ->assertInertia(fn ($page) => $page->where('documentTypes', fn ($options) => collect($options)->pluck('value')->doesntContain($type->id)));
    }

    public function test_the_split_moves_an_older_database_without_losing_a_document(): void
    {
        // A database from before: the family's templates and documents all on
        // its own type, the variants not there yet.
        $rks = DocumentType::query()->where('code', 'rks')->sole();
        $variants = DocumentType::query()->where('family', 'rks')->whereKeyNot($rks->id)->get();
        $spkTemplate = DocumentType::query()->where('code', 'rks-spk')->sole()->templates()->sole();

        $procurement = $this->procurement('SPK', 'barang', 'spk');
        $document = app(DocumentGenerator::class)->generate($procurement, $rks, User::factory()->administrator()->create());

        DocumentTemplate::query()->whereIn('document_type_id', $variants->pluck('id'))->update(['document_type_id' => $rks->id]);
        DB::table('procurement_documents')->where('id', $document->id)->update(['document_type_id' => $rks->id]);
        DB::table('checklist_item_document_type')->whereIn('document_type_id', $variants->pluck('id'))->delete();
        DB::table('document_type_selection_method')->whereIn('document_type_id', $variants->pluck('id'))->delete();
        DB::table('document_types')->whereIn('id', $variants->pluck('id'))->delete();

        DocumentTypeVariants::split();
        DocumentTypeVariants::split();

        $variant = DocumentType::query()->where('code', 'rks-spk')->sole();
        $this->assertSame($variant->id, $spkTemplate->refresh()->document_type_id);
        $this->assertSame($variant->id, $document->refresh()->document_type_id);
        $this->assertSame(4, DocumentType::query()->where('family', 'rks')->count());
        $this->assertSame(0, DocumentType::query()->withCount('templates')->get()->where('templates_count', '>', 1)->count());

        $step = ChecklistItem::query()->shared()->where('name', 'RKS (Rencana Kerja dan Syarat)')->sole();
        $this->assertSame(['rks-spk'], $step->documentTypesFor($procurement->refresh())->pluck('code')->all());
    }

    /**
     * A procurement under a contract format, whose method the format sets.
     */
    protected function procurement(?string $format, ?string $category, ?string $method): Procurement
    {
        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => $format === null ? null : ContractNumberFormat::query()->where('code', $format)->value('id'),
            'work_category' => $category,
            'procurement_method_id' => null,
        ])->refresh();

        $this->assertSame($method, $procurement->procurementMethod?->code);

        return $procurement;
    }
}
