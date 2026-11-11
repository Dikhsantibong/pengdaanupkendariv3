import { router, useForm } from '@inertiajs/react';
import { History, Plus, RotateCcw, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { AdjustableStage, AvailableStep, RemovedStep } from '@/types';

/** Adjusting one stage's steps on this procurement alone. */
export type StepManager = {
    stage: AdjustableStage;
    /** Adding, removing and restoring is open to the user now. */
    canManage: boolean;
    removed: RemovedStep[];
    /** Data Master steps of the stage the procurement does not have. */
    available: AvailableStep[];
};

const STAGE_LABELS: Record<AdjustableStage, string> = {
    perencanaan: 'Perencanaan',
    pelaksanaan: 'Pelaksanaan',
    penyerahan: 'Penyerahan',
};

/**
 * The bar above a stage's steps: add a step to this procurement, or bring
 * back one removed from it. Data Master and other procurements are untouched.
 */
export function StepManagerBar({
    procurementId,
    manager,
}: {
    procurementId: number;
    manager: StepManager;
}) {
    const [adding, setAdding] = useState(false);
    const [showRemoved, setShowRemoved] = useState(false);

    if (!manager.canManage && manager.removed.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 border-b border-border bg-muted/30 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between">
            <p className="flex items-center gap-2 text-xs text-muted-foreground">
                <SlidersHorizontal className="size-3.5 shrink-0" />
                {manager.canManage
                    ? 'Tahapan dapat disesuaikan untuk pengadaan ini saja. Data Master dan pengadaan lain tidak berubah.'
                    : 'Sebagian tahapan pengadaan ini telah disesuaikan.'}
            </p>
            <div className="flex shrink-0 flex-wrap items-center gap-2">
                {manager.removed.length > 0 && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setShowRemoved(true)}
                    >
                        <History className="size-3.5" />
                        Tahapan dihapus ({manager.removed.length})
                    </Button>
                )}
                {manager.canManage && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setAdding(true)}
                    >
                        <Plus className="size-3.5" />
                        Tambah Tahapan
                    </Button>
                )}
            </div>

            <AddStepDialog
                open={adding}
                onOpenChange={setAdding}
                procurementId={procurementId}
                manager={manager}
            />
            <RemovedStepsDialog
                open={showRemoved}
                onOpenChange={setShowRemoved}
                procurementId={procurementId}
                manager={manager}
            />
        </div>
    );
}

function AddStepDialog({
    open,
    onOpenChange,
    procurementId,
    manager,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    procurementId: number;
    manager: StepManager;
}) {
    const form = useForm({
        stage: manager.stage as string,
        checklist_item_id: '',
    });
    const chosen = manager.available.find(
        (step) => String(step.id) === form.data.checklist_item_id,
    );

    const submit = () =>
        form.post(procurements.steps.store(procurementId).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    form.clearErrors();
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        Tambah Tahapan {STAGE_LABELS[manager.stage]}
                    </DialogTitle>
                    <DialogDescription>
                        Pilih tahapan dari Data Master yang belum ada pada
                        pengadaan ini. Tahapan ditambahkan pada pengadaan ini
                        saja, mengikuti urutan Data Master.
                    </DialogDescription>
                </DialogHeader>

                {manager.available.length === 0 ? (
                    <p className="rounded-md border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
                        Semua tahapan {STAGE_LABELS[manager.stage]} di Data
                        Master sudah ada pada pengadaan ini.
                        {manager.removed.length > 0 &&
                            ' Tahapan yang dihapus dapat dipulihkan lewat tombol Tahapan dihapus.'}
                    </p>
                ) : (
                    <form
                        id="add-step-form"
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="step-item">
                                Tahapan dari Data Master
                            </Label>
                            <Select
                                value={form.data.checklist_item_id}
                                onValueChange={(value) =>
                                    form.setData('checklist_item_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="step-item"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Pilih tahapan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {manager.available.map((step) => (
                                        <SelectItem
                                            key={step.id}
                                            value={String(step.id)}
                                        >
                                            <span className="flex flex-col">
                                                <span>
                                                    {step.name}
                                                    {step.is_optional
                                                        ? ' (opsional)'
                                                        : ''}
                                                </span>
                                                {step.description && (
                                                    // Tells apart the same name in two contracts.
                                                    <span className="text-xs text-muted-foreground">
                                                        {step.description}
                                                    </span>
                                                )}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={
                                    form.errors.checklist_item_id ??
                                    form.errors.stage
                                }
                            />
                        </div>

                        {chosen && (
                            <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                <p className="font-medium text-foreground">
                                    {chosen.name}
                                </p>
                                <p>
                                    {chosen.description ??
                                        'Tanpa keterangan di Data Master.'}
                                </p>
                                <p className="mt-1">
                                    {chosen.is_optional
                                        ? 'Opsional: tidak menahan pengajuan.'
                                        : 'Wajib: ikut menahan pengajuan sampai selesai.'}
                                </p>
                            </div>
                        )}

                        <p className="text-xs text-muted-foreground">
                            Tahapan baru dibuat di Data Master → Item Checklist.
                        </p>
                    </form>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    {manager.available.length > 0 && (
                        <Button
                            type="submit"
                            form="add-step-form"
                            disabled={
                                form.processing ||
                                form.data.checklist_item_id === ''
                            }
                        >
                            <Plus className="size-4" />
                            Tambah Tahapan
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function RemovedStepsDialog({
    open,
    onOpenChange,
    procurementId,
    manager,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    procurementId: number;
    manager: StepManager;
}) {
    const restore = (step: RemovedStep) =>
        router.post(
            procurements.steps.restore({
                procurement: procurementId,
                exclusion: step.id,
            }).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (manager.removed.length <= 1) {
                        onOpenChange(false);
                    }
                },
            },
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        Tahapan {STAGE_LABELS[manager.stage]} yang Dihapus
                    </DialogTitle>
                    <DialogDescription>
                        Tahapan ini dihapus dari pengadaan ini saja. Pulihkan
                        untuk mengerjakannya kembali.
                    </DialogDescription>
                </DialogHeader>

                {manager.removed.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        Tidak ada tahapan yang dihapus.
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-md border border-border">
                        {manager.removed.map((step) => (
                            <li
                                key={step.id}
                                className="flex items-center justify-between gap-3 px-3 py-2.5"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {step.name}
                                        {step.is_custom && (
                                            <span className="ml-2 rounded-sm bg-primary/10 px-1.5 py-0.5 text-[0.6875rem] text-primary">
                                                Tambahan
                                            </span>
                                        )}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Dihapus {step.removed_by ?? '—'} ·{' '}
                                        {formatDateTime(step.removed_at)}
                                    </p>
                                </div>
                                {manager.canManage && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => restore(step)}
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Pulihkan
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}
