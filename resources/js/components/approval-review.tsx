import { Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    ExternalLink,
    FileText,
    Inbox,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { DecisionControls } from '@/components/procurement/decision-controls';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import type { Paginated, ProcurementRow } from '@/types';

export type ReviewQueue = 'planning' | 'execution' | 'handover' | 'payment';

/** What the reviewer reads before deciding one submission. */
export type Review = {
    submitted_at: string | null;
    submitted_by: string | null;
    revision: number;
    note: string | null;
    value: number;
    partner_name: string | null;
    payment_state: string | null;
    tl_reviewer: string | null;
    can_decide: boolean;
    papers: { name: string; url: string; is_image: boolean }[];
};

/** The words and the decision route of each queue. */
const QUEUE: Record<
    ReviewQueue,
    {
        stage: string;
        tab: string;
        submitter: string;
        approve: (review: Review) => string;
        url: (id: number) => string;
    }
> = {
    planning: {
        stage: 'Perencanaan',
        tab: 'perencanaan',
        submitter: 'PIC Perencana',
        approve: () => 'Setujui Perencanaan',
        url: (id) => procurements.approval.update(id).url,
    },
    execution: {
        stage: 'Pelaksanaan',
        tab: 'pelaksanaan',
        submitter: 'PIC Pelaksana',
        approve: () => 'Setujui & Teruskan ke Penyerahan',
        url: (id) => procurements.executionApproval.update(id).url,
    },
    handover: {
        stage: 'Penyerahan',
        tab: 'penyerahan',
        submitter: 'PIC Pelaksana',
        approve: () => 'Setujui & Teruskan ke Pembayaran',
        url: (id) => procurements.handoverApproval.update(id).url,
    },
    payment: {
        stage: 'Pembayaran',
        tab: 'pembayaran',
        submitter: 'Keuangan',
        approve: (review) =>
            review.payment_state === 'menunggu_tl'
                ? 'Setujui & Teruskan ke Asman'
                : 'Setujui: Lunas & Tutup Pengadaan',
        url: (id) => procurements.paymentApproval.update(id).url,
    },
};

/**
 * The submissions of one queue as short cards: what it is, who sent it and
 * when, and one button to review it.
 */
export function ReviewList({
    queue,
    page,
    reviews,
    decided,
    emptyTitle,
    emptyDescription,
}: {
    queue: ReviewQueue;
    page: Paginated<ProcurementRow>;
    reviews: Record<number, Review>;
    decided: boolean;
    emptyTitle: string;
    emptyDescription: string;
}) {
    const [openId, setOpenId] = useState<number | null>(null);
    const open = page.data.find((row) => row.id === openId) ?? null;

    if (page.data.length === 0) {
        return (
            <div className="rounded-md border border-border bg-card">
                <EmptyState
                    icon={Inbox}
                    title={emptyTitle}
                    description={emptyDescription}
                />
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-md border border-border bg-card">
            <ul className="divide-y divide-border">
                {page.data.map((row) => {
                    const review = reviews[row.id];

                    return (
                        <li
                            key={row.id}
                            className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{row.name}</p>
                                <p className="tabular text-xs text-muted-foreground">
                                    {row.number} · {row.work_director}
                                    {review?.partner_name
                                        ? ` · ${review.partner_name}`
                                        : ''}
                                </p>
                                {review && (
                                    <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                        <span className="inline-flex items-center gap-1">
                                            <UserRound className="size-3.5" />
                                            {review.submitted_by ??
                                                QUEUE[queue].submitter}
                                        </span>
                                        <span>
                                            {decided
                                                ? 'Diputuskan'
                                                : 'Diajukan'}{' '}
                                            {formatDateTime(
                                                review.submitted_at,
                                            )}
                                        </span>
                                        <span className="tabular font-medium text-foreground">
                                            {formatCurrency(review.value)}
                                        </span>
                                        {review.revision > 0 && (
                                            <span className="rounded-sm bg-amber-500/15 px-1.5 py-0.5 font-medium text-amber-800 dark:text-amber-300">
                                                Revisi ke-{review.revision}
                                            </span>
                                        )}
                                    </p>
                                )}
                            </div>
                            <Button
                                size="sm"
                                variant={
                                    review?.can_decide ? 'default' : 'outline'
                                }
                                className="shrink-0 self-start sm:self-center"
                                onClick={() => setOpenId(row.id)}
                            >
                                {review?.can_decide
                                    ? 'Tinjau & Putuskan'
                                    : 'Lihat'}
                                <ArrowRight className="size-4" />
                            </Button>
                        </li>
                    );
                })}
            </ul>
            <DataPagination page={page} />

            {open && reviews[open.id] && (
                <ReviewSheet
                    key={open.id}
                    queue={queue}
                    row={open}
                    review={reviews[open.id]}
                    onClose={() => setOpenId(null)}
                />
            )}
        </div>
    );
}

/**
 * One submission under review: the summary, the papers to read (each
 * opens in the panel), then Setujui or Kembalikan.
 */
function ReviewSheet({
    queue,
    row,
    review,
    onClose,
}: {
    queue: ReviewQueue;
    row: ProcurementRow;
    review: Review;
    onClose: () => void;
}) {
    const words = QUEUE[queue];
    const [paper, setPaper] = useState(review.papers[0] ?? null);
    const form = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    const decide = (approved: boolean) => {
        form.transform((data) => ({ ...data, approved }));
        form.put(words.url(row.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Sheet open onOpenChange={(value) => !value && onClose()}>
            <SheetContent
                side="right"
                className="flex w-full flex-col gap-0 p-0 sm:max-w-3xl"
            >
                <SheetHeader className="border-b border-border p-4">
                    <p className="section-label">Tinjau {words.stage}</p>
                    <SheetTitle className="text-base leading-snug">
                        {row.name}
                    </SheetTitle>
                    <SheetDescription className="tabular text-xs">
                        {row.number} · {row.work_director} · diajukan{' '}
                        {review.submitted_by ?? words.submitter},{' '}
                        {formatDateTime(review.submitted_at)}
                        {review.tl_reviewer
                            ? ` · disetujui TL ${review.tl_reviewer}`
                            : ''}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-4">
                    {review.note && (
                        <p className="rounded-md border border-amber-500/30 bg-amber-500/5 p-3 text-xs">
                            <span className="font-medium">
                                Catatan revisi sebelumnya:{' '}
                            </span>
                            {review.note}
                        </p>
                    )}

                    <div className="space-y-2">
                        <p className="text-sm font-semibold">
                            1. Periksa berkas ({review.papers.length})
                        </p>
                        {review.papers.length === 0 ? (
                            <p className="rounded-md border border-dashed border-border p-3 text-xs text-muted-foreground">
                                Tidak ada berkas terunggah untuk tahap ini. Buka
                                detail pengadaan untuk melihat selengkapnya.
                            </p>
                        ) : (
                            <>
                                <div className="flex gap-1 overflow-x-auto pb-1">
                                    {review.papers.map((item) => (
                                        <button
                                            key={item.url}
                                            type="button"
                                            onClick={() => setPaper(item)}
                                            className={cn(
                                                'flex max-w-[15rem] shrink-0 items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs transition-colors',
                                                paper?.url === item.url
                                                    ? 'border-primary bg-primary/10 font-medium text-primary'
                                                    : 'border-border text-muted-foreground hover:bg-muted',
                                            )}
                                        >
                                            <FileText className="size-3.5 shrink-0" />
                                            <span className="truncate">
                                                {item.name}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                {paper && (
                                    <div className="overflow-hidden rounded-md border border-border bg-muted/30">
                                        {paper.is_image ? (
                                            <img
                                                src={paper.url}
                                                alt={paper.name}
                                                className="mx-auto max-h-[55vh] object-contain"
                                            />
                                        ) : (
                                            <iframe
                                                key={paper.url}
                                                src={`${paper.url}#view=FitH`}
                                                title={paper.name}
                                                className="h-[55vh] w-full bg-white"
                                            />
                                        )}
                                    </div>
                                )}
                            </>
                        )}
                        <Link
                            href={
                                procurements.show(row.id, {
                                    query: { tab: words.tab },
                                }).url
                            }
                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            <ExternalLink className="size-3.5" />
                            Buka detail pengadaan
                        </Link>
                    </div>
                </div>

                <div className="space-y-2 border-t border-border bg-card p-4">
                    {review.can_decide ? (
                        <>
                            <p className="text-sm font-semibold">2. Putuskan</p>
                            <DecisionControls
                                id={`review-note-${row.id}`}
                                note={form.data.note}
                                onNoteChange={(note) =>
                                    form.setData('note', note)
                                }
                                error={form.errors.note}
                                processing={form.processing}
                                onDecide={decide}
                                approveLabel={words.approve(review)}
                            />
                        </>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            Pengajuan ini tidak menunggu keputusan Anda.
                        </p>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
