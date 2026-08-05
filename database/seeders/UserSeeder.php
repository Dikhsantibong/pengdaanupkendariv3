<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the administrator, team leader and PIC accounts listed in the spec.
     */
    public function run(): void
    {
        $accounts = [
            ['Administrator Sistem', UserRole::Administrator, 'Administrator'],
            ['Team Leader Pengadaan', UserRole::TeamLeader, 'Team Leader Pengadaan'],
            ['Himatullah', UserRole::PicPerencana, 'PIC Perencana'],
            ['Bastial', UserRole::PicPerencana, 'PIC Perencana'],
            ['Iklan Nano', UserRole::PicPerencana, 'PIC Perencana'],
            ['Putu Wisna', UserRole::PicPerencana, 'PIC Perencana'],
            ['Sabrin', UserRole::PicPelaksana, 'PIC Pelaksana'],
            ['Ahmad Bukhari', UserRole::PicPelaksana, 'PIC Pelaksana'],
            ['Supriadi', UserRole::PicPelaksana, 'PIC Pelaksana'],
        ];

        foreach ($accounts as [$name, $role, $position]) {
            User::query()->updateOrCreate(
                ['email' => Str::slug($name, '.').'@upkendari.test'],
                [
                    'name' => $name,
                    'role' => $role,
                    'position' => $position,
                    'is_active' => true,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
