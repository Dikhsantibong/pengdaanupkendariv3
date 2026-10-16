<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\ProgressStatusResolver;
use App\Support\Impersonation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
