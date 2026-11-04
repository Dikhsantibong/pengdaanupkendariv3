<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * The levels of the organisation an account can sit at.
     *
     * @var array<string, string>
     */
    public const LEVELS = [
        'direksi' => 'Direksi Pekerjaan / Asman',
        'tl' => 'Team Leader (TL)',
        'staf' => 'Staf',
        'lainnya' => 'Lainnya',
    ];

    /**
     * Show the user management screen.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value() ?: null,
            'role' => $request->string('role')->value() ?: null,
            // A division id, or "none" for the accounts outside every division.
            'work_director_id' => $request->string('work_director_id')->value() ?: null,
            'level' => in_array($request->query('level'), array_keys(self::LEVELS), true) ? (string) $request->query('level') : null,
            'team_leader_id' => $request->integer('team_leader_id') ?: null,
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : null,
            'missing_leader' => $request->boolean('missing_leader'),
            'view' => $request->query('view') === 'structure' ? 'structure' : 'table',
        ];

        return Inertia::render('users/index', [
            'users' => User::query()
                ->with(['workDirector', 'teamLeader'])
                ->withCount(['plannedProcurements', 'executedProcurements', 'teamMembers'])
                ->when($filters['search'], function (Builder $query, string $search): void {
                    $query->where(function (Builder $inner) use ($search): void {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('position', 'like', "%{$search}%");
                    });
                })
                ->when($filters['role'], fn (Builder $query, string $role) => $query->where('role', $role))
                ->when($filters['work_director_id'], fn (Builder $query, string $division) => $division === 'none'
                    ? $query->whereNull('work_director_id')
                    : $query->where('work_director_id', (int) $division))
                ->when($filters['level'], fn (Builder $query, string $level) => $this->whereLevel($query, $level))
                ->when($filters['team_leader_id'], fn (Builder $query, int $leader) => $query->where(fn (Builder $team) => $team
                    ->where('team_leader_id', $leader)
                    ->orWhere('id', $leader)))
                ->when($filters['status'], fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
                ->when($filters['missing_leader'], fn (Builder $query) => $this->whereLevel($query, 'staf')->whereNull('team_leader_id'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'position' => $user->position,
                    'work_director_id' => $user->work_director_id,
                    'work_director' => $user->workDirector?->name,
                    'team_leader_id' => $user->team_leader_id,
                    'team_leader' => $user->teamLeader?->name,
                    'level' => $this->levelOf($user),
                    'team_size' => $user->team_members_count,
                    'is_active' => $user->is_active,
                    'planned_count' => $user->planned_procurements_count,
                    'executed_count' => $user->executed_procurements_count,
                    'created_at' => $user->created_at?->toDateTimeString(),
                ])->all(),
            'filters' => $filters,
            'levels' => collect(self::LEVELS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'roles' => UserRole::options(),
            // Anyone may be a team leader: their staff's proposals reach them first.
            'teamLeaders' => User::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'position'])
                ->map(fn (User $leader): array => [
                    'value' => $leader->id,
                    'label' => $leader->position ? "{$leader->name} ({$leader->position})" : $leader->name,
                ])
                ->all(),
            'divisions' => WorkDirector::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (WorkDirector $division): array => ['value' => $division->id, 'label' => $division->name])
                ->all(),
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

    /**
     * The level an account sits at: the Direksi Pekerjaan role, a position
     * starting "TL" (or leading staff), a position starting "Staf", or
     * anything else.
     */
    protected function levelOf(User $user): string
    {
        $position = strtolower(trim((string) $user->position));

        return match (true) {
            $user->role === UserRole::DireksiPekerjaan && ! str_starts_with($position, 'tl ') => 'direksi',
            str_starts_with($position, 'tl ') || $position === 'tl' || ($user->team_members_count ?? 0) > 0 => 'tl',
            str_starts_with($position, 'staf') => 'staf',
            default => 'lainnya',
        };
    }

    /**
     * Keep the accounts at one level, by the same rules as levelOf().
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    protected function whereLevel(Builder $query, string $level): Builder
    {
        /** @param Builder<User> $inner */
        $isTeamLeader = function (Builder $inner): void {
            $inner->whereRaw("lower(trim(position)) like 'tl %'")
                ->orWhereRaw("lower(trim(position)) = 'tl'")
                ->orWhereHas('teamMembers');
        };

        return match ($level) {
            'direksi' => $query->where('role', UserRole::DireksiPekerjaan->value)
                ->where(fn (Builder $inner) => $inner->whereNull('position')->orWhereRaw("lower(trim(position)) not like 'tl %'")),
            'tl' => $query->where(fn (Builder $inner) => $inner
                ->where(fn (Builder $direksi) => $direksi
                    ->where('role', UserRole::DireksiPekerjaan->value)
                    ->whereRaw("lower(trim(position)) like 'tl %'"))
                ->orWhere(fn (Builder $other) => $other
                    ->where('role', '!=', UserRole::DireksiPekerjaan->value)
                    ->where($isTeamLeader))),
            'staf' => $query->where('role', '!=', UserRole::DireksiPekerjaan->value)
                ->whereRaw("lower(trim(position)) like 'staf%'")
                ->whereDoesntHave('teamMembers'),
            default => $query->where(fn (Builder $inner) => $inner->whereNull('position')
                ->orWhereRaw("lower(trim(position)) not like 'tl %' and lower(trim(position)) not like 'staf%' and lower(trim(position)) <> 'tl'"))
                ->where('role', '!=', UserRole::DireksiPekerjaan->value)
                ->whereDoesntHave('teamMembers'),
        };
    }
}
