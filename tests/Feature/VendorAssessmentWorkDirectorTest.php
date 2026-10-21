<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\User;
use App\Models\VendorAssessment;
use Database\Seeders\VendorAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Each assessment names its Direksi Pekerjaan, picked from the Direksi
 * Pekerjaan sheet by name only; one who took office later is not offered on
 * work handed over before then.
 */
class VendorAssessmentWorkDirectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VendorAssessmentSeeder::class);

        $this->sheet()->update(['assessor_hidden_until' => [
            'WINANDO PANDIANGAN' => '2026-07-01',
            'TAUHID ALI' => '2026-08-01',
            'TANWIR' => '2026-09-01',
        ]]);
    }

    public function test_the_form_offers_the_names_without_phone_numbers(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('vendor-assessments.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('workDirectors', fn (Collection $names) => $names->pluck('name')->contains('TAUHID ALI')
                    && $names->pluck('name')->every(fn (string $name) => ! str_contains($name, '('))
                    && $names->firstWhere('name', 'TAUHID ALI')['hidden_until'] === '2026-08-01'
                    && $names->firstWhere('name', 'MUSRIYADI')['hidden_until'] === null));
    }

    public function test_a_name_is_hidden_up_to_and_including_its_date(): void
    {
        $sheet = $this->sheet();
        $names = fn (string $date): array => array_map(
            fn (string $option): string => AssessmentForm::nameOf($option),
            $sheet->optionsFor(now()->parse($date)),
        );

        $this->assertNotContains('TAUHID ALI', $names('2026-08-01'));
        $this->assertContains('TAUHID ALI', $names('2026-08-02'));
        $this->assertNotContains('WINANDO PANDIANGAN', $names('2026-06-30'));
        $this->assertContains('WINANDO PANDIANGAN', $names('2026-07-02'));
        $this->assertNotContains('TANWIR', $names('2026-09-01'));
        $this->assertContains('MUSRIYADI', $names('2026-01-01'));
        $this->assertCount(9, $sheet->optionsFor(null));
    }

    public function test_an_assessment_is_saved_with_its_direksi_pekerjaan(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('vendor-assessments.store'), $this->header([
                'bastp_date' => '2026-08-15',
                'work_director_name' => 'TAUHID ALI',
            ]))
            ->assertRedirect();

        $this->assertSame('TAUHID ALI', VendorAssessment::query()->sole()->work_director_name);
    }

    public function test_a_name_not_yet_in_office_by_the_bastp_date_is_refused(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('vendor-assessments.store'), $this->header([
                'bastp_date' => '2026-08-01',
                'work_director_name' => 'TAUHID ALI',
            ]))
            ->assertSessionHasErrors('work_director_name');

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('vendor-assessments.store'), $this->header([
                'bastp_date' => '2026-08-01',
                'work_director_name' => 'ORANG LAIN',
            ]))
            ->assertSessionHasErrors('work_director_name');

        $this->assertSame(0, VendorAssessment::query()->count());
    }

    public function test_the_direksi_sheet_starts_on_the_chosen_name_and_hides_later_ones(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.store'), $this->header([
                'bastp_date' => '2026-07-15',
                'work_director_name' => 'MUSRIYADI',
            ]));

        $assessment = VendorAssessment::query()->sole();

        $this->actingAs($administrator)
            ->get(route('vendor-assessments.show', $assessment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.work_director_name', 'MUSRIYADI')
                ->where('forms', function (Collection $forms): bool {
                    $sheet = $forms->firstWhere('code', AssessmentForm::WORK_DIRECTOR);
                    $options = collect($sheet['assessor_options']);

                    return $sheet['assessor_name'] === 'MUSRIYADI (081342934948)'
                        && $options->contains('WINANDO PANDIANGAN (082194498674)')
                        && ! $options->contains('TAUHID ALI (081943643752)')
                        && ! $options->contains('TANWIR (081242150778)');
                }));
    }

    public function test_signing_the_direksi_sheet_updates_the_assessments_direksi_pekerjaan(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.store'), $this->header(['bastp_date' => '2026-10-01']));

        $assessment = VendorAssessment::query()->sole();
        $sheet = $this->sheet();
        $aspect = $sheet->aspects->first();

        $this->actingAs($administrator)
            ->put(route('vendor-assessments.scores.update', [$assessment, $sheet]), [
                'assessor_name' => 'TANWIR (081242150778)',
                'scores' => [['aspect_id' => $aspect->id, 'level' => 4]],
            ])
            ->assertRedirect();

        $this->assertSame('TANWIR', $assessment->fresh()?->work_director_name);
    }

    public function test_the_dates_are_set_beside_the_names_in_master_data(): void
    {
        $sheet = $this->sheet();

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('master-data.assessment-forms.update', $sheet), [
                'name' => $sheet->name,
                'code' => $sheet->code,
                'assessor_title' => $sheet->assessor_title,
                'assessor_options' => ['TAUHID ALI (0800)', '', 'MUSRIYADI (081342934948)'],
                'assessor_hidden_until' => ['2026-08-01', '2026-05-05', ''],
                'aspect_ids' => $sheet->aspects->pluck('id')->all(),
                'sort_order' => $sheet->sort_order,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $sheet->refresh();

        $this->assertSame(['TAUHID ALI (0800)', 'MUSRIYADI (081342934948)'], $sheet->assessor_options);
        $this->assertSame(['TAUHID ALI' => '2026-08-01'], $sheet->assessor_hidden_until);

        $this->get(route('master-data.assessment-forms.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('records', fn (Collection $records) => $records->firstWhere('code', AssessmentForm::WORK_DIRECTOR)['assessor_hidden_until'] === ['2026-08-01', '']));
    }

    protected function sheet(): AssessmentForm
    {
        return AssessmentForm::query()->where('code', AssessmentForm::WORK_DIRECTOR)->firstOrFail();
    }

    /**
     * A complete assessment header.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function header(array $overrides = []): array
    {
        return [
            'project' => 'Pemeliharaan Genset Unit 2',
            'vendor_name' => 'PT. Sinar Teknik',
            'has_penalty' => false,
            'form_number' => 'SMT-FM-DAN-02.02',
            'revision_number' => '03',
            'place' => 'Kendari',
            ...$overrides,
        ];
    }
}
