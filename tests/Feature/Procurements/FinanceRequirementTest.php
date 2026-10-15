<?php

namespace Tests\Feature\Procurements;

use App\Enums\FinanceSection;
use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\FinanceRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Penyerahan and Pembayaran tabs list the delivery and payment
 * requirements of the procurement's own contract.
 */
class FinanceRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SPK', 'PJ', 'SPPL'] as $code) {
            ContractNumberFormat::factory()->create(['code' => $code]);
        }

        $this->seed(FinanceRequirementSeeder::class);
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2: string}>
     */
    public static function contracts(): array
    {
        return [
            'SPK barang' => ['SPK', 'barang', 'SPK Barang'],
            'SPK jasa' => ['SPK', 'jasa', 'SPK Jasa'],
            'Surat Perjanjian' => ['PJ', 'barang', 'Surat Perjanjian'],
            'Lampiran Surat Pesanan' => ['SPPL', null, 'Lampiran Surat Pesanan'],
        ];
    }

    #[DataProvider('contracts')]
    public function test_each_contract_gets_the_requirements_of_its_own_template(string $format, ?string $category, string $source): void
    {
        $procurement = $this->procurement($format, $category);
        $expected = FinanceRequirementSeeder::SOURCES[$source]['items'];

        foreach (FinanceSection::cases() as $section) {
            $names = $procurement->checklists()
                ->where('stage', ProcurementStage::Keuangan)
                ->with('checklistItem')
                ->get()
                ->filter(fn ($checklist): bool => $checklist->checklistItem->section === $section)
                ->sortBy(fn ($checklist): int => $checklist->checklistItem->sort_order)
                ->map(fn ($checklist): string => $checklist->checklistItem->name)
                ->values()
                ->all();

            $this->assertSame(array_column($expected[$section->value], 0), $names, "{$source} — {$section->value}");
        }
    }

    public function test_requirements_wait_until_the_contract_is_known(): void
    {
        // No contract format: no contract's requirements at all.
        $unknown = $this->procurement(null, null);

        $this->assertSame(0, $unknown->checklists()->where('stage', ProcurementStage::Keuangan)->count());

        // An SPK without its category: neither the goods nor the services list.
        $spk = $this->procurement('SPK', null);

        $this->assertSame(0, $spk->checklists()->where('stage', ProcurementStage::Keuangan)->count());

        // Once the category is filled in, the services list appears.
        $spk->forceFill(['work_category' => 'jasa'])->save();
        app(ProcurementService::class)->syncChecklists($spk);

        $items = FinanceRequirementSeeder::SOURCES['SPK Jasa']['items'];
        $this->assertSame(
            count($items['penyerahan']) + count($items['pembayaran']),
            $spk->checklists()->where('stage', ProcurementStage::Keuangan)->count(),
        );

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $unknown))
            ->assertInertia(fn ($page) => $page->where('detail.finance.requirements_waiting_for', ['format kontrak']));
    }

    public function test_every_requirement_fits_the_name_column(): void
    {
        // SQLite does not enforce the length; MySQL refuses a longer name.
        foreach (FinanceRequirementSeeder::SOURCES as $source => $definition) {
            foreach ($definition['items'] as $items) {
                foreach ($items as [$name]) {
                    $this->assertLessThanOrEqual(255, mb_strlen($name), "{$source}: {$name}");
                }
            }
        }
    }

    public function test_the_requirements_cite_the_article_they_come_from(): void
    {
        $item = ChecklistItem::query()->where('name', 'Sertifikat CSMS dengan klasifikasi minimal Moderat')->sole();

        $this->assertSame('SPK Jasa Pasal 6 ayat (8) huruf f', $item->description);
        $this->assertSame(FinanceSection::Penyerahan, $item->section);

        $optional = ChecklistItem::query()
            ->where('description', 'Surat Perjanjian Pasal 10 ayat (1) huruf g')
            ->sole();

        $this->assertSame('Surat keterangan bebas potong (jika ada)', $optional->name);
        $this->assertTrue($optional->is_optional);
    }

    public function test_the_detail_page_splits_the_steps_into_two_tabs(): void
    {
        $finance = User::factory()->create(['role' => UserRole::Keuangan]);
        $procurement = $this->procurement('PJ', 'barang');
        $procurement->forceFill(['finance_submitted_at' => now()])->save();

        $this->actingAs($finance)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('checklists.keuangan', fn ($rows) => collect($rows)->where('section', 'penyerahan')->count() === 7
                    && collect($rows)->where('section', 'pembayaran')->count() === 8));
    }

    public function test_an_administrator_sets_the_tab_and_category_of_a_step(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('master-data.checklist-items.store'), [
                'stage' => 'keuangan',
                'section' => 'penyerahan',
                'work_category' => 'barang',
                'name' => 'Sertifikat Kalibrasi',
                'is_optional' => false,
                'input_kind' => 'none',
                'sort_order' => 99,
                'is_active' => true,
            ])
            ->assertRedirect();

        $item = ChecklistItem::query()->where('name', 'Sertifikat Kalibrasi')->sole();

        $this->assertSame(FinanceSection::Penyerahan, $item->section);
        $this->assertSame('barang', $item->work_category?->value);

        // A tab means nothing outside Keuangan.
        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('master-data.checklist-items.update', $item), [
                'stage' => 'pelaksanaan',
                'section' => 'penyerahan',
                'work_category' => 'none',
                'name' => 'Sertifikat Kalibrasi',
                'is_optional' => false,
                'input_kind' => 'none',
                'sort_order' => 99,
                'is_active' => true,
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertNull($item->section);
        $this->assertNull($item->work_category);
    }

    /**
     * A procurement under one contract format, with its checklist laid out.
     */
    protected function procurement(?string $format, ?string $category): Procurement
    {
        $procurement = Procurement::factory()->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => $format === null
                ? null
                : ContractNumberFormat::query()->where('code', $format)->value('id'),
            'work_category' => $category,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        return $procurement;
    }
}
