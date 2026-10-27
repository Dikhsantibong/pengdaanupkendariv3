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

export type PaymentApprovalState =
    | 'belum_diajukan'
    | 'menunggu_tl'
    | 'menunggu_asman'
    | 'disetujui'
    | 'dikembalikan';

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
    /** Penyerahan: the execution PIC submits, TL Pengadaan decides. */
    handover_approval: {
        state: PlanningApprovalState;
        label: string;
        submitted_at: string | null;
        submitted_by: string | null;
        reviewed_at: string | null;
        reviewer: string | null;
        note: string | null;
        revision: number;
        pending: string[];
    };
    /** Pembayaran: Keuangan submits, TL Keuangan then the Asman decide. */
    payment_approval: {
        state: PaymentApprovalState;
        label: string;
        submitted_at: string | null;
        submitted_by: string | null;
        team_leader: string | null;
        division: string | null;
        tl_reviewed_at: string | null;
        tl_reviewer: string | null;
        reviewed_at: string | null;
        reviewer: string | null;
        note: string | null;
        revision: number;
        pending: string[];
    };
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
                    Pelaksanaan disetujui dan diteruskan ke Penyerahan{' '}
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
                    disetujui diteruskan ke Penyerahan.
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
                        approveLabel="Setujui & Teruskan ke Penyerahan"
                    />
                </div>
            )}
        </section>
    );
}

/**
 * A decision that sent the papers back: who, when, and what to fix.
 */
function ReturnedNote({
    reviewer,
    reviewedAt,
    note,
    followUp,
}: {
    reviewer: string | null;
    reviewedAt: string | null;
    note: string | null;
    followUp: string | null;
}) {
    return (
        <div className="space-y-1.5 rounded-sm border border-destructive/40 bg-destructive/5 p-3">
            <p className="flex items-center gap-1.5 text-xs font-semibold text-destructive">
                <RotateCcw className="size-3.5" />
                Dikembalikan
                {reviewer ? ` oleh ${reviewer}` : ''}
                {reviewedAt ? ` · ${formatDateTime(reviewedAt)}` : ''}
            </p>
            <p className="text-xs text-foreground">
                <span className="font-medium">Yang harus diperbaiki: </span>
                {note ?? '—'}
            </p>
            {followUp && (
                <p className="text-xs text-muted-foreground">{followUp}</p>
            )}
        </div>
    );
}

/** Post a submission, showing it is on its way. */
function useSubmit(url: string) {
    const [processing, setProcessing] = useState(false);

    const submit = () =>
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );

    return { processing, submit };
}

/**
 * Penyerahan: the execution PIC files the hand-over papers and submits them
 * to TL Pengadaan, who approves them on to Pembayaran or returns them.
 */
