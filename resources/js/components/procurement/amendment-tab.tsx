import { router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    CircleAlert,
    Eye,
    FilePlus2,
    MoreHorizontal,
    Pencil,
    Paperclip,
    Plus,
    RotateCcw,
    Send,
    Trash2,
    Upload,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { DecisionControls } from '@/components/procurement/decision-controls';
import {
    forgetRememberedPanel,
    NextActionBanner,
    PanelSection,
    rememberNewestPanel,
    StageDots,
    touchRememberedPanel,
    useRememberedPanel,
} from '@/components/procurement/request-board';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import {
    DetailToggle,
    RequestDocumentActions,
    RequestProgress,
} from './request-progress';

type AmendmentChange = {
    article: string;
    original_text: string;
    new_text: string;
};

type AmendmentFile = {
    kind: string;
    label: string;
    required: boolean;
    file: {
        name: string;
        size: number | null;
        uploaded_by: string | null;
        uploaded_at: string | null;
        url: string;
    } | null;
};

export type Amendment = {
    id: number;
    sequence: number;
    roman: string;
    number: string | null;
    state: string;
    state_label: string;
    amendment_date: string | null;
    contract_date: string | null;
    meeting_date: string | null;
    memo_number: string | null;
    memo_date: string | null;
    request_number: string | null;
    request_date: string | null;
    created_by: string | null;
    submitted_at: string | null;
    review_note: string | null;
    revision: number;
    decisions: Array<{ level: string; by: string | null; at: string }>;
    changes: AmendmentChange[];
    files: AmendmentFile[];
    document: {
        id: number;
        title: string;
        edit_url: string;
        preview_url: string;
    } | null;
    missing: string[];
    can: { update: boolean; decide: boolean; issue: boolean };
};

/** An article of the contract, which a change is picked from. */
export type ContractArticle = { article: string; text: string };

const STATE_TONE: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    dikembalikan: 'bg-destructive/10 text-destructive',
    disetujui: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
};

/** The steps of an amendment, and where each state stands among them. */
const AMD_STEPS = ['Pengusul', 'TL', 'Asman', 'Laksdan', 'Terbit'];
const AMD_STEP_OF: Record<string, number> = {
    draft: 0,
    dikembalikan: 0,
    menunggu_persetujuan_tl: 1,
    menunggu_persetujuan_asman: 2,
    menunggu_tl_pengadaan: 3,
    disetujui: AMD_STEPS.length,
};

/** Whether the user has something to do on this amendment now. */
const actionable = (amendment: Amendment): boolean =>
    amendment.can.update || amendment.can.decide || amendment.can.issue;

/** What the user is to do on an amendment, in a word and in a sentence. */
function taskOf(
    amendment: Amendment,
): { action: string; detail: string } | null {
    if (amendment.can.update) {
        return amendment.state === 'dikembalikan'
            ? {
                  action: 'Perbaiki',
                  detail: 'Dikembalikan: perbaiki permohonan sesuai catatan, lalu ajukan ulang.',
              }
            : {
                  action: 'Lengkapi',
                  detail: 'Isi Nota Dinas usulan dan tanggal notulen, unggah dokumen permohonan, lalu ajukan.',
              };
    }

    if (amendment.can.decide) {
        return {
            action: 'Tinjau',
            detail: 'Periksa permohonan dan dokumennya, lalu setujui atau kembalikan.',
        };
    }

    if (amendment.can.issue) {
        return {
            action: 'Terbitkan',
            detail: 'Isi nomor, tanggal, dan pasal yang berubah, periksa dokumennya, lalu terbitkan.',
        };
    }

    return null;
}

function StateBadge({ amendment }: { amendment: Amendment }) {
    return (
        <span
            className={`rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap ${
                STATE_TONE[amendment.state] ??
                'bg-amber-500/10 text-amber-800 dark:text-amber-300'
            }`}
        >
            {amendment.state_label}
            {amendment.revision > 0 ? ` · revisi ke-${amendment.revision}` : ''}
        </span>
    );
}

/**
 * The Amandemen tab as a table: what waits on the user is named in a banner
 * above it, every amendment is a row, and the row opens a side panel with
 * only the user's part of it, step by step.
 */
