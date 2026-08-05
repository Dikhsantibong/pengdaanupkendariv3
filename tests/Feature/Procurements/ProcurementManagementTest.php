<?php

namespace Tests\Feature\Procurements;

use App\Enums\ProcurementStage;
use App\Models\BudgetSource;
use App\Models\ChecklistItem;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_leader_can_register_a_procurement_with_generated_number_and_checklists(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $director = WorkDirector::factory()->create();
        $unit = TargetUnit::factory()->create();
        $method = ProcurementMethod::factory()->create();
        $budgetSource = BudgetSource::factory()->create();
        $status = ProgressStatus::factory()->asDefault()->create();

        ChecklistItem::factory()->stage(ProcurementStage::Perencanaan)->count(3)->create();
        ChecklistItem::factory()->stage(ProcurementStage::Pelaksanaan)->count(2)->create();

        $response = $this->actingAs($teamLeader)->post(route('procurements.store'), [
            'name' => 'Pemeliharaan Rutin Mesin Unit 1',
            'work_director_id' => $director->id,
            'target_unit_id' => $unit->id,
            'procurement_method_id' => $method->id,
            'budget_source_id' => $budgetSource->id,
            'pr_ro_number_id' => null,
            'prk_number' => 'ND-021/PRK/2026',
            'hpe_value' => 250_000_000,
            'progress_status_id' => $status->id,
            'target_completion_date' => null,
            'notes' => null,
        ]);

        $procurement = Procurement::query()->firstOrFail();

        $response->assertRedirect(route('procurements.show', $procurement));

        $this->assertSame('Pemeliharaan Rutin Mesin Unit 1', $procurement->name);
        $this->assertSame($method->id, $procurement->procurement_method_id);
        $this->assertSame($budgetSource->id, $procurement->budget_source_id);
        $this->assertSame($teamLeader->id, $procurement->created_by);
        $this->assertMatchesRegularExpression('#^PGD/\d{4}/\d{2}/0001$#', $procurement->number);
        $this->assertCount(5, $procurement->checklists);
        $this->assertCount(3, $procurement->checklists->where('stage', ProcurementStage::Perencanaan));
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => 'dibuat',
        ]);
    }

    public function test_procurement_numbers_increment_within_the_same_month(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $director = WorkDirector::factory()->create();
        $unit = TargetUnit::factory()->create();
        $status = ProgressStatus::factory()->asDefault()->create();

        $payload = [
            'work_director_id' => $director->id,
            'target_unit_id' => $unit->id,
            'procurement_method_id' => ProcurementMethod::factory()->create()->id,
            'budget_source_id' => BudgetSource::factory()->create()->id,
            'hpe_value' => 1_000_000,
            'progress_status_id' => $status->id,
        ];

        $this->actingAs($teamLeader)->post(route('procurements.store'), [...$payload, 'name' => 'Pengadaan A']);
        $this->actingAs($teamLeader)->post(route('procurements.store'), [...$payload, 'name' => 'Pengadaan B']);

        $numbers = Procurement::query()->orderBy('id')->pluck('number')->all();

        $this->assertStringEndsWith('/0001', $numbers[0]);
        $this->assertStringEndsWith('/0002', $numbers[1]);
    }

    public function test_procurement_creation_requires_the_mandatory_fields(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)
            ->from(route('procurements.create'))
            ->post(route('procurements.store'), [])
            ->assertSessionHasErrors([
                'name',
                'work_director_id',
                'target_unit_id',
                'procurement_method_id',
                'budget_source_id',
                'hpe_value',
                'progress_status_id',
            ]);

        $this->assertDatabaseCount('procurements', 0);
    }

    public function test_pic_cannot_register_a_procurement(): void
    {
        $planner = User::factory()->planner()->create();
        $director = WorkDirector::factory()->create();
        $unit = TargetUnit::factory()->create();
        $status = ProgressStatus::factory()->create();

        $this->actingAs($planner)->post(route('procurements.store'), [
            'name' => 'Percobaan',
            'work_director_id' => $director->id,
            'target_unit_id' => $unit->id,
            'procurement_method_id' => ProcurementMethod::factory()->create()->id,
            'budget_source_id' => BudgetSource::factory()->create()->id,
            'hpe_value' => 1_000_000,
            'progress_status_id' => $status->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('procurements', 0);
    }

    public function test_team_leader_can_update_and_archive_a_procurement(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $procurement = Procurement::factory()->create();

        $this->actingAs($teamLeader)->put(route('procurements.update', $procurement), [
            'name' => 'Nama Diperbarui',
            'work_director_id' => $procurement->work_director_id,
            'target_unit_id' => $procurement->target_unit_id,
            'procurement_method_id' => $procurement->procurement_method_id,
            'budget_source_id' => $procurement->budget_source_id,
            'hpe_value' => 500_000_000,
            'progress_status_id' => $procurement->progress_status_id,
        ])->assertRedirect(route('procurements.show', $procurement));

        $this->assertSame('Nama Diperbarui', $procurement->refresh()->name);

        $this->actingAs($teamLeader)
            ->delete(route('procurements.destroy', $procurement))
            ->assertRedirect(route('procurements.index'));

        $this->assertSoftDeleted($procurement);
    }
}
