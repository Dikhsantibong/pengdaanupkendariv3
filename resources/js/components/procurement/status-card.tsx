import { useForm } from '@inertiajs/react';
import { Ban, Check, ChevronDown, RotateCcw, Sparkles } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import type { StatusCategory } from '@/types';

/** Where the job stands on its way to Terkontrak (from the server). */
export type StatusJourney = {
    steps: Array<{
        key: string;
        name: string;
        state: 'done' | 'current' | 'upcoming';
        /** When the status was entered, when known. */
        at: string | null;
    }>;
    /** The place of the current status on the way (1-based). */
    position: number | null;
    /** The place of Terkontrak on the way. */
    to_contract: number;
    /** Statuses left before Terkontrak. */
    remaining: number | null;
    /** The revision status standing in for its approval, if any. */
    revision: string | null;
    cancelled: boolean;
};

/** One status the job entered. */
export type StatusHistoryEntry = {
    id: number;
    name: string;
    key: string | null;
    by: string | null;
    at: string | null;
};

/**
 * The progress status, set by the system from where the job stands. The only
 * manual action is cancelling the job (Batal) and lifting that again, left
 * to the team leaders.
 */
export function StatusCard({
    procurementId,
    status,
    rule,
    journey,
    history = [],
    editable,
}: {
    procurementId: number;
    status: { id: number; name: string; category: StatusCategory };
    /** When the current status applies, as the system reads it. */
    rule: string | null;
    journey?: StatusJourney;
    history?: StatusHistoryEntry[];
    editable: boolean;
}) {
    const isCancelled = status.category === 'batal';
    const [asking, setAsking] = useState(false);
    const [showHistory, setShowHistory] = useState(false);
    const form = useForm<{ cancelled: boolean; note: string }>({
        cancelled: true,
        note: '',
    });

    const send = (cancelled: boolean) => {
        form.transform((data) => ({ ...data, cancelled }));
        form.put(procurements.status.update(procurementId).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setAsking(false);
            },
        });
    };

    return (
        <section className="space-y-3 rounded-md border border-border bg-card p-4">
            <div className="space-y-2">
                <p className="section-label">Status Progres</p>
                <StatusBadge
                    label={status.name}
                    category={status.category}
                    className="text-sm"
                />
            </div>

            <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
                <Sparkles className="mt-0.5 size-3.5 shrink-0" />
                <span>
                    {isCancelled
                        ? 'Pengadaan ini dibatalkan. Status otomatis berhenti sampai pembatalan dicabut.'
                        : `Diperbarui otomatis sesuai proses.${rule ? ` ${rule}` : ''}`}
                </span>
            </p>

            {journey && !isCancelled && <JourneyView journey={journey} />}

            {history.length > 0 && (
                <div className="border-t border-border pt-3">
                    <button
                        type="button"
                        className="flex w-full items-center justify-between text-xs font-medium text-foreground hover:text-primary"
                        aria-expanded={showHistory}
                        onClick={() => setShowHistory(!showHistory)}
                    >
                        Riwayat status ({history.length})
                        <ChevronDown
                            className={cn(
                                'size-3.5 transition-transform',
                                showHistory && 'rotate-180',
                            )}
                        />
                    </button>
                    {showHistory && (
                        <ol className="mt-2 space-y-2 border-l border-border pl-3">
                            {history.map((entry, index) => (
                                <li key={entry.id} className="relative text-xs">
                                    <span
                                        className={cn(
                                            'absolute top-1 -left-[17px] size-2 rounded-full',
                                            index === 0
                                                ? 'bg-primary'
                                                : 'bg-border',
                                        )}
                                        aria-hidden
                                    />
                                    <p className="font-medium text-foreground">
                                        {entry.name}
                                    </p>
                                    <p className="tabular text-muted-foreground">
                                        {entry.at
                                            ? formatDateTime(entry.at)
                                            : '—'}
                                        {entry.by ? ` · ${entry.by}` : ''}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </div>
            )}

            {editable && isCancelled && (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="w-full"
                    disabled={form.processing}
                    onClick={() => send(false)}
                >
                    <RotateCcw className="size-3.5" />
                    Cabut Pembatalan
                </Button>
            )}

            {editable && !isCancelled && !asking && (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="w-full text-destructive hover:text-destructive"
                    onClick={() => setAsking(true)}
                >
                    <Ban className="size-3.5" />
                    Batalkan Pengadaan
                </Button>
            )}

            {editable && !isCancelled && asking && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        send(true);
                    }}
                    className="space-y-2 border-t border-border pt-3"
                >
                    <Label htmlFor="cancel-note">Alasan pembatalan</Label>
                    <Textarea
                        id="cancel-note"
                        rows={2}
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                        placeholder="Wajib diisi"
                    />
                    <InputError message={form.errors.note} />
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="flex-1"
                            onClick={() => setAsking(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            variant="destructive"
                            className="flex-1"
                            disabled={form.processing}
                        >
                            Batalkan Pengadaan
                        </Button>
                    </div>
                </form>
            )}
        </section>
    );
}

/**
 * The way to Terkontrak: how far the job has come, how many statuses are
 * left, and every status of its path (done ones ticked with when they were
 * entered, the current one marked, the rest still ahead).
 */
function JourneyView({ journey }: { journey: StatusJourney }) {
    const { position, to_contract: toContract, remaining } = journey;
    const contracted = remaining === 0 && position !== null;
    const percent =
        position === null
            ? 0
            : Math.min(100, Math.round((position / toContract) * 100));

    return (
        <div className="space-y-3 border-t border-border pt-3">
            {position !== null && (
                <div className="space-y-1.5">
                    <div className="flex items-baseline justify-between gap-2 text-xs">
                        <span className="font-medium text-foreground">
                            {contracted
                                ? 'Sudah terkontrak'
                                : `Tahap ${position} dari ${toContract}`}
                        </span>
                        {!contracted && remaining !== null && (
                            <span className="tabular font-semibold text-primary">
                                {remaining} status lagi menuju Terkontrak
                            </span>
                        )}
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{ width: `${percent}%` }}
                        />
                    </div>
                    {journey.revision && (
                        <p className="text-xs text-destructive">
                            Sedang {journey.revision.toLowerCase()}: kembali ke
                            tahap persetujuan setelah diperbaiki.
                        </p>
                    )}
                </div>
            )}

            <ol className="space-y-1">
                {journey.steps.map((step) => (
                    <li
                        key={step.key}
                        className={cn(
                            'flex items-start gap-2 rounded-sm px-1.5 py-1 text-xs',
                            step.state === 'current' && 'bg-amber-500/10',
                        )}
                    >
                        <span
                            className={cn(
                                'mt-px flex size-4 shrink-0 items-center justify-center rounded-full border',
                                step.state === 'done' &&
                                    'border-primary bg-primary text-primary-foreground',
                                step.state === 'current' &&
                                    'border-amber-500 bg-amber-500/20',
                                step.state === 'upcoming' && 'border-border',
                            )}
                            aria-hidden
                        >
                            {step.state === 'done' && (
                                <Check className="size-2.5" />
                            )}
                            {step.state === 'current' && (
                                <span className="size-1.5 rounded-full bg-amber-600" />
                            )}
                        </span>
                        <span className="min-w-0 flex-1">
                            <span
                                className={cn(
                                    'block',
                                    step.state === 'upcoming'
                                        ? 'text-muted-foreground'
                                        : 'font-medium text-foreground',
                                    step.key === 'terkontrak' &&
                                        'font-semibold',
                                )}
                            >
                                {step.name}
                                {step.state === 'current' && (
                                    <span className="ml-1 text-[10px] font-semibold tracking-wide text-amber-800 uppercase dark:text-amber-300">
                                        · sekarang
                                    </span>
                                )}
                            </span>
                            {step.at && (
                                <span className="tabular block text-[11px] text-muted-foreground">
                                    {formatDateTime(step.at)}
                                </span>
                            )}
                        </span>
                    </li>
                ))}
            </ol>
        </div>
    );
}
