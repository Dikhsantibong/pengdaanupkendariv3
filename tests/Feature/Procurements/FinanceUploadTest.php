<?php

namespace Tests\Feature\Procurements;

use App\Enums\FinanceSection;
use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\ProcurementArchive;
use App\Services\ProcurementService;
use Database\Seeders\FinanceRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

/**
 * Every Penyerahan and Pembayaran requirement is an upload, filed on the
 * step itself before it can be ticked: Penyerahan by the execution side,
 * Pembayaran by Keuangan once the hand-over is approved.
 */
class FinanceUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $finance;

    protected Procurement $procurement;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        foreach (['SPK', 'PJ', 'SPPL'] as $code) {
            ContractNumberFormat::factory()->create(['code' => $code]);
        }

        $this->seed(FinanceRequirementSeeder::class);

        $this->finance = User::factory()->create(['role' => UserRole::Keuangan]);
        $this->procurement = Procurement::factory()->planningApproved()->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            'work_category' => 'jasa',
            'finance_submitted_at' => now(),
            // TL Pengadaan approved the hand-over: Pembayaran is open.
            'handover_approval_state' => 'disetujui',
        ]);

        app(ProcurementService::class)->syncChecklists($this->procurement);
    }

    public function test_a_requirement_is_ticked_only_once_its_paper_is_uploaded(): void
    {
        $step = $this->requiredStep();

        $this->actingAs($this->finance)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.keuangan', fn ($rows) => collect($rows)->every(fn (array $row): bool => $row['needs_upload'] === true)));

        $this->tick($step)->assertSessionHasErrors('is_completed');

        $this->actingAs($this->finance)
            ->post(route('procurements.checklists.files.store', [$this->procurement, $step]), [
                'files' => [
                    UploadedFile::fake()->create('surat-jalan.pdf', 40, 'application/pdf'),
                    UploadedFile::fake()->image('foto.jpg'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $files = $step->files()->get();
        $this->assertCount(2, $files);
        Storage::disk('local')->assertExists($files->first()->path);

        $this->tick($step)->assertSessionHasNoErrors();
        $this->assertTrue($step->refresh()->is_completed);

        $this->actingAs($this->finance)
            ->get(route('procurements.checklists.files.show', [$this->procurement, $step, $files->first()]))
            ->assertOk();

        // Removing every paper unticks the step again.
        foreach ($files as $file) {
            $this->actingAs($this->finance)
                ->delete(route('procurements.checklists.files.destroy', [$this->procurement, $step, $file]))
                ->assertRedirect();
        }

        $this->assertFalse($step->refresh()->is_completed);
        Storage::disk('local')->assertMissing($files->first()->path);
    }

    public function test_only_keuangan_uploads_and_only_on_its_own_steps(): void
    {
        $step = $this->requiredStep();
        $file = ['files' => [UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')]];

        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->post(route('procurements.checklists.files.store', [$this->procurement, $step]), $file)
            ->assertForbidden();

        $other = Procurement::factory()->create();

        $this->actingAs($this->finance)
            ->post(route('procurements.checklists.files.store', [$other, $step]), $file)
            ->assertNotFound();

        $this->actingAs($this->finance)
            ->post(route('procurements.checklists.files.store', [$this->procurement, $step]), ['files' => [UploadedFile::fake()->create('virus.exe', 10)]])
            ->assertSessionHasErrors('files.0');

        $this->assertSame(0, $step->files()->count());
    }

    public function test_the_handover_papers_are_the_executors(): void
    {
        $executor = User::factory()->executor()->create();
        $this->procurement->forceFill(['executor_id' => $executor->id, 'handover_approval_state' => 'belum_diajukan'])->save();
        $step = $this->requiredStep(FinanceSection::Penyerahan);
        $file = ['files' => [UploadedFile::fake()->create('bast.pdf', 20, 'application/pdf')]];

        $this->actingAs($this->finance)
            ->post(route('procurements.checklists.files.store', [$this->procurement, $step]), $file)
            ->assertForbidden();

        $this->actingAs($executor)
            ->post(route('procurements.checklists.files.store', [$this->procurement, $step]), $file)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $step->files()->count());

        // Once approved, the papers are final.
        $this->procurement->forceFill(['handover_approval_state' => 'disetujui'])->save();

        $this->actingAs($executor)
            ->post(route('procurements.checklists.files.store', [$this->procurement, $step]), $file)
            ->assertForbidden();
    }

    public function test_the_papers_travel_in_the_zip(): void
    {
        $step = $this->requiredStep();

        $this->actingAs($this->finance)
            ->post(route('procurements.checklists.files.store', [$this->procurement, $step]), [
                'files' => [UploadedFile::fake()->create('kwitansi.pdf', 20, 'application/pdf')],
            ]);

        $path = app(ProcurementArchive::class)->build($this->procurement->refresh());
        $zip = new ZipArchive;
        $zip->open($path);

        $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $index): string => (string) $zip->getNameIndex($index));
        $zip->close();
        @unlink($path);

        $this->assertTrue($names->contains(fn (string $name): bool => str_starts_with($name, '4. Keuangan/') && str_ends_with($name, 'kwitansi.pdf')));
    }

    protected function requiredStep(FinanceSection $section = FinanceSection::Pembayaran): ProcurementChecklist
    {
        return $this->procurement->checklists()
            ->where('stage', ProcurementStage::Keuangan->value)
            ->whereHas('checklistItem', fn ($item) => $item->where('is_optional', false)->where('section', $section->value))
            ->with('checklistItem')
            ->firstOrFail();
    }

    protected function tick(ProcurementChecklist $step): TestResponse
    {
        return $this->actingAs($this->finance)
            ->put(route('procurements.checklists.update', [$this->procurement, $step]), ['is_completed' => true]);
    }
}
