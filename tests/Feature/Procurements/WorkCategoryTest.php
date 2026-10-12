<?php

namespace Tests\Feature\Procurements;

use App\Enums\WorkCategory;
use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\KontrakTemplateSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PjTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A valid create form for the given format.
     *
     * @return array<string, mixed>
     */
    protected function payload(ContractNumberFormat $format, ?string $category): array
    {
        $procurement = Procurement::factory()->make();

        return [
            'contract_number_format_id' => $format->id,
            'work_category' => $category,
            'name' => 'Pengadaan Actuator Type StG 30',
            'work_director_id' => $procurement->work_director_id,
            'target_unit_ids' => [$procurement->target_unit_id],
            'procurement_method_id' => $procurement->procurement_method_id,
            'budget_source_id' => $procurement->budget_source_id,
            'hpe_value' => 187000000,
            'progress_status_id' => $procurement->progress_status_id,
        ];
    }

    public function test_spk_and_pj_ask_for_barang_or_jasa(): void
    {
        $this->seed(MasterDataSeeder::class);
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();

        foreach (['SPK', 'PJ'] as $code) {
            $format = ContractNumberFormat::query()->where('code', $code)->firstOrFail();

            $this->actingAs($teamLeader)
                ->post(route('procurements.store'), $this->payload($format, null))
                ->assertSessionHasErrors('work_category');

            $this->actingAs($teamLeader)
                ->post(route('procurements.store'), [...$this->payload($format, 'barang'), 'name' => "Pengadaan {$code}"])
                ->assertSessionHasNoErrors();

            $this->assertSame(
                WorkCategory::Barang,
                Procurement::query()->where('name', "Pengadaan {$code}")->firstOrFail()->work_category,
            );
        }
    }

    public function test_a_format_without_categories_drops_one_sent_along(): void
    {
        $this->seed(MasterDataSeeder::class);
        $sppl = ContractNumberFormat::query()->where('code', 'SPPL')->firstOrFail();

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->post(route('procurements.store'), $this->payload($sppl, 'jasa'))
            ->assertSessionHasNoErrors();

        $this->assertNull(Procurement::query()->latest('id')->firstOrFail()->work_category);
    }

    public function test_the_spk_follows_the_procurement_category(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(KontrakTemplateSeeder::class);
        $spk = DocumentType::query()->where('code', 'spk')->firstOrFail();
        $author = User::factory()->administrator()->create();

        $goods = Procurement::factory()->create(['work_category' => 'barang']);
        $services = Procurement::factory()->create(['work_category' => 'jasa']);

        $goodsSpk = app(DocumentGenerator::class)->generate($goods, $spk, $author);
        $servicesSpk = app(DocumentGenerator::class)->generate($services, $spk, $author);

        $this->assertStringContainsString('JENIS DAN JUMLAH BARANG', $goodsSpk->rendered_body);
        $this->assertStringContainsString('LINGKUP PEKERJAAN', $servicesSpk->rendered_body);
        $this->assertStringNotContainsString('JENIS DAN JUMLAH BARANG', $servicesSpk->rendered_body);
    }

    public function test_a_pj_for_goods_prints_its_own_hpe_while_others_keep_the_standard_one(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateSeeder::class);
        $this->seed(PjTemplateSeeder::class);

        $hpe = DocumentType::query()->where('code', 'hpe')->firstOrFail();
        $pj = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();
        $spk = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();
        $author = User::factory()->administrator()->create();

        $pjGoods = Procurement::factory()->create([
            'contract_number_format_id' => $pj->id,
            'work_category' => 'barang',
            'proposal_memo_number' => '0001/UPKD/ENJ/COMMON/P/AI-2026/2/2026',
        ]);
        $spkGoods = Procurement::factory()->create([
            'contract_number_format_id' => $spk->id,
            'work_category' => 'barang',
        ]);

        $pjDocument = app(DocumentGenerator::class)->generate($pjGoods, $hpe, $author);
        $spkDocument = app(DocumentGenerator::class)->generate($spkGoods, $hpe, $author);

        $pjText = strip_tags($pjDocument->rendered_body);
        $this->assertStringContainsString('Harga Perkiraan Enjiniring', $pjText);
        $this->assertStringContainsString('0001/UPKD/ENJ/COMMON/P/AI-2026/2/2026-HPE(A)', $pjText);
        $this->assertStringContainsString('Nama Barang dan Spesifikasi', $pjText);
        $this->assertStringNotContainsString('Harga Perkiraan Enjiniring', strip_tags($spkDocument->rendered_body));
    }

    public function test_activating_a_category_template_keeps_the_other_category_active(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(KontrakTemplateSeeder::class);
        $spk = DocumentType::query()->where('code', 'spk')->firstOrFail();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('master-data.document-templates.store'), [
                'document_type_id' => $spk->id,
                'work_category' => 'barang',
                'name' => 'SPK Barang Revisi',
                'body' => '<p>{{nama_pengadaan}}</p>',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $active = DocumentTemplate::query()->where('document_type_id', $spk->id)->where('is_active', true)->get();

        $this->assertEqualsCanonicalizing(['barang', 'jasa'], $active->map(fn (DocumentTemplate $template): string => $template->work_category->value)->all());
        $this->assertSame(2, DocumentTemplate::query()->where('name', 'SPK Barang Revisi')->value('version'));
    }
}
