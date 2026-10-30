<?php

namespace Tests\Feature\Procurements;

use App\Enums\DeliveryOrderState;
use App\Enums\UserRole;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementDeliveryOrder;
use App\Models\UnitManager;
use App\Models\User;
use App\Models\WorkDirector;
use Database\Seeders\DocumentTemplateLibrarySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Delivery Orders of a KHS contract: the proposer requests (nota dinas
 * usulan + lines from the contract's price list), TL and Asman approve,
 * Rendan writes the Nota Dinas DO, Laksdan dates and issues the DO.
 */
class DeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected WorkDirector $division;

    protected User $proposer;

    protected User $teamLeader;

    protected User $director;

    protected User $planner;

    protected User $executor;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->seed(MasterDataSeeder::class);
        $this->seed(DocumentTemplateLibrarySeeder::class);

        UnitManager::query()->update(['is_active' => false]);
        UnitManager::factory()->create(['name' => 'RUBERTUS TRI ADI NUGROHO', 'is_active' => true, 'sort_order' => 0]);

        $this->division = WorkDirector::factory()->create();
        $this->teamLeader = User::factory()->proposer($this->division)->create();
        $this->proposer = User::factory()->proposer($this->division)->create(['team_leader_id' => $this->teamLeader->id]);
        $this->director = User::factory()->workDirector($this->division)->create();
        $this->planner = User::factory()->planner()->create();
        $this->executor = User::factory()->executor()->create();

        $this->procurement = $this->khsContract();
    }

    public function test_the_tab_is_only_offered_for_a_khs_spk_or_pj(): void
    {
        $this->actingAs($this->proposer)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliveryOrders.available', true)
                ->where('can.startDeliveryOrder', true));

        $lumpSum = $this->khsContract(['contract_type_id' => ContractType::query()->where('code', 'lumsum')->value('id')]);

        $this->actingAs($this->proposer)
            ->get(route('procurements.show', $lumpSum))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliveryOrders', null)
                ->where('can.startDeliveryOrder', false));

        $this->actingAs($this->proposer)
            ->post(route('procurements.delivery-orders.store', $lumpSum))
            ->assertForbidden();
    }

    public function test_a_do_runs_from_the_request_to_its_issue(): void
    {
        $this->priceList();
        $order = $this->request();

        // The TL, then the Asman.
        $this->actingAs($this->proposer)->post(route('procurements.delivery-orders.submit', [$this->procurement, $order]))->assertRedirect();
        $this->assertSame(DeliveryOrderState::MenungguPersetujuanTl, $order->refresh()->state);

        $this->actingAs($this->proposer)
            ->post(route('procurements.delivery-orders.decide', [$this->procurement, $order]), ['approved' => true])
            ->assertForbidden();

        $this->decide($this->teamLeader, $order);
        $this->assertSame(DeliveryOrderState::MenungguPersetujuanAsman, $order->refresh()->state);

        $this->decide($this->director, $order);
        $this->assertSame(DeliveryOrderState::DiRendan, $order->refresh()->state);

        // Rendan: the Nota Dinas DO, then on to Laksdan.
        $this->actingAs($this->executor)
            ->put(route('procurements.delivery-orders.memo', [$this->procurement, $order]), ['do_memo_number' => 'X'])
            ->assertForbidden();

        $this->actingAs($this->planner)
            ->post(route('procurements.delivery-orders.forward', [$this->procurement, $order]))
            ->assertRedirect();
        $this->assertSame(DeliveryOrderState::DiRendan, $order->refresh()->state, 'Tanpa nota dinas DO tidak bisa diteruskan.');

        $this->actingAs($this->planner)
            ->put(route('procurements.delivery-orders.memo', [$this->procurement, $order]), [
                'do_memo_number' => '0123/DAN.01.03/C01030300/2026',
                'do_memo_date' => '2026-09-25',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->planner)
            ->post(route('procurements.delivery-orders.forward', [$this->procurement, $order]))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(DeliveryOrderState::DiLaksdan, $order->state);
        $this->assertSame('D00001.DO/612/UPKD/'.now()->format('Y'), $order->number);

        // TL ICC follows the execution only.
        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->post(route('procurements.delivery-orders.issue', [$this->procurement, $order]))
            ->assertForbidden();

        // Laksdan: date, delivery terms, then issue.
        $this->actingAs($this->executor)
            ->put(route('procurements.delivery-orders.issue-data', [$this->procurement, $order]), [
                'number' => 'D26252.DO/612/UPKD/2026',
                'order_date' => '2026-09-29',
                'vendor_code' => 'V07051',
                'work_description' => 'CRITIKAL PART PENDUKUNG OVERHAUL MESIN MAK 8M 453 AK/C',
                'delivery_place' => 'PT PLN Nusantara Power UP Kendari PLTD Wua Wua',
                'delivery_days' => 90,
                'items' => $order->items->map(fn ($item): array => ['contract_item_id' => $item->procurement_contract_item_id, 'quantity' => (float) $item->quantity])->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->executor)
            ->post(route('procurements.delivery-orders.issue', [$this->procurement, $order]))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(DeliveryOrderState::Terbit, $order->state);
        $this->assertNotNull($order->procurement_document_id);

        $body = $order->document->rendered_body;
        $this->assertStringContainsString('D26252.DO/612/UPKD/2026', $body);
        $this->assertStringContainsString('MATERIAL MAK 8M 453 C', $body);
        $this->assertStringContainsString('CONNECTING ROD, ENGINE', $body);
        $this->assertStringContainsString('1.647.538.000', $body);
        $this->assertStringContainsString('V07051', $body);
        $this->assertStringContainsString('sembilan puluh', $body);
        $this->assertStringContainsString('RUBERTUS TRI ADI NUGROHO', $body);
    }

    public function test_do_numbers_follow_one_sequence_across_khs_contracts(): void
    {
        $other = $this->khsContract();

        foreach ([$this->procurement, $other] as $procurement) {
            $order = ProcurementDeliveryOrder::factory()->for($procurement)->create([
                'state' => DeliveryOrderState::DiRendan,
                'do_memo_number' => 'ND-1',
                'do_memo_date' => now()->toDateString(),
            ]);

            $this->actingAs($this->planner)
                ->post(route('procurements.delivery-orders.forward', [$procurement, $order]))
                ->assertRedirect();
        }

        $year = now()->format('Y');

        $this->assertSame(
            ["D00001.DO/612/UPKD/{$year}", "D00002.DO/612/UPKD/{$year}"],
            ProcurementDeliveryOrder::query()->orderBy('id')->pluck('number')->all(),
        );
    }

    public function test_lines_come_from_this_contracts_price_list_and_keep_their_price(): void
    {
        $this->priceList();
        $order = $this->request();
        $foreign = $this->khsContract()->contractItems()->create(['description' => 'Lain', 'unit' => 'Bh', 'unit_price' => 1]);

        $this->actingAs($this->proposer)
            ->put(route('procurements.delivery-orders.request', [$this->procurement, $order]), [
                'request_memo_number' => 'ND-1',
                'items' => [['contract_item_id' => $foreign->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items.0.contract_item_id');

        // Changing the price list later leaves the DO's lines alone.
        $item = $this->procurement->contractItems()->firstOrFail();
        $item->update(['unit_price' => 1]);

        $this->assertSame('823769000.00', $order->items()->firstOrFail()->unit_price);
    }

    public function test_only_laksdan_keeps_the_price_list(): void
    {
        $this->actingAs($this->proposer)
            ->put(route('procurements.delivery-orders.contract-items', $this->procurement), ['items' => []])
            ->assertForbidden();

        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->put(route('procurements.delivery-orders.contract-items', $this->procurement), ['items' => []])
            ->assertForbidden();

        $this->priceList();
        $this->assertSame(6, $this->procurement->contractItems()->count());
    }

    /**
     * A KHS SPK with its contract drawn, proposed by the proposer.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function test_in_k3_and_lingkungan_the_team_leader_approves_a_do_once(): void
    {
        // The TL leads the division (K3 KAM, Lingkungan); no Asman above.
        $this->director->forceFill(['is_active' => false])->save();
        $this->teamLeader->forceFill(['role' => UserRole::DireksiPekerjaan])->save();
        $this->priceList();
        $order = $this->request();

        $this->actingAs($this->proposer)->post(route('procurements.delivery-orders.submit', [$this->procurement, $order]));
        $this->assertSame(DeliveryOrderState::MenungguPersetujuanAsman, $order->refresh()->state);

        $this->decide($this->teamLeader, $order);
        $this->assertSame(DeliveryOrderState::DiRendan, $order->refresh()->state);
    }

    public function test_a_do_of_a_division_without_an_asman_account_goes_on_after_the_team_leader(): void
    {
        $this->director->forceFill(['is_active' => false])->save();
        $this->priceList();
        $order = $this->request();

        $this->actingAs($this->proposer)->post(route('procurements.delivery-orders.submit', [$this->procurement, $order]));
        $this->assertSame(DeliveryOrderState::MenungguPersetujuanTl, $order->refresh()->state);

        $this->decide($this->teamLeader, $order);

        // On to Rendan, not left waiting on an Asman who is not there.
        $this->assertSame(DeliveryOrderState::DiRendan, $order->refresh()->state);

        // One already waiting at the Asman level is the TL's to decide.
        $waiting = $this->request();
        $waiting->forceFill(['state' => DeliveryOrderState::MenungguPersetujuanAsman, 'submitted_at' => now()])->save();

        $this->actingAs($this->teamLeader)
            ->get(route('approvals.index', ['queue' => 'delivery-order']))
            ->assertInertia(fn (Assert $page) => $page->where('requests.data.0.id', $waiting->id));

        $this->decide($this->teamLeader, $waiting);
        $this->assertSame(DeliveryOrderState::DiRendan, $waiting->refresh()->state);
    }

    protected function khsContract(array $attributes = []): Procurement
    {
        $procurement = Procurement::factory()
            ->planningApproved()
            ->plannedBy($this->planner)
            ->executedBy($this->executor)
            ->create([
                'work_director_id' => $this->division->id,
                'created_by' => $this->proposer->id,
                'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
                'contract_type_id' => ContractType::query()->where('code', 'khs')->value('id'),
                'procurement_method_id' => null,
                'partner_name' => 'KONSORSIUM PT. SINAR TIMUR UTAMA RAYA',
                'partner_director_name' => 'JEFFRY YUWANDI',
                ...$attributes,
            ]);

        $procurement->documents()->create([
            'document_type_id' => DocumentType::query()->where('code', 'spk')->value('id'),
            'title' => 'SPK',
            'file_name' => 'spk.html',
            'template_version' => 0,
            'rendered_body' => '<p>SPK</p>',
            'generated_at' => now(),
        ]);

        return $procurement;
    }

    /**
     * Laksdan keeps the contract's price list, grouped as on the contract.
     */
    protected function priceList(): void
    {
        $line = fn (string $group, string $description, string $part, int $price): array => [
            'group_name' => $group, 'description' => $description, 'part_number' => $part, 'unit' => 'Bh', 'unit_price' => $price,
        ];

        $this->actingAs($this->executor)
            ->put(route('procurements.delivery-orders.contract-items', $this->procurement), ['items' => [
                $line('MATERIAL MAK 8M 453 C', 'CONNECTING ROD, ENGINE', '1.2810 A', 823_769_000),
                $line('MATERIAL MAK 8M 453 C', 'PISTON, ENGINE', '1.2670 N', 840_341_000),
                $line('MATERIAL MAK 8M 453 AK', 'PISTON, ENGINE', '1.2670 N', 840_341_000),
                $line('MATERIAL MAK 8M 453 AK', 'CYLINDER LINER', '1.1101-002', 175_830_000),
                $line('MATERIAL MAK 8M 453 AK', 'INJECTION PUMP', '1.3597 LL', 191_594_000),
                $line('MATERIAL MAK 8M 453 AK', 'LUBE OIL PUMP', '1.6417', 375_102_000),
            ]])
            ->assertSessionHasNoErrors();
    }

    /**
     * The proposer requests a DO: two connecting rods and a liner.
     */
    protected function request(): ProcurementDeliveryOrder
    {
        $this->actingAs($this->proposer)
            ->post(route('procurements.delivery-orders.store', $this->procurement))
            ->assertRedirect();

        $order = $this->procurement->deliveryOrders()->latest('id')->firstOrFail();
        $items = $this->procurement->contractItems()->get();

        $this->actingAs($this->proposer)
            ->put(route('procurements.delivery-orders.request', [$this->procurement, $order]), [
                'request_memo_number' => '0456/USL/2026',
                'request_memo_date' => '2026-09-20',
                'items' => [
                    ['contract_item_id' => $items[0]->id, 'quantity' => 2],
                    ['contract_item_id' => $items[3]->id, 'quantity' => 4],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->proposer)
            ->post(route('procurements.delivery-orders.upload-request', [$this->procurement, $order]), [
                'file' => UploadedFile::fake()->create('nodis-usulan.pdf', 30, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        return $order->refresh()->load('items');
    }

    protected function decide(User $user, ProcurementDeliveryOrder $order): void
    {
        $this->actingAs($user)
            ->post(route('procurements.delivery-orders.decide', [$this->procurement, $order]), ['approved' => true])
            ->assertRedirect();
    }
}
