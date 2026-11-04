<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Procurement;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_register_a_new_user(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('users.store'), [
                'name' => 'Himatullah',
                'email' => 'himatullah@upkendari.test',
                'role' => UserRole::PicPerencana->value,
                'position' => 'PIC Perencana',
                'is_active' => true,
                'password' => 'rahasia-sekali',
                'password_confirmation' => 'rahasia-sekali',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'himatullah@upkendari.test')->firstOrFail();

        $this->assertSame(UserRole::PicPerencana, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_a_proposer_must_belong_to_a_division(): void
    {
        $administrator = User::factory()->administrator()->create();
        $division = WorkDirector::factory()->create();
        $payload = [
            'name' => 'Akmal Hidayat',
            'email' => 'akmal.hidayat@upkendari.test',
            'role' => UserRole::Pengusul->value,
            'position' => 'TL Operasi',
            'is_active' => true,
            'password' => 'rahasia-sekali',
            'password_confirmation' => 'rahasia-sekali',
        ];

        $this->actingAs($administrator)
            ->post(route('users.store'), $payload)
            ->assertSessionHasErrors('work_director_id');

        $this->actingAs($administrator)
            ->post(route('users.store'), [...$payload, 'work_director_id' => $division->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $division->id,
            User::query()->where('email', 'akmal.hidayat@upkendari.test')->value('work_director_id'),
        );
    }

    public function test_administrator_can_change_a_user_role_without_touching_the_password(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->planner()->create();
        $originalPassword = $user->password;

        $this->actingAs($administrator)
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => UserRole::PicPelaksana->value,
                'position' => 'PIC Pelaksana',
                'is_active' => true,
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame(UserRole::PicPelaksana, $user->role);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_the_last_active_administrator_cannot_be_demoted_or_removed(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->from(route('users.index'))
            ->put(route('users.update', $administrator), [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'role' => UserRole::PicPerencana->value,
                'position' => null,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Administrator, $administrator->refresh()->role);

        $this->actingAs($administrator)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $administrator))
            ->assertSessionHasErrors('name');

        $this->assertNotSoftDeleted($administrator);
    }

    public function test_non_administrators_cannot_manage_users(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_inactive_users_are_not_selectable_as_pic(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $inactivePlanner = User::factory()->planner()->create(['is_active' => false]);
        $procurement = Procurement::factory()->create();

        $this->actingAs($teamLeader)
            ->from(route('procurements.show', $procurement))
            ->put(route('procurements.pic.update', $procurement), [
                'planner_id' => $inactivePlanner->id,
                'executor_id' => null,
            ])
            ->assertSessionHasErrors('planner_id');
    }

    public function test_the_list_filters_by_division_level_team_leader_and_status(): void
    {
        $administrator = User::factory()->administrator()->create(['name' => 'Admin']);
        $operasi = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
        $engineering = WorkDirector::factory()->create(['name' => 'Asman Engineering']);

        User::factory()->workDirector($operasi)->create(['name' => 'Asman Operasi', 'position' => 'Asman Operasi']);
        $leader = User::factory()->proposer($operasi)->create(['name' => 'Budi Bahrun', 'position' => 'TL Energi Primer']);
        User::factory()->proposer($operasi)->create(['name' => 'Dewa', 'position' => 'Staf Energi Primer', 'team_leader_id' => $leader->id]);
        $loner = User::factory()->proposer($operasi)->create(['name' => 'Angga', 'position' => 'Staf Operasi']);
        User::factory()->proposer($engineering)->create(['name' => 'Lama', 'position' => 'Staf CBM', 'is_active' => false]);

        $names = fn (array $query): array => collect(
            $this->actingAs($administrator)->get(route('users.index', $query))->viewData('page')['props']['users'],
        )->pluck('name')->sort()->values()->all();

        $this->assertSame(['Angga', 'Asman Operasi', 'Budi Bahrun', 'Dewa'], $names(['work_director_id' => $operasi->id]));
        $this->assertSame(['Admin'], $names(['work_director_id' => 'none']));
        $this->assertSame(['Asman Operasi'], $names(['level' => 'direksi']));
        $this->assertSame(['Budi Bahrun'], $names(['level' => 'tl']));
        $this->assertSame(['Angga', 'Dewa', 'Lama'], $names(['level' => 'staf']));
        // A TL and the staff they lead.
        $this->assertSame(['Budi Bahrun', 'Dewa'], $names(['team_leader_id' => $leader->id]));
        $this->assertSame(['Lama'], $names(['status' => 'inactive']));
        $this->assertSame(['Budi Bahrun', 'Dewa'], $names(['search' => 'energi primer']));

        // "Angga" has no TL named "TL Operasi" to link to, so it stays unlinked.
        $this->assertNull($loner->refresh()->team_leader_id);
        $this->assertSame(['Angga'], $names(['missing_leader' => 1, 'status' => 'active']));

        $this->actingAs($administrator)
            ->get(route('users.index', ['view' => 'structure']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.view', 'structure')
                ->has('levels', 4)
                ->where('users', fn ($users) => collect($users)->firstWhere('name', 'Budi Bahrun')['level'] === 'tl'
                    && collect($users)->firstWhere('name', 'Budi Bahrun')['team_size'] === 1
                    && collect($users)->firstWhere('name', 'Dewa')['level'] === 'staf'));

    }
}
