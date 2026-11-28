<?php

namespace App\Http\Controllers;

use App\Enums\CoaRequestStatus;
use App\Enums\CoaSegment;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Requests\StoreCoaRequestRequest;
use App\Http\Requests\UpdateCoaRequestRequest;
use App\Models\CoaRequest;
use App\Models\User;
use App\Notifications\CoaRequestNotification;
use App\Support\CoaCatalog;
use App\Support\CoaUsage;
use App\Support\NotificationFeed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoaRequestController extends Controller
{
    /**
     * Show the Request COA screen for Staf / TL Pengusul and Keuangan.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canRequest = $user->hasPermission(Permission::RequestCoa);
        $canManage = $user->hasPermission(Permission::ManageCoaRequests);
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $baseQuery = CoaRequest::query()->visibleTo($user);

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', CoaRequestStatus::Draft->value)->count(),
            'pending' => (clone $baseQuery)->where('status', CoaRequestStatus::Pending->value)->count(),
            'active' => (clone $baseQuery)->where('status', CoaRequestStatus::Active->value)->count(),
        ];

        $query = (clone $baseQuery)
            ->with(['requester:id,name,position', 'workDirector:id,name', 'activator:id,name', 'updater:id,name']);

        if (in_array($status, array_column(CoaRequestStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('coa_code', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('requester', fn (Builder $sub) => $sub->where('name', 'like', $like))
                    ->orWhereHas('workDirector', fn (Builder $sub) => $sub->where('name', 'like', $like));
            });
        }

        $labelMap = CoaCatalog::labelMap();

        $requests = $query
            ->orderByRaw("CASE status WHEN 'menunggu_aktivasi' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->latest('requested_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Where each code on this page is already used (usulan & pengadaan).
        $usage = CoaUsage::map($requests->getCollection()->pluck('coa_code')->all());

        $requests->through(fn (CoaRequest $coa): array => $this->present($coa, $user, $canRequest, $canManage, $labelMap, $usage));

        $catalog = CoaCatalog::formOptions();

        return Inertia::render('coa-requests/index', [
            'coaRequests' => $requests,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'segments' => $catalog['options'],
            'defaultSegment2' => $catalog['defaultSegment2'],
            'segmentMeta' => CoaSegment::options(),
            'statusOptions' => CoaRequestStatus::options(),
            'canRequest' => $canRequest,
            'canManage' => $canManage,
        ]);
    }

    /**
     * Store a new COA request as draft (Simpan) or submitted (Request Aktivasi).
     */
    public function store(StoreCoaRequestRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $submitNow = $validated['action'] === 'request_activation';

        $coaCode = CoaCatalog::composeCode(
            $validated['segment_1'],
            $validated['segment_2'],
            $validated['segment_3'],
            $validated['segment_4'],
            $validated['segment_5'],
            $validated['segment_6'],
            $validated['segment_7'],
            $validated['expense_element'],
        );

        $coaRequest = CoaRequest::query()->create([
            'requested_by' => $user->id,
            'work_director_id' => $user->divisionId(),
            'segment_1' => $validated['segment_1'],
            'segment_2' => $validated['segment_2'],
            'segment_3' => $validated['segment_3'],
            'segment_4' => $validated['segment_4'],
            'segment_5' => $validated['segment_5'],
            'segment_6' => $validated['segment_6'],
            'segment_7' => $validated['segment_7'],
            'expense_element' => $validated['expense_element'],
            'coa_code' => $coaCode,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => $submitNow ? CoaRequestStatus::Pending : CoaRequestStatus::Draft,
            'requested_at' => $submitNow ? now() : null,
            'updated_by' => $user->id,
        ]);

        if ($submitNow) {
            $this->notifyFinanceOfSubmission($coaRequest, $user);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $submitNow
                ? "Request aktivasi COA {$coaRequest->coa_code} dikirim ke bagian Keuangan."
                : "Draft COA {$coaRequest->coa_code} disimpan.",
        ]);

        return back();
    }

    /**
     * Update a COA request:
     * - Staf / TL may edit their own draft and optionally submit it.
     * - Keuangan / Admin may edit any request (segments, description, notes, or custom code)
     *   and optionally activate it in one step.
     */
    public function update(UpdateCoaRequestRequest $request, CoaRequest $coaRequest): RedirectResponse
    {
        $user = $request->user();
        $canManage = $user->hasPermission(Permission::ManageCoaRequests);
        $validated = $request->validated();

        $composedCode = CoaCatalog::composeCode(
            $validated['segment_1'],
            $validated['segment_2'],
            $validated['segment_3'],
            $validated['segment_4'],
            $validated['segment_5'],
            $validated['segment_6'],
            $validated['segment_7'],
            $validated['expense_element'],
        );

        $coaCode = $canManage && ! empty($validated['coa_code'])
            ? $validated['coa_code']
            : $composedCode;

        $wasPending = $coaRequest->status === CoaRequestStatus::Pending;
        $wasActive = $coaRequest->status === CoaRequestStatus::Active;
        $previousCode = $coaRequest->coa_code;

        $coaRequest->fill([
            'segment_1' => $validated['segment_1'],
            'segment_2' => $validated['segment_2'],
            'segment_3' => $validated['segment_3'],
            'segment_4' => $validated['segment_4'],
            'segment_5' => $validated['segment_5'],
            'segment_6' => $validated['segment_6'],
            'segment_7' => $validated['segment_7'],
            'expense_element' => $validated['expense_element'],
            'coa_code' => $coaCode,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $user->id,
        ]);

        $message = "Request COA {$coaRequest->coa_code} diperbarui.";

        if ($validated['action'] === 'request_activation' && $coaRequest->status === CoaRequestStatus::Draft) {
            $coaRequest->status = CoaRequestStatus::Pending;
            $coaRequest->requested_at = now();
            $coaRequest->save();

            $this->notifyFinanceOfSubmission($coaRequest, $user);
            $message = "Request aktivasi COA {$coaRequest->coa_code} dikirim ke bagian Keuangan.";
        } elseif ($canManage && $validated['action'] === 'activate' && ! $wasActive) {
            $coaRequest->status = CoaRequestStatus::Active;
            $coaRequest->requested_at ??= now();
            $coaRequest->activated_by = $user->id;
            $coaRequest->activated_at = now();
            $coaRequest->save();

            $this->notifyRequesterOfActivation($coaRequest);
            $message = "COA {$coaRequest->coa_code} diperbarui dan diaktifkan.";
        } else {
            $coaRequest->save();

            if (! $wasPending && $coaRequest->status === CoaRequestStatus::Pending) {
                $this->notifyFinanceOfSubmission($coaRequest, $user);
            }
        }

        // An active code Keuangan corrects follows into the usulan using it,
        // unless another active request still carries the old code.
        if ($wasActive && $previousCode !== $coaRequest->coa_code) {
            $stillActive = CoaRequest::query()->active()
                ->whereKeyNot($coaRequest->id)
                ->where('coa_code', $previousCode)
                ->exists();

            $renamed = $stillActive ? 0 : CoaUsage::rename($previousCode, $coaRequest->coa_code);

            if ($renamed > 0) {
                $message .= " Nomor COA pada {$renamed} usulan/pengadaan ikut diperbarui.";
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return back();
    }

    /**
     * Submit an existing draft COA request to Keuangan for activation.
     */
    public function submit(Request $request, CoaRequest $coaRequest): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->role === UserRole::Administrator || $coaRequest->requested_by === $user->id,
            403,
            'Anda hanya dapat mengajukan draft COA milik sendiri.',
        );

        if ($coaRequest->status !== CoaRequestStatus::Draft) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Request COA ini sudah diajukan sebelumnya.',
            ]);

            return back();
        }

        $coaRequest->forceFill([
            'status' => CoaRequestStatus::Pending,
            'requested_at' => now(),
            'updated_by' => $user->id,
        ])->save();

        $this->notifyFinanceOfSubmission($coaRequest, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Request aktivasi COA {$coaRequest->coa_code} dikirim ke bagian Keuangan.",
        ]);

        return back();
    }

    /**
     * Cancel a pending COA activation request before Finance activates it,
     * returning it to Draft so the requester can edit, resubmit, or delete it.
     */
    public function cancel(Request $request, CoaRequest $coaRequest): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->role === UserRole::Administrator || $coaRequest->requested_by === $user->id,
            403,
            'Anda hanya dapat membatalkan request COA milik sendiri.',
        );

        if ($coaRequest->status === CoaRequestStatus::Active) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => "COA {$coaRequest->coa_code} sudah diaktifkan oleh bagian Keuangan dan tidak dapat dibatalkan.",
            ]);

            return back();
        }

        if ($coaRequest->status !== CoaRequestStatus::Pending) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Request COA ini tidak sedang menunggu aktivasi.',
            ]);

            return back();
        }

        $coaRequest->forceFill([
            'status' => CoaRequestStatus::Draft,
            'requested_at' => null,
            'updated_by' => $user->id,
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Request aktivasi COA {$coaRequest->coa_code} dibatalkan dan dikembalikan ke Draft.",
        ]);

        return back();
    }

    /**
     * Activate a COA request so it syncs into Usulan Pekerjaan.
     */
    public function activate(Request $request, CoaRequest $coaRequest): RedirectResponse
    {
        $user = $request->user();

        if ($coaRequest->status === CoaRequestStatus::Active) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => "COA {$coaRequest->coa_code} sudah berstatus aktif.",
            ]);

            return back();
        }

        $coaRequest->forceFill([
            'status' => CoaRequestStatus::Active,
            'requested_at' => $coaRequest->requested_at ?? now(),
            'activated_by' => $user->id,
            'activated_at' => now(),
            'updated_by' => $user->id,
        ])->save();

        $this->notifyRequesterOfActivation($coaRequest);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "COA {$coaRequest->coa_code} berhasil diaktifkan dan siap digunakan pada Usulan Pekerjaan.",
        ]);

        return back();
    }

    /**
     * Delete a draft COA request.
     */
    public function destroy(Request $request, CoaRequest $coaRequest): RedirectResponse
    {
        $user = $request->user();

        $isAdmin = $user->role === UserRole::Administrator;
        $isOwnDraft = $coaRequest->requested_by === $user->id && $coaRequest->status === CoaRequestStatus::Draft;

        abort_unless(
            $isAdmin || $isOwnDraft,
            403,
            'Hanya draft COA milik sendiri yang dapat dihapus.',
        );

        $code = $coaRequest->coa_code;
        $coaRequest->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Request COA {$code} dihapus.",
        ]);

        return back();
    }

    /**
     * Shape one COA request row for the Inertia table.
     *
     * @param  array<string, array<string, string>>  $labelMap
     * @param  array<string, list<array{id: int, number: string, name: string}>>  $usage
     * @return array<string, mixed>
     */
    protected function present(
        CoaRequest $coa,
        User $viewer,
        bool $canRequest,
        bool $canManage,
        array $labelMap,
        array $usage = [],
    ): array {
        $usedBy = $usage[mb_strtoupper($coa->coa_code)] ?? [];

        $isOwner = $coa->requested_by === $viewer->id;
        $isAdmin = $viewer->role === UserRole::Administrator;

        return [
            'id' => $coa->id,
            'coa_code' => $coa->coa_code,
            'segment_1' => $coa->segment_1,
            'segment_2' => $coa->segment_2,
            'segment_3' => $coa->segment_3,
            'segment_4' => $coa->segment_4,
            'segment_5' => $coa->segment_5,
            'segment_6' => $coa->segment_6,
            'segment_7' => $coa->segment_7,
            'expense_element' => $coa->expense_element,
            'segment_labels' => [
                'segment_1' => $labelMap[CoaSegment::Segment1->value][$coa->segment_1] ?? $coa->segment_1,
                'segment_2' => $labelMap[CoaSegment::Segment2->value][$coa->segment_2] ?? $coa->segment_2,
                'segment_3' => $labelMap[CoaSegment::Segment3->value][$coa->segment_3] ?? $coa->segment_3,
                'segment_4' => $labelMap[CoaSegment::Segment4->value][$coa->segment_4] ?? $coa->segment_4,
                'segment_5' => $labelMap[CoaSegment::Segment5->value][$coa->segment_5] ?? $coa->segment_5,
                'segment_6' => $labelMap[CoaSegment::Segment6->value][$coa->segment_6] ?? $coa->segment_6,
                'segment_7' => $labelMap[CoaSegment::Segment7->value][$coa->segment_7] ?? $coa->segment_7,
                'expense_element' => $labelMap[CoaSegment::ExpenseElement->value][$coa->expense_element] ?? $coa->expense_element,
            ],
            'description' => $coa->description,
            'notes' => $coa->notes,
            'status' => $coa->status->value,
            'status_label' => $coa->status->label(),
            'requester' => $coa->requester ? [
                'id' => $coa->requester->id,
                'name' => $coa->requester->name,
                'position' => $coa->requester->position,
            ] : null,
            'work_director' => $coa->workDirector?->name,
            'activator' => $coa->activator?->name,
            'updater' => $coa->updater?->name,
            'requested_at' => $coa->requested_at?->toIso8601String(),
            'activated_at' => $coa->activated_at?->toIso8601String(),
            'updated_at' => $coa->updated_at?->toIso8601String(),
            // How often the code is used on usulan / pengadaan, and where.
            'usage_count' => count($usedBy),
            'usages' => array_slice($usedBy, 0, 20),
            'can_edit' => $canManage || ($canRequest && $isOwner && $coa->status === CoaRequestStatus::Draft),
            'can_submit' => ($isAdmin || ($canRequest && $isOwner)) && $coa->status === CoaRequestStatus::Draft,
            'can_cancel' => ($isAdmin || ($canRequest && $isOwner)) && $coa->status === CoaRequestStatus::Pending,
            'can_activate' => $canManage && $coa->status !== CoaRequestStatus::Active,
            'can_delete' => $isAdmin || ($canRequest && $isOwner && $coa->status === CoaRequestStatus::Draft),
        ];
    }

    /**
     * Notify active Keuangan users when a COA activation request is submitted.
     */
    protected function notifyFinanceOfSubmission(CoaRequest $coaRequest, User $actor): void
    {
        $recipients = User::query()
            ->active()
            ->get()
            ->filter(fn (User $candidate): bool => $candidate->id !== $actor->id
                && $candidate->role !== UserRole::Administrator
                && $candidate->hasPermission(Permission::ManageCoaRequests));

        foreach ($recipients as $recipient) {
            $notification = new CoaRequestNotification(
                $coaRequest,
                'Request aktivasi COA baru',
                "{$actor->name} mengajukan aktivasi COA {$coaRequest->coa_code}.",
            );
            $recipient->notify($notification);
            NotificationFeed::broadcast($recipient);
        }
    }

    /**
     * Notify the requester when Keuangan activates their COA code.
     */
    protected function notifyRequesterOfActivation(CoaRequest $coaRequest): void
    {
        $requester = $coaRequest->requester;

        if ($requester === null || ! $requester->is_active) {
            return;
        }

        $requester->notify(new CoaRequestNotification(
            $coaRequest,
            'Nomor COA telah diaktifkan',
            "COA {$coaRequest->coa_code} telah diaktifkan oleh bagian Keuangan dan siap dipilih saat membuat Usulan Pekerjaan.",
        ));
        NotificationFeed::broadcast($requester);
    }
}
