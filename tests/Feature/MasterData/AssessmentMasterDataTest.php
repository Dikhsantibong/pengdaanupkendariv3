<?php

namespace Tests\Feature\MasterData;

use App\Models\AssessmentAspect;
use App\Models\AssessmentForm;
use App\Models\User;
use App\Models\VendorAssessment;
use App\Services\VendorAssessmentService;
use Database\Seeders\VendorAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The aspects of the vendor performance form and the assessor sheets that
 * score them are master data, not code: an administrator adds, reorders and
 * deactivates them without a deploy.
 */
class AssessmentMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_adds_an_aspect_with_its_indicators(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('master-data.assessment-aspects.store'), [
                'name' => 'Aspek Inovasi',
                'code' => 'Aspek Inovasi',
                'preamble' => 'Kemampuan penyedia menawarkan perbaikan.',
                'indicators' => [
                    'Mengusulkan perbaikan metode kerja',
                    '   ',
                    'Menerapkan usulan yang disetujui',
                ],
                'sort_order' => 10,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $aspect = AssessmentAspect::query()->firstOrFail();

        // Printed in capitals, keyed by a slug, and blank rows dropped so the
        // form never prints an empty lettered line.
        $this->assertSame('ASPEK INOVASI', $aspect->name);
        $this->assertSame('aspek-inovasi', $aspect->code);
        $this->assertSame([
            'Mengusulkan perbaikan metode kerja',
            'Menerapkan usulan yang disetujui',
        ], $aspect->indicators);
    }

    public function test_an_aspect_needs_at_least_one_indicator(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->from(route('master-data.assessment-aspects.index'))
            ->post(route('master-data.assessment-aspects.store'), [
                'name' => 'Aspek Kosong',
                'code' => 'aspek-kosong',
                'indicators' => [],
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('indicators');

        $this->assertSame(0, AssessmentAspect::query()->count());
    }

    public function test_reordering_an_aspect_changes_the_printed_order(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $aspect = AssessmentAspect::query()->where('code', 'keamanan')->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('master-data.assessment-aspects.update', $aspect), [
                'name' => $aspect->name,
                'code' => $aspect->code,
                'preamble' => $aspect->preamble,
                'indicators' => $aspect->indicators,
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame(
            'ASPEK KEAMANAN',
            AssessmentAspect::query()->ordered()->firstOrFail()->name,
        );
    }

    public function test_a_deactivated_aspect_leaves_old_assessments_alone(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $aspect = AssessmentAspect::query()->where('code', 'lingkungan')->firstOrFail();

        $this->actingAs($administrator)
            ->delete(route('master-data.assessment-aspects.destroy', $aspect))
            ->assertRedirect();

        $this->assertSoftDeleted($aspect);

        // The rows already laid out on the open assessment survive.
        $this->assertTrue(
            $assessment->scores()->where('assessment_aspect_id', $aspect->id)->exists(),
        );
    }

    public function test_an_administrator_adds_a_sheet_and_chooses_its_aspects(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $administrator = User::factory()->administrator()->create();

        $chosen = AssessmentAspect::query()
            ->whereIn('code', ['mutu', 'waktu', 'harga'])
            ->ordered()
            ->pluck('id')
            ->all();

        $this->actingAs($administrator)
            ->post(route('master-data.assessment-forms.store'), [
                'name' => 'Perencanaan',
                'code' => 'Perencanaan',
                'assessor_title' => 'Asman Perencanaan',
                'assessor_options' => ['MUSRIYADI', ' ', 'SADRI'],
                'description' => 'Lembar perencanaan',
                'aspect_ids' => $chosen,
                'sort_order' => 6,
                'is_active' => true,
            ])
            ->assertRedirect();

        $form = AssessmentForm::query()->where('code', 'perencanaan')->firstOrFail();

        $this->assertSame(['MUSRIYADI', 'SADRI'], $form->assessor_options);
        $this->assertSame($chosen, $form->aspects->pluck('id')->all());

        // The pivot order is the order the aspects were given in.
        $this->assertSame([1, 2, 3], $form->aspects->pluck('pivot.sort_order')->all());
    }

    public function test_an_empty_name_list_means_the_assessor_types_their_name(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $form = AssessmentForm::query()->where('code', 'direksi-pekerjaan')->firstOrFail();

        $this->assertNotEmpty($form->assessor_options);

        $this->actingAs($administrator)
            ->put(route('master-data.assessment-forms.update', $form), [
                'name' => $form->name,
                'code' => $form->code,
                'assessor_title' => $form->assessor_title,
                'assessor_options' => [],
                'aspect_ids' => $form->aspects->pluck('id')->all(),
                'sort_order' => $form->sort_order,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertNull($form->fresh()?->assessor_options);
    }

    public function test_a_sheet_needs_at_least_one_aspect(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->from(route('master-data.assessment-forms.index'))
            ->post(route('master-data.assessment-forms.store'), [
                'name' => 'Lembar Kosong',
                'code' => 'lembar-kosong',
                'assessor_title' => 'Asman',
                'aspect_ids' => [],
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('aspect_ids');

        $this->assertSame(0, AssessmentForm::query()->count());
    }

    public function test_an_aspect_added_to_a_sheet_reaches_open_assessments(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $form = AssessmentForm::query()->where('code', 'lingkungan')->firstOrFail();
        $extra = AssessmentAspect::query()->where('code', 'harga')->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('master-data.assessment-forms.update', $form), [
                'name' => $form->name,
                'code' => $form->code,
                'assessor_title' => $form->assessor_title,
                'aspect_ids' => [...$form->aspects->pluck('id')->all(), $extra->id],
                'sort_order' => $form->sort_order,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($administrator)
            ->get(route('vendor-assessments.show', $assessment))
            ->assertOk();

        $this->assertTrue(
            $assessment->scores()
                ->where('assessment_form_id', $form->id)
                ->where('assessment_aspect_id', $extra->id)
                ->exists(),
        );
    }

    public function test_the_screens_are_closed_to_everybody_but_an_administrator(): void
    {
        $planner = User::factory()->planner()->create();

        $this->actingAs($planner)
            ->get(route('master-data.assessment-aspects.index'))
            ->assertForbidden();

        $this->actingAs($planner)
            ->get(route('master-data.assessment-forms.index'))
            ->assertForbidden();
    }

    public function test_the_screens_render_their_records(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('master-data.assessment-aspects.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('master-data/assessment-aspects')
                ->has('records', 9));

        $this->actingAs($administrator)
            ->get(route('master-data.assessment-forms.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('master-data/assessment-forms')
                ->has('records', 5)
                ->has('aspectOptions', 9));
    }

    /**
     * Open an assessment with every sheet laid out and nothing scored.
     *
     * @return array{0: VendorAssessment, 1: User}
     */
    protected function openAssessment(): array
    {
        $this->seed(VendorAssessmentSeeder::class);

        $administrator = User::factory()->administrator()->create();

        $assessment = app(VendorAssessmentService::class)->create([
            'project' => 'Pengadaan Jasa Angkut BBM',
            'vendor_name' => 'PT. Surveyor Indonesia',
            'form_number' => 'SMT-FM-DAN-02.02',
            'revision_number' => '03',
            'form_date' => '2026-06-10',
            'place' => 'Kendari',
        ], $administrator);

        return [$assessment, $administrator];
    }
}
