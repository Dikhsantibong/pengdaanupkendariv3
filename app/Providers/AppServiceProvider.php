<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\ProgressStatusResolver;
use App\Support\ContractRequestQueue;
use App\Support\Impersonation;
use App\Support\PaymentQueue;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per application, so the statuses are read once per request.
        $this->app->singleton(ProgressStatusResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        $this->configureImpersonation();
        $this->configureErrorPages();
    }

    /**
     * Show the application's own error page, in its look, for a page that is
     * forbidden (403), missing (404), expired (419) or asked for too often
     * (429), and for a server failure (500/503) outside debug mode, where
     * the stack trace is more useful. A request asking for JSON keeps its
     * JSON answer.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            $status = $response->statusCode();
            $shown = config('app.debug') ? [403, 404, 419, 429] : [403, 404, 419, 429, 500, 503];

            if (! in_array($status, $shown, true) || $response->request->expectsJson()) {
                return null;
            }

            $retryAfter = $response->response->headers->get('Retry-After');

            $page = $response->render('error', [
                'status' => $status,
                // Our own reason for a refusal (in Indonesian), not the
                // framework's generic English one.
                'reason' => $status === 403 && $response->exception instanceof HttpExceptionInterface
                    && ! in_array($response->exception->getMessage(), ['', 'This action is unauthorized.'], true)
                    ? $response->exception->getMessage()
                    : null,
                'retryAfter' => $status === 429 && is_numeric($retryAfter) ? (int) $retryAfter : null,
            ]);

            // Shared data (the signed-in user among it) needs the session,
            // which a request stopped before the web middleware lacks.
            return $response->request->hasSession() ? $page->withSharedData() : $page;
        });
    }

    /**
     * Close the audit entry of a "Pindah Akun" session that signs out
     * without coming back first.
     */
    protected function configureImpersonation(): void
    {
        Event::listen(Logout::class, function (): void {
            $request = request();

            if ($request->hasSession() && Impersonation::isActive($request)) {
                Impersonation::close($request);
            }
        });
    }

    /**
     * Register the application wide authorization gates.
     */
    protected function configureGates(): void
    {
        // These follow the rights set on the "Hak Akses" screen.
        Gate::define('manage-master-data', fn (User $user): bool => $user->hasPermission(Permission::ManageMasterData));

        Gate::define('manage-vendor-assessments', fn (User $user): bool => $user->hasPermission(Permission::ManageVendorAssessments));

        Gate::define('view-all-procurements', fn (User $user): bool => $user->hasPermission(Permission::ViewAllProcurements));

        // The Penunjukan PIC board serves both team leaders: TL ICC appoints
        // planners, TL Pengadaan executors.
        Gate::define('assign-pic', fn (User $user): bool => $user->hasPermission(Permission::AssignPlanner)
            || $user->hasPermission(Permission::AssignExecutor));

        // Every right is also a gate under its own name, so a route can be
        // closed with `can:menu.reports` and the like.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }

        // The Approval menu: its own right, or a part in the amendments and
        // Delivery Orders (TL, Asman, Rendan, Laksdan), whose decisions are
        // queued there too.
        Gate::define('open-approvals', fn (User $user): bool => $user->hasPermission(Permission::MenuApprovals)
            || ContractRequestQueue::involves($user)
            || PaymentQueue::involves($user));

        // Users and access rights stay with the administrator alone: anyone
        // able to change them could raise their own role.
        Gate::define('manage-users', fn (User $user): bool => $user->isAdministrator());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Dates rendered for users and inside generated documents are Indonesian.
        CarbonImmutable::setLocale('id');

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
