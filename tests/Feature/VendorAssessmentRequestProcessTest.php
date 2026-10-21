<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorAssessmentRequestController;
use App\Models\AssessmentForm;
use App\Models\User;
use App\Models\VendorAssessment;
use App\Models\VendorAssessmentInvitation;
use App\Models\VendorAssessmentRequest;
use Database\Seeders\VendorAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * One click on a vendor's request opens the assessment and readies a
 * WhatsApp link for every sheet, each to its default assessor.
 */
class VendorAssessmentRequestProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VendorAssessmentSeeder::class);
    }

    public function test_a_request_becomes_an_assessment_with_a_link_for_every_sheet(): void
    {
        $request = VendorAssessmentRequest::factory()->create([
            'work_director_name' => 'TAUHID ALI',
            'has_penalty' => true,
        ]);

        $response = $this->actingAs(User::factory()->administrator()->create())
            ->post(route('vendor-assessments.requests.process', $request));

        $assessment = VendorAssessment::query()->sole();

        $response->assertRedirect(route('vendor-assessments.show', $assessment));

        $this->assertSame('PT. Sinar Teknik', $assessment->vendor_name);
        $this->assertSame('2026-09-15', $assessment->bastp_date?->toDateString());
        $this->assertTrue($assessment->has_penalty);
        $this->assertSame(VendorAssessmentRequestController::FORM_DATE, $assessment->form_date?->toDateString());
        $this->assertStringContainsString('Termin: Termin 1 (100%)', (string) $assessment->notes);
        $this->assertStringContainsString('Direksi Pekerjaan: TAUHID ALI', (string) $assessment->notes);
        $this->assertSame($assessment->id, $request->refresh()->vendor_assessment_id);

        $links = $assessment->invitations()->with('form')->get()->keyBy(fn (VendorAssessmentInvitation $link) => $link->form->code);

        $this->assertCount(AssessmentForm::query()->active()->count(), $links);
        // The first name of a sheet with a choice.
        $this->assertSame('FIRMAN', $links['pengadaan']->recipient_name);
        $this->assertSame('6282292352111', $links['pengadaan']->recipient_phone);
        $this->assertSame('BASTIAL', $links['icc-gudang']->recipient_name);
        // The Direksi Pekerjaan the vendor named.
        $this->assertSame('TAUHID ALI', $links[AssessmentForm::WORK_DIRECTOR]->recipient_name);
        $this->assertSame('6281943643752', $links[AssessmentForm::WORK_DIRECTOR]->recipient_phone);
        $this->assertTrue($links->every(fn (VendorAssessmentInvitation $link) => $link->isOpen()));
    }

    public function test_without_a_direksi_pekerjaan_that_sheet_waits_for_a_recipient(): void
    {
        $request = VendorAssessmentRequest::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('vendor-assessments.requests.process', $request))
            ->assertRedirect();

        $link = VendorAssessmentInvitation::query()
            ->whereRelation('form', 'code', AssessmentForm::WORK_DIRECTOR)
            ->sole();

        $this->assertNull($link->recipient_name);
        $this->assertNull($link->recipient_phone);
    }

    public function test_the_page_lists_every_sheet_with_its_whatsapp_message(): void
    {
        $administrator = User::factory()->administrator()->create();
        $request = VendorAssessmentRequest::factory()->create(['work_director_name' => 'MUSRIYADI']);

        $this->actingAs($administrator)->post(route('vendor-assessments.requests.process', $request));

        $this->actingAs($administrator)
            ->get(route('vendor-assessments.show', VendorAssessment::query()->sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('forms', function (Collection $forms): bool {
                    $url = (string) $forms->firstWhere('code', 'lingkungan')['invitation']['whatsapp_url'];
                    $message = rawurldecode((string) parse_url($url, PHP_URL_QUERY));

                    return str_starts_with($url, 'https://wa.me/6281241130696?text=')
                        && str_contains($message, 'Lembar: Lingkungan')
                        && str_contains($message, 'Tanggal BASTP: 15 Sep 2026')
                        && str_contains($message, 'Denda: Tidak Ada')
                        && str_contains($message, '/penilaian/');
                }));
    }

    public function test_a_request_already_processed_leads_to_its_assessment(): void
    {
        $administrator = User::factory()->administrator()->create();
        $request = VendorAssessmentRequest::factory()->create();

        $this->actingAs($administrator)->post(route('vendor-assessments.requests.process', $request));
        $assessment = VendorAssessment::query()->sole();

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.requests.process', $request))
            ->assertRedirect(route('vendor-assessments.show', $assessment));

        $this->assertSame(1, VendorAssessment::query()->count());
        $this->assertSame(AssessmentForm::query()->active()->count(), VendorAssessmentInvitation::query()->count());
    }

    public function test_only_sheets_without_a_link_are_readied_later(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.store'), [
                'project' => 'Pemeliharaan Genset Unit 2',
                'vendor_name' => 'PT. Sinar Teknik',
                'has_penalty' => false,
                'form_number' => 'SMT-FM-DAN-02.02',
                'revision_number' => '03',
                'place' => 'Kendari',
            ]);

        $assessment = VendorAssessment::query()->sole();
        $sheet = AssessmentForm::query()->where('code', 'lingkungan')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.links.store', [$assessment, $sheet]), [
                'recipient_name' => 'ORANG LAIN',
                'recipient_phone' => '081200000000',
            ]);

        $kept = VendorAssessmentInvitation::query()->sole();

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.links.store-all', $assessment))
            ->assertRedirect();

        $this->assertSame(AssessmentForm::query()->active()->count(), $assessment->invitations()->count());
        $this->assertSame('ORANG LAIN', $kept->fresh()?->recipient_name);
        $this->assertSame($kept->token, $kept->fresh()?->token);
    }

    public function test_a_sheet_already_scored_gets_no_link(): void
    {
        $administrator = User::factory()->administrator()->create();
        $request = VendorAssessmentRequest::factory()->create();

        $this->actingAs($administrator)->post(route('vendor-assessments.requests.process', $request));

        $assessment = VendorAssessment::query()->sole();
        $sheet = AssessmentForm::query()->where('code', 'k3-keamanan')->firstOrFail();

        $assessment->invitations()->where('assessment_form_id', $sheet->id)->delete();
        $assessment->scores()->where('assessment_form_id', $sheet->id)->update(['level' => 4]);

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.links.store-all', $assessment))
            ->assertRedirect();

        $this->assertFalse($assessment->invitations()->where('assessment_form_id', $sheet->id)->exists());
    }

    public function test_only_an_administrator_may_process_a_request(): void
    {
        $request = VendorAssessmentRequest::factory()->create();

        $this->actingAs(User::factory()->planner()->create())
            ->post(route('vendor-assessments.requests.process', $request))
            ->assertForbidden();

        $this->assertSame(0, VendorAssessment::query()->count());
    }
}
