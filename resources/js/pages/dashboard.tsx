import { Head, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Ban,
    CalendarClock,
    ChevronRight,
    CircleDashed,
    CircleDot,
    ClipboardCheck,
    FolderKanban,
    ListChecks,
    PartyPopper,
    RotateCcw,
    UserPlus,
    Wallet,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
    formatDateTime,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import approvals from '@/routes/approvals';
import execution from '@/routes/execution';
import picAssignments from '@/routes/pic-assignments';
import planning from '@/routes/planning';
import procurements from '@/routes/procurements';
import type { Auth, ProcurementRow } from '@/types';

type Breakdown = { label: string; total: number };

type TaskItem = {
    id: number;
    number: string;
    name: string;
    note: string;
    date: string | null;
};

type TaskList = { total: number; items: TaskItem[] };

type DashboardTasks = {
    approvals: TaskList | null;
    assignments: TaskList | null;
    planning: TaskList;
    execution: TaskList;
};

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
    tasks: DashboardTasks;
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
    tasks,
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

                <TaskInbox tasks={tasks} menus={auth.permissions.menus} />

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
                                ? 'Perlu tindakan TL ICC'
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

/**
 * The work waiting on the signed-in user, each row linking to where it is done.
 */
function TaskInbox({
    tasks,
    menus,
}: {
    tasks: DashboardTasks;
    menus: Auth['permissions']['menus'];
}) {
    const cards: Array<{
        key: string;
        title: string;
        icon: LucideIcon;
        list: TaskList;
        emptyText: string;
        itemHref: (item: TaskItem) => string;
        dateLabel: (item: TaskItem) => string | null;
        allHref: string | null;
        tone: string;
    }> = [];

    if (tasks.approvals !== null) {
        cards.push({
            key: 'approvals',
            title: 'Menunggu Persetujuan Anda',
            icon: ClipboardCheck,
            list: tasks.approvals,
            emptyText: 'Tidak ada perencanaan yang menunggu persetujuan.',
            itemHref: (item) => procurements.show(item.id).url,
            dateLabel: (item) =>
                item.date ? `Diajukan ${formatDateTime(item.date)}` : null,
            allHref: menus.approvals ? approvals.index().url : null,
            tone: 'bg-primary/10 text-primary',
        });
    }

    if (tasks.assignments !== null) {
        cards.push({
            key: 'assignments',
            title: 'Perlu Penunjukan PIC',
            icon: UserPlus,
            list: tasks.assignments,
            emptyText: 'Semua pengadaan aktif sudah memiliki PIC.',
            itemHref: (item) =>
                picAssignments.index({
                    query: { unassigned: 1, search: item.number },
                }).url,
            dateLabel: (item) =>
                item.date ? `Dibuat ${formatDate(item.date)}` : null,
            allHref: picAssignments.index({ query: { unassigned: 1 } }).url,
            tone: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        });
    }

    if (tasks.planning.total > 0) {
        cards.push({
            key: 'planning',
            title: 'Tugas Perencanaan Anda',
            icon: ListChecks,
            list: tasks.planning,
            emptyText: '',
            itemHref: (item) => procurements.show(item.id).url,
            dateLabel: (item) =>
                item.date ? `Target ${formatDate(item.date)}` : null,
            allHref: menus.planning ? planning.index().url : null,
            tone: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
        });
    }

    if (tasks.execution.total > 0) {
        cards.push({
            key: 'execution',
            title: 'Tugas Pelaksanaan Anda',
            icon: Wrench,
            list: tasks.execution,
            emptyText: '',
            itemHref: (item) => procurements.show(item.id).url,
            dateLabel: (item) =>
                item.date ? `Target ${formatDate(item.date)}` : null,
            allHref: menus.execution ? execution.index().url : null,
            tone: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        });
    }

    const pending = cards.reduce((sum, card) => sum + card.list.total, 0);

    if (pending === 0) {
        return (
            <section className="flex items-center gap-3 rounded-md border border-border bg-card px-4 py-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                    <PartyPopper className="size-4" />
                </span>
                <div>
                    <p className="text-sm font-semibold text-foreground">
                        Tidak ada tugas yang menunggu Anda
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Approval, penunjukan PIC, dan tugas pengadaan Anda akan
                        muncul di sini.
                    </p>
                </div>
            </section>
        );
    }

    return (
        <section className="space-y-3">
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="text-sm font-semibold text-foreground">
                    Perlu Tindakan Anda
                </h2>
                <span className="tabular text-xs text-muted-foreground">
                    {pending} tugas menunggu
                </span>
            </div>

            <div
                className={cn(
                    'grid gap-4',
                    cards.length > 1 && 'lg:grid-cols-2',
                )}
            >
                {cards.map((card) => (
                    <div
                        key={card.key}
                        className="flex flex-col overflow-hidden rounded-md border border-border bg-card"
                    >
                        <header className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                            <div className="flex min-w-0 items-center gap-2.5">
                                <span
                                    className={cn(
                                        'flex size-7 shrink-0 items-center justify-center rounded-md',
                                        card.tone,
                                    )}
                                >
                                    <card.icon className="size-4" />
                                </span>
                                <h3 className="truncate text-sm font-semibold text-foreground">
                                    {card.title}
                                </h3>
                                <span
                                    className={cn(
                                        'tabular rounded-full px-2 py-0.5 text-xs font-semibold',
                                        card.list.total > 0
                                            ? card.tone
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {card.list.total}
                                </span>
                            </div>
                            {card.allHref !== null && card.list.total > 0 && (
                                <Link
                                    href={card.allHref}
                                    className="shrink-0 text-xs font-medium text-primary hover:underline"
                                >
                                    Lihat semua
                                </Link>
                            )}
                        </header>

                        {card.list.items.length === 0 ? (
                            <p className="px-4 py-6 text-center text-xs text-muted-foreground">
                                {card.emptyText}
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {card.list.items.map((item) => {
                                    const date = card.dateLabel(item);

                                    return (
                                        <li key={item.id}>
                                            <Link
                                                href={card.itemHref(item)}
                                                className="group flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-accent/40"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-foreground group-hover:text-primary">
                                                        {item.name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        <span className="tabular">
                                                            {item.number}
                                                        </span>
                                                        {' · '}
                                                        {item.note}
                                                    </p>
                                                </div>
                                                {date !== null && (
                                                    <span className="tabular hidden shrink-0 text-xs text-muted-foreground sm:block">
                                                        {date}
                                                    </span>
                                                )}
                                                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        {card.list.total > card.list.items.length && (
                            <p className="mt-auto border-t border-border px-4 py-2 text-xs text-muted-foreground">
                                +{card.list.total - card.list.items.length}{' '}
                                lainnya
                            </p>
                        )}
                    </div>
                ))}
            </div>
        </section>
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
