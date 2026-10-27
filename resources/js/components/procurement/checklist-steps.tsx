import { router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    CheckCircle2,
    ChevronRight,
    Circle,
    CircleAlert,
    CircleDashed,
    CircleDot,
    Download,
    Eye,
    Lock,
    MoreHorizontal,
    PartyPopper,
    Send,
    Undo2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    ChecklistFileList,
    LetterTemplateLink,
} from '@/components/procurement/checklist-file-list';
import { ChecklistInputForm } from '@/components/procurement/checklist-input-form';
import { ChecklistDocumentActions } from '@/components/procurement/checklist-panel';
import { focusContractNumber } from '@/components/procurement/contract-number-field';
import {
    PanelSection,
    touchRememberedPanel,
    useRememberedPanel,
} from '@/components/procurement/request-board';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import type { ChecklistInput, ChecklistRow, PartnerCandidate } from '@/types';

/** What a step still asks for, in the author's words. */
type Requirement = {
    key: string;
    /** The thing itself: "Nomor RKS", "Dokumen RKS tervalidasi". */
    label: string;
    done: boolean;
    /** The action that fills it, short enough for a button. */
    action: string;
    /** The document's own name, for a step that asks for several. */
    name?: string;
};

/** Beyond this many documents a step's row sums them up instead of listing. */
const LISTED_DOCUMENTS = 3;

/** Beyond this many needs the side panel lists only what is missing. */
const LISTED_REQUIREMENTS = 6;

/** "A, B dan 5 lainnya": the missing needs, short enough to read at once. */
function describeMissing(missing: Requirement[]): string {
    const labels = missing.map((requirement) => requirement.label);

    return labels.length > LISTED_DOCUMENTS
        ? `${labels.slice(0, 2).join(', ')} dan ${labels.length - 2} lainnya`
        : labels.join(', ');
}

function isDocument(requirement: Requirement): boolean {
    return requirement.key.startsWith('document-');
}

/**
 * Where a step stands, read from its data rather than from a tick alone:
 * finished, ready to be marked finished, started but short of something,
 * not started, or optional.
 */
export type StepState = 'done' | 'ready' | 'progress' | 'todo' | 'optional';

/** The numbers short enough to type straight into the table. */
const INLINE_NUMBERS: Partial<
    Record<
        ChecklistInput['kind'],
        {
            field: 'rks_number' | 'hpe_number' | 'upb_number' | 'number';
            placeholder: string;
        }
    >
> = {
    rks_number: {
        field: 'rks_number',
        placeholder: 'Contoh: 017.RKS-PJ/612/UPKD/2026',
    },
    hpe_number: { field: 'hpe_number', placeholder: 'Nomor dokumen HPE' },
    upb_number: { field: 'upb_number', placeholder: 'Nomor dokumen UPB' },
};

/** "Rentang waktu (tanggal mulai + jumlah hari)" reads "Rentang waktu". */
function shortInputLabel(input: ChecklistInput): string {
    return INPUT_LABELS[input.kind] ?? input.label.replace(/\s*\(.*\)\s*$/, '');
}

/** What each kind of data reads as in the table, plainly. */
const INPUT_LABELS: Partial<Record<ChecklistInput['kind'], string>> = {
    contract_period: 'Tanggal mulai & jumlah hari',
    warranty: 'Masa garansi (bulan)',
    bank_account: 'Pelaksana & rekening bank',
    procurement_memo: 'Nomor & tanggal nota dinas',
    // Given in the identity of the procurement; the step checks it is there.
    contract_number: 'Nomor kontrak (di Identitas Pengadaan)',
};

