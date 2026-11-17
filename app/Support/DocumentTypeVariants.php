<?php

namespace App\Support;

use App\Models\ContractNumberFormat;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One document type, one template.
 *
 * The RKS, the SPK and the SPK's Pasal used to carry several templates, each
 * for a procurement method, contract format or goods/services category. Each
 * of those is its own document type now, a variant of its family (the code
 * the documents always had, which the rest of the application keeps reading),
 * scoped like the template it carries.
 *
 * Of a family, a procurement is asked for the variant that fits it best, in
 * the order the templates were picked by: the contract format weighs most,
 * then the category, then the procurement method.
 */
class DocumentTypeVariants
{
    /**
     * Each family's variants, the first being the family's own document type.
     * A name of null keeps the type's current name; the title is what the
     * generated document is called.
     *
     * @var array<string, array<string, array{name: string|null, title: string|null, procurement_method: string|null, contract_format: string|null, work_category: string|null}>>
     */
    public const FAMILIES = [
        'rks' => [
            'rks' => ['name' => null, 'title' => null, 'procurement_method' => null, 'contract_format' => null, 'work_category' => null],
            'rks-spk' => ['name' => 'RKS (Rencana Kerja dan Syarat) - Metode SPK', 'title' => 'RKS (Rencana Kerja dan Syarat)', 'procurement_method' => 'spk', 'contract_format' => null, 'work_category' => null],
            'rks-tender' => ['name' => 'RKS (Rencana Kerja dan Syarat) - Metode Tender', 'title' => 'RKS (Rencana Kerja dan Syarat)', 'procurement_method' => 'tender', 'contract_format' => null, 'work_category' => null],
            'rks-pj-jasa' => ['name' => 'RKS (Rencana Kerja dan Syarat) - PJ Jasa', 'title' => 'RKS (Rencana Kerja dan Syarat)', 'procurement_method' => null, 'contract_format' => 'PJ', 'work_category' => 'jasa'],
        ],
        'spk' => [
            'spk' => ['name' => null, 'title' => null, 'procurement_method' => null, 'contract_format' => 'SPK', 'work_category' => null],
            'spk-pj' => ['name' => 'Surat Perjanjian (PJ)', 'title' => 'Surat Perjanjian (PJ)', 'procurement_method' => null, 'contract_format' => 'PJ', 'work_category' => null],
        ],
        'spk-pasal' => [
            'spk-pasal' => ['name' => 'SPK - Syarat & Ketentuan (Pasal) Jasa', 'title' => 'SPK - Syarat & Ketentuan (Pasal)', 'procurement_method' => null, 'contract_format' => 'SPK', 'work_category' => 'jasa'],
            'spk-pasal-barang' => ['name' => 'SPK - Syarat & Ketentuan (Pasal) Barang', 'title' => 'SPK - Syarat & Ketentuan (Pasal)', 'procurement_method' => null, 'contract_format' => 'SPK', 'work_category' => 'barang'],
        ],
    ];

    /**
     * What a generated document of this type is called, when its variant
     * says (a variant is named apart in the master data only).
     */
    public static function titleOf(DocumentType $type): ?string
    {
        return self::FAMILIES[$type->familyCode()][$type->code]['title'] ?? null;
    }

    /**
     * Of each family, the variant that fits the procurement best; types
     * outside a family pass as they are. Types that do not apply are
     * expected to be filtered out already.
     *
     * @param  EloquentCollection<int, DocumentType>  $types
     * @return EloquentCollection<int, DocumentType>
     */
    public static function bestOf(EloquentCollection $types, ?Procurement $procurement): EloquentCollection
    {
        $families = $types->groupBy(fn (DocumentType $type): string => $type->familyCode());

        // As the templates used to: a variant without its template gives way
        // to the next fitting one (the general RKS when the method's own is
        // missing). Asked only where a family has more than one candidate.
        $contested = $families->filter(fn (Collection $group): bool => $group->count() > 1)->flatten(1);
        $withTemplate = $contested->isEmpty()
            ? []
            : DocumentTemplate::query()->active()->whereIn('document_type_id', $contested->pluck('id'))->distinct()->pluck('document_type_id')->map(fn (mixed $id): int => (int) $id)->all();

        $kept = [];

        foreach ($families as $family => $group) {
            $usable = $group->count() > 1
                ? $group->filter(fn (DocumentType $type): bool => $type->upload_only || in_array($type->id, $withTemplate, true))
                : $group;

            foreach ($usable->isEmpty() ? $group : $usable as $type) {
                $current = $kept[$family] ?? null;

                if ($current === null || self::specificity($type, $procurement) > self::specificity($current, $procurement)) {
                    $kept[$family] = $type;
                }
            }
        }

        // In the order the step lists them.
        return $types->filter(fn (DocumentType $type): bool => ($kept[$type->familyCode()] ?? null) === $type)->values();
    }

    /**
     * The variant of a type's family a procurement is asked for, or the type
     * itself when it has no better variant.
     */
    public static function variantFor(DocumentType $type, ?Procurement $procurement): DocumentType
    {
        if ($procurement === null || ! array_key_exists($type->familyCode(), self::FAMILIES)) {
            return $type;
        }

        $variants = DocumentType::query()
            ->inFamily($type->familyCode())
            ->where('is_active', true)
            ->with('selectionMethods')
            ->orderBy('id')
            ->get()
            ->filter(fn (DocumentType $variant): bool => $variant->appliesTo($procurement));

        return self::bestOf($variants, $procurement)->first() ?? $type;
    }

