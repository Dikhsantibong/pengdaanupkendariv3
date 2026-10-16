import { useForm } from '@inertiajs/react';
import { Ban, RotateCcw, Sparkles } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import procurements from '@/routes/procurements';
import type { StatusCategory } from '@/types';

/**
 * The progress status, set by the system from where the job stands. The only
 * manual action is cancelling the job (Batal) and lifting that again, left
 * to the team leaders.
 */
export function StatusCard({
    procurementId,
    status,
    rule,
    editable,
}: {
    procurementId: number;
    status: { id: number; name: string; category: StatusCategory };
    /** When the current status applies, as the system reads it. */
    rule: string | null;
    editable: boolean;
}) {
    const isCancelled = status.category === 'batal';
    const [asking, setAsking] = useState(false);
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