export function requirementsOf(row: ChecklistRow): Requirement[] {
    const requirements: Requirement[] = [];

    if (row.input !== null) {
        const label =
            row.input.kind === 'partner_candidates'
                ? 'Calon mitra & penawaran'
                : shortInputLabel(row.input);

        requirements.push({
            key: 'input',
            label,
            done: row.input.is_filled,
            action:
                row.input.kind === 'partner_candidates'
                    ? 'Isi calon mitra'
                    : row.input.kind === 'contract_number'
                      ? 'Isi Nomor Kontrak'
                      : 'Isi data',
        });
    }

    if (row.needs_upload) {
        requirements.push({
            key: 'files',
            label: 'Dokumen tervalidasi diunggah',
            done: row.files.length > 0,
            action: 'Unggah Dokumen Tervalidasi',
        });
    }

    row.documents
        .filter((document) => !document.is_alternative)
        .forEach((document) => {
            const name = document.type_name ?? 'dokumen';

            requirements.push({
                key: `document-${document.type_id}`,
                label: `${name} tervalidasi`,
                done: document.is_signed,
                action:
                    !document.upload_only && document.id === null
                        ? 'Generate'
                        : 'Unggah Dokumen Tervalidasi',
                name,
            });
        });

    const alternatives = row.documents.filter(
        (document) => document.is_alternative,
    );

    if (alternatives.length > 0) {
        requirements.push({
            key: 'alternatives',
            label: `Salah satu: ${alternatives
                .map((document) => document.type_name ?? 'dokumen')
                .join(' / ')}`,
            done: alternatives.some((document) => document.is_signed),
            action: 'Unggah Dokumen Tervalidasi',
        });
    }

    return requirements;
}

export function stateOf(row: ChecklistRow): StepState {
    if (row.is_completed) {
        return 'done';
    }

    const requirements = requirementsOf(row);

    // An optional step that asks for nothing is only done when it is needed.
    if (row.is_optional && requirements.length === 0) {
        return 'optional';
    }

    if (requirements.every((requirement) => requirement.done)) {
        return 'ready';
    }

    if (row.is_optional) {
        return 'optional';
    }

    const started =
        requirements.some((requirement) => requirement.done) ||
        row.files.length > 0 ||
        row.documents.some(
            (document) => document.id !== null || document.uploads.length > 0,
        );

    return started ? 'progress' : 'todo';
}

const STATES: Record<
    StepState,
    { label: string; icon: ReactNode; className: string }
> = {
    done: {
        label: 'Selesai',
        icon: <CheckCircle2 className="size-3.5" />,
        className:
            'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 dark:text-emerald-400',
    },
    ready: {
        label: 'Siap ditandai selesai',
        icon: <CircleDot className="size-3.5" />,
        className: 'bg-primary/10 text-primary ring-primary/25',
    },
    progress: {
        label: 'Perlu dilengkapi',
        icon: <CircleAlert className="size-3.5" />,
        className:
            'bg-amber-500/10 text-amber-700 ring-amber-500/25 dark:text-amber-400',
    },
    todo: {
        label: 'Belum mulai',
        icon: <Circle className="size-3.5" />,
        className: 'bg-muted text-muted-foreground ring-border',
    },
    optional: {
        label: 'Opsional',
        icon: <CircleDashed className="size-3.5" />,
        className:
            'bg-transparent text-muted-foreground ring-border ring-dashed',
    },
};

export function StateBadge({ state }: { state: StepState }) {
    const { label, icon, className } = STATES[state];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
                className,
            )}
        >
            {icon}
            {label}
        </span>
    );
}

/**
 * A stage's checklist as a table of steps, one row each: what the step asks
 * for, where it stands, and the one thing to do next. The step's own forms,
 * uploads and documents open in a side panel, and a banner above the table
 * names the first step still to do.
 */
