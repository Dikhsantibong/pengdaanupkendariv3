import { useForm } from '@inertiajs/react';
import { FileText, Plus, Upload, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CurrencyInput } from '@/components/currency-input';
import InputError from '@/components/input-error';
import { PicSelect } from '@/components/procurement/pic-select';
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
import { cn } from '@/lib/utils';
import type { Option, PicOption, StatusOption } from '@/types';

const NONE = 'none';

/** A contract number format, and whether it asks for a category. */
export type ContractFormatOption = Option & { usesWorkCategory?: boolean };

export type ProcurementFormOptions = {
    contractNumberFormats: ContractFormatOption[];
    /** The categories of an SPK or PJ, from the Jenis Kontrak master data. */
    contractTypes: Option[];
    workDirectors: Option[];
    targetUnits: Option[];
    procurementMethods: Option[];
    budgetSources: Option[];
    progressStatuses: StatusOption[];
    defaultProgressStatusId: number | null;
    planners: PicOption[];
};

export type ProcurementFormValues = {
    contract_number_format_id: number | null;
    contract_type_id: number | null;
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
    sr_number: string;
    wo_number: string;
    quotation_number: string;
    quotation_date: string;
    hpe_value: number;
    value_after_negotiation: number | null;
    progress_status_id: number | null;
    notes: string;
    planner_id?: number | null;
    /** Supporting files, sent with a work proposal. */
    attachments?: File[];
};

/** How the form behaves for someone who may only propose work. */
export type ProposalMode = {
    enabled: boolean;
    workDirectorId: number | null;
    division: string | null;
};

