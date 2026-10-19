<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\Procurement;
use App\Support\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    ...$user->toArray(),
                    'role_label' => $user->role->label(),
                ],
                'permissions' => [
                    'manageMasterData' => $user !== null && Gate::forUser($user)->allows('manage-master-data'),
                    'manageUsers' => $user !== null && Gate::forUser($user)->allows('manage-users'),
                    'viewAllProcurements' => $user !== null && Gate::forUser($user)->allows('view-all-procurements'),
                    'createProcurement' => $user !== null && Gate::forUser($user)->allows('create', Procurement::class),
                    // May only propose work: the create screen is the proposal form.
                    'proposeOnly' => $user !== null
                        && ! $user->hasPermission(Permission::CreateProcurement)
                        && $user->hasPermission(Permission::ProposeWork),
                    'assignPic' => $user !== null && Gate::forUser($user)->allows('assign-pic'),
                    'manageVendorAssessments' => $user !== null && Gate::forUser($user)->allows('manage-vendor-assessments'),
                    // Which menus to show, keyed by the right's name.
                    'menus' => collect(Permission::cases())
                        ->filter(fn (Permission $permission): bool => str_starts_with($permission->value, 'menu.'))
                        ->mapWithKeys(fn (Permission $permission): array => [
                            substr($permission->value, 5) => $user !== null && $user->hasPermission($permission),
                        ])
                        // Whoever decides amendments or DOs gets the Approval menu too.
                        ->put('approvals', $user !== null && Gate::forUser($user)->allows('open-approvals'))
                        ->all(),
                ],
            ],
            // The administrator's "Pindah Akun" in the header: whether it may
            // be offered, and who is behind a switched session.
            'impersonation' => [
                'canStart' => Impersonation::canStart($user, $request),
                'active' => $user === null ? null : Impersonation::shared($request),
            ],
            'unreadNotifications' => $user === null ? 0 : $user->unreadNotifications()->count(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
