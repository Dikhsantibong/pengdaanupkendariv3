import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    ChevronRight,
    Clock,
    Landmark,
    Search,
    Wallet,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    formatCompactCurrency,
    formatCurrency,
    formatDateTime,
} from '@/lib/format';
import { dashboard } from '@/routes';
import finance from '@/routes/finance';
import procurements from '@/routes/procurements';

type FinanceRow = {
    id: number;
    number: string;
    name: string;
    partner_name: string | null;
    division: string;
    executor: string | null;
    value: number;
    submitted_at: string | null;
    submitted_by: string | null;
    paid_at: string | null;
    paid_by: string | null;
    steps_done: number;
    steps_total: number;
};

/** Open the job on its Keuangan tab. */
function financeTabUrl(id: number): string {
    return procurements.show(id, { query: { tab: 'keuangan' } }).url;
}

function WaitingCard({ row }: { row: FinanceRow }) {
    const percentage =
        row.steps_total > 0
            ? Math.round((row.steps_done / row.steps_total) * 100)
            : 0;
    const ready = row.steps_total > 0 && row.steps_done === row.steps_total;

    return (
        <Link
            href={financeTabUrl(row.id)}
            className="group flex flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-xs transition hover:border-primary/50 hover:shadow-md"
        >
            <div className="min-w-0">
                <p className="tabular text-xs text-muted-foreground">
                    {row.number}
                </p>
                <p className="line-clamp-2 font-semibold text-foreground group-hover:text-primary">
                    {row.name}
                </p>
            </div>

            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">Mitra</dt>
                    <dd className="truncate font-medium">
                        {row.partner_name ?? '-'}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Nilai Tagihan
                    </dt>
                    <dd className="tabular font-medium">
                        {formatCurrency(row.value)}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Bidang</dt>
                    <dd className="truncate">{row.division}</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Diserahkan
                    </dt>
                    <dd className="tabular">
                        {formatDateTime(row.submitted_at)}
                    </dd>
                </div>
            </dl>

            <div className="space-y-1.5 border-t border-border pt-3">
                <div className="tabular flex items-center justify-between text-xs">
                    <span className="text-muted-foreground">
                        {row.steps_done}/{row.steps_total} tahapan keuangan
                    </span>
                    <span className="flex items-center gap-1 font-semibold text-primary">
                        {ready ? 'Siap dicatat lunas' : 'Proses'}
                        <ChevronRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                    </span>
                </div>
                <Progress value={percentage} />
            </div>
        </Link>
    );
}

export default function FinanceIndex({
    waiting,
    paid,
    summary,
    filters,
}: {
    waiting: FinanceRow[];
    paid: FinanceRow[];
    summary: { waiting: number; waitingValue: number; paidThisMonth: number };
    filters: { search: string | null };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(finance.index().url, search ? { search } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);

        return () => clearTimeout(timeout);
    }, [search]);

    return (
        <>
            <Head title="Keuangan" />

            <div className="flex w-full flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Tahap Keuangan"
                    title="Keuangan"
                    description="Pengadaan yang pelaksanaannya selesai dan diserahkan untuk dibayar. Lengkapi dokumen tagihan, catat pembayaran, lalu pengadaan otomatis ditutup."
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard
                        label="Menunggu Pembayaran"
                        value={summary.waiting}
                        icon={Clock}
                        accent
                    />
                    <StatCard
                        label="Nilai Menunggu Dibayar"
                        value={formatCompactCurrency(summary.waitingValue)}
                        hint={formatCurrency(summary.waitingValue)}
                        icon={Wallet}
                    />
                    <StatCard
                        label="Dibayar Bulan Ini"
                        value={summary.paidThisMonth}
                        icon={BadgeCheck}
                    />
                </div>

                <div className="relative max-w-md">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nomor, nama pekerjaan, atau mitra"
                        className="pl-9"
                        aria-label="Cari pengadaan"
                    />
                </div>

                <section className="space-y-3">
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <Clock className="size-5 text-primary" />
                        Menunggu Pembayaran
                        <span className="tabular rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                            {waiting.length}
                        </span>
                    </h2>
                    {waiting.length === 0 ? (
                        <div className="rounded-md border border-border bg-card">
                            <EmptyState
                                icon={Landmark}
                                title="Tidak ada tagihan yang menunggu"
                                description="Pengadaan yang pelaksanaannya disetujui TL Pengadaan akan muncul di sini."
                            />
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                            {waiting.map((row) => (
                                <WaitingCard key={row.id} row={row} />
                            ))}
                        </div>
                    )}
                </section>

                {paid.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="flex items-center gap-2 text-base font-semibold">
                            <BadgeCheck className="size-5 text-emerald-600" />
                            Sudah Dibayar
                        </h2>
                        <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                            {paid.map((row) => (
                                <li key={row.id}>
                                    <Link
                                        href={financeTabUrl(row.id)}
                                        className="group flex flex-col gap-1 px-4 py-3 transition-colors hover:bg-accent/40 sm:flex-row sm:items-center sm:gap-4"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium group-hover:text-primary">
                                                {row.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {row.number} ·{' '}
                                                {row.partner_name ?? '-'}
                                            </p>
                                        </div>
                                        <span className="tabular text-sm font-medium">
                                            {formatCurrency(row.value)}
                                        </span>
                                        <span className="tabular text-xs text-muted-foreground sm:w-48 sm:text-right">
                                            Lunas {formatDateTime(row.paid_at)}
                                            {row.paid_by
                                                ? ` · ${row.paid_by}`
                                                : ''}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

FinanceIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Keuangan', href: finance.index() },
    ],
};
