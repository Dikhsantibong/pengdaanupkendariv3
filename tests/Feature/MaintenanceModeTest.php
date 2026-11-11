<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Impersonation;
use App\Support\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The administrator can close the application for maintenance: the login
 * page says so, only the administrator signs in, and everyone else is kept
 * out until it is switched off.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected User $planner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->planner = User::factory()->planner()->create();
    }

    public function test_the_application_is_open_by_default(): void
    {
        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('maintenance', null));

        $this->actingAs($this->planner)->get(route('dashboard'))->assertOk();
        $this->get(route('public-monitoring.planning'))->assertOk();
    }

    public function test_the_administrator_switches_it_on_with_a_message(): void
    {
        $this->actingAs($this->administrator)
            ->put(route('maintenance.update'), [
                'active' => true,
                'message' => 'Pembaruan server, kembali pukul 16.00.',
                'until' => '2026-11-12T16:00',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(MaintenanceMode::isActive());

        $this->actingAs($this->administrator)
            ->get(route('maintenance.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('maintenance.active', true)
                ->where('maintenance.message', 'Pembaruan server, kembali pukul 16.00.')
                ->where('maintenance.updated_by', $this->administrator->name));

        // The administrator keeps working, reminded on every page.
        $this->actingAs($this->administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('maintenance.active', true));

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('maintenance.active', true)
                ->where('maintenance.message', 'Pembaruan server, kembali pukul 16.00.')
                ->whereNot('maintenance.until', null));
    }

    public function test_anyone_else_still_signed_in_is_signed_out(): void
    {
        $this->switchOn();

        $this->actingAs($this->planner)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();

        // A background request is answered, not redirected.
        $this->actingAs($this->planner)
            ->getJson(route('notifications.feed'))
            ->assertStatus(503);
    }

    public function test_only_the_administrator_signs_in(): void
    {
        $this->switchOn();

        $this->post(route('login.store'), ['email' => $this->planner->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // The same answer for an address nobody has.
        $this->post(route('login.store'), ['email' => 'tidak.ada@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->post(route('login.store'), ['email' => $this->administrator->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($this->administrator);
    }

    public function test_public_pages_close_and_reopen(): void
    {
        $this->switchOn();

        $this->get(route('home'))->assertRedirect(route('login'));
        $this->get(route('public-monitoring.planning'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();

        $this->actingAs($this->administrator)
            ->put(route('maintenance.update'), ['active' => false])
            ->assertRedirect();
        auth()->logout();

        $this->assertFalse(MaintenanceMode::isActive());
        $this->get(route('public-monitoring.planning'))->assertOk();
        $this->actingAs($this->planner)->get(route('dashboard'))->assertOk();
    }

    public function test_the_administrator_working_as_another_account_is_let_through(): void
    {
        $this->switchOn();

        $this->actingAs($this->planner)
            ->withSession([Impersonation::SESSION_KEY => [
                'impersonator_id' => $this->administrator->id,
                'impersonated_id' => $this->planner->id,
                'log_id' => null,
                'started_at' => now()->toIso8601String(),
            ]])
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_only_the_administrator_reaches_the_switch(): void
    {
        $this->actingAs($this->planner)->get(route('maintenance.edit'))->assertForbidden();
        $this->actingAs($this->planner)->put(route('maintenance.update'), ['active' => true])->assertForbidden();

        $this->assertFalse(MaintenanceMode::isActive());
    }

    protected function switchOn(): void
    {
        MaintenanceMode::update(true, null, null, $this->administrator);
    }
}
