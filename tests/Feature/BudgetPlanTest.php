<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BudgetPlan;
use App\Models\User;
use App\Models\WorkDirector;
use App\Services\AccessRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Perencanaan Anggaran: the Anggaran role keeps the PRK of each bidang, the
 * team leaders read them, and the proposers of that bidang pick them.
 */
class BudgetPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $budgeter;

    protected WorkDirector $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->budgeter = User::factory()->role(UserRole::Anggaran)->create();
        $this->division = WorkDirector::factory()->create(['name' => 'Asman Operasi']);
    }

    public function test_haskar_udin_has_an_anggaran_account(): void
    {
        // The account migration is skipped while testing, so other tests
        // keep their own users; it is run here on purpose, twice, to show
        // a second run changes nothing.
        $this->app->instance('migrations.force-anggaran-account', true);
        $migration = require database_path('migrations/2026_11_12_032427_create_anggaran_account_for_haskar_udin.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, User::query()->where('email', 'haskar.udin@upkendari.go.id')->count());

        $haskar = User::query()->where('email', 'haskar.udin@upkendari.go.id')->firstOrFail();

        $this->assertSame('Haskar Udin', $haskar->name);
        $this->assertSame(UserRole::Anggaran, $haskar->role);
        $this->assertTrue($haskar->is_active);
        $this->assertTrue(Hash::check('password', $haskar->password));
    }

    public function test_the_anggaran_role_adds_changes_and_removes_a_prk(): void
    {
        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('budget-plans/index')
                ->where('canManage', true)
                ->has('plans', 0));

        $this->actingAs($this->budgeter)
            ->post(route('budget-plans.store'), [
                'prk_induk' => '2026.AO.01.001',
                'work_director_id' => $this->division->id,
                'prk_induk_description' => 'Pemeliharaan Mesin Pembangkit',
                'sub_program_description' => 'Overhaul Mesin PLTD',
                'ai_plan_value' => 1500000000,
                'aki_plan_value' => 1200000000,
                'wo_number' => 'WO-2026-0042',
                'pr_number' => '1000012345',
                // The contract, physical progress and payment columns.
                'district' => 'UPKD',
                'po_stock_type' => 'Non Stock',
                'po_number' => 'KDCD54',
                'allocation' => 'UNIT',
                'po_item' => '1-4',
                'supplier' => 'PT. ALTRAK 1978',
                'po_date' => '2025-05-19',
                'po_value' => 650792334,
                'delivery_date' => '2026-03-31',
                'contract_progress' => 'Jasa Pekerjaan 29 Hari Kerja',
                'physical_progress' => 'Pekerjaan Selesai, BASTP 25 Februari 2026',
                'invoice_value' => 650792334,
                'invoice_number' => 'AKD0526OKDCD5402',
                'payment_dates' => '05/06/2026',
                'payment_proof_number' => 'F26011',
                'payment_progress' => 'TERBAYAR 100%',
            ])
            ->assertSessionHasNoErrors();

        $plan = BudgetPlan::query()->sole();
        $this->assertSame($this->division->id, $plan->work_director_id);
        $this->assertSame('1500000000.00', $plan->ai_plan_value);
        $this->assertSame($this->budgeter->id, $plan->created_by);
        $this->assertSame('PT. ALTRAK 1978', $plan->supplier);
        $this->assertSame('2025-05-19', $plan->po_date?->toDateString());
        $this->assertSame('650792334.00', $plan->invoice_value);
        $this->assertSame('TERBAYAR 100%', $plan->payment_progress);

        $this->actingAs($this->budgeter)
            ->put(route('budget-plans.update', $plan), [
                'prk_induk' => '2026.AO.01.001',
                'work_director_id' => $this->division->id,
                'aki_plan_value' => 1100000000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('1100000000.00', $plan->refresh()->aki_plan_value);

        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 1)
                ->where('plans.0.work_director', 'Asman Operasi')
                ->where('totals.aki', 1100000000));

        $this->actingAs($this->budgeter)
            ->delete(route('budget-plans.destroy', $plan))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('budget_plans', 0);
    }

    public function test_a_prk_needs_its_number_and_bidang(): void
    {
        $this->actingAs($this->budgeter)
            ->post(route('budget-plans.store'), ['ai_plan_value' => -5])
            ->assertSessionHasErrors(['prk_induk', 'work_director_id', 'ai_plan_value']);

        $this->assertDatabaseCount('budget_plans', 0);
    }

    public function test_the_list_filters_by_bidang_and_search(): void
    {
        $other = WorkDirector::factory()->create();
        BudgetPlan::factory()->create(['prk_induk' => '2026.AO.01.001', 'work_director_id' => $this->division->id]);
        BudgetPlan::factory()->create(['prk_induk' => '2026.HAR.02.002', 'work_director_id' => $other->id]);

        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index', ['bidang' => $this->division->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 1)
                ->where('plans.0.prk_induk', '2026.AO.01.001'));

        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index', ['cari' => '2026.HAR']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 1)
                ->where('plans.0.prk_induk', '2026.HAR.02.002'));
    }

    public function test_team_leaders_read_the_plan_but_do_not_change_it(): void
    {
        $teamLeader = User::factory()->teamLeaderPengadaan()->create();
        $plan = BudgetPlan::factory()->create();

        $this->actingAs($teamLeader)
            ->get(route('budget-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($teamLeader)
            ->post(route('budget-plans.store'), ['prk_induk' => 'X', 'work_director_id' => $this->division->id])
            ->assertForbidden();
        $this->actingAs($teamLeader)->delete(route('budget-plans.destroy', $plan))->assertForbidden();

        $this->assertDatabaseCount('budget_plans', 1);
    }

    public function test_other_roles_do_not_see_the_plan(): void
    {
        $this->actingAs(User::factory()->planner()->create())
            ->get(route('budget-plans.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->proposer($this->division)->create())
            ->get(route('budget-plans.index'))
            ->assertForbidden();
    }

    public function test_a_running_database_gets_the_rights_from_the_grant_migration(): void
    {
        // A database migrated before the rights existed has no rows for them.
        DB::table('role_permissions')->whereIn('permission', ['budget-plans.manage', 'menu.budget-plans'])->delete();
        AccessRights::forget();

        $this->actingAs($this->budgeter)->get(route('budget-plans.index'))->assertForbidden();

        $migration = require database_path('migrations/2026_11_12_034046_grant_budget_plan_permissions.php');
        $migration->up();
        $migration->up();

        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', true)
                ->where('auth.permissions.menus.budget-plans', true));

        $this->actingAs(User::factory()->teamLeaderIcc()->create())
            ->get(route('budget-plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    }

    public function test_unknown_choices_are_refused(): void
    {
        $this->actingAs($this->budgeter)
            ->post(route('budget-plans.store'), [
                'prk_induk' => 'KD254A0102',
                'work_director_id' => $this->division->id,
                'po_stock_type' => 'Campuran',
                'allocation' => 'GUDANG',
                'po_date' => 'bukan tanggal',
            ])
            ->assertSessionHasErrors(['po_stock_type', 'allocation', 'po_date']);
    }

    public function test_the_plan_downloads_as_the_anggaran_sheet(): void
    {
        BudgetPlan::factory()->create([
            'prk_induk' => 'KD254A0102',
            'work_director_id' => $this->division->id,
            'prk_induk_description' => 'FINAL STAGE PLTD RAHA #11 (CUMMINS)',
            'ai_plan_value' => 650792000,
            'supplier' => 'PT. ALTRAK 1978',
            'po_date' => '2025-05-19',
            'payment_progress' => 'TERBAYAR 100%',
        ]);
        BudgetPlan::factory()->create(['prk_induk' => 'KD254A0107', 'work_director_id' => WorkDirector::factory()]);

        $response = $this->actingAs($this->budgeter)
            ->get(route('budget-plans.export', ['bidang' => $this->division->id]))
            ->assertOk()
            ->assertDownload();

        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $this->assertSame('UPKD', $sheet->getName());

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();

        // The note, the group band, the headers, one filtered row, the total.
        $this->assertCount(5, $rows);
        $this->assertSame('P R O G R A M', $rows[1][0]);
        $this->assertSame('K O N T R A K', $rows[1][7]);
        $this->assertSame('P E M B A Y A R A N', $rows[1][19]);
        $this->assertSame('PRK INDUK', $rows[2][2]);
        $this->assertSame('Supplier', $rows[2][13]);
        $this->assertSame([1, 'UPKD', 'KD254A0102', 'FINAL STAGE PLTD RAHA #11 (CUMMINS)'], array_slice($rows[3], 0, 4));
        $this->assertEquals(650792000, $rows[3][5]);
        $this->assertSame('PT. ALTRAK 1978', $rows[3][13]);
        $this->assertSame('19/05/2025', $rows[3][14]);
        $this->assertSame('TERBAYAR 100%', $rows[3][23]);
        $this->assertSame('Asman Operasi', $rows[3][24]);
        $this->assertSame('TOTAL', $rows[4][0]);
    }

    public function test_only_who_sees_the_plan_downloads_it(): void
    {
        $this->actingAs(User::factory()->planner()->create())
            ->get(route('budget-plans.export'))
            ->assertForbidden();
    }

    public function test_the_menu_is_shown_to_the_anggaran_role(): void
    {
        $this->actingAs($this->budgeter)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions.menus.budget-plans', true));
    }

    public function test_the_anggaran_role_adds_and_filters_anggaran_operasi_and_energi_primer(): void
    {
        // Add an Anggaran Operasi (AO PEMELIHARAAN) plan.
        $this->actingAs($this->budgeter)
            ->post(route('budget-plans.store'), [
                'budget_type' => 'operasi',
                'prk_induk' => 'KD262G0101',
                'work_director_id' => $this->division->id,
                'prk_induk_description' => 'PENGADAAN MATERIAL DAN JASA BIDANG OPERASI',
                'pagu_value' => 168000.00,
                'realization_value' => 127554.04,
                'disburse_plan' => '01-12',
                'notes' => "Terkontrak 11 site\nDokumen tagihan Real",
            ])
            ->assertSessionHasNoErrors();

        // Add an Anggaran Pendukung Energi Primer plan.
        $this->actingAs($this->budgeter)
            ->post(route('budget-plans.store'), [
                'budget_type' => 'energi_primer',
                'prk_induk' => 'KD262G0102',
                'work_director_id' => $this->division->id,
                'prk_induk_description' => 'JASA TAD PPKIT PLTD DAN PLTM TERSEBAR 11 Site',
                'pagu_value' => 28571662.00,
                'realization_value' => 25238889.68,
                'disburse_plan' => '01-12',
                'notes' => "PT Nusa Daya\nTahap 1 s/d JUNI 26 ok (done)",
            ])
            ->assertSessionHasNoErrors();

        $ao = BudgetPlan::query()->where('prk_induk', 'KD262G0101')->sole();
        $this->assertSame('operasi', $ao->budget_type->value);
        $this->assertSame('168000.00', $ao->pagu_value);
        $this->assertSame('127554.04', $ao->realization_value);
        // Sisa pagu is calculated automatically: 168000.00 - 127554.04 = 40445.96
        $this->assertSame('40445.96', $ao->remaining_pagu_value);

        // Filter by jenis = operasi
        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index', ['jenis' => 'operasi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('budget-plans/index')
                ->where('activeType', 'operasi')
                ->has('plans', 1)
                ->where('plans.0.prk_induk', 'KD262G0101')
                ->where('totals.pagu', fn ($pagu) => (float) $pagu === 168000.0)
                ->where('totals.realisasi', fn ($realisasi) => abs((float) $realisasi - 127554.04) < 0.01)
                ->where('totals.sisa_pagu', fn ($sisa) => abs((float) $sisa - 40445.96) < 0.01));

        // Filter by jenis = energi_primer
        $this->actingAs($this->budgeter)
            ->get(route('budget-plans.index', ['jenis' => 'energi_primer']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeType', 'energi_primer')
                ->has('plans', 1)
                ->where('plans.0.prk_induk', 'KD262G0102')
                ->where('totals.pagu', fn ($pagu) => (float) $pagu === 28571662.0)
                ->where('totals.realisasi', fn ($realisasi) => abs((float) $realisasi - 25238889.68) < 0.01));

        // Export as Operasi Excel sheet
        $response = $this->actingAs($this->budgeter)
            ->get(route('budget-plans.export', ['jenis' => 'operasi']))
            ->assertOk()
            ->assertDownload();

        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertCount(3, $rows);
        $this->assertSame(['NO', 'PRK', 'DESKRIPSI', 'BIDANG', 'PAGU', 'SISA PAGU', 'RENCANA DISBURSE', 'REALISASI s/d SM 2 (OKTOBER)', 'KET'], $rows[0]);
        $this->assertSame([1, 'KD262G0101', 'PENGADAAN MATERIAL DAN JASA BIDANG OPERASI', 'Asman Operasi'], array_slice($rows[1], 0, 4));
        $this->assertEquals(168000.0, $rows[1][4]);
        $this->assertEquals(40445.96, $rows[1][5]);
        $this->assertSame('TOTAL', $rows[2][0]);
    }

    public function test_the_proposal_form_offers_the_prk_of_each_bidang(): void
    {
        $plan = BudgetPlan::factory()->create([
            'prk_induk' => '2026.AO.01.001',
            'work_director_id' => $this->division->id,
            'sub_program_description' => 'Overhaul Mesin PLTD',
            'wo_number' => 'WO-2026-0042',
            'pr_number' => '1000012345',
        ]);

        $this->actingAs(User::factory()->proposer($this->division)->create())
            ->get(route('procurements.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.budgetPlans', 1)
                ->where('options.budgetPlans.0.value', '2026.AO.01.001')
                ->where('options.budgetPlans.0.workDirectorId', $this->division->id)
                ->where('options.budgetPlans.0.description', 'Overhaul Mesin PLTD')
                ->where('options.budgetPlans.0.woNumber', 'WO-2026-0042')
                ->where('options.budgetPlans.0.prNumber', '1000012345')
                ->where('options.budgetPlans.0.subProgram', 'Overhaul Mesin PLTD')
                ->has('options.budgetPlans.0.programName')
                ->has('options.budgetPlans.0.aiValue'));

        $this->assertNotNull($plan->id);
    }
}
