<?php

namespace Tests\Feature;

use App\Enums\CoaRequestStatus;
use App\Enums\CoaSegment;
use App\Enums\UserRole;
use App\Models\CoaRequest;
use App\Models\CoaSegmentOption;
use App\Models\User;
use App\Models\WorkDirector;
use App\Notifications\CoaRequestNotification;
use App\Support\CoaCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoaRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_coa_segments_are_seeded_across_all_eight_segments(): void
    {
        foreach (CoaSegment::cases() as $segment) {
            $this->assertGreaterThan(
                0,
                CoaSegmentOption::query()->forSegment($segment)->active()->count(),
                "Expected default options for {$segment->value}.",
            );
        }

        // Verify exact code composition matching UP Kendari Excel examples.
        $this->assertSame(
            'AKD213720002320F102/JBJSLN',
            CoaCatalog::composeCode('A', 'KD', '21', '372', '000', '23', '20', 'F102/JBJSLN'),
        );
        $this->assertSame(
            'CKD192820013822F104/JBJSTK',
            CoaCatalog::composeCode('C', 'KD', '19', '282', '001', '38', '22', 'F104/JBJSTK'),
        );
    }

    public function test_administrator_can_manage_coa_segments_in_master_data(): void
    {
        $admin = User::factory()->administrator()->create();
        $proposer = User::factory()->proposer(WorkDirector::factory()->create())->create();

        $this->actingAs($proposer)
            ->get(route('master-data.coa-segments.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('master-data.coa-segments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/coa-segments')
                ->has('segments', 8)
                ->where('records.0.segment', 'segment_1'));

        $this->actingAs($admin)
            ->post(route('master-data.coa-segments.store'), [
                'segment' => CoaSegment::ExpenseElement->value,
                'code' => 'f199/jbtst',
                'name' => 'JASA PENGUJIAN KHUSUS',
                'description' => 'Ditambahkan dari Data Master',
                'sort_order' => 99,
                'is_active' => true,
            ])
            ->assertRedirect();

        $option = CoaSegmentOption::query()
            ->where('segment', CoaSegment::ExpenseElement->value)
            ->where('code', 'F199/JBTST')
            ->firstOrFail();

        $this->assertSame('JASA PENGUJIAN KHUSUS', $option->name);

        $this->actingAs($admin)
            ->put(route('master-data.coa-segments.update', $option), [
                'segment' => CoaSegment::ExpenseElement->value,
                'code' => 'F199/JBTST',
                'name' => 'JASA PENGUJIAN KHUSUS REVISI',
                'description' => 'Diubah admin',
                'sort_order' => 50,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame('JASA PENGUJIAN KHUSUS REVISI', $option->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('master-data.coa-segments.destroy', $option))
            ->assertRedirect();

        $this->assertSoftDeleted('coa_segment_options', ['id' => $option->id]);
    }

    public function test_proposer_can_save_draft_edit_and_request_activation_with_finance_notification(): void
    {
        Notification::fake();

        $division = WorkDirector::factory()->create(['name' => 'Asman Pemeliharaan']);
        $staf = User::factory()->proposer($division)->create(['name' => 'Staf Pemeliharaan']);
        $keuangan = User::factory()->role(UserRole::Keuangan)->create(['name' => 'Staf Keuangan']);

        // 1. Save as draft (Segment 2 defaults to KD automatically when omitted).
        $this->actingAs($staf)
            ->post(route('coa-requests.store'), [
                'segment_1' => 'A',
                'segment_2' => '',
                'segment_3' => '21',
                'segment_4' => '372',
                'segment_5' => '000',
                'segment_6' => '23',
                'segment_7' => '20',
                'expense_element' => 'F102/JBJSLN',
                'description' => 'Pemeliharaan Water Way System Mikuasi',
                'action' => 'save',
            ])
            ->assertRedirect();

        $coa = CoaRequest::query()->firstOrFail();
        $this->assertSame('AKD213720002320F102/JBJSLN', $coa->coa_code);
        $this->assertSame('KD', $coa->segment_2);
        $this->assertSame(CoaRequestStatus::Draft, $coa->status);
        $this->assertSame($division->id, $coa->work_director_id);
        $this->assertNull($coa->requested_at);
        Notification::assertNothingSent();

        // 2. Edit draft and submit for activation ("Request Aktivasi").
        $this->actingAs($staf)
            ->put(route('coa-requests.update', $coa), [
                'segment_1' => 'A',
                'segment_2' => 'KD',
                'segment_3' => '21',
                'segment_4' => '375',
                'segment_5' => '000',
                'segment_6' => '23',
                'segment_7' => '20',
                'expense_element' => 'F102/JBJSLN',
                'description' => 'Pemeliharaan Water Way System Sabilambo',
                'action' => 'request_activation',
            ])
            ->assertRedirect();

        $coa->refresh();
        $this->assertSame('AKD213750002320F102/JBJSLN', $coa->coa_code);
        $this->assertSame(CoaRequestStatus::Pending, $coa->status);
        $this->assertNotNull($coa->requested_at);

        Notification::assertSentTo($keuangan, CoaRequestNotification::class);

        // 3. Proposer cannot edit after submission and cannot activate.
        $this->actingAs($staf)
            ->put(route('coa-requests.update', $coa), [
                'segment_1' => 'A',
                'segment_2' => 'KD',
                'segment_3' => '21',
                'segment_4' => '377',
                'segment_5' => '000',
                'segment_6' => '23',
                'segment_7' => '20',
                'expense_element' => 'F102/JBJSLN',
                'action' => 'save',
            ])
            ->assertForbidden();

        $this->actingAs($staf)
            ->post(route('coa-requests.activate', $coa))
            ->assertForbidden();
    }

    public function test_keuangan_receives_all_requests_can_edit_and_activate_and_sync_to_proposal_form(): void
    {
        Notification::fake();

        $divisionA = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
        $divisionB = WorkDirector::factory()->create(['name' => 'Asman Engineering']);
        $tl = User::factory()->teamLeaderPengadaan()->create(['name' => 'TL Pengadaan']);
        $stafA = User::factory()->proposer($divisionA)->create(['name' => 'Pengusul A']);
        $stafB = User::factory()->proposer($divisionB)->create(['name' => 'Pengusul B']);
        $keuangan = User::factory()->role(UserRole::Keuangan)->create(['name' => 'Bagian Keuangan']);

        // Draft stays private from Keuangan's queue, submitted requests from all divisions appear.
        $draft = CoaRequest::factory()->create([
            'requested_by' => $stafA->id,
            'work_director_id' => $divisionA->id,
            'status' => CoaRequestStatus::Draft,
        ]);

        $pendingFromStaf = CoaRequest::factory()->pending()->create([
            'requested_by' => $stafB->id,
            'work_director_id' => $divisionB->id,
            'segment_1' => 'C',
            'segment_2' => 'KD',
            'segment_3' => '19',
            'segment_4' => '282',
            'segment_5' => '002',
            'segment_6' => '38',
            'segment_7' => '22',
            'expense_element' => 'F104/JBJSTK',
            'coa_code' => 'CKD192820023822F104/JBJSTK',
            'description' => 'Corrective Maintenance Unit 2 Nii Tanasa',
        ]);

        $pendingFromTl = CoaRequest::factory()->pending()->create([
            'requested_by' => $tl->id,
            'segment_1' => 'A',
            'segment_2' => 'KD',
            'segment_3' => '18',
            'segment_4' => '277',
            'segment_5' => '000',
            'segment_6' => '02',
            'segment_7' => '11',
            'expense_element' => 'F104/JBJSTK',
            'coa_code' => 'AKD182770000211F104/JBJSTK',
        ]);

        // Keuangan sees both pending requests (not other users' drafts) on Request COA & Dashboard.
        $this->actingAs($keuangan)
            ->get(route('coa-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('coa-requests/index')
                ->where('summary.pending', 2)
                ->where('summary.draft', 0)
                ->where('canManage', true));

        $this->actingAs($keuangan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.coaActivations.total', 2));

        // Before activation, none appear in activeCoas on Usulan Pekerjaan form.
        $this->actingAs($stafA)
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.activeCoas', 0));

        // Keuangan edits segment 5 (Unit 2 -> Unit 1) and activates in one step.
        $this->actingAs($keuangan)
            ->put(route('coa-requests.update', $pendingFromStaf), [
                'segment_1' => 'C',
                'segment_2' => 'KD',
                'segment_3' => '19',
                'segment_4' => '282',
                'segment_5' => '001',
                'segment_6' => '38',
                'segment_7' => '22',
                'expense_element' => 'F104/JBJSTK',
                'description' => 'Corrective Maintenance Unit 1 Nii Tanasa',
                'notes' => 'Dikoreksi dari Unit 2 ke Unit 1 oleh Keuangan',
                'action' => 'activate',
            ])
            ->assertRedirect();

        $pendingFromStaf->refresh();
        $this->assertSame('CKD192820013822F104/JBJSTK', $pendingFromStaf->coa_code);
        $this->assertSame(CoaRequestStatus::Active, $pendingFromStaf->status);
        $this->assertSame($keuangan->id, $pendingFromStaf->activated_by);
        $this->assertNotNull($pendingFromStaf->activated_at);
        Notification::assertSentTo($stafB, CoaRequestNotification::class);

        // Keuangan can also activate directly with the Aktivasi button.
        $this->actingAs($keuangan)
            ->post(route('coa-requests.activate', $pendingFromTl))
            ->assertRedirect();

        $this->assertSame(CoaRequestStatus::Active, $pendingFromTl->fresh()->status);

        // Both activated COAs now sync into Usulan Pekerjaan form; draft stays excluded.
        $this->actingAs($stafA)
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.activeCoas', 2)
                ->where('options.activeCoas', function ($coas) use ($draft): bool {
                    $codes = collect($coas)->pluck('value')->all();

                    return in_array('CKD192820013822F104/JBJSTK', $codes, true)
                        && in_array('AKD182770000211F104/JBJSTK', $codes, true)
                        && ! in_array($draft->coa_code, $codes, true);
                }));
    }

    public function test_unauthorized_roles_cannot_open_request_coa_by_default(): void
    {
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();

        $this->actingAs($planner)->get(route('coa-requests.index'))->assertForbidden();
        $this->actingAs($executor)->get(route('coa-requests.index'))->assertForbidden();
    }
}
