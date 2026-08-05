import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CurrencyInput } from '@/components/currency-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Option, StatusOption } from '@/types';

const NONE = 'none';

export type ProcurementFormOptions = {
    workDirectors: Option[];
    targetUnits: Option[];
    procurementMethods: Option[];
    budgetSources: Option[];
    prRoNumbers: Option[];
    progressStatuses: StatusOption[];
    defaultProgressStatusId: number | null;
    planners: Option[];
};

export type ProcurementFormValues = {
    name: string;
    work_director_id: number | null;
    target_unit_id: number | null;
    procurement_method_id: number | null;
    budget_source_id: number | null;
    pr_ro_number_id: number | null;
    prk_number: string;
    hpe_value: number;
    progress_status_id: number | null;
    target_completion_date: string;
    notes: string;
    planner_id?: number | null;
};

export function ProcurementForm({
    options,
    initialValues,
    submitLabel,
    onSubmit,
    onCancel,
    withPlanner = false,
}: {
    options: ProcurementFormOptions;
    initialValues?: Partial<ProcurementFormValues>;
    submitLabel: string;
    onSubmit: (form: ReturnType<typeof useForm<ProcurementFormValues>>) => void;
    onCancel?: () => void;
    /**
     * Offer the planning PIC on this form. Only the create screen does: later
     * changes belong on the appointment screen, which notifies the handover.
     */
    withPlanner?: boolean;
}) {
    const form = useForm<ProcurementFormValues>({
        ...(withPlanner
            ? { planner_id: initialValues?.planner_id ?? null }
            : {}),
        name: initialValues?.name ?? '',
        work_director_id: initialValues?.work_director_id ?? null,
        target_unit_id: initialValues?.target_unit_id ?? null,
        procurement_method_id: initialValues?.procurement_method_id ?? null,
        budget_source_id: initialValues?.budget_source_id ?? null,
        pr_ro_number_id: initialValues?.pr_ro_number_id ?? null,
        prk_number: initialValues?.prk_number ?? '',
        hpe_value: initialValues?.hpe_value ?? 0,
        progress_status_id:
            initialValues?.progress_status_id ??
            options.defaultProgressStatusId ??
            null,
        target_completion_date: initialValues?.target_completion_date ?? '',
        notes: initialValues?.notes ?? '',
    });

    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit(form);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <section className="space-y-4 rounded-md border border-border bg-card p-5">
                <p className="section-label">Identitas Pengadaan</p>

                <div className="grid gap-2">
                    <Label htmlFor="name">Nama Pengadaan</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        placeholder="Contoh: Pemeliharaan Rutin Mesin Unit 1"
                        autoComplete="off"
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="work_director_id">
                            Direksi Pekerjaan
                        </Label>
                        <Select
                            value={
                                data.work_director_id === null
                                    ? undefined
                                    : String(data.work_director_id)
                            }
                            onValueChange={(value) =>
                                setData('work_director_id', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="work_director_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih direksi pekerjaan" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.workDirectors.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.work_director_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="target_unit_id">Unit Tujuan</Label>
                        <Select
                            value={
                                data.target_unit_id === null
                                    ? undefined
                                    : String(data.target_unit_id)
                            }
                            onValueChange={(value) =>
                                setData('target_unit_id', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="target_unit_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih unit tujuan" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.targetUnits.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.target_unit_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="procurement_method_id">
                            Metode Pengadaan
                        </Label>
                        <Select
                            value={
                                data.procurement_method_id === null
                                    ? undefined
                                    : String(data.procurement_method_id)
                            }
                            onValueChange={(value) =>
                                setData('procurement_method_id', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="procurement_method_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih metode pengadaan" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.procurementMethods.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.procurement_method_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="budget_source_id">
                            Sumber Anggaran
                        </Label>
                        <Select
                            value={
                                data.budget_source_id === null
                                    ? undefined
                                    : String(data.budget_source_id)
                            }
                            onValueChange={(value) =>
                                setData('budget_source_id', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="budget_source_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih sumber anggaran" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.budgetSources.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                        {option.description !== null &&
                                        option.description !== undefined
                                            ? ` — ${option.description.replace(/\.$/, '')}`
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.budget_source_id} />
                    </div>
                </div>
            </section>

            <section className="space-y-4 rounded-md border border-border bg-card p-5">
                <p className="section-label">Referensi &amp; Anggaran</p>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="pr_ro_number_id">Nomor PR/RO</Label>
                        <Select
                            value={
                                data.pr_ro_number_id === null
                                    ? NONE
                                    : String(data.pr_ro_number_id)
                            }
                            onValueChange={(value) =>
                                setData(
                                    'pr_ro_number_id',
                                    value === NONE ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger
                                id="pr_ro_number_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Belum tersedia" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>
                                    Belum tersedia
                                </SelectItem>
                                {options.prRoNumbers.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Daftar nomor PR/RO yang tersedia dari Smart SCM.
                        </p>
                        <InputError message={errors.pr_ro_number_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="prk_number">
                            Nomor PRK (Nota Dinas Usulan)
                        </Label>
                        <Input
                            id="prk_number"
                            value={data.prk_number}
                            onChange={(event) =>
                                setData('prk_number', event.target.value)
                            }
                            placeholder="Contoh: ND-021/PRK/2026"
                            autoComplete="off"
                        />
                        <InputError message={errors.prk_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="hpe_value">Nilai HPE / Anggaran</Label>
                        <CurrencyInput
                            id="hpe_value"
                            value={data.hpe_value}
                            onValueChange={(next) => setData('hpe_value', next)}
                        />
                        <InputError message={errors.hpe_value} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="progress_status_id">
                            Status Progres
                        </Label>
                        <Select
                            value={
                                data.progress_status_id === null
                                    ? undefined
                                    : String(data.progress_status_id)
                            }
                            onValueChange={(value) =>
                                setData('progress_status_id', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="progress_status_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih status progres" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.progressStatuses.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.progress_status_id} />
                    </div>
                </div>
            </section>

            {withPlanner && (
                <section className="space-y-4 rounded-md border border-border bg-card p-5">
                    <p className="section-label">Penunjukan PIC Perencana</p>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="planner_id">PIC Perencana</Label>
                            <Select
                                value={
                                    data.planner_id === null ||
                                    data.planner_id === undefined
                                        ? NONE
                                        : String(data.planner_id)
                                }
                                onValueChange={(value) =>
                                    setData(
                                        'planner_id',
                                        value === NONE ? null : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="planner_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Tunjuk nanti" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        Tunjuk nanti
                                    </SelectItem>
                                    {options.planners.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                PIC yang ditunjuk langsung menerima notifikasi
                                dan hanya dapat melihat pengadaan yang
                                ditugaskan kepadanya. Dapat dikosongkan dan
                                ditunjuk kemudian dari menu Penunjukan PIC.
                            </p>
                            <InputError message={errors.planner_id} />
                        </div>
                    </div>
                </section>
            )}

            <section className="space-y-4 rounded-md border border-border bg-card p-5">
                <p className="section-label">Informasi Tambahan</p>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="target_completion_date">
                            Target Penyelesaian
                        </Label>
                        <Input
                            id="target_completion_date"
                            type="date"
                            value={data.target_completion_date}
                            onChange={(event) =>
                                setData(
                                    'target_completion_date',
                                    event.target.value,
                                )
                            }
                            className="tabular"
                        />
                        <InputError message={errors.target_completion_date} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="notes">Catatan</Label>
                    <Textarea
                        id="notes"
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        rows={3}
                        placeholder="Catatan internal terkait pengadaan ini"
                    />
                    <InputError message={errors.notes} />
                </div>
            </section>

            <div className="flex items-center gap-2">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
                {onCancel && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={processing}
                    >
                        Batal
                    </Button>
                )}
            </div>
        </form>
    );
}
