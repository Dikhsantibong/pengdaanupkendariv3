<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\User;
use App\Models\VendorAssessment;
use App\Models\VendorAssessmentInvitation;
use App\Services\VendorAssessmentRenderer;
use App\Services\VendorAssessmentService;
use Database\Seeders\VendorAssessmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The WhatsApp handoff: the administrator issues a link, the assessor opens it
 * without an account, scores the sheet and signs it, and the administrator
 * downloads every document as one archive.
 */
class AssessmentSigningTest extends TestCase
{
    use RefreshDatabase;

    /** A one pixel transparent PNG, as the canvas would send it. */
    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB'
        .'CAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function test_an_administrator_issues_a_link_carrying_a_whatsapp_message(): void
    {
        [$assessment, $administrator, $form] = $this->openSheet();

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.links.store', [$assessment, $form]), [
                'recipient_name' => 'MUSRIYADI',
                'recipient_phone' => '081234567890',
            ])
            ->assertRedirect();

        $invitation = VendorAssessmentInvitation::query()->firstOrFail();

        $this->assertSame('6281234567890', $invitation->recipient_phone);
        $this->assertSame(64, strlen($invitation->token));
        $this->assertTrue($invitation->isOpen());

        $whatsapp = $invitation->whatsappUrl($assessment->project, $assessment->vendor_name);

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $whatsapp);
        $this->assertStringContainsString(rawurlencode($invitation->url()), $whatsapp);
    }

    public function test_re_issuing_a_link_kills_the_previous_token(): void
    {
        [$assessment, $administrator, $form] = $this->openSheet();

        $first = $this->issue($assessment, $administrator, $form);

        $this->actingAs($administrator)
            ->post(route('vendor-assessments.links.store', [$assessment, $form]))
            ->assertRedirect();

        $this->get(route('assessment-signing.show', $first->token))->assertNotFound();

        $this->assertSame(1, VendorAssessmentInvitation::query()->count());
    }

    public function test_the_signing_page_opens_without_logging_in(): void
    {
        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);

        $this->get(route('assessment-signing.show', $invitation->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('assessment-signing/show')
                ->where('form.name', 'Direksi Pekerjaan')
                ->where('assessment.vendor_name', $assessment->vendor_name)
                ->has('aspects'));

        $this->assertNotNull($invitation->fresh()?->opened_at);
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->get(route('assessment-signing.show', str_repeat('a', 64)))
            ->assertNotFound();
    }

    public function test_the_assessor_scores_and_signs_without_an_account(): void
    {
        Storage::fake('local');

        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);

        $this->post(route('assessment-signing.store', $invitation->token), [
            'assessor_name' => $this->rosterName($form),
            'scores' => $this->everyAspect($form, 4),
            'signature' => self::SIGNATURE,
        ])->assertRedirect(route('assessment-signing.show', $invitation->token));

        $invitation->refresh();

        $this->assertNotNull($invitation->submitted_at);
        $this->assertSame($this->rosterName($form), $invitation->assessor_name);
        $this->assertNotNull($invitation->signature_path);
        Storage::disk('local')->assertExists($invitation->signature_path);

        // The levels land on the sheet, and the printed name is the signatory.
        $this->assertSame(
            $form->aspects()->count(),
            $assessment->scores()
                ->where('assessment_form_id', $form->id)
                ->where('level', 4)
                ->count(),
        );
        $this->assertSame($this->rosterName($form), $form->fresh()?->assessor_name);
    }

    public function test_a_partly_filled_sheet_is_rejected(): void
    {
        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);

        $scores = $this->everyAspect($form, 4);
        $scores[0]['level'] = null;

        $this->post(route('assessment-signing.store', $invitation->token), [
            'assessor_name' => $this->rosterName($form),
            'scores' => $scores,
            'signature' => self::SIGNATURE,
        ])->assertSessionHasErrors('scores.0.level');

        $this->assertNull($invitation->fresh()?->submitted_at);
    }

    public function test_a_sheet_without_a_signature_is_rejected(): void
    {
        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);

        $this->post(route('assessment-signing.store', $invitation->token), [
            'assessor_name' => $this->rosterName($form),
            'scores' => $this->everyAspect($form, 3),
        ])->assertSessionHasErrors('signature');

        $this->assertNull($invitation->fresh()?->submitted_at);
    }

    public function test_a_name_outside_the_offered_list_is_rejected(): void
    {
        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);

        $this->post(route('assessment-signing.store', $invitation->token), [
            'assessor_name' => 'ORANG LAIN',
            'scores' => $this->everyAspect($form, 3),
            'signature' => self::SIGNATURE,
        ])->assertSessionHasErrors('assessor_name');
    }

    public function test_a_link_is_spent_once_the_sheet_is_signed(): void
    {
        Storage::fake('local');

        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);

        $this->post(route('assessment-signing.store', $invitation->token), [
            'assessor_name' => $this->rosterName($form),
            'scores' => $this->everyAspect($form, 5),
            'signature' => self::SIGNATURE,
        ])->assertRedirect();

        $this->get(route('assessment-signing.show', $invitation->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('assessment-signing/closed')
                ->where('state', 'submitted')
                ->where('assessorName', $this->rosterName($form)));

        $this->post(route('assessment-signing.store', $invitation->token), [
            'assessor_name' => $this->rosterName($form),
            'scores' => $this->everyAspect($form, 1),
            'signature' => self::SIGNATURE,
        ])->assertStatus(410);

        // The second attempt changed nothing.
        $this->assertSame(
            $form->aspects()->count(),
            $assessment->scores()
                ->where('assessment_form_id', $form->id)
                ->where('level', 5)
                ->count(),
        );
    }

    public function test_a_revoked_link_stops_opening(): void
    {
        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);

        $this->actingAs($administrator)
            ->delete(route('vendor-assessments.links.destroy', [$assessment, $form]))
            ->assertRedirect();

        $this->get(route('assessment-signing.show', $invitation->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('assessment-signing/closed')
                ->where('state', 'revoked'));

        $this->post(route('assessment-signing.store', $invitation->token), [
            'assessor_name' => $this->rosterName($form),
            'scores' => $this->everyAspect($form, 3),
            'signature' => self::SIGNATURE,
        ])->assertStatus(410);
    }

    public function test_an_expired_link_stops_opening(): void
    {
        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);
        $invitation->update(['expires_at' => now()->subDay()]);

        $this->get(route('assessment-signing.show', $invitation->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('assessment-signing/closed')
                ->where('state', 'expired'));
    }

    public function test_only_an_administrator_may_issue_a_link(): void
    {
        [$assessment, , $form] = $this->openSheet();

        $this->actingAs(User::factory()->planner()->create())
            ->post(route('vendor-assessments.links.store', [$assessment, $form]))
            ->assertForbidden();
    }

    public function test_a_signed_sheet_carries_the_drawn_signature_into_the_pdf(): void
    {
        Storage::fake('local');

        [$assessment, $administrator, $form] = $this->openSheet();

        $invitation = $this->issue($assessment, $administrator, $form);

        $before = app(VendorAssessmentRenderer::class)->html($assessment->fresh(), $form);

        // The signature space is empty until the sheet is actually signed.
        $this->assertStringContainsString('<td class="space"></td>', $before);

        $this->post(route('assessment-signing.store', $invitation->token), [
            'assessor_name' => $this->rosterName($form),
            'scores' => $this->everyAspect($form, 4),
            'signature' => self::SIGNATURE,
        ])->assertRedirect();

        $after = app(VendorAssessmentRenderer::class)->html(
            VendorAssessment::query()->findOrFail($assessment->id),
            $form->fresh(),
        );

        $this->assertStringContainsString('class="space"><img src="data:image/png;base64,', $after);
        // The roster carries a phone number for the WhatsApp handoff; the
        // contract prints the name alone.
        $this->assertStringContainsString(
            trim((string) preg_replace('/\s*\([^)]*\)/', '', $this->rosterName($form))),
            $after,
        );
    }

    public function test_the_administrator_downloads_every_document_as_one_archive(): void
    {
        [$assessment, $administrator] = $this->openSheet();

        $response = $this->actingAs($administrator)
            ->get(route('vendor-assessments.download-all', $assessment))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');

        $archive = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents((string) $archive, $response->streamedContent());

        $zip = new \ZipArchive;
        $zip->open((string) $archive);

        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = (string) $zip->getNameIndex($index);
        }

        $zip->close();
        unlink((string) $archive);

        $this->assertContains('00-akumulasi.pdf', $names);
        $this->assertContains('03-direksi-pekerjaan.pdf', $names);
        $this->assertCount(1 + AssessmentForm::query()->active()->count(), $names);
    }

    /**
     * Open an assessment and hand back the Direksi Pekerjaan sheet, which is
     * the one with a fixed list of assessors to choose from.
     *
     * @return array{0: VendorAssessment, 1: User, 2: AssessmentForm}
     */
    protected function openSheet(): array
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

        $form = AssessmentForm::query()->where('code', 'direksi-pekerjaan')->firstOrFail();

        return [$assessment, $administrator, $form];
    }

    /**
     * Issue a link for one sheet the way the administrator would.
     */
    protected function issue(
        VendorAssessment $assessment,
        User $administrator,
        AssessmentForm $form,
    ): VendorAssessmentInvitation {
        $this->actingAs($administrator)
            ->post(route('vendor-assessments.links.store', [$assessment, $form]), [
                'recipient_name' => 'SADRI',
                'recipient_phone' => '081234567890',
            ])
            ->assertRedirect();

        // The assessor arrives as a guest, so no session may carry over.
        auth()->logout();

        return VendorAssessmentInvitation::query()
            ->where('assessment_form_id', $form->id)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * A name from the sheet's own roster.
     *
     * Taken from the data rather than written out here, so editing the list of
     * Asman does not break these tests.
     */
    protected function rosterName(AssessmentForm $form, int $index = 0): string
    {
        return ($form->assessor_options ?? [])[$index] ?? '';
    }

    /**
     * Every aspect on a sheet given the same level.
     *
     * @return array<int, array{aspect_id: int, level: int|null}>
     */
    protected function everyAspect(AssessmentForm $form, int $level): array
    {
        return $form->aspects()
            ->pluck('assessment_aspects.id')
            ->map(fn (int $id): array => ['aspect_id' => $id, 'level' => $level])
            ->all();
    }
}
