<?php

namespace Tests\Feature;

use App\Enums\AmendmentState;
use App\Enums\DeliveryOrderState;
use App\Enums\StatusCategory;
use App\Http\Controllers\MonitoringController;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementDeliveryOrder;
use App\Models\ProgressStatus;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Monitoring menu opens on one folder per partner; a folder lists the
 * work done with that partner (procurements, amendments, Delivery Orders,
 * running contracts), by state, filtered and paginated on the server.
 */
class ContractMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_amendments_are_filed_per_partner_listed_by_state_and_searchable(): void
    {
        $lead = User::factory()->teamLeaderPengadaan()->create();
        $procurement = Procurement::factory()->create(['name' => 'Overhaul Mesin MAK', 'partner_name' => 'PT Sinar Timur']);

        ProcurementAmendment::factory()->for($procurement)->create(['state' => AmendmentState::Disetujui, 'number' => 'KDD001.Amd/612/UPKD/2026']);
        ProcurementAmendment::factory()->for($procurement)->create(['sequence' => 2, 'state' => AmendmentState::MenungguTlPengadaan]);
        ProcurementAmendment::factory()->for(Procurement::factory()->create(['partner_name' => 'CV Lain']))->create(['state' => AmendmentState::MenungguPersetujuanTl]);

        $this->actingAs($lead)
            ->get(route('monitoring.amendments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('monitoring/amendments')
                ->where('amendments', null)
                ->has('folders.data', 2)
                ->where('folders.data', fn (Collection $folders) => $folders->firstWhere('name', 'PT Sinar Timur') === [
                    'key' => 'PT Sinar Timur',
                    'name' => 'PT Sinar Timur',
                    'total' => 2,
                    'works' => 1,
                    'done' => 1,
                    'latest_at' => $folders->firstWhere('name', 'PT Sinar Timur')['latest_at'],
                ])
                ->where('summary', fn (Collection $summary) => $summary->firstWhere('value', 'disetujui')['total'] === 1
                    && collect($summary)->firstWhere('value', 'menunggu_tl_pengadaan')['total'] === 1));

        // A partner's folder, spelling aside, lists its work's amendments.
        $this->actingAs($lead)
            ->get(route('monitoring.amendments', ['mitra' => 'pt sinar timur']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('folders', null)
                ->where('partner.key', 'pt sinar timur')
                ->has('amendments.data', 2));

        $this->actingAs($lead)
            ->get(route('monitoring.amendments', ['mitra' => 'PT Sinar Timur', 'state' => 'disetujui']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('amendments.data', 1)
                ->where('amendments.data.0.number', 'KDD001.Amd/612/UPKD/2026')
                ->where('amendments.data.0.url', route('procurements.show', [$procurement, 'tab' => 'amandemen'])));

        $this->actingAs($lead)
            ->get(route('monitoring.amendments', ['search' => 'Sinar Timur']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('folders.data', 1)
                ->where('folders.data.0.total', 2));
    }

    public function test_delivery_orders_are_listed_with_their_value(): void
    {
        $lead = User::factory()->teamLeaderPengadaan()->create();
        $procurement = Procurement::factory()->create(['partner_name' => 'PT Mesin Jaya']);
        $order = ProcurementDeliveryOrder::factory()->for($procurement)->create(['state' => DeliveryOrderState::Terbit, 'number' => 'D00001.DO/612/UPKD/2026']);
        $order->items()->create(['description' => 'PISTON, ENGINE', 'quantity' => 2, 'unit' => 'Bh', 'unit_price' => 840_341_000]);

        $this->actingAs($lead)
            ->get(route('monitoring.delivery-orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('monitoring/delivery-orders')
                ->has('folders.data', 1)
                ->where('folders.data.0.name', 'PT Mesin Jaya')
                ->where('folders.data.0.done', 1));

        $this->actingAs($lead)
            ->get(route('monitoring.delivery-orders', ['mitra' => 'PT Mesin Jaya']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.number', 'D00001.DO/612/UPKD/2026')
                ->where('orders.data.0.total', 1_680_682_000)
                ->where('orders.data.0.url', route('procurements.show', [$order->procurement_id, 'tab' => 'do'])));
    }

    public function test_only_what_the_user_may_see_is_listed(): void
    {
        $planner = User::factory()->planner()->create();
        $own = Procurement::factory()->plannedBy($planner)->create(['partner_name' => 'PT Milik Sendiri']);

        ProcurementDeliveryOrder::factory()->for($own)->create();
        ProcurementDeliveryOrder::factory()->for(Procurement::factory()->create(['partner_name' => 'PT Orang Lain']))->create();

        $this->actingAs($planner)
            ->get(route('monitoring.delivery-orders'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('folders.data', 1)
                ->where('folders.data.0.name', 'PT Milik Sendiri'));

        $this->actingAs($planner)
            ->get(route('monitoring.delivery-orders', ['mitra' => 'PT Orang Lain']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));

        // The menu right guards every list.
        $this->actingAs(User::factory()->proposer(WorkDirector::factory()->create())->create())
            ->get(route('monitoring.amendments'))
            ->assertForbidden();
    }

    public function test_procurements_are_filed_per_partner(): void
    {
        $lead = User::factory()->teamLeaderPengadaan()->create();
        $done = ProgressStatus::factory()->category(StatusCategory::Selesai)->create();

        Procurement::factory()->create(['partner_name' => 'PT Sinar Timur', 'name' => 'Pekerjaan Satu']);
        Procurement::factory()->create(['partner_name' => ' pt sinar timur ', 'name' => 'Pekerjaan Dua', 'progress_status_id' => $done->id]);
        Procurement::factory()->create(['partner_name' => null, 'name' => 'Belum Bermitra']);

        $this->actingAs($lead)
            ->get(route('monitoring.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('monitoring/index')
                ->where('procurements', null)
                ->has('folders.data', 2)
                // The work without a partner comes last.
                ->where('folders.data.0.total', 2)
                ->where('folders.data.0.done', 1)
                ->where('folders.data.1.key', MonitoringController::NO_PARTNER)
                ->where('folders.data.1.name', 'Belum Ada Mitra'));

        $this->actingAs($lead)
            ->get(route('monitoring.index', ['mitra' => 'PT Sinar Timur']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('folders', null)
                ->has('procurements.data', 2));

        $this->actingAs($lead)
            ->get(route('monitoring.index', ['mitra' => MonitoringController::NO_PARTNER]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('procurements.data', 1)
                ->where('procurements.data.0.name', 'Belum Bermitra'));
    }

    public function test_the_contract_board_holds_the_partners_under_contract_now(): void
    {
        $lead = User::factory()->teamLeaderPengadaan()->create();
        $finished = ProgressStatus::factory()->category(StatusCategory::Selesai)->create();

        $running = Procurement::factory()->create([
            'number' => 'KDD010.SPK/612/UPKD/2026',
            'partner_name' => 'PT Sinar Timur',
            'execution_start_date' => '2026-10-01',
            'execution_duration_days' => 30,
            'value_after_negotiation' => 50_000_000,
        ]);
        // Finished, not yet a contract, or without a partner: not under contract.
        Procurement::factory()->create(['number' => 'KDD011.SPK/612/UPKD/2026', 'partner_name' => 'PT Sinar Timur', 'progress_status_id' => $finished->id]);
        Procurement::factory()->create(['partner_name' => 'CV Belum Kontrak']);
        Procurement::factory()->create(['number' => 'KDD012.PJ/612/UPKD/2026', 'partner_name' => null]);

        $this->actingAs($lead)
            ->get(route('monitoring.contracts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('monitoring/contracts')
                ->has('folders.data', 1)
                ->where('folders.data.0.name', 'PT Sinar Timur')
                ->where('folders.data.0.total', 1));

        $this->actingAs($lead)
            ->get(route('monitoring.contracts', ['mitra' => 'PT Sinar Timur']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('contracts.data', 1)
                ->where('contracts.data.0.number', 'KDD010.SPK/612/UPKD/2026')
                ->where('contracts.data.0.end_date', '2026-10-30')
                ->where('contracts.data.0.url', route('procurements.show', $running)));
    }

    public function test_every_list_narrows_to_a_year_and_month(): void
    {
        $lead = User::factory()->teamLeaderPengadaan()->create();

        $this->travelTo('2025-03-10');
        $old = Procurement::factory()->create(['partner_name' => 'PT Lama']);
        ProcurementAmendment::factory()->for($old)->create();

        $this->travelTo('2026-10-05');
        $new = Procurement::factory()->create(['partner_name' => 'PT Baru']);
        ProcurementAmendment::factory()->for($new)->create();

        $this->actingAs($lead)
            ->get(route('monitoring.index', ['year' => 2025]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('folders.data', 1)
                ->where('folders.data.0.name', 'PT Lama')
                ->where('filters.year', 2025)
                ->where('options.years', [2026, 2025]));

        $this->actingAs($lead)
            ->get(route('monitoring.index', ['year' => 2026, 'month' => 3]))
            ->assertInertia(fn (Assert $page) => $page->has('folders.data', 0));

        $this->actingAs($lead)
            ->get(route('monitoring.amendments', ['year' => 2026, 'month' => 10]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('folders.data', 1)
                ->where('folders.data.0.name', 'PT Baru'));

        // The shared filter narrows Daftar Pengadaan too.
        $this->actingAs($lead)
            ->get(route('procurements.index', ['year' => 2025, 'month' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.month', 3)
                ->where('procurements.data', fn (Collection $rows) => $rows->pluck('id')->all() === [$old->id]));
    }
}