export function ChecklistSteps({
    procurementId,
    description,
    rows,
    editable,
    canManageDocuments,
    lockedReason,
    doneMessage,
    submit,
    partnerCandidates = [],
    candidatesEditable = false,
    directAppointment = false,
}: {
    procurementId: number;
    description?: string;
    rows: ChecklistRow[];
    editable: boolean;
    /** Generating, editing and filing documents follows the document policy. */
    canManageDocuments: boolean;
    lockedReason?: string;
    /** Shown once every required step is finished. */
    doneMessage: string;
    /** The submission the finished stage goes on to, for whoever may send it. */
    submit?: { label: string; onSubmit: () => void };
    partnerCandidates?: PartnerCandidate[];
    candidatesEditable?: boolean;
    directAppointment?: boolean;
}) {
    // The step whose details are open, kept across the reload every save
    // brings, so the panel is still there when the save comes back.
    const [openId, open] = useRememberedPanel(
        `checklist:${procurementId}`,
        rows.map((row) => row.id),
    );
    const [error, setError] = useState<string | null>(null);

    const toggle = (row: ChecklistRow, next: boolean) => {
        setError(null);
        router.put(
            procurements.checklists.update({
                procurement: procurementId,
                checklist: row.id,
            }).url,
            { is_completed: next, notes: row.notes ?? '' },
            {
                preserveScroll: true,
                // Keep the page (and this message) when the step is refused.
                preserveState: 'errors',
                onError: (errors) =>
                    setError(
                        errors.is_completed ??
                            Object.values(errors)[0] ??
                            'Langkah ini belum bisa ditandai selesai.',
                    ),
            },
        );
    };

    const required = rows.filter((row) => !row.is_optional);
    const requiredDone = required.filter((row) => row.is_completed).length;
    const completed = rows.filter((row) => row.is_completed).length;
    const percentage =
        rows.length > 0 ? Math.round((completed / rows.length) * 100) : 0;

    const nextIndex = rows.findIndex(
        (row) => !row.is_completed && !row.is_optional,
    );
    const next = nextIndex === -1 ? null : rows[nextIndex];
    const openIndex = rows.findIndex((row) => row.id === openId);
    const openRow = openIndex === -1 ? null : rows[openIndex];

    return (
        <section className="space-y-3">
            {editable && next !== null && (
                <NextStepBanner
                    number={nextIndex + 1}
                    row={next}
                    canManageDocuments={canManageDocuments}
                    onOpen={() => open(next.id)}
                    onComplete={() => toggle(next, true)}
                />
            )}

            {editable && next === null && rows.length > 0 && (
                <div className="flex flex-col gap-3 rounded-md border border-emerald-500/30 bg-emerald-500/5 px-4 py-3 text-sm sm:flex-row sm:items-center">
                    <PartyPopper className="size-4 shrink-0 text-emerald-600" />
                    <span className="flex-1">{doneMessage}</span>
                    {submit && (
                        <Button onClick={submit.onSubmit} className="shrink-0">
                            <Send className="size-4" />
                            {submit.label}
                        </Button>
                    )}
                </div>
            )}

            {error !== null && (
                <div
                    role="alert"
                    className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                >
                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                    <span className="flex-1">{error}</span>
                    <button
                        type="button"
                        onClick={() => setError(null)}
                        aria-label="Tutup pesan"
                        className="rounded p-0.5 hover:bg-destructive/10"
                    >
                        <X className="size-3.5" />
                    </button>
                </div>
            )}

            <div className="@container overflow-hidden rounded-md border border-border bg-card">
                <header className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                    {description && (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}

                    <div className="w-full space-y-1.5 sm:w-56">
                        <div className="tabular flex items-center justify-between text-xs">
                            <span className="font-semibold">{percentage}%</span>
                            <span className="text-muted-foreground">
                                {requiredDone}/{required.length} langkah wajib
                                selesai
                            </span>
                        </div>
                        <Progress value={percentage} />
                    </div>
                </header>

                {!editable && lockedReason && (
                    <p className="flex items-center gap-2 border-b border-border bg-muted/40 px-4 py-2 text-xs text-muted-foreground">
                        <Lock className="size-3.5" />
                        {lockedReason}
                    </p>
                )}

                {/* The column heads, once the card is wide enough for a table (a tablet beside the side panel is not). */}
                <div
                    aria-hidden="true"
                    className="hidden grid-cols-[2.25rem_minmax(0,1.3fr)_minmax(0,1.5fr)_10rem_16.5rem] gap-3 border-b border-border bg-muted/40 px-4 py-2 text-[0.6875rem] font-semibold tracking-wide text-muted-foreground uppercase @4xl:grid"
                >
                    <span>No</span>
                    <span>Langkah</span>
                    <span>Yang dibutuhkan</span>
                    <span>Status</span>
                    <span className="text-right">Aksi</span>
                </div>

                <ol className="divide-y divide-border">
                    {rows.map((row, index) => (
                        <StepRow
                            key={row.id}
                            procurementId={procurementId}
                            number={index + 1}
                            row={row}
                            isNext={editable && next?.id === row.id}
                            editable={editable}
                            canManageDocuments={canManageDocuments}
                            onOpen={() => open(row.id)}
                            onToggle={(value) => toggle(row, value)}
                        />
                    ))}
                </ol>
            </div>

            <Sheet
                open={openRow !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        open(null);
                    }
                }}
            >
                <SheetContent
                    className="w-full gap-0 sm:max-w-xl lg:max-w-2xl"
                    onPointerDownCapture={touchRememberedPanel}
                >
                    {openRow !== null && (
                        <StepDetails
                            procurementId={procurementId}
                            number={openIndex + 1}
                            total={rows.length}
                            row={openRow}
                            editable={editable}
                            canManageDocuments={canManageDocuments}
                            lockedReason={lockedReason}
                            partnerCandidates={partnerCandidates}
                            candidatesEditable={candidatesEditable}
                            directAppointment={directAppointment}
                            error={error}
                            onToggle={(value) => toggle(openRow, value)}
                            onNext={
                                openIndex < rows.length - 1
                                    ? () => open(rows[openIndex + 1].id)
                                    : undefined
                            }
                        />
                    )}
                </SheetContent>
            </Sheet>
        </section>
    );
}

