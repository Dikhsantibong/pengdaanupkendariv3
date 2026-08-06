import { Head, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Ban,
    CalendarClock,
    CircleDashed,
    CircleDot,
    FolderKanban,
    RotateCcw,
    Wallet,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatCompactCurrency,
    formatCurrency,
    formatDate,
} from '@/lib/format';
import { dashboard } from '@/routes';
import approvals from '@/routes/approvals';
import planning from '@/routes/planning';
import procurements from '@/routes/procurements';
import type { Auth, ProcurementRow } from '@/types';

type Breakdown = { label: string; total: number };

type DashboardProps = {
    summary: {
        total: number;
        running: number;
        completed: number;
        pending: number;
        cancelled: number;
        awaitingApproval: number;
        needsRevision: number;
        totalHpe: number;
    };
    byStatus: Breakdown[];
    byWorkDirector: Breakdown[];
    byTargetUnit: Breakdown[];
    byProcurementMethod: Breakdown[];
    byBudgetSource: Breakdown[];
    byPlanner: Breakdown[];
    byExecutor: Breakdown[];
    recent: ProcurementRow[];
    upcoming: ProcurementRow[];
};

export default function Dashboard({
    summary,
    byStatus,
    byWorkDirector,
    byTargetUnit,
    byProcurementMethod,
    byBudgetSource,
    byPlanner,
    byExecutor,
    recent,
    upcoming,
}: DashboardProps) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Sistem Management Pengadaan UP Kendari"
                    title={`Selamat datang, ${auth.user.name}`}
                    description={
                        auth.permissions.viewAllProcurements
                            ? 'Ringkasan seluruh pengadaan barang dan jasa yang berjalan di UP Kendari.'
                            : 'Ringkasan pengadaan yang ditugaskan kepada Anda.'
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Total Pengadaan"
                        value={summary.total}
                        icon={FolderKanban}
                        accent
                    />
                    <StatCard
                        label="Pengadaan Berjalan"
                        value={summary.running}
                        icon={CircleDot}
                        hint={`${summary.pending} pending · ${summary.cancelled} batal`}
                    />
                    <StatCard
                        label="Pengadaan Selesai"
                        value={summary.completed}
                        icon={BadgeCheck}
                    />
                    <StatCard
                        label="Menunggu Approval"
                        value={summary.awaitingApproval}
                        icon={CircleDashed}
                        hint={
                            summary.awaitingApproval > 0
                                ? 'Perlu tindakan TL Perencanaan'
                                : 'Tidak ada antrean'
                        }
                    />
                </div>

                {summary.needsRevision > 0 && (
                    <Link
                        href={
                            planning.index({
                                query: { approval_state: 'ditolak' },
                            }).url
                        }
                        className="flex items-start gap-3 rounded-md border border-destructive/40 bg-destructive/5 p-4 transition-colors hover:bg-destructive/10"
                    >
                        <RotateCcw className="mt-0.5 size-4 shrink-0 text-destructive" />
                        <div className="space-y-0.5">
                            <p className="text-sm font-semibold text-destructive">
                                {summary.needsRevision} perencanaan dikembalikan
                                untuk revisi
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Perbaiki sesuai catatan reviewer, lalu ajukan
                                ulang untuk ditinjau kembali.
                            </p>
                        </div>
                    </Link>
                )}

                <div className="grid gap-3 sm:grid-cols-2">
                    <StatCard
                        label="Total Nilai HPE"
                        value={formatCompactCurrency(summary.totalHpe)}
                        hint={formatCurrency(summary.totalHpe)}
                        icon={Wallet}
                    />
                    <StatCard
                        label="Pengadaan Dibatalkan"
                        value={summary.cancelled}
                        icon={Ban}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <BreakdownCard
                        title="Progres Pengadaan"
                        rows={byStatus}
                        total={summary.total}
                    />
                    <BreakdownCard
                        title="Berdasarkan Direksi Pekerjaan"
                        rows={byWorkDirector}
                        total={summary.total}
                    />
                    <BreakdownCard
                        title="Berdasarkan Unit Tujuan"
                        rows={byTargetUnit}
                        total={summary.total}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <BreakdownCard
                        title="Berdasarkan Metode Pengadaan"
                        rows={byProcurementMethod}
                        total={summary.total}
                    />
                    <BreakdownCard
                        title="Berdasarkan Sumber Anggaran"
                        rows={byBudgetSource}
                        total={summary.total}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <BreakdownCard
                        title="Berdasarkan PIC Perencana"
                        rows={byPlanner}
                        total={summary.total}
                    />
                    <BreakdownCard
                        title="Berdasarkan PIC Pelaksana"
                        rows={byExecutor}
                        total={summary.total}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <RecentTable rows={recent} />
                    <ScheduleCard rows={upcoming} />
                </div>

                {summary.awaitingApproval > 0 &&
                    auth.permissions.viewAllProcurements && (
                        <Link
                            href={approvals.index()}
                            className="flex items-center justify-between rounded-md border border-l-2 border-border border-l-primary bg-card px-4 py-3 text-sm transition-colors hover:bg-accent/40"
                        >
                            <span className="font-medium text-foreground">
                                {summary.awaitingApproval} pengadaan menunggu
                                persetujuan perencanaan
                            </span>
                            <span className="text-xs text-muted-foreground">
                                Buka antrean approval →
                            </span>
                        </Link>
                    )}
            </div>
        </>
    );
}

