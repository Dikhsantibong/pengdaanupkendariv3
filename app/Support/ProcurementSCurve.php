<?php

namespace App\Support;

use App\Enums\ProcurementStage;
use App\Models\Procurement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the "kurva S" (rencana vs realisasi) for a procurement stage.
 *
 * The plan curve spreads each procurement's checklist items evenly between the
 * moment its stage started and its target completion date. The actual curve
 * counts checklist rows by the moment they were ticked. Only procurements that
 * have a target completion date can be plotted, so the payload reports how many
 * were left out.
 */
class ProcurementSCurve
{
    /**
     * How many sample points the curve is drawn with.
     */
    private const POINTS = 18;

    /**
     * Build the curve payload for a stage.
     *
     * @param  Collection<int, Procurement>  $procurements  eager loaded with checklists
     * @return array<string, mixed>
     */
    public function build(Collection $procurements, ProcurementStage $stage): array
    {
        $tracks = $procurements
            ->map(fn (Procurement $procurement): ?array => $this->track($procurement, $stage))
            ->filter()
            ->values();

        $excluded = $procurements->count() - $tracks->count();

        if ($tracks->isEmpty()) {
            return [
                'points' => [],
                'totalItems' => 0,
                'includedCount' => 0,
                'excludedCount' => $excluded,
                'currentPlan' => 0.0,
                'currentActual' => 0.0,
                'deviation' => 0.0,
            ];
        }

        $now = CarbonImmutable::now();
        $start = $tracks->min(fn (array $track): int => $track['start']->getTimestamp());
        $end = max(
            $tracks->max(fn (array $track): int => $track['end']->getTimestamp()),
            $now->getTimestamp(),
        );

        $totalItems = (int) $tracks->sum(fn (array $track): int => $track['items']);
        $step = max(1, (int) round(($end - $start) / (self::POINTS - 1)));

        $points = [];

        for ($index = 0; $index < self::POINTS; $index++) {
            $timestamp = min($start + ($index * $step), $end);
            $moment = CarbonImmutable::createFromTimestamp($timestamp);

            $planned = 0.0;
            $actual = 0;

            foreach ($tracks as $track) {
                $planned += $track['items'] * $this->elapsedRatio($track, $timestamp);
                $actual += count(array_filter(
                    $track['completions'],
                    fn (int $completedAt): bool => $completedAt <= $timestamp,
                ));
            }

            $points[] = [
                'label' => $moment->translatedFormat('d M'),
                'date' => $moment->toDateString(),
                'rencana' => $this->percentage($planned, $totalItems),
                'realisasi' => $timestamp > $now->getTimestamp()
                    ? null
                    : $this->percentage($actual, $totalItems),
            ];
        }

        $currentPlan = 0.0;
        $currentActual = 0;

        foreach ($tracks as $track) {
            $currentPlan += $track['items'] * $this->elapsedRatio($track, $now->getTimestamp());
            $currentActual += count($track['completions']);
        }

        $plan = $this->percentage($currentPlan, $totalItems);
        $realised = $this->percentage($currentActual, $totalItems);

        return [
            'points' => $points,
            'totalItems' => $totalItems,
            'includedCount' => $tracks->count(),
            'excludedCount' => $excluded,
            'currentPlan' => $plan,
            'currentActual' => $realised,
            'deviation' => round($realised - $plan, 1),
        ];
    }

    /**
     * Reduce a procurement to the numbers the curve needs.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, items: int, completions: array<int, int>}|null
     */
    protected function track(Procurement $procurement, ProcurementStage $stage): ?array
    {
        if ($procurement->target_completion_date === null) {
            return null;
        }

        $rows = $procurement->checklists->where('stage', $stage);

        if ($rows->isEmpty()) {
            return null;
        }

        $start = $stage === ProcurementStage::Pelaksanaan
            ? ($procurement->planning_reviewed_at ?? $procurement->created_at)
            : $procurement->created_at;

        $start = CarbonImmutable::parse($start ?? $procurement->target_completion_date);
        $end = CarbonImmutable::parse($procurement->target_completion_date)->endOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->addDay();
        }

        return [
            'start' => $start,
            'end' => $end,
            'items' => $rows->count(),
            'completions' => $rows
                ->where('is_completed', true)
                ->filter(fn ($row): bool => $row->completed_at !== null)
                ->map(fn ($row): int => $row->completed_at->getTimestamp())
                ->values()
                ->all(),
        ];
    }

    /**
     * How far a track should have progressed at a given moment.
     *
     * @param  array{start: CarbonImmutable, end: CarbonImmutable, items: int, completions: array<int, int>}  $track
     */
    protected function elapsedRatio(array $track, int $timestamp): float
    {
        $start = $track['start']->getTimestamp();
        $end = $track['end']->getTimestamp();

        if ($timestamp <= $start) {
            return 0.0;
        }

        if ($timestamp >= $end) {
            return 1.0;
        }

        return ($timestamp - $start) / ($end - $start);
    }

    /**
     * Express a value as a percentage of the total, rounded for display.
     */
    protected function percentage(float|int $value, int $total): float
    {
        return $total > 0 ? round($value / $total * 100, 1) : 0.0;
    }
}
