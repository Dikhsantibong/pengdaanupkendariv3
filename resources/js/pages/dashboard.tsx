import { Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    ClipboardCheck,
    FilePlus2,
    Inbox,
    Landmark,
    ListChecks,
    PartyPopper,
    RotateCcw,
    UserCircle2,
    UserPlus,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Children, cloneElement, isValidElement, useState } from 'react';
import type { ReactElement, ReactNode } from 'react';
import { AreaChart } from '@/components/charts/area-chart';
import { CATEGORICAL, VIZ } from '@/components/charts/chart-kit';
import { GaugeChart } from '@/components/charts/gauge-chart';
import { GroupedColumnChart } from '@/components/charts/grouped-column-chart';
import { PairedBars, RankingBars } from '@/components/charts/ranking-bars';
import { RingChart } from '@/components/charts/ring-chart';
import type { RingSlice } from '@/components/charts/ring-chart';
import {
    ActivityTimeline,
    DashboardFilterBar,
    KpiGrid,
    Panel,
    queryOf,
    RecentProcurementTable,
    SectionError,
    STAGE_COLORS,
    StagePipeline,
    UpcomingList,
    visitDashboard,
} from '@/components/dashboard/analytics';
import type {
    Analytics,
    Breakdown,
    DashboardFilterState,
    FilterOptions,
    TablePage,
    TableQuery,
} from '@/components/dashboard/analytics';
import {
    DivisionBudgetPlans,
    FigureCards,
    PROFILE_DESCRIPTIONS,
    RoleJobs,
} from '@/components/dashboard/role-dashboard';
import type {
    RoleDashboardData,
    RoleProfile,
} from '@/components/dashboard/role-dashboard';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    formatCompactCurrency,
    formatCurrency,
    formatDate,
    formatDateTime,
    formatNumber,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import approvals from '@/routes/approvals';
import execution from '@/routes/execution';
import finance from '@/routes/finance';
import picAssignments from '@/routes/pic-assignments';
import planning from '@/routes/planning';
import procurements from '@/routes/procurements';
import proposals from '@/routes/proposals';
import type { Auth } from '@/types';

type TaskItem = {
    id: number;
    number: string;
    name: string;
    note: string;
    date: string | null;
};

type TaskList = { total: number; items: TaskItem[] };

type DashboardTasks = {
    proposals: TaskList | null;
    budgetPlans?: TaskList | null;
    myProposals: TaskList;
    finance: TaskList | null;
    approvals: TaskList | null;
    executionApprovals: TaskList | null;
    handoverApprovals: TaskList | null;
    paymentApprovals: TaskList | null;
    amendmentApprovals: TaskList | null;
    deliveryOrderApprovals: TaskList | null;
    assignments: TaskList | null;
    planning: TaskList;
    execution: TaskList;
};

type DashboardProps = {
    profile: RoleProfile;
    role?: RoleDashboardData;
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
    tasks: DashboardTasks;
    account: { role: string; division: string | null };
    filters: DashboardFilterState;
    filterOptions: FilterOptions;
    analytics: Analytics;
    table: TablePage;
    tableQuery: TableQuery;
};

