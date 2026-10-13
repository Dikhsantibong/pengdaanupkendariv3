import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Download,
    ExternalLink,
    FileText,
    Paperclip,
    Pencil,
    RotateCcw,
    Send,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { StateBadge } from '@/pages/proposals/index';
import type { ProposalRow } from '@/pages/proposals/index';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import proposals from '@/routes/proposals';

type Attachment = {
    id: number;
    file_name: string;
    size: number | null;
    previewable: boolean;
    is_image: boolean;
    uploaded_by: string | null;
    url: string;
};

type HistoryItem = {
    id: number;
    label: string;
    description: string;
    user: string | null;
    at: string | null;
};

type Proposal = ProposalRow & {
    procurement_method: string | null;
    budget_source: string | null;
    prk_number: string | null;
    proposal_memo_number: string | null;
    proposal_memo_date: string | null;
    notes: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    review_note: string | null;
    attachments: Attachment[];
    history: HistoryItem[];
};

function formatSize(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    return bytes > 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Large in-page reader for the supporting files. */
function DocumentViewer({
    attachments,
    canRemove,
    proposalId,
}: {
    attachments: Attachment[];
    canRemove: boolean;
    proposalId: number;
}) {
    const [activeId, setActiveId] = useState(attachments[0]?.id ?? null);
    const active =
        attachments.find((file) => file.id === activeId) ?? attachments[0];

    if (active === undefined) {
        return (
            <div className="flex h-full min-h-[50vh] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-card p-8 text-center">
                <Paperclip className="size-8 text-muted-foreground" />
                <p className="font-medium">Tidak ada lampiran</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    Usulan ini diajukan tanpa dokumen pendukung. Tinjau data
                    usulan di panel samping.
                </p>
            </div>
        );
    }

    return (
        <div className="flex h-full min-h-0 flex-col overflow-hidden rounded-lg border border-border bg-card">
            <div className="flex gap-1 overflow-x-auto border-b border-border bg-muted/40 p-1.5">
                {attachments.map((file) => (
                    <button
                        key={file.id}
                        type="button"
                        onClick={() => setActiveId(file.id)}
                        className={cn(
                            'flex max-w-[16rem] shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors',
                            file.id === active.id
                                ? 'bg-background font-medium text-foreground shadow-xs'
                                : 'text-muted-foreground hover:bg-background/60',
                        )}
                    >
                        <FileText className="size-4 shrink-0" />
                        <span className="truncate">{file.file_name}</span>
                    </button>
                ))}
            </div>

            <div className="flex items-center justify-between gap-3 border-b border-border px-3 py-2 text-xs text-muted-foreground">
                <span className="truncate">
                    {formatSize(active.size)}
                    {active.uploaded_by
                        ? ` · diunggah ${active.uploaded_by}`
                        : ''}
                </span>
                <div className="flex shrink-0 items-center gap-1">
                    <Button asChild size="sm" variant="ghost">
                        <a href={active.url} target="_blank" rel="noreferrer">
                            <ExternalLink className="size-3.5" />
                            Buka
                        </a>
                    </Button>
                    {canRemove && (
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-destructive hover:text-destructive"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        `Hapus lampiran ${active.file_name}?`,
                                    )
                                ) {
                                    router.delete(
                                        proposals.attachments.destroy({
                                            procurement: proposalId,
                                            attachment: active.id,
                                        }).url,
                                        { preserveScroll: true },
                                    );
                                }
                            }}
                        >
                            <Trash2 className="size-3.5" />
                            Hapus
                        </Button>
                    )}
                </div>
            </div>

            <div className="min-h-0 flex-1 bg-muted/30">
                {active.is_image ? (
                    <div className="flex h-full items-center justify-center overflow-auto p-4">
                        <img
                            src={active.url}
                            alt={active.file_name}
                            className="max-h-full max-w-full rounded shadow-sm"
                        />
                    </div>
                ) : active.previewable ? (
                    <iframe
                        key={active.id}
                        src={`${active.url}#view=FitH`}
                        title={active.file_name}
                        className="h-full w-full bg-white"
                    />
                ) : (
                    <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
                        <FileText className="size-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Berkas ini tidak dapat ditampilkan di browser.
                        </p>
                        <Button asChild variant="outline">
                            <a href={active.url}>
                                <Download className="size-4" />
                                Unduh {active.file_name}
                            </a>
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}

/** Approve, or return with a reason. */
function DecisionPanel({ proposalId }: { proposalId: number }) {
    const [returning, setReturning] = useState(false);
    const form = useForm<{ decision: 'approve' | 'return'; note: string }>({
        decision: 'approve',
        note: '',
    });

    const submit = (decision: 'approve' | 'return') => {
        form.transform((data) => ({ ...data, decision }));
        form.post(proposals.decide(proposalId).url);
    };

    return (
        <section className="space-y-3 rounded-lg border-2 border-primary/30 bg-primary/5 p-4">
            <p className="text-sm font-semibold text-foreground">
                Keputusan Anda
            </p>

            {!returning ? (
                <div className="grid gap-2">
                    <Button
                        size="lg"
                        className="h-12 bg-emerald-600 text-base hover:bg-emerald-700"
                        disabled={form.processing}
                        onClick={() => submit('approve')}
                    >
                        <CheckCircle2 className="size-5" />
                        Setujui Usulan
                    </Button>
                    <Button
                        size="lg"
                        variant="outline"
                        className="h-11"
                        disabled={form.processing}
                        onClick={() => setReturning(true)}
                    >
                        <RotateCcw className="size-4" />
                        Kembalikan ke Pengusul
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        Usulan yang disetujui langsung diteruskan ke perencanaan
                        pengadaan.
                    </p>
                </div>
            ) : (
                <div className="grid gap-2">
                    <label htmlFor="note" className="text-sm font-medium">
                        Apa yang perlu diperbaiki?
                    </label>
                    <Textarea
                        id="note"
                        rows={4}
                        autoFocus
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                        placeholder="Contoh: lengkapi spesifikasi teknis dan lampirkan foto kondisi peralatan."
                    />
                    <InputError message={form.errors.note} />
                    <div className="flex gap-2">
                        <Button
                            className="flex-1"
                            variant="destructive"
                            disabled={form.processing}
                            onClick={() => submit('return')}
                        >
                            Kembalikan
                        </Button>
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setReturning(false);
                                form.clearErrors();
                            }}
                        >
                            Batal
                        </Button>
                    </div>
                </div>
            )}
        </section>
    );
}