/** The first required step still to do, with the one action that moves it. */
function NextStepBanner({
    number,
    row,
    canManageDocuments,
    onOpen,
    onComplete,
}: {
    number: number;
    row: ChecklistRow;
    canManageDocuments: boolean;
    onOpen: () => void;
    onComplete: () => void;
}) {
    const state = stateOf(row);
    const missing = requirementsOf(row).filter(
        (requirement) => !requirement.done,
    );

    return (
        <div className="flex flex-col gap-3 rounded-md border border-primary/30 bg-primary/5 px-4 py-3 sm:flex-row sm:items-center">
            <span className="tabular flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                {number}
            </span>
            <div className="min-w-0 flex-1">
                <p className="section-label text-primary">Langkah berikutnya</p>
                <p className="text-sm font-semibold text-foreground">
                    {row.name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {state === 'ready'
                        ? 'Semua kebutuhan sudah lengkap. Tandai langkah ini selesai.'
                        : missing.length > 0
                          ? `Yang belum: ${describeMissing(missing)}.`
                          : (row.description ??
                            'Tandai selesai bila sudah dikerjakan.')}
                </p>
            </div>
            {state === 'ready' ? (
                <Button onClick={onComplete} className="shrink-0">
                    <Check className="size-4" />
                    Tandai selesai
                </Button>
            ) : (
                <Button
                    onClick={
                        awaitsContractNumber(row) ? focusContractNumber : onOpen
                    }
                    className="shrink-0"
                >
                    {primaryLabel(row, canManageDocuments)}
                    <ArrowRight className="size-4" />
                </Button>
            )}
        </div>
    );
}

/**
 * Whether the step's next requirement is the contract number, given in the
 * identity of the procurement rather than in the step.
 */
function awaitsContractNumber(row: ChecklistRow): boolean {
    const missing = requirementsOf(row).find(
        (requirement) => !requirement.done,
    );

    return missing?.key === 'input' && row.input?.kind === 'contract_number';
}

/** The label of a step's main button while it is not finished. */
function primaryLabel(
    row: ChecklistRow,
    canManageDocuments: boolean,
    skipInlineInput = false,
): string {
    const missing = requirementsOf(row).find(
        (requirement) =>
            !requirement.done &&
            !(skipInlineInput && requirement.key === 'input'),
    );

    if (missing === undefined) {
        return 'Buka';
    }

    if (missing.key.startsWith('document') || missing.key === 'alternatives') {
        return canManageDocuments ? missing.action : 'Lihat';
    }

    return missing.action;
}

function StepRow({
    procurementId,
    number,
    row,
    isNext,
    editable,
    canManageDocuments,
    onOpen,
    onToggle,
}: {
    procurementId: number;
    number: number;
    row: ChecklistRow;
    isNext: boolean;
    editable: boolean;
    canManageDocuments: boolean;
    onOpen: () => void;
    onToggle: (value: boolean) => void;
}) {
    const state = stateOf(row);
    const requirements = requirementsOf(row);
    const inline =
        row.input !== null && editable ? INLINE_NUMBERS[row.input.kind] : null;
    const generated = row.documents.filter((document) => document.id !== null);
    const documentRequirements = requirements.filter(isDocument);
    const groupDocuments = documentRequirements.length > LISTED_DOCUMENTS;

    return (
        <li
            onClick={(event) => {
                // The row opens the step, except where a control was used.
                if (
                    !(event.target as HTMLElement).closest(
                        'button, a, input, label, [role="menuitem"]',
                    )
                ) {
                    onOpen();
                }
            }}
            className={cn(
                'grid cursor-pointer grid-cols-[2.25rem_minmax(0,1fr)] gap-x-3 gap-y-2 px-4 py-3 transition-colors hover:bg-muted/40 @4xl:grid-cols-[2.25rem_minmax(0,1.3fr)_minmax(0,1.5fr)_10rem_16.5rem] @4xl:items-center',
                isNext &&
                    'bg-primary/[0.03] shadow-[inset_3px_0_0_var(--primary)]',
                state === 'done' && 'text-muted-foreground',
            )}
        >
            <span
                className={cn(
                    'tabular flex size-7 items-center justify-center rounded-full text-xs font-semibold',
                    state === 'done'
                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                        : isNext
                          ? 'bg-primary text-primary-foreground'
                          : 'bg-muted text-muted-foreground',
                )}
            >
                {state === 'done' ? <Check className="size-3.5" /> : number}
            </span>

            <div className="min-w-0">
                <button
                    type="button"
                    onClick={onOpen}
                    className={cn(
                        'text-left text-sm font-medium hover:underline',
                        state === 'done'
                            ? 'text-foreground/80'
                            : 'text-foreground',
                    )}
                >
                    {row.name}
                </button>
                {row.is_optional && (
                    <span className="ml-2 rounded-sm bg-muted px-1.5 py-0.5 text-[0.6875rem] text-muted-foreground">
                        Opsional
                    </span>
                )}
                {row.description && (
                    <p className="line-clamp-2 text-xs text-muted-foreground">
                        {row.description}
                    </p>
                )}
            </div>

            {/* Below the name on a phone; its own columns on a wide screen. */}
            <div className="col-start-2 min-w-0 @4xl:col-start-auto">
                {requirements.length === 0 ? (
                    <span className="text-xs text-muted-foreground">
                        Cukup ditandai selesai
                    </span>
                ) : (
                    <ul className="flex flex-col gap-1">
                        {(groupDocuments
                            ? requirements.filter(
                                  (requirement) => !isDocument(requirement),
                              )
                            : requirements
                        ).map((requirement) =>
                            requirement.key === 'input' &&
                            inline !== null &&
                            inline !== undefined &&
                            row.input !== null ? (
                                <li key={requirement.key}>
                                    <InlineNumber
                                        procurementId={procurementId}
                                        checklistId={row.id}
                                        label={requirement.label}
                                        field={inline.field}
                                        placeholder={inline.placeholder}
                                        value={
                                            row.input.values[inline.field] ?? ''
                                        }
                                    />
                                </li>
                            ) : (
                                <li
                                    key={requirement.key}
                                    className="flex items-start gap-1.5 text-xs"
                                >
                                    {requirement.done ? (
                                        <CheckCircle2 className="mt-px size-3.5 shrink-0 text-emerald-600" />
                                    ) : (
                                        <Circle className="mt-px size-3.5 shrink-0 text-muted-foreground/60" />
                                    )}
                                    <span
                                        className={
                                            requirement.done
                                                ? 'text-muted-foreground'
                                                : 'text-foreground'
                                        }
                                    >
                                        {requirement.label}
                                    </span>
                                </li>
                            ),
                        )}
                        {groupDocuments && (
                            <DocumentsSummary
                                requirements={documentRequirements}
                                finished={state === 'done'}
                            />
                        )}
                    </ul>
                )}
            </div>

            <div className="col-start-2 @4xl:col-start-auto">
                <StateBadge state={state} />
                {state === 'done' && row.completed_by && (
                    <p className="mt-1 truncate text-[11px] text-muted-foreground">
                        {row.completed_by} · {formatDateTime(row.completed_at)}
                    </p>
                )}
            </div>

            <div className="col-start-2 flex items-center gap-1 @4xl:col-start-auto @4xl:justify-end">
                {state === 'ready' && editable ? (
                    <Button size="sm" onClick={() => onToggle(true)}>
                        <Check className="size-3.5" />
                        Tandai selesai
                    </Button>
                ) : state === 'done' || !editable ? (
                    <Button size="sm" variant="ghost" onClick={onOpen}>
                        Lihat
                        <ChevronRight className="size-3.5" />
                    </Button>
                ) : (
                    <Button
                        size="sm"
                        variant={isNext ? 'default' : 'outline'}
                        // A long label wraps inside the column rather than
                        // spilling over the status beside it.
                        className="h-auto min-h-8 min-w-0 py-1.5 whitespace-normal"
                        onClick={
                            state === 'optional' && requirements.length === 0
                                ? () => onToggle(true)
                                : editable && awaitsContractNumber(row)
                                  ? focusContractNumber
                                  : onOpen
                        }
                    >
                        {state === 'optional' && requirements.length === 0
                            ? 'Tandai selesai'
                            : primaryLabel(
                                  row,
                                  canManageDocuments,
                                  // The number is typed in the row itself.
                                  Boolean(inline),
                              )}
                    </Button>
                )}

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            size="sm"
                            variant="ghost"
                            className="size-8 p-0"
                            aria-label={`Aksi lain untuk ${row.name}`}
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-52">
                        <DropdownMenuItem onSelect={onOpen}>
                            <ChevronRight className="size-4" />
                            Buka detail langkah
                        </DropdownMenuItem>
                        {row.template_url && (
                            <DropdownMenuItem asChild>
                                <a href={row.template_url}>
                                    <Download className="size-4" />
                                    Unduh template surat (Word)
                                </a>
                            </DropdownMenuItem>
                        )}
                        {generated.map((document) => (
                            <DocumentLinks
                                key={document.type_id}
                                procurementId={procurementId}
                                documentId={document.id as number}
                                name={document.type_name ?? 'dokumen'}
                            />
                        ))}
                        {editable &&
                            state === 'optional' &&
                            requirements.length > 0 && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onSelect={() => onToggle(true)}
                                    >
                                        <Check className="size-4" />
                                        Tandai selesai
                                    </DropdownMenuItem>
                                </>
                            )}
                        {editable && state === 'done' && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => onToggle(false)}
                                >
                                    <Undo2 className="size-4" />
                                    Tandai belum selesai
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </li>
    );
}

