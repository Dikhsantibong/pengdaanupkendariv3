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
    public function test_no_contract_gets_handover_or_payment_steps_by_itself(string $format, ?string $category, string $source): void
    {
        $procurement = $this->procurement($format, $category);

        // The Data Master holds this contract's template, yet none of it
        // becomes a step: the PIC types the steps.
        $this->assertNotEmpty(FinanceRequirementSeeder::SOURCES[$source]['items']['penyerahan']);
        $this->assertSame(0, $procurement->checklists()->where('stage', ProcurementStage::Keuangan)->count(), $source);

        // Steps given by hand are kept as they are.
        $this->addHandoverAndPaymentSteps($procurement);
        app(ProcurementService::class)->syncChecklists($procurement);
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

    public function test_nothing_waits_for_the_contract_format_any_more(): void
    {
        $unknown = $this->procurement(null, null);
        $this->assertSame(0, $unknown->checklists()->where('stage', ProcurementStage::Keuangan)->count());

        // Filling in the format and category adds no step either.
        $spk = $this->procurement('SPK', null);
        $spk->forceFill(['work_category' => 'jasa'])->save();
        app(ProcurementService::class)->syncChecklists($spk);

        $this->assertSame(0, $spk->checklists()->where('stage', ProcurementStage::Keuangan)->count());

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $unknown))
            ->assertInertia(fn ($page) => $page->where('detail.finance.requirements_waiting_for', []));
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
        $this->addHandoverAndPaymentSteps($procurement);
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