export default function ProposalShow({
    proposal,
    can,
}: {
    proposal: Proposal;
    can: { approve: boolean; revise: boolean; openProcurement: boolean };
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const upload = useForm<{ attachments: File[] }>({ attachments: [] });

    const details: Array<[string, string | null]> = [
        ['Bidang / Direksi Pekerjaan', proposal.division],
        [
            'Pengusul',
            [proposal.proposer, proposal.proposer_position]
                .filter(Boolean)
                .join(' · ') || null,
        ],
        ['Unit Tujuan', proposal.target_units || null],
        ['Perkiraan Nilai', formatCurrency(proposal.hpe_value)],
        ['Metode Pengadaan', proposal.procurement_method],
        ['Sumber Anggaran', proposal.budget_source],
        ['Nomor PRK', proposal.prk_number],
        [
            'Nota Dinas Usulan',
            proposal.proposal_memo_number
                ? `${proposal.proposal_memo_number}${proposal.proposal_memo_date ? ` · ${formatDate(proposal.proposal_memo_date)}` : ''}`
                : null,
        ],
        ['Diajukan', formatDateTime(proposal.submitted_at)],
    ];

    return (
        <>
            <Head title={`Usulan ${proposal.name}`} />

            <div className="flex w-full flex-col gap-4 p-4 md:p-6 lg:h-[calc(100svh-4rem)] lg:min-h-[640px]">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button variant="ghost" size="icon" asChild>
                            <Link
                                href={proposals.index().url}
                                aria-label="Kembali ke daftar usulan"
                            >
                                <ArrowLeft className="size-5" />
                            </Link>
                        </Button>
                        <div className="min-w-0">
                            <p className="section-label">
                                Tinjau Usulan Pekerjaan
                            </p>
                            <h1 className="truncate text-xl font-semibold tracking-tight">
                                {proposal.name}
                            </h1>
                        </div>
                    </div>
                    <StateBadge row={proposal} />
                </div>

                <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="min-h-[60vh] lg:min-h-0">
                        <DocumentViewer
                            attachments={proposal.attachments}
                            canRemove={can.revise}
                            proposalId={proposal.id}
                        />
                    </div>

                    <aside className="flex min-h-0 flex-col gap-4 lg:overflow-y-auto">
                        {can.approve && (
                            <DecisionPanel proposalId={proposal.id} />
                        )}

                        {proposal.state === 'dikembalikan' && (
                            <section className="space-y-2 rounded-lg border border-red-500/30 bg-red-500/5 p-4">
                                <p className="text-sm font-semibold text-red-700 dark:text-red-400">
                                    Dikembalikan oleh{' '}
                                    {proposal.reviewed_by ??
                                        'Direksi Pekerjaan'}
                                </p>
                                <p className="text-sm whitespace-pre-line">
                                    {proposal.review_note ?? '-'}
                                </p>
                            </section>
                        )}

                        {can.revise && (
                            <section className="grid gap-2 rounded-lg border border-border bg-card p-4">
                                <p className="text-sm font-semibold">
                                    Perbaiki Usulan
                                </p>
                                <div className="grid grid-cols-2 gap-2">
                                    <Button asChild variant="outline">
                                        <Link
                                            href={
                                                procurements.edit(proposal.id)
                                                    .url
                                            }
                                        >
                                            <Pencil className="size-4" />
                                            Ubah Data
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        disabled={upload.processing}
                                        onClick={() =>
                                            fileInput.current?.click()
                                        }
                                    >
                                        <Upload className="size-4" />
                                        Tambah Lampiran
                                    </Button>
                                </div>
                                <input
                                    ref={fileInput}
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                    className="hidden"
                                    onChange={(event) => {
                                        const files = Array.from(
                                            event.target.files ?? [],
                                        );
                                        event.target.value = '';

                                        if (files.length === 0) {
                                            return;
                                        }

                                        upload.transform(() => ({
                                            attachments: files,
                                        }));
                                        upload.post(
                                            proposals.attachments.store(
                                                proposal.id,
                                            ).url,
                                            {
                                                forceFormData: true,
                                                preserveScroll: true,
                                            },
                                        );
                                    }}
                                />
                                <InputError
                                    message={
                                        (
                                            upload.errors as Record<
                                                string,
                                                string | undefined
                                            >
                                        ).attachments ??
                                        Object.entries(upload.errors).find(
                                            ([key]) =>
                                                key.startsWith('attachments.'),
                                        )?.[1]
                                    }
                                />
                                {proposal.state === 'dikembalikan' && (
                                    <Button
                                        size="lg"
                                        className="mt-1"
                                        onClick={() =>
                                            router.post(
                                                proposals.resubmit(proposal.id)
                                                    .url,
                                            )
                                        }
                                    >
                                        <Send className="size-4" />
                                        Ajukan Ulang
                                    </Button>
                                )}
                            </section>
                        )}

                        {can.openProcurement && (
                            <Button asChild size="lg" variant="outline">
                                <Link href={procurements.show(proposal.id).url}>
                                    <ExternalLink className="size-4" />
                                    Buka Pengadaan
                                </Link>
                            </Button>
                        )}

                        <section className="rounded-lg border border-border bg-card">
                            <p className="border-b border-border px-4 py-3 text-sm font-semibold">
                                Data Usulan
                            </p>
                            <dl className="divide-y divide-border">
                                {details.map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="grid grid-cols-[9rem_1fr] gap-3 px-4 py-2.5 text-sm"
                                    >
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="font-medium break-words">
                                            {value ?? '-'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            {proposal.notes && (
                                <p className="border-t border-border px-4 py-3 text-sm whitespace-pre-line text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        Catatan:{' '}
                                    </span>
                                    {proposal.notes}
                                </p>
                            )}
                        </section>

                        {proposal.history.length > 0 && (
                            <section className="rounded-lg border border-border bg-card p-4">
                                <p className="mb-3 text-sm font-semibold">
                                    Riwayat
                                </p>
                                <ol className="space-y-3">
                                    {proposal.history.map((item) => (
                                        <li
                                            key={item.id}
                                            className="border-l-2 border-primary/40 pl-3"
                                        >
                                            <p className="text-sm font-medium">
                                                {item.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.user ?? 'Sistem'} ·{' '}
                                                {formatDateTime(item.at)}
                                            </p>
                                            {item.description && (
                                                <p className="mt-0.5 text-xs">
                                                    {item.description}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

ProposalShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Usulan Pekerjaan', href: proposals.index() },
    ],
};
