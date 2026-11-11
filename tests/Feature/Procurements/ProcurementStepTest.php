<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\FinanceSection;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementStepAddition;
use App\Models\ProcurementStepExclusion;
use App\Models\User;
use App\Services\ProcurementService;
use App\Services\ProcurementStepService;
use Database\Seeders\FinanceRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The steps of one procurement can be adjusted on that procurement alone:
 * a Data Master step it skips added, a step removed and restored. Data Master and every
 * other procurement stay as they are, and nothing filed on a step is lost.
 */
class ProcurementStepTest extends TestCase
{
    use RefreshDatabase;

    protected User $teamLeader;

    protected ChecklistItem $first;

    protected ChecklistItem $second;

    /** A Data Master step both procurements skip, for their method. */
    protected ChecklistItem $skipped;

    protected Procurement $procurement;

    protected Procurement $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teamLeader = User::factory()->teamLeaderPengadaan()->create();

        $this->first = ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->create(['name' => 'RKS Uji', 'sort_order' => 1]);
        $this->skipped = ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->create(['name' => 'Survei Uji', 'sort_order' => 2]);
        $this->second = ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->create(['name' => 'HPE Uji', 'sort_order' => 3]);
        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->create(['name' => 'Kontrak Uji', 'sort_order' => 1]);

        $this->procurement = Procurement::factory()->create();
        $this->other = Procurement::factory()->create();

        $this->skipped->excludedProcurementMethods()->attach([
            $this->procurement->procurement_method_id,
            $this->other->procurement_method_id,
        ]);

