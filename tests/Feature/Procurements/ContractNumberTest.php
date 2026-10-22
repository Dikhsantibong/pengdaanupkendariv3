<?php

namespace Tests\Feature\Procurements;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The contract number is given in the identity of the procurement by its
 * execution PIC or TL Pengadaan, no longer in the Lampiran Surat Pesanan
 * step (which still asks for it to be there).
 */
class ContractNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_execution_pic_gives_the_contract_number(): void
    {
        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->planningApproved()->executedBy($executor)->create(['number' => 'USL/2026/10/0001']);

        $this->assertFalse($procurement->hasContractNumber());

        $this->actingAs($executor)
            ->put(route('procurements.contract-number.update', $procurement), ['number' => '  KDD058.SPPL/612/UPKD/2026 '])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $procurement->refresh();

        $this->assertSame('KDD058.SPPL/612/UPKD/2026', $procurement->number);
        $this->assertTrue($procurement->hasContractNumber());
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'description' => 'Nomor kontrak diisi: KDD058.SPPL/612/UPKD/2026.',
        ]);
    }

    public function test_tl_pengadaan_may_give_or_correct_it_too(): void
    {
        $procurement = Procurement::factory()->planningApproved()->create(['number' => 'KDD001.SPK/612/UPKD/2026']);

        $this->actingAs(User::factory()->teamLeaderPengadaan()->create())
            ->put(route('procurements.contract-number.update', $procurement), ['number' => 'KDD002.SPK/612/UPKD/2026'])
            ->assertSessionHasNoErrors();

        $this->assertSame('KDD002.SPK/612/UPKD/2026', $procurement->refresh()->number);
    }

    public function test_others_and_an_unapproved_plan_may_not(): void
    {
        $procurement = Procurement::factory()->planningApproved()->create(['number' => 'USL/2026/10/0002']);

        $this->actingAs(User::factory()->planner()->create())
            ->put(route('procurements.contract-number.update', $procurement), ['number' => 'KDD003.SPK/612/UPKD/2026'])
            ->assertForbidden();

        $executor = User::factory()->executor()->create();
        $planning = Procurement::factory()->executedBy($executor)->create(['number' => 'USL/2026/10/0003']);

        $this->actingAs($executor)
            ->put(route('procurements.contract-number.update', $planning), ['number' => 'KDD004.SPK/612/UPKD/2026'])
            ->assertForbidden();

        $this->assertSame('USL/2026/10/0002', $procurement->refresh()->number);
    }

    public function test_a_registration_number_or_one_taken_is_refused(): void
    {
        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->planningApproved()->executedBy($executor)->create(['number' => 'USL/2026/10/0004']);
        Procurement::factory()->create(['number' => 'KDD009.SPK/612/UPKD/2026']);

        $this->actingAs($executor)
            ->put(route('procurements.contract-number.update', $procurement), ['number' => 'PGD/2026/10/0001'])
            ->assertSessionHasErrors('number');

        $this->actingAs($executor)
            ->put(route('procurements.contract-number.update', $procurement), ['number' => 'KDD009.SPK/612/UPKD/2026'])
            ->assertSessionHasErrors('number');

        $this->assertSame('USL/2026/10/0004', $procurement->refresh()->number);
    }

    public function test_the_page_offers_the_field_to_who_may_fill_it(): void
    {
        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->planningApproved()->executedBy($executor)->create(['number' => 'USL/2026/10/0005']);

        $this->actingAs($executor)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.updateContractNumber', true)
                ->where('contractNumber.value', null));

        // TL ICC follows the execution but does not give the number.
        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page->where('can.updateContractNumber', false));
    }
}
