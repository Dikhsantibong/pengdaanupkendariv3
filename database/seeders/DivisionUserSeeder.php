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
     * Each account names its team leader (`tl`), who approves its work
     * proposals before the Asman does.
     *
     * @var array<string, array<int, array{email: string, name: string, role: UserRole, position: string, tl: string|null}>>
     */
    protected const DIVISIONS = [
        'Asman Operasi' => [
            ['email' => 'asman.operasi@upkendari.go.id', 'name' => 'Asman Operasi', 'role' => UserRole::DireksiPekerjaan, 'position' => 'Asman Operasi', 'tl' => null],
            ['email' => 'akmal.hidayat@upkendari.go.id', 'name' => 'Akmal Hidayat', 'role' => UserRole::Pengusul, 'position' => 'TL Operasi', 'tl' => null],
            ['email' => 'rohmat.hidayat@upkendari.go.id', 'name' => 'Rohmat Hidayat', 'role' => UserRole::Pengusul, 'position' => 'Staf Operasi', 'tl' => 'akmal.hidayat@upkendari.go.id'],
            ['email' => 'la.ode.muhamad.kasman@upkendari.go.id', 'name' => 'La Ode Muhamad Kasman', 'role' => UserRole::Pengusul, 'position' => 'Staf Operasi', 'tl' => 'akmal.hidayat@upkendari.go.id'],
            ['email' => 'angga.prasetya.wahyuda@upkendari.go.id', 'name' => 'Angga Prasetya Wahyuda', 'role' => UserRole::Pengusul, 'position' => 'Staf Operasi', 'tl' => 'akmal.hidayat@upkendari.go.id'],
            ['email' => 'budi.bahrun@upkendari.go.id', 'name' => 'Budi Bahrun', 'role' => UserRole::Pengusul, 'position' => 'TL Energi Primer', 'tl' => null],
            ['email' => 'dewa.triarya.ganehsa@upkendari.go.id', 'name' => 'Dewa Triarya Ganehsa', 'role' => UserRole::Pengusul, 'position' => 'Staf Energi Primer', 'tl' => 'budi.bahrun@upkendari.go.id'],
            ['email' => 'benny.dwi.novrianto@upkendari.go.id', 'name' => 'Benny Dwi Novrianto', 'role' => UserRole::Pengusul, 'position' => 'Staf Energi Primer', 'tl' => 'budi.bahrun@upkendari.go.id'],
        ],
        'Asman Engineering' => [
            ['email' => 'asman.engineering@upkendari.go.id', 'name' => 'Asman Engineering', 'role' => UserRole::DireksiPekerjaan, 'position' => 'Asman Engineering', 'tl' => null],
            ['email' => 'firman@upkendari.go.id', 'name' => 'Firman', 'role' => UserRole::Pengusul, 'position' => 'TL CBM', 'tl' => null],
            ['email' => 'muhammad.imam.ismail@upkendari.go.id', 'name' => 'Muhammad Imam Ismail', 'role' => UserRole::Pengusul, 'position' => 'Staf CBM', 'tl' => 'firman@upkendari.go.id'],
            ['email' => 'dedyy.saputra@upkendari.go.id', 'name' => 'Dedyy Saputra', 'role' => UserRole::Pengusul, 'position' => 'Staf CBM', 'tl' => 'firman@upkendari.go.id'],
            ['email' => 'zul.fikar@upkendari.go.id', 'name' => 'Zul Fikar', 'role' => UserRole::Pengusul, 'position' => 'TL System Owner', 'tl' => null],
            ['email' => 'la.ode.deny@upkendari.go.id', 'name' => 'La Ode Deny', 'role' => UserRole::Pengusul, 'position' => 'Staf System Owner', 'tl' => 'zul.fikar@upkendari.go.id'],
            ['email' => 'muhammad.eko.santoso@upkendari.go.id', 'name' => 'Muhammad Eko Santoso', 'role' => UserRole::Pengusul, 'position' => 'Staf System Owner', 'tl' => 'zul.fikar@upkendari.go.id'],
        ],
        'Asman Pemeliharaan' => [
            ['email' => 'asman.pemeliharaan@upkendari.go.id', 'name' => 'Asman Pemeliharaan', 'role' => UserRole::DireksiPekerjaan, 'position' => 'Asman Pemeliharaan', 'tl' => null],
            ['email' => 'galih.prima.yoga@upkendari.go.id', 'name' => 'Galih Prima Yoga', 'role' => UserRole::Pengusul, 'position' => 'TL Rendal HAR', 'tl' => null],
            ['email' => 'faisal.herman@upkendari.go.id', 'name' => 'Faisal Herman', 'role' => UserRole::Pengusul, 'position' => 'Staf Rendal HAR', 'tl' => 'galih.prima.yoga@upkendari.go.id'],
            ['email' => 'alfandi.nurdiansyah@upkendari.go.id', 'name' => 'Alfandi Nurdiansyah', 'role' => UserRole::Pengusul, 'position' => 'TL Outage Management', 'tl' => null],
            ['email' => 'samsul@upkendari.go.id', 'name' => 'Samsul', 'role' => UserRole::Pengusul, 'position' => 'Staf Outage Management', 'tl' => 'alfandi.nurdiansyah@upkendari.go.id'],
            ['email' => 'firmansyah@upkendari.go.id', 'name' => 'Firmansyah', 'role' => UserRole::Pengusul, 'position' => 'Staf Outage Management', 'tl' => 'alfandi.nurdiansyah@upkendari.go.id'],
            // Bastial (TL ICC) already has an account as PIC Perencana.
        ],
        'Team Leader K3' => [
            ['email' => 'musriadi@upkendari.go.id', 'name' => 'Musriadi', 'role' => UserRole::DireksiPekerjaan, 'position' => 'TL K3 KAM', 'tl' => null],
            ['email' => 'eri.kurnia.satya.nugraha@upkendari.go.id', 'name' => 'Eri Kurnia Satya Nugraha', 'role' => UserRole::Pengusul, 'position' => 'Staf K3 KAM', 'tl' => 'musriadi@upkendari.go.id'],
            ['email' => 'ibrahim.nawir@upkendari.go.id', 'name' => 'Ibrahim Nawir', 'role' => UserRole::Pengusul, 'position' => 'Staf K3 KAM', 'tl' => 'musriadi@upkendari.go.id'],
        ],
        'Team Leader Lingkungan' => [
            ['email' => 'sadri@upkendari.go.id', 'name' => 'Sadri', 'role' => UserRole::DireksiPekerjaan, 'position' => 'TL Lingkungan', 'tl' => null],
            ['email' => 'yahsan.sulaiman@upkendari.go.id', 'name' => 'Yahsan Sulaiman', 'role' => UserRole::Pengusul, 'position' => 'Staf Lingkungan', 'tl' => 'sadri@upkendari.go.id'],
            ['email' => 'muhammad.ricky.hermawan@upkendari.go.id', 'name' => 'Muhammad Ricky Hermawan', 'role' => UserRole::Pengusul, 'position' => 'Staf Lingkungan', 'tl' => 'sadri@upkendari.go.id'],
        ],
        'Asman Business Support' => [
            ['email' => 'asman.bs@upkendari.go.id', 'name' => 'Asman Business Support', 'role' => UserRole::DireksiPekerjaan, 'position' => 'Asman Business Support', 'tl' => null],
            ['email' => 'ichsanuddin.akbar@upkendari.go.id', 'name' => 'Ichsanuddin Akbar', 'role' => UserRole::Pengusul, 'position' => 'TL SDM', 'tl' => null],
            ['email' => 'charisma@upkendari.go.id', 'name' => 'Charisma', 'role' => UserRole::Pengusul, 'position' => 'Staf SDM', 'tl' => 'ichsanuddin.akbar@upkendari.go.id'],
            ['email' => 'ardian.periarda.sumalong@upkendari.go.id', 'name' => 'Ardian Periarda Sumalong', 'role' => UserRole::Pengusul, 'position' => 'Staf SDM', 'tl' => 'ichsanuddin.akbar@upkendari.go.id'],
            ['email' => 'nova.sang.jaya@upkendari.go.id', 'name' => 'Nova Sang Jaya', 'role' => UserRole::Keuangan, 'position' => 'TL Keuangan', 'tl' => null],
            ['email' => 'muhammad.jaya.junaid@upkendari.go.id', 'name' => 'Muhammad Jaya Junaid', 'role' => UserRole::Keuangan, 'position' => 'Staf Keuangan', 'tl' => 'nova.sang.jaya@upkendari.go.id'],
            ['email' => 'inggrid.novela.pitaloka@upkendari.go.id', 'name' => 'Inggrid Novela Pitaloka', 'role' => UserRole::Keuangan, 'position' => 'Staf Keuangan', 'tl' => 'nova.sang.jaya@upkendari.go.id'],
            ['email' => 'muhammad.amrullah@upkendari.go.id', 'name' => 'Muhammad Amrullah', 'role' => UserRole::TeamLeaderPengadaan, 'position' => 'TL Pengadaan', 'tl' => null],
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

        $this->linkTeams();
    }

    /**
     * Point each account at its team leader, and give the finance staff
     * their Keuangan role.
     *
     * Only what is still at its seeded default is filled in: a team leader
     * or role an administrator has already set is left alone.
     */
    protected function linkTeams(): void
    {
        foreach (self::DIVISIONS as $accounts) {
            foreach ($accounts as $account) {
                $user = User::query()->where('email', $account['email'])->first();

                if ($user === null) {
                    continue;
                }

                if ($user->team_leader_id === null && $account['tl'] !== null) {
                    $user->team_leader_id = User::query()->where('email', $account['tl'])->value('id');
                }

                if ($user->role === UserRole::Pengusul && $account['role'] === UserRole::Keuangan) {
                    $user->role = UserRole::Keuangan;
                }

                $user->save();
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
