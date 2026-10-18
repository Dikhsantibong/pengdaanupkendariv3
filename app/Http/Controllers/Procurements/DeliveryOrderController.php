<?php

namespace App\Http\Controllers\Procurements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurements\ReviewPlanningRequest;
use App\Models\Procurement;
use App\Models\ProcurementDeliveryOrder;
use App\Services\DeliveryOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The DO tab of a KHS contract: the contract's price list, and each
 * Delivery Order from the proposer's request through the TL and Asman,
 * Rendan's Nota Dinas DO, to Laksdan issuing it.
 */
class DeliveryOrderController extends Controller
{
    public function __construct(protected DeliveryOrderService $orders) {}

    /**
     * Save the contract's price list (material / jasa and their unit prices).
     */
    public function saveContractItems(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('manageContractItems', $procurement);

        $validated = $request->validate([
            'items' => ['present', 'array', 'max:500'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.group_name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.part_number' => ['nullable', 'string', 'max:100'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999999999'],
        ], [], [
            'items.*.description' => 'uraian',
            'items.*.unit' => 'satuan',
            'items.*.unit_price' => 'harga satuan',
        ]);

        $this->orders->saveContractItems($procurement, $validated['items']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Daftar harga kontrak tersimpan.']);

        return back();
    }

    /**
     * Start a DO request.
     */
    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $this->authorize('startDeliveryOrder', $procurement);

        $order = $this->orders->start($procurement, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Permohonan DO ke-{$order->sequence} dibuat. Lengkapi nota dinas usulan dan item, lalu ajukan."]);

        return back();
    }

    /**
     * Save the proposer's request: the Nota Dinas usulan and the lines.
     */
    public function saveRequest(Request $request, Procurement $procurement, ProcurementDeliveryOrder $order): RedirectResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('updateDeliveryOrderRequest', [$procurement, $order]);

        $validated = $request->validate([
            'request_memo_number' => ['nullable', 'string', 'max:255'],
            'request_memo_date' => ['nullable', 'date'],
            ...$this->lineRules($procurement),
        ], [], $this->lineAttributes());

        $this->orders->save($order, Arr::except($validated, 'items'), $validated['items']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permohonan DO tersimpan.']);

        return back();
    }

    /**
     * File the scan of the Nota Dinas usulan.
     */
    public function uploadRequest(Request $request, Procurement $procurement, ProcurementDeliveryOrder $order): RedirectResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('updateDeliveryOrderRequest', [$procurement, $order]);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ], [], ['file' => 'dokumen nota dinas usulan']);

        $this->orders->attachRequest($order, $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen nota dinas usulan tersimpan.']);

        return back();
    }

    /**
     * Open the scan of the Nota Dinas usulan.
     */
    public function requestFile(Procurement $procurement, ProcurementDeliveryOrder $order): StreamedResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('view', $procurement);

        abort_if($order->request_file_path === null || ! Storage::disk('local')->exists($order->request_file_path), 404);

        return Storage::disk('local')->response($order->request_file_path, $order->request_file_name, [
            'Content-Type' => $order->request_file_mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * Send the request to the TL / Asman.
     */
    public function submit(Request $request, Procurement $procurement, ProcurementDeliveryOrder $order): RedirectResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('updateDeliveryOrderRequest', [$procurement, $order]);

        $missing = $order->load('items')->missingRequest();

        if ($missing !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Lengkapi dulu: '.implode(', ', $missing).'.']);

            return back();
        }

        $this->orders->submit($order, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permohonan DO diajukan untuk persetujuan.']);

        return back();
    }

    /**
     * The TL or Asman approves or returns the request.
     */
    public function decide(ReviewPlanningRequest $request, Procurement $procurement, ProcurementDeliveryOrder $order): RedirectResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('decideDeliveryOrder', [$procurement, $order]);

        $approved = $request->boolean('approved');

        $this->orders->decide($order, $request->user(), $approved, $request->string('note')->trim()->value() ?: null);

        Inertia::flash('toast', [
            'type' => $approved ? 'success' : 'warning',
            'message' => $approved ? 'Permohonan DO disetujui.' : 'Permohonan DO dikembalikan ke pengusul.',
        ]);

        return back();
    }

    /**
     * Rendan saves the Nota Dinas DO.
     */
    public function saveMemo(Request $request, Procurement $procurement, ProcurementDeliveryOrder $order): RedirectResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('forwardDeliveryOrder', [$procurement, $order]);

        $validated = $request->validate([
            'do_memo_number' => ['nullable', 'string', 'max:255'],
            'do_memo_date' => ['nullable', 'date'],
        ]);

        $this->orders->save($order, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nota dinas DO tersimpan.']);

        return back();
    }

    /**
     * Rendan hands the DO to Laksdan.
     */
    public function forward(Request $request, Procurement $procurement, ProcurementDeliveryOrder $order): RedirectResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('forwardDeliveryOrder', [$procurement, $order]);

        $missing = $order->missingMemo();

        if ($missing !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Lengkapi dulu: '.implode(', ', $missing).'.']);

            return back();
        }

        $this->orders->forward($order, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "DO diteruskan ke Laksdan dengan nomor {$order->number}."]);

        return back();
    }

    /**
     * Laksdan saves the DO's number, date, delivery terms and lines.
     */
    public function saveIssue(Request $request, Procurement $procurement, ProcurementDeliveryOrder $order): RedirectResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('issueDeliveryOrder', [$procurement, $order]);

        $validated = $request->validate([
            'number' => ['nullable', 'string', 'max:100', Rule::unique('procurement_delivery_orders', 'number')->ignore($order->id)],
            'order_date' => ['nullable', 'date'],
            'vendor_code' => ['nullable', 'string', 'max:100'],
            'work_description' => ['nullable', 'string', 'max:500'],
            'delivery_place' => ['nullable', 'string', 'max:500'],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            ...$this->lineRules($procurement),
        ], [], [
            ...$this->lineAttributes(),
            'number' => 'nomor DO',
            'order_date' => 'tanggal DO',
            'delivery_days' => 'waktu penyerahan',
        ]);

        $this->orders->save($order, Arr::except($validated, 'items'), $validated['items']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data DO tersimpan.']);

        return back();
    }

    /**
     * Laksdan issues the DO and its document is drawn.
     */
    public function issue(Request $request, Procurement $procurement, ProcurementDeliveryOrder $order): RedirectResponse
    {
        $this->belongs($procurement, $order);
        $this->authorize('issueDeliveryOrder', [$procurement, $order]);

        $missing = $order->load('items')->missingIssue();

        if ($missing !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Lengkapi dulu: '.implode(', ', $missing).'.']);

            return back();
        }

        try {
            $this->orders->issue($order, $request->user());
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Delivery Order {$order->number} terbit."]);

        return back();
    }

    /**
     * The lines of a DO: each one a line of this procurement's price list,
     * with how many are called off.
     *
     * @return array<string, mixed>
     */
    protected function lineRules(Procurement $procurement): array
    {
        return [
            'items' => ['present', 'array', 'max:500'],
            'items.*.contract_item_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('procurement_contract_items', 'id')->where('procurement_id', $procurement->id),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function lineAttributes(): array
    {
        return [
            'items.*.contract_item_id' => 'item kontrak',
            'items.*.quantity' => 'volume',
        ];
    }

    /**
     * A DO is only reached through its own procurement.
     */
    protected function belongs(Procurement $procurement, ProcurementDeliveryOrder $order): void
    {
        abort_unless($order->procurement_id === $procurement->id, 404);
    }
}
