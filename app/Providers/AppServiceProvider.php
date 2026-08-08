<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Register the application wide authorization gates.
     */
    protected function configureGates(): void
    {
        Gate::define('manage-master-data', fn (User $user): bool => $user->isAdministrator());

        Gate::define('manage-users', fn (User $user): bool => $user->isAdministrator());

        // The vendor performance form is an administrator responsibility.
        Gate::define('manage-vendor-assessments', fn (User $user): bool => $user->isAdministrator());

        Gate::define('view-all-procurements', fn (User $user): bool => $user->isSupervisor());
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
