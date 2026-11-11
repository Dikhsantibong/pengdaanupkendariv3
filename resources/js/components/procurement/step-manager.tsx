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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { AdjustableStage, ChecklistRow, RemovedStep } from '@/types';

/** Adjusting one stage's steps on this procurement alone. */
export type StepManager = {
    stage: AdjustableStage;
    /** Adding, removing and restoring is open to the user now. */
    canManage: boolean;
    removed: RemovedStep[];
    documentTypes: Array<{ value: number; label: string }>;
};

const STAGE_LABELS: Record<AdjustableStage, string> = {
    perencanaan: 'Perencanaan',
    pelaksanaan: 'Pelaksanaan',
    penyerahan: 'Penyerahan',
};

const END = 'end';
const NONE = 'none';

/**
 * The bar above a stage's steps: add a step to this procurement, or bring
 * back one removed from it. Data Master and other procurements are untouched.
 */
export function StepManagerBar({
    procurementId,
    manager,
    rows,
}: {
    procurementId: number;
    manager: StepManager;
    rows: ChecklistRow[];
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
                rows={rows}
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
    rows,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    procurementId: number;
    manager: StepManager;
    rows: ChecklistRow[];
}) {
    const isHandover = manager.stage === 'penyerahan';
    const form = useForm({
        stage: manager.stage as string,
        name: '',
        description: '',
        is_optional: false,
        after_checklist_id: END,
        document_type_id: NONE,
    });

    const submit = () => {
        form.transform((data) => ({
            stage: data.stage,
            name: data.name,
            description: data.description,
            is_optional: data.is_optional,
            after_checklist_id:
                data.after_checklist_id === END
                    ? null
                    : Number(data.after_checklist_id),
            document_type_id:
                data.document_type_id === NONE
                    ? null
                    : Number(data.document_type_id),
        }));

        form.post(procurements.steps.store(procurementId).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

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
                        Tahapan ini hanya ditambahkan pada pengadaan ini.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="add-step-form"
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="step-name">Nama Tahapan</Label>
                        <Input
                            id="step-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Contoh: Survei Lapangan"
                            autoComplete="off"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="step-description">
                            Keterangan (opsional)
                        </Label>
                        <Textarea
                            id="step-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            rows={2}
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="step-after">Letakkan</Label>
                        <Select
                            value={form.data.after_checklist_id}
                            onValueChange={(value) =>
                                form.setData('after_checklist_id', value)
                            }
                        >
                            <SelectTrigger id="step-after" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={END}>
                                    Di akhir tahapan
                                </SelectItem>
                                {rows.map((row, index) => (
                                    <SelectItem
                                        key={row.id}
                                        value={String(row.id)}
                                    >
                                        Setelah {index + 1}. {row.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.after_checklist_id} />
                    </div>

                    {isHandover ? (
                        <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                            Tahapan penyerahan diselesaikan dengan mengunggah
                            berkasnya pada tahapan itu.
                        </p>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor="step-document">
                                Yang dibutuhkan
                            </Label>
                            <Select
                                value={form.data.document_type_id}
                                onValueChange={(value) =>
                                    form.setData('document_type_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="step-document"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        Cukup ditandai selesai
                                    </SelectItem>
                                    {manager.documentTypes.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={String(type.value)}
                                        >
                                            Dokumen: {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Pilih jenis dokumen bila tahapan ini harus
                                dibuktikan dengan dokumen bertanda tangan. Jenis
                                dokumen baru dibuat di Data Master → Jenis
                                Dokumen.
                            </p>
                            <InputError
                                message={form.errors.document_type_id}
                            />
                        </div>
                    )}

                    <div className="flex items-center justify-between gap-4 rounded-md border border-border px-3 py-2.5">
                        <div className="space-y-0.5">
                            <Label htmlFor="step-optional">Opsional</Label>
                            <p className="text-xs text-muted-foreground">
                                Tahapan opsional tidak menahan pengajuan.
                            </p>
                        </div>
                        <Switch
                            id="step-optional"
                            checked={form.data.is_optional}
                            onCheckedChange={(checked) =>
                                form.setData('is_optional', checked)
                            }
                        />
                    </div>
                    <InputError message={form.errors.stage} />
                </form>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        form="add-step-form"
                        disabled={form.processing}
                    >
                        <Plus className="size-4" />
                        Tambah Tahapan
                    </Button>
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