    /**
     * How closely a type is scoped to the procurement, in the order the
     * templates were picked by: format, then category, then method.
     */
    public static function specificity(DocumentType $type, ?Procurement $procurement): int
    {
        return ($type->contract_number_format_id !== null ? 4 : 0)
            + ($type->work_category !== null ? 2 : 0)
            + ($type->procurement_method_id !== null ? 1 : 0);
    }

    /**
     * Give every family its variants, each with its one template, the steps
     * of the family and its documents. Safe to run again: what is in place
     * is left as it is.
     */
    public static function split(): void
    {
        // Every type names its family; one seeded quietly is its own.
        DB::table('document_types')->whereNull('family')->update(['family' => DB::raw('code')]);

        $methods = ProcurementMethod::query()->withTrashed()->pluck('id', 'code');
        $formats = ContractNumberFormat::query()->withTrashed()->pluck('id', 'code');

        foreach (self::FAMILIES as $family => $variants) {
            $root = DocumentType::query()->withTrashed()->where('code', $family)->first();

            if ($root === null) {
                continue;
            }

            $types = [];

            foreach ($variants as $code => $variant) {
                $scope = [
                    'family' => $family,
                    'procurement_method_id' => $variant['procurement_method'] === null ? null : ($methods[$variant['procurement_method']] ?? null),
                    'contract_number_format_id' => $variant['contract_format'] === null ? null : ($formats[$variant['contract_format']] ?? null),
                    'work_category' => $variant['work_category'],
                ];

                $type = DocumentType::query()->withTrashed()->where('code', $code)->first();

                if ($type === null) {
                    $type = DocumentType::query()->create([
                        'code' => $code,
                        'name' => $variant['name'] ?? $root->name,
                        'stage' => $root->stage,
                        'upload_only' => $root->upload_only,
                        'description' => $root->description,
                        'sort_order' => $root->sort_order,
                        'is_active' => $root->is_active,
                        ...$scope,
                    ]);

                    // Asked for under the same selection methods as its family.
                    $type->selectionMethods()->sync($root->selectionMethods()->pluck('selection_methods.id')->all());
                } else {
                    $type->forceFill([
                        ...$scope,
                        'name' => $variant['name'] ?? $type->name,
                    ])->save();
                }

                $types[$code] = $type;
            }

            self::linkSteps($root, $types);
            self::moveTemplates($family, $types);
            self::moveDocuments($family, $types);
        }
    }

    /**
     * Every step asking for the family asks for each variant, in its place;
     * the procurement then gets the one that fits it.
     *
     * @param  array<string, DocumentType>  $types
     */
    protected static function linkSteps(DocumentType $root, array $types): void
    {
        $links = DB::table('checklist_item_document_type')->where('document_type_id', $root->id)->get();

        foreach ($links as $link) {
            foreach ($types as $type) {
                DB::table('checklist_item_document_type')->insertOrIgnore([
                    'checklist_item_id' => $link->checklist_item_id,
                    'document_type_id' => $type->id,
                    'sort_order' => $link->sort_order,
                    'is_alternative' => $link->is_alternative,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Each template goes to the variant its own scope was written for: the
     * one every value it sets agrees with, the least specific one for a
     * template written for every procurement.
     *
     * @param  array<string, DocumentType>  $types
     */
    protected static function moveTemplates(string $family, array $types): void
    {
        $ids = array_map(fn (DocumentType $type): int => $type->id, $types);

        DocumentTemplate::query()
            ->withTrashed()
            ->whereIn('document_type_id', $ids)
            ->get()
            ->each(function (DocumentTemplate $template) use ($types): void {
                $fitting = collect($types)
                    ->filter(fn (DocumentType $type): bool => ($template->procurement_method_id === null || $template->procurement_method_id === $type->procurement_method_id)
                        && ($template->contract_number_format_id === null || $template->contract_number_format_id === $type->contract_number_format_id)
                        && ($template->work_category === null || $template->work_category->value === $type->work_category?->value))
                    ->sortBy(fn (DocumentType $type): int => self::specificity($type, null))
                    ->first();

                if ($fitting !== null && $template->document_type_id !== $fitting->id) {
                    $template->forceFill(['document_type_id' => $fitting->id])->saveQuietly();
                }
            });
    }

    /**
     * Each document already drawn goes to the variant its procurement is
     * asked for now, so its step still finds it.
     *
     * @param  array<string, DocumentType>  $types
     */
    protected static function moveDocuments(string $family, array $types): void
    {
        $ids = array_map(fn (DocumentType $type): int => $type->id, $types);
        $variants = (new EloquentCollection(array_values($types)))->load('selectionMethods');

        DB::table('procurement_documents')
            ->whereIn('document_type_id', $ids)
            ->orderBy('id')
            ->get(['id', 'procurement_id', 'document_type_id'])
            ->groupBy('procurement_id')
            ->each(function (Collection $documents, int|string $procurementId) use ($variants): void {
                $procurement = Procurement::query()->withTrashed()->with('selectionMethod')->find($procurementId);

                if ($procurement === null) {
                    return;
                }

                $applying = $variants->filter(fn (DocumentType $type): bool => $type->appliesTo($procurement));
                $best = self::bestOf($applying, $procurement)->first();

                if ($best !== null) {
                    DB::table('procurement_documents')->whereIn('id', $documents->pluck('id'))->update(['document_type_id' => $best->id]);
                }
            });
    }
}
