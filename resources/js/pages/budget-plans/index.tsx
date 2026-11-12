import { Head, router, useForm } from '@inertiajs/react';
import {
    Activity,
    ChevronDown,
    Coins,
    FileSpreadsheet,
    Flame,
    Pencil,
    Plus,
    Search,
    Trash2,
    TrendingUp,
    Wallet,
    Wrench,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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

type BudgetPlanType = 'investasi' | 'operasi' | 'energi_primer';

type BudgetPlan = {
    id: number;
    budget_type: BudgetPlanType;
    district: string | null;
    prk_induk: string;
    work_director_id: number | null;
    work_director: string | null;
    prk_induk_description: string | null;
    sub_program_description: string | null;
    pagu_value: number | null;
    remaining_pagu_value: number | null;
    disburse_plan: string | null;
    realization_value: number | null;
    notes: string | null;
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
    ai?: number;
    aki?: number;
    po?: number;
    invoice?: number;
    pagu?: number;
    realisasi?: number;
    sisa_pagu?: number;
};

type TextKey =
    | 'district'
    | 'prk_induk'
    | 'prk_induk_description'
    | 'sub_program_description'
    | 'disburse_plan'
    | 'notes'
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
    | 'ai_plan_value'
    | 'aki_plan_value'
    | 'po_value'
    | 'invoice_value'
    | 'pagu_value'
    | 'realization_value'
    | 'remaining_pagu_value';

type BudgetPlanFormValues = Record<TextKey, string> &
    Record<MoneyKey, number> & {
        budget_type: BudgetPlanType;
        work_director_id: string;
        po_stock_type: string;
        allocation: string;
    };

const ALL = 'semua';
const NONE = 'kosong';

const TYPE_CONFIG: Record<
    BudgetPlanType,
    { label: string; shortLabel: string; badge: string; icon: typeof Wallet }
> = {
    investasi: {
        label: 'Anggaran Investasi (AI)',
        shortLabel: 'AI',
        badge: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
        icon: TrendingUp,
    },
    operasi: {
        label: 'Anggaran Operasi (AO PEMELIHARAAN)',
        shortLabel: 'AO Pemeliharaan',
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
        icon: Wrench,
    },
    energi_primer: {
        label: 'Anggaran Pendukung Energi Primer',
        shortLabel: 'Energi Primer',
        badge: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
        icon: Flame,
    },
};

/** The column groups of the Anggaran Investasi sheet. */
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

export default function BudgetPlansIndex({
    plans,
    totals,
    filters,
    activeType = 'investasi',
    workDirectors,
    canManage,
}: {
    plans: BudgetPlan[];
    totals: Totals;
    filters: { cari: string; bidang: number | null; jenis?: string };
    activeType?: BudgetPlanType;
    budgetTypes?: Array<{ value: string; label: string }>;
    workDirectors: Option[];
    canManage: boolean;
}) {
    const [search, setSearch] = useState(filters.cari);
    const [editing, setEditing] = useState<BudgetPlan | null>(null);
    const [createType, setCreateType] = useState<BudgetPlanType>(activeType);
    const [open, setOpen] = useState(false);

    const switchType = (nextType: BudgetPlanType) => {
        router.get(
            budgetPlans.index.url({
                query: {
                    jenis: nextType,
                    ...(filters.cari ? { cari: filters.cari } : {}),
                    ...(filters.bidang !== null ? { bidang: filters.bidang } : {}),
                },
            }),
            {},
            { preserveState: false, preserveScroll: true },
        );
    };

    const filter = (next: { cari?: string; bidang?: string }) => {
        const bidang = next.bidang ?? String(filters.bidang ?? ALL);
        const cari = next.cari ?? search;

        router.get(
            budgetPlans.index.url({
                query: {
                    jenis: activeType,
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

    const openCreateModal = (type: BudgetPlanType) => {
        setEditing(null);
        setCreateType(type);
        setOpen(true);
    };

    const money = (value: number | null) =>
        value === null ? '—' : formatCurrency(value);

    const isOperationView =
        activeType === 'operasi' || activeType === 'energi_primer';

    return (
        <>
            <Head title={`Perencanaan Anggaran - ${TYPE_CONFIG[activeType].label}`} />

            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Anggaran & Keuangan"
                    title="Perencanaan Anggaran"
                    description="Kelola program PRK untuk Anggaran Operasi (AO), Anggaran Investasi (AI), dan Anggaran Pendukung Energi Primer."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <a
                                    href={budgetPlans.export.url({
                                        query: {
                                            jenis: activeType,
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
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button className="gap-1.5 shadow-sm">
                                            <Plus className="size-4" />
                                            Tambah PRK
                                            <ChevronDown className="size-3.5 opacity-70" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-80">
                                        <DropdownMenuLabel className="text-xs text-muted-foreground uppercase tracking-wider">
                                            Pilih Jenis Anggaran
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            className="flex flex-col items-start gap-1 p-2.5 cursor-pointer"
                                            onClick={() => openCreateModal('operasi')}
                                        >
                                            <div className="flex items-center gap-2 font-medium text-foreground">
                                                <Wrench className="size-4 text-emerald-600 dark:text-emerald-400" />
                                                Anggaran Operasi (AO PEMELIHARAAN)
                                            </div>
                                            <p className="text-[11px] text-muted-foreground">
                                                Pagu, Realisasi, Sisa Pagu, Rencana Disburse
                                            </p>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            className="flex flex-col items-start gap-1 p-2.5 cursor-pointer"
                                            onClick={() => openCreateModal('investasi')}
                                        >
                                            <div className="flex items-center gap-2 font-medium text-foreground">
                                                <TrendingUp className="size-4 text-blue-600 dark:text-blue-400" />
                                                Anggaran Investasi (AI)
                                            </div>
                                            <p className="text-[11px] text-muted-foreground">
                                                Program, Kontrak, Fisik/BA, Pembayaran
                                            </p>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            className="flex flex-col items-start gap-1 p-2.5 cursor-pointer"
                                            onClick={() => openCreateModal('energi_primer')}
                                        >
                                            <div className="flex items-center gap-2 font-medium text-foreground">
                                                <Flame className="size-4 text-amber-600 dark:text-amber-400" />
                                                Anggaran Pendukung Energi Primer
                                            </div>
                                            <p className="text-[11px] text-muted-foreground">
                                                Pagu, Realisasi, Sisa Pagu, Rencana Disburse
                                            </p>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                    }
                />

                {/* Navigation Tabs for Budget Types */}
                <div className="flex flex-wrap items-center gap-2 border-b border-border pb-3">
                    <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mr-1">
                        Kategori:
                    </span>
                    {(
                        [
                            'investasi',
                            'operasi',
                            'energi_primer',
                        ] as BudgetPlanType[]
                    ).map((type) => {
                        const cfg = TYPE_CONFIG[type];
                        const Icon = cfg.icon;
                        const isActive = activeType === type;
                        return (
                            <button
                                key={type}
                                type="button"
                                onClick={() => switchType(type)}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-medium transition-all',
                                    isActive
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                <Icon className="size-3.5" />
                                {cfg.label}
                            </button>
                        );
                    })}
                </div>

                {/* Summaries based on Active View */}
                {!isOperationView ? (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <Summary label="Jumlah PRK" value={String(totals.count)} />
                        <Summary
                            label="Total Rencana AI / Nilai RAB"
                            value={formatCurrency(totals.ai ?? 0)}
                        />
                        <Summary
                            label="Total Rencana AKI"
                            value={formatCurrency(totals.aki ?? 0)}
                        />
                        <Summary
                            label="Total Nilai PO"
                            value={formatCurrency(totals.po ?? 0)}
                        />
                        <Summary
                            label="Total Nilai Invoice"
                            value={formatCurrency(totals.invoice ?? 0)}
                        />
                    </div>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Summary
                            label="Jumlah PRK"
                            value={String(totals.count)}
                            icon={Coins}
                        />
                        <Summary
                            label="Total Pagu"
                            value={formatCurrency(totals.pagu ?? 0)}
                            icon={Wallet}
                        />
                        <Summary
                            label="Total Realisasi s/d SM 2"
                            value={formatCurrency(totals.realisasi ?? 0)}
                            icon={Activity}
                        />
                        <Summary
                            label="Total Sisa Pagu (Pagu - Realisasi)"
                            value={formatCurrency(totals.sisa_pagu ?? 0)}
                            highlight
                            icon={TrendingUp}
                        />
                    </div>
                )}

                {/* Filter Controls */}
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
                            placeholder="Cari nomor PRK, deskripsi, catatan, bidang…"
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

                {/* Empty State or Tables */}
                {plans.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Wallet}
                            title={
                                filters.cari !== '' || filters.bidang !== null
                                    ? 'Tidak ada PRK yang cocok'
                                    : `Belum ada ${TYPE_CONFIG[activeType].label}`
                            }
                            description={
                                canManage
                                    ? `Klik "Tambah PRK" untuk menambahkan data ${TYPE_CONFIG[activeType].label}.`
                                    : 'Data PRK diisi oleh akun Anggaran.'
                            }
                        />
                    </div>
                ) : isOperationView ? (
                    /* Table for Anggaran Operasi (AO) & Anggaran Pendukung Energi Primer matching user screenshot */
                    <div className="max-h-[70vh] overflow-auto rounded-md border border-border bg-card shadow-sm">
                        <table className="w-max min-w-full border-collapse text-xs">
                            <thead className="sticky top-0 z-10 bg-sky-100/90 dark:bg-sky-950/80 backdrop-blur">
                                <tr className="border-b border-border font-semibold text-foreground">
                                    <Th center className="w-12">
                                        NO
                                    </Th>
                                    <Th className="min-w-36">PRK</Th>
                                    <Th wide>DESKRIPSI</Th>
                                    <Th className="min-w-32">BIDANG</Th>
                                    <Th right className="min-w-36">
                                        PAGU
                                    </Th>
                                    <Th
                                        right
                                        className="min-w-36 bg-emerald-200/70 dark:bg-emerald-950/70 text-emerald-950 dark:text-emerald-200"
                                    >
                                        SISA PAGU
                                    </Th>
                                    <Th center className="min-w-28">
                                        RENCANA DISBURSE
                                    </Th>
                                    <Th right className="min-w-40">
                                        REALISASI s/d SM 2 (OKTOBER)
                                    </Th>
                                    <Th
                                        wide
                                        className="min-w-64 bg-amber-200/60 dark:bg-amber-950/60 text-amber-950 dark:text-amber-200"
                                    >
                                        KET
                                    </Th>
                                    {canManage && (
                                        <Th right className="min-w-28">
                                            AKSI
                                        </Th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {plans.map((plan, index) => (
                                    <tr
                                        key={plan.id}
                                        className="align-top hover:bg-muted/40 transition-colors"
                                    >
                                        <Td className="tabular text-center font-medium">
                                            {index + 1}
                                        </Td>
                                        <Td className="tabular font-semibold whitespace-nowrap text-primary">
                                            {plan.prk_induk}
                                        </Td>
                                        <Td wide className="font-medium">
                                            {plan.prk_induk_description || '—'}
                                        </Td>
                                        <Td className="whitespace-nowrap text-muted-foreground">
                                            {plan.work_director || '—'}
                                        </Td>
                                        <Td right className="font-medium">
                                            {money(plan.pagu_value)}
                                        </Td>
                                        <Td
                                            right
                                            className="font-bold tabular bg-emerald-50/70 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300 border-l border-r border-emerald-200/50"
                                        >
                                            {money(
                                                plan.remaining_pagu_value ??
                                                    (plan.pagu_value !== null &&
                                                    plan.realization_value !== null
                                                        ? plan.pagu_value -
                                                          plan.realization_value
                                                        : null),
                                            )}
                                        </Td>
                                        <Td className="tabular text-center">
                                            {plan.disburse_plan || '—'}
                                        </Td>
                                        <Td right className="font-medium">
                                            {money(plan.realization_value)}
                                        </Td>
                                        <Td
                                            wide
                                            className="bg-amber-50/70 dark:bg-amber-950/30 text-amber-950 dark:text-amber-200 border-l border-amber-200/50"
                                        >
                                            {plan.notes || '—'}
                                        </Td>
                                        {canManage && (
                                            <Td className="text-right whitespace-nowrap">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => {
                                                        setEditing(plan);
                                                        setCreateType(plan.budget_type);
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
                            <tfoot className="bg-sky-50/80 dark:bg-sky-950/60 font-semibold border-t-2 border-border">
                                <tr>
                                    <Td className="text-center font-bold">TOTAL</Td>
                                    <Td colSpan={3} />
                                    <Td right>{formatCurrency(totals.pagu ?? 0)}</Td>
                                    <Td
                                        right
                                        className="font-bold tabular text-emerald-800 dark:text-emerald-300 bg-emerald-100/60 dark:bg-emerald-950/60"
                                    >
                                        {formatCurrency(totals.sisa_pagu ?? 0)}
                                    </Td>
                                    <Td />
                                    <Td right>
                                        {formatCurrency(totals.realisasi ?? 0)}
                                    </Td>
                                    <Td colSpan={canManage ? 2 : 1} />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                ) : (
                    /* Existing 25-column table for Anggaran Investasi */
                    <div className="max-h-[70vh] overflow-auto rounded-md border border-border bg-card shadow-sm">
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
                                                        setCreateType(plan.budget_type);
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
                                    <Td right>{formatCurrency(totals.ai ?? 0)}</Td>
                                    <Td right>{formatCurrency(totals.aki ?? 0)}</Td>
                                    <Td colSpan={8} />
                                    <Td right>{formatCurrency(totals.po ?? 0)}</Td>
                                    <Td colSpan={3} />
                                    <Td right>
                                        {formatCurrency(totals.invoice ?? 0)}
                                    </Td>
                                    <Td colSpan={canManage ? 5 : 4} />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>

            {/* Modal Dialog Form */}
            {canManage && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                        <BudgetPlanForm
                            key={editing?.id ?? `new-${createType}`}
                            plan={editing}
                            initialType={createType}
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
    center = false,
    wide = false,
    className,
}: {
    children?: ReactNode;
    right?: boolean;
    center?: boolean;
    wide?: boolean;
    className?: string;
}) {
    return (
        <th
            className={cn(
                'border border-border px-2 py-2 align-middle font-medium text-foreground',
                center ? 'text-center' : right ? 'text-right' : 'text-left',
                wide ? 'min-w-56' : 'max-w-44',
                className,
            )}
        >
            {children}
        </th>
    );
}

function Hint({ children }: { children: ReactNode }) {
    return <span className="block text-[10px] font-normal text-muted-foreground">{children}</span>;
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

function Summary({
    label,
    value,
    highlight = false,
    icon: Icon,
}: {
    label: string;
    value: string;
    highlight?: boolean;
    icon?: typeof Wallet;
}) {
    return (
        <div
            className={cn(
                'rounded-lg border px-4 py-3 shadow-xs transition-colors',
                highlight
                    ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30'
                    : 'border-border bg-card',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground font-medium">{label}</p>
                {Icon && (
                    <Icon
                        className={cn(
                            'size-4',
                            highlight ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground',
                        )}
                    />
                )}
            </div>
            <p
                className={cn(
                    'tabular mt-1.5 truncate text-lg font-bold tracking-tight',
                    highlight ? 'text-emerald-900 dark:text-emerald-200' : 'text-foreground',
                )}
            >
                {value}
            </p>
        </div>
    );
}

function BudgetPlanForm({
    plan,
    initialType,
    workDirectors,
    onDone,
}: {
    plan: BudgetPlan | null;
    initialType: BudgetPlanType;
    workDirectors: Option[];
    onDone: () => void;
}) {
    const selectedType: BudgetPlanType = plan?.budget_type ?? initialType;

    const form = useForm<BudgetPlanFormValues>({
        budget_type: selectedType,
        district: plan?.district ?? 'UPKD',
        prk_induk: plan?.prk_induk ?? '',
        work_director_id:
            plan === null || plan.work_director_id === null
                ? ''
                : String(plan.work_director_id),
        prk_induk_description: plan?.prk_induk_description ?? '',
        sub_program_description: plan?.sub_program_description ?? '',
        pagu_value: plan?.pagu_value ?? 0,
        remaining_pagu_value: plan?.remaining_pagu_value ?? 0,
        disburse_plan: plan?.disburse_plan ?? '',
        realization_value: plan?.realization_value ?? 0,
        notes: plan?.notes ?? '',
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

    const isOperationType =
        form.data.budget_type === 'operasi' ||
        form.data.budget_type === 'energi_primer';

    // Live calculation for Sisa Pagu = Pagu - Realisasi
    const computedRemainingPagu =
        (form.data.pagu_value || 0) - (form.data.realization_value || 0);

    const submit = () => {
        form.transform((data) => ({
            ...data,
            budget_type: data.budget_type,
            work_director_id:
                data.work_director_id === '' ? null : data.work_director_id,
            pagu_value: data.pagu_value === 0 ? null : data.pagu_value,
            remaining_pagu_value: isOperationType
                ? (data.pagu_value || 0) - (data.realization_value || 0)
                : null,
            realization_value:
                data.realization_value === 0 ? null : data.realization_value,
            ai_plan_value:
                data.ai_plan_value === 0 ? null : data.ai_plan_value,
            aki_plan_value:
                data.aki_plan_value === 0 ? null : data.aki_plan_value,
            po_value: data.po_value === 0 ? null : data.po_value,
            invoice_value:
                data.invoice_value === 0 ? null : data.invoice_value,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: onDone,
        };

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
            required?: boolean;
        } = {},
    ) => (
        <div
            className={cn(
                'grid content-start gap-1.5',
                options.wide && 'md:col-span-2',
            )}
        >
            <Label htmlFor={`budget-${name}`}>
                {label} {options.required && <span className="text-destructive">*</span>}
            </Label>
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
                <DialogTitle className="flex items-center gap-2">
                    {plan === null ? (
                        <>
                            <span>Tambah PRK:</span>
                            <span className="text-primary font-bold">
                                {TYPE_CONFIG[form.data.budget_type].label}
                            </span>
                        </>
                    ) : (
                        `Ubah PRK ${plan.prk_induk}`
                    )}
                </DialogTitle>
                <DialogDescription>
                    {isOperationType
                        ? 'Form input Anggaran Operasi dan Pendukung Energi Primer (Pagu, Realisasi, Sisa Pagu, Rencana Disburse, Keterangan).'
                        : 'Form input Anggaran Investasi (Program, Kontrak, Fisik/BA, Pembayaran).'}
                </DialogDescription>
            </DialogHeader>

            {/* Selector for Budget Type in Modal */}
            <div className="rounded-lg border border-border bg-muted/40 p-3">
                <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2 block">
                    Jenis Anggaran:
                </Label>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    {(
                        [
                            'operasi',
                            'investasi',
                            'energi_primer',
                        ] as BudgetPlanType[]
                    ).map((type) => {
                        const cfg = TYPE_CONFIG[type];
                        const Icon = cfg.icon;
                        const isSelected = form.data.budget_type === type;
                        return (
                            <button
                                key={type}
                                type="button"
                                onClick={() => form.setData('budget_type', type)}
                                className={cn(
                                    'flex items-center gap-2 rounded-md border p-2.5 text-left text-xs font-medium transition-all',
                                    isSelected
                                        ? 'border-primary bg-primary/10 text-primary font-semibold ring-1 ring-primary'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted/70 hover:text-foreground',
                                )}
                            >
                                <Icon className="size-4 shrink-0" />
                                <span className="truncate">{cfg.shortLabel}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            <form
                id="budget-plan-form"
                className="space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
            >
                {isOperationType ? (
                    /* Form for Anggaran Operasi (AO) & Anggaran Pendukung Energi Primer */
                    <Section title={`Data ${TYPE_CONFIG[form.data.budget_type].label}`}>
                        {text('prk_induk', 'Nomor PRK', {
                            placeholder: 'Contoh: KD262G0101',
                            required: true,
                        })}
                        <div className="grid content-start gap-1.5">
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

                        {text('prk_induk_description', 'Deskripsi PRK', {
                            area: true,
                            wide: true,
                            placeholder:
                                'Contoh: PENGADAAN MATERIAL DAN JASA BIDANG OPERASI',
                        })}

                        {money('pagu_value', 'Pagu (Rp)', 'Total pagu anggaran.')}

                        {money(
                            'realization_value',
                            'Realisasi s/d SM 2 (Oktober)',
                            'Total realisasi sampai periode berjalan.',
                        )}

                        {/* Sisa Pagu (Auto-calculated) */}
                        <div className="grid content-start gap-1.5">
                            <Label htmlFor="budget-sisa-pagu">
                                Sisa Pagu (Pagu - Realisasi)
                            </Label>
                            <div
                                id="budget-sisa-pagu"
                                className={cn(
                                    'flex h-9 w-full items-center rounded-md border px-3 text-sm font-bold tabular-nums',
                                    computedRemainingPagu < 0
                                        ? 'border-destructive bg-destructive/10 text-destructive'
                                        : 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
                                )}
                            >
                                {formatCurrency(computedRemainingPagu)}
                            </div>
                            <p className="text-[11px] text-muted-foreground">
                                Dihitung otomatis: Pagu dikurangi Realisasi.
                            </p>
                        </div>

                        {text('disburse_plan', 'Rencana Disburse', {
                            placeholder: 'Contoh: 01-12',
                        })}

                        {text('notes', 'Keterangan (KET)', {
                            area: true,
                            wide: true,
                            placeholder:
                                'Contoh: Terkontrak 11 site, Dokumen tagihan Real, Tahap 1 s/d JUNI 26 ok (done)...',
                        })}
                    </Section>
                ) : (
                    /* Existing 4-section Form for Anggaran Investasi */
                    <>
                        <Section title="Program">
                            {text('district', 'Distrik', { placeholder: 'UPKD' })}
                            {text('prk_induk', 'PRK Induk', {
                                placeholder: 'Contoh: KD254A0102',
                                required: true,
                            })}
                            <div className="grid content-start gap-1.5 md:col-span-2">
                                <Label htmlFor="budget-work_director_id">
                                    Bidang (Direksi Pekerjaan){' '}
                                    <span className="text-destructive">*</span>
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
                            {text(
                                'sub_program_description',
                                'Deskripsi Sub Program',
                                {
                                    area: true,
                                    wide: true,
                                },
                            )}
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
                                placeholder:
                                    'Lebih dari satu: pisahkan dengan koma',
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
                            {text(
                                'po_date',
                                'Tanggal PO Ellipse / Surat Penunjukan',
                                {
                                    type: 'date',
                                },
                            )}
                            {money(
                                'po_value',
                                'Nilai PO Ellipse / Surat Penunjukan',
                            )}
                            {text('delivery_date', 'Tanggal Levering', {
                                type: 'date',
                            })}
                            {text(
                                'contract_progress',
                                'Keterangan Progres Kontrak',
                                {
                                    area: true,
                                    wide: true,
                                },
                            )}
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
                                placeholder:
                                    'Lebih dari satu: pisahkan dengan koma',
                            })}
                            {text('payment_dates', 'Tanggal Bayar', {
                                placeholder:
                                    'Contoh: 24/06/2026, 21/09/2026',
                            })}
                            {text('payment_proof_number', 'Nomor Bukti Bayar')}
                            {text('payment_progress', 'Keterangan Progres Bayar', {
                                placeholder: 'Contoh: TERBAYAR 100%',
                                wide: true,
                            })}
                        </Section>
                    </>
                )}
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
            <legend className="section-label px-1 font-semibold text-xs tracking-wider uppercase text-muted-foreground">
                {title}
            </legend>
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