function BreakdownCard({
    title,
    rows,
    total,
}: {
    title: string;
    rows: Breakdown[];
    total: number;
}) {
    return (
        <section className="rounded-md border border-border bg-card">
            <header className="border-b border-border px-4 py-3">
                <h2 className="text-sm font-semibold text-foreground">
                    {title}
                </h2>
            </header>

            {rows.length === 0 ? (
                <EmptyState title="Belum ada data" className="py-8" />
            ) : (
                <ul className="divide-y divide-border">
                    {rows.slice(0, 8).map((row) => (
                        <li key={row.label} className="px-4 py-2.5">
                            <div className="flex items-center justify-between gap-3 text-sm">
                                <span className="truncate text-foreground">
                                    {row.label}
                                </span>
                                <span className="tabular font-semibold">
                                    {row.total}
                                </span>
                            </div>
                            <div className="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full bg-primary/70"
                                    style={{
                                        width: `${total > 0 ? (row.total / total) * 100 : 0}%`,
                                    }}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function RecentTable({ rows }: { rows: ProcurementRow[] }) {
    return (
        <section className="overflow-hidden rounded-md border border-border bg-card">
            <header className="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 className="text-sm font-semibold text-foreground">
                    Pengadaan Terbaru
                </h2>
                <Link
                    href={procurements.index()}
                    className="text-xs font-medium text-primary hover:underline"
                >
                    Lihat semua
                </Link>
            </header>

            {rows.length === 0 ? (
                <EmptyState title="Belum ada pengadaan terdaftar" />
            ) : (
                <Table>
                    <TableHeader className="bg-muted/60">
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Pengadaan</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="text-right">
                                Nilai HPE
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell>
                                    <Link
                                        href={procurements.show(row.id)}
                                        className="block max-w-[18rem] truncate font-medium hover:text-primary hover:underline"
                                    >
                                        {row.name}
                                    </Link>
                                    <span className="tabular text-xs text-muted-foreground">
                                        {row.number} · {row.target_unit}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <StatusBadge
                                        label={row.status.name}
                                        category={row.status.category}
                                    />
                                </TableCell>
                                <TableCell className="tabular text-right font-medium whitespace-nowrap">
                                    {formatCurrency(row.hpe_value)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}

function ScheduleCard({ rows }: { rows: ProcurementRow[] }) {
    return (
        <section className="rounded-md border border-border bg-card">
            <header className="border-b border-border px-4 py-3">
                <h2 className="text-sm font-semibold text-foreground">
                    Jadwal Pengadaan
                </h2>
            </header>

            {rows.length === 0 ? (
                <EmptyState
                    icon={CalendarClock}
                    title="Belum ada target penyelesaian"
                    description="Isi target penyelesaian pada data pengadaan agar tampil di sini."
                />
            ) : (
                <ul className="divide-y divide-border">
                    {rows.map((row) => (
                        <li
                            key={row.id}
                            className="flex items-center justify-between gap-3 px-4 py-3"
                        >
                            <div className="min-w-0">
                                <Link
                                    href={procurements.show(row.id)}
                                    className="block truncate text-sm font-medium hover:text-primary hover:underline"
                                >
                                    {row.name}
                                </Link>
                                <span className="tabular text-xs text-muted-foreground">
                                    {row.number}
                                </span>
                            </div>
                            <span className="tabular shrink-0 text-xs font-medium text-foreground">
                                {formatDate(row.target_completion_date)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
