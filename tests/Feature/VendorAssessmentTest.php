<?php

namespace Tests\Feature;

use App\Models\AssessmentAspect;
use App\Models\AssessmentForm;
use App\Models\Procurement;
use App\Models\User;
use App\Models\VendorAssessment;
use App\Services\VendorAssessmentRenderer;
use App\Services\VendorAssessmentService;
use Database\Seeders\VendorAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Formulir Penilaian Kinerja Penyedia Barang dan Jasa: five assessor sheets
 * over nine aspects, and a master recap averaging each aspect.
 */
class VendorAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_official_aspects_and_sheets_are_seeded(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $this->assertSame([
            'ASPEK INTEGRITAS',
            'ASPEK KERJA SAMA',
            'ASPEK MUTU',
            'ASPEK WAKTU',
            'ASPEK HARGA',
            'ASPEK MANAJEMEN K3',
            'ASPEK LINGKUNGAN',
            'ASPEK KEAMANAN',
            'ASPEK MANAJEMEN ENERGI',
        ], AssessmentAspect::query()->ordered()->pluck('name')->all());

        $this->assertSame([
            'Pengadaan',
            'ICC & Gudang',
            'Direksi Pekerjaan',
            'Lingkungan',
            'K3 & Keamanan',
        ], AssessmentForm::query()->ordered()->pluck('name')->all());

        $k3 = AssessmentForm::query()->where('code', 'k3-keamanan')->firstOrFail();

        $this->assertSame(
            ['manajemen-k3', 'keamanan'],
            $k3->aspects->pluck('code')->all(),
        );
        $this->assertSame('TL K3 DAN KEAMANAN', $k3->assessor_title);
    }

    public function test_only_an_administrator_reaches_the_feature(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        foreach ([
            User::factory()->teamLeader()->create(),
            User::factory()->planner()->create(),
            User::factory()->executor()->create(),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('vendor-assessments.index'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('vendor-assessments.index'))
            ->assertOk();
    }

    public function test_creating_an_assessment_lays_out_every_sheet(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $procurement = Procurement::factory()->create(['name' => 'Pengadaan Jasa Angkut BBM']);

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.store'), [
                'procurement_id' => $procurement->id,
                'project' => 'Pengadaan Jasa Angkut BBM',
                'po_number' => 'EKDDAK',
                'po_date' => '2026-02-24',
                'vendor_name' => 'PT. Surveyor Indonesia',
                'form_number' => 'SMT-FM-DAN-02.02',
                'revision_number' => '03',
                'form_date' => '2026-06-10',
                'place' => 'Kendari',
            ])
            ->assertRedirect();

        $assessment = VendorAssessment::query()->firstOrFail();

        // One empty row per sheet-and-aspect pairing: 5 + 6 + 2 + 1 + 2.
        $this->assertSame(16, $assessment->scores()->count());
        $this->assertSame(0, $assessment->scores()->whereNotNull('level')->count());
        $this->assertSame($administrator->id, $assessment->created_by);
    }

    public function test_an_aspect_is_averaged_across_the_sheets_that_score_it(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $k3Aspect = AssessmentAspect::query()->where('code', 'manajemen-k3')->firstOrFail();

        // Manajemen K3 sits on both the ICC and the K3 sheet.
        $this->scoreSheet($assessment, $administrator, 'icc-gudang', [$k3Aspect->id => 4]);
        $this->scoreSheet($assessment, $administrator, 'k3-keamanan', [$k3Aspect->id => 3]);

        $assessment->load('scores');

        $this->assertSame(3.5, $assessment->averageFor($k3Aspect->id));
    }

    public function test_an_unscored_sheet_does_not_drag_the_average_down(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $aspect = AssessmentAspect::query()->where('code', 'keamanan')->firstOrFail();

        // Keamanan is on ICC and K3; only one of them scores it.
        $this->scoreSheet($assessment, $administrator, 'k3-keamanan', [$aspect->id => 5]);

        $assessment->load('scores');

        $this->assertSame(5.0, $assessment->averageFor($aspect->id));
    }

    public function test_an_aspect_nobody_scored_has_no_average(): void
    {
        [$assessment] = $this->openAssessment();

        $aspect = AssessmentAspect::query()->where('code', 'harga')->firstOrFail();

        $this->assertNull($assessment->averageFor($aspect->id));
    }

    public function test_a_level_outside_the_scale_is_rejected(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $form = AssessmentForm::query()->where('code', 'pengadaan')->firstOrFail();
        $aspect = $form->aspects->first();

        $this->actingAs($administrator)
            ->put(route('vendor-assessments.scores.update', [$assessment, $form]), [
                'scores' => [['aspect_id' => $aspect->id, 'level' => 6]],
            ])
            ->assertSessionHasErrors('scores.0.level');

        $this->assertSame(0, $assessment->scores()->whereNotNull('level')->count());
    }

    public function test_clearing_a_level_also_clears_who_scored_it(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $form = AssessmentForm::query()->where('code', 'lingkungan')->firstOrFail();
        $aspect = $form->aspects->firstOrFail();

        $this->scoreSheet($assessment, $administrator, 'lingkungan', [$aspect->id => 4]);

        $score = $assessment->scores()->where('assessment_aspect_id', $aspect->id)
            ->where('assessment_form_id', $form->id)->firstOrFail();

        $this->assertSame($administrator->id, $score->scored_by);

        $this->actingAs($administrator)
            ->put(route('vendor-assessments.scores.update', [$assessment, $form]), [
                'scores' => [['aspect_id' => $aspect->id, 'level' => null]],
            ])
            ->assertRedirect();

        $score->refresh();

        $this->assertNull($score->level);
        $this->assertNull($score->scored_by);
        $this->assertNull($score->scored_at);
    }

    public function test_the_assessor_name_is_remembered_on_the_sheet(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $form = AssessmentForm::query()->where('code', 'direksi-pekerjaan')->firstOrFail();
        $aspect = $form->aspects->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('vendor-assessments.scores.update', [$assessment, $form]), [
                'assessor_name' => 'EKO YULI WIDIYATMOKO',
                'scores' => [['aspect_id' => $aspect->id, 'level' => 4]],
            ])
            ->assertRedirect();

        $this->assertSame('EKO YULI WIDIYATMOKO', $form->refresh()->assessor_name);
    }

    public function test_the_direksi_sheet_offers_the_five_asman(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $form = AssessmentForm::query()->where('code', 'direksi-pekerjaan')->firstOrFail();

        $this->assertSame([
            'MUSRIYADI',
            'SADRI',
            'EKO YULI WIDIYATMOKO',
            'AGUS SALIM',
            'ROBY FIRMANSYAH',
        ], $form->assessor_options);

        // The other sheets keep a free text signatory.
        $this->assertNull(
            AssessmentForm::query()->where('code', 'lingkungan')->firstOrFail()->assessor_options,
        );
    }

    public function test_the_direksi_sheet_refuses_a_name_outside_the_list(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $form = AssessmentForm::query()->where('code', 'direksi-pekerjaan')->firstOrFail();
        $aspect = $form->aspects->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('vendor-assessments.scores.update', [$assessment, $form]), [
                'assessor_name' => 'ORANG LAIN',
                'scores' => [['aspect_id' => $aspect->id, 'level' => 4]],
            ])
            ->assertSessionHasErrors('assessor_name');

        $this->assertNull($form->refresh()->assessor_name);
    }

    public function test_a_sheet_without_a_list_still_takes_any_name(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $form = AssessmentForm::query()->where('code', 'lingkungan')->firstOrFail();
        $aspect = $form->aspects->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('vendor-assessments.scores.update', [$assessment, $form]), [
                'assessor_name' => 'NAMA BARU',
                'scores' => [['aspect_id' => $aspect->id, 'level' => 4]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('NAMA BARU', $form->refresh()->assessor_name);
    }

    public function test_the_letterhead_uses_the_sidebar_logo(): void
    {
        [$assessment] = $this->openAssessment();

        $html = app(VendorAssessmentRenderer::class)->html($assessment);

        // Embedded as a data URI so the PDF needs no network access, and
        // scaled down so the renderer never expands the full size asset.
        $this->assertStringContainsString('src="data:image/png;base64,', $html);

        $cached = glob(storage_path('app/private/cache/logo-print-*.png'));

        $this->assertNotEmpty($cached);

        $size = getimagesize($cached[0]);

        $this->assertNotFalse($size);
        $this->assertSame(420, $size[0]);
        // The wide lockup keeps its shape rather than being squared off.
        $this->assertLessThan(200, $size[1]);
    }

    public function test_the_detail_screen_carries_every_sheet_and_the_recap(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $this->actingAs($administrator)
            ->get(route('vendor-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('vendor-assessments/show')
                ->has('forms', 5)
                ->has('recap.aspects', 9)
                ->where('recap.total', 16)
                ->where('recap.scored', 0)
                ->where('assessment.vendor_name', 'PT. Surveyor Indonesia')
            );
    }

    public function test_every_sheet_and_the_recap_render_to_pdf(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $renderer = app(VendorAssessmentRenderer::class);

        $recap = $renderer->pdf($assessment);
        $this->assertStringStartsWith('%PDF-', $recap);

        foreach (AssessmentForm::query()->ordered()->get() as $form) {
            $this->assertStringStartsWith('%PDF-', $renderer->pdf($assessment, $form));
        }

        $this->actingAs($administrator)
            ->get(route('vendor-assessments.print', $assessment))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_printable_sheet_shows_the_official_heading_and_levels(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $form = AssessmentForm::query()->where('code', 'k3-keamanan')->firstOrFail();
        $aspect = AssessmentAspect::query()->where('code', 'manajemen-k3')->firstOrFail();

        $this->scoreSheet($assessment, $administrator, 'k3-keamanan', [$aspect->id => 3]);

        $html = app(VendorAssessmentRenderer::class)->html($assessment->refresh(), $form);

        foreach ([
            'PENILAIAN KINERJA',
            'PENYEDIA BARANG DAN JASA',
            'SMT-FM-DAN-02.02',
            'PT. Surveyor Indonesia',
            'ASPEK MANAJEMEN K3',
            'Alat Perlindungan Diri (APD)',
            'TL K3 DAN KEAMANAN',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "[{$needle}] tidak ada pada lembar cetak.");
        }
    }

    public function test_an_aspect_added_to_a_sheet_later_appears_on_open_assessments(): void
    {
        [$assessment, $administrator] = $this->openAssessment();

        $form = AssessmentForm::query()->where('code', 'lingkungan')->firstOrFail();
        $extra = AssessmentAspect::query()->where('code', 'mutu')->firstOrFail();

        $form->aspects()->attach($extra->id, ['sort_order' => 9]);

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

    /**
     * Post levels to one sheet.
     *
     * @param  array<int, int|null>  $levels  Aspect id to level.
     */
    protected function scoreSheet(
        VendorAssessment $assessment,
        User $user,
        string $formCode,
        array $levels,
    ): void {
        $form = AssessmentForm::query()->where('code', $formCode)->firstOrFail();

        $scores = [];

        foreach ($levels as $aspectId => $level) {
            $scores[] = ['aspect_id' => $aspectId, 'level' => $level];
        }

        $this->actingAs($user)
            ->put(route('vendor-assessments.scores.update', [$assessment, $form]), [
                'scores' => $scores,
            ])
            ->assertRedirect();
    }
}
