import { Head, router, useForm } from '@inertiajs/react';
import {
    FileSpreadsheet,
    Pencil,
    Plus,
    Search,
    Trash2,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { CurrencyInput } from '@/components/currency-input';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
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
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import budgetPlans from '@/routes/budget-plans';
import type { Option } from '@/types';

type BudgetPlan = {
    id: number;
    district: string | null;
    prk_induk: string;
    work_director_id: number | null;
    work_director: string | null;
    prk_induk_description: string | null;
    sub_program_description: string | null;
    ai_plan_value: number | null;
    aki_plan_value: number | null;
    wo_number: string | null;
    pr_number: string | null;
    po_stock_type: string | null;
    po_number: string | null;
    allocation: string | null;
    po_item: string | null;
    supplier: string | null;
    po_date: string | null;
    po_value: number | null;
    delivery_date: string | null;
    contract_progress: string | null;
    physical_progress: string | null;
    invoice_value: number | null;
    invoice_number: string | null;
    payment_dates: string | null;
    payment_proof_number: string | null;
    payment_progress: string | null;
    updated_by: string | null;
    updated_at: string | null;
};

type Totals = {
    count: number;
    ai: number;
    aki: number;
    po: number;
    invoice: number;
};

type TextKey =
    | 'district'
    | 'prk_induk'
    | 'prk_induk_description'
    | 'sub_program_description'
    | 'wo_number'
    | 'pr_number'
    | 'po_number'
    | 'po_item'
    | 'supplier'
    | 'po_date'
    | 'delivery_date'
    | 'contract_progress'
    | 'physical_progress'
    | 'invoice_number'
    | 'payment_dates'
    | 'payment_proof_number'
    | 'payment_progress';

type MoneyKey =
    'ai_plan_value' | 'aki_plan_value' | 'po_value' | 'invoice_value';

type BudgetPlanValues = Record<TextKey, string> &
    Record<MoneyKey, number> & {
        work_director_id: string;
        po_stock_type: string;
        allocation: string;
    };

const ALL = 'semua';
const NONE = 'kosong';

/** The column groups of the Anggaran sheet. */
const GROUPS = [
    {
        label: 'Program',
        span: 8,
        className: 'bg-yellow-100 dark:bg-yellow-950/40',
    },
    {
        label: 'Kontrak',
        span: 11,
        className: 'bg-green-100 dark:bg-green-950/40',
    },
    {
        label: 'Fisik / BA',
        span: 1,
        className: 'bg-sky-100 dark:bg-sky-950/40',
    },
    {
        label: 'Pembayaran',
        span: 5,
        className: 'bg-orange-100 dark:bg-orange-950/40',
    },
];

/**
 * Perencanaan Anggaran: the Anggaran role keeps each PRK induk from program
 * (AI / AKI) through contract, physical progress and payment, and points it
 * at the bidang whose proposers pick it on the work proposal form.
 */
export default function BudgetPlansIndex({
    plans,
    totals,
    filters,
    workDirectors,
    canManage,
}: {
    plans: BudgetPlan[];
    totals: Totals;
    filters: { cari: string; bidang: number | null };
    workDirectors: Option[];
    canManage: boolean;
}) {
    const [search, setSearch] = useState(filters.cari);
    const [editing, setEditing] = useState<BudgetPlan | null>(null);
    const [open, setOpen] = useState(false);

    const filter = (next: { cari?: string; bidang?: string }) => {
        const bidang = next.bidang ?? String(filters.bidang ?? ALL);
        const cari = next.cari ?? search;

        router.get(
            budgetPlans.index.url({
                query: {
                    ...(cari.trim() !== '' ? { cari: cari.trim() } : {}),
                    ...(bidang !== ALL ? { bidang } : {}),
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const remove = (plan: BudgetPlan) => {
        if (
            window.confirm(
                `Hapus PRK "${plan.prk_induk}" dari perencanaan anggaran?`,
            )
        ) {
            router.delete(budgetPlans.destroy(plan.id).url, {
                preserveScroll: true,
            });
        }
    };

    const money = (value: number | null) =>
        value === null ? '—' : formatCurrency(value);

    return (
        <>
            <Head title="Perencanaan Anggaran" />

            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Anggaran"
                    title="Perencanaan Anggaran"
                    description="Program, kontrak, progres fisik/BA, dan pembayaran tiap PRK induk. Setiap PRK ditujukan ke satu bidang (Direksi Pekerjaan); pengusul bidang tersebut tinggal memilih nomor PRK-nya di form Usulan Pekerjaan."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <a
                                    href={budgetPlans.export.url({
                                        query: {
                                            ...(filters.cari !== ''
                                                ? { cari: filters.cari }
                                                : {}),
                                            ...(filters.bidang !== null
                                                ? { bidang: filters.bidang }
                                                : {}),
                                        },
                                    })}
                                >
                                    <FileSpreadsheet className="size-4" />
                                    Unduh Excel
                                </a>
                            </Button>
                            {canManage && (
                                <Button
                                    onClick={() => {
                                        setEditing(null);
                                        setOpen(true);
                                    }}
                                >
                                    <Plus className="size-4" />
                                    Tambah PRK
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <Summary label="Jumlah PRK" value={String(totals.count)} />
                    <Summary
                        label="Total Rencana AI / Nilai RAB"
                        value={formatCurrency(totals.ai)}
                    />
                    <Summary
                        label="Total Rencana AKI"
                        value={formatCurrency(totals.aki)}
                    />
                    <Summary
                        label="Total Nilai PO"
                        value={formatCurrency(totals.po)}
                    />
                    <Summary
                        label="Total Nilai Invoice"
                        value={formatCurrency(totals.invoice)}
                    />
                </div>

                <form
                    className="flex flex-col gap-2 sm:flex-row"
                    onSubmit={(event) => {
                        event.preventDefault();
                        filter({ cari: search });
                    }}
                >
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari PRK, deskripsi, WO, PR, PO, supplier, invoice…"
                            aria-label="Cari perencanaan anggaran"
                        />
                    </div>
                    <Select
                        value={String(filters.bidang ?? ALL)}
                        onValueChange={(value) => filter({ bidang: value })}
                    >
                        <SelectTrigger
                            className="sm:w-64"
                            aria-label="Filter bidang"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Semua bidang</SelectItem>
                            {workDirectors.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={String(option.value)}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="outline">
                        Cari
                    </Button>
                </form>

                {plans.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Wallet}
                            title={
                                filters.cari !== '' || filters.bidang !== null
                                    ? 'Tidak ada PRK yang cocok'
                                    : 'Belum ada perencanaan anggaran'
                            }
                            description={
                                canManage
                                    ? 'Tambahkan PRK induk agar dapat dipilih pengusul pada form Usulan Pekerjaan.'
                                    : 'PRK diisi oleh akun Anggaran.'
                            }
                        />
                    </div>
                ) : (
                    <div className="max-h-[70vh] overflow-auto rounded-md border border-border bg-card">
                        <table className="w-max min-w-full border-collapse text-xs">
                            <thead className="sticky top-0 z-10">
                                <tr>
                                    {GROUPS.map((group) => (
                                        <th
                                            key={group.label}
                                            colSpan={group.span}
                                            className={cn(
                                                'border border-border px-2 py-1.5 text-center text-[11px] font-semibold tracking-[0.2em] uppercase',
                                                group.className,
                                            )}
                                        >
                                            {group.label}
                                        </th>
                                    ))}
                                    {canManage && (
                                        <th className="border border-border bg-muted" />
                                    )}
                                </tr>
                                <tr className="bg-muted">
                                    <Th>No</Th>
                                    <Th>Distrik</Th>
                                    <Th>PRK Induk</Th>
                                    <Th>Bidang</Th>
                                    <Th wide>Deskripsi PRK Induk</Th>
                                    <Th wide>Deskripsi Sub Program</Th>
                                    <Th right>
                                        Rencana AI / Nilai RAB
                                        <Hint>(total PRK ≤ Penetapan AI)</Hint>
                                    </Th>
                                    <Th right>
                                        Rencana AKI
                                        <Hint>(total PRK ≤ Penetapan AKI)</Hint>
                                    </Th>
                                    <Th>No WO</Th>
                                    <Th>No PR</Th>
                                    <Th>PO Stock / Non Stock</Th>
                                    <Th>
                                        No PO Ellipse / Surat Penunjukan / DO /
                                        Surkes UMRO
                                    </Th>
                                    <Th>Alokasi PJB2 / Unit</Th>
                                    <Th>Item PO Ellipse</Th>
                                    <Th>Supplier</Th>
                                    <Th>Tanggal PO</Th>
                                    <Th right>Nilai PO</Th>
                                    <Th>Tanggal Levering</Th>
                                    <Th wide>Keterangan Progres Kontrak</Th>
                                    <Th wide>Progres Fisik / BA</Th>
                                    <Th right>Nilai Invoice</Th>
                                    <Th>Nomor Invoice</Th>
                                    <Th>Tanggal Bayar</Th>
                                    <Th>Nomor Bukti Bayar</Th>
                                    <Th>Keterangan Progres Bayar</Th>
                                    {canManage && <Th right>Aksi</Th>}
                                </tr>
                            </thead>
                            <tbody>
                                {plans.map((plan, index) => (
                                    <tr
                                        key={plan.id}
                                        className="align-top hover:bg-muted/40"
                                    >
                                        <Td className="tabular text-center">
                                            {index + 1}
                                        </Td>
                                        <Td>{plan.district}</Td>
                                        <Td className="tabular font-medium whitespace-nowrap">
                                            {plan.prk_induk}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {plan.work_director}
                                        </Td>
                                        <Td wide>
                                            {plan.prk_induk_description}
                                        </Td>
                                        <Td wide>
                                            {plan.sub_program_description}
                                        </Td>
                                        <Td right>
                                            {money(plan.ai_plan_value)}
                                        </Td>
                                        <Td right>
                                            {money(plan.aki_plan_value)}
                                        </Td>
                                        <Td className="tabular">
                                            {plan.wo_number}
                                        </Td>
                                        <Td className="tabular">
                                            {plan.pr_number}
                                        </Td>
                                        <Td>{plan.po_stock_type}</Td>
                                        <Td className="tabular">
                                            {plan.po_number}
                                        </Td>
                                        <Td>{plan.allocation}</Td>
                                        <Td>{plan.po_item}</Td>
                                        <Td className="min-w-40">
                                            {plan.supplier}
                                        </Td>
                                        <Td className="tabular whitespace-nowrap">
                                            {plan.po_date &&
                                                formatDate(plan.po_date)}
                                        </Td>
                                        <Td right>{money(plan.po_value)}</Td>
                                        <Td className="tabular whitespace-nowrap">
                                            {plan.delivery_date &&
                                                formatDate(plan.delivery_date)}
                                        </Td>
                                        <Td wide>{plan.contract_progress}</Td>
                                        <Td wide>{plan.physical_progress}</Td>
                                        <Td right>
                                            {money(plan.invoice_value)}
                                        </Td>
                                        <Td className="tabular">
                                            {plan.invoice_number}
                                        </Td>
                                        <Td className="tabular whitespace-nowrap">
                                            {plan.payment_dates}
                                        </Td>
                                        <Td className="tabular">
                                            {plan.payment_proof_number}
                                        </Td>
                                        <Td className="min-w-32">
                                            {plan.payment_progress}
                                        </Td>
                                        {canManage && (
                                            <Td className="text-right whitespace-nowrap">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => {
                                                        setEditing(plan);
                                                        setOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="size-3.5" />
                                                    Ubah
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => remove(plan)}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    Hapus
                                                </Button>
                                            </Td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-muted/60 font-medium">
                                <tr>
                                    <Td className="font-semibold">Total</Td>
                                    <Td colSpan={5} />
                                    <Td right>{formatCurrency(totals.ai)}</Td>
                                    <Td right>{formatCurrency(totals.aki)}</Td>
                                    <Td colSpan={8} />
                                    <Td right>{formatCurrency(totals.po)}</Td>
                                    <Td colSpan={3} />
                                    <Td right>
                                        {formatCurrency(totals.invoice)}
                                    </Td>
                                    <Td colSpan={canManage ? 5 : 4} />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>

            {canManage && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                        <BudgetPlanForm
                            // A fresh form for each row opened.
                            key={editing?.id ?? 'new'}
                            plan={editing}
                            workDirectors={workDirectors}
                            onDone={() => setOpen(false)}
                        />
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

function Th({
    children,
    right = false,
    wide = false,
}: {
    children?: ReactNode;
    right?: boolean;
    wide?: boolean;
}) {
    return (
        <th
            className={cn(
                'border border-border px-2 py-2 align-middle font-medium text-muted-foreground',
                right ? 'text-right' : 'text-left',
                wide ? 'min-w-56' : 'max-w-44',
            )}
        >
            {children}
        </th>
    );
}

function Hint({ children }: { children: ReactNode }) {
    return <span className="block text-[10px] font-normal">{children}</span>;
}

function Td({
    children,
    right = false,
    wide = false,
    className,
    colSpan,
}: {
    children?: ReactNode;
    right?: boolean;
    wide?: boolean;
    className?: string;
    colSpan?: number;
}) {
    return (
        <td
            colSpan={colSpan}
            className={cn(
                'border border-border px-2 py-1.5 whitespace-pre-line',
                right && 'tabular text-right whitespace-nowrap',
                wide && 'min-w-56',
                className,
            )}
        >
            {children}
        </td>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-border bg-card px-4 py-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="tabular mt-1 truncate text-lg font-semibold">
                {value}
            </p>
        </div>
    );
}

function BudgetPlanForm({
    plan,
    workDirectors,
    onDone,
}: {
    plan: BudgetPlan | null;
    workDirectors: Option[];
    onDone: () => void;
}) {
    const form = useForm<BudgetPlanValues>({
        district: plan?.district ?? 'UPKD',
        prk_induk: plan?.prk_induk ?? '',
        work_director_id:
            plan === null || plan.work_director_id === null
                ? ''
                : String(plan.work_director_id),
        prk_induk_description: plan?.prk_induk_description ?? '',
        sub_program_description: plan?.sub_program_description ?? '',
        ai_plan_value: plan?.ai_plan_value ?? 0,
        aki_plan_value: plan?.aki_plan_value ?? 0,
        wo_number: plan?.wo_number ?? '',
        pr_number: plan?.pr_number ?? '',
        po_stock_type: plan?.po_stock_type ?? '',
        po_number: plan?.po_number ?? '',
        allocation: plan?.allocation ?? '',
        po_item: plan?.po_item ?? '',
        supplier: plan?.supplier ?? '',
        po_date: plan?.po_date ?? '',
        po_value: plan?.po_value ?? 0,
        delivery_date: plan?.delivery_date ?? '',
        contract_progress: plan?.contract_progress ?? '',
        physical_progress: plan?.physical_progress ?? '',
        invoice_value: plan?.invoice_value ?? 0,
        invoice_number: plan?.invoice_number ?? '',
        payment_dates: plan?.payment_dates ?? '',
        payment_proof_number: plan?.payment_proof_number ?? '',
        payment_progress: plan?.payment_progress ?? '',
    });

    const submit = () => {
        // An amount left at zero is sent as empty, as on the sheet.
        form.transform((data) => ({
            ...data,
            ai_plan_value: data.ai_plan_value === 0 ? null : data.ai_plan_value,
            aki_plan_value:
                data.aki_plan_value === 0 ? null : data.aki_plan_value,
            po_value: data.po_value === 0 ? null : data.po_value,
            invoice_value: data.invoice_value === 0 ? null : data.invoice_value,
        }));

        const options = { preserveScroll: true, onSuccess: onDone };

        if (plan === null) {
            form.post(budgetPlans.store().url, options);
        } else {
            form.put(budgetPlans.update(plan.id).url, options);
        }
    };

    const text = (
        name: TextKey,
        label: string,
        options: {
            placeholder?: string;
            type?: 'text' | 'date';
            area?: boolean;
            wide?: boolean;
        } = {},
    ) => (
        <div
            className={cn(
                'grid content-start gap-1.5',
                options.wide && 'md:col-span-2',
            )}
        >
            <Label htmlFor={`budget-${name}`}>{label}</Label>
            {options.area ? (
                <Textarea
                    id={`budget-${name}`}
                    rows={3}
                    value={form.data[name]}
                    onChange={(event) => form.setData(name, event.target.value)}
                    placeholder={options.placeholder}
                />
            ) : (
                <Input
                    id={`budget-${name}`}
                    type={options.type ?? 'text'}
                    value={form.data[name]}
                    onChange={(event) => form.setData(name, event.target.value)}
                    placeholder={options.placeholder}
                    className={options.type === 'date' ? 'tabular' : undefined}
                    autoComplete="off"
                />
            )}
            <InputError message={form.errors[name]} />
        </div>
    );

    const money = (name: MoneyKey, label: string, hint?: string) => (
        <div className="grid content-start gap-1.5">
            <Label htmlFor={`budget-${name}`}>{label}</Label>
            <CurrencyInput
                id={`budget-${name}`}
                value={form.data[name]}
                onValueChange={(value) => form.setData(name, value)}
            />
            {hint && (
                <p className="text-[11px] text-muted-foreground">{hint}</p>
            )}
            <InputError message={form.errors[name]} />
        </div>
    );

    const choice = (
        name: 'po_stock_type' | 'allocation',
        label: string,
        values: string[],
    ) => (
        <div className="grid content-start gap-1.5">
            <Label htmlFor={`budget-${name}`}>{label}</Label>
            <Select
                value={form.data[name] === '' ? NONE : form.data[name]}
                onValueChange={(value) =>
                    form.setData(name, value === NONE ? '' : value)
                }
            >
                <SelectTrigger id={`budget-${name}`} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>—</SelectItem>
                    {values.map((value) => (
                        <SelectItem key={value} value={value}>
                            {value}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    {plan === null
                        ? 'Tambah PRK'
                        : `Ubah PRK ${plan.prk_induk}`}
                </DialogTitle>
                <DialogDescription>
                    Nilai dalam rupiah penuh. PRK muncul sebagai pilihan Nomor
                    PRK pada form Usulan Pekerjaan bidang yang dipilih.
                </DialogDescription>
            </DialogHeader>

            <form
                id="budget-plan-form"
                className="space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
            >
                <Section title="Program">
                    {text('district', 'Distrik', { placeholder: 'UPKD' })}
                    {text('prk_induk', 'PRK Induk', {
                        placeholder: 'Contoh: KD254A0102',
                    })}
                    <div className="grid content-start gap-1.5 md:col-span-2">
                        <Label htmlFor="budget-work_director_id">
                            Bidang (Direksi Pekerjaan)
                        </Label>
                        <Select
                            value={form.data.work_director_id}
                            onValueChange={(value) =>
                                form.setData('work_director_id', value)
                            }
                        >
                            <SelectTrigger
                                id="budget-work_director_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih bidang" />
                            </SelectTrigger>
                            <SelectContent>
                                {workDirectors.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.work_director_id} />
                    </div>
                    {text('prk_induk_description', 'Deskripsi PRK Induk', {
                        area: true,
                        wide: true,
                    })}
                    {text('sub_program_description', 'Deskripsi Sub Program', {
                        area: true,
                        wide: true,
                    })}
                    {money(
                        'ai_plan_value',
                        'Rencana AI / Nilai RAB',
                        'Total PRK ≤ Penetapan AI.',
                    )}
                    {money(
                        'aki_plan_value',
                        'Rencana AKI',
                        'Total PRK ≤ Penetapan AKI.',
                    )}
                </Section>

                <Section title="Kontrak">
                    {text('wo_number', 'No WO')}
                    {text('pr_number', 'No PR', {
                        placeholder: 'Lebih dari satu: pisahkan dengan koma',
                    })}
                    {choice('po_stock_type', 'PO Stock / Non Stock', [
                        'Stock',
                        'Non Stock',
                    ])}
                    {choice('allocation', 'Alokasi PJB2 / Unit', [
                        'UNIT',
                        'PJB2',
                    ])}
                    {text(
                        'po_number',
                        'No PO Ellipse / Surat Penunjukan / DO / Surkes UMRO',
                        { wide: true },
                    )}
                    {text('po_item', 'Item PO Ellipse', {
                        placeholder: 'Contoh: 1-4',
                    })}
                    {text('supplier', 'Supplier')}
                    {text('po_date', 'Tanggal PO Ellipse / Surat Penunjukan', {
                        type: 'date',
                    })}
                    {money('po_value', 'Nilai PO Ellipse / Surat Penunjukan')}
                    {text('delivery_date', 'Tanggal Levering', {
                        type: 'date',
                    })}
                    {text('contract_progress', 'Keterangan Progres Kontrak', {
                        area: true,
                        wide: true,
                    })}
                </Section>

                <Section title="Fisik / BA">
                    {text(
                        'physical_progress',
                        'Keterangan Progres Fisik dan/atau Progres BA',
                        { area: true, wide: true },
                    )}
                </Section>

                <Section title="Pembayaran">
                    {money('invoice_value', 'Nilai Invoice')}
                    {text('invoice_number', 'Nomor Invoice', {
                        placeholder: 'Lebih dari satu: pisahkan dengan koma',
                    })}
                    {text('payment_dates', 'Tanggal Bayar', {
                        placeholder: 'Contoh: 24/06/2026, 21/09/2026',
                    })}
                    {text('payment_proof_number', 'Nomor Bukti Bayar')}
                    {text('payment_progress', 'Keterangan Progres Bayar', {
                        placeholder: 'Contoh: TERBAYAR 100%',
                        wide: true,
                    })}
                </Section>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    Batal
                </Button>
                <Button
                    type="submit"
                    form="budget-plan-form"
                    disabled={form.processing}
                >
                    Simpan
                </Button>
            </DialogFooter>
        </>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <fieldset className="rounded-md border border-border p-4">
            <legend className="section-label px-1">{title}</legend>
            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                {children}
            </div>
        </fieldset>
    );
}

BudgetPlansIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Perencanaan Anggaran', href: budgetPlans.index() },
    ],
};
