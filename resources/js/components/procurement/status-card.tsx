import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import procurements from '@/routes/procurements';
import type { StatusCategory, StatusOption } from '@/types';

export function StatusCard({
    procurementId,
    status,
    statuses,
    editable,
}: {
    procurementId: number;
    status: { id: number; name: string; category: StatusCategory };
    statuses: StatusOption[];
    editable: boolean;
}) {
    const form = useForm({
        progress_status_id: status.id,
        note: '',
    });

    return (
        <section className="space-y-4 rounded-md border border-border bg-card p-4">
            <div className="space-y-2">
                <p className="section-label">Status Progres</p>
                <StatusBadge
                    label={status.name}
                    category={status.category}
                    className="text-sm"
                />
            </div>

            {editable && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(
                            procurements.status.update(procurementId).url,
                            {
                                preserveScroll: true,
                                onSuccess: () => form.setData('note', ''),
                            },
                        );
                    }}
                    className="space-y-3 border-t border-border pt-3"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="progress_status_id">Ubah Status</Label>
                        <Select
                            value={String(form.data.progress_status_id)}
                            onValueChange={(value) =>
                                form.setData(
                                    'progress_status_id',
                                    Number(value),
                                )
                            }
                        >
                            <SelectTrigger
                                id="progress_status_id"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.progress_status_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status-note">Catatan</Label>
                        <Textarea
                            id="status-note"
                            rows={2}
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                            placeholder="Opsional"
                        />
                        <InputError message={form.errors.note} />
                    </div>

                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        className="w-full"
                        disabled={
                            form.processing ||
                            form.data.progress_status_id === status.id
                        }
                    >
                        Perbarui Status
                    </Button>
                </form>
            )}
        </section>
    );
}
