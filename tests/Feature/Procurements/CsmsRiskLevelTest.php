<?php

namespace Tests\Feature\Procurements;

use App\Enums\CsmsRiskLevel;
use App\Models\Procurement;
use App\Models\User;
use App\Services\DocumentGenerator;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The user states the CSMS risk level (Low, Moderat, High, Extreme) when
 * registering or proposing a job; the detail page and documents show it.
 */
class CsmsRiskLevelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
    }

    /**
     * A valid create form.
     *
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        $procurement = Procurement::factory()->make();

        return [
            'name' => 'Pengadaan Scaffolding',
            'work_director_id' => $procurement->work_director_id,
            'target_unit_ids' => [$procurement->target_unit_id],
            'budget_source_id' => $procurement->budget_source_id,
            'hpe_value' => 1000000,
            ...$overrides,
        ];
    }

    public function test_the_form_offers_the_four_levels(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('options.csmsRiskLevels', [
                    ['value' => 'low', 'label' => 'Low'],
                    ['value' => 'moderate', 'label' => 'Moderat'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'extreme', 'label' => 'Extreme'],
                ]));
    }

    public function test_the_level_is_saved_shown_and_printed(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('procurements.store'), $this->payload(['csms_risk_level' => 'high']))
            ->assertSessionHasNoErrors();

        $procurement = Procurement::query()->where('name', 'Pengadaan Scaffolding')->firstOrFail();
        $this->assertSame(CsmsRiskLevel::High, $procurement->csms_risk_level);

        $this->actingAs($administrator)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page->where('procurement.csms_risk_level', 'High'));

        $this->assertSame('High', app(DocumentGenerator::class)->placeholderValues($procurement)['level_risiko_csms']);

        // The edit form starts from it.
        $this->actingAs($administrator)
            ->get(route('procurements.edit', $procurement))
            ->assertInertia(fn (Assert $page) => $page->where('procurement.csms_risk_level', 'high'));
    }

    public function test_an_unknown_level_is_refused(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('procurements.store'), $this->payload(['csms_risk_level' => 'sedang']))
            ->assertSessionHasErrors('csms_risk_level');

        $this->assertSame(0, Procurement::query()->count());
    }
}
