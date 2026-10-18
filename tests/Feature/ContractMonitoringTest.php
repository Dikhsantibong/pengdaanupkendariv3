<?php

namespace Tests\Feature;

use App\Enums\AmendmentState;
use App\Enums\DeliveryOrderState;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementDeliveryOrder;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Monitoring menu lists every amendment and Delivery Order the user may
 * see, by state, filtered and paginated on the server.
 */
class ContractMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_amendments_are_listed_by_state_and_searchable(): void
    {
        $lead = User::factory()->teamLeaderPengadaan()->create();
        $procurement = Procurement::factory()->create(['name' => 'Overhaul Mesin MAK', 'partner_name' => 'PT Sinar Timur']);

        ProcurementAmendment::factory()->for($procurement)->create(['state' => AmendmentState::Disetujui, 'number' => 'KDD001.Amd/612/UPKD/2026']);
        ProcurementAmendment::factory()->for($procurement)->create(['sequence' => 2, 'state' => AmendmentState::MenungguTlPengadaan]);
        ProcurementAmendment::factory()->create(['state' => AmendmentState::MenungguPersetujuanTl]);

        $this->actingAs($lead)
            ->get(route('monitoring.amendments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('monitoring/amendments')
                ->has('amendments.data', 3)
                ->where('summary', fn ($summary) => collect($summary)->firstWhere('value', 'disetujui')['total'] === 1
                    && collect($summary)->firstWhere('value', 'menunggu_tl_pengadaan')['total'] === 1));

        $this->actingAs($lead)
            ->get(route('monitoring.amendments', ['state' => 'disetujui']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('amendments.data', 1)
                ->where('amendments.data.0.number', 'KDD001.Amd/612/UPKD/2026')
                ->where('amendments.data.0.url', route('procurements.show', [$procurement, 'tab' => 'amandemen'])));

        $this->actingAs($lead)
            ->get(route('monitoring.amendments', ['search' => 'Sinar Timur']))
            ->assertInertia(fn (Assert $page) => $page->has('amendments.data', 2));
    }

    public function test_delivery_orders_are_listed_with_their_value(): void
    {
        $lead = User::factory()->teamLeaderPengadaan()->create();
        $order = ProcurementDeliveryOrder::factory()->create(['state' => DeliveryOrderState::Terbit, 'number' => 'D00001.DO/612/UPKD/2026']);
        $order->items()->create(['description' => 'PISTON, ENGINE', 'quantity' => 2, 'unit' => 'Bh', 'unit_price' => 840_341_000]);

        $this->actingAs($lead)
            ->get(route('monitoring.delivery-orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('monitoring/delivery-orders')
                ->has('orders.data', 1)
                ->where('orders.data.0.number', 'D00001.DO/612/UPKD/2026')
                ->where('orders.data.0.total', 1_680_682_000)
                ->where('orders.data.0.url', route('procurements.show', [$order->procurement_id, 'tab' => 'do'])));
    }

    public function test_only_what_the_user_may_see_is_listed(): void
    {
        $planner = User::factory()->planner()->create();
        $own = Procurement::factory()->plannedBy($planner)->create();

        ProcurementDeliveryOrder::factory()->for($own)->create();
        ProcurementDeliveryOrder::factory()->create();

        $this->actingAs($planner)
            ->get(route('monitoring.delivery-orders'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.procurement.id', $own->id));

        // The menu right guards both lists.
        $this->actingAs(User::factory()->proposer(WorkDirector::factory()->create())->create())
            ->get(route('monitoring.amendments'))
            ->assertForbidden();
    }
}
