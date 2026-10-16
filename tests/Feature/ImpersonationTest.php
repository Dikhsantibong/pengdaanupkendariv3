<?php

namespace Tests\Feature;

use App\Models\ImpersonationLog;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The administrator's "Pindah Akun": switching into another account from the
 * header and coming back, with every guard around it.
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create(['name' => 'Admin Sistem']);
        $this->target = User::factory()->executor()->create(['name' => 'Sabrin']);
    }

    public function test_the_administrator_switches_with_their_password_and_comes_back(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('impersonation.canStart', true)
                ->where('impersonation.active', null));

        $this->actingAs($this->administrator)
            ->post(route('impersonation.store', $this->target), ['password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->target);

        $log = ImpersonationLog::query()->sole();
        $this->assertSame($this->administrator->id, $log->impersonator_id);
        $this->assertSame($this->target->id, $log->impersonated_id);
        $this->assertNull($log->ended_at);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $this->target->id)
                ->where('impersonation.canStart', false)
                ->where('impersonation.active.impersonator', 'Admin Sistem'));

        $this->delete(route('impersonation.destroy'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->administrator);
        $this->assertNotNull($log->refresh()->ended_at);
        $this->assertFalse(session()->has(Impersonation::SESSION_KEY));
    }

    public function test_a_wrong_or_missing_password_does_not_switch(): void
    {
        $this->actingAs($this->administrator)
            ->post(route('impersonation.store', $this->target), ['password' => 'salah'])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->administrator)
            ->post(route('impersonation.store', $this->target))
            ->assertSessionHasErrors('password');

        $this->assertAuthenticatedAs($this->administrator);
        $this->assertDatabaseCount('impersonation_logs', 0);
    }

    public function test_a_recent_confirmation_spares_the_password(): void
    {
        $this->actingAs($this->administrator)
            ->withSession([Impersonation::CONFIRMED_KEY => time() - 60])
            ->post(route('impersonation.store', $this->target))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->target);

        // An old confirmation is not enough.
        $other = User::factory()->planner()->create();

        $this->actingAs($this->administrator)
            ->withSession([Impersonation::CONFIRMED_KEY => time() - Impersonation::CONFIRMATION_TIMEOUT - 1])
            ->post(route('impersonation.store', $other))
            ->assertSessionHasErrors('password');
    }

    public function test_only_the_administrator_may_switch_and_list_accounts(): void
    {
        foreach ([User::factory()->teamLeaderPengadaan()->create(), User::factory()->teamLeaderIcc()->create(), User::factory()->planner()->create()] as $user) {
            $this->actingAs($user)->get(route('impersonation.index'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('impersonation.store', $this->target), ['password' => 'password'])
                ->assertForbidden();
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertInertia(fn (Assert $page) => $page->where('impersonation.canStart', false));
        }

        $this->assertDatabaseCount('impersonation_logs', 0);

        // A guest is sent to sign in.
        $this->app['auth']->guard('web')->logout();

        $this->post(route('impersonation.store', $this->target))->assertRedirect(route('login'));
    }

    public function test_administrators_inactive_and_deleted_accounts_cannot_be_switched_into(): void
    {
        $otherAdministrator = User::factory()->administrator()->create();
        $inactive = User::factory()->planner()->create(['is_active' => false]);
        $deleted = User::factory()->planner()->create();
        $deleted->delete();

        foreach ([$otherAdministrator, $inactive, $this->administrator] as $user) {
            $this->actingAs($this->administrator)
                ->post(route('impersonation.store', $user), ['password' => 'password'])
                ->assertForbidden();
        }

        $this->actingAs($this->administrator)
            ->post(route('impersonation.store', $deleted->id), ['password' => 'password'])
            ->assertNotFound();

        $this->assertAuthenticatedAs($this->administrator);

        $this->actingAs($this->administrator)
            ->getJson(route('impersonation.index'))
            ->assertOk()
            ->assertJsonPath('needsPassword', true)
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $this->target->id);

        $this->actingAs($this->administrator)
            ->getJson(route('impersonation.index', ['search' => 'tidak-ada']))
            ->assertOk()
            ->assertJsonCount(0, 'users');
    }

    public function test_a_switched_session_cannot_switch_again_or_touch_security_settings(): void
    {
        $this->switchIn();

        $this->get(route('impersonation.index'))->assertForbidden();
        $this->post(route('impersonation.store', User::factory()->planner()->create()))->assertForbidden();

        // The borrowed account's credentials, profile and security stay closed.
        $this->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'kata-sandi-baru-123',
            'password_confirmation' => 'kata-sandi-baru-123',
        ])->assertForbidden();
        $this->patch(route('profile.update'), ['name' => 'Diubah', 'email' => 'baru@example.com'])->assertForbidden();
        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
        $this->get(route('security.edit'))->assertForbidden();
        $this->post(route('password.confirm.store'), ['password' => 'password'])->assertForbidden();

        $this->assertSame('Sabrin', $this->target->refresh()->name);

        // Everyday work goes on.
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_a_password_confirmation_does_not_carry_into_the_borrowed_account(): void
    {
        $this->actingAs($this->administrator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('impersonation.store', $this->target), ['password' => 'password']);

        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_coming_back_signs_out_an_administrator_who_lost_the_role(): void
    {
        $this->switchIn();

        $this->administrator->forceFill(['is_active' => false])->save();

        $this->delete(route('impersonation.destroy'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotNull(ImpersonationLog::query()->sole()->ended_at);
    }

    public function test_coming_back_needs_a_switched_session(): void
    {
        $this->actingAs($this->target)
            ->delete(route('impersonation.destroy'))
            ->assertForbidden();

        $this->assertAuthenticatedAs($this->target);
    }

    public function test_signing_out_while_switched_closes_the_audit_entry(): void
    {
        $this->switchIn();

        $this->post(route('logout'));

        $this->assertGuest();
        $this->assertNotNull(ImpersonationLog::query()->sole()->ended_at);
    }

    public function test_a_stale_switch_record_is_dropped(): void
    {
        $this->actingAs($this->target)
            ->withSession([Impersonation::SESSION_KEY => [
                'impersonator_id' => $this->administrator->id,
                'impersonated_id' => $this->administrator->id,
                'log_id' => 999,
            ]])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('impersonation.active', null));

        $this->delete(route('impersonation.destroy'))->assertForbidden();
        $this->assertAuthenticatedAs($this->target);
    }

    /**
     * Switch the administrator into the target account.
     */
    protected function switchIn(): void
    {
        $this->actingAs($this->administrator)
            ->post(route('impersonation.store', $this->target), ['password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->target);
    }
}