export function ProcurementForm({
    options,
    initialValues,
    submitLabel,
    onSubmit,
    onCancel,
    withPlanner = false,
    nextNumbers = {},
    proposal,
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
    /**
     * Proposal mode: a staff member or TL proposes work for their division.
     * Contract numbering, partner, PIC and progress are left to procurement;
     * the division is fixed and supporting files can be attached.
     */
    proposal?: ProposalMode;
}) {
    const isProposal = proposal?.enabled === true;
    // A new proposal is told its TOR will be generated once it is sent.
    const asksForTor = isProposal && initialValues === undefined;

    const firstFormatId = options.contractNumberFormats[0]?.value ?? null;

    const startingFormatId = isProposal
        ? (initialValues?.contract_number_format_id ?? null)
        : (initialValues?.contract_number_format_id ?? firstFormatId);

    const form = useForm<ProcurementFormValues>({
        ...(withPlanner && !isProposal
            ? { planner_id: initialValues?.planner_id ?? null }
            : {}),
        ...(asksForTor ? { attachments: [] } : {}),
        contract_number_format_id: startingFormatId,
        contract_type_id: initialValues?.contract_type_id ?? null,
        number:
            initialValues?.number ??
            (startingFormatId === null
                ? ''
                : (nextNumbers[startingFormatId] ?? '')),
        name: initialValues?.name ?? '',
        partner_name: initialValues?.partner_name ?? '',
        partner_director_name: initialValues?.partner_director_name ?? '',
        partner_address: initialValues?.partner_address ?? '',
        work_director_id:
            initialValues?.work_director_id ??
            (isProposal ? (proposal?.workDirectorId ?? null) : null),
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
        sr_number: initialValues?.sr_number ?? '',
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
    const usesWorkCategory = (formatId: number | null): boolean =>
        options.contractNumberFormats.find(
            (option) => option.value === formatId,
        )?.usesWorkCategory === true;

    const chooseFormat = (formatId: number | null) => {
        const untouched = Object.values(nextNumbers).includes(data.number);

        setData((current) => ({
            ...current,
            contract_number_format_id: formatId,
            // The category only belongs to the formats that ask for it.
            contract_type_id: usesWorkCategory(formatId)
                ? current.contract_type_id
                : null,
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

                {!isProposal && (
                    <>
                        <div className="grid gap-4 sm:grid-cols-[9rem_1fr]">
                            <div className="grid gap-2">
                                <Label htmlFor="contract_number_format_id">
                                    Jenis No Kontrak
                                </Label>
                                <Select
                                    value={
                                        data.contract_number_format_id === null
                                            ? NONE
                                            : String(
                                                  data.contract_number_format_id,
                                              )
                                    }
                                    onValueChange={(value) =>
                                        chooseFormat(
                                            value === NONE
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger id="contract_number_format_id">
                                        <SelectValue placeholder="Pilih" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.contractNumberFormats.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={String(option.value)}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
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
                                                    setData(
                                                        'number',
                                                        suggestion,
                                                    )
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
                                    Terisi otomatis mengikuti urutan berjalan
                                    jenis yang dipilih, dan masih bisa diubah
                                    bila perlu.
                                </p>
                                <InputError message={errors.number} />
                            </div>
                        </div>

                        {usesWorkCategory(data.contract_number_format_id) && (
                            <div className="grid gap-2">
                                <Label id="contract_type_label">
                                    Kategori{' '}
                                    {options.contractNumberFormats.find(
                                        (option) =>
                                            option.value ===
                                            data.contract_number_format_id,
                                    )?.label ?? ''}
                                    <span className="text-destructive">*</span>
                                </Label>
                                {options.contractTypes.length === 0 ? (
                                    <p className="rounded-md border border-dashed border-border px-3 py-2.5 text-sm text-muted-foreground">
                                        Belum ada Jenis Kontrak aktif. Tambahkan
                                        di Data Master → Jenis Kontrak.
                                    </p>
                                ) : (
                                    <div
                                        role="radiogroup"
                                        aria-labelledby="contract_type_label"
                                        className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
                                    >
                                        {options.contractTypes.map((type) => {
                                            const selected =
                                                data.contract_type_id ===
                                                type.value;

                                            return (
                                                <button
                                                    key={type.value}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={selected}
                                                    onClick={() =>
                                                        setData(
                                                            'contract_type_id',
                                                            type.value,
                                                        )
                                                    }
                                                    className={
                                                        selected
                                                            ? 'flex items-start gap-2.5 rounded-md border border-primary bg-primary/5 px-3 py-2.5 text-left text-sm font-medium text-primary ring-1 ring-primary'
                                                            : 'flex items-start gap-2.5 rounded-md border border-border bg-card px-3 py-2.5 text-left text-sm text-foreground transition-colors hover:bg-muted'
                                                    }
                                                >
                                                    <span
                                                        className={
                                                            selected
                                                                ? 'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border-4 border-primary'
                                                                : 'mt-0.5 size-4 shrink-0 rounded-full border border-muted-foreground/50'
                                                        }
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block">
                                                            {type.label}
                                                        </span>
                                                        {type.description && (
                                                            <span className="block text-xs font-normal text-muted-foreground">
                                                                {
                                                                    type.description
                                                                }
                                                            </span>
                                                        )}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Pilihan diambil dari Data Master → Jenis
                                    Kontrak, yang juga menentukan format dokumen
                                    SPK/PJ yang digenerate.
                                </p>
                                <InputError message={errors.contract_type_id} />
                            </div>
                        )}
                    </>
                )}

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
                    {isProposal && proposal?.workDirectorId ? (
                        <div className="grid gap-2">
                            <Label>Direksi Pekerjaan (yang menyetujui)</Label>
                            <div className="flex h-9 items-center rounded-md border border-border bg-muted/40 px-3 text-sm font-medium">
                                {proposal.division ?? '-'}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Usulan dikirim ke Direksi Pekerjaan bidang Anda
                                untuk disetujui.
                            </p>
                        </div>
                    ) : (
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
                    )}

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

                    {!isProposal && (
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
                                    setData(
                                        'procurement_method_id',
                                        Number(value),
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="procurement_method_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Pilih metode pengadaan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.procurementMethods.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={errors.procurement_method_id}
                            />
                        </div>
                    )}

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

            {!isProposal && (
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
                            onChange={(value) =>
                                setData('quotation_number', value)
                            }
                            placeholder="Contoh: 012/PMR/X/2026"
                            error={errors.quotation_number}
                        />

                        <DateField
                            id="quotation_date"
                            label="Tanggal Surat Penawaran"
                            value={data.quotation_date}
                            onChange={(value) =>
                                setData('quotation_date', value)
                            }
                            error={errors.quotation_date}
                        />

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="partner_address">
                                Alamat Perusahaan
                            </Label>
                            <Textarea
                                id="partner_address"
                                value={data.partner_address}
                                onChange={(event) =>
                                    setData(
                                        'partner_address',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                placeholder="Alamat lengkap kantor / perusahaan calon mitra"
                            />
                            <InputError message={errors.partner_address} />
                        </div>
                    </div>
                </section>
            )}

            <section className="space-y-4 rounded-md border border-border bg-card p-5">
                <div>
                    <p className="section-label">Nomor Referensi</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Setiap nomor dapat diisi lebih dari satu — klik{' '}
                        <b>Tambah</b> untuk menambah baris.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <MultiValueField
                        id="prk_number"
                        label="Nomor PRK"
                        value={data.prk_number}
                        onChange={(value) => setData('prk_number', value)}
                        placeholder="Contoh: 2026.AO.01.001"
                        required={isProposal}
                        error={errors.prk_number}
                    />
                    <MultiValueField
                        id="pr_po_number"
                        label="Nomor PR"
                        value={data.pr_po_number}
                        onChange={(value) => setData('pr_po_number', value)}
                        placeholder="Contoh: 1000012345"
                        required={isProposal}
                        error={errors.pr_po_number}
                    />
                    <MultiValueField
                        id="coa_number"
                        label="Nomor COA"
                        value={data.coa_number}
                        onChange={(value) => setData('coa_number', value)}
                        placeholder="Contoh: 5110100000"
                        required={isProposal}
                        error={errors.coa_number}
                    />
                    <MultiValueField
                        id="sr_number"
                        label="Nomor SR"
                        value={data.sr_number}
                        onChange={(value) => setData('sr_number', value)}
                        placeholder="Contoh: SR-2026-0015"
                        optional
                        error={errors.sr_number}
                    />
                    <MultiValueField
                        id="wo_number"
                        label="Nomor WO"
                        value={data.wo_number}
                        onChange={(value) => setData('wo_number', value)}
                        placeholder="Contoh: WO-2026-0042"
                        optional
                        error={errors.wo_number}
                    />
                </div>
            </section>

            <section className="space-y-4 rounded-md border border-border bg-card p-5">
                <p className="section-label">
                    {isProposal ? 'Nota Dinas Usulan' : 'Nota Dinas & Nilai'}
                </p>

                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        id="proposal_memo_number"
                        label="Nomor Nota Dinas Usulan"
                        value={data.proposal_memo_number}
                        onChange={(value) =>
                            setData('proposal_memo_number', value)
                        }
                        placeholder="Contoh: ND-021/USL/2026"
                        required={isProposal}
                        error={errors.proposal_memo_number}
                    />
                    <DateField
                        id="proposal_memo_date"
                        label="Tanggal Nota Dinas Usulan"
                        value={data.proposal_memo_date}
                        onChange={(value) =>
                            setData('proposal_memo_date', value)
                        }
                        required={isProposal}
                        error={errors.proposal_memo_date}
                    />

                    {!isProposal && (
                        <>
                            <TextField
                                id="icc_memo_number"
                                label="Nomor Nota Dinas ke Manager"
                                value={data.icc_memo_number}
                                onChange={(value) =>
                                    setData('icc_memo_number', value)
                                }
                                placeholder="Contoh: ND-014/ND-MGR/2026"
                                error={errors.icc_memo_number}
                            />
                            <DateField
                                id="icc_memo_date"
                                label="Tanggal Nota Dinas ke Manager"
                                value={data.icc_memo_date}
                                onChange={(value) =>
                                    setData('icc_memo_date', value)
                                }
                                error={errors.icc_memo_date}
                            />
                        </>
                    )}

                    {!isProposal && (
                        <div className="grid gap-2">
                            <Label htmlFor="hpe_value">
                                Nilai (Sebelum Nego)
                            </Label>
                            <CurrencyInput
                                id="hpe_value"
                                value={data.hpe_value}
                                onValueChange={(next) =>
                                    setData('hpe_value', next)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Nilai HPE / anggaran sebelum negosiasi.
                            </p>
                            <InputError message={errors.hpe_value} />
                        </div>
                    )}

                    {!isProposal && (
                        <>
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
                                <InputError
                                    message={errors.value_after_negotiation}
                                />
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
                                        setData(
                                            'progress_status_id',
                                            Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="progress_status_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih status progres" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.progressStatuses.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={String(option.value)}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.progress_status_id}
                                />
                            </div>
                        </>
                    )}
                </div>
            </section>

            {withPlanner && !isProposal && (
                <section className="space-y-4 rounded-md border border-border bg-card p-5">
                    <p className="section-label">Penunjukan PIC Perencana</p>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="planner_id">PIC Perencana</Label>
                            <PicSelect
                                id="planner_id"
                                value={data.planner_id ?? null}
                                options={options.planners}
                                role="planner"
                                emptyLabel="Tunjuk nanti"
                                onChange={(value) =>
                                    setData('planner_id', value)
                                }
                            />
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

            {asksForTor && (
                <section className="flex items-start gap-3 rounded-md border border-primary/30 bg-primary/5 p-5">
                    <FileText className="mt-0.5 size-5 shrink-0 text-primary" />
                    <div className="space-y-1">
                        <p className="text-sm font-semibold text-foreground">
                            TOR digenerate otomatis
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Setelah usulan dikirim, dokumen TOR dibuat dari
                            template dengan data di atas. Lengkapi isinya lewat
                            tombol <b>Edit TOR</b> di halaman usulan sebelum
                            ditinjau Direksi Pekerjaan.
                        </p>
                    </div>
                </section>
            )}

            {asksForTor && (
                <SupportingFilesField
                    files={data.attachments ?? []}
                    onChange={(files) => setData('attachments', files)}
                    error={
                        (errors as Record<string, string | undefined>)
                            .attachments ??
                        Object.entries(errors).find(([key]) =>
                            key.startsWith('attachments.'),
                        )?.[1]
                    }
                />
            )}

            {!isProposal && (
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
            )}

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

/** A field label marking required or optional entries. */
function FieldLabel({
    htmlFor,
    label,
    required = false,
    optional = false,
}: {
    htmlFor: string;
    label: string;
    required?: boolean;
    optional?: boolean;
}) {
    return (
        <Label htmlFor={htmlFor}>
            {label}
            {required && <span className="text-destructive">*</span>}
            {optional && (
                <span className="font-normal text-muted-foreground">
                    (opsional)
                </span>
            )}
        </Label>
    );
}

/** A labelled text field for a reference number. */
function TextField({
    id,
    label,
    value,
    onChange,
    placeholder,
    required = false,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    error?: string;
}) {
    return (
        <div className="grid content-start gap-2">
            <FieldLabel htmlFor={id} label={label} required={required} />
            <Input
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                autoComplete="off"
                required={required}
            />
            <InputError message={error} />
        </div>
    );
}

/**
 * Several reference numbers of one kind, one per row.
 *
 * The rows are kept locally and handed up as one comma separated list, which
 * is how the numbers are stored.
 */
function MultiValueField({
    id,
    label,
    value,
    onChange,
    placeholder,
    required = false,
    optional = false,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    required?: boolean;
    optional?: boolean;
    error?: string;
}) {
    const [rows, setRows] = useState<string[]>(() => {
        const numbers = value
            .split(/[,;\n]+/)
            .map((part) => part.trim())
            .filter((part) => part !== '');

        return numbers.length > 0 ? numbers : [''];
    });

    const update = (next: string[]) => {
        setRows(next);
        onChange(
            next
                .map((part) => part.trim())
                .filter((part) => part !== '')
                .join(', '),
        );
    };

    return (
        <div className="grid content-start gap-2">
            <FieldLabel
                htmlFor={`${id}-0`}
                label={label}
                required={required}
                optional={optional}
            />
            {rows.map((row, index) => (
                <div key={index} className="flex items-center gap-1.5">
                    <Input
                        id={`${id}-${index}`}
                        value={row}
                        onChange={(event) =>
                            update(
                                rows.map((current, position) =>
                                    position === index
                                        ? event.target.value
                                        : current,
                                ),
                            )
                        }
                        placeholder={placeholder}
                        autoComplete="off"
                        required={required && index === 0}
                        aria-label={`${label} ke-${index + 1}`}
                    />
                    {rows.length > 1 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="shrink-0 text-muted-foreground hover:text-destructive"
                            aria-label={`Hapus ${label} ke-${index + 1}`}
                            onClick={() =>
                                update(
                                    rows.filter(
                                        (_, position) => position !== index,
                                    ),
                                )
                            }
                        >
                            <X className="size-4" />
                        </Button>
                    )}
                </div>
            ))}
            <button
                type="button"
                className="flex items-center gap-1 justify-self-start text-xs font-medium text-primary hover:underline"
                onClick={() => setRows([...rows, ''])}
            >
                <Plus className="size-3.5" />
                Tambah {label}
            </button>
            <InputError message={error} />
        </div>
    );
}

/** The most supporting files one proposal may carry. */
const MAX_SUPPORTING_FILES = 10;

/**
 * Optional supporting files sent with a proposal.
 *
 * Several files can be picked at once, and picking again adds to the list
 * rather than replacing it, so files from different folders can be combined.
 */
function SupportingFilesField({
    files,
    onChange,
    error,
}: {
    files: File[];
    onChange: (files: File[]) => void;
    error?: string;
}) {
    const [dragging, setDragging] = useState(false);

    const add = (chosen: File[]) => {
        const known = new Set(files.map((file) => `${file.name}:${file.size}`));
        const fresh = chosen.filter(
            (file) => !known.has(`${file.name}:${file.size}`),
        );

        onChange([...files, ...fresh].slice(0, MAX_SUPPORTING_FILES));
    };

    const full = files.length >= MAX_SUPPORTING_FILES;

    return (
        <section className="space-y-3 rounded-md border border-border bg-card p-5">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <p className="section-label">
                        Dokumen Pendukung{' '}
                        <span className="font-normal normal-case">
                            (opsional)
                        </span>
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Misalnya foto kondisi, spesifikasi, atau perhitungan
                        kebutuhan. Bisa lebih dari satu berkas — PDF, JPG, PNG,
                        Word, atau Excel, maksimal 20 MB per berkas.
                    </p>
                </div>
                <span className="tabular text-xs text-muted-foreground">
                    {files.length}/{MAX_SUPPORTING_FILES} berkas
                </span>
            </div>

            {!full && (
                <label
                    htmlFor="attachments"
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault();
                        setDragging(false);
                        add(Array.from(event.dataTransfer.files));
                    }}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed px-4 py-6 text-center text-sm transition-colors hover:border-primary hover:bg-primary/5',
                        dragging
                            ? 'border-primary bg-primary/5'
                            : 'border-border bg-muted/30',
                    )}
                >
                    <Upload className="size-5 text-muted-foreground" />
                    <span className="font-medium text-foreground">
                        Klik atau seret berkas ke sini
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Pilih beberapa berkas sekaligus (Ctrl/Shift + klik)
                    </span>
                    <input
                        id="attachments"
                        type="file"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                        className="sr-only"
                        onChange={(event) => {
                            add(Array.from(event.target.files ?? []));
                            event.target.value = '';
                        }}
                    />
                </label>
            )}

            {files.length > 0 && (
                <ul className="divide-y divide-border rounded-md border border-border">
                    {files.map((file, index) => (
                        <li
                            key={`${file.name}-${file.size}`}
                            className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                        >
                            <span className="flex min-w-0 items-center gap-2">
                                <FileText className="size-4 shrink-0 text-muted-foreground" />
                                <span className="truncate">{file.name}</span>
                                <span className="tabular shrink-0 text-xs text-muted-foreground">
                                    {file.size > 1024 * 1024
                                        ? `${(file.size / 1024 / 1024).toFixed(1)} MB`
                                        : `${Math.max(1, Math.round(file.size / 1024))} KB`}
                                </span>
                            </span>
                            <button
                                type="button"
                                className="shrink-0 text-xs font-medium text-destructive hover:underline"
                                onClick={() =>
                                    onChange(
                                        files.filter(
                                            (_, position) => position !== index,
                                        ),
                                    )
                                }
                            >
                                Hapus
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <InputError message={error} />
        </section>
    );
}

/** A labelled date field for memo dates. */
function DateField({
    id,
    label,
    value,
    onChange,
    required = false,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    required?: boolean;
    error?: string;
}) {
    return (
        <div className="grid content-start gap-2">
            <FieldLabel htmlFor={id} label={label} required={required} />
            <Input
                id={id}
                type="date"
                className="tabular"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required={required}
            />
            <InputError message={error} />
        </div>
    );
}