export function HandoverApproval({
    procurementId,
    finance,
    canSubmit,
    canReview,
    pendingAction,
}: {
    procurementId: number;
    finance: FinanceDetail;
    canSubmit: boolean;
    canReview: boolean;
    pendingAction?: (name: string) => (() => void) | undefined;
}) {
    const approval = finance.handover_approval;
    const { processing, submit } = useSubmit(
        procurements.handoverApproval.store(procurementId).url,
    );
    const reviewForm = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    if (finance.submitted_at === null) {
        return null;
    }

    if (approval.state === 'disetujui') {
        return (
            <p className="flex items-start gap-2 text-xs text-muted-foreground">
                <BadgeCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                <span>
                    Penyerahan disetujui {formatDateTime(approval.reviewed_at)}
                    {approval.reviewer ? ` oleh ${approval.reviewer}` : ''} dan
                    diteruskan ke Pembayaran (Keuangan).
                </span>
            </p>
        );
    }

    const isWaiting = approval.state === 'menunggu_persetujuan';
    const isReturned = approval.state === 'ditolak';

    const decide = (approved: boolean) => {
        reviewForm.transform((data) => ({ ...data, approved }));
        reviewForm.put(
            procurements.handoverApproval.update(procurementId).url,
            { preserveScroll: true },
        );
    };

    return (
        <section className="space-y-3">
            <p className="text-xs text-muted-foreground">
                PIC Pelaksana melengkapi berkas di tab Penyerahan, lalu
                mengajukannya ke TL Pengadaan. Penyerahan yang disetujui
                diteruskan ke Pembayaran (Keuangan).
            </p>

            {isReturned && (
                <ReturnedNote
                    reviewer={approval.reviewer}
                    reviewedAt={approval.reviewed_at}
                    note={approval.note}
                    followUp={
                        finance.execution_approval.is_executor
                            ? null
                            : `Tindak lanjut ada pada PIC Pelaksana${finance.execution_approval.executor_name ? ` (${finance.execution_approval.executor_name})` : ''}.`
                    }
                />
            )}

            {isWaiting && !canReview && (
                <p className="text-xs text-muted-foreground">
                    Diajukan {formatDateTime(approval.submitted_at)}
                    {approval.submitted_by
                        ? ` oleh ${approval.submitted_by}`
                        : ''}
                    . Menunggu keputusan TL Pengadaan; checklist Penyerahan
                    terkunci selama ditinjau.
                </p>
            )}

            {!isWaiting && approval.pending.length > 0 && (
                <PendingList
                    title={`Belum dapat diajukan: ${approval.pending.length} tahapan wajib Penyerahan belum selesai`}
                    items={approval.pending.map((name) => ({
                        label: name,
                        onOpen: pendingAction?.(name),
                    }))}
                />
            )}

            {canSubmit && (
                <>
                    <ReadyNote>
                        Semua berkas wajib Penyerahan sudah lengkap. Penyerahan
                        siap diajukan ke TL Pengadaan.
                    </ReadyNote>
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
                </>
            )}

            {canReview && (
                <div className="border-t border-border pt-3">
                    <DecisionControls
                        id="handover-review-note"
                        note={reviewForm.data.note}
                        onNoteChange={(note) =>
                            reviewForm.setData('note', note)
                        }
                        error={reviewForm.errors.note}
                        processing={reviewForm.processing}
                        onDecide={decide}
                        approveLabel="Setujui & Teruskan ke Pembayaran"
                    />
                </div>
            )}
        </section>
    );
}

/**
 * Pembayaran: a Keuangan staff member files the payment papers and submits
 * them; their TL (TL Keuangan) and then the Asman of the division approve.
 * The Asman's approval records the payment and closes the job.
 */
