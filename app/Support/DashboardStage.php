<?php

namespace App\Support;

use App\Enums\PlanningApprovalState;
use App\Enums\ProposalState;
use App\Enums\StatusCategory;
use App\Models\Procurement;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where a job stands on its journey, worked out in SQL so a whole set of
 * jobs is counted in one query.
 *
 * The rules are those of Procurement::currentStage() (and the stage flow
 * of RoleDashboard), with a cancelled job counted on its own rather than
 * at the step it stopped.
 */
final class DashboardStage
{
    /**
     * The stages, in the order a job goes through them, cancelled last.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'usulan' => 'Usulan',
        'perencanaan' => 'Perencanaan',
        'pelaksanaan' => 'Pelaksanaan',
        'keuangan' => 'Penyerahan & Pembayaran',
        'selesai' => 'Selesai',
        'batal' => 'Dibatalkan',
    ];

    /**
     * The SQL expression naming a job's stage, with its bindings.
     *
     * @return array{0: literal-string, 1: list<string>}
     */
    public static function expression(): array
    {
        return ["case
            when exists (select 1 from progress_statuses as stage_status
                where stage_status.id = procurements.progress_status_id and stage_status.category = ?) then 'batal'
            when procurements.completed_at is not null then 'selesai'
            when procurements.finance_submitted_at is not null then 'keuangan'
            when procurements.planning_approval_state = ? then 'pelaksanaan'
            when procurements.proposal_state = ? then 'perencanaan'
            else 'usulan' end", [
            StatusCategory::Batal->value,
            PlanningApprovalState::Disetujui->value,
            ProposalState::Disetujui->value,
        ]];
    }

    /**
     * Keep the jobs at one stage.
     *
     * @param  Builder<Procurement>  $query
     * @return Builder<Procurement>
     */
    public static function where(Builder $query, string $stage): Builder
    {
        [$sql, $bindings] = self::expression();

        return $query->whereRaw("({$sql}) = ?", [...$bindings, $stage]);
    }

    /**
     * How many of the jobs are at each stage, every stage listed.
     *
     * @param  Builder<Procurement>  $query
     * @return array<string, int>
     */
    public static function counts(Builder $query): array
    {
        [$sql, $bindings] = self::expression();

        $counts = $query->toBase()
            ->selectRaw("{$sql} as stage, count(*) as total", $bindings)
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stages = [];

        foreach (array_keys(self::LABELS) as $key) {
            $stages[$key] = (int) ($counts[$key] ?? 0);
        }

        return $stages;
    }
}
