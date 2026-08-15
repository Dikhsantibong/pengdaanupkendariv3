<?php

namespace Tests\Feature\Procurements;

use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract numbers read KDD075.SPK/612/UPKD/2026. SPK and PJ each keep their
 * own running count within a year, the form offers the next free one, and it
 * can still be corrected by hand.
 */
class ContractNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_two_formats_are_seeded_where_the_unit_stands_today(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->travelTo('2026-08-07');

        $formats = ContractNumberFormat::query()->active()->ordered()->get();

        $this->assertSame(['SPK', 'PJ'], $formats->pluck('code')->all());

        // The unit is partway through the year, so the count resumes rather
        // than starting over at 001.
        $this->assertSame('KDD075.SPK/612/UPKD/2026', $formats[0]->sample());
        $this->assertSame('KDD020.PJ/612/UPKD/2026', $formats[1]->sample());
    }

    public function test_the_count_never_drops_below_the_starting_number(): void
    {
        $this->travelTo('2026-08-07');

        $spk = ContractNumberFormat::factory()->create([
            'code' => 'SPK',
            'starting_sequence' => 75,
        ]);

        $service = app(ProcurementService::class);

        $this->assertSame('KDD075.SPK/612/UPKD/2026', $service->nextNumber($spk));

        // A number issued below the starting point does not pull the count
        // back down to it.
        Procurement::factory()->create([
            'contract_number_format_id' => $spk->id,
            'number' => 'KDD010.SPK/612/UPKD/2026',
        ]);

        $this->assertSame('KDD075.SPK/612/UPKD/2026', $service->nextNumber($spk));

        // Once the count passes the starting point it carries on from there.
        Procurement::factory()->create([
            'contract_number_format_id' => $spk->id,
            'number' => 'KDD075.SPK/612/UPKD/2026',
        ]);

        $this->assertSame('KDD076.SPK/612/UPKD/2026', $service->nextNumber($spk));
    }

    public function test_a_new_year_starts_the_count_at_the_starting_number(): void
    {
        $spk = ContractNumberFormat::factory()->create([
            'code' => 'SPK',
            'starting_sequence' => 75,
        ]);

        $this->travelTo('2026-12-31');

        Procurement::factory()->create([
            'contract_number_format_id' => $spk->id,
            'number' => 'KDD090.SPK/612/UPKD/2026',
        ]);

        // Last year's numbers belong to last year's count.
        $this->travelTo('2027-01-02');

        $this->assertSame(
            'KDD075.SPK/612/UPKD/2027',
            app(ProcurementService::class)->nextNumber($spk),
        );
    }

    public function test_a_format_composes_a_number_in_the_official_shape(): void
    {
        $format = ContractNumberFormat::factory()->create([
            'code' => 'SPK',
            'prefix' => 'KDD',
            'unit_segment' => '612/UPKD',
            'sequence_length' => 3,
        ]);

        $this->assertSame('KDD075.SPK/612/UPKD/2026', $format->compose(75, 2026));
        $this->assertSame(75, $format->sequenceIn('KDD075.SPK/612/UPKD/2026', 2026));

        // A number from a different year or shape belongs to a different count.
        $this->assertNull($format->sequenceIn('KDD075.SPK/612/UPKD/2025', 2026));
        $this->assertNull($format->sequenceIn('PGD/2026/08/0001', 2026));
    }

    public function test_each_format_keeps_its_own_running_count(): void
    {
        $this->travelTo('2026-08-07');

        $spk = ContractNumberFormat::factory()->create(['code' => 'SPK']);
        $pj = ContractNumberFormat::factory()->create(['code' => 'PJ']);

        $service = app(ProcurementService::class);

        $this->assertSame('KDD001.SPK/612/UPKD/2026', $service->nextNumber($spk));
        $this->assertSame('KDD001.PJ/612/UPKD/2026', $service->nextNumber($pj));

        Procurement::factory()->create([
            'contract_number_format_id' => $spk->id,
            'number' => 'KDD074.SPK/612/UPKD/2026',
        ]);

        // The SPK count moves on; PJ is untouched by it.
        $this->assertSame('KDD075.SPK/612/UPKD/2026', $service->nextNumber($spk));
        $this->assertSame('KDD001.PJ/612/UPKD/2026', $service->nextNumber($pj));
    }

    public function test_a_hand_written_number_does_not_derail_the_count(): void
    {
        $this->travelTo('2026-08-07');

        $spk = ContractNumberFormat::factory()->create(['code' => 'SPK']);

        Procurement::factory()->create([
            'contract_number_format_id' => $spk->id,
            'number' => 'KDD020.SPK/612/UPKD/2026',
        ]);

        // Corrected into a shape this format does not produce: skipped rather
        // than guessed at, so the count carries on from the numbers it knows.
        Procurement::factory()->create([
            'contract_number_format_id' => $spk->id,
            'number' => 'KDD-KHUSUS/2026',
        ]);

        $this->assertSame(
            'KDD021.SPK/612/UPKD/2026',
            app(ProcurementService::class)->nextNumber($spk),
        );
    }

    public function test_the_create_form_offers_a_number_for_every_format(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->travelTo('2026-08-07');

        $administrator = User::factory()->administrator()->create();

        $formats = ContractNumberFormat::query()->active()->ordered()->get();

        $this->actingAs($administrator)
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('procurements/create')
                ->has('options.contractNumberFormats', 2)
                ->where('nextNumbers.'.$formats[0]->id, 'KDD075.SPK/612/UPKD/2026')
                ->where('nextNumbers.'.$formats[1]->id, 'KDD020.PJ/612/UPKD/2026'));
    }

    public function test_a_procurement_is_registered_with_the_number_given(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->travelTo('2026-08-07');

        $administrator = User::factory()->administrator()->create();
        $format = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'contract_number_format_id' => $format->id,
                'number' => 'KDD020.PJ/612/UPKD/2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $procurement = Procurement::query()->firstOrFail();

        $this->assertSame('KDD020.PJ/612/UPKD/2026', $procurement->number);
        $this->assertSame($format->id, $procurement->contract_number_format_id);
    }

    public function test_a_blank_number_falls_back_to_the_running_one(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->travelTo('2026-08-07');

        $administrator = User::factory()->administrator()->create();
        $format = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'contract_number_format_id' => $format->id,
                'number' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            'KDD075.SPK/612/UPKD/2026',
            Procurement::query()->firstOrFail()->number,
        );
    }

    public function test_a_procurement_registered_without_a_format_keeps_the_internal_number(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('procurements.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertStringStartsWith('PGD/', Procurement::query()->firstOrFail()->number);
    }

    public function test_two_procurements_cannot_share_a_number(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $format = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();

        Procurement::factory()->create(['number' => 'KDD075.SPK/612/UPKD/2026']);

        $this->actingAs($administrator)
            ->from(route('procurements.create'))
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'contract_number_format_id' => $format->id,
                'number' => 'KDD075.SPK/612/UPKD/2026',
            ])
            ->assertSessionHasErrors('number');

        $this->assertSame(1, Procurement::query()->count());
    }

    public function test_the_number_can_be_corrected_on_the_edit_form(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $format = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();

        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => $format->id,
            'number' => 'KDD075.SPK/612/UPKD/2026',
        ]);

        $this->actingAs($administrator)
            ->put(route('procurements.update', $procurement), [
                ...$this->payload(),
                'contract_number_format_id' => $format->id,
                // Keeping its own number must not trip the unique rule.
                'number' => 'KDD076.SPK/612/UPKD/2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('KDD076.SPK/612/UPKD/2026', $procurement->fresh()?->number);
    }

    public function test_an_unchanged_number_passes_its_own_uniqueness_check(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();

        $procurement = Procurement::factory()->create([
            'number' => 'KDD075.SPK/612/UPKD/2026',
        ]);

        $this->actingAs($administrator)
            ->put(route('procurements.update', $procurement), [
                ...$this->payload(),
                'number' => 'KDD075.SPK/612/UPKD/2026',
                'name' => 'Nama Baru',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Nama Baru', $procurement->fresh()?->name);
        $this->assertSame('KDD075.SPK/612/UPKD/2026', $procurement->fresh()?->number);
    }

    public function test_an_administrator_edits_the_shape_of_a_format(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->travelTo('2026-08-07');

        $administrator = User::factory()->administrator()->create();
        $format = ContractNumberFormat::query()->where('code', 'SPK')->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('master-data.contract-number-formats.update', $format), [
                'code' => 'spk',
                'name' => 'Surat Perintah Kerja',
                'prefix' => 'kdd',
                'unit_segment' => '/700/UPKD/',
                'sequence_length' => 4,
                'starting_sequence' => 75,
                'description' => null,
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $format->refresh();

        // Code and prefix are printed inside the number, so they are stored the
        // way they appear on the contract; stray slashes are trimmed.
        $this->assertSame('SPK', $format->code);
        $this->assertSame('KDD', $format->prefix);
        $this->assertSame('700/UPKD', $format->unit_segment);
        $this->assertSame('KDD0075.SPK/700/UPKD/2026', $format->sample());
    }

    public function test_a_deactivated_format_leaves_its_procurements_numbered(): void
    {
        $this->seed(MasterDataSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $format = ContractNumberFormat::query()->where('code', 'PJ')->firstOrFail();

        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => $format->id,
            'number' => 'KDD020.PJ/612/UPKD/2026',
        ]);

        $this->actingAs($administrator)
            ->delete(route('master-data.contract-number-formats.destroy', $format))
            ->assertRedirect();

        $this->assertSoftDeleted($format);
        $this->assertSame('KDD020.PJ/612/UPKD/2026', $procurement->fresh()?->number);
        $this->assertNotNull($procurement->fresh()?->contractNumberFormat);
    }

    public function test_only_an_administrator_manages_the_formats(): void
    {
        $this->actingAs(User::factory()->planner()->create())
            ->get(route('master-data.contract-number-formats.index'))
            ->assertForbidden();
    }

    /**
     * A valid procurement payload built from the seeded master data.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $procurement = Procurement::factory()->make();

        return [
            'name' => 'Pengadaan Jasa Angkut BBM',
            'work_director_id' => $procurement->work_director_id,
            'target_unit_id' => $procurement->target_unit_id,
            'procurement_method_id' => $procurement->procurement_method_id,
            'budget_source_id' => $procurement->budget_source_id,
            'prk_number' => null,
            'hpe_value' => 150000000,
            'progress_status_id' => $procurement->progress_status_id,
            'target_completion_date' => null,
            'notes' => null,
        ];
    }
}