export function AmendmentTab({
    procurementId,
    amendments,
    canStart,
    articles = [],
}: {
    procurementId: number;
    amendments: Amendment[];
    canStart: boolean;
    /** The contract's articles, for Laksdan to pick the changed ones. */
    articles?: ContractArticle[];
}) {
    const scope = `amendment:${procurementId}`;
    const [starting, setStarting] = useState(false);
    const [openId, open] = useRememberedPanel(
        scope,
        amendments.map((amendment) => amendment.id),
    );
    const newestFirst = [...amendments].reverse();
    const mine = newestFirst.filter(actionable);
    const first = mine[0] ?? null;
    const firstTask = first === null ? null : taskOf(first);
    const openAmendment =
        amendments.find((amendment) => amendment.id === openId) ?? null;

    return (
        <div className="space-y-3">
            <section className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card p-4">
                <div className="text-sm">
                    <p className="font-semibold">Amandemen Kontrak</p>
                    <p className="text-xs text-muted-foreground">
                        Perubahan pasal kontrak: diajukan pengusul, disetujui TL
                        dan Asman, lalu diterbitkan Laksdan.
                    </p>
                </div>
                {canStart && (
                    <Button
                        disabled={starting}
                        onClick={() =>
                            router.post(
                                procurements.amendments.store(procurementId)
                                    .url,
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => {
                                        setStarting(true);
                                        // The new draft opens when it is back.
                                        rememberNewestPanel(scope);
                                    },
                                    onError: forgetRememberedPanel,
                                    onFinish: () => setStarting(false),
                                },
                            )
                        }
                    >
                        <FilePlus2 className="size-4" />
                        Ajukan Amandemen
                    </Button>
                )}
            </section>

            {first !== null && firstTask !== null && (
                <NextActionBanner
                    badge={first.roman}
                    label={
                        mine.length > 1
                            ? `Perlu tindakan Anda (${mine.length})`
                            : 'Perlu tindakan Anda'
                    }
                    title={`Amandemen ${first.roman} · ${first.state_label}`}
                    detail={
                        firstTask.detail +
                        ((first.can.update || first.can.issue) &&
                        first.missing.length > 0
                            ? ` Yang belum: ${first.missing.slice(0, 3).join(', ')}${first.missing.length > 3 ? ` dan ${first.missing.length - 3} lainnya` : ''}.`
                            : '')
                    }
                    action={firstTask.action}
                    onAction={() => open(first.id)}
                />
            )}

            {amendments.length === 0 ? (
                <p className="rounded-md border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                    Belum ada amandemen.
                    {canStart
                        ? ' Klik Ajukan Amandemen untuk memulai permohonan.'
                        : ' Amandemen dapat diajukan pengusul setelah dokumen kontrak dibuat.'}
                </p>
            ) : (
                <div className="@container overflow-hidden rounded-md border border-border bg-card">
                    <div
                        aria-hidden="true"
                        className="hidden grid-cols-[minmax(0,0.9fr)_minmax(0,1.3fr)_minmax(0,0.8fr)_minmax(0,1.5fr)_10rem] gap-3 border-b border-border bg-muted/40 px-4 py-2 text-[0.6875rem] font-semibold tracking-wide text-muted-foreground uppercase @3xl:grid"
                    >
                        <span>Amandemen</span>
                        <span>Nomor &amp; tanggal</span>
                        <span>Perubahan</span>
                        <span>Status</span>
                        <span className="text-right">Aksi</span>
                    </div>
                    <ul className="divide-y divide-border">
                        {newestFirst.map((amendment) => (
                            <AmendmentRow
                                key={amendment.id}
                                amendment={amendment}
                                highlighted={amendment.id === first?.id}
                                onOpen={() => open(amendment.id)}
                            />
                        ))}
                    </ul>
                </div>
            )}

            <Sheet
                open={openAmendment !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        open(null);
                    }
                }}
            >
                <SheetContent
                    className="w-full gap-0 sm:max-w-2xl lg:max-w-3xl"
                    onPointerDownCapture={touchRememberedPanel}
                >
                    {openAmendment !== null && (
                        <AmendmentPanel
                            procurementId={procurementId}
                            amendment={openAmendment}
                            articles={articles}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}

/** One amendment in the table: what it is, where it stands, and one action. */
function AmendmentRow({
    amendment,
    highlighted,
    onOpen,
}: {
    amendment: Amendment;
    highlighted: boolean;
    onOpen: () => void;
}) {
    const task = taskOf(amendment);

    return (
        <li
            onClick={(event) => {
                if (
                    !(event.target as HTMLElement).closest(
                        'button, a, [role="menuitem"]',
                    )
                ) {
                    onOpen();
                }
            }}
            className={cn(
                'grid cursor-pointer gap-x-3 gap-y-1.5 px-4 py-3 transition-colors hover:bg-muted/40 @3xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.3fr)_minmax(0,0.8fr)_minmax(0,1.5fr)_10rem] @3xl:items-center',
                highlighted &&
                    'bg-primary/[0.03] shadow-[inset_3px_0_0_var(--primary)]',
            )}
        >
            <button
                type="button"
                onClick={onOpen}
                className="text-left text-sm font-medium hover:underline"
            >
                Amandemen {amendment.roman}
            </button>
            <div className="tabular min-w-0 text-xs">
                <p className="truncate">
                    {amendment.number ?? (
                        <span className="text-muted-foreground">
                            Belum bernomor
                        </span>
                    )}
                </p>
                <p className="text-muted-foreground">
                    {amendment.amendment_date
                        ? formatDate(amendment.amendment_date)
                        : amendment.created_by
                          ? `Diajukan ${amendment.created_by}`
                          : '—'}
                </p>
            </div>
            <p className="text-xs text-muted-foreground">
                {amendment.changes.length > 0
                    ? `${amendment.changes.length} pasal`
                    : '—'}
            </p>
            <div className="space-y-1">
                <StateBadge amendment={amendment} />
                <StageDots
                    steps={AMD_STEPS}
                    current={AMD_STEP_OF[amendment.state] ?? 0}
                    returned={amendment.state === 'dikembalikan'}
                />
            </div>
            <div className="flex items-center gap-1 @3xl:justify-end">
                <Button
                    size="sm"
                    variant={
                        task === null
                            ? 'ghost'
                            : highlighted
                              ? 'default'
                              : 'outline'
                    }
                    onClick={onOpen}
                >
                    {task?.action ?? 'Lihat'}
                    {task === null && <ChevronRight className="size-3.5" />}
                </Button>
                {amendment.document && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                size="sm"
                                variant="ghost"
                                className="size-8 p-0"
                                aria-label={`Aksi lain untuk Amandemen ${amendment.roman}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <a
                                    href={amendment.document.preview_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Eye className="size-4" />
                                    Pratinjau PDF
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <a href={amendment.document.edit_url}>
                                    <Pencil className="size-4" />
                                    Edit dokumen
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </li>
    );
}

/** Who agreed, and when. */
function DecisionList({ decisions }: { decisions: Amendment['decisions'] }) {
    if (decisions.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-1 text-xs text-muted-foreground">
            {decisions.map((decision) => (
                <li key={decision.level} className="flex items-center gap-1.5">
                    <CheckCircle2 className="size-3.5 text-emerald-600" />
                    {decision.level}
                    {decision.by ? ` (${decision.by})` : ''} ·{' '}
                    {formatDateTime(decision.at)}
                </li>
            ))}
        </ul>
    );
}

/**
 * An amendment in the side panel: where it stands, then only the user's
 * part of it, numbered in the order it is done. The proposer fills in the
 * request and its papers and submits; an approver reads them and decides;
 * Laksdan fills in the changed articles and issues. Anyone else reads it.
 */
function AmendmentPanel({
    procurementId,
    amendment,
    articles,
}: {
    procurementId: number;
    amendment: Amendment;
    articles: ContractArticle[];
}) {
    const args = { procurement: procurementId, amendment: amendment.id };
    const [details, setDetails] = useState(false);
    const task = taskOf(amendment);
    let section = 0;

    return (
        <>
            <SheetHeader className="border-b border-border pr-12">
                <p className="section-label">Amandemen Kontrak</p>
                <SheetTitle className="flex flex-wrap items-center gap-2 text-base">
                    Amandemen {amendment.roman}
                    <StateBadge amendment={amendment} />
                </SheetTitle>
                <SheetDescription className="tabular">
                    {amendment.number ?? 'Nomor belum diisi'}
                    {amendment.created_by
                        ? ` · diajukan ${amendment.created_by}`
                        : ''}
                </SheetDescription>
                <RequestProgress
                    steps={AMD_STEPS}
                    current={AMD_STEP_OF[amendment.state] ?? 0}
                    returned={amendment.state === 'dikembalikan'}
                />
            </SheetHeader>

            <div className="flex-1 space-y-5 overflow-y-auto p-4">
                {task !== null && (
                    <p className="rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-xs">
                        <b>Tugas Anda:</b> {task.detail}
                    </p>
                )}

                {amendment.state === 'dikembalikan' &&
                    amendment.review_note && (
                        <p className="flex items-start gap-1.5 rounded-sm border border-destructive/40 bg-destructive/5 p-2.5 text-xs">
                            <RotateCcw className="mt-0.5 size-3.5 shrink-0 text-destructive" />
                            <span>
                                <b>Yang harus diperbaiki:</b>{' '}
                                {amendment.review_note}
                            </span>
                        </p>
                    )}

                {amendment.can.update && (
                    <>
                        <PanelSection
                            number={++section}
                            title="Isi permohonan"
                            hint="Simpan isian sebelum mengajukan."
                        >
                            <RequestForm
                                procurementId={procurementId}
                                amendment={amendment}
                            />
                        </PanelSection>
                        <PanelSection
                            number={++section}
                            title="Unggah dokumen permohonan"
                            hint="Yang bertanda * wajib diunggah."
                        >
                            <FileList
                                procurementId={procurementId}
                                amendment={amendment}
                                titled={false}
                            />
                        </PanelSection>
                    </>
                )}

                {amendment.can.decide && (
                    <PanelSection number={++section} title="Periksa permohonan">
                        <AmendmentSummary amendment={amendment} />
                        <FileList
                            procurementId={procurementId}
                            amendment={amendment}
                        />
                    </PanelSection>
                )}

                {amendment.can.issue && (
                    <>
                        <PanelSection
                            number={++section}
                            title="Periksa permohonan"
                        >
                            <DetailToggle
                                open={details}
                                onToggle={() => setDetails(!details)}
                            />
                            {details && (
                                <>
                                    <AmendmentSummary amendment={amendment} />
                                    <FileList
                                        procurementId={procurementId}
                                        amendment={amendment}
                                    />
                                </>
                            )}
                        </PanelSection>
                        <PanelSection
                            number={++section}
                            title="Data amandemen & pasal yang berubah"
                        >
                            <IssueForm
                                procurementId={procurementId}
                                amendment={amendment}
                                articles={articles}
                            />
                        </PanelSection>
                    </>
                )}

                {!actionable(amendment) && (
                    <>
                        <PanelSection number={++section} title="Ringkasan">
                            <AmendmentSummary amendment={amendment} />
                        </PanelSection>
                        <PanelSection
                            number={++section}
                            title="Dokumen permohonan"
                        >
                            <FileList
                                procurementId={procurementId}
                                amendment={amendment}
                                titled={false}
                            />
                        </PanelSection>
                    </>
                )}

                {amendment.document && (
                    <PanelSection
                        number={++section}
                        title="Dokumen amandemen"
                        hint="Periksa dokumen yang digenerate; perbaiki redaksinya lewat Edit bila perlu."
                    >
                        <RequestDocumentActions document={amendment.document} />
                    </PanelSection>
                )}

                {amendment.can.update && (
                    <PanelSection number={++section} title="Ajukan">
                        <ActionBar
                            missing={amendment.missing}
                            label={
                                amendment.state === 'dikembalikan'
                                    ? 'Ajukan Ulang Amandemen'
                                    : 'Ajukan untuk Persetujuan'
                            }
                            url={procurements.amendments.submit(args).url}
                        />
                    </PanelSection>
                )}

                {amendment.can.issue && (
                    <PanelSection number={++section} title="Terbitkan">
                        <ActionBar
                            missing={amendment.missing}
                            label="Terbitkan Amandemen"
                            url={procurements.amendments.issue(args).url}
                        />
                    </PanelSection>
                )}

                {amendment.can.decide && (
                    <PanelSection number={++section} title="Keputusan">
                        <DecisionForm
                            procurementId={procurementId}
                            amendment={amendment}
                        />
                    </PanelSection>
                )}

                {amendment.decisions.length > 0 && (
                    <div className="space-y-1">
                        <p className="text-xs font-semibold">Persetujuan</p>
                        <DecisionList decisions={amendment.decisions} />
                    </div>
                )}
            </div>
        </>
    );
}

/** The proposer's request: the Nota Dinas usulan and the Notulen date. */
function RequestForm({
    procurementId,
    amendment,
}: {
    procurementId: number;
    amendment: Amendment;
}) {
    const form = useForm({
        memo_number: amendment.memo_number ?? '',
        memo_date: amendment.memo_date ?? '',
        meeting_date: amendment.meeting_date ?? '',
    });

    const field = (
        name: 'memo_number' | 'memo_date' | 'meeting_date',
        label: string,
        type: 'text' | 'date' = 'text',
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`amd-${amendment.id}-${name}`}>{label}</Label>
            <Input
                id={`amd-${amendment.id}-${name}`}
                type={type}
                className="tabular"
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
            />
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <form
            className="space-y-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    procurements.amendments.update({
                        procurement: procurementId,
                        amendment: amendment.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <div className="grid gap-3 md:grid-cols-3">
                {field('memo_number', 'Nomor Nota Dinas Usulan')}
                {field('memo_date', 'Tanggal Nota Dinas Usulan', 'date')}
                {field('meeting_date', 'Tanggal Notulen Rapat', 'date')}
            </div>
            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Permohonan
            </Button>
        </form>
    );
}

/**
 * Laksdan's part: the number (drawn automatically, correctable), the dates,
 * and the articles that change, each picked from the contract with what it
 * said there, and what it says now.
 */
function IssueForm({
    procurementId,
    amendment,
    articles,
}: {
    procurementId: number;
    amendment: Amendment;
    articles: ContractArticle[];
}) {
    const form = useForm<{
        number: string;
        amendment_date: string;
        contract_date: string;
        changes: AmendmentChange[];
    }>({
        number: amendment.number ?? '',
        amendment_date: amendment.amendment_date ?? '',
        contract_date: amendment.contract_date ?? '',
        changes:
            amendment.changes.length > 0
                ? amendment.changes
                : [{ article: '', original_text: '', new_text: '' }],
    });

    const errors = form.errors as Record<string, string | undefined>;

    const setChange = (index: number, patch: Partial<AmendmentChange>) =>
        form.setData(
            'changes',
            form.data.changes.map((change, at) =>
                at === index ? { ...change, ...patch } : change,
            ),
        );

    const field = (
        name: 'number' | 'amendment_date' | 'contract_date',
        label: string,
        type: 'text' | 'date' = 'text',
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`amd-${amendment.id}-${name}`}>{label}</Label>
            <Input
                id={`amd-${amendment.id}-${name}`}
                type={type}
                className="tabular"
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
            />
            <InputError message={errors[name]} />
        </div>
    );

    return (
        <form
            className="space-y-4 rounded-sm border border-border p-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    procurements.amendments.issueData({
                        procurement: procurementId,
                        amendment: amendment.id,
                    }).url,
                    { preserveScroll: true },
                );
            }}
        >
            <p className="text-sm font-semibold">
                Penerbitan Amandemen (Laksdan)
            </p>
            <div className="grid gap-3 md:grid-cols-3">
                {field('number', 'Nomor Amandemen (otomatis, bisa dikoreksi)')}
                {field('amendment_date', 'Tanggal Amandemen', 'date')}
                {field('contract_date', 'Tanggal Kontrak', 'date')}
            </div>

            <div className="space-y-3">
                <p className="text-sm font-semibold">
                    Pasal 1 · Perubahan Ketentuan
                </p>
                {articles.length === 0 && (
                    <p className="text-xs text-muted-foreground">
                        Pasal kontrak tidak terbaca dari dokumen kontrak; ketik
                        pasal dan bunyi semula secara manual.
                    </p>
                )}
                {form.data.changes.map((change, index) => (
                    <ChangeEditor
                        key={index}
                        amendmentId={amendment.id}
                        index={index}
                        change={change}
                        articles={articles}
                        errors={errors}
                        canRemove={form.data.changes.length > 1}
                        onChange={(patch) => setChange(index, patch)}
                        onRemove={() =>
                            form.setData(
                                'changes',
                                form.data.changes.filter(
                                    (_, at) => at !== index,
                                ),
                            )
                        }
                    />
                ))}
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        form.setData('changes', [
                            ...form.data.changes,
                            { article: '', original_text: '', new_text: '' },
                        ])
                    }
                >
                    <Plus className="size-4" />
                    Tambah Perubahan
                </Button>
            </div>

            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan Data Amandemen
            </Button>
        </form>
    );
}

/**
 * One changed article, kept simple: pick the article (ayat) from the
 * contract, its wording comes along and is shown as the document will print
 * it; "Menjadi" starts from that wording, so only what changes is edited.
 */
function ChangeEditor({
    amendmentId,
    index,
    change,
    articles,
    errors,
    canRemove,
    onChange,
    onRemove,
}: {
    amendmentId: number;
    index: number;
    change: AmendmentChange;
    articles: ContractArticle[];
    errors: Record<string, string | undefined>;
    canRemove: boolean;
    onChange: (patch: Partial<AmendmentChange>) => void;
    onRemove: () => void;
}) {
    // Typed by hand when the contract offers nothing to pick from.
    const [editOriginal, setEditOriginal] = useState(articles.length === 0);
    const picked = articles.findIndex(
        (article) => article.article === change.article,
    );
    // What changes, marked as it is typed.
    const words = useMemo(
        () => changedWords(change.original_text, change.new_text),
        [change.original_text, change.new_text],
    );

    return (
        <div className="space-y-3 rounded-sm border border-border p-3">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-semibold">
                    Perubahan {index + 1}
                    {change.article ? (
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            · {change.article}
                        </span>
                    ) : null}
                </p>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="text-destructive hover:text-destructive"
                    disabled={!canRemove}
                    onClick={onRemove}
                >
                    <Trash2 className="size-3.5" />
                    Hapus
                </Button>
            </div>

            {articles.length > 0 ? (
                <div className="grid gap-1.5">
                    <Label>1. Pilih pasal / ayat yang diubah</Label>
                    <Select
                        value={picked >= 0 ? String(picked) : ''}
                        onValueChange={(value) => {
                            const article = articles[Number(value)];

                            if (article) {
                                // "Menjadi" starts from the old wording.
                                onChange({
                                    article: article.article,
                                    original_text: article.text,
                                    new_text: article.text,
                                });
                            }
                        }}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Pilih dari pasal kontrak, mis. PASAL 8 HARGA PEKERJAAN Ayat 1" />
                        </SelectTrigger>
                        <SelectContent className="max-h-80">
                            {articles.map((article, at) => (
                                <SelectItem
                                    key={`${article.article}-${at}`}
                                    value={String(at)}
                                >
                                    {article.article}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            ) : (
                <div className="grid gap-1.5">
                    <Label htmlFor={`amd-${amendmentId}-article-${index}`}>
                        1. Pasal / ayat yang diubah
                    </Label>
                    <Input
                        id={`amd-${amendmentId}-article-${index}`}
                        value={change.article}
                        placeholder="Contoh: PASAL 8 HARGA PEKERJAAN Ayat 1"
                        onChange={(event) =>
                            onChange({ article: event.target.value })
                        }
                    />
                </div>
            )}
            <InputError message={errors[`changes.${index}.article`]} />

            {change.article !== '' || articles.length === 0 ? (
                <div className="grid gap-3 lg:grid-cols-2">
                    <div className="grid content-start gap-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label>Semula berbunyi</Label>
                            {articles.length > 0 && (
                                <button
                                    type="button"
                                    className="text-xs text-primary hover:underline"
                                    onClick={() =>
                                        setEditOriginal(!editOriginal)
                                    }
                                >
                                    {editOriginal
                                        ? 'Selesai'
                                        : 'Koreksi teks semula'}
                                </button>
                            )}
                        </div>
                        {editOriginal ? (
                            <Textarea
                                rows={8}
                                value={change.original_text}
                                onChange={(event) =>
                                    onChange({
                                        original_text: event.target.value,
                                    })
                                }
                            />
                        ) : (
                            <div className="max-h-72 overflow-y-auto rounded-md border border-border bg-muted/30 p-3">
                                <ArticleText
                                    text={change.original_text}
                                    changed={words?.removed}
                                    tone="removed"
                                />
                            </div>
                        )}
                        <InputError
                            message={errors[`changes.${index}.original_text`]}
                        />
                    </div>

                    <div className="grid content-start gap-1.5">
                        <Label htmlFor={`amd-${amendmentId}-new-${index}`}>
                            2. Menjadi — ubah bagian yang berubah saja
                        </Label>
                        <Textarea
                            id={`amd-${amendmentId}-new-${index}`}
                            rows={8}
                            value={change.new_text}
                            onChange={(event) =>
                                onChange({ new_text: event.target.value })
                            }
                        />
                        <p className="text-[11px] text-muted-foreground">
                            Baris diawali “1.” menjadi nomor ayat; baris diawali
                            spasi lalu “a.” menjadi sub-butir menjorok, seperti
                            di kontrak.
                        </p>
                        <InputError
                            message={errors[`changes.${index}.new_text`]}
                        />
                        {change.new_text.trim() !== '' && (
                            <div className="rounded-md border border-dashed border-border p-3">
                                <p className="mb-1 text-[11px] font-medium text-muted-foreground">
                                    Tampilan di dokumen · yang berubah ditandai
                                    hijau, yang dihapus dicoret merah di teks
                                    semula
                                </p>
                                <ArticleText
                                    text={change.new_text}
                                    changed={words?.added}
                                    tone="added"
                                />
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                <p className="text-xs text-muted-foreground">
                    Pilih pasal dulu; bunyi semula terisi dari kontrak dan
                    “Menjadi” disiapkan dari teks yang sama.
                </p>
            )}
        </div>
    );
}

/** The lines of an article's wording, each with its marker and indent. */
function layoutArticle(text: string) {
    const rows: Array<{
        indented: boolean;
        marker: string | null;
        body: string;
        under: boolean;
    }> = [];
    let numbered = false;

    for (const raw of text.split(/\r?\n/)) {
        if (raw.trim() === '') {
            continue;
        }

        const indented = /^\s+/.test(raw);
        const line = raw.trim();
        const match = line.match(
            /^((?:\d+|[a-z]|[ivxlc]+)\.|\(\d+\)|-)\s+(.*)$/,
        );
        const marker = match ? match[1] : null;

        if (!indented && marker) {
            numbered = true;
        }

        rows.push({
            indented,
            marker,
            body: match ? match[2] : line,
            under: numbered,
        });
    }

    return rows;
}

/** The words of an article's wording, in order, numbers and markers left out. */
function articleWords(text: string): string[] {
    return layoutArticle(text).flatMap((row) => splitWords(row.body));
}

function splitWords(text: string): string[] {
    return text.split(/\s+/).filter(Boolean);
}

/** Beyond this many word pairs the comparison is left out (too long to weigh). */
const DIFF_LIMIT = 600_000;

/**
 * Which words of the old wording went, and which of the new one came: the
 * words outside their longest common run.
 */
function changedWords(
    before: string,
    after: string,
): { removed: Set<number>; added: Set<number> } | null {
    const a = articleWords(before);
    const b = articleWords(after);

    if (a.length * b.length > DIFF_LIMIT) {
        return null;
    }

    // lengths[i * width + j]: the common run of a[i..] and b[j..].
    const width = b.length + 1;
    const lengths = new Uint32Array((a.length + 1) * width);

    for (let i = a.length - 1; i >= 0; i--) {
        for (let j = b.length - 1; j >= 0; j--) {
            lengths[i * width + j] =
                a[i] === b[j]
                    ? lengths[(i + 1) * width + j + 1] + 1
                    : Math.max(
                          lengths[(i + 1) * width + j],
                          lengths[i * width + j + 1],
                      );
        }
    }

    const removed = new Set<number>();
    const added = new Set<number>();
    let i = 0;
    let j = 0;

    while (i < a.length && j < b.length) {
        if (a[i] === b[j]) {
            i++;
            j++;
        } else if (lengths[(i + 1) * width + j] >= lengths[i * width + j + 1]) {
            removed.add(i++);
        } else {
            added.add(j++);
        }
    }

    while (i < a.length) {
        removed.add(i++);
    }

    while (j < b.length) {
        added.add(j++);
    }

    return { removed, added };
}

const CHANGE_TONE = {
    removed:
        'rounded-sm bg-red-500/15 text-red-800 line-through decoration-red-500/60 dark:text-red-300',
    added: 'rounded-sm bg-emerald-500/20 text-emerald-800 dark:text-emerald-300',
};

/**
 * An article's wording laid out as the amendment prints it: the ayat number
 * hanging beside its text, list items indented under it. Mirrors the
 * server's layout of the document. Words that changed can be marked.
 */
function ArticleText({
    text,
    changed,
    tone = 'added',
}: {
    text: string;
    /** The words (counted across the whole wording) to mark. */
    changed?: Set<number>;
    tone?: 'removed' | 'added';
}) {
    const rows = layoutArticle(text);

    if (rows.length === 0) {
        return <p className="text-xs text-muted-foreground">—</p>;
    }

    // Where each row's words start in the count across the wording.
    const starts = rows.reduce<number[]>(
        (list, row, at) => [
            ...list,
            at === 0 ? 0 : list[at - 1] + splitWords(rows[at - 1].body).length,
        ],
        [],
    );

    return (
        <div className="space-y-0.5 text-xs leading-relaxed text-foreground">
            {rows.map((row, at) => (
                <div
                    key={at}
                    className={cn(
                        'flex gap-1.5 text-justify',
                        (row.indented || (!row.marker && row.under)) && 'pl-5',
                    )}
                >
                    {row.marker && (
                        <span className="w-5 shrink-0">{row.marker}</span>
                    )}
                    <span className="min-w-0 flex-1">
                        {changed === undefined || changed.size === 0
                            ? row.body
                            : splitWords(row.body).map((word, index) => (
                                  <span key={index}>
                                      {index > 0 && ' '}
                                      <span
                                          className={
                                              changed.has(starts[at] + index)
                                                  ? CHANGE_TONE[tone]
                                                  : undefined
                                          }
                                      >
                                          {word}
                                      </span>
                                  </span>
                              ))}
                    </span>
                </div>
            ))}
        </div>
    );
}

/**
 * An article's change side by side: Semula with the words that go struck
 * through in red, Menjadi with the words that come marked green.
 */
function ArticleDiff({ before, after }: { before: string; after: string }) {
    const words = changedWords(before, after);

    return (
        <div className="grid gap-2 md:grid-cols-2">
            <div className="rounded-sm border border-red-500/20 bg-red-500/[0.03] p-2.5">
                <p className="mb-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                    Semula
                </p>
                <ArticleText
                    text={before}
                    changed={words?.removed}
                    tone="removed"
                />
            </div>
            <div className="rounded-sm border border-emerald-500/25 bg-emerald-500/[0.03] p-2.5">
                <p className="mb-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                    Menjadi
                </p>
                <ArticleText text={after} changed={words?.added} tone="added" />
            </div>
        </div>
    );
}

/** What the amendment says, for everyone who cannot edit it. */
function AmendmentSummary({ amendment }: { amendment: Amendment }) {
    const rows: Array<[string, string | null]> = [
        ['Tanggal Nota Dinas Usulan', amendment.memo_date],
        ['Tanggal Notulen Rapat', amendment.meeting_date],
        ['Tanggal Amandemen', amendment.amendment_date],
        ['Tanggal Kontrak', amendment.contract_date],
    ];

    return (
        <div className="space-y-3 text-sm">
            <dl className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">
                        Nomor Nota Dinas Usulan
                    </dt>
                    <dd className="tabular font-medium">
                        {amendment.memo_number ?? '—'}
                    </dd>
                </div>
                {rows.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-3">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="tabular font-medium">
                            {value ? formatDate(value) : '—'}
                        </dd>
                    </div>
                ))}
            </dl>
            {amendment.changes.length > 0 && (
                <ol className="list-decimal space-y-3 pl-5">
                    {amendment.changes.map((change, index) => (
                        <li key={index} className="space-y-1.5">
                            <p className="font-medium">{change.article}</p>
                            <ArticleDiff
                                before={change.original_text}
                                after={change.new_text}
                            />
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

/** The four papers, uploaded by the proposer while they may edit. */
function FileList({
    procurementId,
    amendment,
    titled = true,
}: {
    procurementId: number;
    amendment: Amendment;
    /** Off where the panel section already names the papers. */
    titled?: boolean;
}) {
    // Only what was filed, unless the papers are being filled in.
    const shown = amendment.files.filter(
        (entry) => amendment.can.update || entry.file,
    );

    if (shown.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            {titled && (
                <p className="text-sm font-semibold">Dokumen Permohonan</p>
            )}
            <ul className="divide-y divide-border rounded-sm border border-border">
                {shown.map((entry) => (
                    <FileRow
                        key={entry.kind}
                        procurementId={procurementId}
                        amendment={amendment}
                        entry={entry}
                    />
                ))}
            </ul>
        </div>
    );
}

function FileRow({
    procurementId,
    amendment,
    entry,
}: {
    procurementId: number;
    amendment: Amendment;
    entry: AmendmentFile;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | undefined>();

    return (
        <li className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
            <div className="min-w-0">
                <p className="font-medium">
                    {entry.label}
                    {entry.required && (
                        <span className="text-destructive"> *</span>
                    )}
                </p>
                {entry.file ? (
                    <a
                        href={entry.file.url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        <Paperclip className="size-3" />
                        {entry.file.name}
                    </a>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Belum diunggah
                    </p>
                )}
                <InputError message={error} />
            </div>
            {/* The attendance list is no longer asked for; one filed stays. */}
            {amendment.can.update && entry.kind !== 'daftar_hadir' && (
                <>
                    <input
                        ref={input}
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        className="sr-only"
                        onChange={(event) => {
                            const file = event.target.files?.[0];
                            event.target.value = '';

                            if (!file) {
                                return;
                            }

                            router.post(
                                procurements.amendments.upload({
                                    procurement: procurementId,
                                    amendment: amendment.id,
                                }).url,
                                { kind: entry.kind, file },
                                {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onStart: () => {
                                        setUploading(true);
                                        setError(undefined);
                                    },
                                    onError: (errors) =>
                                        setError(errors.file ?? errors.kind),
                                    onFinish: () => setUploading(false),
                                },
                            );
                        }}
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={uploading}
                        onClick={() => input.current?.click()}
                    >
                        <Upload className="size-3.5" />
                        {entry.file ? 'Ganti' : 'Unggah'}
                    </Button>
                </>
            )}
        </li>
    );
}

/** What still holds the step back, and the button that moves it on. */
function ActionBar({
    missing,
    label,
    url,
}: {
    missing: string[];
    label: string;
    url: string;
}) {
    const [sending, setSending] = useState(false);

    return (
        <div className="space-y-2 border-t border-border pt-3">
            {missing.length > 0 && (
                <div className="space-y-1 rounded-sm border border-border bg-muted/40 p-2.5 text-xs">
                    <p className="flex items-center gap-1.5 font-medium">
                        <CircleAlert className="size-3.5 text-amber-600" />
                        Lengkapi dulu (simpan isian terlebih dahulu):
                    </p>
                    <ul className="list-disc pl-4 text-muted-foreground">
                        {missing.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}
            <Button
                disabled={sending || missing.length > 0}
                onClick={() =>
                    router.post(
                        url,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setSending(true),
                            onFinish: () => setSending(false),
                        },
                    )
                }
            >
                <Send className="size-4" />
                {label}
            </Button>
        </div>
    );
}

/** The TL or Asman decision on the request. */
function DecisionForm({
    procurementId,
    amendment,
}: {
    procurementId: number;
    amendment: Amendment;
}) {
    const form = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    const decide = (approved: boolean) => {
        form.transform((data) => ({ ...data, approved }));
        form.post(
            procurements.amendments.decide({
                procurement: procurementId,
                amendment: amendment.id,
            }).url,
            { preserveScroll: true },
        );
    };

    return (
        <DecisionControls
            id={`amd-${amendment.id}-note`}
            note={form.data.note}
            onNoteChange={(note) => form.setData('note', note)}
            error={form.errors.note}
            processing={form.processing}
            onDecide={decide}
        />
    );
}
