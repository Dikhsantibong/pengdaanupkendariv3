import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Wallet } from 'lucide-react';
import { useState } from 'react';
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
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/format';
import { dashboard } from '@/routes';
import budgetPlans from '@/routes/budget-plans';
import type { Option } from '@/types';

type BudgetPlan = {
    id: number;
    prk_induk: string;
    work_director_id: number | null;
    work_director: string | null;
    prk_induk_description: string | null;
    sub_program_description: string | null;
    ai_plan_value: number | null;
    aki_plan_value: number | null;
    wo_number: string | null;
    pr_number: string | null;
    updated_by: string | null;
    updated_at: string | null;
};

type BudgetPlanValues = {
    prk_induk: string;
    work_director_id: string;
    prk_induk_description: string;
    sub_program_description: string;
    ai_plan_value: number;
    aki_plan_value: number;
    wo_number: string;
    pr_number: string;
};

const ALL = 'semua';

/**
 * Perencanaan Anggaran: the Anggaran role lists each PRK induk with its
 * planned AI (Nilai RAB) and AKI and its WO and PR, and points it at the
 * bidang whose proposers pick it on the work proposal form.
 */
export default function BudgetPlansIndex({
    plans,
    totals,
    filters,
    workDirectors,
    canManage,
}: {
    plans: BudgetPlan[];
    totals: { count: number; ai: number; aki: number };
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

    return (
        <>
            <Head title="Perencanaan Anggaran" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Anggaran"
                    title="Perencanaan Anggaran"
                    description="Daftar PRK induk beserta rencana AI / nilai RAB, rencana AKI, nomor WO, dan nomor PR. Setiap PRK ditujukan ke satu bidang (Direksi Pekerjaan); pengusul bidang tersebut tinggal memilih nomor PRK-nya di form Usulan Pekerjaan."
                    actions={
                        canManage && (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setOpen(true);
                                }}
                            >
                                <Plus className="size-4" />
                                Tambah PRK
                            </Button>
                        )
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <Summary label="Jumlah PRK" value={String(totals.count)} />
                    <Summary
                        label="Total Rencana AI / Nilai RAB"
                        value={formatCurrency(totals.ai)}
                    />
                    <Summary
                        label="Total Rencana AKI"
                        value={formatCurrency(totals.aki)}
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
                            placeholder="Cari PRK, deskripsi, nomor WO atau PR…"
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
                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>PRK Induk</TableHead>
                                    <TableHead>Bidang</TableHead>
                                    <TableHead className="min-w-56">
                                        Deskripsi PRK Induk
                                    </TableHead>
                                    <TableHead className="min-w-56">
                                        Deskripsi Sub Program
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Rencana AI / Nilai RAB
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Rencana AKI
                                    </TableHead>
                                    <TableHead>No WO</TableHead>
                                    <TableHead>No PR</TableHead>
                                    {canManage && (
                                        <TableHead className="text-right">
                                            Aksi
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {plans.map((plan) => (
                                    <TableRow key={plan.id}>
                                        <TableCell className="tabular font-medium whitespace-nowrap">
                                            {plan.prk_induk}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {plan.work_director ?? '—'}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {plan.prk_induk_description ?? '—'}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {plan.sub_program_description ??
                                                '—'}
                                        </TableCell>
                                        <TableCell className="tabular text-right whitespace-nowrap">
                                            {plan.ai_plan_value === null
                                                ? '—'
                                                : formatCurrency(
                                                      plan.ai_plan_value,
                                                  )}
                                        </TableCell>
                                        <TableCell className="tabular text-right whitespace-nowrap">
                                            {plan.aki_plan_value === null
                                                ? '—'
                                                : formatCurrency(
                                                      plan.aki_plan_value,
                                                  )}
                                        </TableCell>
                                        <TableCell className="tabular whitespace-nowrap">
                                            {plan.wo_number ?? '—'}
                                        </TableCell>
                                        <TableCell className="tabular whitespace-nowrap">
                                            {plan.pr_number ?? '—'}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-right whitespace-nowrap">
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
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="font-medium"
                                    >
                                        Total
                                    </TableCell>
                                    <TableCell className="tabular text-right font-medium whitespace-nowrap">
                                        {formatCurrency(totals.ai)}
                                    </TableCell>
                                    <TableCell className="tabular text-right font-medium whitespace-nowrap">
                                        {formatCurrency(totals.aki)}
                                    </TableCell>
                                    <TableCell colSpan={canManage ? 3 : 2} />
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                )}
            </div>

            {canManage && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
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
        prk_induk: plan?.prk_induk ?? '',
        work_director_id:
            plan?.work_director_id === null || plan === null
                ? ''
                : String(plan.work_director_id),
        prk_induk_description: plan?.prk_induk_description ?? '',
        sub_program_description: plan?.sub_program_description ?? '',
        ai_plan_value: plan?.ai_plan_value ?? 0,
        aki_plan_value: plan?.aki_plan_value ?? 0,
        wo_number: plan?.wo_number ?? '',
        pr_number: plan?.pr_number ?? '',
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onDone };

        if (plan === null) {
            form.post(budgetPlans.store().url, options);
        } else {
            form.put(budgetPlans.update(plan.id).url, options);
        }
    };

    const text = (
        name: 'prk_induk' | 'wo_number' | 'pr_number',
        label: string,
        placeholder: string,
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`budget-${name}`}>{label}</Label>
            <Input
                id={`budget-${name}`}
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
                placeholder={placeholder}
                className="tabular"
                autoComplete="off"
            />
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
                    PRK ini muncul sebagai pilihan Nomor PRK pada form Usulan
                    Pekerjaan bidang yang dipilih.
                </DialogDescription>
            </DialogHeader>

            <form
                id="budget-plan-form"
                className="grid grid-cols-1 items-start gap-4 md:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
            >
                {text('prk_induk', 'PRK Induk', 'Contoh: 2026.AO.01.001')}
                <div className="grid gap-1.5">
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
                <div className="grid gap-1.5 md:col-span-2">
                    <Label htmlFor="budget-prk_induk_description">
                        Deskripsi PRK Induk
                    </Label>
                    <Textarea
                        id="budget-prk_induk_description"
                        rows={2}
                        value={form.data.prk_induk_description}
                        onChange={(event) =>
                            form.setData(
                                'prk_induk_description',
                                event.target.value,
                            )
                        }
                    />
                    <InputError message={form.errors.prk_induk_description} />
                </div>
                <div className="grid gap-1.5 md:col-span-2">
                    <Label htmlFor="budget-sub_program_description">
                        Deskripsi Sub Program
                    </Label>
                    <Textarea
                        id="budget-sub_program_description"
                        rows={2}
                        value={form.data.sub_program_description}
                        onChange={(event) =>
                            form.setData(
                                'sub_program_description',
                                event.target.value,
                            )
                        }
                    />
                    <InputError message={form.errors.sub_program_description} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="budget-ai_plan_value">
                        Rencana AI / Nilai RAB
                    </Label>
                    <CurrencyInput
                        id="budget-ai_plan_value"
                        value={form.data.ai_plan_value}
                        onValueChange={(value) =>
                            form.setData('ai_plan_value', value)
                        }
                    />
                    <p className="text-[11px] text-muted-foreground">
                        Total PRK tidak melebihi Penetapan AI.
                    </p>
                    <InputError message={form.errors.ai_plan_value} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="budget-aki_plan_value">Rencana AKI</Label>
                    <CurrencyInput
                        id="budget-aki_plan_value"
                        value={form.data.aki_plan_value}
                        onValueChange={(value) =>
                            form.setData('aki_plan_value', value)
                        }
                    />
                    <p className="text-[11px] text-muted-foreground">
                        Total PRK tidak melebihi Penetapan AKI.
                    </p>
                    <InputError message={form.errors.aki_plan_value} />
                </div>
                {text('wo_number', 'No WO', 'Contoh: WO-2026-0042')}
                {text('pr_number', 'No PR', 'Contoh: 1000012345')}
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

BudgetPlansIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Perencanaan Anggaran', href: budgetPlans.index() },
    ],
};