        foreach ([$this->procurement, $this->other] as $procurement) {
            app(ProcurementService::class)->syncChecklists($procurement);
        }
    }

    public function test_a_data_master_step_it_skips_is_added_to_this_procurement_only(): void
    {
        $this->actingAs($this->teamLeader)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stepSettings.can.perencanaan', true)
                ->where('stepSettings.available.perencanaan', fn ($steps) => collect($steps)->pluck('name')->all() === ['Survei Uji']));

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), [
                'stage' => 'perencanaan',
                'checklist_item_id' => $this->skipped->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // In its Data Master place, and no longer offered.
        $this->actingAs($this->teamLeader)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.perencanaan', fn ($rows) => collect($rows)->pluck('name')->all() === ['RKS Uji', 'Survei Uji', 'HPE Uji'])
                ->where('checklists.perencanaan.1.removable', true)
                ->where('stepSettings.available.perencanaan', []));

        // A required step holds the planning back like any other.
        $this->assertContains('Survei Uji', $this->procurement->fresh()->pendingRequiredPlanningChecklists()->map(fn ($row) => $row->checklistItem->name)->all());

        // Nowhere else: Data Master and the other procurement are untouched.
        app(ProcurementService::class)->syncChecklists($this->other);
        $this->assertNull($this->row($this->other, $this->skipped));
        $this->assertSame(2, $this->skipped->excludedProcurementMethods()->count());
        $this->assertSame(0, ChecklistItem::query()->where('is_custom', true)->count());

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $this->procurement->id,
            'type' => ActivityType::ChecklistDiperbarui->value,
            'description' => 'Tahapan Survei Uji ditambahkan ke Perencanaan.',
        ]);

        // Removing it again takes it off and offers it once more.
        $this->actingAs($this->teamLeader)
            ->delete(route('procurements.steps.destroy', [$this->procurement, $this->row($this->procurement, $this->skipped)]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($this->row($this->procurement, $this->skipped));
        $this->assertSame(0, ProcurementStepAddition::query()->count());
        $this->assertSame(0, ProcurementStepExclusion::query()->count());
    }

    public function test_only_a_missing_data_master_step_of_the_stage_is_added(): void
    {
        $foreign = ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->create(['name' => 'Langkah Lain']);
        $ownOfAnother = ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->create([
            'procurement_id' => $this->other->id,
            'is_custom' => true,
        ]);

        // Already on the procurement, of another stage, or owned by another
        // procurement: all refused.
        foreach ([$this->first, $foreign] as $item) {
            $this->actingAs($this->teamLeader)
                ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'perencanaan', 'checklist_item_id' => $item->id])
                ->assertSessionHasErrors('checklist_item_id');
        }

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'perencanaan', 'checklist_item_id' => $ownOfAnother->id])
            ->assertSessionHasErrors('checklist_item_id');

        $this->assertSame(0, ProcurementStepAddition::query()->count());
    }

    public function test_a_step_written_by_hand_earlier_stays_and_can_be_removed(): void
    {
        $custom = ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->create([
            'procurement_id' => $this->procurement->id,
            'is_custom' => true,
            'name' => 'Langkah Lama',
            'sort_order' => 9,
        ]);
        app(ProcurementService::class)->syncChecklists($this->procurement);

        $row = $this->row($this->procurement, $custom);
        $this->assertNotNull($row);

        $this->actingAs($this->teamLeader)
            ->delete(route('procurements.steps.destroy', [$this->procurement, $row]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($this->row($this->procurement, $custom));
        $this->assertSame(1, ProcurementStepExclusion::query()->count());
    }

    public function test_a_step_is_removed_and_restored_without_touching_data_master(): void
    {
        $row = $this->row($this->procurement, $this->second);

        $this->actingAs($this->teamLeader)
            ->delete(route('procurements.steps.destroy', [$this->procurement, $row]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Opening the procurement again does not bring it back.
        $this->actingAs($this->teamLeader)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.perencanaan', fn ($rows) => collect($rows)->pluck('name')->all() === ['RKS Uji'])
                ->has('stepSettings.removed.perencanaan', 1)
                ->where('stepSettings.removed.perencanaan.0.name', 'HPE Uji')
                ->where('stepSettings.removed.perencanaan.0.removed_by', $this->teamLeader->name));

        $this->assertTrue($this->second->fresh()->is_active);
        $this->assertNotNull($this->row($this->other, $this->second));

        $exclusion = ProcurementStepExclusion::query()->sole();

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.restore', [$this->procurement, $exclusion]))
            ->assertRedirect();

        $restored = $this->row($this->procurement, $this->second);
        $this->assertNotNull($restored);
        $this->assertFalse($restored->is_completed);
        $this->assertSame(0, ProcurementStepExclusion::query()->count());
    }

    public function test_a_step_with_something_on_it_is_never_removed(): void
    {
        $ticked = $this->row($this->procurement, $this->first);
        $ticked->update(['is_completed' => true, 'completed_at' => now(), 'completed_by' => $this->teamLeader->id]);

        $noted = $this->row($this->procurement, $this->second);
        $noted->update(['notes' => 'Menunggu harga dari vendor.']);

        foreach ([$ticked, $noted] as $row) {
            $this->actingAs($this->teamLeader)
                ->delete(route('procurements.steps.destroy', [$this->procurement, $row]))
                ->assertSessionHasErrors('step');
        }

        $this->assertSame(0, ProcurementStepExclusion::query()->count());
        $this->assertNotNull($this->row($this->procurement, $this->first)?->completed_at);
        $this->assertSame('Menunggu harga dari vendor.', $this->row($this->procurement, $this->second)?->notes);
    }

    public function test_only_holders_of_the_right_may_adjust_an_open_stage(): void
    {
        $planner = User::factory()->planner()->create();
        $this->procurement->forceFill(['planner_id' => $planner->id])->save();
        $row = $this->row($this->procurement, $this->first);

        $this->actingAs($planner)
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'perencanaan', 'checklist_item_id' => $this->skipped->id])
            ->assertForbidden();

        $this->actingAs($planner)
            ->delete(route('procurements.steps.destroy', [$this->procurement, $row]))
            ->assertForbidden();

        // The right can be given to the role on the Hak Akses screen.
        DB::table('role_permissions')->insert([
            'role' => UserRole::PicPerencana->value,
            'permission' => 'procurement.manage-steps',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('access-rights.grants');

        $this->actingAs($planner)
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'perencanaan', 'checklist_item_id' => $this->skipped->id])
            ->assertRedirect();

        // Closed once the planning is submitted.
        $this->procurement->forceFill(['planning_approval_state' => PlanningApprovalState::MenungguPersetujuan])->save();

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'perencanaan', 'checklist_item_id' => $this->skipped->id])
            ->assertForbidden();

        $this->actingAs($this->teamLeader)
            ->delete(route('procurements.steps.destroy', [$this->procurement, $row]))
            ->assertForbidden();

        // The execution is still open to adjust.
        $test = ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->create(['name' => 'Uji Fungsi']);
        $test->excludedProcurementMethods()->attach($this->procurement->procurement_method_id);

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'pelaksanaan', 'checklist_item_id' => $test->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->skipped->id, $test->id], ProcurementStepAddition::query()->orderBy('id')->pluck('checklist_item_id')->all());
    }

    public function test_a_penyerahan_step_of_another_contract_can_be_added(): void
    {
        foreach (['SPK', 'PJ', 'SPPL'] as $code) {
            ContractNumberFormat::factory()->create(['code' => $code]);
        }
        $this->seed(FinanceRequirementSeeder::class);

        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->planningApproved()->executedBy($executor)->create([
            'procurement_method_id' => null,
            'contract_number_format_id' => ContractNumberFormat::query()->where('code', 'SPK')->value('id'),
            'work_category' => 'jasa',
            'finance_submitted_at' => now(),
            'execution_approval_state' => PlanningApprovalState::Disetujui,
        ]);
        app(ProcurementService::class)->syncChecklists($procurement);

        $templateRows = $this->handoverRows($procurement)->count();
        $this->assertGreaterThan(0, $templateRows);

        // A Penyerahan requirement of another contract is offered.
        $offered = app(ProcurementStepService::class)->available($procurement, 'penyerahan');
        $this->assertNotEmpty($offered);

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $procurement), ['stage' => 'penyerahan', 'checklist_item_id' => $offered[0]['id']])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Kept on every visit, beside the procurement's own requirements.
        $this->actingAs($this->teamLeader)->get(route('procurements.show', $procurement))->assertOk();

        $added = ChecklistItem::query()->findOrFail($offered[0]['id']);
        $this->assertSame(FinanceSection::Penyerahan, $added->section);
        $this->assertSame($templateRows + 1, $this->handoverRows($procurement)->count());
        $this->assertTrue($this->row($procurement, $added)?->needsOwnUpload());

        // Pembayaran is not adjusted here.
        $payment = $procurement->checklists()->with('checklistItem')->get()
            ->first(fn (ProcurementChecklist $checklist): bool => $checklist->financeSection() === FinanceSection::Pembayaran);

        $this->assertNotNull($payment);
        $this->actingAs($this->teamLeader)
            ->delete(route('procurements.steps.destroy', [$procurement, $payment]))
            ->assertNotFound();
    }

    protected function row(Procurement $procurement, ChecklistItem $item): ?ProcurementChecklist
    {
        return $procurement->checklists()->where('checklist_item_id', $item->id)->first();
    }

    /**
     * @return Collection<int, ProcurementChecklist>
     */
    protected function handoverRows(Procurement $procurement): Collection
    {
        return $procurement->checklists()->with('checklistItem')->get()
            ->filter(fn (ProcurementChecklist $checklist): bool => $checklist->financeSection() === FinanceSection::Penyerahan)
            ->values()
            ->toBase();
    }
}
