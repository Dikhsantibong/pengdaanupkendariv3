import { router, useForm } from '@inertiajs/react';
import { CircleAlert, RotateCcw, Undo2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { ApprovalBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { PlanningApprovalState } from '@/types';

export function ApprovalCard({
    procurementId,
    state,
    stateLabel,
    submittedAt,
    reviewedAt,
    reviewer,
    reviewNote,
    canSubmit,
    isPlanner,
    plannerName,
    revision,
    pendingRequired,
    canReview,
    canRevert,
    canComplete,
    completedAt,
}: {
    procurementId: number;
    state: PlanningApprovalState;
    stateLabel: string;
    submittedAt: string | null;
    reviewedAt: string | null;
    reviewer: string | null;
    reviewNote: string | null;
    canSubmit: boolean;
    /** Only the appointed planning PIC submits; approvers never do. */
    isPlanner: boolean;
    /** Who has to act on a revision, shown to everyone else. */
    plannerName: string | null;
    /** How many times the stage has been sent back and resubmitted. */
    revision: number;
    /** Mandatory planning steps still outstanding. */
    pendingRequired: string[];
    canReview: boolean;
    /** A supervisor may withdraw a rejection they should not have made. */
    canRevert: boolean;
    canComplete: boolean;
    completedAt: string | null;
}) {
    const reviewForm = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    const isRejected = state === 'ditolak';

    const decide = (approved: boolean) => {
        reviewForm.transform((data) => ({ ...data, approved }));
        reviewForm.put(procurements.approval.update(procurementId).url, {
            preserveScroll: true,
        });
    };

    const submit = () =>
        router.post(
            procurements.approval.store(procurementId).url,
            {},
            { preserveScroll: true },
        );

    return (
        <section className="space-y-4 rounded-md border border-border bg-card p-4">
            <div className="space-y-2">
                <p className="section-label">Persetujuan Perencanaan</p>
                <div className="flex flex-wrap items-center gap-2">
                    <ApprovalBadge
                        state={state}
                        label={stateLabel}
                        className="text-sm"
                    />
                    {revision > 0 && (
                        <span className="tabular rounded-sm bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground">
                            Revisi ke-{revision}
                        </span>
                    )}
                </div>
            </div>

            <dl className="space-y-1.5 text-xs text-muted-foreground">
                {submittedAt && (
                    <div className="flex justify-between gap-3">
                        <dt>Diajukan</dt>
                        <dd className="tabular text-right">
                            {formatDateTime(submittedAt)}
                        </dd>
                    </div>
                )}
                {reviewedAt && (
                    <div className="flex justify-between gap-3">
                        <dt>Ditinjau</dt>
                        <dd className="tabular text-right">
                            {formatDateTime(reviewedAt)}
                            {reviewer ? ` · ${reviewer}` : ''}
                        </dd>
                    </div>
                )}
                {completedAt && (
                    <div className="flex justify-between gap-3">
                        <dt>Selesai</dt>
                        <dd className="tabular text-right">
                            {formatDateTime(completedAt)}
                        </dd>
                    </div>
                )}
            </dl>

            {reviewNote && !isRejected && (
                <p className="rounded-sm border border-border bg-muted/40 p-2.5 text-xs text-foreground">
                    <span className="font-medium">
                        {state === 'menunggu_persetujuan' && revision > 0
                            ? 'Catatan revisi yang ditindaklanjuti: '
                            : 'Catatan reviewer: '}
                    </span>
                    {reviewNote}
                </p>
            )}

            {isRejected && (
                <div className="space-y-2.5 rounded-sm border border-destructive/40 bg-destructive/5 p-3">
                    <p className="flex items-center gap-1.5 text-xs font-semibold text-destructive">
                        <RotateCcw className="size-3.5" />
                        Dikembalikan untuk revisi
                    </p>

                    {reviewNote ? (
                        <p className="text-xs text-foreground">
                            <span className="font-medium">
                                Yang harus diperbaiki:{' '}
                            </span>
                            {reviewNote}
                        </p>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            Reviewer tidak menuliskan catatan. Hubungi reviewer
                            untuk memastikan bagian yang perlu diperbaiki.
                        </p>
                    )}

                    {isPlanner ? (
                        <>
                            <ol className="list-decimal space-y-0.5 pl-4 text-xs text-muted-foreground">
                                <li>
                                    Perbaiki dokumen atau data sesuai catatan di
                                    atas.
                                </li>
                                <li>
                                    Pastikan seluruh tahapan wajib pada
                                    Checklist Perencanaan tetap tercentang.
                                </li>
                                <li>Ajukan ulang untuk ditinjau kembali.</li>
                            </ol>

                            {canSubmit ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="w-full"
                                    onClick={submit}
                                >
                                    Ajukan Ulang Persetujuan
                                </Button>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    Lengkapi kembali tahapan wajib sebelum dapat
                                    diajukan ulang.
                                </p>
                            )}
                        </>
                    ) : (
                        <>
                            <p className="text-xs text-muted-foreground">
                                {plannerName === null ? (
                                    <>
                                        Pengadaan ini belum punya PIC Perencana,
                                        sehingga belum ada yang dapat mengajukan
                                        ulang. Tunjuk PIC lebih dulu melalui
                                        menu Penunjukan PIC.
                                    </>
                                ) : (
                                    <>
                                        Tindak lanjut ada pada PIC Perencana (
                                        {plannerName}). Setelah diperbaiki, PIC
                                        akan mengajukan ulang dan pengadaan ini
                                        kembali masuk antrean persetujuan.
                                    </>
                                )}
                            </p>

                            {canRevert && (
                                <div className="space-y-1.5 border-t border-destructive/20 pt-2.5">
                                    <p className="text-xs text-muted-foreground">
                                        Salah menolak, atau PIC berhalangan?
                                        Batalkan penolakan untuk mengembalikan
                                        pengajuan ke antrean persetujuan tanpa
                                        menunggu PIC.
                                    </p>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="w-full"
                                        onClick={() =>
                                            router.delete(
                                                procurements.approval.destroy(
                                                    procurementId,
                                                ).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Undo2 className="size-3.5" />
                                        Batalkan Penolakan
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </div>
            )}

            {canSubmit && !isRejected && (
                <Button
                    type="button"
                    size="sm"
                    className="w-full"
                    onClick={submit}
                >
                    Ajukan Persetujuan
                </Button>
            )}

            {isPlanner && !canSubmit && pendingRequired.length > 0 && (
                <div className="space-y-1.5 rounded-sm border border-border bg-muted/40 p-2.5">
                    <p className="flex items-center gap-1.5 text-xs font-medium text-foreground">
                        <CircleAlert className="size-3.5 text-amber-600 dark:text-amber-400" />
                        Belum dapat diajukan
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Lengkapi {pendingRequired.length} tahapan wajib berikut
                        terlebih dahulu:
                    </p>
                    <ul className="list-disc space-y-0.5 pl-4 text-xs text-muted-foreground">
                        {pendingRequired.map((name) => (
                            <li key={name}>{name}</li>
                        ))}
                    </ul>
                </div>
            )}

            {canReview && (
                <div className="space-y-3 border-t border-border pt-3">
                    <div className="grid gap-2">
                        <Label htmlFor="review-note">Catatan Persetujuan</Label>
                        <Textarea
                            id="review-note"
                            rows={2}
                            value={reviewForm.data.note}
                            onChange={(event) =>
                                reviewForm.setData('note', event.target.value)
                            }
                            placeholder="Wajib diisi bila menolak"
                        />
                        <InputError message={reviewForm.errors.note} />
                    </div>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            className="flex-1"
                            disabled={reviewForm.processing}
                            onClick={() => decide(true)}
                        >
                            Setujui
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            className="flex-1"
                            disabled={reviewForm.processing}
                            onClick={() => decide(false)}
                        >
                            Tolak
                        </Button>
                    </div>
                </div>
            )}

            {canComplete && state === 'disetujui' && (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="w-full"
                    onClick={() =>
                        router.post(
                            procurements.completion.store(procurementId).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Tandai Pengadaan Selesai
                </Button>
            )}
        </section>
    );
}
