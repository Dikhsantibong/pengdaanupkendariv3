<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the administrator, TL Perencanaan and PIC accounts listed in the spec.
     *
     * The login address is stated rather than derived from the name, so that
     * renaming an account never orphans the credentials behind it.
     *
     * @var array<int, array{email: string, name: string, role: UserRole, position: string}>
     */
    protected const ACCOUNTS = [
        [
            'email' => 'administrator.sistem@upkendari.test',
            'name' => 'Administrator Sistem',
            'role' => UserRole::Administrator,
            'position' => 'Administrator',
        ],
        [
            'email' => 'team.leader.pengadaan@upkendari.test',
            'name' => 'TL Perencanaan',
            'role' => UserRole::TeamLeader,
            'position' => 'TL Perencanaan',
        ],
        [
            'email' => 'himatullah@upkendari.test',
            'name' => 'Himatullah',
            'role' => UserRole::PicPerencana,
            'position' => 'PIC Perencana',
        ],
        [
            'email' => 'bastial@upkendari.test',
            'name' => 'Bastial',
            'role' => UserRole::PicPerencana,
            'position' => 'PIC Perencana',
        ],
        [
            'email' => 'iklan.nano@upkendari.test',
            'name' => 'Iklan Nano',
            'role' => UserRole::PicPerencana,
            'position' => 'PIC Perencana',
        ],
        [
            'email' => 'putu.wisna@upkendari.test',
            'name' => 'Putu Wisna',
            'role' => UserRole::PicPerencana,
            'position' => 'PIC Perencana',
        ],
        [
            'email' => 'sabrin@upkendari.test',
            'name' => 'Sabrin',
            'role' => UserRole::PicPelaksana,
            'position' => 'PIC Pelaksana',
        ],
        [
            'email' => 'ahmad.bukhari@upkendari.test',
            'name' => 'Ahmad Bukhari',
            'role' => UserRole::PicPelaksana,
            'position' => 'PIC Pelaksana',
        ],
        [
            'email' => 'supriadi@upkendari.test',
            'name' => 'Supriadi',
            'role' => UserRole::PicPelaksana,
            'position' => 'PIC Pelaksana',
        ],
    ];

    /**
     * Seed the accounts, keeping the password of an existing account intact.
     */
    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            $user->fill([
                'name' => $account['name'],
                'role' => $account['role'],
                'position' => $account['position'],
                'is_active' => true,
            ]);

            if (! $user->exists) {
                $user->password = Hash::make('password');
                $user->email_verified_at = now();
            }

            $user->save();
        }
    }
}
