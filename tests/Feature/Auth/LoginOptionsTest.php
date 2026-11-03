<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Passkey sign-in and resetting a forgotten password are switched off
 * until turned on in .env, and can be turned back on at any time.
 */
class LoginOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_options_are_off_by_default(): void
    {
        config([
            'fortify.login_options.passkeys' => false,
            'fortify.login_options.password_reset' => false,
        ]);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canResetPassword', false)
                ->where('canUsePasskeys', false));

        $this->get(route('password.request'))->assertNotFound();
        $this->post(route('password.email'), ['email' => 'someone@example.com'])->assertNotFound();
        $this->get(route('password.reset', 'token'))->assertNotFound();
        $this->get(route('passkey.login-options'))->assertNotFound();
    }

    public function test_switched_off_passkeys_leave_the_security_settings(): void
    {
        config(['fortify.login_options.passkeys' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('canManagePasskeys', false));

        $this->actingAs($user)->get(route('passkey.registration-options'))->assertNotFound();
    }

    public function test_both_options_can_be_turned_back_on(): void
    {
        config([
            'fortify.login_options.passkeys' => true,
            'fortify.login_options.password_reset' => true,
        ]);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canResetPassword', true)
                ->where('canUsePasskeys', true));

        $this->get(route('password.request'))->assertOk();
        $this->get(route('passkey.login-options'))->assertOk();
    }

    public function test_signing_in_with_a_password_still_works(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }
}
