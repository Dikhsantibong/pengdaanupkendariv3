<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\AmendmentFileKind;
use App\Enums\AmendmentState;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ContractNumberFormat;
use App\Models\DocumentType;
use App\Models\Procurement;
use App\Models\ProcurementAmendment;
use App\Models\ProcurementAmendmentFile;
use App\Models\User;
use App\Notifications\ProcurementMilestone;
use App\Support\IndonesianNumber;
use App\Support\UploadedFileType;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contract amendments: the proposer requests one (Nota Dinas usulan with its
 * date and scan, the Notulen, optionally the partner's request letter),
 * their TL and the division's Asman agree, and the execution side (Laksdan)
 * numbers it in sequence across every contract, dates it, picks the
 * articles that change from the contract and issues the document.
 */
class AmendmentService
{
    /**
     * The document types a contract is drawn as, by format.
     *
     * @var array<int, string>
     */
    public const CONTRACT_DOCUMENTS = ['spk', 'spk-pasal', 'lampiran-sp', 'purchase-order', 'surat-pesanan'];

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

            // Numbered and dated by Laksdan once the TL and Asman agree.
            $amendment = new ProcurementAmendment([
                'contract_date' => $this->contractDate($procurement)?->toDateString(),
            ]);
            $amendment->procurement_id = $procurement->id;
            $amendment->sequence = (int) $procurement->amendments()->max('sequence') + 1;
            $amendment->created_by = $author->id;
            $amendment->save();

            return $amendment;
        });
    }

    /**
     * Save the fields of a step and, when given, the changed articles.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{article: string, original_text: string, new_text: string}>|null  $changes
     */
    public function update(ProcurementAmendment $amendment, array $attributes, ?array $changes = null): ProcurementAmendment
    {
        return DB::transaction(function () use ($amendment, $attributes, $changes): ProcurementAmendment {
            $amendment->fill($attributes)->save();

            if ($changes === null) {
                return $amendment;
            }

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

        // The stored name and type come from the content, never from what the
        // browser sent.
        $extension = UploadedFileType::extension($file);
        $path = $file->storeAs(
            self::DIRECTORY.'/'.$amendment->id,
            Str::slug($kind->value).'-'.Str::random(12).'.'.$extension,
            'local',
        );

        $stored = $amendment->files()->create([
            'kind' => $kind,
            'path' => $path === false ? '' : $path,
            'file_name' => Str::limit($file->getClientOriginalName(), 240, ''),
            'mime' => UploadedFileType::mime($file),
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
     * The TL or the Asman agrees, or returns the request. The Asman's
     * agreement hands it to Laksdan, numbered on the Amd format in sequence
     * across every contract (Laksdan may correct the number).
     */
    public function decide(ProcurementAmendment $amendment, User $reviewer, bool $approved, ?string $note = null): ProcurementAmendment
    {
        $procurement = $amendment->procurement;
        $level = $amendment->state;
        $atTeamLeader = $level === AmendmentState::MenungguPersetujuanTl;

        $next = match (true) {
            ! $approved => AmendmentState::Dikembalikan,
            $atTeamLeader => AmendmentState::MenungguPersetujuanAsman,
            default => AmendmentState::MenungguTlPengadaan,
        };

        DB::transaction(function () use ($amendment, $reviewer, $atTeamLeader, $next, $note): void {
            if ($next === AmendmentState::MenungguTlPengadaan && blank($amendment->number)) {
                $format = ContractNumberFormat::forAmendments();

                if ($format !== null) {
                    // One at a time, so two amendments never draw one number.
                    ContractNumberFormat::query()->whereKey($format->id)->lockForUpdate()->first();
                    $amendment->number = $this->nextNumber($format);
                    $amendment->contract_number_format_id = $format->id;
                }
            }

            $amendment->forceFill([
                ...($atTeamLeader
                    ? ['tl_reviewed_at' => now(), 'tl_reviewed_by' => $reviewer->id]
                    : ['director_reviewed_at' => now(), 'director_reviewed_by' => $reviewer->id]),
                'state' => $next,
                'review_note' => $note,
                'amendment_date' => $amendment->amendment_date ?? ($next === AmendmentState::MenungguTlPengadaan ? now()->toDateString() : null),
            ])->save();
        });

        $who = $atTeamLeader ? 'TL' : 'Asman';

        $this->procurements->recordActivity(
            $procurement,
            $reviewer,
            match (true) {
                ! $approved => ActivityType::AmandemenDikembalikan,
                $atTeamLeader => ActivityType::AmandemenDisetujuiTl,
                default => ActivityType::AmandemenDisetujuiAsman,
            },
            Str::limit($approved
                ? "Amandemen {$amendment->roman()} disetujui {$who}".($atTeamLeader ? '.' : " dan diteruskan ke Laksdan dengan nomor {$amendment->number}.")
                : "Amandemen {$amendment->roman()} dikembalikan oleh {$who}.".($note !== null ? " Catatan: {$note}" : ''), 250),
            ['amendment_id' => $amendment->id],
        );

        if ($approved) {
            $this->notifyNext($amendment);
        } else {
            $amendment->creator?->notify(new ProcurementMilestone(
                $procurement,
                'Amandemen dikembalikan',
                "Amandemen {$amendment->roman()} {$procurement->name} dikembalikan oleh {$who}.".($note !== null ? " Catatan: {$note}" : ''),
                'amandemen',
            ));
        }

        return $amendment;
    }

    /**
     * Laksdan issues the amendment: its document is drawn from the template.
     */
    public function issue(ProcurementAmendment $amendment, User $actor): ProcurementAmendment
    {
        $procurement = $amendment->procurement;

        $this->documents->generateAmendment($amendment, $actor);

        $amendment->forceFill([
            'state' => AmendmentState::Disetujui,
            'issued_at' => now(),
            'issued_by' => $actor->id,
        ])->save();

        $this->procurements->recordActivity(
            $procurement,
            $actor,
            ActivityType::AmandemenTerbit,
            "Amandemen {$amendment->roman()} nomor {$amendment->number} terbit.",
            ['amendment_id' => $amendment->id],
        );

        $message = "Amandemen {$amendment->roman()} {$procurement->name} terbit dengan nomor {$amendment->number}.";

        collect([$amendment->creator, $procurement->executor])
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new ProcurementMilestone($procurement, 'Amandemen terbit', $message, 'amandemen')));

        return $amendment;
    }

    /**
     * The articles of the procurement's contract a change is picked from,
     * one per ayat ("PASAL 8 HARGA PEKERJAAN Ayat 1") as the amendment cites
     * them, each with its wording: the ayat numbered ("1. ..."), its lists
     * indented under it ("   a. ..."). An article without ayat is offered
     * whole. Read from the contract document (the SPK's Pasal document, the
     * Surat Perjanjian).
     *
     * @return array<int, array{article: string, text: string}>
     */
    public function contractArticles(Procurement $procurement): array
    {
        $document = app(ContractRequirementReader::class)->contractDocument($procurement);

        if ($document === null) {
            return [];
        }

        $html = (string) $document->rendered_body;

        if (preg_match_all('#<div[^>]*class="pasal"[^>]*>\s*<div[^>]*>(.*?)</div>\s*<div[^>]*>(.*?)</div>\s*</div>#is', $html, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $articles = [];
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            $start = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $end = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($html);

            $heading = Str::upper(trim($this->clean($matches[1][$index][0]).' '.$this->clean($matches[2][$index][0])));

            array_push($articles, ...$this->ayatOptions(Str::limit($heading, 200, ''), substr($html, $start, $end - $start)));
        }

        return $articles;
    }

    /**
     * The ayat of one article, or the whole article when it has none.
     *
     * @return array<int, array{article: string, text: string}>
     */
    protected function ayatOptions(string $heading, string $segment): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="pasal-root">'.$segment.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $ayat = 'contains(concat(" ", normalize-space(@class), " "), " ayat ")';
        $rows = $xpath->query("//table[{$ayat}]/tr | //table[{$ayat}]/tbody/tr");
        $options = [];

        foreach ($rows === false ? [] : $rows as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            // The ayat number cell, then its wording.
            $cells = [];

            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && strtolower($cell->tagName) === 'td') {
                    $cells[] = $cell;
                }
            }

            if (count($cells) < 2) {
                continue;
            }

            $number = preg_replace('/\D/', '', $cells[0]->textContent) ?: (string) (count($options) + 1);
            $lines = $this->lines($cells[1], 0);

            if ($lines === []) {
                continue;
            }

            $lines[0] = $number.'. '.ltrim($lines[0]);
            $options[] = ['article' => "{$heading} Ayat {$number}", 'text' => implode("\n", $lines)];
        }

        if ($options === []) {
            $root = $dom->getElementById('pasal-root');
            $lines = $root === null ? [] : $this->lines($root, 0);

            if ($lines !== []) {
                $options[] = ['article' => $heading, 'text' => implode("\n", $lines)];
            }
        }

        return $options;
    }

    /**
     * The text of a fragment as lines: its paragraphs one per line, its list
     * items marked (a., 1., i., -) and indented three spaces per level.
     *
     * @return array<int, string>
     */
    protected function lines(DOMNode $node, int $depth): array
    {
        $lines = [];
        $buffer = '';

        $flush = function () use (&$lines, &$buffer): void {
            if (trim($buffer) !== '') {
                $lines[] = $this->clean($buffer);
            }

            $buffer = '';
        };

        foreach ($node->childNodes as $child) {
            $tag = $child instanceof DOMElement ? strtolower($child->tagName) : null;

            if ($tag === 'ol' || $tag === 'ul') {
                $flush();
                $class = $child->getAttribute('class');
                $position = 0;

                foreach ($child->childNodes as $item) {
                    if (! $item instanceof DOMElement || strtolower($item->tagName) !== 'li') {
                        continue;
                    }

                    $position++;
                    $marker = match (true) {
                        $tag === 'ul' => '-',
                        str_contains($class, 'huruf') => chr(96 + min($position, 26)).'.',
                        str_contains($class, 'romawi') => strtolower(IndonesianNumber::roman($position)).'.',
                        default => $position.'.',
                    };
                    $indent = str_repeat('   ', $depth + 1);

                    foreach ($this->lines($item, $depth + 1) as $at => $line) {
                        $lines[] = $at === 0 ? $indent.$marker.' '.ltrim($line) : (str_starts_with($line, ' ') ? $line : $indent.'   '.$line);
                    }
                }

                continue;
            }

            if ($tag === 'br') {
                $flush();

                continue;
            }

            if (in_array($tag, ['p', 'div', 'table', 'tbody', 'tr'], true)) {
                $flush();
                array_push($lines, ...$this->lines($child, $depth));

                continue;
            }

            $buffer .= ' '.$child->textContent;
        }

        $flush();

        return $lines;
    }

    /**
     * Visible text on one line.
     */
    protected function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $text)));
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
            $amendment->state === AmendmentState::MenungguTlPengadaan ? 'Terbitkan amandemen' : 'Amandemen menunggu persetujuan',
            $amendment->state === AmendmentState::MenungguTlPengadaan
                ? "Amandemen {$amendment->roman()} {$procurement->number} - {$procurement->name} disetujui Asman. Isi tanggal dan perubahan pasal, lalu terbitkan."
                : "Amandemen {$amendment->roman()} {$procurement->number} - {$procurement->name} menunggu persetujuan Anda.",
            'amandemen',
        );

        $recipients = match ($amendment->state) {
            AmendmentState::MenungguPersetujuanTl => collect([$this->teamLeaderOf($amendment)]),
            AmendmentState::MenungguPersetujuanAsman => User::query()->active()
                ->where('work_director_id', $procurement->work_director_id)
                ->get()
                ->filter(fn (User $user): bool => $user->hasPermission(Permission::ApproveProposal)),
            // Laksdan: the execution PIC, or TL Pengadaan when none is appointed.
            AmendmentState::MenungguTlPengadaan => $procurement->executor !== null
                ? collect([$procurement->executor])
                : User::query()->active()->withRole([UserRole::TeamLeaderPengadaan])->get(),
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
