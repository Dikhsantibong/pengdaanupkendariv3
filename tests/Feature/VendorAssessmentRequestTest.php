<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorAssessment;
use App\Models\VendorAssessmentRequest;
use App\Services\DocumentGenerator;
use Database\Seeders\VendorAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The invoice details handoff: every vendor opens the same fixed link printed
 * in the contract, fills the details in without an account, and the request
 * shows on the assessment page ready to be opened as an assessment.
 */
class VendorAssessmentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_link_is_fixed_and_opens_without_an_account(): void
    {
        $this->assertSame(url('permintaan-penilaian'), VendorAssessmentRequest::formUrl());

        $this->get(VendorAssessmentRequest::formUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment-signing/request')
                ->where('submitted', null));
    }

    public function test_each_submission_becomes_a_new_request(): void
    {
        $this->post(route('assessment-requests.store'), $this->details())
            ->assertRedirect(route('assessment-requests.show'))
            ->assertSessionHas('assessment_request_submitted.vendor_name', 'PT. Sinar Teknik');

        $this->post(route('assessment-requests.store'), [
            ...$this->details(),
            'vendor_name' => 'CV. Maju Jaya',
        ])->assertRedirect();

        $this->assertSame(2, VendorAssessmentRequest::query()->count());

        $request = VendorAssessmentRequest::query()->where('vendor_name', 'PT. Sinar Teknik')->sole();

        $this->assertSame('Pemeliharaan Genset Unit 2', $request->project);
        $this->assertSame('2026-08-01', $request->contract_date->toDateString());
        $this->assertSame('15000000.00', $request->invoice_amount);
        $this->assertTrue($request->has_penalty);
        $this->assertSame('submitted', $request->status());

        // The same link is still open for the next vendor.
        $this->get(route('assessment-requests.show'))->assertOk();
    }

    public function test_every_detail_except_the_phone_is_required(): void
    {
        $this->post(route('assessment-requests.store'), [])
            ->assertSessionHasErrors([
                'project', 'vendor_name', 'contract_number', 'contract_date', 'term',
                'invoice_amount', 'bastp_number', 'bastp_date', 'has_penalty', 'submitter_name',
            ])
            ->assertSessionDoesntHaveErrors('submitter_phone');

        $this->assertSame(0, VendorAssessmentRequest::query()->count());
    }

    public function test_the_contract_template_can_print_the_link(): void
    {
        $this->assertArrayHasKey('tautan_penilaian_vendor', DocumentGenerator::placeholderCatalog());
    }

    public function test_the_request_shows_on_the_assessment_page(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $request = VendorAssessmentRequest::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('vendor-assessments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requestFormUrl', VendorAssessmentRequest::formUrl())
                ->has('requests', 1)
                ->where('requests.0.id', $request->id)
                ->where('requests.0.status', 'submitted')
                ->where('requests.0.vendor_name', 'PT. Sinar Teknik')
                ->where('requests.0.term', 'Termin 1 (100%)'));
    }

    public function test_a_processed_request_leaves_the_list_for_its_vendor_folder(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $waiting = VendorAssessmentRequest::factory()->create();
        $assessment = VendorAssessment::factory()->create(['vendor_name' => 'PT. Sinar Teknik']);
        VendorAssessmentRequest::factory()->create(['vendor_assessment_id' => $assessment->id]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('vendor-assessments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('requests', 1)
                ->where('requests.0.id', $waiting->id)
                ->where('vendors.data', fn ($folders) => collect($folders)->contains('name', 'PT. Sinar Teknik')));
    }

    public function test_only_an_administrator_may_delete_a_request(): void
    {
        $request = VendorAssessmentRequest::factory()->create();

        $this->actingAs(User::factory()->planner()->create())
            ->delete(route('vendor-assessments.requests.destroy', $request))
            ->assertForbidden();

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('vendor-assessments.requests.destroy', $request))
            ->assertRedirect();

        $this->assertModelMissing($request);
    }

    public function test_an_assessment_is_opened_from_a_request(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $administrator = User::factory()->administrator()->create();
        $request = VendorAssessmentRequest::factory()->create(['has_penalty' => true]);

        $this->actingAs($administrator)
            ->get(route('vendor-assessments.create', ['request_id' => $request->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('vendor-assessments/create')
                ->where('defaults.project', 'Pemeliharaan Genset Unit 2')
                ->where('defaults.vendor_name', 'PT. Sinar Teknik')
                ->where('defaults.po_number', 'KDD001.SPPL/100/UPKD/2026')
                ->where('defaults.po_date', '2026-08-01')
                ->where('defaults.bastp_date', '2026-09-15')
                ->where('defaults.has_penalty', true)
                ->where('defaults.vendor_assessment_request_id', $request->id)
                ->where('defaults.notes', fn (string $notes) => str_contains($notes, 'Termin: Termin 1 (100%)')
                    && str_contains($notes, 'Nilai tagihan: Rp 15.000.000')
                    && str_contains($notes, 'No BAPP/BASTP: BASTP/021/2026')));

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.store'), [
                'project' => 'Pemeliharaan Genset Unit 2',
                'vendor_name' => 'PT. Sinar Teknik',
                'po_number' => 'KDD001.SPPL/100/UPKD/2026',
                'po_date' => '2026-08-01',
                'bastp_date' => '2026-09-15',
                'has_penalty' => true,
                'form_number' => 'SMT-FM-DAN-02.02',
                'revision_number' => '03',
                'place' => 'Kendari',
                'notes' => 'Termin: Termin 1 (100%)',
                'vendor_assessment_request_id' => $request->id,
            ])
            ->assertRedirect();

        $assessment = VendorAssessment::query()->sole();

        $this->assertSame('PT. Sinar Teknik', $assessment->vendor_name);
        $this->assertTrue($assessment->has_penalty);
        $this->assertSame($assessment->id, $request->refresh()->vendor_assessment_id);
        $this->assertSame('processed', $request->status());
    }

    public function test_an_assessment_without_a_request_is_opened_as_before(): void
    {
        $this->seed(VendorAssessmentSeeder::class);

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('vendor-assessments.store'), [
                'project' => 'Pengadaan Jasa Angkut BBM',
                'vendor_name' => 'PT. Surveyor Indonesia',
                'has_penalty' => false,
                'form_number' => 'SMT-FM-DAN-02.02',
                'revision_number' => '03',
                'place' => 'Kendari',
            ])
            ->assertRedirect();

        $this->assertSame(1, VendorAssessment::query()->count());
    }

    /**
     * A complete set of invoice details.
     *
     * @return array<string, mixed>
     */
    protected function details(): array
    {
        return [
            'project' => 'Pemeliharaan Genset Unit 2',
            'vendor_name' => 'PT. Sinar Teknik',
            'contract_number' => 'KDD001.SPPL/100/UPKD/2026',
            'contract_date' => '2026-08-01',
            'term' => 'Termin 1 (100%)',
            'invoice_amount' => '15000000',
            'bastp_number' => 'BASTP/021/2026',
            'bastp_date' => '2026-09-15',
            'has_penalty' => true,
            'submitter_name' => 'Budi',
            'submitter_phone' => '081234567890',
        ];
    }
}
