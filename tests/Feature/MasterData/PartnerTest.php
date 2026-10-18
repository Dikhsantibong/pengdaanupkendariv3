<?php

namespace Tests\Feature\MasterData;

use App\Models\Partner;
use App\Models\Procurement;
use App\Models\User;
use App\Models\VendorAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Data Master Mitra: every partner entered in planning or in a vendor
 * assessment, offered back while a partner's name is typed.
 */
class PartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_manages_the_partners(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('master-data.partners.store'), [
                'name' => 'CV. Akasha Nusa Engineering',
                'vendor_code' => 'V07452',
                'director_name' => 'Trisno Saputra',
                'address' => 'Jl. Dr. Sam Ratulangi, Kendari',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $partner = Partner::query()->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('master-data.partners.update', $partner), [
                'name' => 'CV. Akasha Nusa Engineering',
                'vendor_code' => 'V07453',
                'director_name' => 'Trisno Saputra',
                'address' => null,
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('V07453', $partner->refresh()->vendor_code);

        // The same name twice is refused.
        $this->actingAs($administrator)
            ->post(route('master-data.partners.store'), [
                'name' => 'CV. Akasha Nusa Engineering',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($administrator)
            ->delete(route('master-data.partners.destroy', $partner))
            ->assertRedirect();

        $this->assertSoftDeleted($partner);
    }

    public function test_only_master_data_managers_reach_the_page(): void
    {
        $this->actingAs(User::factory()->planner()->create())
            ->get(route('master-data.partners.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('master-data.partners.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('master-data/partners'));
    }

    public function test_typing_a_name_suggests_the_matching_active_partners(): void
    {
        Partner::factory()->create(['name' => 'PT Sinar Teknik Sulawesi', 'vendor_code' => 'V07000']);
        Partner::factory()->create(['name' => 'CV Teknik Mandiri']);
        Partner::factory()->create(['name' => 'PT Karya Energi']);
        Partner::factory()->inactive()->create(['name' => 'Teknik Lama']);

        $response = $this->actingAs(User::factory()->planner()->create())
            ->getJson(route('partners.suggest', ['search' => 'tek']))
            ->assertOk();

        // Those containing the letters, never an inactive one.
        $this->assertSame(
            ['CV Teknik Mandiri', 'PT Sinar Teknik Sulawesi'],
            collect($response->json('partners'))->pluck('name')->all(),
        );

        // A name starting with what was typed comes first, with its details.
        $this->actingAs(User::factory()->planner()->create())
            ->getJson(route('partners.suggest', ['search' => 'PT S']))
            ->assertJsonPath('partners.0.name', 'PT Sinar Teknik Sulawesi')
            ->assertJsonPath('partners.0.vendor_code', 'V07000');

        $this->actingAs(User::factory()->planner()->create())
            ->getJson(route('partners.suggest', ['search' => '  ']))
            ->assertExactJson(['partners' => []]);
    }

    public function test_suggestions_need_a_signed_in_user(): void
    {
        $this->getJson(route('partners.suggest', ['search' => 'PT']))->assertUnauthorized();
    }

    public function test_a_new_partner_candidate_joins_the_master_data(): void
    {
        $procurement = Procurement::factory()->create();

        $procurement->partnerCandidates()->create([
            'name' => '  CV.  Bumi Anoa Teknik ',
            'vendor_code' => 'V07452',
            'director_name' => 'La Ode Hamid',
            'address' => 'Kendari',
            'sort_order' => 1,
        ]);

        $partner = Partner::query()->sole();
        $this->assertSame('CV. Bumi Anoa Teknik', $partner->name);
        $this->assertSame('V07452', $partner->vendor_code);
        $this->assertSame('La Ode Hamid', $partner->director_name);
    }

    public function test_a_known_partner_only_gains_what_it_lacks(): void
    {
        $partner = Partner::factory()->create([
            'name' => 'PT Patria Power Service',
            'vendor_code' => 'V07339',
            'director_name' => null,
        ]);

        Procurement::factory()->create()->partnerCandidates()->create([
            'name' => 'pt patria power service',
            'vendor_code' => 'SALAH',
            'director_name' => 'Yohanes Saputra',
            'sort_order' => 1,
        ]);

        $partner->refresh();
        $this->assertSame(1, Partner::query()->count());
        // The master data's own code stays; the missing director is filled.
        $this->assertSame('V07339', $partner->vendor_code);
        $this->assertSame('Yohanes Saputra', $partner->director_name);
    }

    public function test_an_assessed_vendor_joins_the_master_data(): void
    {
        VendorAssessment::factory()->create(['vendor_name' => 'PT. Surveyor Indonesia']);

        $this->assertTrue(Partner::query()->where('name', 'PT. Surveyor Indonesia')->exists());
    }

    public function test_the_migration_gathers_every_partner_entered_so_far(): void
    {
        $procurement = Procurement::factory()->create([
            'partner_name' => 'PT Karya Energi Nusantara',
            'partner_vendor_code' => 'V07226',
            'partner_director_name' => 'Rudy Hartono',
        ]);
        $procurement->partnerCandidates()->create(['name' => 'CV Mandiri Jaya Abadi', 'director_name' => 'Hasanuddin', 'sort_order' => 1]);
        $procurement->partnerCandidates()->create(['name' => 'PT KARYA ENERGI NUSANTARA', 'address' => 'Kendari', 'sort_order' => 2]);
        VendorAssessment::factory()->create(['vendor_name' => 'PT. Surveyor Indonesia']);

        // Rebuilt from scratch, as on a server running the migration.
        Schema::drop('partners');
        $migration = require database_path('migrations/2026_10_18_093810_create_partners_table.php');
        $migration->up();

        $this->assertSame(3, Partner::query()->count());

        // The same company spelled twice is one partner, its details merged.
        $karya = Partner::query()->whereRaw('LOWER(name) = ?', ['pt karya energi nusantara'])->sole();
        $this->assertSame('V07226', $karya->vendor_code);
        $this->assertSame('Rudy Hartono', $karya->director_name);
        $this->assertSame('Kendari', $karya->address);

        $this->assertTrue(Partner::query()->where('name', 'CV Mandiri Jaya Abadi')->exists());
        $this->assertTrue(Partner::query()->where('name', 'PT. Surveyor Indonesia')->exists());
    }
}