/** Many documents in one row: how many are in, and the next one to do. */
function DocumentsSummary({
    requirements,
    finished,
}: {
    requirements: Requirement[];
    /** A finished step points to nothing further. */
    finished: boolean;
}) {
    const done = requirements.filter((requirement) => requirement.done).length;
    const next = requirements.find((requirement) => !requirement.done);
    const percentage = Math.round((done / requirements.length) * 100);

    return (
        <li className="space-y-1 text-xs">
            <span className="flex items-center gap-1.5">
                {next === undefined ? (
                    <CheckCircle2 className="size-3.5 shrink-0 text-emerald-600" />
                ) : (
                    <Circle className="size-3.5 shrink-0 text-muted-foreground/60" />
                )}
                <span className="tabular">
                    {done} dari {requirements.length} dokumen lengkap
                </span>
            </span>
            <span className="ml-5 block h-1 max-w-40 overflow-hidden rounded-full bg-muted">
                <span
                    className="block h-full rounded-full bg-emerald-500"
                    style={{ width: `${percentage}%` }}
                />
            </span>
            {next !== undefined && !finished && (
                <span className="ml-5 block text-muted-foreground">
                    Berikutnya: {next.name ?? next.label}
                </span>
            )}
        </li>
    );
}

function DocumentLinks({
    procurementId,
    documentId,
    name,
}: {
    procurementId: number;
    documentId: number;
    name: string;
}) {
    return (
        <>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <a
                    href={
                        procurements.documents.show(
                            {
                                procurement: procurementId,
                                document: documentId,
                            },
                            { query: { preview: '1' } },
                        ).url
                    }
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <Eye className="size-4" />
                    Pratinjau {name}
                </a>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
                <a
                    href={
                        procurements.documents.show({
                            procurement: procurementId,
                            document: documentId,
                        }).url
                    }
                >
                    <Download className="size-4" />
                    Unduh {name}
                </a>
            </DropdownMenuItem>
        </>
    );
}

