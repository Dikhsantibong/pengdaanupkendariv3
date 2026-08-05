<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Models\BudgetSource;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\ProcurementAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The planning PIC can be appointed straight from the create form, and doing so
 * has to behave exactly like appointing them from the appointment screen.
 */
class PlannerOnCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_create_form_offers_the_planning_pics(): void
    {
        $planner = User::factory()->planner()->create(['name' => 'Himatullah']);
        User::factory()->executor()->create(['name' => 'Sabrin']);

        $this->actingAs(User::factory()->teamLeader()->create())
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('procurements/create')
                ->has('options.planners', 1)
                ->where('options.planners.0.value', $planner->id)
                ->where('options.planners.0.label', 'Himatullah')
            );
    }

    public function test_appointing_the_planner_on_create_notifies_and_is_logged(): void
    {
        Notification::fake();

        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();

        $this->actingAs($teamLeader)
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'planner_id' => $planner->id,
            ])
            ->assertRedirect();

        $procurement = Procurement::query()->firstOrFail();

        $this->assertSame($planner->id, $procurement->planner_id);

        Notification::assertSentTo($planner, ProcurementAssigned::class);

        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::PicDitunjuk->value,
        ]);
        $this->assertDatabaseHas('procurement_activities', [
            'procurement_id' => $procurement->id,
            'type' => ActivityType::Dibuat->value,
        ]);
    }

    public function test_the_planner_can_open_the_procurement_created_for_them(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();

        $this->actingAs($teamLeader)->post(route('procurements.store'), [
            ...$this->payload(),
            'planner_id' => $planner->id,
        ]);

        $procurement = Procurement::query()->firstOrFail();

        $this->actingAs($planner)
            ->get(route('procurements.show', $procurement))
            ->assertOk();

        $this->actingAs(User::factory()->planner()->create())
            ->get(route('procurements.show', $procurement))
            ->assertForbidden();
    }

    public function test_the_planner_is_optional(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.store'), $this->payload())
            ->assertRedirect();

        $this->assertNull(Procurement::query()->firstOrFail()->planner_id);

        Notification::assertNothingSent();
    }

    public function test_a_user_who_is_not_a_planning_pic_is_rejected(): void
    {
        $executor = User::factory()->executor()->create();

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'planner_id' => $executor->id,
            ])
            ->assertSessionHasErrors('planner_id');

        $this->assertDatabaseCount('procurements', 0);
    }

    public function test_an_inactive_planner_is_rejected(): void
    {
        $planner = User::factory()->planner()->create(['is_active' => false]);

        $this->actingAs(User::factory()->teamLeader()->create())
            ->post(route('procurements.store'), [
                ...$this->payload(),
                'planner_id' => $planner->id,
            ])
            ->assertSessionHasErrors('planner_id');
    }

    public function test_the_update_form_ignores_a_planner_sent_with_it(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();
        $planner = User::factory()->planner()->create();
        $other = User::factory()->planner()->create();

        $this->actingAs($teamLeader)->post(route('procurements.store'), [
            ...$this->payload(),
            'planner_id' => $planner->id,
        ]);

        $procurement = Procurement::query()->firstOrFail();

        // PIC changes belong on the appointment screen, which notifies the
        // handover; the edit form must not be a silent back door.
        $this->actingAs($teamLeader)
            ->put(route('procurements.update', $procurement), [
                ...$this->payload(),
                'name' => 'Nama Diperbarui',
                'planner_id' => $other->id,
            ])
            ->assertRedirect();

        $procurement->refresh();

        $this->assertSame('Nama Diperbarui', $procurement->name);
        $this->assertSame($planner->id, $procurement->planner_id);
    }

    /**
     * A valid create payload without the planner.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'name' => 'Pengadaan Uji PIC',
            'work_director_id' => WorkDirector::factory()->create()->id,
            'target_unit_id' => TargetUnit::factory()->create()->id,
            'procurement_method_id' => ProcurementMethod::factory()->create()->id,
            'budget_source_id' => BudgetSource::factory()->create()->id,
            'hpe_value' => 1_000_000,
            'progress_status_id' => ProgressStatus::factory()->asDefault()->create()->id,
        ];
    }
}
