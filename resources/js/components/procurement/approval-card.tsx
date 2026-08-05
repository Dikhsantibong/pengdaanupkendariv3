import { router, useForm } from '@inertiajs/react';
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
    canReview,
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
    canReview: boolean;
    canComplete: boolean;
    completedAt: string | null;
}) {
    const reviewForm = useForm<{ approved: boolean; note: string }>({
        approved: true,
        note: '',
    });

    const decide = (approved: boolean) => {
        reviewForm.transform((data) => ({ ...data, approved }));
        reviewForm.put(procurements.approval.update(procurementId).url, {
            preserveScroll: true,
        });
    };

    return (
        <section className="space-y-4 rounded-md border border-border bg-card p-4">
            <div className="space-y-2">
                <p className="section-label">Persetujuan Perencanaan</p>
                <ApprovalBadge
                    state={state}
                    label={stateLabel}
                    className="text-sm"
                />
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

            {reviewNote && (
                <p className="rounded-sm border border-border bg-muted/40 p-2.5 text-xs text-foreground">
                    <span className="font-medium">Catatan reviewer: </span>
                    {reviewNote}
                </p>
            )}

            {canSubmit && (
                <Button
                    type="button"
                    size="sm"
                    className="w-full"
                    onClick={() =>
                        router.post(
                            procurements.approval.store(procurementId).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Ajukan Persetujuan
                </Button>
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
