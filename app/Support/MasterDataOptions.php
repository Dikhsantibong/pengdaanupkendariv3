<?php

namespace App\Support;

use App\Enums\ProcurementStage;
use App\Enums\UserRole;
use App\Models\BudgetSource;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProgressStatus;
use App\Models\PrRoNumber;
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
            'workDirectors' => self::workDirectors(true),
            'targetUnits' => self::targetUnits(true),
            'procurementMethods' => self::procurementMethods(true),
            'budgetSources' => self::budgetSources(true),
            'prRoNumbers' => PrRoNumber::query()->active()->ordered()->get()
                ->map(fn (PrRoNumber $number): array => [
                    'value' => $number->id,
                    'label' => $number->number,
                    'description' => $number->description,
                ])->all(),
            'progressStatuses' => self::statuses(),
            'defaultProgressStatusId' => ProgressStatus::defaultStatus()?->id,
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
            'planners' => self::users(UserRole::PicPerencana),
            'executors' => self::users(UserRole::PicPelaksana),
            'documentTypes' => DocumentType::query()->active()->ordered()->get()
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
