<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * A forbidden, missing, expired or throttled page, and a server failure
 * outside debug mode, show the application's own error page.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::get('_uji/ditolak', fn () => abort(403, 'Tidak tersedia saat memakai akun lain.'));
            Route::get('_uji/terkunci', fn () => abort(403));
            Route::get('_uji/kedaluwarsa', fn () => abort(419));
            Route::get('_uji/rusak', fn () => throw new RuntimeException('Gagal.'));
            Route::get('_uji/dibatasi', fn () => 'ok')->middleware('throttle:1,1');
        });
    }

    public function test_a_missing_page_shows_the_404_page_with_the_signed_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 404)
                ->where('auth.user.id', $user->id));
    }

    public function test_a_visitor_sees_the_404_page_too(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('auth.user', null));
    }

    public function test_a_refusal_shows_the_403_page_with_our_own_reason_only(): void
    {
        $this->get('/_uji/ditolak')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 403)
                ->where('reason', 'Tidak tersedia saat memakai akun lain.'));

        $this->get('/_uji/terkunci')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('reason', null));
    }

    public function test_a_page_without_the_right_shows_the_403_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('master-data.document-templates.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
    }

    public function test_an_expired_page_shows_the_419_page(): void
    {
        $this->get('/_uji/kedaluwarsa')
            ->assertStatus(419)
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 419));
    }

    public function test_too_many_requests_shows_the_429_page_with_the_wait(): void
    {
        $this->get('/_uji/dibatasi')->assertOk();

        $this->get('/_uji/dibatasi')
            ->assertTooManyRequests()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 429)
                ->where('retryAfter', fn (int $seconds): bool => $seconds > 0 && $seconds <= 60));
    }

    public function test_a_server_failure_shows_the_500_page_only_outside_debug_mode(): void
    {
        config(['app.debug' => false]);

        $this->get('/_uji/rusak')
            ->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 500));

        config(['app.debug' => true]);

        // In debug mode the stack trace is more useful than a friendly page.
        $this->assertStringNotContainsString('"component":"error"', (string) $this->get('/_uji/rusak')->assertStatus(500)->getContent());
    }

    public function test_a_request_for_json_keeps_its_json_answer(): void
    {
        $this->getJson('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