export function PaymentApproval({
    procurementId,
    finance,
    canSubmit,
    canDecide,
    pendingAction,
}: {
    procurementId: number;
    finance: FinanceDetail;
    canSubmit: boolean;
    canDecide: boolean;
    pendingAction?: (name: string) => (() => void) | undefined;
}) {
    const approval = finance.payment_approval;
    const { processing, submit } = useSubmit(
        procurements.paymentApproval.store(procurementId).url,
    );
    const reviewForm = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    if (finance.handover_approval.state !== 'disetujui') {
        return null;
    }

    if (finance.paid_at !== null) {
        return (
            <p className="flex items-start gap-2 text-xs text-muted-foreground">
                <BadgeCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                <span>
                    Lunas: disetujui {formatDateTime(finance.paid_at)}
                    {finance.paid_by ? ` oleh ${finance.paid_by}` : ''},
                    pengadaan ditutup.
                </span>
            </p>
        );
    }

    const atTeamLeader = approval.state === 'menunggu_tl';
    const isWaiting = atTeamLeader || approval.state === 'menunggu_asman';
    const isReturned = approval.state === 'dikembalikan';
    const decider = atTeamLeader
        ? `TL Keuangan${approval.team_leader ? ` (${approval.team_leader})` : ''}`
        : `Asman${approval.division ? ` ${approval.division.replace(/^Asman\s+/i, '')}` : ''}`;

    const decide = (approved: boolean) => {
        reviewForm.transform((data) => ({ ...data, approved }));
        reviewForm.put(procurements.paymentApproval.update(procurementId).url, {
            preserveScroll: true,
        });
    };

    return (
        <section className="space-y-3">
            <p className="text-xs text-muted-foreground">
                Keuangan melengkapi berkas di tab Pembayaran, lalu mengajukan ke
                TL Keuangan dan Asman. Persetujuan Asman mencatat pembayaran
                lunas dan menutup pengadaan.
            </p>

            {isReturned && (
                <ReturnedNote
                    reviewer={approval.reviewer}
                    reviewedAt={approval.reviewed_at}
                    note={approval.note}
                    followUp={null}
                />
            )}

            {isWaiting && (
                <div className="space-y-1 text-xs text-muted-foreground">
                    <p>
                        Diajukan {formatDateTime(approval.submitted_at)}
                        {approval.submitted_by
                            ? ` oleh ${approval.submitted_by}`
                            : ''}
                        .
                    </p>
                    {approval.tl_reviewed_at && (
                        <p>
                            Disetujui TL{' '}
                            {formatDateTime(approval.tl_reviewed_at)}
                            {approval.tl_reviewer
                                ? ` oleh ${approval.tl_reviewer}`
                                : ''}
                            .
                        </p>
                    )}
                    {!canDecide && (
                        <p className="font-medium text-foreground">
                            Menunggu keputusan {decider}.
                        </p>
                    )}
                </div>
            )}

            {!isWaiting && approval.pending.length > 0 && (
                <PendingList
                    title={`Belum dapat diajukan: ${approval.pending.length} tahapan wajib Pembayaran belum selesai`}
                    items={approval.pending.map((name) => ({
                        label: name,
                        onOpen: pendingAction?.(name),
                    }))}
                />
            )}

            {canSubmit && (
                <>
                    <ReadyNote>
                        Semua berkas wajib Pembayaran sudah lengkap. Pembayaran
                        siap diajukan untuk disetujui.
                    </ReadyNote>
                    <Button
                        className="w-full"
                        disabled={processing}
                        onClick={submit}
                    >
                        <Send className="size-4" />
                        {isReturned
                            ? 'Ajukan Ulang Pembayaran'
                            : 'Ajukan Pembayaran'}
                    </Button>
                </>
            )}

            {canDecide && (
                <div className="border-t border-border pt-3">
                    <DecisionControls
                        id="payment-review-note"
                        note={reviewForm.data.note}
                        onNoteChange={(note) =>
                            reviewForm.setData('note', note)
                        }
                        error={reviewForm.errors.note}
                        processing={reviewForm.processing}
                        onDecide={decide}
                        approveLabel={
                            atTeamLeader
                                ? 'Setujui & Teruskan ke Asman'
                                : 'Setujui: Lunas & Tutup Pengadaan'
                        }
                    />
                </div>
            )}
        </section>
    );
}

/**
 * Where the payment stands, at the head of the Pembayaran tab.
 */
export function FinanceStatus({ finance }: { finance: FinanceDetail }) {
    if (finance.paid_at !== null) {
        return (
            <section className="flex items-start gap-3 rounded-md border border-emerald-500/30 bg-emerald-500/5 p-4">
                <BadgeCheck className="mt-0.5 size-5 text-emerald-600" />
                <div className="text-sm">
                    <p className="font-semibold text-emerald-800 dark:text-emerald-300">
                        Lunas — pengadaan ditutup
                    </p>
                    <p className="text-muted-foreground">
                        Disetujui {formatDateTime(finance.paid_at)}
                        {finance.paid_by ? ` oleh ${finance.paid_by}` : ''}.
                    </p>
                </div>
            </section>
        );
    }

    if (finance.handover_approval.state !== 'disetujui') {
        return (
            <p className="rounded-md border border-dashed border-border px-4 py-3 text-sm text-muted-foreground">
                Tahap Pembayaran terbuka setelah TL Pengadaan menyetujui berkas
                Penyerahan yang diajukan PIC Pelaksana.
            </p>
        );
    }

    return (
        <p className="flex items-start gap-2 rounded-md border border-border bg-card px-4 py-3 text-sm">
            <Landmark className="mt-0.5 size-4 shrink-0 text-primary" />
            <span>
                <span className="font-medium">
                    {finance.payment_approval.label}.
                </span>{' '}
                <span className="text-muted-foreground">
                    Keuangan melengkapi berkas di bawah lalu mengajukan
                    pembayaran (kolom Persetujuan). Persetujuan TL Keuangan dan
                    Asman mencatat lunas dan menutup pengadaan.
                </span>
            </span>
        </p>
    );
}
