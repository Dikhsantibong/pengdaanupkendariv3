<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the division accounts of the work proposal flow.
 *
 * Staff and team leaders of each division propose work (Pengusul); the
 * Direksi Pekerjaan of that division (its Asman, or the TL for K3 and
 * Lingkungan) approves it before it reaches the procurement planners.
 *
 * Only missing accounts are created: an account that already exists keeps
 * its role, division and password, so the seeder is safe to run again.
 */
class DivisionUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The accounts per division, keyed by the Direksi Pekerjaan name.
     *
     * @var array<string, array<int, array{email: string, name: string, role: UserRole, position: string}>>
     */
    protected const DIVISIONS = [
        'Asman Operasi' => [
            ['email' => 'asman.operasi@upkendari.test', 'name' => 'Asman Operasi', 'role' => UserRole::DireksiPekerjaan, 'position' => 'Asman Operasi'],
            ['email' => 'akmal.hidayat@upkendari.test', 'name' => 'Akmal Hidayat', 'role' => UserRole::Pengusul, 'position' => 'TL Operasi'],
            ['email' => 'rohmat.hidayat@upkendari.test', 'name' => 'Rohmat Hidayat', 'role' => UserRole::Pengusul, 'position' => 'Staf Operasi'],
            ['email' => 'la.ode.muhamad.kasman@upkendari.test', 'name' => 'La Ode Muhamad Kasman', 'role' => UserRole::Pengusul, 'position' => 'Staf Operasi'],
            ['email' => 'angga.prasetya.wahyuda@upkendari.test', 'name' => 'Angga Prasetya Wahyuda', 'role' => UserRole::Pengusul, 'position' => 'Staf Operasi'],
            ['email' => 'budi.bahrun@upkendari.test', 'name' => 'Budi Bahrun', 'role' => UserRole::Pengusul, 'position' => 'TL Energi Primer'],
            ['email' => 'dewa.triarya.ganehsa@upkendari.test', 'name' => 'Dewa Triarya Ganehsa', 'role' => UserRole::Pengusul, 'position' => 'Staf Energi Primer'],
            ['email' => 'benny.dwi.novrianto@upkendari.test', 'name' => 'Benny Dwi Novrianto', 'role' => UserRole::Pengusul, 'position' => 'Staf Energi Primer'],
        ],
        'Asman Engineering' => [
            ['email' => 'asman.engineering@upkendari.test', 'name' => 'Asman Engineering', 'role' => UserRole::DireksiPekerjaan, 'position' => 'Asman Engineering'],
            ['email' => 'firman@upkendari.test', 'name' => 'Firman', 'role' => UserRole::Pengusul, 'position' => 'TL CBM'],
            ['email' => 'muhammad.imam.ismail@upkendari.test', 'name' => 'Muhammad Imam Ismail', 'role' => UserRole::Pengusul, 'position' => 'Staf CBM'],
            ['email' => 'dedyy.saputra@upkendari.test', 'name' => 'Dedyy Saputra', 'role' => UserRole::Pengusul, 'position' => 'Staf CBM'],
            ['email' => 'zul.fikar@upkendari.test', 'name' => 'Zul Fikar', 'role' => UserRole::Pengusul, 'position' => 'TL System Owner'],
            ['email' => 'la.ode.deny@upkendari.test', 'name' => 'La Ode Deny', 'role' => UserRole::Pengusul, 'position' => 'Staf System Owner'],
            ['email' => 'muhammad.eko.santoso@upkendari.test', 'name' => 'Muhammad Eko Santoso', 'role' => UserRole::Pengusul, 'position' => 'Staf System Owner'],
        ],
        'Asman Pemeliharaan' => [
            ['email' => 'asman.pemeliharaan@upkendari.test', 'name' => 'Asman Pemeliharaan', 'role' => UserRole::DireksiPekerjaan, 'position' => 'Asman Pemeliharaan'],
            ['email' => 'galih.prima.yoga@upkendari.test', 'name' => 'Galih Prima Yoga', 'role' => UserRole::Pengusul, 'position' => 'TL Rendal HAR'],
            ['email' => 'faisal.herman@upkendari.test', 'name' => 'Faisal Herman', 'role' => UserRole::Pengusul, 'position' => 'Staf Rendal HAR'],
            ['email' => 'alfandi.nurdiansyah@upkendari.test', 'name' => 'Alfandi Nurdiansyah', 'role' => UserRole::Pengusul, 'position' => 'TL Outage Management'],
            ['email' => 'samsul@upkendari.test', 'name' => 'Samsul', 'role' => UserRole::Pengusul, 'position' => 'Staf Outage Management'],
            ['email' => 'firmansyah@upkendari.test', 'name' => 'Firmansyah', 'role' => UserRole::Pengusul, 'position' => 'Staf Outage Management'],
            // Bastial (TL ICC) already has an account as PIC Perencana.
        ],
        'Team Leader K3' => [
            ['email' => 'musriadi@upkendari.test', 'name' => 'Musriadi', 'role' => UserRole::DireksiPekerjaan, 'position' => 'TL K3 KAM'],
            ['email' => 'eri.kurnia.satya.nugraha@upkendari.test', 'name' => 'Eri Kurnia Satya Nugraha', 'role' => UserRole::Pengusul, 'position' => 'Staf K3 KAM'],
            ['email' => 'ibrahim.nawir@upkendari.test', 'name' => 'Ibrahim Nawir', 'role' => UserRole::Pengusul, 'position' => 'Staf K3 KAM'],
        ],
        'Team Leader Lingkungan' => [
            ['email' => 'sadri@upkendari.test', 'name' => 'Sadri', 'role' => UserRole::DireksiPekerjaan, 'position' => 'TL Lingkungan'],
            ['email' => 'yahsan.sulaiman@upkendari.test', 'name' => 'Yahsan Sulaiman', 'role' => UserRole::Pengusul, 'position' => 'Staf Lingkungan'],
            ['email' => 'muhammad.ricky.hermawan@upkendari.test', 'name' => 'Muhammad Ricky Hermawan', 'role' => UserRole::Pengusul, 'position' => 'Staf Lingkungan'],
        ],
        'Asman Business Support' => [
            ['email' => 'asman.bs@upkendari.test', 'name' => 'Asman Business Support', 'role' => UserRole::DireksiPekerjaan, 'position' => 'Asman Business Support'],
            ['email' => 'ichsanuddin.akbar@upkendari.test', 'name' => 'Ichsanuddin Akbar', 'role' => UserRole::Pengusul, 'position' => 'TL SDM'],
            ['email' => 'charisma@upkendari.test', 'name' => 'Charisma', 'role' => UserRole::Pengusul, 'position' => 'Staf SDM'],
            ['email' => 'ardian.periarda.sumalong@upkendari.test', 'name' => 'Ardian Periarda Sumalong', 'role' => UserRole::Pengusul, 'position' => 'Staf SDM'],
            ['email' => 'nova.sang.jaya@upkendari.test', 'name' => 'Nova Sang Jaya', 'role' => UserRole::Pengusul, 'position' => 'TL Keuangan'],
            ['email' => 'muhammad.jaya.junaid@upkendari.test', 'name' => 'Muhammad Jaya Junaid', 'role' => UserRole::Pengusul, 'position' => 'Staf Keuangan'],
            ['email' => 'inggrid.novela.pitaloka@upkendari.test', 'name' => 'Inggrid Novela Pitaloka', 'role' => UserRole::Pengusul, 'position' => 'Staf Keuangan'],
            ['email' => 'muhammad.amrullah@upkendari.test', 'name' => 'Muhammad Amrullah', 'role' => UserRole::TeamLeaderPengadaan, 'position' => 'TL Pengadaan'],
        ],
    ];

    /**
     * Create the missing division accounts.
     */
    public function run(): void
    {
        foreach (self::DIVISIONS as $divisionName => $accounts) {
            $division = WorkDirector::query()->firstOrCreate(
                ['name' => $divisionName],
                ['sort_order' => (int) WorkDirector::query()->max('sort_order') + 1, 'is_active' => true],
            );

            foreach ($accounts as $account) {
                if (User::query()->where('email', $account['email'])->exists()) {
                    continue;
                }

                User::query()->create([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                    'position' => $account['position'],
                    'work_director_id' => $division->id,
                    'is_active' => true,
                    'password' => Hash::make('password'),
                ])->forceFill(['email_verified_at' => now()])->save();
            }
        }
    }

    /**
     * Every seeded login address, for checks and documentation.
     *
     * @return array<int, string>
     */
    public static function emails(): array
    {
        return array_merge(...array_map(
            fn (array $accounts): array => array_column($accounts, 'email'),
            array_values(self::DIVISIONS),
        ));
    }
}
