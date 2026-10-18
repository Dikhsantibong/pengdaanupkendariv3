<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\DeliveryOrderState;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementContractItem;
use App\Models\ProcurementDeliveryOrder;
use App\Models\User;
use App\Notifications\ProcurementMilestone;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Delivery Orders (DO) under a KHS contract.
 *
 * The proposer requests one (Nota Dinas usulan, its date and scan, and the
 * lines wanted from the contract's price list); their TL and the division's
 * Asman agree; Rendan (the planning side) adds the Nota Dinas DO and hands
 * it to Laksdan (the execution side), who dates it and issues the DO
 * document, numbered in sequence across every KHS contract.
 */
class DeliveryOrderService
{
    /**
     * Where the request scans are kept on the private disk.
     */
    private const DIRECTORY = 'delivery-orders';

    public function __construct(
        protected ProcurementService $procurements,
        protected DocumentGenerator $documents,
        protected AmendmentService $amendments,
    ) {}

    /**
     * Whether DOs can be called off under the procurement's contract: a KHS
     * SPK or PJ whose contract document exists.
     */
    public function available(Procurement $procurement): bool
    {
        return $procurement->usesDeliveryOrders() && $this->amendments->hasContract($procurement);
    }

    /**
     * Replace the contract's price list with the given lines, keeping the
     * lines that stay (so DO lines still point at them).
     *
     * @param  array<int, array{id?: int|null, group_name?: string|null, description: string, part_number?: string|null, unit: string, unit_price: int|float|string}>  $lines
     */
    public function saveContractItems(Procurement $procurement, array $lines): void
    {
        DB::transaction(function () use ($procurement, $lines): void {
            $kept = [];

            foreach (array_values($lines) as $index => $line) {
                $values = [
                    'sort_order' => $index + 1,
                    'group_name' => filled($line['group_name'] ?? null) ? trim((string) $line['group_name']) : null,
                    'description' => trim($line['description']),
                    'part_number' => filled($line['part_number'] ?? null) ? trim((string) $line['part_number']) : null,
                    'unit' => trim($line['unit']),
                    'unit_price' => $line['unit_price'],
                ];

                $existing = isset($line['id'])
                    ? $procurement->contractItems()->whereKey((int) $line['id'])->first()
                    : null;

                if ($existing !== null) {
                    $existing->update($values);
                    $kept[] = $existing->id;
                } else {
                    $kept[] = $procurement->contractItems()->create($values)->id;
                }
            }

            $procurement->contractItems()->whereNotIn('id', $kept)->delete();
        });
    }

    /**
     * Start a DO request, its description and contract date taken from the
     * procurement.
     */
    public function start(Procurement $procurement, User $author): ProcurementDeliveryOrder
    {
        return DB::transaction(function () use ($procurement, $author): ProcurementDeliveryOrder {
            Procurement::query()->whereKey($procurement->id)->lockForUpdate()->first();

            $order = new ProcurementDeliveryOrder([
                'contract_date' => $this->contractDate($procurement)?->toDateString(),
                'work_description' => Str::limit($procurement->name, 500, ''),
                'vendor_code' => $procurement->partner_vendor_code,
            ]);
            $order->procurement_id = $procurement->id;
            $order->sequence = (int) $procurement->deliveryOrders()->max('sequence') + 1;
            $order->created_by = $author->id;
            $order->save();

            return $order;
        });
    }

    /**
     * Save the header fields of a step and, when given, the lines.
     *
     * Each line is drawn from the contract's price list: its wording and
     * unit price are copied, only the quantity is the DO's own.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{contract_item_id: int, quantity: int|float|string}>|null  $lines
     */
    public function save(ProcurementDeliveryOrder $order, array $attributes, ?array $lines = null): ProcurementDeliveryOrder
    {
        return DB::transaction(function () use ($order, $attributes, $lines): ProcurementDeliveryOrder {
            $order->fill($attributes)->save();

            if ($lines !== null) {
                /** @var Collection<int, ProcurementContractItem> $catalog */
                $catalog = $order->procurement->contractItems()->get()->keyBy('id');

                $order->items()->delete();

                foreach (array_values($lines) as $index => $line) {
                    $item = $catalog->get((int) $line['contract_item_id']);

                    if ($item === null) {
                        continue;
                    }

                    $order->items()->create([
                        'procurement_contract_item_id' => $item->id,
                        'sort_order' => $index + 1,
                        'group_name' => $item->group_name,
                        'description' => $item->description,
                        'part_number' => $item->part_number,
                        'quantity' => $line['quantity'],
                        'unit' => $item->unit,
                        'unit_price' => $item->unit_price,
                    ]);
                }
            }

            return $order->unsetRelation('items');
        });
    }

    /**
     * File the scan of the Nota Dinas usulan, replacing the previous one.
     */
    public function attachRequest(ProcurementDeliveryOrder $order, UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        // The stored name never reuses what the browser sent.
        $path = $file->storeAs(
            self::DIRECTORY.'/'.$order->id,
            'nota-dinas-usulan-'.Str::random(12).($extension === '' ? '' : '.'.$extension),
            'local',
        );

        $previous = $order->request_file_path;

        $order->forceFill([
            'request_file_path' => $path === false ? null : $path,
            'request_file_name' => Str::limit($file->getClientOriginalName(), 240, ''),
            'request_file_mime' => $file->getClientMimeType(),
            'request_file_size' => $file->getSize() ?: null,
        ])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }
    }

    /**
     * Send the request to the TL (or straight to the Asman), first time or
     * after it was returned.
     */
    public function submit(ProcurementDeliveryOrder $order, User $author): ProcurementDeliveryOrder
    {
        $returned = $order->state === DeliveryOrderState::Dikembalikan;
        $atTeamLeader = $this->teamLeaderOf($order) !== null;

        $order->forceFill([
            'state' => $atTeamLeader ? DeliveryOrderState::MenungguPersetujuanTl : DeliveryOrderState::MenungguPersetujuanAsman,
            'submitted_at' => now(),
            'tl_reviewed_at' => null,
            'tl_reviewed_by' => null,
            'director_reviewed_at' => null,
            'director_reviewed_by' => null,
            'revision' => $returned ? $order->revision + 1 : $order->revision,
        ])->save();

        $this->log($order, $author, ActivityType::DoDiajukan, "Permohonan DO ke-{$order->sequence} diajukan"
            .($returned ? " ulang (revisi ke-{$order->revision})" : '')
            .' ke '.($atTeamLeader ? 'TL' : 'Direksi Pekerjaan (Asman)').' untuk disetujui.');

        $this->notify($order, $this->nextRecipients($order), 'Permohonan DO menunggu persetujuan');

        return $order;
    }

    /**
     * The TL or Asman agrees (the Asman's approval hands it to Rendan), or
     * returns it to the proposer.
     */
    public function decide(ProcurementDeliveryOrder $order, User $reviewer, bool $approved, ?string $note = null): ProcurementDeliveryOrder
    {
        $atTeamLeader = $order->state === DeliveryOrderState::MenungguPersetujuanTl;
        $who = $atTeamLeader ? 'TL' : 'Asman';

        $order->forceFill([
            ...($atTeamLeader
                ? ['tl_reviewed_at' => now(), 'tl_reviewed_by' => $reviewer->id]
                : ['director_reviewed_at' => now(), 'director_reviewed_by' => $reviewer->id]),
            'state' => match (true) {
                ! $approved => DeliveryOrderState::Dikembalikan,
                $atTeamLeader => DeliveryOrderState::MenungguPersetujuanAsman,
                default => DeliveryOrderState::DiRendan,
            },
            'review_note' => $note,
        ])->save();

        $this->log($order, $reviewer, match (true) {
            ! $approved => ActivityType::DoDikembalikan,
            $atTeamLeader => ActivityType::DoDisetujuiTl,
            default => ActivityType::DoDisetujuiAsman,
        }, Str::limit($approved
            ? "Permohonan DO ke-{$order->sequence} disetujui {$who}".($atTeamLeader ? '.' : ' dan diteruskan ke Rendan.')
            : "Permohonan DO ke-{$order->sequence} dikembalikan oleh {$who}.".($note !== null ? " Catatan: {$note}" : ''), 250));

        if ($approved) {
            $this->notify($order, $this->nextRecipients($order), $atTeamLeader ? 'Permohonan DO menunggu persetujuan' : 'Isi Nota Dinas DO');
        } else {
            $this->notify($order, [$order->creator], 'Permohonan DO dikembalikan', $note);
        }

        return $order;
    }

    /**
     * Rendan hands the DO, with its Nota Dinas DO, to Laksdan. The DO
     * number is drawn then, the next on the DO format; Laksdan may correct it.
     */
    public function forward(ProcurementDeliveryOrder $order, User $actor): ProcurementDeliveryOrder
    {
        DB::transaction(function () use ($order, $actor): void {
            $format = ContractNumberFormat::forDeliveryOrders();

            if (blank($order->number) && $format !== null) {
                // One at a time, so two DOs never draw the same number.
                ContractNumberFormat::query()->whereKey($format->id)->lockForUpdate()->first();
                $order->number = $this->nextNumber($format);
                $order->contract_number_format_id = $format->id;
            }

            $order->forceFill([
                'state' => DeliveryOrderState::DiLaksdan,
                'order_date' => $order->order_date ?? now()->toDateString(),
                'forwarded_at' => now(),
                'forwarded_by' => $actor->id,
                'review_note' => null,
            ])->save();
        });

        $this->log($order, $actor, ActivityType::DoDiteruskanLaksdan, "Nota Dinas DO {$order->do_memo_number} dibuat dan DO ke-{$order->sequence} diteruskan ke Laksdan.");
        $this->notify($order, $this->nextRecipients($order), 'Terbitkan Delivery Order');

        return $order;
    }

    /**
     * Laksdan issues the DO: its document is drawn from the template.
     */
    public function issue(ProcurementDeliveryOrder $order, User $actor): ProcurementDeliveryOrder
    {
        $this->documents->generateDeliveryOrder($order, $actor);

        $order->forceFill([
            'state' => DeliveryOrderState::Terbit,
            'issued_at' => now(),
            'issued_by' => $actor->id,
        ])->save();

        $this->log($order, $actor, ActivityType::DoTerbit, "Delivery Order {$order->number} terbit.");
        $this->notify($order, [$order->creator], 'Delivery Order terbit');

        return $order;
    }

    /**
     * The proposer's team leader, when the request goes to them first: the
     * same rule as a work proposal and an amendment.
     */
    public function teamLeaderOf(ProcurementDeliveryOrder $order): ?User
    {
        $leader = $order->creator?->teamLeader;
        $division = $order->procurement->work_director_id;

        if ($leader === null || ! $leader->is_active || $leader->trashed() || $leader->is($order->creator)) {
            return null;
        }

        $leadsDivision = $leader->work_director_id !== null
            && $leader->work_director_id === (int) $division
            && $leader->hasPermission(Permission::ApproveProposal);

        return $leadsDivision ? null : $leader;
    }

    /**
     * The next free DO number: one sequence across every KHS contract.
     */
    public function nextNumber(ContractNumberFormat $format): string
    {
        $year = (int) now()->format('Y');
        $highest = 0;

        ProcurementDeliveryOrder::query()
            ->whereNotNull('number')
            ->pluck('number')
            ->each(function (string $number) use ($format, $year, &$highest): void {
                $sequence = $format->sequenceIn($number, $year);

                if ($sequence !== null && $sequence > $highest) {
                    $highest = $sequence;
                }
            });

        return $format->compose(max($highest + 1, $format->starting_sequence), $year);
    }

    /**
     * Who acts next on the DO.
     *
     * @return array<int, User|null>
     */
    protected function nextRecipients(ProcurementDeliveryOrder $order): array
    {
        $procurement = $order->procurement;

        return match ($order->state) {
            DeliveryOrderState::MenungguPersetujuanTl => [$this->teamLeaderOf($order)],
            DeliveryOrderState::MenungguPersetujuanAsman => User::query()->active()
                ->where('work_director_id', $procurement->work_director_id)
                ->get()
                ->filter(fn (User $user): bool => $user->hasPermission(Permission::ApproveProposal))
                ->values()
                ->all(),
            // The planning PIC, or TL ICC when none is appointed.
            DeliveryOrderState::DiRendan => $procurement->planner !== null
                ? [$procurement->planner]
                : User::query()->active()->withRole([UserRole::TeamLeaderIcc])->get()->all(),
            // The execution PIC, or TL Pengadaan when none is appointed.
            DeliveryOrderState::DiLaksdan => $procurement->executor !== null
                ? [$procurement->executor]
                : User::query()->active()->withRole([UserRole::TeamLeaderPengadaan])->get()->all(),
            default => [],
        };
    }

    /**
     * Tell the given people about the DO.
     *
     * @param  array<int, User|null>  $recipients
     */
    protected function notify(ProcurementDeliveryOrder $order, array $recipients, string $title, ?string $note = null): void
    {
        $procurement = $order->procurement;
        $message = "DO ke-{$order->sequence} {$procurement->number} - {$procurement->name}: {$order->state->label()}."
            .($note !== null ? " Catatan: {$note}" : '');

        collect($recipients)->filter()->unique('id')->each(fn (User $user) => $user->notify(new ProcurementMilestone($procurement, $title, $message)));
    }

    protected function log(ProcurementDeliveryOrder $order, User $actor, ActivityType $type, string $description): void
    {
        $this->procurements->recordActivity($order->procurement, $actor, $type, $description, ['delivery_order_id' => $order->id]);
    }

    /**
     * When the contract was drawn, as the DO refers to it.
     */
    protected function contractDate(Procurement $procurement): ?CarbonImmutable
    {
        $typeIds = DocumentType::query()->whereIn('code', AmendmentService::CONTRACT_DOCUMENTS)->pluck('id');

        $generated = $procurement->documents()
            ->whereNull('procurement_amendment_id')
            ->whereNull('procurement_delivery_order_id')
            ->whereIn('document_type_id', $typeIds)
            ->oldest('generated_at')
            ->value('generated_at');

        return $generated === null ? null : CarbonImmutable::parse($generated);
    }
}