/** A document number typed straight into the table; Enter saves it. */
function InlineNumber({
    procurementId,
    checklistId,
    label,
    field,
    placeholder,
    value,
}: {
    procurementId: number;
    checklistId: number;
    label: string;
    field: 'rks_number' | 'hpe_number' | 'upb_number' | 'number';
    placeholder: string;
    value: string;
}) {
    const form = useForm({ [field]: value } as Record<string, string>);
    const id = `inline-${field}-${checklistId}`;

    const save = () =>
        form.put(
            procurements.checklists.input({
                procurement: procurementId,
                checklist: checklistId,
            }).url,
            { preserveScroll: true },
        );

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                save();
            }}
            className="grid gap-1"
        >
            <label
                htmlFor={id}
                className="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                {value !== '' ? (
                    <CheckCircle2 className="size-3.5 text-emerald-600" />
                ) : (
                    <Circle className="size-3.5 text-muted-foreground/60" />
                )}
                {label}
            </label>
            <div className="flex items-center gap-1">
                <Input
                    id={id}
                    value={form.data[field] ?? ''}
                    onChange={(event) =>
                        form.setData(field, event.target.value)
                    }
                    placeholder={placeholder}
                    autoComplete="off"
                    disabled={form.processing}
                    aria-invalid={form.errors[field] ? true : undefined}
                    className="tabular h-8 text-xs"
                />
                {form.isDirty && (
                    <Button
                        type="submit"
                        size="sm"
                        className="h-8 shrink-0 px-2"
                        disabled={form.processing}
                        aria-label={`Simpan ${label}`}
                    >
                        <Check className="size-3.5" />
                    </Button>
                )}
            </div>
            {form.errors[field] && (
                <p className="text-xs text-destructive">{form.errors[field]}</p>
            )}
            {form.isDirty && !form.errors[field] && (
                <p className="text-[11px] text-muted-foreground">
                    Tekan Enter atau ✓ untuk menyimpan.
                </p>
            )}
        </form>
    );
}

