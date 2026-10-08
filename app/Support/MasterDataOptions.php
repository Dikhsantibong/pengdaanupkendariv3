<?php

namespace App\Support;

use App\Enums\PlanningApprovalState;
use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\BudgetSource;
use App\Models\ContractNumberFormat;
use App\Models\ContractType;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\TargetUnit;
use App\Models\User;
use App\Models\WorkDirector;

/**
 * Builds the dropdown option payloads shared across the procurement screens.
 */
class MasterDataOptions
{
    /**
     * Options required by the initial procurement input form.
     *
     * @return array<string, mixed>
     */
    public static function forProcurementForm(): array
    {
        return [
            'contractNumberFormats' => ContractNumberFormat::query()->active()->ordered()->get()
                ->map(fn (ContractNumberFormat $format): array => [
                    'value' => $format->id,
                    'label' => $format->code,
                    'description' => $format->name,
                ])->all(),
            'workDirectors' => self::workDirectors(true),
            'targetUnits' => self::targetUnits(true),
            'procurementMethods' => self::procurementMethods(true),
            'budgetSources' => self::budgetSources(true),
            'progressStatuses' => self::statuses(),
            'defaultProgressStatusId' => ProgressStatus::defaultStatus()?->id,
            'planners' => self::planners(),
        ];
    }

    /**
     * Options required by the procurement list filters.
     *
     * @return array<string, mixed>
     */
    public static function forFilters(): array
    {
        return [
            'workDirectors' => self::workDirectors(false),
            'targetUnits' => self::targetUnits(false),
            'procurementMethods' => self::procurementMethods(false),
            'budgetSources' => self::budgetSources(false),
            'progressStatuses' => self::statuses(),
        ];
    }

    /**
     * Options required by the procurement detail screen.
     *
     * @return array<string, mixed>
     */
    public static function forProcurementDetail(Procurement $procurement): array
    {
        // A document is generatable when a template exists for this
        // procurement's method, or a general fallback template exists. All of
        // them are resolved in one query rather than one query per type.
        $resolvable = DocumentTemplate::documentTypeIdsResolvableFor(
            $procurement->procurement_method_id,
        );

        return [
            'progressStatuses' => self::statuses(),
            'planners' => self::planners(),
            'executors' => self::executors(),
            'contractTypes' => self::contractTypes(),
            // Upload-only documents are filed on their checklist step, never
            // generated, so they are not offered here.
            'documentTypes' => DocumentType::query()->active()->where('upload_only', false)->ordered()->get()
                ->map(fn (DocumentType $type): array => [
                    'value' => $type->id,
                    'label' => $type->name,
                    'stage' => $type->stage->value,
                    'hasTemplate' => in_array($type->id, $resolvable, true),
                ])->all(),
            'stages' => ProcurementStage::options(),
        ];
    }

    /**
     * Get the selectable planners (PIC Perencana or TL ICC).
     *
     * @return array<int, array{value: int, label: string, workload: array{planning: int, execution: int, active: int}}>
     */
    public static function planners(): array
    {
        return self::picOptions([
            UserRole::PicPerencana,
            UserRole::TeamLeaderIcc,
        ]);
    }

    /**
     * Get the selectable executors (PIC Pelaksana or TL Pengadaan).
     *
     * @return array<int, array{value: int, label: string, workload: array{planning: int, execution: int, active: int}}>
     */
    public static function executors(): array
    {
        return self::picOptions([
            UserRole::PicPelaksana,
            UserRole::TeamLeaderPengadaan,
        ]);
    }

