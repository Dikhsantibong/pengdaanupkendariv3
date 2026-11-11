<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Enums\FinanceSection;
use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementChecklist;
use App\Models\ProcurementStepExclusion;
use App\Models\User;
use App\Services\ProcurementService;
use Database\Seeders\FinanceRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The steps of one procurement can be adjusted on that procurement alone:
 * a step added by hand, a step removed and restored. Data Master and every
 * other procurement stay as they are, and nothing filed on a step is lost.
 */
class ProcurementStepTest extends TestCase
{
    use RefreshDatabase;

    protected User $teamLeader;

    protected ChecklistItem $first;

    protected ChecklistItem $second;

    protected Procurement $procurement;

    protected Procurement $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teamLeader = User::factory()->teamLeaderPengadaan()->create();

        $this->first = ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->create(['name' => 'RKS Uji', 'sort_order' => 1]);
        $this->second = ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->create(['name' => 'HPE Uji', 'sort_order' => 2]);
        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->create(['name' => 'Kontrak Uji', 'sort_order' => 1]);

        $this->procurement = Procurement::factory()->create();
        $this->other = Procurement::factory()->create();

        foreach ([$this->procurement, $this->other] as $procurement) {
            app(ProcurementService::class)->syncChecklists($procurement);
        }
    }

    public function test_a_step_is_added_after_another_on_this_procurement_only(): void
    {
        $after = $this->row($this->procurement, $this->first);

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), [
                'stage' => 'perencanaan',
                'name' => 'Survei Lapangan',
                'description' => 'Tinjau lokasi bersama Direksi Pekerjaan.',
                'is_optional' => false,
                'after_checklist_id' => $after->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = ChecklistItem::query()->where('name', 'Survei Lapangan')->firstOrFail();
        $this->assertTrue($item->is_custom);
        $this->assertSame($this->procurement->id, $item->procurement_id);

        $this->actingAs($this->teamLeader)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.perencanaan', fn ($rows) => collect($rows)->pluck('name')->all() === ['RKS Uji', 'Survei Lapangan', 'HPE Uji'])
                ->where('checklists.perencanaan.1.is_custom', true)
                ->where('checklists.perencanaan.1.removable', true)
                ->where('stepSettings.can.perencanaan', true));

        // A required step added by hand holds the planning back like any other.
        $this->assertContains('Survei Lapangan', $this->procurement->fresh()->pendingRequiredPlanningChecklists()->map(fn ($row) => $row->checklistItem->name)->all());

        // Nowhere else: not on another procurement, not on Data Master.
        app(ProcurementService::class)->syncChecklists($this->other);
        $this->assertSame(2, $this->other->checklists()->where('stage', ProcurementStage::Perencanaan->value)->count());
        $this->assertSame(0, ChecklistItem::query()->shared()->where('name', 'Survei Lapangan')->count());

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $this->procurement->id,
            'type' => ActivityType::ChecklistDiperbarui->value,
            'description' => 'Tahapan Survei Lapangan ditambahkan ke Perencanaan.',
        ]);
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
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'perencanaan', 'name' => 'Langkah Lain'])
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
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'perencanaan', 'name' => 'Langkah Lain'])
            ->assertRedirect();

        // Closed once the planning is submitted.
        $this->procurement->forceFill(['planning_approval_state' => PlanningApprovalState::MenungguPersetujuan])->save();

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'perencanaan', 'name' => 'Terlambat'])
            ->assertForbidden();

        $this->actingAs($this->teamLeader)
            ->delete(route('procurements.steps.destroy', [$this->procurement, $row]))
            ->assertForbidden();

        // The execution is still open to adjust.
        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), ['stage' => 'pelaksanaan', 'name' => 'Uji Fungsi'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['Langkah Lain', 'Uji Fungsi'], ChecklistItem::query()->where('is_custom', true)->orderBy('id')->pluck('name')->all());
    }

    public function test_a_step_added_by_hand_may_ask_for_a_document(): void
    {
        $type = DocumentType::factory()->create(['name' => 'Laporan Survei', 'upload_only' => true]);

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), [
                'stage' => 'pelaksanaan',
                'name' => 'Laporan Survei Lokasi',
                'document_type_id' => $type->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teamLeader)
            ->get(route('procurements.show', $this->procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checklists.pelaksanaan.1.name', 'Laporan Survei Lokasi')
                ->has('checklists.pelaksanaan.1.documents', 1)
                ->where('checklists.pelaksanaan.1.documents.0.type_id', $type->id));

        // A Penyerahan step is an upload on the step itself, not a document.
        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $this->procurement), [
                'stage' => 'penyerahan',
                'name' => 'Foto Serah Terima',
                'document_type_id' => $type->id,
            ])
            ->assertSessionHasErrors('document_type_id');
    }

    public function test_a_penyerahan_step_added_by_hand_stands_beside_the_contract_requirements(): void
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

        $this->actingAs($this->teamLeader)
            ->post(route('procurements.steps.store', $procurement), ['stage' => 'penyerahan', 'name' => 'Foto Serah Terima'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Read again on every visit: the contract never switches it off, and
        // it does not stand in for the template's requirements either.
        $this->actingAs($this->teamLeader)->get(route('procurements.show', $procurement))->assertOk();

        $custom = ChecklistItem::query()->where('name', 'Foto Serah Terima')->sole();
        $this->assertTrue($custom->is_active);
        $this->assertSame(FinanceSection::Penyerahan, $custom->section);
        $this->assertSame($templateRows + 1, $this->handoverRows($procurement)->count());

        $row = $this->row($procurement, $custom);
        $this->assertTrue($row?->needsOwnUpload());

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
