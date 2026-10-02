<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Requests\UpdateAccessRightsRequest;
use App\Services\AccessRights;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Hak Akses" screen: which role may do what.
 */
class AccessRightController extends Controller
{
    /**
     * Show the rights of every role as a grid.
     */
    public function index(): Response
    {
        $grants = AccessRights::grants();

        return Inertia::render('access-rights/index', [
            'permissions' => array_map(fn (Permission $permission): array => [
                'value' => $permission->value,
                'label' => $permission->label(),
                'description' => $permission->description(),
                'group' => $permission->group(),
                'defaults' => array_map(
                    fn (UserRole $role): string => $role->value,
                    $permission->defaultRoles(),
                ),
            ], Permission::cases()),
            'roles' => array_map(fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                // The administrator always holds every right.
                'locked' => $role === UserRole::Administrator,
            ], UserRole::cases()),
            'grants' => collect(AccessRights::configurableRoles())
                ->mapWithKeys(fn (UserRole $role): array => [
                    $role->value => array_values($grants[$role->value] ?? []),
                ])
                ->all(),
        ]);
    }

    /**
     * Save the rights of every configurable role.
     */
    public function update(UpdateAccessRightsRequest $request): RedirectResponse
    {
        /** @var array<string, array<int, string>> $grants */
        $grants = $request->validated('grants', []);

        AccessRights::replace($grants);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hak akses diperbarui.']);

        return back();
    }
}
