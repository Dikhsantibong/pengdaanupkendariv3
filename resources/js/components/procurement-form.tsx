import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CurrencyInput } from '@/components/currency-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    contractNumberFormats: Option[];
    workDirectors: Option[];
    targetUnits: Option[];
    procurementMethods: Option[];
    budgetSources: Option[];
    progressStatuses: StatusOption[];
    defaultProgressStatusId: number | null;
    planners: Option[];
};

export type ProcurementFormValues = {
    contract_number_format_id: number | null;
    number: string;
    name: string;
    partner_name: string;
    partner_director_name: string;
    partner_address: string;
    work_director_id: number | null;
    target_unit_ids: number[];
    procurement_method_id: number | null;
    budget_source_id: number | null;
    prk_number: string;
    proposal_memo_number: string;
    proposal_memo_date: string;
    icc_memo_number: string;
    icc_memo_date: string;
    pr_po_number: string;
    coa_number: string;
    wo_number: string;
    quotation_number: string;
    quotation_date: string;
    hpe_value: number;
    value_after_negotiation: number | null;
    progress_status_id: number | null;
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
    nextNumbers = {},
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
    /** The next free number of each format, keyed by format id. */
    nextNumbers?: Record<number, string>;
}) {
    const firstFormatId = options.contractNumberFormats[0]?.value ?? null;

    const startingFormatId =
        initialValues?.contract_number_format_id ?? firstFormatId;

    const form = useForm<ProcurementFormValues>({
        ...(withPlanner
            ? { planner_id: initialValues?.planner_id ?? null }
            : {}),
        contract_number_format_id: startingFormatId,
        number:
            initialValues?.number ??
            (startingFormatId === null
                ? ''
                : (nextNumbers[startingFormatId] ?? '')),
        name: initialValues?.name ?? '',
        partner_name: initialValues?.partner_name ?? '',
        partner_director_name: initialValues?.partner_director_name ?? '',
        partner_address: initialValues?.partner_address ?? '',
        work_director_id: initialValues?.work_director_id ?? null,
        target_unit_ids: initialValues?.target_unit_ids ?? [],
        procurement_method_id: initialValues?.procurement_method_id ?? null,
        budget_source_id: initialValues?.budget_source_id ?? null,
        prk_number: initialValues?.prk_number ?? '',
        proposal_memo_number: initialValues?.proposal_memo_number ?? '',
        proposal_memo_date: initialValues?.proposal_memo_date ?? '',
        icc_memo_number: initialValues?.icc_memo_number ?? '',
        icc_memo_date: initialValues?.icc_memo_date ?? '',
        pr_po_number: initialValues?.pr_po_number ?? '',
        coa_number: initialValues?.coa_number ?? '',
        wo_number: initialValues?.wo_number ?? '',
        quotation_number: initialValues?.quotation_number ?? '',
        quotation_date: initialValues?.quotation_date ?? '',
        hpe_value: initialValues?.hpe_value ?? 0,
        value_after_negotiation: initialValues?.value_after_negotiation ?? null,
        progress_status_id:
            initialValues?.progress_status_id ??
            options.defaultProgressStatusId ??
            null,
        notes: initialValues?.notes ?? '',
    });

    /**
     * Tick or untick a unit, keeping the list in master data order so the
     * first unit shown is always the same one.
     */
    const toggleUnit = (unitId: number, checked: boolean) => {
        const chosen = new Set(form.data.target_unit_ids);

        if (checked) {
            chosen.add(unitId);
        } else {
            chosen.delete(unitId);
        }

        form.setData(
            'target_unit_ids',
            options.targetUnits
                .map((option) => option.value)
                .filter((value) => chosen.has(value)),
        );
    };

    const unitError =
        form.errors.target_unit_ids ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('target_unit_ids.'),
        )?.[1];

    const { data, setData, errors, processing } = form;

    const suggestion =
        data.contract_number_format_id === null
            ? null
            : (nextNumbers[data.contract_number_format_id] ?? null);

    /**
     * Switch the kind of contract number.
     *
     * The number follows along only while it is still an untouched suggestion.
     * Once it has been corrected by hand — or belongs to a procurement that is
     * already numbered — it is left alone, and the author can pull the running
     * number in deliberately with the link beside the field.
     */
    const chooseFormat = (formatId: number | null) => {
        const untouched = Object.values(nextNumbers).includes(data.number);

        setData((current) => ({
            ...current,
            contract_number_format_id: formatId,
            number:
                untouched || current.number === ''
                    ? formatId === null
                        ? ''
                        : (nextNumbers[formatId] ?? '')
                    : current.number,
        }));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit(form);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <section className="space-y-4 rounded-md border border-border bg-card p-5">
                <p className="section-label">Identitas Pengadaan</p>

                <div className="grid gap-4 sm:grid-cols-[9rem_1fr]">
                    <div className="grid gap-2">
                        <Label htmlFor="contract_number_format_id">
                            Jenis No Kontrak
                        </Label>
                        <Select
                            value={
                                data.contract_number_format_id === null
                                    ? NONE
                                    : String(data.contract_number_format_id)
                            }
                            onValueChange={(value) =>
                                chooseFormat(
                                    value === NONE ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger id="contract_number_format_id">
                                <SelectValue placeholder="Pilih" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.contractNumberFormats.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors.contract_number_format_id}
                        />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor="number">No Kontrak</Label>
                            {suggestion !== null &&
                                suggestion !== data.number && (
                                    <button
                                        type="button"
                                        className="text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        onClick={() =>
                                            setData('number', suggestion)
                                        }
                                    >
                                        Pakai nomor otomatis
                                    </button>
                                )}
                        </div>
                        <Input
                            id="number"
                            className="tabular"
                            value={data.number}
                            onChange={(event) =>
                                setData('number', event.target.value)
                            }
                            placeholder="KDD001.SPK/612/UPKD/2026"
                            autoComplete="off"
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            Terisi otomatis mengikuti urutan berjalan jenis yang
                            dipilih, dan masih bisa diubah bila perlu.
                        </p>
                        <InputError message={errors.number} />
                    </div>
                </div>

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

                    <div className="grid gap-2 md:row-span-3">
                        <div className="flex items-baseline justify-between gap-2">
                            <Label>Unit Tujuan</Label>
                            <span className="text-xs text-muted-foreground">
                                {data.target_unit_ids.length} dipilih
                            </span>
                        </div>
                        <div
                            role="group"
                            aria-label="Unit Tujuan"
                            className="grid max-h-56 gap-1.5 overflow-y-auto rounded-md border border-input px-3 py-2.5"
                        >
                            {options.targetUnits.map((option) => (
                                <label
                                    key={option.value}
                                    className="flex cursor-pointer items-center gap-2.5 text-sm"
                                >
                                    <Checkbox
                                        checked={data.target_unit_ids.includes(
                                            option.value,
                                        )}
                                        onCheckedChange={(next) =>
                                            toggleUnit(
                                                option.value,
                                                next === true,
                                            )
                                        }
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Pilih satu atau lebih unit yang dilayani pengadaan
                            ini.
                        </p>
                        <InputError message={unitError} />
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
                <p className="section-label">Calon Mitra</p>

                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        id="partner_name"
                        label="Nama Calon Mitra"
                        value={data.partner_name}
                        onChange={(value) => setData('partner_name', value)}
                        placeholder="Contoh: PT Konstruksi Indonesia"
                        error={errors.partner_name}
                    />

                    <TextField
                        id="partner_director_name"
                        label="Nama Direktur"
                        value={data.partner_director_name}
                        onChange={(value) =>
                            setData('partner_director_name', value)
                        }
                        error={errors.partner_director_name}
                    />

                    <TextField
                        id="quotation_number"
                        label="Nomor Surat Penawaran"
                        value={data.quotation_number}
                        onChange={(value) => setData('quotation_number', value)}
                        placeholder="Contoh: 012/PMR/X/2026"
                        error={errors.quotation_number}
                    />

                    <DateField
                        id="quotation_date"
                        label="Tanggal Surat Penawaran"
                        value={data.quotation_date}
                        onChange={(value) => setData('quotation_date', value)}
                        error={errors.quotation_date}
                    />

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="partner_address">Alamat Perusahaan</Label>
                        <Textarea
                            id="partner_address"
                            value={data.partner_address}
                            onChange={(event) =>
                                setData('partner_address', event.target.value)
                            }
                            rows={3}
                            placeholder="Alamat lengkap kantor / perusahaan calon mitra"
                        />
                        <InputError message={errors.partner_address} />
                    </div>
                </div>
            </section>

            <section className="space-y-4 rounded-md border border-border bg-card p-5">
                <p className="section-label">Usulan Pekerjaan</p>

                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        id="prk_number"
                        label="Nomor PRK"
                        value={data.prk_number}
                        onChange={(value) => setData('prk_number', value)}
                        placeholder="Contoh: 2026.AO.01.001"
                        error={errors.prk_number}
                    />
                    <TextField
                        id="coa_number"
                        label="Nomor COA"
                        value={data.coa_number}
                        onChange={(value) => setData('coa_number', value)}
                        placeholder="Contoh: 5110100000"
                        error={errors.coa_number}
                    />

                    <TextField
                        id="pr_po_number"
                        label="Nomor PR/PO"
                        value={data.pr_po_number}
                        onChange={(value) => setData('pr_po_number', value)}
                        placeholder="Kosongkan bila belum tersedia"
                        error={errors.pr_po_number}
                    />
                    <TextField
                        id="wo_number"
                        label="Nomor WO"
                        value={data.wo_number}
                        onChange={(value) => setData('wo_number', value)}
                        placeholder="Contoh: WO-2026-0042"
                        error={errors.wo_number}
                    />

                    <TextField
                        id="proposal_memo_number"
                        label="Nomor Nota Dinas Usulan"
                        value={data.proposal_memo_number}
                        onChange={(value) =>
                            setData('proposal_memo_number', value)
                        }
                        placeholder="Contoh: ND-021/USL/2026"
                        error={errors.proposal_memo_number}
                    />
                    <DateField
                        id="proposal_memo_date"
                        label="Tanggal Nota Dinas Usulan"
                        value={data.proposal_memo_date}
                        onChange={(value) =>
                            setData('proposal_memo_date', value)
                        }
                        error={errors.proposal_memo_date}
                    />

                    <TextField
                        id="icc_memo_number"
                        label="Nomor Nota Dinas ke Manager"
                        value={data.icc_memo_number}
                        onChange={(value) => setData('icc_memo_number', value)}
                        placeholder="Contoh: ND-014/ND-MGR/2026"
                        error={errors.icc_memo_number}
                    />
                    <DateField
                        id="icc_memo_date"
                        label="Tanggal Nota Dinas ke Manager"
                        value={data.icc_memo_date}
                        onChange={(value) => setData('icc_memo_date', value)}
                        error={errors.icc_memo_date}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="hpe_value">Nilai (Sebelum Nego)</Label>
                        <CurrencyInput
                            id="hpe_value"
                            value={data.hpe_value}
                            onValueChange={(next) => setData('hpe_value', next)}
                        />
                        <p className="text-xs text-muted-foreground">
                            Nilai HPE / anggaran sebelum negosiasi.
                        </p>
                        <InputError message={errors.hpe_value} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="value_after_negotiation">
                            Nilai Setelah Nego
                        </Label>
                        <CurrencyInput
                            id="value_after_negotiation"
                            value={data.value_after_negotiation ?? 0}
                            onValueChange={(next) =>
                                setData(
                                    'value_after_negotiation',
                                    next === 0 ? null : next,
                                )
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Kosongkan bila negosiasi belum dilakukan.
                        </p>
                        <InputError message={errors.value_after_negotiation} />
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

/** A labelled, optional text field for a reference number. */
function TextField({
    id,
    label,
    value,
    onChange,
    placeholder,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                autoComplete="off"
            />
            <InputError message={error} />
        </div>
    );
}

/** A labelled, optional date field for memo dates. */
function DateField({
    id,
    label,
    value,
    onChange,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="date"
                className="tabular"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} />
        </div>
    );
}