    /**
     * Get the users of the given roles together with their current workload.
     *
     * The workload lets whoever assigns a PIC see how busy each candidate is:
     * planning still under way as PIC Perencana, execution under way as PIC
     * Pelaksana, and every unfinished procurement they hold in either role.
     * All counts come from subqueries, so this stays a single query.
     *
     * @param  array<int, UserRole>  $roles
     * @return array<int, array{value: int, label: string, workload: array{planning: int, execution: int, active: int}}>
     */
    protected static function picOptions(array $roles): array
    {
        return User::query()
            ->active()
            ->withRole($roles)
            ->withCount([
                'plannedProcurements as planning_workload' => fn ($query) => $query
                    ->inProgress()
                    ->where('planning_approval_state', '!=', PlanningApprovalState::Disetujui->value),
                'executedProcurements as execution_workload' => fn ($query) => $query
                    ->inProgress()
                    ->where('planning_approval_state', PlanningApprovalState::Disetujui->value),
            ])
            ->addSelect(['active_workload' => Procurement::query()
                ->inProgress()
                ->selectRaw('count(*)')
                ->where(fn ($query) => $query
                    ->whereColumn('procurements.planner_id', 'users.id')
                    ->orWhereColumn('procurements.executor_id', 'users.id')),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'value' => $user->id,
                'label' => $user->name,
                'workload' => [
                    'planning' => (int) $user->getAttribute('planning_workload'),
                    'execution' => (int) $user->getAttribute('execution_workload'),
                    'active' => (int) $user->getAttribute('active_workload'),
                ],
            ])
            ->all();
    }

    /**
     * Get the selectable users for a given PIC role.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function users(UserRole $role): array
    {
        return User::query()
            ->active()
            ->withRole([$role])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])
            ->all();
    }

    /**
     * Get the selectable progress statuses.
     *
     * @return array<int, array{value: int, label: string, category: string}>
     */
    public static function statuses(): array
    {
        return ProgressStatus::query()->active()->ordered()->get()
            ->map(fn (ProgressStatus $status): array => [
                'value' => $status->id,
                'label' => $status->name,
                'category' => $status->category->value,
            ])->all();
    }

    /**
     * Get the selectable direksi pekerjaan.
     *
     * @return array<int, array{value: int, label: string}>
     */
    protected static function workDirectors(bool $onlyActive): array
    {
        return WorkDirector::query()
            ->when($onlyActive, fn ($query) => $query->active())
            ->ordered()
            ->get()
            ->map(fn (WorkDirector $record): array => [
                'value' => $record->id,
                'label' => $record->name,
            ])->all();
    }

    /**
     * Get the selectable unit tujuan.
     *
     * @return array<int, array{value: int, label: string}>
     */
    protected static function targetUnits(bool $onlyActive): array
    {
        return TargetUnit::query()
            ->when($onlyActive, fn ($query) => $query->active())
            ->ordered()
            ->get()
            ->map(fn (TargetUnit $record): array => [
                'value' => $record->id,
                'label' => $record->name,
            ])->all();
    }

    /**
     * Get the selectable metode pengadaan.
     *
     * @return array<int, array{value: int, label: string, description: string|null}>
     */
    protected static function procurementMethods(bool $onlyActive): array
    {
        return ProcurementMethod::query()
            ->when($onlyActive, fn ($query) => $query->active())
            ->ordered()
            ->get()
            ->map(fn (ProcurementMethod $record): array => [
                'value' => $record->id,
                'label' => $record->name,
                'description' => $record->description,
            ])->all();
    }

    /**
     * Get the selectable jenis kontrak.
     *
     * @return array<int, array{value: int, label: string, description: string|null}>
     */
    public static function contractTypes(): array
    {
        return ContractType::query()->active()->ordered()->get()
            ->map(fn (ContractType $record): array => [
                'value' => $record->id,
                'label' => $record->name,
                'description' => $record->description,
            ])->all();
    }

    /**
     * Get the selectable sumber anggaran.
     *
     * @return array<int, array{value: int, label: string, description: string|null}>
     */
    protected static function budgetSources(bool $onlyActive): array
    {
        return BudgetSource::query()
            ->when($onlyActive, fn ($query) => $query->active())
            ->ordered()
            ->get()
            ->map(fn (BudgetSource $record): array => [
                'value' => $record->id,
                'label' => $record->name,
                'description' => $record->description,
            ])->all();
    }
}
