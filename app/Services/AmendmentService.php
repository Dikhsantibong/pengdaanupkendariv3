<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\AmendmentFileKind;
use App\Enums\AmendmentState;
use App\Enums\Permission;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementAmendmentFile;
use App\Models\User;
use App\Notifications\ProcurementMilestone;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contract amendments: the proposer drafts one (four papers, the numbers and
 * dates, the articles that change), their TL and the division's Asman agree,
 * and TL Pengadaan gives the final approval. The amendment document is
 * drawn from its template when the request is sent.
 */
class AmendmentService
{
    /**
     * The document types a contract is drawn as, by format.
     *
     * @var array<int, string>
     */
    public const CONTRACT_DOCUMENTS = ['spk', 'lampiran-sp', 'purchase-order', 'surat-pesanan'];

    /**
     * Where the papers of an amendment are kept on the private disk.
     */
    private const DIRECTORY = 'amendments';

    public function __construct(
        protected ProcurementService $procurements,
        protected DocumentGenerator $documents,
    ) {}

    /**
     * Whether the procurement has a contract that can be amended.
     */
    public function hasContract(Procurement $procurement): bool
    {
        return $procurement->documents()
            ->whereNull('procurement_amendment_id')
            ->whereHas('documentType', fn ($query) => $query->whereIn('code', self::CONTRACT_DOCUMENTS))
            ->exists();
    }

    /**
     * The amendment still open (drafted, waiting or returned), if any.
     */
    public function open(Procurement $procurement): ?ProcurementAmendment
    {
        return $procurement->amendments()
            ->whereNot('state', AmendmentState::Disetujui->value)
            ->latest('sequence')
            ->first();
    }

    /**
     * Start the next amendment of the procurement's contract.
     */
    public function start(Procurement $procurement, User $author): ProcurementAmendment
    {
        return DB::transaction(function () use ($procurement, $author): ProcurementAmendment {
            Procurement::query()->whereKey($procurement->id)->lockForUpdate()->first();

            $format = ContractNumberFormat::forAmendments();

            $amendment = new ProcurementAmendment([
                'number' => $format === null ? null : $this->nextNumber($format),
                'amendment_date' => now()->toDateString(),
                'contract_date' => $this->contractDate($procurement)?->toDateString(),
            ]);
            $amendment->procurement_id = $procurement->id;
            $amendment->sequence = (int) $procurement->amendments()->max('sequence') + 1;
            $amendment->contract_number_format_id = $format?->id;
            $amendment->created_by = $author->id;
            $amendment->save();

            return $amendment;
        });
    }

    /**
     * Save the numbers, dates and changed articles of a draft.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{article: string, original_text: string, new_text: string}>  $changes
     */
    public function update(ProcurementAmendment $amendment, array $attributes, array $changes): ProcurementAmendment
    {
        return DB::transaction(function () use ($amendment, $attributes, $changes): ProcurementAmendment {
            $amendment->fill($attributes)->save();

            $amendment->changedArticles()->delete();

            foreach (array_values($changes) as $index => $change) {
                $amendment->changedArticles()->create([
                    'sort_order' => $index + 1,
                    'article' => $change['article'],
                    'original_text' => $change['original_text'],
                    'new_text' => $change['new_text'],
                ]);
            }

            return $amendment->unsetRelation('changedArticles');
        });
    }

    /**
     * File one of the four papers, replacing the one filed before.
     */
    public function attach(ProcurementAmendment $amendment, AmendmentFileKind $kind, UploadedFile $file, User $uploader): ProcurementAmendmentFile
    {
        $previous = $amendment->files()->where('kind', $kind->value)->get();

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        // The stored name never reuses what the browser sent.
        $path = $file->storeAs(
            self::DIRECTORY.'/'.$amendment->id,
            Str::slug($kind->value).'-'.Str::random(12).($extension === '' ? '' : '.'.$extension),
            'local',
        );

        $stored = $amendment->files()->create([
            'kind' => $kind,
            'path' => $path === false ? '' : $path,
            'file_name' => Str::limit($file->getClientOriginalName(), 240, ''),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: null,
            'uploaded_by' => $uploader->id,
        ]);

        foreach ($previous as $old) {
            Storage::disk('local')->delete($old->path);
            $old->delete();
        }

        return $stored;
    }

    /**
     * Send the request on, or send it again after it was returned.
     */
    public function submit(ProcurementAmendment $amendment, User $author): ProcurementAmendment
    {
        $procurement = $amendment->procurement;
        $returned = $amendment->state === AmendmentState::Dikembalikan;
        $atTeamLeader = $this->teamLeaderOf($amendment) !== null;

        $amendment->forceFill([
            'state' => $atTeamLeader ? AmendmentState::MenungguPersetujuanTl : AmendmentState::MenungguPersetujuanAsman,
            'submitted_at' => now(),
            'tl_reviewed_at' => null,
            'tl_reviewed_by' => null,
            'director_reviewed_at' => null,
            'director_reviewed_by' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'revision' => $returned ? $amendment->revision + 1 : $amendment->revision,
        ])->save();

        // Drawn now, so every approver reads the document being agreed.
        $this->documents->generateAmendment($amendment, $author);

        $this->procurements->recordActivity(
            $procurement,
            $author,
            ActivityType::AmandemenDiajukan,
            "Amandemen {$amendment->roman()} diajukan".($returned ? " ulang (revisi ke-{$amendment->revision})" : '')
                .' ke '.($atTeamLeader ? 'TL' : 'Direksi Pekerjaan (Asman)').' untuk disetujui.',
            ['amendment_id' => $amendment->id],
        );

        $this->notifyNext($amendment);

        return $amendment;
    }

