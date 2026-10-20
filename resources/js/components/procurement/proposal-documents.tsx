import {
    Check,
    ChevronRight,
    Download,
    Eye,
    MoreHorizontal,
    Pencil,
    PartyPopper,
} from 'lucide-react';
import { ChecklistDocumentActions } from '@/components/procurement/checklist-panel';
import { StateBadge } from '@/components/procurement/checklist-steps';
import type { StepState } from '@/components/procurement/checklist-steps';
import {
    NextActionBanner,
    PanelSection,
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
import { Progress } from '@/components/ui/progress';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import type { ProposalDocument } from '@/types';

/** Where a proposal document stands: filed, started, or not yet begun. */
function stateOf(document: ProposalDocument): StepState {
    if (document.is_signed) {
        return 'done';
    }

    return document.id !== null || document.uploads.length > 0
        ? 'progress'
        : 'todo';
}

/** What the document asks for, in the author's words. */
function needOf(document: ProposalDocument): string {
    return document.upload_only
        ? 'Unggah berkas'
        : 'Generate dari template, lalu unggah yang bertanda tangan';
}

/** The one thing to do next on the document. */
function actionOf(document: ProposalDocument, canManage: boolean): string {
    if (document.is_signed || !canManage) {
        return 'Lihat';
    }

    if (document.upload_only) {
        return 'Unggah';
    }

    return document.id === null ? 'Generate' : 'Unggah TTD';
}

/**
 * The two documents a division files with its proposal (the TOR, drafted
 * from the template then uploaded signed, and the Nota Dinas Usulan) as a
 * table of steps, like the checklists: the one to do next named above it,
 * and each document's buttons and files in a side panel.
 */
export function ProposalDocuments({
    procurementId,
    proposer,
    documents,
    canManage,
}: {
    procurementId: number;
    proposer: string;
    documents: ProposalDocument[];
    canManage: boolean;
}) {
    const [openId, open] = useRememberedPanel(
        `proposal:${procurementId}`,
        documents.map((document) => document.type_id),
    );
    const filed = documents.filter((document) => document.is_signed).length;
    const nextIndex = documents.findIndex((document) => !document.is_signed);
    const next = nextIndex === -1 ? null : documents[nextIndex];
    const openIndex = documents.findIndex(
        (document) => document.type_id === openId,
    );
    const openDocument = openIndex === -1 ? null : documents[openIndex];

    return (
        <section className="space-y-3">
            {canManage && next !== null && (
                <NextActionBanner
                    badge={nextIndex + 1}
                    title={next.type_name ?? 'Dokumen usulan'}
                    detail={`Yang belum: ${next.type_name ?? 'dokumen'} ${next.upload_only ? 'diunggah' : 'bertanda tangan'}.`}
                    action={actionOf(next, canManage)}
                    onAction={() => open(next.type_id)}
                />
            )}

            {next === null && documents.length > 0 && (
                <div className="flex items-center gap-3 rounded-md border border-emerald-500/30 bg-emerald-500/5 px-4 py-3 text-sm">
                    <PartyPopper className="size-4 shrink-0 text-emerald-600" />
                    <span>
                        Kedua dokumen usulan sudah terunggah dan menggantikan
                        tahapan TOR serta Nota Dinas Usulan di checklist.
                    </span>
                </div>
            )}

            <div className="overflow-hidden rounded-md border border-border bg-card">
                <header className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs text-muted-foreground">
                        Dokumen usulan yang diunggah{' '}
                        <span className="font-medium text-foreground">
                            {proposer}
                        </span>
                        . Keduanya wajib terunggah sebelum perencanaan diajukan.
                        Klik sebuah dokumen untuk membuat, memeriksa, dan
                        mengunggahnya.
                    </p>
                    <div className="w-full space-y-1.5 sm:w-56">
                        <div className="tabular flex items-center justify-between text-xs">
                            <span className="font-semibold">
                                {documents.length > 0
                                    ? Math.round(
                                          (filed / documents.length) * 100,
                                      )
                                    : 0}
                                %
                            </span>
                            <span className="text-muted-foreground">
                                {filed}/{documents.length} dokumen terunggah
                            </span>
                        </div>
                        <Progress
                            value={
                                documents.length > 0
                                    ? (filed / documents.length) * 100
                                    : 0
                            }
                        />
                    </div>
                </header>

                <div
                    aria-hidden="true"
                    className="hidden grid-cols-[2.25rem_minmax(0,1.2fr)_minmax(0,1.6fr)_11rem_9.5rem] gap-3 border-b border-border bg-muted/40 px-4 py-2 text-[0.6875rem] font-semibold tracking-wide text-muted-foreground uppercase md:grid"
                >
                    <span>No</span>
                    <span>Dokumen</span>
                    <span>Yang dibutuhkan</span>
                    <span>Status</span>
                    <span className="text-right">Aksi</span>
                </div>

                <ol className="divide-y divide-border">
                    {documents.map((document, index) => {
                        const state = stateOf(document);
                        const isNext =
                            canManage && next?.type_id === document.type_id;

                        return (
                            <li
                                key={document.type_id}
                                onClick={(event) => {
                                    if (
                                        !(event.target as HTMLElement).closest(
                                            'button, a, [role="menuitem"]',
                                        )
                                    ) {
                                        open(document.type_id);
                                    }
                                }}
                                className={cn(
                                    'grid cursor-pointer grid-cols-[2.25rem_minmax(0,1fr)] gap-x-3 gap-y-2 px-4 py-3 transition-colors hover:bg-muted/40 md:grid-cols-[2.25rem_minmax(0,1.2fr)_minmax(0,1.6fr)_11rem_9.5rem] md:items-center',
                                    isNext &&
                                        'bg-primary/[0.03] shadow-[inset_3px_0_0_var(--primary)]',
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
                                    {state === 'done' ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        index + 1
                                    )}
                                </span>

                                <button
                                    type="button"
                                    onClick={() => open(document.type_id)}
                                    className="text-left text-sm font-medium hover:underline"
                                >
                                    {document.type_name}
                                </button>

                                <div className="col-start-2 min-w-0 text-xs md:col-start-auto">
                                    <p
                                        className={
                                            state === 'done'
                                                ? 'text-muted-foreground'
                                                : 'text-foreground'
                                        }
                                    >
                                        {needOf(document)}
                                    </p>
                                    {document.uploads.length > 0 && (
                                        <p className="truncate text-muted-foreground">
                                            {document.uploads.length} berkas ·{' '}
                                            {document.uploads[0].file_name}
                                        </p>
                                    )}
                                </div>

                                <div className="col-start-2 md:col-start-auto">
                                    <StateBadge state={state} />
                                </div>

                                <div className="col-start-2 flex items-center gap-1 md:col-start-auto md:justify-end">
                                    <Button
                                        size="sm"
                                        variant={
                                            state === 'done' || !canManage
                                                ? 'ghost'
                                                : isNext
                                                  ? 'default'
                                                  : 'outline'
                                        }
                                        onClick={() => open(document.type_id)}
                                    >
                                        {actionOf(document, canManage)}
                                        {(state === 'done' || !canManage) && (
                                            <ChevronRight className="size-3.5" />
                                        )}
                                    </Button>
                                    {document.id !== null && (
                                        <DocumentMenu
                                            procurementId={procurementId}
                                            document={document}
                                            documentId={document.id}
                                            canManage={canManage}
                                        />
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </div>

            <Sheet
                open={openDocument !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        open(null);
                    }
                }}
            >
                <SheetContent
                    className="w-full gap-0 sm:max-w-xl"
                    onPointerDownCapture={touchRememberedPanel}
                >
                    {openDocument !== null && (
                        <>
                            <SheetHeader className="border-b border-border pr-12">
                                <p className="section-label">
                                    Dokumen usulan {openIndex + 1} dari{' '}
                                    {documents.length}
                                </p>
                                <SheetTitle className="flex flex-wrap items-center gap-2 text-base">
                                    {openDocument.type_name}
                                    <StateBadge state={stateOf(openDocument)} />
                                </SheetTitle>
                                <SheetDescription>
                                    {needOf(openDocument)}.
                                </SheetDescription>
                            </SheetHeader>

                            <div className="flex-1 space-y-5 overflow-y-auto p-4">
                                <PanelSection
                                    number={1}
                                    title={
                                        openDocument.upload_only
                                            ? 'Unggah berkas'
                                            : 'Buat, periksa, lalu unggah yang bertanda tangan'
                                    }
                                    hint={
                                        openDocument.upload_only
                                            ? 'Bisa pilih beberapa berkas sekaligus.'
                                            : 'Generate dari template, periksa lewat Preview PDF atau Edit, cetak dan tanda tangani, lalu unggah hasilnya.'
                                    }
                                >
                                    <ChecklistDocumentActions
                                        procurementId={procurementId}
                                        document={openDocument}
                                        canManage={canManage}
                                    />
                                </PanelSection>
                            </div>

                            {openIndex < documents.length - 1 && (
                                <div className="flex justify-end border-t border-border p-4">
                                    <Button
                                        variant="ghost"
                                        onClick={() =>
                                            open(
                                                documents[openIndex + 1]
                                                    .type_id,
                                            )
                                        }
                                    >
                                        Dokumen berikutnya
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </section>
    );
}

/** Preview, download and edit a generated document from its row. */
function DocumentMenu({
    procurementId,
    document,
    documentId,
    canManage,
}: {
    procurementId: number;
    document: ProposalDocument;
    documentId: number;
    canManage: boolean;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    size="sm"
                    variant="ghost"
                    className="size-8 p-0"
                    aria-label={`Aksi lain untuk ${document.type_name ?? 'dokumen'}`}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
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
                        Pratinjau PDF
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
                        Unduh PDF
                    </a>
                </DropdownMenuItem>
                {canManage && (
                    <DropdownMenuItem asChild>
                        <a
                            href={
                                procurements.documents.edit({
                                    procurement: procurementId,
                                    document: documentId,
                                }).url
                            }
                        >
                            <Pencil className="size-4" />
                            Edit dokumen
                        </a>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
