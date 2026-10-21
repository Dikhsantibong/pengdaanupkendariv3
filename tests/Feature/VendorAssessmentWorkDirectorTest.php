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
 * A Direksi Pekerjaan who took office later is not offered on work handed
 * over (BASTP) before then; the dates are set beside the names of the
 * Direksi Pekerjaan sheet.
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

    public function test_the_direksi_sheet_hides_names_not_yet_in_office(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.store'), [
                'project' => 'Pemeliharaan Genset Unit 2',
                'vendor_name' => 'PT. Sinar Teknik',
                'bastp_date' => '2026-07-15',
                'has_penalty' => false,
                'form_number' => 'SMT-FM-DAN-02.02',
                'revision_number' => '03',
                'place' => 'Kendari',
            ]);

        $this->actingAs($administrator)
            ->get(route('vendor-assessments.show', VendorAssessment::query()->sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('forms', function (Collection $forms): bool {
                    $options = collect($forms->firstWhere('code', AssessmentForm::WORK_DIRECTOR)['assessor_options']);

                    return $options->contains('WINANDO PANDIANGAN (082194498674)')
                        && ! $options->contains('TAUHID ALI (081943643752)')
                        && ! $options->contains('TANWIR (081242150778)');
                }));
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
}
