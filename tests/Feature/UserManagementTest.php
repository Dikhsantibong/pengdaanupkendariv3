<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Procurement;
use App\Models\User;
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
}
