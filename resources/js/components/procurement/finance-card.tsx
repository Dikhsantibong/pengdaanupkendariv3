import { router } from '@inertiajs/react';
import { BadgeCheck, Landmark, Send } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';

export type FinanceDetail = {
    submitted_at: string | null;
    submitted_by: string | null;
    paid_at: string | null;
    paid_by: string | null;
    pending_execution: string[];
    pending_finance: string[];
};

/** A short list of the steps still holding a hand-over back. */
function PendingSteps({ title, steps }: { title: string; steps: string[] }) {
    if (steps.length === 0) {
        return null;
    }

    return (
        <div className="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs">
            <p className="font-medium text-amber-800 dark:text-amber-300">
                {title}
            </p>
            <ul className="mt-1 list-inside list-disc text-muted-foreground">
                {steps.map((step) => (
                    <li key={step}>{step}</li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Hand the executed job to Keuangan, shown at the end of the execution tab.
 */
export function FinanceHandover({
    procurementId,
    finance,
    canSubmit,
    planningApproved,
}: {
    procurementId: number;
    finance: FinanceDetail;
    canSubmit: boolean;
    planningApproved: boolean;
}) {
    const [processing, setProcessing] = useState(false);

    if (!planningApproved) {
        return null;
    }

    if (finance.submitted_at !== null) {
        return (
            <p className="flex items-center gap-2 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                <Landmark className="size-4 text-primary" />
                Diserahkan ke Keuangan {formatDateTime(finance.submitted_at)}
                {finance.submitted_by ? ` oleh ${finance.submitted_by}` : ''}.
                Pelaksanaan terkunci.
            </p>
        );
    }

    return (
        <section className="space-y-3 rounded-md border border-border bg-card p-4">
            <div>
                <p className="text-sm font-semibold">Serahkan ke Keuangan</p>
                <p className="text-xs text-muted-foreground">
                    Setelah semua tahapan wajib pelaksanaan selesai, serahkan
                    pengadaan ke Keuangan untuk proses pembayaran.
                </p>
            </div>
            <PendingSteps
                title="Tahapan wajib yang belum selesai:"
                steps={finance.pending_execution}
            />
            {canSubmit && (
                <Button
                    disabled={processing}
                    onClick={() =>
                        router.post(
                            procurements.finance.submit(procurementId).url,
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setProcessing(true),
                                onFinish: () => setProcessing(false),
                            },
                        )
                    }
                >
                    <Send className="size-4" />
                    Serahkan ke Keuangan
                </Button>
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
}: {
    procurementId: number;
    finance: FinanceDetail;
    canComplete: boolean;
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
                Tahap Keuangan terbuka setelah PIC Pelaksana menyerahkan
                pengadaan yang pelaksanaannya selesai.
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
            <PendingSteps
                title="Tahapan wajib yang belum selesai:"
                steps={finance.pending_finance}
            />
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
