<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Show the user management screen.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('users/index', [
            'users' => User::query()
                ->withCount(['plannedProcurements', 'executedProcurements'])
                ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($request->string('role')->value(), fn ($query, string $role) => $query->where('role', $role))
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'position' => $user->position,
                    'is_active' => $user->is_active,
                    'planned_count' => $user->planned_procurements_count,
                    'executed_count' => $user->executed_procurements_count,
                    'created_at' => $user->created_at?->toDateTimeString(),
                ])->all(),
            'filters' => [
                'search' => $request->string('search')->trim()->value() ?: null,
                'role' => $request->string('role')->value() ?: null,
            ],
            'roles' => UserRole::options(),
        ]);
    }

    /**
     * Register a new application user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->except('password'));
        $user->password = $request->string('password')->value();
        $user->forceFill(['email_verified_at' => now()]);
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pengguna {$user->name} ditambahkan."]);

        return back();
    }

    /**
     * Update an existing application user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->guardLastAdministrator($request, $user);

        $user->fill($request->safe()->except('password'));

        if ($request->filled('password')) {
            $user->password = $request->string('password')->value();
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data pengguna diperbarui.']);

        return back();
    }

    /**
     * Deactivate a user while keeping their procurement history.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'name' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ]);
        }

        if ($user->isAdministrator() && $this->administratorCount() <= 1) {
            throw ValidationException::withMessages([
                'name' => 'Minimal satu administrator harus tetap aktif.',
            ]);
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna dinonaktifkan.']);

        return back();
    }

    /**
     * Prevent the system from losing its last active administrator.
     */
    protected function guardLastAdministrator(Request $request, User $user): void
    {
        $wasAdministrator = $user->isAdministrator();
        $staysAdministrator = $request->string('role')->value() === UserRole::Administrator->value
            && $request->boolean('is_active');

        if ($wasAdministrator && ! $staysAdministrator && $this->administratorCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Minimal satu administrator aktif harus tetap ada.',
            ]);
        }
    }

    /**
     * Count the active administrators of the system.
     */
    protected function administratorCount(): int
    {
        return User::query()
            ->active()
            ->withRole([UserRole::Administrator])
            ->count();
    }
}
