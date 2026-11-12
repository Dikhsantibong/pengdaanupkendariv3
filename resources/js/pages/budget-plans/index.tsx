import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, Search, Wallet } from 'lucide-react';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

const TYPE_LABELS: Record<BudgetPlanType, string> = {
    investasi: 'Anggaran Investasi (AI)',
    operasi: 'Anggaran Operasi (AO PEMELIHARAAN)',
    energi_primer: 'Anggaran Pendukung Energi Primer',
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
            <Head title={`Perencanaan Anggaran - ${TYPE_LABELS[activeType]}`} />

            <div className="flex min-w-0 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Anggaran"
                    title="Perencanaan Anggaran"
                    description="Program, kontrak, progres fisik/BA, dan pembayaran tiap PRK induk. Tersedia pilihan Anggaran Operasi (AO), Anggaran Investasi (AI), dan Anggaran Pendukung Energi Primer."
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
                                    Unduh Excel
                                </a>
                            </Button>
                            {canManage && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button>
                                            Tambah PRK
                                            <ChevronDown className="size-4 opacity-50" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-72">
                                        <DropdownMenuLabel className="section-label px-2 py-1.5">
                                            Pilih Jenis Anggaran
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onClick={() => openCreateModal('operasi')}
                                        >
                                            Anggaran Operasi (AO PEMELIHARAAN)
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() => openCreateModal('investasi')}
                                        >
                                            Anggaran Investasi (AI)
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() => openCreateModal('energi_primer')}
                                        >
                                            Anggaran Pendukung Energi Primer
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                    }
                />

                {/* Tabs Kategori sesuai spesifikasi DESIGN.md 4.6 */}
                <Tabs
                    defaultValue={activeType}
                    value={activeType}
                    onValueChange={(val) => switchType(val as BudgetPlanType)}
                >
                    <TabsList className="w-full justify-start overflow-x-auto">
                        <TabsTrigger value="investasi">
                            Anggaran Investasi (AI)
                        </TabsTrigger>
                        <TabsTrigger value="operasi">
                            Anggaran Operasi (AO PEMELIHARAAN)
                        </TabsTrigger>
                        <TabsTrigger value="energi_primer">
                            Anggaran Pendukung Energi Primer
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                {/* StatCard ringkas sesuai spesifikasi DESIGN.md 5.4 */}
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
                        <Summary label="Jumlah PRK" value={String(totals.count)} />
                        <Summary
                            label="Total Pagu"
                            value={formatCurrency(totals.pagu ?? 0)}
                        />
                        <Summary
                            label="Total Realisasi s/d SM 2"
                            value={formatCurrency(totals.realisasi ?? 0)}
                        />
                        <Summary
                            label="Total Sisa Pagu (Pagu - Realisasi)"
                            value={formatCurrency(totals.sisa_pagu ?? 0)}
                            highlight
                        />
                    </div>
                )}

                {/* Baris Filter sesuai DESIGN.md 5.10 */}
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

                {/* Tabel atau Empty State sesuai DESIGN.md 4.7 & 5.9 */}
                {plans.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={Wallet}
                            title={
                                filters.cari !== '' || filters.bidang !== null
                                    ? 'Tidak ada PRK yang cocok'
                                    : `Belum ada data ${TYPE_LABELS[activeType]}`
                            }
                            description={
                                canManage
                                    ? `Klik "Tambah PRK" untuk menambahkan data ${TYPE_LABELS[activeType]}.`
                                    : 'Data PRK diisi oleh akun Anggaran.'
                            }
                        />
                    </div>
                ) : isOperationView ? (
                    /* Tabel Anggaran Operasi (AO) & Pendukung Energi Primer */
                    <div className="max-h-[70vh] overflow-auto rounded-md border border-border bg-card">
                        <table className="w-max min-w-full border-collapse text-xs">
                            <thead className="sticky top-0 z-10 bg-muted/80 backdrop-blur">
                                <tr className="border-b border-border">
                                    <Th center className="w-12">
                                        NO
                                    </Th>
                                    <Th className="min-w-36">PRK</Th>
                                    <Th wide>DESKRIPSI</Th>
                                    <Th className="min-w-32">BIDANG</Th>
                                    <Th right className="min-w-36">
                                        PAGU
                                    </Th>
                                    <Th right className="min-w-36 bg-muted/60 font-semibold">
                                        SISA PAGU
                                    </Th>
                                    <Th center className="min-w-28">
                                        RENCANA DISBURSE
                                    </Th>
                                    <Th right className="min-w-40">
                                        REALISASI s/d SM 2 (OKTOBER)
                                    </Th>
                                    <Th wide className="min-w-64">
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
                                        className="align-top border-b border-border hover:bg-muted/50 transition-colors"
                                    >
                                        <Td className="tabular text-center">
                                            {index + 1}
                                        </Td>
                                        <Td className="tabular font-medium whitespace-nowrap text-foreground">
                                            {plan.prk_induk}
                                        </Td>
                                        <Td wide>
                                            {plan.prk_induk_description || '—'}
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            {plan.work_director || '—'}
                                        </Td>
                                        <Td right>
                                            {money(plan.pagu_value)}
                                        </Td>
                                        <Td
                                            right
                                            className="font-medium tabular bg-muted/20"
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
                                        <Td className="tabular text-center whitespace-nowrap">
                                            {plan.disburse_plan || '—'}
                                        </Td>
                                        <Td right>
                                            {money(plan.realization_value)}
                                        </Td>
                                        <Td wide>
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
                                                    Ubah
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => remove(plan)}
                                                >
                                                    Hapus
                                                </Button>
                                            </Td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-muted/60 border-t border-border font-medium">
                                <tr>
                                    <Td className="font-semibold text-center">TOTAL</Td>
                                    <Td colSpan={3} />
                                    <Td right className="font-semibold">
                                        {formatCurrency(totals.pagu ?? 0)}
                                    </Td>
                                    <Td right className="font-semibold">
                                        {formatCurrency(totals.sisa_pagu ?? 0)}
                                    </Td>
                                    <Td />
                                    <Td right className="font-semibold">
                                        {formatCurrency(totals.realisasi ?? 0)}
                                    </Td>
                                    <Td colSpan={canManage ? 2 : 1} />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                ) : (
                    /* Tabel Anggaran Investasi (AI) */
                    <div className="max-h-[70vh] overflow-auto rounded-md border border-border bg-card">
                        <table className="w-max min-w-full border-collapse text-xs">
                            <thead className="sticky top-0 z-10">
                                <tr>
                                    {GROUPS.map((group) => (
                                        <th
                                            key={group.label}
                                            colSpan={group.span}
                                            className={cn(
                                                'border border-border px-2 py-1.5 text-center text-[0.6875rem] font-semibold tracking-[0.2em] uppercase',
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
                                        className="align-top border-b border-border hover:bg-muted/50 transition-colors"
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
                                                    Ubah
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => remove(plan)}
                                                >
                                                    Hapus
                                                </Button>
                                            </Td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-muted/60 border-t border-border font-medium">
                                <tr>
                                    <Td className="font-semibold text-center">Total</Td>
                                    <Td colSpan={5} />
                                    <Td right className="font-semibold">
                                        {formatCurrency(totals.ai ?? 0)}
                                    </Td>
                                    <Td right className="font-semibold">
                                        {formatCurrency(totals.aki ?? 0)}
                                    </Td>
                                    <Td colSpan={8} />
                                    <Td right className="font-semibold">
                                        {formatCurrency(totals.po ?? 0)}
                                    </Td>
                                    <Td colSpan={3} />
                                    <Td right className="font-semibold">
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
                'border border-border px-3 py-2 align-middle text-[0.6875rem] font-semibold tracking-[0.06em] uppercase text-muted-foreground',
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
    return (
        <span className="block text-[10px] font-normal normal-case text-muted-foreground">
            {children}
        </span>
    );
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
                'border border-border px-3 py-2 whitespace-pre-line text-sm text-foreground',
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
}: {
    label: string;
    value: string;
    highlight?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-md border border-border bg-card p-4 space-y-1',
                highlight && 'border-l-2 border-l-primary',
            )}
        >
            <p className="section-label truncate">{label}</p>
            <p className="tabular text-2xl font-semibold tracking-tight text-foreground">
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

    // Live calculation: Sisa Pagu = Pagu - Realisasi
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
            <Label htmlFor={`budget-${name}`} className="section-label">
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
            <Label htmlFor={`budget-${name}`} className="section-label">
                {label}
            </Label>
            <CurrencyInput
                id={`budget-${name}`}
                value={form.data[name]}
                onValueChange={(value) => form.setData(name, value)}
            />
            {hint && (
                <p className="text-xs text-muted-foreground">{hint}</p>
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
            <Label htmlFor={`budget-${name}`} className="section-label">
                {label}
            </Label>
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
                        ? `Tambah PRK - ${TYPE_LABELS[form.data.budget_type]}`
                        : `Ubah PRK ${plan.prk_induk}`}
                </DialogTitle>
                <DialogDescription>
                    {isOperationType
                        ? 'Isi nomor PRK, deskripsi, pagu, realisasi, rencana disburse, dan keterangan.'
                        : 'Nilai dalam rupiah penuh. PRK muncul sebagai pilihan Nomor PRK pada form Usulan Pekerjaan bidang yang dipilih.'}
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
                {/* Pilihan Jenis Anggaran memakai Select standar */}
                <div className="grid content-start gap-1.5">
                    <Label className="section-label">Jenis Anggaran</Label>
                    <Select
                        value={form.data.budget_type}
                        onValueChange={(value) =>
                            form.setData('budget_type', value as BudgetPlanType)
                        }
                        disabled={plan !== null}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="operasi">
                                Anggaran Operasi (AO PEMELIHARAAN)
                            </SelectItem>
                            <SelectItem value="investasi">
                                Anggaran Investasi (AI)
                            </SelectItem>
                            <SelectItem value="energi_primer">
                                Anggaran Pendukung Energi Primer
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.budget_type} />
                </div>

                {isOperationType ? (
                    /* Form Anggaran Operasi & Pendukung Energi Primer */
                    <Section title={TYPE_LABELS[form.data.budget_type]}>
                        {text('prk_induk', 'Nomor PRK', {
                            placeholder: 'Contoh: KD262G0101',
                            required: true,
                        })}
                        <div className="grid content-start gap-1.5">
                            <Label htmlFor="budget-work_director_id" className="section-label">
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

                        {/* Sisa Pagu (Dihitung otomatis) */}
                        <div className="grid content-start gap-1.5">
                            <Label className="section-label">
                                Sisa Pagu (Pagu - Realisasi)
                            </Label>
                            <Input
                                readOnly
                                disabled
                                value={formatCurrency(computedRemainingPagu)}
                                className="tabular font-semibold text-foreground bg-muted/40"
                            />
                            <p className="text-xs text-muted-foreground">
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
                                'Contoh: Terkontrak 11 site, Dokumen tagihan Real, Tahap 1 s/d JUNI 26 ok...',
                        })}
                    </Section>
                ) : (
                    /* Form 4 Bagian untuk Anggaran Investasi */
                    <>
                        <Section title="Program">
                            {text('district', 'Distrik', { placeholder: 'UPKD' })}
                            {text('prk_induk', 'PRK Induk', {
                                placeholder: 'Contoh: KD254A0102',
                                required: true,
                            })}
                            <div className="grid content-start gap-1.5 md:col-span-2">
                                <Label htmlFor="budget-work_director_id" className="section-label">
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
