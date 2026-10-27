<?php

namespace Tests\Feature\Procurements;

use App\Enums\ActivityType;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Riwayat Approval tab lists the decisions and hand-overs of a job, from
 * the proposal to the payment, as one table read top to bottom.
 */
class ApprovalHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_history_runs_from_the_proposal_to_the_payment(): void
    {
        $administrator = User::factory()->administrator()->create();
        $planner = User::factory()->planner()->create(['name' => 'Himatullah']);
        $procurement = Procurement::factory()->create();

        $steps = [
            [ActivityType::UsulanDiajukan, null, 'Usulan pekerjaan diajukan ke TL untuk disetujui.'],
            [ActivityType::UsulanDisetujuiTl, $administrator, 'Usulan disetujui TL.'],
            // Routine edits are not approvals.
            [ActivityType::Diperbarui, $administrator, 'Data diperbarui.'],
            [ActivityType::ChecklistDiperbarui, $planner, 'Checklist dicentang.'],
            [ActivityType::DokumenDigenerate, $planner, 'Dokumen dibuat.'],
            [ActivityType::PerencanaanDiajukan, $planner, 'Dokumen perencanaan diajukan untuk persetujuan.'],
            [ActivityType::PerencanaanDitolak, $administrator, 'Dokumen perencanaan dikembalikan untuk revisi. Catatan: lengkapi RAB.'],
            [ActivityType::PerencanaanDisetujui, $administrator, 'Dokumen perencanaan disetujui.'],
            [ActivityType::DiajukanKeKeuangan, $planner, 'Pelaksanaan selesai, pengadaan diserahkan ke Keuangan untuk proses pembayaran.'],
            [ActivityType::PembayaranSelesai, $administrator, 'Pembayaran ke mitra selesai, pengadaan ditutup.'],
        ];

        foreach ($steps as $index => [$type, $actor, $description]) {
            Carbon::setTestNow(now()->addMinutes($index + 1));
            app(ProcurementService::class)->recordActivity($procurement, $actor, $type, $description);
        }

        Carbon::setTestNow();

        $this->actingAs($administrator)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('activities')
                // Oldest first, routine edits left out.
                ->has('approvalHistory.rows', 7)
                ->where('approvalHistory.rows.0.stage', 'Usulan')
                ->where('approvalHistory.rows.0.step', 'Usulan Diajukan')
                ->where('approvalHistory.rows.0.outcome', 'diajukan')
                ->where('approvalHistory.rows.0.user', null)
                ->where('approvalHistory.rows.1.outcome', 'disetujui')
                ->where('approvalHistory.rows.2.stage', 'Perencanaan')
                ->where('approvalHistory.rows.2.user', 'Himatullah')
                ->where('approvalHistory.rows.2.user_role', 'PIC Perencana')
                ->where('approvalHistory.rows.3.outcome', 'dikembalikan')
                ->where('approvalHistory.rows.3.description', 'Dokumen perencanaan dikembalikan untuk revisi. Catatan: lengkapi RAB.')
                ->where('approvalHistory.rows.5.stage', 'Pelaksanaan')
                ->where('approvalHistory.rows.6.stage', 'Keuangan')
                ->where('approvalHistory.rows.6.outcome', 'selesai')
                // Still open: the last row says what it waits for, here
                // TL ICC appointing the planning PIC.
                ->where('approvalHistory.waiting', 'Menunggu penunjukan PIC Perencana oleh TL ICC'));
    }

    public function test_appointing_a_pic_belongs_to_the_stage_the_pic_works_on(): void
    {
        $administrator = User::factory()->administrator()->create();
        $planner = User::factory()->planner()->create();
        $executor = User::factory()->executor()->create();
        $procurement = Procurement::factory()->create(['planner_id' => null, 'executor_id' => null]);

        // Through the service, as the PIC screen does: the entries say which PIC.
        app(ProcurementService::class)->assignPic($procurement, $administrator, $planner->id, $executor->id);

        // An entry written before the PIC was recorded in the meta.
        Carbon::setTestNow(now()->addMinute());
        app(ProcurementService::class)->recordActivity($procurement, $administrator, ActivityType::PicDitunjuk, 'PIC Pelaksana diubah menjadi Ahmad Bukhari.');
        Carbon::setTestNow();

        $this->actingAs($administrator)
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->where('approvalHistory.rows', fn ($rows) => collect($rows)
                    ->where('step', 'PIC Ditunjuk')
                    ->map(fn ($row) => $row['stage'].' | '.$row['description'])
                    ->values()
                    ->all() === [
                        'Perencanaan | PIC Perencana diubah menjadi '.$planner->name.'.',
                        'Pelaksanaan | PIC Pelaksana diubah menjadi '.$executor->name.'.',
                        'Pelaksanaan | PIC Pelaksana diubah menjadi Ahmad Bukhari.',
                    ]));
    }

    public function test_a_closed_job_waits_for_nothing(): void
    {
        $procurement = Procurement::factory()->create(['completed_at' => now()]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('procurements.show', $procurement))
            ->assertInertia(fn (Assert $page) => $page
                ->has('approvalHistory.rows', 0)
                ->where('approvalHistory.waiting', null));
    }

    public function test_every_approval_step_belongs_to_a_stage(): void
    {
        $routine = [
            ActivityType::Diperbarui,
            ActivityType::StatusDiubah,
            ActivityType::ChecklistDiperbarui,
            ActivityType::DokumenDigenerate,
            ActivityType::DokumenDiedit,
            ActivityType::DokumenDitandatangani,
        ];

        foreach (ActivityType::cases() as $type) {
            in_array($type, $routine, true)
                ? $this->assertNull($type->approvalStage(), $type->value)
                : $this->assertContains($type->approvalStage(), ['Usulan', 'Perencanaan', 'Pelaksanaan', 'Penyerahan', 'Pembayaran', 'Keuangan'], $type->value);
        }
    }
}
