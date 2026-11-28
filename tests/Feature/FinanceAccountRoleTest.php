<?php

namespace Tests\Feature;

use App\Enums\CoaRequestStatus;
use App\Enums\UserRole;
use App\Models\CoaRequest;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Finance accounts created as Pengusul get their Keuangan role, so TL
 * Keuangan only activates COA, and the requests of the Pengusul reach it.
 */
class FinanceAccountRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_accounts_left_as_pengusul_become_keuangan_and_verify_coa(): void
    {
        $division = WorkDirector::factory()->create();
        $tl = User::factory()->proposer($division)->create(['email' => 'nova.sang.jaya@upkendari.go.id', 'position' => 'TL Keuangan']);
        $staff = User::factory()->proposer($division)->create(['email' => 'muhammad.jaya.junaid@upkendari.go.id', 'position' => 'Staf Keuangan']);
        $byPosition = User::factory()->proposer($division)->create(['position' => 'Staf Keuangan']);
        // A role an administrator chose stays; so does a real Pengusul.
        $chosen = User::factory()->role(UserRole::DireksiPekerjaan)->create(['email' => 'inggrid.novela.pitaloka@upkendari.go.id', 'position' => 'Staf Keuangan']);
        $pengusul = User::factory()->proposer($division)->create(['position' => 'TL Operasi']);

        $migration = require database_path('migrations/2026_11_28_063158_give_the_finance_accounts_their_keuangan_role.php');
        $migration->up();

        $this->assertSame(UserRole::Keuangan, $tl->fresh()->role);
        $this->assertSame(UserRole::Keuangan, $staff->fresh()->role);
        $this->assertSame($tl->id, $staff->fresh()->team_leader_id);
        $this->assertSame(UserRole::Keuangan, $byPosition->fresh()->role);
        $this->assertSame(UserRole::DireksiPekerjaan, $chosen->fresh()->role);
        $this->assertSame(UserRole::Pengusul, $pengusul->fresh()->role);

        // The Pengusul requests; TL Keuangan finds it to activate, and cannot request.
        $this->actingAs($pengusul)
            ->post(route('coa-requests.store'), [
                'segment_1' => 'A', 'segment_2' => '', 'segment_3' => '21', 'segment_4' => '372',
                'segment_5' => '000', 'segment_6' => '23', 'segment_7' => '20',
                'expense_element' => 'F102/JBJSLN', 'action' => 'request_activation',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($tl->fresh())
            ->get(route('coa-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canRequest', false)
                ->where('canManage', true)
                ->where('summary.pending', 1)
                ->where('auth.permissions.requestCoa', false)
                ->where('auth.permissions.manageCoaRequests', true)
                ->where('coaRequests.data.0.can_activate', true));

        $this->actingAs($tl->fresh())
            ->post(route('coa-requests.store'), ['action' => 'save'])
            ->assertForbidden();

        $coa = CoaRequest::query()->firstOrFail();
        $this->actingAs($tl->fresh())->post(route('coa-requests.activate', $coa))->assertRedirect();
        $this->assertSame(CoaRequestStatus::Active, $coa->fresh()->status);
    }
}
