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
import type { Option, PicOption } from '@/types';

/** A contract number format, and whether it asks for a category. */
export type ContractFormatOption = Option & { usesWorkCategory?: boolean };

export type ProcurementFormOptions = {
    contractNumberFormats: ContractFormatOption[];
    /** The categories of an SPK or PJ, from the Jenis Kontrak master data. */
    contractTypes: Option[];
    /** Low, Moderat, High, Extreme. */
    csmsRiskLevels: Array<{ value: string; label: string }>;
    workDirectors: Option[];
    targetUnits: Option[];
    budgetSources: Option[];
    planners: PicOption[];
};

export type ProcurementFormValues = {
    contract_number_format_id: number | null;
    /** The CSMS risk level the user states (low, moderate, high, extreme). */
    csms_risk_level: string;
    /** The TOR's Tanggal Terbit; empty, the TOR takes its generating day. */
    tor_issue_date: string;
    name: string;
    work_director_id: number | null;
    target_unit_ids: number[];
    budget_source_id: number | null;
    prk_number: string;
    proposal_memo_number: string;
    proposal_memo_date: string;
    pr_po_number: string;
    coa_number: string;
    sr_number: string;
    wo_number: string;
    hpe_value: number;
    value_after_negotiation: number | null;
    notes: string;
    /** Set by procurement; a work proposal never carries it. */
    target_completion_date?: string;
    planner_id?: number | null;
    /** Supporting files, sent with a work proposal. */
    attachments?: File[];
    /** The signed TOR scan, when the proposer already has one. */
    tor_file?: File | null;
    /** The Nota Dinas Usulan scan. */
    memo_file?: File | null;
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
        ...(asksForTor ? { tor_file: null, memo_file: null } : {}),
        ...(!isProposal
            ? {
                  target_completion_date:
                      initialValues?.target_completion_date ?? '',
              }
            : {}),
        contract_number_format_id: startingFormatId,
        csms_risk_level: initialValues?.csms_risk_level ?? '',
        tor_issue_date: initialValues?.tor_issue_date ?? '',
        name: initialValues?.name ?? '',
        work_director_id:
            initialValues?.work_director_id ??
            (isProposal ? (proposal?.workDirectorId ?? null) : null),
        target_unit_ids: initialValues?.target_unit_ids ?? [],
        budget_source_id: initialValues?.budget_source_id ?? null,
        prk_number: initialValues?.prk_number ?? '',
        proposal_memo_number: initialValues?.proposal_memo_number ?? '',
        proposal_memo_date: initialValues?.proposal_memo_date ?? '',
        pr_po_number: initialValues?.pr_po_number ?? '',
        coa_number: initialValues?.coa_number ?? '',
        sr_number: initialValues?.sr_number ?? '',
        wo_number: initialValues?.wo_number ?? '',
        hpe_value: initialValues?.hpe_value ?? 0,
        value_after_negotiation: initialValues?.value_after_negotiation ?? null,
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

    /**
     * Switch the kind of contract. Its category is picked by the planning
     * PIC, and its number given by the executor on the Kontrak / Lampiran
     * Surat Pesanan step.
     */
    const chooseFormat = (formatId: number) =>
        setData('contract_number_format_id', formatId);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit(form);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <section className="space-y-4 rounded-md border border-border bg-card p-5">
                <p className="section-label">Identitas Pengadaan</p>

                {/* Asked of whoever registers or proposes the job. */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="grid content-start gap-2">
                        <Label htmlFor="contract_number_format_id">
                            Jenis Kontrak
                        </Label>
                        <Select
                            value={
                                data.contract_number_format_id === null
                                    ? undefined
                                    : String(data.contract_number_format_id)
                            }
                            onValueChange={(value) =>
                                chooseFormat(Number(value))
                            }
                        >
                            <SelectTrigger id="contract_number_format_id">
                                <SelectValue placeholder="Pilih SPK / PJ / SPPL" />
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
                        <p className="text-xs text-muted-foreground">
                            Metode pengadaan dan kategori (Barang, Jasa, KHS,
                            Barang dan Jasa) dipilih PIC Perencana di detail
                            pengadaan. Nomor kontrak diisi PIC Pelaksana pada
                            tahapan Kontrak (SPK/PJ) atau Lampiran Surat Pesanan
                            (SPPL).
                        </p>
                    </div>

                    <div className="grid content-start gap-2">
                        <Label htmlFor="csms_risk_level">
                            Level Risiko CSMS
                        </Label>
                        <Select
                            value={data.csms_risk_level || undefined}
                            onValueChange={(value) =>
                                setData('csms_risk_level', value)
                            }
                        >
                            <SelectTrigger id="csms_risk_level">
                                <SelectValue placeholder="Pilih level risiko" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.csmsRiskLevels.map((level) => (
                                    <SelectItem
                                        key={level.value}
                                        value={level.value}
                                    >
                                        {level.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.csms_risk_level} />
                        <p className="text-xs text-muted-foreground">
                            Tingkat risiko K3 pekerjaan untuk penilaian CSMS
                            calon mitra.
                        </p>
                    </div>

                    <div className="grid content-start gap-2">
                        <DateField
                            id="tor_issue_date"
                            label="Tanggal Terbit TOR"
                            value={data.tor_issue_date}
                            onChange={(value) =>
                                setData('tor_issue_date', value)
                            }
                            error={errors.tor_issue_date}
                        />
                        <p className="text-xs text-muted-foreground">
                            Tercetak pada kop TOR. Bila dikosongkan, memakai
                            tanggal TOR digenerate.
                        </p>
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
                <section className="space-y-4 rounded-md border border-border bg-card p-5">
                    <div>
                        <p className="section-label">Dokumen Usulan</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Dua dokumen yang diunggah pengusul. TOR otomatis
                            dibuat dari template setelah usulan dikirim:
                            lengkapi lewat <b>Edit TOR</b>, cetak, tanda
                            tangani, lalu unggah di halaman usulan. Bila sudah
                            punya scan TOR bertanda tangan, boleh langsung
                            diunggah di sini. Usulan baru dapat disetujui
                            setelah keduanya terunggah.
                        </p>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <SingleFileField
                            id="tor_file"
                            label="TOR (bertanda tangan)"
                            file={data.tor_file ?? null}
                            onChange={(file) => setData('tor_file', file)}
                            error={
                                (errors as Record<string, string | undefined>)
                                    .tor_file
                            }
                        />
                        <SingleFileField
                            id="memo_file"
                            label="Nota Dinas Usulan"
                            file={data.memo_file ?? null}
                            onChange={(file) => setData('memo_file', file)}
                            error={
                                (errors as Record<string, string | undefined>)
                                    .memo_file
                            }
                        />
                    </div>
                </section>
            )}

            {!isProposal && (
                <section className="space-y-4 rounded-md border border-border bg-card p-5">
                    <p className="section-label">Informasi Tambahan</p>

                    <div className="grid gap-4 md:grid-cols-2">
                        <DateField
                            id="target_completion_date"
                            label="Target Selesai"
                            value={data.target_completion_date ?? ''}
                            onChange={(value) =>
                                setData('target_completion_date', value)
                            }
                            error={errors.target_completion_date}
                        />
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

/**
 * One scanned document of a proposal: pick or drop a file, replace or remove it.
 */
function SingleFileField({
    id,
    label,
    file,
    onChange,
    error,
}: {
    id: string;
    label: string;
    file: File | null;
    onChange: (file: File | null) => void;
    error?: string;
}) {
    const [dragging, setDragging] = useState(false);

    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            {file === null ? (
                <label
                    htmlFor={id}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault();
                        setDragging(false);
                        onChange(event.dataTransfer.files[0] ?? null);
                    }}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed px-4 py-5 text-center text-sm transition-colors hover:border-primary hover:bg-primary/5',
                        dragging
                            ? 'border-primary bg-primary/5'
                            : 'border-border bg-muted/30',
                    )}
                >
                    <Upload className="size-5 text-muted-foreground" />
                    <span className="font-medium text-foreground">
                        Klik atau seret berkas
                    </span>
                    <span className="text-xs text-muted-foreground">
                        PDF, JPG, atau PNG, maks. 20 MB
                    </span>
                </label>
            ) : (
                <div className="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2.5 text-sm">
                    <span className="flex min-w-0 items-center gap-2">
                        <FileText className="size-4 shrink-0 text-primary" />
                        <span className="truncate">{file.name}</span>
                    </span>
                    <button
                        type="button"
                        className="shrink-0 text-xs font-medium text-destructive hover:underline"
                        onClick={() => onChange(null)}
                    >
                        Hapus
                    </button>
                </div>
            )}
            <input
                id={id}
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                className="sr-only"
                onChange={(event) => {
                    onChange(event.target.files?.[0] ?? null);
                    event.target.value = '';
                }}
            />
            <InputError message={error} />
        </div>
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
