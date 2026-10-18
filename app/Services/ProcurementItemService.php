<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\Procurement;
use App\Models\ProcurementContractItem;
use App\Models\ProcurementItemQuotation;
use App\Models\ProcurementPartnerCandidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Rincian Item of a procurement: kept as the form lists it, then every
 * item table of its documents (and its value after negotiation) follows.
 */
class ProcurementItemService
{
    public function __construct(
        protected DocumentGenerator $documents,
        protected ProcurementService $procurements,
    ) {}

    /**
     * The items as the document editor's Rincian Item tab lists them.
     *
     * @return array<int, array{id: int, group_name: string|null, description: string, part_number: string|null, volume: float, unit: string, price_before: float|null, unit_price: float|null, quotes: array<int, float>}>
     */
    public function payload(Procurement $procurement): array
    {
        return $procurement->contractItems()->with('quotations')->get()
            ->map(fn (ProcurementContractItem $item): array => [
                'id' => $item->id,
                'group_name' => $item->group_name,
                'description' => $item->description,
                'part_number' => $item->part_number,
                'volume' => (float) $item->volume,
                'unit' => $item->unit,
                'price_before' => $item->price_before === null ? null : (float) $item->price_before,
                'unit_price' => $item->unit_price === null ? null : (float) $item->unit_price,
                'quotes' => $item->quotations
                    ->mapWithKeys(fn (ProcurementItemQuotation $quote): array => [$quote->procurement_partner_candidate_id => (float) $quote->unit_price])
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The calon mitra whose prices the Evaluasi Harga compares, one column
     * each in the Rincian Item.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function partners(Procurement $procurement): array
    {
        return $procurement->partnerCandidates()->get()
            ->map(fn (ProcurementPartnerCandidate $candidate): array => ['id' => $candidate->id, 'name' => $candidate->name])
            ->all();
    }

    /**
     * The rows a BA Negosiasi table already holds, offered as the starting
     * Rincian Item of a job that has none yet (unsaved: no ids).
     *
     * @return array<int, array{id: null, group_name: string|null, description: string, part_number: null, volume: float, unit: string, price_before: float|null, unit_price: float|null, quotes: array<int, float>}>
     */
    public function draftFromNegotiation(string $body): array
    {
        return array_map(function (array $line): array {
            $volume = (float) str_replace(',', '.', (string) preg_replace('/[^0-9.,]/', '', $line['volume']));

            return [
                'id' => null,
                'group_name' => $line['group'],
                'description' => $line['name'],
                'part_number' => null,
                'volume' => $volume > 0 ? $volume : 1.0,
                'unit' => $line['satuan'],
                'price_before' => $line['price_before'] > 0 ? $line['price_before'] : null,
                'unit_price' => $line['price_after'] > 0 ? $line['price_after'] : null,
                'quotes' => [],
            ];
        }, $this->documents->parseNegotiationItems($body));
    }

    /**
     * Keep the rows as listed: a group row names the items below it until
     * the next one, and an item keeps its id so a Delivery Order line still
     * points at it.
     *
     * @param  array<int, mixed>  $rows  The validated rows: kind (group or item),
     *                                   description, and for an item its part number, volume, unit and
     *                                   unit prices before and after negotiation, and what each calon
     *                                   mitra quoted.
     */
    public function save(Procurement $procurement, array $rows, User $editor): void
    {
        DB::transaction(function () use ($procurement, $rows): void {
            $kept = [];
            $candidates = $procurement->partnerCandidates()->get()
                ->map(fn (ProcurementPartnerCandidate $candidate): int => $candidate->id)
                ->all();

            foreach ($this->flatten($rows, $candidates) as [$id, $values, $quotes]) {
                $existing = $id !== null
                    ? $procurement->contractItems()->whereKey($id)->first()
                    : null;

                if ($existing !== null) {
                    $existing->update($values);
                } else {
                    $existing = $procurement->contractItems()->create($values);
                }

                $kept[] = $existing->id;

                if ($quotes !== null) {
                    $this->saveQuotes($existing, $quotes);
                }
            }

            $procurement->contractItems()->whereNotIn('id', $kept)->delete();
        });

        $this->documents->syncItemDocuments($procurement->refresh());

        $this->procurements->recordActivity(
            $procurement,
            $editor,
            ActivityType::Diperbarui,
            'Rincian item diperbarui ('.$procurement->contractItems()->count().' item).',
        );
    }

    /**
     * The rows as unsaved items, for the preview of the table they make.
     *
     * @param  array<int, mixed>  $rows
     * @return Collection<int, ProcurementContractItem>
     */
    public function unsavedItems(array $rows): Collection
    {
        return new Collection(array_map(function (array $line): ProcurementContractItem {
            $item = new ProcurementContractItem($line[1]);

            return $item->setRelation('quotations', new Collection(array_map(
                fn (int $candidate, string $price): ProcurementItemQuotation => new ProcurementItemQuotation(['procurement_partner_candidate_id' => $candidate, 'unit_price' => $price]),
                array_keys($line[2] ?? []),
                $line[2] ?? [],
            )));
        }, $this->flatten($rows)));
    }

    /**
     * Keep exactly the quotes given for an item; a mitra left blank has none.
     *
     * @param  array<int, string>  $quotes  unit price by calon mitra id
     */
    protected function saveQuotes(ProcurementContractItem $item, array $quotes): void
    {
        $item->quotations()->whereNotIn('procurement_partner_candidate_id', array_keys($quotes))->delete();

        foreach ($quotes as $candidate => $price) {
            $item->quotations()->updateOrCreate(['procurement_partner_candidate_id' => $candidate], ['unit_price' => $price]);
        }
    }

    /**
     * The item rows with the group they sit under, in order: a group row
     * names the items below it until the next one, and is no item itself.
     * An item's quotes are kept by calon mitra (only the given ones, when
     * listed); with no HPE price of its own it takes the lowest quote.
     *
     * @param  array<int, mixed>  $rows
     * @param  array<int, int>|null  $candidates
     * @return list<array{0: int|null, 1: array{sort_order: int, group_name: string|null, description: string, part_number: string|null, volume: string|int, unit: string, price_before: string|null, unit_price: string|null}, 2: array<int, string>|null}>
     */
    protected function flatten(array $rows, ?array $candidates = null): array
    {
        $items = [];
        $group = null;
        $order = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $text = fn (string $key): ?string => filled($row[$key] ?? null) ? trim((string) $row[$key]) : null;
            $description = (string) $text('description');

            if (($row['kind'] ?? null) === 'group') {
                $group = $description === '' ? null : $description;

                continue;
            }

            // Rows sent without quotes (any editor but the Evaluasi Harga's,
            // say) leave the quotes as they are.
            $quotes = array_key_exists('quotes', $row) ? [] : null;

            foreach ((array) ($row['quotes'] ?? []) as $candidate => $price) {
                if (filled($price) && is_numeric($price) && ($candidates === null || in_array((int) $candidate, $candidates, true))) {
                    $quotes[(int) $candidate] = trim((string) $price);
                }
            }

            $lowest = $quotes === null || $quotes === [] ? null : (string) min(array_map('floatval', $quotes));

            $items[] = [
                filled($row['id'] ?? null) ? (int) $row['id'] : null,
                [
                    'sort_order' => ++$order,
                    'group_name' => $group,
                    'description' => $description,
                    'part_number' => $text('part_number'),
                    'volume' => $text('volume') ?? 1,
                    'unit' => $text('unit') ?? 'Lot',
                    'price_before' => $text('price_before') ?? $lowest,
                    'unit_price' => $text('unit_price'),
                ],
                $quotes,
            ];
        }

        return $items;
    }
}
