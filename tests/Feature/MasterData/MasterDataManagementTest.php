<?php

namespace Tests\Feature\MasterData;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_direksi_pekerjaan(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('master-data.work-directors.store'), [
                'name' => 'Asman Pemeliharaan',
                'description' => null,
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect();

        $director = WorkDirector::query()->firstOrFail();
        $this->assertSame('Asman Pemeliharaan', $director->name);

        $this->actingAs($administrator)
            ->put(route('master-data.work-directors.update', $director), [
                'name' => 'Asman Operasi',
                'description' => 'Pengarah operasi',
                'sort_order' => 2,
                'is_active' => false,
            ])
            ->assertRedirect();

        $director->refresh();

        $this->assertSame('Asman Operasi', $director->name);
        $this->assertFalse($director->is_active);
    }

    public function test_master_data_names_must_stay_unique(): void
    {
        $administrator = User::factory()->administrator()->create();
        WorkDirector::factory()->create(['name' => 'Asman Engineering']);

        $this->actingAs($administrator)
            ->from(route('master-data.work-directors.index'))
            ->post(route('master-data.work-directors.store'), [
                'name' => 'Asman Engineering',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_deleting_master_data_is_a_soft_delete_that_keeps_procurement_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $unit = TargetUnit::factory()->create(['name' => 'PLTD Poasia']);
        $procurement = Procurement::factory()->create(['target_unit_id' => $unit->id]);

        $this->actingAs($administrator)
            ->delete(route('master-data.target-units.destroy', $unit))
            ->assertRedirect();

        $this->assertSoftDeleted($unit);
        $this->assertSame('PLTD Poasia', $procurement->refresh()->targetUnit->name);
    }

    public function test_a_status_still_used_by_a_procurement_cannot_be_deleted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $status = ProgressStatus::factory()->create();
        Procurement::factory()->create(['progress_status_id' => $status->id]);

        $this->actingAs($administrator)
            ->from(route('master-data.progress-statuses.index'))
            ->delete(route('master-data.progress-statuses.destroy', $status))
            ->assertSessionHasErrors('name');

        $this->assertNotSoftDeleted($status);
    }

    public function test_only_one_status_stays_the_default(): void
    {
        $administrator = User::factory()->administrator()->create();
        $existing = ProgressStatus::factory()->asDefault()->create(['name' => 'Pending']);

        $this->actingAs($administrator)
            ->post(route('master-data.progress-statuses.store'), [
                'name' => 'Inisiasi Laksdan',
                'category' => 'berjalan',
                'sort_order' => 2,
                'is_default' => true,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertFalse($existing->refresh()->is_default);
        $this->assertSame(1, ProgressStatus::query()->where('is_default', true)->count());
    }

    public function test_team_leader_cannot_reach_the_master_data_screens(): void
    {
        $teamLeader = User::factory()->teamLeader()->create();

        $this->actingAs($teamLeader)
            ->get(route('master-data.work-directors.index'))
            ->assertForbidden();

        $this->actingAs($teamLeader)
            ->post(route('master-data.work-directors.store'), [
                'name' => 'Asman Baru',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_administrator_can_publish_a_new_template_version_and_deactivate_the_previous_one(): void
    {
        $administrator = User::factory()->administrator()->create();
        $documentType = DocumentType::factory()->create();
        $first = DocumentTemplate::factory()->create([
            'document_type_id' => $documentType->id,
            'version' => 1,
        ]);

        $this->actingAs($administrator)
            ->post(route('master-data.document-templates.store'), [
                'document_type_id' => $documentType->id,
                'name' => 'Template Resmi RKS',
                'body' => '<h1>{{nama_pengadaan}}</h1><p>{{unit_tujuan}}</p>',
                'is_active' => true,
            ])
            ->assertRedirect();

        $latest = DocumentTemplate::query()->orderByDesc('version')->firstOrFail();

        $this->assertSame(2, $latest->version);
        $this->assertTrue($latest->is_active);
        $this->assertSame(['nama_pengadaan', 'unit_tujuan'], $latest->placeholders);
        $this->assertFalse($first->refresh()->is_active);
    }
}