    /**
     * Approve the amendment at its current level, or return it.
     */
    public function decide(ProcurementAmendment $amendment, User $reviewer, bool $approved, ?string $note = null): ProcurementAmendment
    {
        $procurement = $amendment->procurement;
        $level = $amendment->state;

        $stamp = match ($level) {
            AmendmentState::MenungguPersetujuanTl => ['tl_reviewed_at' => now(), 'tl_reviewed_by' => $reviewer->id],
            AmendmentState::MenungguPersetujuanAsman => ['director_reviewed_at' => now(), 'director_reviewed_by' => $reviewer->id],
            default => ['reviewed_at' => now(), 'reviewed_by' => $reviewer->id],
        };

        $next = match (true) {
            ! $approved => AmendmentState::Dikembalikan,
            $level === AmendmentState::MenungguPersetujuanTl => AmendmentState::MenungguPersetujuanAsman,
            $level === AmendmentState::MenungguPersetujuanAsman => AmendmentState::MenungguTlPengadaan,
            default => AmendmentState::Disetujui,
        };

        $amendment->forceFill([...$stamp, 'state' => $next, 'review_note' => $note])->save();

        $who = match ($level) {
            AmendmentState::MenungguPersetujuanTl => 'TL',
            AmendmentState::MenungguPersetujuanAsman => 'Asman',
            default => 'TL Pengadaan',
        };

        $this->procurements->recordActivity(
            $procurement,
            $reviewer,
            match (true) {
                ! $approved => ActivityType::AmandemenDikembalikan,
                $level === AmendmentState::MenungguPersetujuanTl => ActivityType::AmandemenDisetujuiTl,
                $level === AmendmentState::MenungguPersetujuanAsman => ActivityType::AmandemenDisetujuiAsman,
                default => ActivityType::AmandemenDisetujui,
            },
            Str::limit($approved
                ? "Amandemen {$amendment->roman()} disetujui {$who}".($next === AmendmentState::Disetujui ? ' dan berlaku.' : '.')
                : "Amandemen {$amendment->roman()} dikembalikan oleh {$who}.".($note !== null ? " Catatan: {$note}" : ''), 250),
            ['amendment_id' => $amendment->id],
        );

        if (! $approved || $next === AmendmentState::Disetujui) {
            $message = $approved
                ? "Amandemen {$amendment->roman()} {$procurement->name} disetujui TL Pengadaan."
                : "Amandemen {$amendment->roman()} {$procurement->name} dikembalikan oleh {$who}.".($note !== null ? " Catatan: {$note}" : '');

            collect([$amendment->creator, $approved ? $procurement->executor : null])
                ->filter()
                ->unique('id')
                ->each(fn (User $user) => $user->notify(new ProcurementMilestone($procurement, $approved ? 'Amandemen disetujui' : 'Amandemen dikembalikan', $message)));
        } else {
            $this->notifyNext($amendment);
        }

        return $amendment;
    }

    /**
     * The proposer's team leader, when the request goes to them first:
     * the same rule as a work proposal.
     */
    public function teamLeaderOf(ProcurementAmendment $amendment): ?User
    {
        $leader = $amendment->creator?->teamLeader;
        $division = $amendment->procurement->work_director_id;

        if ($leader === null || ! $leader->is_active || $leader->trashed() || $leader->is($amendment->creator)) {
            return null;
        }

        $leadsDivision = $leader->work_director_id !== null
            && $leader->work_director_id === (int) $division
            && $leader->hasPermission(Permission::ApproveProposal);

        return $leadsDivision ? null : $leader;
    }

    /**
     * Tell whoever decides next.
     */
    protected function notifyNext(ProcurementAmendment $amendment): void
    {
        $procurement = $amendment->procurement;
        $notification = new ProcurementMilestone(
            $procurement,
            'Amandemen menunggu persetujuan',
            "Amandemen {$amendment->roman()} {$procurement->number} - {$procurement->name} menunggu persetujuan Anda.",
        );

        $recipients = match ($amendment->state) {
            AmendmentState::MenungguPersetujuanTl => collect([$this->teamLeaderOf($amendment)]),
            AmendmentState::MenungguPersetujuanAsman => User::query()->active()
                ->where('work_director_id', $procurement->work_director_id)
                ->get()
                ->filter(fn (User $user): bool => $user->hasPermission(Permission::ApproveProposal)),
            AmendmentState::MenungguTlPengadaan => User::query()->active()->get()
                ->filter(fn (User $user): bool => $user->hasPermission(Permission::ApproveAmendment)),
            default => collect(),
        };

        $recipients->filter()->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * The next free amendment number on the amendment format.
     */
    public function nextNumber(ContractNumberFormat $format): string
    {
        $year = (int) now()->format('Y');
        $highest = 0;

        ProcurementAmendment::query()
            ->where('contract_number_format_id', $format->getKey())
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
     * When the contract was drawn, as the amendment refers to it.
     */
    protected function contractDate(Procurement $procurement): ?CarbonImmutable
    {
        $typeIds = DocumentType::query()->whereIn('code', self::CONTRACT_DOCUMENTS)->pluck('id');

        $generated = $procurement->documents()
            ->whereNull('procurement_amendment_id')
            ->whereIn('document_type_id', $typeIds)
            ->oldest('generated_at')
            ->value('generated_at');

        return $generated === null ? null : CarbonImmutable::parse($generated);
    }
}