export default function Dashboard(props: DashboardProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const {
        profile,
        role,
        tasks,
        summary,
        account,
        filters,
        filterOptions,
        analytics,
        table,
        tableQuery,
    } = props;
    const [loading, setLoading] = useState(false);
    const isManagement = profile === 'manajemen' || role === undefined;
    const selectCard = (card: string | null) =>
        visitDashboard(
            queryOf(filters, tableQuery, { card, table_page: null }),
            setLoading,
            ['role', 'table', 'tableQuery'],
        );

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="SIM-SCM UP Kendari · Dashboard Pengadaan"
                    title={`Selamat datang, ${auth.user.name}`}
                    description={PROFILE_DESCRIPTIONS[profile]}
                    actions={
                        <>
                            <span className="flex items-center gap-2 rounded-md border border-border bg-card px-3 py-1.5 text-xs">
                                <UserCircle2 className="size-4 text-muted-foreground" />
                                <span className="leading-tight">
                                    <span className="block font-semibold text-foreground">
                                        {account.role}
                                    </span>
                                    {account.division && (
                                        <span className="block text-muted-foreground">
                                            {account.division}
                                        </span>
                                    )}
                                </span>
                            </span>
                            {auth.permissions.createProcurement && (
                                <Button asChild>
                                    <Link href={procurements.create()}>
                                        <FilePlus2 className="size-4" />
                                        {auth.permissions.proposeOnly
                                            ? 'Buat Usulan Pekerjaan'
                                            : 'Buat Pengadaan'}
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <DashboardFilterBar
                    filters={filters}
                    options={filterOptions}
                    tableQuery={tableQuery}
                    loading={loading}
                    setLoading={setLoading}
                />

                <div
                    className={cn(
                        'flex flex-col gap-5 transition-opacity',
                        loading && 'pointer-events-none opacity-60',
                    )}
                    aria-busy={loading}
                >
                    <AnalyticsSections
                        analytics={analytics}
                        filters={filters}
                        filterOptions={filterOptions}
                        tableQuery={tableQuery}
                        table={table}
                        setLoading={setLoading}
                        canOpenApprovals={auth.permissions.menus.approvals}
                        // Under the headline figures: the role's own
                        // figures, then what waits on the user, in one line.
                        afterKpis={
                            <>
                                {!isManagement && role !== undefined && (
                                    <FigureCards
                                        cards={role.cards}
                                        activeCard={role.activeCard ?? null}
                                        onSelect={selectCard}
                                    />
                                )}
                                <TaskInbox
                                    tasks={tasks}
                                    menus={auth.permissions.menus}
                                />
                                {(isManagement || profile === 'perencana') &&
                                    summary.needsRevision > 0 && (
                                        <RevisionNotice
                                            count={summary.needsRevision}
                                        />
                                    )}
                            </>
                        }
                        beforeTable={
                            !isManagement && role !== undefined ? (
                                <RoleJobsSection
                                    profile={profile}
                                    role={role}
                                    menus={auth.permissions.menus}
                                    canPropose={
                                        auth.permissions.createProcurement
                                    }
                                    onResetFilter={() => selectCard(null)}
                                />
                            ) : undefined
                        }
                    />
                </div>
            </div>
        </>
    );
}

/**
 * Up to five named slices; the rest folded into "Lainnya".
 */
function categoricalSlices(rows: Breakdown[]): RingSlice[] {
    const sorted = [...rows].sort((a, b) => b.total - a.total);
    const head = sorted.slice(0, 4);
    const rest = sorted.slice(4);
    const slices = head.map((row, index) => ({
        key: row.label,
        label: row.label,
        value: row.total,
        color: CATEGORICAL[index],
    }));

    if (rest.length === 1) {
        slices.push({
            key: rest[0].label,
            label: rest[0].label,
            value: rest[0].total,
            color: CATEGORICAL[4],
        });
    } else if (rest.length > 1) {
        slices.push({
            key: 'lainnya',
            label: `Lainnya (${rest.length})`,
            value: rest.reduce((sum, row) => sum + row.total, 0),
            color: CATEGORICAL[4],
        });
    }

    return slices;
}

/**
 * Every chart and list the profile shows, in order of importance.
 */
function AnalyticsSections({
    analytics,
    filters,
    filterOptions,
    tableQuery,
    table,
    setLoading,
    canOpenApprovals,
    afterKpis,
    beforeTable,
}: {
    analytics: Analytics;
    filters: DashboardFilterState;
    filterOptions: FilterOptions;
    tableQuery: TableQuery;
    table: TablePage;
    setLoading: (loading: boolean) => void;
    canOpenApprovals: boolean;
    afterKpis?: ReactNode;
    beforeTable?: ReactNode;
}) {
    const shows = (section: string) => analytics.sections.includes(section);
    const hasSideColumn = shows('upcoming') || shows('statuses');
    const filterStage = (stage: string | null) =>
        visitDashboard(
            queryOf(filters, tableQuery, { stage, table_page: null }),
            setLoading,
        );

    /** A section's content, or its error state when it failed. */
    const render = <T,>(
        value: T | null | undefined,
        content: (value: T) => ReactNode,
    ) =>
        value === null || value === undefined ? (
            <SectionError />
        ) : (
            content(value)
        );

    return (
        <>
            {shows('kpis') &&
                (analytics.kpis ? (
                    <KpiGrid
                        kpis={analytics.kpis}
                        canOpenApprovals={canOpenApprovals}
                    />
                ) : (
                    <SectionError />
                ))}

            {afterKpis}

            {shows('stages') &&
                (analytics.stages ? (
                    <StagePipeline
                        stages={analytics.stages}
                        activeStage={filters.stage}
                        onSelect={filterStage}
                    />
                ) : (
                    <SectionError />
                ))}

            {beforeTable}

            <RecentProcurementTable
                page={table}
                filters={filters}
                options={filterOptions}
                tableQuery={tableQuery}
                setLoading={setLoading}
            />

            <FilledGrid>
                {shows('trend') && (
                    <Panel
                        title="Tren Pengadaan"
                        description="Jumlah pengadaan per bulan didaftarkan, menurut statusnya saat ini."
                        span={2}
                    >
                        {render(analytics.trend, (trend) => (
                            <GroupedColumnChart
                                labels={trend.months.map(
                                    (month) => month.label,
                                )}
                                series={[
                                    {
                                        key: 'total',
                                        label: 'Total',
                                        color: VIZ.blue,
                                        values: trend.total,
                                    },
                                    {
                                        key: 'running',
                                        label: 'Berjalan',
                                        color: VIZ.yellow,
                                        values: trend.running,
                                    },
                                    {
                                        key: 'completed',
                                        label: 'Selesai',
                                        color: VIZ.green,
                                        values: trend.completed,
                                    },
                                    {
                                        key: 'cancelled',
                                        label: 'Dibatalkan',
                                        color: VIZ.red,
                                        values: trend.cancelled,
                                    },
                                ]}
                                emptyText="Belum ada pengadaan yang didaftarkan pada periode ini."
                            />
                        ))}
                    </Panel>
                )}

                {shows('stages') && (
                    <Panel
                        title="Status Pengadaan"
                        description="Sebaran pengadaan per tahap. Klik untuk menyaring."
                    >
                        {render(analytics.stages, (stages) => (
                            <RingChart
                                slices={stages.map((stage) => ({
                                    key: stage.key,
                                    label: stage.label,
                                    value: stage.total,
                                    color: STAGE_COLORS[stage.key],
                                }))}
                                centerLabel="Pengadaan"
                                onSelect={(stage) =>
                                    filterStage(
                                        filters.stage === stage ? null : stage,
                                    )
                                }
                            />
                        ))}
                    </Panel>
                )}

                {shows('budget') && (
                    <Panel
                        title="Perkembangan Nilai Pengadaan"
                        description="Nilai HPE pengadaan per bulan didaftarkan. HPE adalah perkiraan, bukan realisasi pembayaran."
                        span={2}
                    >
                        {render(analytics.budget, (budget) => (
                            <div className="space-y-2">
                                <AreaChart
                                    labels={budget.months.map(
                                        (month) => month.label,
                                    )}
                                    series={[
                                        {
                                            key: 'hpe',
                                            label: 'Nilai HPE',
                                            color: VIZ.blue,
                                            values: budget.hpe,
                                        },
                                        {
                                            key: 'running',
                                            label: 'Nilai berjalan',
                                            color: VIZ.yellow,
                                            values: budget.running,
                                        },
                                        {
                                            key: 'completed',
                                            label: 'Nilai selesai',
                                            color: VIZ.green,
                                            values: budget.completed,
                                        },
                                    ]}
                                    format={(value) => formatCurrency(value)}
                                    axisFormat={(value) =>
                                        formatCompactCurrency(value)
                                    }
                                    emptyText="Belum ada nilai HPE pada periode ini."
                                />
                                {budget.target === null && (
                                    <p className="text-xs text-muted-foreground">
                                        Target anggaran belum tersedia di
                                        database, sehingga tidak ditampilkan.
                                    </p>
                                )}
                            </div>
                        ))}
                    </Panel>
                )}

                {shows('methods') && (
                    <Panel
                        title="Metode Pengadaan"
                        description="Jumlah pengadaan per metode."
                    >
                        {render(analytics.methods, (methods) => (
                            <RingChart
                                slices={categoricalSlices(methods)}
                                centerLabel="Pengadaan"
                            />
                        ))}
                    </Panel>
                )}

                {shows('progress') && (
                    <Panel
                        title="Progres Pengadaan"
                        description="Rata-rata posisi pengadaan aktif pada alur, dari usulan (0%) sampai selesai (100%)."
                    >
                        {render(analytics.progress, (progress) =>
                            progress.active === 0 ? (
                                <SectionEmpty />
                            ) : (
                                <div className="space-y-4">
                                    <GaugeChart
                                        percent={progress.percent}
                                        label="Progres keseluruhan"
                                    />
                                    <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-md border border-border bg-border text-sm">
                                        {[
                                            {
                                                label: 'Penyusunan Surat Pesanan',
                                                value: formatNumber(
                                                    progress.purchaseOrder,
                                                ),
                                            },
                                            {
                                                label: 'Terkontrak',
                                                value: formatNumber(
                                                    progress.contracted,
                                                ),
                                            },
                                            {
                                                label: 'Dalam Pelaksanaan',
                                                value: formatNumber(
                                                    progress.inExecution,
                                                ),
                                            },
                                            {
                                                label: 'Penyelesaian',
                                                value: `${progress.completion.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`,
                                            },
                                        ].map((item) => (
                                            <div
                                                key={item.label}
                                                className="bg-card px-3 py-2"
                                            >
                                                <dt className="text-[0.6875rem] text-muted-foreground">
                                                    {item.label}
                                                </dt>
                                                <dd className="tabular text-base font-semibold text-foreground">
                                                    {item.value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                            ),
                        )}
                    </Panel>
                )}

                {shows('directorates') && (
                    <Panel
                        title="Peringkat Direksi Pekerjaan"
                        description="Jumlah pengadaan per bidang, terbanyak di atas."
                    >
                        {render(analytics.directorates, (rows) => (
                            <RankingBars rows={rows} color={VIZ.blue} />
                        ))}
                    </Panel>
                )}

                {shows('units') && (
                    <Panel
                        title="Unit Tujuan"
                        description="Pengadaan untuk beberapa unit dihitung di setiap unit."
                    >
                        {render(analytics.units, (rows) => (
                            <RankingBars rows={rows} color={VIZ.navy} />
                        ))}
                    </Panel>
                )}

                {shows('budgetSources') && (
                    <Panel
                        title="Sumber Anggaran"
                        description="Jumlah pengadaan per sumber anggaran."
                    >
                        {render(analytics.budgetSources, (rows) => (
                            <RingChart
                                slices={categoricalSlices(rows)}
                                centerLabel="Pengadaan"
                            />
                        ))}
                    </Panel>
                )}

                {shows('pics') && (
                    <Panel
                        title="Distribusi PIC"
                        description="Jumlah penugasan per PIC. Bukan ukuran kinerja."
                        span={2}
                    >
                        {render(analytics.pics, (rows) => (
                            <PairedBars
                                rows={rows}
                                emptyText="Belum ada PIC yang ditunjuk pada pengadaan sesuai filter."
                            />
                        ))}
                    </Panel>
                )}
            </FilledGrid>

            {(shows('activities') || hasSideColumn) && (
                <div
                    className={cn(
                        'grid gap-4',
                        shows('activities') &&
                            hasSideColumn &&
                            'xl:grid-cols-3',
                    )}
                >
                    {shows('activities') && (
                        <Panel
                            title="Aktivitas Terbaru"
                            description="Pengajuan, persetujuan, penunjukan PIC, dokumen, dan penyelesaian."
                            className={cn(hasSideColumn && 'xl:col-span-2')}
                            // Beside the side column, the timeline takes
                            // that column's height and scrolls within it.
                            bodyClassName={cn(
                                hasSideColumn &&
                                    'xl:relative xl:min-h-80 xl:p-0',
                            )}
                        >
                            <div
                                className={cn(
                                    hasSideColumn &&
                                        'xl:absolute xl:inset-0 xl:overflow-y-auto xl:p-4',
                                )}
                            >
                                {render(analytics.activities, (activities) => (
                                    <ActivityTimeline activities={activities} />
                                ))}
                            </div>
                        </Panel>
                    )}

                    {hasSideColumn && (
                        <div className="flex min-w-0 flex-col gap-4">
                            {shows('upcoming') && (
                                <Panel
                                    title="Jadwal Pengadaan"
                                    description="Pengadaan berjalan dengan target penyelesaian terdekat."
                                >
                                    {render(analytics.upcoming, (rows) => (
                                        <UpcomingList rows={rows} />
                                    ))}
                                </Panel>
                            )}

                            {shows('statuses') && (
                                <Panel
                                    title="Rincian Status Progres"
                                    description="Jumlah pengadaan per status progres di Data Master."
                                >
                                    {render(analytics.statuses, (rows) => (
                                        <RankingBars
                                            rows={rows}
                                            color={VIZ.sky}
                                            limit={8}
                                        />
                                    ))}
                                </Panel>
                            )}
                        </div>
                    )}
                </div>
            )}
        </>
    );
}

/** How many of the three columns a chart panel takes. */
type Span = 1 | 2 | 3;

const SPAN_CLASS: Record<Span, string> = {
    1: '',
    2: 'xl:col-span-2',
    3: 'xl:col-span-3',
};

/**
 * Three columns of chart panels in which the last panel of every row
 * widens to fill it, so a role showing fewer charts is left with no holes.
 * Each panel gives its width with `span`.
 */
function FilledGrid({ children }: { children: ReactNode }) {
    const items = Children.toArray(children).filter(isValidElement) as Array<
        ReactElement<{ span?: Span; className?: string }>
    >;

    const spans = items.map((item) => item.props.span ?? 1);
    let used = 0;

    spans.forEach((span, index) => {
        if (used + span > 3) {
            // The row before is short: its last panel takes the rest.
            spans[index - 1] = (spans[index - 1] + 3 - used) as Span;
            used = 0;
        }

        used = (used + span) % 3;
    });

    if (used > 0 && spans.length > 0) {
        spans[spans.length - 1] = (spans[spans.length - 1] + 3 - used) as Span;
    }

    return (
        <div className="grid gap-4 xl:grid-cols-3">
            {items.map((item, index) =>
                cloneElement(item, {
                    className: cn(
                        item.props.className,
                        SPAN_CLASS[spans[index]],
                    ),
                }),
            )}
        </div>
    );
}

function SectionEmpty() {
    return (
        <p className="py-8 text-center text-sm text-muted-foreground">
            Belum ada pengadaan aktif sesuai filter.
        </p>
    );
}

/**
 * The jobs a role works on, listed the way that role needs them.
 */
function RoleJobsSection({
    profile,
    role,
    menus,
    canPropose,
    onResetFilter,
}: {
    profile: RoleProfile;
    role: RoleDashboardData;
    menus: Auth['permissions']['menus'];
    canPropose: boolean;
    onResetFilter?: () => void;
}) {
    // Where "Lihat semua" leads for each role's list.
    const allHref: Record<RoleProfile, string | null> = {
        manajemen: procurements.index().url,
        pengusul: menus.proposals ? proposals.index().url : null,
        direksi: menus.proposals ? proposals.index().url : null,
        perencana: menus.planning ? planning.index().url : null,
        pelaksana: menus.execution ? execution.index().url : null,
        keuangan: menus.finance ? finance.index().url : null,
        umum: procurements.index().url,
    };

    const activeFilterLabel =
        role.activeCard && role.activeCard !== 'total'
            ? (role.cards.find((card) => card.key === role.activeCard)?.label ??
              null)
            : null;

    return (
        <>
            <DivisionBudgetPlans
                plans={role.budgetPlans ?? []}
                canPropose={canPropose}
            />
            <RoleJobs
                title={role.jobsTitle}
                jobs={role.jobs}
                allHref={allHref[profile]}
                showDivision={profile !== 'pengusul' && profile !== 'direksi'}
                activeFilterLabel={activeFilterLabel}
                onResetFilter={onResetFilter}
            />
        </>
    );
}

/**
 * A planning returned for revision is work waiting on its PIC.
 */
function RevisionNotice({ count }: { count: number }) {
    return (
        <Link
            href={planning.index({ query: { approval_state: 'ditolak' } }).url}
            className="flex items-start gap-3 rounded-md border border-destructive/40 bg-destructive/5 p-4 transition-colors hover:bg-destructive/10"
        >
            <RotateCcw className="mt-0.5 size-4 shrink-0 text-destructive" />
            <div className="space-y-0.5">
                <p className="text-sm font-semibold text-destructive">
                    {count} perencanaan dikembalikan untuk revisi
                </p>
                <p className="text-xs text-muted-foreground">
                    Perbaiki sesuai catatan reviewer, lalu ajukan ulang untuk
                    ditinjau kembali.
                </p>
            </div>
        </Link>
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

    if (tasks.proposals !== null) {
        cards.push({
            key: 'proposals',
            title: 'Usulan Menunggu Persetujuan Anda',
            icon: Inbox,
            list: tasks.proposals,
            emptyText: 'Tidak ada usulan pekerjaan yang menunggu.',
            itemHref: (item) => proposals.show(item.id).url,
            dateLabel: (item) =>
                item.date ? `Diajukan ${formatDateTime(item.date)}` : null,
            allHref: menus.proposals ? proposals.index().url : null,
            tone: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        });
    }

    if (tasks.budgetPlans !== null && tasks.budgetPlans !== undefined) {
        cards.push({
            key: 'budgetPlans',
            title: 'PRK Perlu Ditindaklanjuti',
            icon: FilePlus2,
            list: tasks.budgetPlans,
            emptyText: 'Semua PRK bidang Anda sudah ditindaklanjuti.',
            itemHref: (item) =>
                procurements.create({ query: { prk: item.number } }).url,
            dateLabel: (item) =>
                item.date ? `Dibuat ${formatDate(item.date)}` : null,
            allHref: null,
            tone: 'bg-primary/10 text-primary',
        });
    }

    if (tasks.myProposals.total > 0) {
        cards.push({
            key: 'myProposals',
            title: 'Usulan Saya',
            icon: FilePlus2,
            list: tasks.myProposals,
            emptyText: '',
            itemHref: (item) => proposals.show(item.id).url,
            dateLabel: (item) =>
                item.date ? `Diajukan ${formatDateTime(item.date)}` : null,
            allHref: menus.proposals ? proposals.index().url : null,
            tone: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        });
    }

    if (tasks.finance !== null) {
        cards.push({
            key: 'finance',
            title: 'Tagihan Menunggu Pembayaran',
            icon: Landmark,
            list: tasks.finance,
            emptyText: 'Tidak ada tagihan yang menunggu pembayaran.',
            itemHref: (item) =>
                procurements.show(item.id, { query: { tab: 'pembayaran' } })
                    .url,
            dateLabel: (item) =>
                item.date
                    ? `Penyerahan disetujui ${formatDateTime(item.date)}`
                    : null,
            allHref: menus.finance ? finance.index().url : null,
            tone: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        });
    }

    if (tasks.approvals !== null) {
        cards.push({
            key: 'approvals',
            title: 'Perencanaan Menunggu Persetujuan Anda',
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

    if (tasks.executionApprovals !== null) {
        cards.push({
            key: 'executionApprovals',
            title: 'Pelaksanaan Menunggu Persetujuan Anda',
            icon: ClipboardCheck,
            list: tasks.executionApprovals,
            emptyText: 'Tidak ada pelaksanaan yang menunggu persetujuan.',
            itemHref: (item) =>
                procurements.show(item.id, { query: { tab: 'pelaksanaan' } })
                    .url,
            dateLabel: (item) =>
                item.date ? `Diajukan ${formatDateTime(item.date)}` : null,
            allHref: menus.approvals ? approvals.index().url : null,
            tone: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
        });
    }

    if (tasks.handoverApprovals !== null) {
        cards.push({
            key: 'handoverApprovals',
            title: 'Penyerahan Menunggu Persetujuan Anda',
            icon: ClipboardCheck,
            list: tasks.handoverApprovals,
            emptyText: 'Tidak ada penyerahan yang menunggu persetujuan.',
            itemHref: (item) =>
                procurements.show(item.id, { query: { tab: 'penyerahan' } })
                    .url,
            dateLabel: (item) =>
                item.date ? `Diajukan ${formatDateTime(item.date)}` : null,
            allHref: menus.approvals ? approvals.index().url : null,
            tone: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
        });
    }

    if (tasks.paymentApprovals !== null) {
        cards.push({
            key: 'paymentApprovals',
            title: 'Pembayaran Menunggu Persetujuan Anda',
            icon: ClipboardCheck,
            list: tasks.paymentApprovals,
            emptyText: 'Tidak ada pembayaran yang menunggu persetujuan.',
            itemHref: (item) =>
                procurements.show(item.id, { query: { tab: 'pembayaran' } })
                    .url,
            dateLabel: (item) =>
                item.date ? `Diajukan ${formatDateTime(item.date)}` : null,
            allHref: menus.approvals ? approvals.index().url : null,
            tone: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        });
    }

    if (tasks.amendmentApprovals !== null) {
        cards.push({
            key: 'amendmentApprovals',
            title: 'Amandemen Menunggu Persetujuan Anda',
            icon: ClipboardCheck,
            list: tasks.amendmentApprovals,
            emptyText: 'Tidak ada amandemen yang menunggu persetujuan.',
            itemHref: (item) =>
                procurements.show(item.id, { query: { tab: 'amandemen' } }).url,
            dateLabel: (item) =>
                item.date ? `Diajukan ${formatDateTime(item.date)}` : null,
            allHref: menus.approvals
                ? approvals.index({ query: { queue: 'amendment' } }).url
                : null,
            tone: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        });
    }

    if (tasks.deliveryOrderApprovals !== null) {
        cards.push({
            key: 'deliveryOrderApprovals',
            title: 'Delivery Order Menunggu Persetujuan Anda',
            icon: ClipboardCheck,
            list: tasks.deliveryOrderApprovals,
            emptyText: 'Tidak ada Delivery Order yang menunggu persetujuan.',
            itemHref: (item) =>
                procurements.show(item.id, { query: { tab: 'do' } }).url,
            dateLabel: (item) =>
                item.date ? `Diajukan ${formatDateTime(item.date)}` : null,
            allHref: menus.approvals
                ? approvals.index({ query: { queue: 'delivery-order' } }).url
                : null,
            tone: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
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
    const waiting = cards.filter((card) => card.list.total > 0);

    // One line under the headline figures: what waits on the user, each
    // chip leading to where it is done.
    return (
        <section
            aria-label="Perlu tindakan Anda"
            className="rounded-md border border-border bg-card"
        >
            <header className="flex items-center gap-3 border-b border-border px-4 py-3">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-md',
                        pending > 0
                            ? 'bg-primary/10 text-primary'
                            : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                    )}
                >
                    {pending > 0 ? (
                        <ListChecks className="size-5" />
                    ) : (
                        <PartyPopper className="size-5" />
                    )}
                </span>
                <div>
                    <h2 className="text-base font-semibold text-foreground">
                        {pending > 0
                            ? 'Perlu Tindakan Anda'
                            : 'Tidak ada tugas yang menunggu Anda'}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {pending > 0
                            ? `${pending} tugas menunggu. Klik untuk membuka.`
                            : 'Approval, penunjukan PIC, dan tugas pengadaan Anda akan muncul di sini.'}
                    </p>
                </div>
            </header>

            {waiting.length > 0 && (
                <ul className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                    {waiting.map((card) => {
                        const href =
                            card.allHref ??
                            (card.list.items[0] !== undefined
                                ? card.itemHref(card.list.items[0])
                                : null);
                        const tile = (
                            <>
                                <span
                                    className={cn(
                                        'flex size-10 shrink-0 items-center justify-center rounded-md',
                                        card.tone,
                                    )}
                                >
                                    <card.icon className="size-5" />
                                </span>
                                <span className="min-w-0 flex-1 text-sm leading-snug font-medium text-foreground">
                                    {card.title}
                                </span>
                                <span className="tabular shrink-0 text-2xl leading-none font-semibold text-foreground">
                                    {card.list.total}
                                </span>
                            </>
                        );
                        const className =
                            'flex h-full items-center gap-3 rounded-md border border-border bg-background px-3.5 py-3';

                        return (
                            <li key={card.key}>
                                {href !== null ? (
                                    <Link
                                        href={href}
                                        className={cn(
                                            className,
                                            'group transition-colors hover:border-primary/50 hover:bg-accent/40',
                                        )}
                                    >
                                        {tile}
                                        <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                                    </Link>
                                ) : (
                                    <span className={className}>{tile}</span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