/** Everything a step asks for, in the order it is done, in a side panel. */
function StepDetails({
    procurementId,
    number,
    total,
    row,
    editable,
    canManageDocuments,
    lockedReason,
    partnerCandidates,
    candidatesEditable,
    directAppointment,
    error,
    onToggle,
    onNext,
}: {
    procurementId: number;
    number: number;
    total: number;
    row: ChecklistRow;
    editable: boolean;
    canManageDocuments: boolean;
    /** Why the step cannot be worked on here, shown in place of the upload. */
    lockedReason?: string;
    partnerCandidates: PartnerCandidate[];
    candidatesEditable: boolean;
    directAppointment: boolean;
    error: string | null;
    onToggle: (value: boolean) => void;
    onNext?: () => void;
}) {
    const state = stateOf(row);
    const requirements = requirementsOf(row);
    const missing = requirements.filter((requirement) => !requirement.done);
    const canComplete = missing.length === 0 || row.is_optional;
    let section = 0;

    return (
        <>
            <SheetHeader className="border-b border-border pr-12">
                <p className="section-label">
                    Langkah {number} dari {total}
                </p>
                <SheetTitle className="flex flex-wrap items-center gap-2 text-base">
                    {row.name}
                    <StateBadge state={state} />
                </SheetTitle>
                <SheetDescription>
                    {row.description ??
                        'Lengkapi kebutuhan langkah ini, lalu tandai selesai.'}
                </SheetDescription>
            </SheetHeader>

            <div className="flex-1 space-y-5 overflow-y-auto p-4">
                {/* Read-only here: say why, so a missing upload is not a puzzle. */}
                {!editable && lockedReason && (
                    <p className="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/5 p-3 text-xs text-foreground">
                        <Lock className="mt-0.5 size-3.5 shrink-0 text-amber-600" />
                        <span>
                            <span className="font-semibold">
                                Tidak dapat mengunggah di sini.{' '}
                            </span>
                            {lockedReason}
                        </span>
                    </p>
                )}
                {requirements.length > 0 && (
                    <div className="rounded-md border border-border bg-muted/30 p-3">
                        <p className="mb-2 text-xs font-semibold">
                            Yang dibutuhkan
                            {requirements.length > LISTED_REQUIREMENTS && (
                                <span className="tabular ml-1 font-normal text-muted-foreground">
                                    · {requirements.length - missing.length}{' '}
                                    dari {requirements.length} sudah lengkap
                                    {missing.length > 0 && ', yang belum:'}
                                </span>
                            )}
                        </p>
                        <ul className="space-y-1">
                            {(requirements.length > LISTED_REQUIREMENTS
                                ? missing
                                : requirements
                            ).map((requirement) => (
                                <li
                                    key={requirement.key}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    {requirement.done ? (
                                        <CheckCircle2 className="size-4 shrink-0 text-emerald-600" />
                                    ) : (
                                        <Circle className="size-4 shrink-0 text-muted-foreground/60" />
                                    )}
                                    <span
                                        className={
                                            requirement.done
                                                ? 'text-muted-foreground line-through decoration-muted-foreground/40'
                                                : ''
                                        }
                                    >
                                        {requirement.label}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {row.input !== null && (
                    <PanelSection number={++section} title="Isi data">
                        <ChecklistInputForm
                            procurementId={procurementId}
                            checklistId={row.id}
                            input={row.input}
                            canManage={editable}
                            partnerCandidates={partnerCandidates}
                            candidatesEditable={candidatesEditable}
                            directAppointment={directAppointment}
                        />
                    </PanelSection>
                )}

                {row.needs_upload && (
                    <PanelSection
                        number={++section}
                        title="Unggah dokumen tervalidasi"
                    >
                        <ChecklistFileList
                            procurementId={procurementId}
                            checklistId={row.id}
                            name={row.name}
                            files={row.files}
                            templateUrl={row.template_url}
                            canManage={editable}
                        />
                    </PanelSection>
                )}

                {!row.needs_upload && row.template_url && (
                    <PanelSection number={++section} title="Template surat">
                        <LetterTemplateLink url={row.template_url} />
                    </PanelSection>
                )}

                {row.documents.some((document) => !document.is_alternative) && (
                    <PanelSection
                        number={++section}
                        title="Dokumen"
                        hint="Generate dokumen, periksa, lalu unggah salinan yang sudah ditandatangani."
                    >
                        <div className="space-y-2">
                            {row.documents
                                .filter((document) => !document.is_alternative)
                                .map((document) => (
                                    <ChecklistDocumentActions
                                        key={document.type_id}
                                        procurementId={procurementId}
                                        document={document}
                                        canManage={canManageDocuments}
                                    />
                                ))}
                        </div>
                    </PanelSection>
                )}

                {row.documents.some((document) => document.is_alternative) && (
                    <PanelSection
                        number={++section}
                        title="Pilih salah satu dokumen"
                        hint="Cukup satu dokumen berikut yang diunggah."
                    >
                        <div className="space-y-2">
                            {row.documents
                                .filter((document) => document.is_alternative)
                                .map((document) => (
                                    <ChecklistDocumentActions
                                        key={document.type_id}
                                        procurementId={procurementId}
                                        document={document}
                                        canManage={canManageDocuments}
                                    />
                                ))}
                        </div>
                    </PanelSection>
                )}

                {state === 'done' && row.completed_by && (
                    <p className="text-xs text-muted-foreground">
                        Ditandai selesai oleh {row.completed_by} ·{' '}
                        {formatDateTime(row.completed_at)}
                    </p>
                )}
            </div>

            <SheetFooter className="gap-2 border-t border-border">
                {error !== null && (
                    <p role="alert" className="text-xs text-destructive">
                        {error}
                    </p>
                )}
                {editable && !canComplete && state !== 'done' && (
                    <p className="text-xs text-muted-foreground">
                        Lengkapi dulu: {describeMissing(missing)}.
                    </p>
                )}
                <div className="flex flex-wrap items-center justify-between gap-2">
                    {editable &&
                        (state === 'done' ? (
                            <Button
                                variant="outline"
                                onClick={() => onToggle(false)}
                            >
                                <Undo2 className="size-4" />
                                Tandai belum selesai
                            </Button>
                        ) : (
                            <Button
                                onClick={() => onToggle(true)}
                                disabled={!canComplete}
                            >
                                <Check className="size-4" />
                                Tandai selesai
                            </Button>
                        ))}
                    {onNext && (
                        <Button
                            variant="ghost"
                            onClick={onNext}
                            className="ml-auto"
                        >
                            Langkah berikutnya
                            <ArrowRight className="size-4" />
                        </Button>
                    )}
                </div>
            </SheetFooter>
        </>
    );
}
