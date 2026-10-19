<?php

namespace Tests\Feature\Procurements;

use App\Enums\PlanningApprovalState;
use App\Models\ContractNumberFormat;
use App\Models\Procurement;
use App\Models\ProcurementStatusHistory;
use App\Models\User;
use App\Services\ProgressStatusResolver;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Status Progres card shows every status a job entered and where it
 * stands on its way to Terkontrak.
 */
class StatusJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);
    }

    protected function format(string $code): int
    {
        return (int) ContractNumberFormat::query()->where('code', $code)->value('id');
    }

    public function test_each_status_change_is_recorded_with_who_caused_it(): void
    {
        $planner = User::factory()->planner()->create();
        $procurement = Procurement::factory()->plannedBy($planner)->create(['contract_number_format_id' => $this->format('SPK')]);
        $first = $procurement->refresh()->progressStatus->system_key;

        $this->actingAs($planner);
        $procurement->forceFill([
            'planning_approval_state' => PlanningApprovalState::MenungguPersetujuan,
            'planning_submitted_at' => now(),
        ])->save();

        $keys = $procurement->statusHistories()->pluck('system_key')->all();
        $this->assertSame($first, $keys[count($keys) - 2]);
        $this->assertSame('approval_icc', end($keys));
        $this->assertSame($planner->id, $procurement->statusHistories()->get()->last()?->user_id);
    }

    public function test_an_spk_job_shows_its_place_and_how_many_statuses_are_left(): void
    {
        // A job that began as a proposal from its division.
        $procurement = Procurement::factory()
            ->planningSubmitted()
            ->create(['contract_number_format_id' => $this->format('SPK'), 'proposal_submitted_at' => now()->subWeek()]);

        $journey = app(ProgressStatusResolver::class)->journey($procurement->refresh());
        $keys = array_column($journey['steps'], 'key');

        // From the proposal through the SPK execution to Selesai.
        $this->assertSame([
            'penyusunan_tor', 'draft_nodis_usulan', 'approval_usulan',
            'inisiasi_rendan', 'penyusunan_rks', 'menunggu_penawaran', 'draft_nodis_pengadaan', 'approval_icc',
            'inisiasi_laksdan', 'penyusunan_hps', 'proses_smart_scm', 'penyusunan_kontrak', 'approval_tl_pengadaan',
            'terkontrak', 'selesai',
        ], $keys);

        $this->assertSame(8, $journey['position']);
        $this->assertSame(14, $journey['to_contract']);
        $this->assertSame(6, $journey['remaining']);
        $this->assertSame('done', $journey['steps'][6]['state']);
        $this->assertSame('current', $journey['steps'][7]['state']);
        $this->assertSame('upcoming', $journey['steps'][8]['state']);
        // When it entered the status it is in.
        $this->assertNotNull($journey['steps'][7]['at']);
    }

    public function test_an_sppl_job_follows_its_shorter_way(): void
    {
        $procurement = Procurement::factory()
            ->planningApproved()
            ->create(['contract_number_format_id' => $this->format('SPPL'), 'executor_id' => null]);

        $journey = app(ProgressStatusResolver::class)->journey($procurement->refresh());
        $keys = array_column($journey['steps'], 'key');

        $this->assertContains('draft_nodis_laksdan', $keys);
        $this->assertContains('penyusunan_surat_pesanan', $keys);
        $this->assertNotContains('penyusunan_rks', $keys);
        $this->assertNotContains('proses_smart_scm', $keys);
        // Inisiasi Laksdan, then Penyusunan Surat Pesanan, Approval TL Pengadaan, Terkontrak.
        $this->assertSame('inisiasi_laksdan', $keys[$journey['position'] - 1]);
        $this->assertSame(3, $journey['remaining']);
    }

    public function test_a_returned_planning_stands_in_the_place_of_its_approval(): void
    {
        $procurement = Procurement::factory()->create([
            'contract_number_format_id' => $this->format('PJ'),
            'planning_approval_state' => PlanningApprovalState::Ditolak,
            'planning_submitted_at' => now()->subDay(),
            'planning_reviewed_at' => now(),
        ]);

        $journey = app(ProgressStatusResolver::class)->journey($procurement->refresh());

        $this->assertSame('Revisi Perencanaan', $journey['revision']);
        $this->assertSame('approval_icc', $journey['steps'][$journey['position'] - 1]['key']);
    }

    public function test_a_job_created_directly_starts_at_inisiasi_rendan(): void
    {
        $procurement = Procurement::factory()->planningSubmitted()->create(['contract_number_format_id' => $this->format('SPK')]);

        $journey = app(ProgressStatusResolver::class)->journey($procurement->refresh());

        $this->assertSame('inisiasi_rendan', $journey['steps'][0]['key']);
        $this->assertSame(5, $journey['position']);
        $this->assertSame(11, $journey['to_contract']);
        $this->assertSame(6, $journey['remaining']);
    }

    public function test_the_detail_page_carries_the_journey_and_the_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $procurement = Procurement::factory()->planningSubmitted()->create(['contract_number_format_id' => $this->format('SPK')]);

        $this->actingAs($administrator)
            ->get(route('procurements.show', $procurement))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('detail.status_journey.remaining', 6)
                ->where('detail.status_journey.cancelled', false)
                ->has('detail.status_history', fn (Assert $history) => $history
                    ->first(fn (Assert $entry) => $entry
                        ->where('key', 'approval_icc')
                        ->where('name', 'Menunggu Approval ICC')
                        ->etc())
                    ->etc()));
    }

    public function test_the_history_of_running_jobs_is_rebuilt_from_their_activity(): void
    {
        $procurement = Procurement::factory()->planningSubmitted()->create(['contract_number_format_id' => $this->format('SPK')]);
        $asman = User::factory()->create();
        $procurement->activities()->create(['user_id' => $asman->id, 'type' => 'usulan_disetujui', 'description' => 'Disetujui.', 'created_at' => now()->subDays(5)]);
        $procurement->activities()->create(['user_id' => null, 'type' => 'perencanaan_diajukan', 'description' => 'Diajukan.', 'created_at' => now()->subDay()]);
        ProcurementStatusHistory::query()->delete();

        $migration = require database_path('migrations/2026_10_18_195209_create_procurement_status_histories_table.php');
        (fn () => $this->backfill())->call($migration);

        $entries = $procurement->statusHistories()->get();
        $this->assertSame(['inisiasi_rendan', 'approval_icc'], $entries->pluck('system_key')->all());
        $this->assertSame($asman->id, $entries->first()?->user_id);
    }
}
