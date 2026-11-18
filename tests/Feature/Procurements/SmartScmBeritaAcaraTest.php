<?php

namespace Tests\Feature\Procurements;

use App\Enums\ChecklistInput;
use App\Enums\PlanningApprovalState;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\User;
use App\Services\DocumentGenerator;
use App\Services\ProcurementService;
use App\Support\TenderBeritaAcara;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The "Proses SMART SCM & Berita Acara" step asks for the number and date of
 * its Berita Acara, kept on the procurement for its documents.
 */
class SmartScmBeritaAcaraTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_step_asks_for_the_ba_number_and_date(): void
    {
        [$procurement, $executor, $step] = $this->step();

        $this->assertSame(ChecklistInput::BeritaAcara, $step->checklistItem->input_kind);

        $this->actingAs($executor)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page->where('checklists.pelaksanaan', fn ($rows) => collect($rows)
                ->contains(fn (array $row): bool => $row['id'] === $step->id
                    && $row['input']['kind'] === 'berita_acara'
                    && $row['input']['is_filled'] === false
                    && array_key_exists('smart_scm_ba_number', $row['input']['values']))));

        // The step cannot be ticked before they are in, and says so (its
        // documents set aside, as they are checked first).
        $step->checklistItem->documentTypes()->detach();

        $this->actingAs($executor)
            ->put(route('procurements.checklists.update', [$procurement, $step]), ['is_completed' => true])
            ->assertSessionHasErrors(['is_completed' => 'Lengkapi isian nomor & tanggal BA (Proses SMART SCM & Berita Acara) sebelum menandai tahapan ini selesai.']);

        // Both are asked for.
        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), ['smart_scm_ba_number' => '', 'smart_scm_ba_date' => 'bukan tanggal'])
            ->assertSessionHasErrors(['smart_scm_ba_number', 'smart_scm_ba_date']);

        $this->actingAs($executor)
            ->put(route('procurements.checklists.input', [$procurement, $step]), [
                'smart_scm_ba_number' => '0012/BAPP/612/UPKD/2026',
                'smart_scm_ba_date' => '2026-11-10',
            ])
            ->assertSessionHasNoErrors();

        $procurement->refresh();
        $this->assertSame('0012/BAPP/612/UPKD/2026', $procurement->smart_scm_ba_number);
        $this->assertSame('2026-11-10', $procurement->smart_scm_ba_date?->toDateString());
        $this->assertTrue(ChecklistInput::BeritaAcara->isFilledOn($procurement));

        // Documents may print them.
        $values = app(DocumentGenerator::class)->placeholderValues($procurement);
        $this->assertSame('0012/BAPP/612/UPKD/2026', $values['nomor_ba_smart_scm']);
        $this->assertSame('10 November 2026', $values['tanggal_ba_smart_scm']);
    }

    /**
     * An SPK job in execution with its Proses SMART SCM step.
     *
     * @return array{0: Procurement, 1: User, 2: ProcurementChecklist}
     */
    protected function step(): array
    {
        $this->seed(MasterDataSeeder::class);

        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            'executor_id' => $executor->id,
            'planning_approval_state' => PlanningApprovalState::Disetujui,
        ]);

        app(ProcurementService::class)->syncChecklists($procurement);

        $step = $procurement->checklists()
            ->whereHas('checklistItem', fn ($query) => $query->where('name', TenderBeritaAcara::STEP_NAME))
            ->with('checklistItem')
            ->firstOrFail();

        return [$procurement, $executor, $step];
    }
}
