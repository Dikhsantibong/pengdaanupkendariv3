import { router, useForm } from '@inertiajs/react';
import { BadgeCheck, Landmark, RotateCcw, Send } from 'lucide-react';
import { useState } from 'react';
import { DecisionControls } from '@/components/procurement/decision-controls';
import { PendingList, ReadyNote } from '@/components/procurement/request-board';
import { ApprovalBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { PlanningApprovalState } from '@/types';

export type FinanceDetail = {
    submitted_at: string | null;
    submitted_by: string | null;
    paid_at: string | null;
    paid_by: string | null;
    pending_execution: string[];
    /** The execution PIC's submission to TL Pengadaan. */
    execution_approval: {
        state: PlanningApprovalState;
        label: string;
        submitted_at: string | null;
        reviewed_at: string | null;
        reviewer: string | null;
        note: string | null;
        revision: number;
        is_executor: boolean;
        executor_name: string | null;
    };
    pending_finance: string[];
    /** Per tab: the contract the requirements were read from, or null for the template. */
    requirements_source: Record<'penyerahan' | 'pembayaran', string | null>;
    /** What has to be filled in before the contract's requirements appear. */
    requirements_waiting_for: string[];
};

/**
 * The execution PIC submits the finished execution and TL Pengadaan approves
 * it into Penyerahan and Pembayaran, shown in the side column under the
 * planning approval, in the same card style.
 */
export function ExecutionApproval({
    procurementId,
    finance,
    canSubmit,
    canReview,
    planningApproved,
    pendingAction,
    embedded = false,
}: {
    procurementId: number;
    finance: FinanceDetail;
    canSubmit: boolean;
    canReview: boolean;
    planningApproved: boolean;
    /** Where an outstanding step is done, when the page can go there. */
    pendingAction?: (name: string) => (() => void) | undefined;
    /** Inside the Persetujuan panel, which draws the card and names the stage. */
    embedded?: boolean;
}) {
    const [processing, setProcessing] = useState(false);
    const reviewForm = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    if (!planningApproved) {
        return null;
    }

    const approval = finance.execution_approval;

    if (finance.submitted_at !== null) {
        return (
            <p className="flex items-start gap-2 rounded-md border border-border bg-card p-4 text-xs text-muted-foreground">
                <Landmark className="mt-0.5 size-4 shrink-0 text-primary" />
                <span>
                    Pelaksanaan disetujui dan diserahkan ke Keuangan{' '}
                    {formatDateTime(finance.submitted_at)}
                    {finance.submitted_by
                        ? ` oleh ${finance.submitted_by}`
                        : ''}
                    . Pelaksanaan terkunci.
                </span>
            </p>
        );
    }

    const isWaiting = approval.state === 'menunggu_persetujuan';
    const isReturned = approval.state === 'ditolak';

    const submit = () =>
        router.post(
            procurements.executionApproval.store(procurementId).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );

    const decide = (approved: boolean) => {
        reviewForm.transform((data) => ({ ...data, approved }));
        reviewForm.put(
            procurements.executionApproval.update(procurementId).url,
            { preserveScroll: true },
        );
    };

    return (
        <section
            className={
                embedded
                    ? 'space-y-3'
                    : 'space-y-4 rounded-md border border-border bg-card p-4'
            }
        >
            <div className="space-y-2">
                {!embedded && (
                    <p className="section-label">Persetujuan Pelaksanaan</p>
                )}
                <div className="flex flex-wrap items-center gap-2">
                    <ApprovalBadge
                        state={approval.state}
                        label={approval.label}
                        className="text-sm"
                    />
                    {approval.revision > 0 && (
                        <span className="tabular rounded-sm bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground">
                            Revisi ke-{approval.revision}
                        </span>
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    Setelah semua tahapan wajib selesai, PIC Pelaksana
                    mengajukan pelaksanaan ke TL Pengadaan. Pelaksanaan yang
                    disetujui diteruskan ke Penyerahan dan Pembayaran
                    (Keuangan).
                </p>
                {approval.submitted_at && (
                    <p className="tabular text-xs text-muted-foreground">
                        Diajukan {formatDateTime(approval.submitted_at)}
                    </p>
                )}
            </div>

            {isReturned && (
                <div className="space-y-1.5 rounded-sm border border-destructive/40 bg-destructive/5 p-3">
                    <p className="flex items-center gap-1.5 text-xs font-semibold text-destructive">
                        <RotateCcw className="size-3.5" />
                        Dikembalikan
                        {approval.reviewer ? ` oleh ${approval.reviewer}` : ''}
                        {approval.reviewed_at
                            ? ` · ${formatDateTime(approval.reviewed_at)}`
                            : ''}
                    </p>
                    <p className="text-xs text-foreground">
                        <span className="font-medium">
                            Yang harus diperbaiki:{' '}
                        </span>
                        {approval.note ?? '—'}
                    </p>
                    {!approval.is_executor && (
                        <p className="text-xs text-muted-foreground">
                            Tindak lanjut ada pada PIC Pelaksana
                            {approval.executor_name
                                ? ` (${approval.executor_name})`
                                : ''}
                            .
                        </p>
                    )}
                </div>
            )}

            {isWaiting && approval.note && (
                <p className="rounded-sm border border-border bg-muted/40 p-2.5 text-xs text-foreground">
                    <span className="font-medium">
                        Catatan revisi yang ditindaklanjuti:{' '}
                    </span>
                    {approval.note}
                </p>
            )}

            {isWaiting && !canReview && (
                <p className="text-xs text-muted-foreground">
                    Menunggu keputusan TL Pengadaan. Checklist pelaksanaan
                    terkunci selama ditinjau.
                </p>
            )}

            {/* Each outstanding step named, and a click away. */}
            {!isWaiting && finance.pending_execution.length > 0 && (
                <PendingList
                    title={`Belum dapat diajukan: ${finance.pending_execution.length} tahapan wajib Pelaksanaan belum selesai`}
                    items={finance.pending_execution.map((name) => ({
                        label: name,
                        onOpen: pendingAction?.(name),
                    }))}
                />
            )}

            {canSubmit && (
                <ReadyNote>
                    Semua tahapan wajib sudah lengkap. Pelaksanaan siap diajukan
                    ke TL Pengadaan.
                </ReadyNote>
            )}

            {canSubmit && (
                <Button
                    className="w-full"
                    disabled={processing}
                    onClick={submit}
                >
                    <Send className="size-4" />
                    {isReturned
                        ? 'Ajukan Ulang ke TL Pengadaan'
                        : 'Ajukan ke TL Pengadaan'}
                </Button>
            )}

            {canReview && (
                <div className="border-t border-border pt-3">
                    <DecisionControls
                        id="execution-review-note"
                        note={reviewForm.data.note}
                        onNoteChange={(note) =>
                            reviewForm.setData('note', note)
                        }
                        error={reviewForm.errors.note}
                        processing={reviewForm.processing}
                        onDecide={decide}
                        approveLabel="Setujui & Teruskan ke Keuangan"
                    />
                </div>
            )}
        </section>
    );
}

/**
 * Where the job stands with Keuangan, and the closing action.
 */
export function FinanceStatus({
    procurementId,
    finance,
    canComplete,
    pendingAction,
}: {
    procurementId: number;
    finance: FinanceDetail;
    canComplete: boolean;
    /** Where an outstanding step is done, when the page can go there. */
    pendingAction?: (name: string) => (() => void) | undefined;
}) {
    const [processing, setProcessing] = useState(false);

    if (finance.paid_at !== null) {
        return (
            <section className="flex items-start gap-3 rounded-md border border-emerald-500/30 bg-emerald-500/5 p-4">
                <BadgeCheck className="mt-0.5 size-5 text-emerald-600" />
                <div className="text-sm">
                    <p className="font-semibold text-emerald-800 dark:text-emerald-300">
                        Lunas — pengadaan ditutup
                    </p>
                    <p className="text-muted-foreground">
                        Dicatat {formatDateTime(finance.paid_at)}
                        {finance.paid_by ? ` oleh ${finance.paid_by}` : ''}.
                    </p>
                </div>
            </section>
        );
    }

    if (finance.submitted_at === null) {
        return (
            <p className="rounded-md border border-dashed border-border px-4 py-3 text-sm text-muted-foreground">
                Tahap Keuangan terbuka setelah TL Pengadaan menyetujui
                pelaksanaan yang diajukan PIC Pelaksana.
            </p>
        );
    }

    return (
        <section className="space-y-3 rounded-md border border-border bg-card p-4">
            <div>
                <p className="text-sm font-semibold">Proses Pembayaran</p>
                <p className="text-xs text-muted-foreground">
                    Diserahkan {formatDateTime(finance.submitted_at)}
                    {finance.submitted_by
                        ? ` oleh ${finance.submitted_by}`
                        : ''}
                    . Lengkapi tahapan di bawah, lalu catat pembayaran untuk
                    menutup pengadaan.
                </p>
            </div>
            {finance.pending_finance.length > 0 ? (
                <PendingList
                    title={`${finance.pending_finance.length} tahapan wajib Penyerahan / Pembayaran belum selesai`}
                    items={finance.pending_finance.map((name) => ({
                        label: name,
                        onOpen: pendingAction?.(name),
                    }))}
                />
            ) : (
                <ReadyNote>
                    Semua tahapan wajib Penyerahan dan Pembayaran sudah lengkap.
                </ReadyNote>
            )}
            {canComplete && (
                <Button
                    className="bg-emerald-600 hover:bg-emerald-700"
                    disabled={processing}
                    onClick={() => {
                        if (
                            window.confirm(
                                'Catat pembayaran lunas dan tutup pengadaan ini?',
                            )
                        ) {
                            router.post(
                                procurements.finance.complete(procurementId)
                                    .url,
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setProcessing(true),
                                    onFinish: () => setProcessing(false),
                                },
                            );
                        }
                    }}
                >
                    <BadgeCheck className="size-4" />
                    Pembayaran Lunas &amp; Tutup Pengadaan
                </Button>
            )}
        </section>
    );
}
