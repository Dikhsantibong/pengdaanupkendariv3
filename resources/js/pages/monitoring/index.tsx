import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    BadgeCheck,
    Briefcase,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Circle,
    ClipboardList,
    Clock,
    FilePlus2,
    Folder,
    GitCommitHorizontal,
    Landmark,
    Lock,
    MapPin,
    RotateCcw,
    Sparkles,
    Table2,
    User,
    Wallet,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { StageCount } from '@/components/dashboard/role-dashboard';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import {
    FolderBadge,
    OpenPartnerBar,
    PartnerFolderGrid,
} from '@/components/partner-folder-grid';
import type {
    OpenPartner,
    PartnerFolder,
} from '@/components/partner-folder-grid';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { ProcurementTable } from '@/components/procurement-table';
import type { StatusJourney } from '@/components/procurement/status-card';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    formatCompactCurrency,
    formatCurrency,
    formatDate,
    formatDateTime,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import monitoring from '@/routes/monitoring';
import type {
    FilterOptions,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
    StatusCategory,
} from '@/types';

type StatusBoardRow = {
    id: number;
    name: string;
    category: StatusCategory;
    stage?: string;
    total: number;
};

type TrackerChecklistStep = {
    id: number;
    name: string;
    is_completed: boolean;
    is_optional: boolean;
    completed_at: string | null;
};

type TrackerStage = {
    key: 'usulan' | 'perencanaan' | 'pelaksanaan' | 'penyerahan' | 'pembayaran';
    label: string;
    state: 'done' | 'current' | 'todo' | 'locked';
    summary: string;
    pic: string | null;
    pic_role: string;
    completed: number;
    total: number;
    percentage: number;
    steps: TrackerChecklistStep[];
    url: string;
};

type TrackerProcurement = {
    id: number;
    number: string;
    has_contract_number: boolean;
    name: string;
    format: string | null;
    work_director: string;
    target_unit: string;
    partner_name: string | null;
    hpe_value: number;
    value_after_negotiation: number | null;
    target_completion_date: string | null;
    created_at: string | null;
    updated_at: string | null;
    url: string;
    status: {
        id: number;
        name: string;
        category: StatusCategory;
        rule: string | null;
    };
    current_stage: {
        key: string;
        label: string;
    };
    waiting_note: string;
    overall_percentage: number;
    pic: {
        proposer: string | null;
        planner: string | null;
        executor: string | null;
    };
    journey: StatusJourney;
    stages: TrackerStage[];
};

const categoryBar: Record<StatusCategory, string> = {
    pending: 'bg-status-pending',
    batal: 'bg-status-batal',
    berjalan: 'bg-status-berjalan',
    selesai: 'bg-status-selesai',
};

const STAGE_ICONS: Record<string, LucideIcon> = {
    usulan: FilePlus2,
    perencanaan: ClipboardList,
    pelaksanaan: Wrench,
    keuangan: Landmark,
    selesai: BadgeCheck,
};

/** The filters as a query, the empty ones left out. */
function queryOf(values: Record<string, string | number | null | undefined>) {
    return Object.fromEntries(
        Object.entries(values).filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        ),
    ) as Record<string, string | number>;
}

export default function MonitoringIndex({
    folders,
    procurements: page,
    tracker,
    stages = [],
    activeStage = null,
    partner,
    filters,
    options,
    statusBoard,
}: {
    /** One folder per partner, while none is open. */
    folders: Paginated<PartnerFolder> | null;
    /** The open partner's work. */
    procurements: Paginated<ProcurementRow> | null;
    /** Visual stage and step tracker for each matching procurement. */
    tracker?: Paginated<TrackerProcurement>;
    /** Counts of procurements across the 5 lifecycle stages. */
    stages?: StageCount[];
    /** Currently filtered lifecycle stage key, if any. */
    activeStage?: string | null;
    partner: OpenPartner | null;
    filters: ProcurementFilters;
    options: FilterOptions;
    statusBoard: StatusBoardRow[];
}) {
    const [viewMode, setViewMode] = useState<'visual' | 'folders' | 'table'>(
        'visual',
    );
    const [showStatusChips, setShowStatusChips] = useState(false);

    const { search, ...narrowing } = filters;
    const persist: Record<string, string> = {
        ...(partner ? { mitra: partner.key } : {}),
        ...(activeStage ? { stage: activeStage } : {}),
    };

    const totalStageCount = stages.reduce((sum, s) => sum + s.total, 0);
    const trackerTotal =
        tracker?.meta?.total ?? tracker?.total ?? tracker?.data.length ?? 0;
    const foldersTotal =
        folders?.meta?.total ?? folders?.total ?? folders?.data.length ?? 0;
    const selectedStatus = statusBoard.find(
        (row) => row.id === filters.progress_status_id,
    );

    // Filter status chips to the selected macro stage when one is active.
    const stageStatuses = activeStage
        ? statusBoard.filter((row) => row.stage === activeStage)
        : statusBoard;
    const isSubBarOpen =
        showStatusChips || activeStage !== null || selectedStatus !== undefined;

    return (
        <>
            <Head title="Monitoring" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Monitoring"
                    title="Monitoring Progress Pengadaan"
                    description="Visualisasi tahapan pengadaan secara detail dari Usulan, Perencanaan, Pelaksanaan, hingga Penyerahan & Pembayaran, beserta pengelompokan per mitra."
                />

                {/* 1. Bar Pipeline Ringkas (Tahapan Siklus & Chip Status Detail dalam 1 Bar Hemat Ruang) */}
                <section className="overflow-hidden rounded-md border border-border bg-card">
                    {/* Stacked Progress Bar Proporsi Tahapan */}
                    {totalStageCount > 0 && (
                        <div
                            className="flex h-1.5 w-full overflow-hidden bg-muted"
                            aria-hidden="true"
                        >
                            {stages.map((stage) => {
                                const width =
                                    (stage.total / totalStageCount) * 100;
                                if (width <= 0) {
                                    return null;
                                }
                                return (
                                    <div
                                        key={stage.key}
                                        style={{ width: `${width}%` }}
                                        title={`${stage.label}: ${stage.total} (${Math.round(width)}%)`}
                                        className={cn(
                                            'h-full border-r border-card/40 transition-all last:border-r-0',
                                            stage.key === 'usulan' &&
                                                'bg-amber-500',
                                            stage.key === 'perencanaan' &&
                                                'bg-sky-500',
                                            stage.key === 'pelaksanaan' &&
                                                'bg-primary',
                                            stage.key === 'keuangan' &&
                                                'bg-violet-500',
                                            stage.key === 'selesai' &&
                                                'bg-emerald-600',
                                        )}
                                    />
                                );
                            })}
                        </div>
                    )}

                    {/* Bar Utama: 5 Tahap Siklus dalam 1 Baris Horizontal */}
                    <div className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                        <div className="flex flex-wrap items-center gap-1">
                            <Link
                                href={
                                    monitoring.index({
                                        query: queryOf({
                                            ...filters,
                                            ...(partner
                                                ? { mitra: partner.key }
                                                : {}),
                                            stage: null,
                                            progress_status_id: null,
                                        }),
                                    }).url
                                }
                                className={cn(
                                    'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors',
                                    activeStage === null &&
                                        filters.progress_status_id === null
                                        ? 'bg-primary text-primary-foreground shadow-2xs'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                <span>Semua Tahap</span>
                                <span
                                    className={cn(
                                        'tabular rounded-full px-1.5 py-0.2 text-[11px] font-semibold',
                                        activeStage === null &&
                                            filters.progress_status_id === null
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-muted text-foreground',
                                    )}
                                >
                                    {totalStageCount}
                                </span>
                            </Link>

                            {stages.map((stage, index) => {
                                const Icon =
                                    STAGE_ICONS[stage.key] ?? ClipboardList;
                                const isSelected = activeStage === stage.key;

                                return (
                                    <div
                                        key={stage.key}
                                        className="inline-flex items-center gap-1"
                                    >
                                        <ChevronRight
                                            className=" hidden size-3.5 text-muted-foreground/50 sm:block"
                                            aria-hidden="true"
                                        />
                                        <Link
                                            href={
                                                monitoring.index({
                                                    query: queryOf({
                                                        ...filters,
                                                        ...(partner
                                                            ? {
                                                                  mitra: partner.key,
                                                              }
                                                            : {}),
                                                        stage: isSelected
                                                            ? null
                                                            : stage.key,
                                                        progress_status_id:
                                                            null,
                                                    }),
                                                }).url
                                            }
                                            className={cn(
                                                'inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium transition-colors',
                                                isSelected
                                                    ? 'border-primary bg-primary/10 font-semibold text-primary'
                                                    : 'border-transparent text-foreground hover:border-border hover:bg-muted/60',
                                            )}
                                        >
                                            <Icon
                                                className={cn(
                                                    'size-3.5 shrink-0',
                                                    isSelected
                                                        ? 'text-primary'
                                                        : 'text-muted-foreground',
                                                )}
                                            />
                                            <span>
                                                {index + 1}. {stage.label}
                                            </span>
                                            <span
                                                className={cn(
                                                    'tabular rounded-full px-1.5 py-0.2 text-[11px] font-semibold',
                                                    isSelected
                                                        ? 'bg-primary text-primary-foreground'
                                                        : stage.total > 0
                                                          ? 'bg-primary/10 text-primary'
                                                          : 'bg-muted text-muted-foreground',
                                                )}
                                            >
                                                {stage.total}
                                            </span>
                                        </Link>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Tombol Buka/Tutup Chip Status Progres Detail */}
                        <div className="flex items-center gap-2">
                            {selectedStatus && (
                                <Link
                                    href={
                                        monitoring.index({
                                            query: queryOf({
                                                ...filters,
                                                ...persist,
                                                progress_status_id: null,
                                            }),
                                        }).url
                                    }
                                    className="inline-flex h-7 items-center gap-1 rounded-full bg-primary/10 px-2.5 text-[11px] font-medium text-primary hover:bg-primary/15"
                                >
                                    <span>{selectedStatus.name}</span>
                                    <span className="font-bold">×</span>
                                </Link>
                            )}
                            <button
                                type="button"
                                onClick={() =>
                                    setShowStatusChips(!showStatusChips)
                                }
                                className="inline-flex h-7 items-center gap-1 rounded-md border border-border bg-muted/40 px-2.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                <span>
                                    {isSubBarOpen && showStatusChips
                                        ? 'Sembunyikan Status'
                                        : `Rincian Status (${stageStatuses.length})`}
                                </span>
                                <ChevronDown
                                    className={cn(
                                        'size-3.5 transition-transform',
                                        isSubBarOpen && 'rotate-180',
                                    )}
                                />
                            </button>
                        </div>
                    </div>

                    {/* Sub-bar Chip Status Progres (Hanya tampil sebagai pill satu baris yang hemat tempat) */}
                    {isSubBarOpen && (
                        <div className="flex flex-wrap items-center gap-1.5 border-t border-border bg-muted/20 px-3 py-2">
                            <span className="mr-1 text-[11px] font-medium text-muted-foreground">
                                {activeStage
                                    ? `Status pada tahap ${stages.find((s) => s.key === activeStage)?.label ?? activeStage}:`
                                    : 'Filter Status:'}
                            </span>
                            {stageStatuses.map((row) => {
                                const isSelected =
                                    filters.progress_status_id === row.id;

                                return (
                                    <Link
                                        key={row.id}
                                        href={monitoring.index({
                                            query: queryOf({
                                                ...filters,
                                                ...persist,
                                                progress_status_id: isSelected
                                                    ? null
                                                    : row.id,
                                            }),
                                        })}
                                        className={cn(
                                            'inline-flex h-6 items-center gap-1.5 rounded-full border px-2.5 text-[11px] transition-colors',
                                            isSelected
                                                ? 'border-primary bg-primary font-semibold text-primary-foreground shadow-2xs'
                                                : row.total > 0
                                                  ? 'border-border bg-card font-medium text-foreground hover:border-primary/40 hover:bg-accent/50'
                                                  : 'border-border/60 bg-card/50 text-muted-foreground hover:bg-card hover:text-foreground',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'size-1.5 shrink-0 rounded-full',
                                                isSelected
                                                    ? 'bg-primary-foreground'
                                                    : categoryBar[row.category],
                                            )}
                                        />
                                        <span>{row.name}</span>
                                        <span
                                            className={cn(
                                                'tabular font-semibold',
                                                isSelected
                                                    ? 'text-primary-foreground'
                                                    : row.total > 0
                                                      ? 'text-primary'
                                                      : 'text-muted-foreground',
                                            )}
                                        >
                                            {row.total}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </section>

                {/* 3. Bar Filter Pencarian & Atribut */}
                <ProcurementFilterBar
                    url={monitoring.index().url}
                    filters={filters}
                    options={options}
                    persist={persist}
                />

                {/* 4. Bar Mitra Terbuka (jika sedang melihat satu folder mitra) */}
                {partner !== null && (
                    <OpenPartnerBar
                        partner={partner}
                        backHref={
                            monitoring.index({
                                query: queryOf({
                                    search,
                                    ...narrowing,
                                    ...(activeStage
                                        ? { stage: activeStage }
                                        : {}),
                                }),
                            }).url
                        }
                    >
                        {trackerTotal ||
                            (page?.meta?.total ??
                                page?.total ??
                                page?.data.length ??
                                0)}{' '}
                        pekerjaan
                    </OpenPartnerBar>
                )}

                {/* 5. Pemilih Mode Tampilan (Visualisasi Detail vs Folder Mitra / Tabel) */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-card px-3.5 py-2.5">
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span className="font-semibold text-foreground">
                            {viewMode === 'folders' && partner === null
                                ? `${foldersTotal} Folder Mitra`
                                : `${trackerTotal} Pengadaan`}
                        </span>
                        {activeStage && (
                            <span className="rounded bg-primary/10 px-2 py-0.5 font-medium text-primary">
                                Tahap:{' '}
                                {stages.find((s) => s.key === activeStage)
                                    ?.label ?? activeStage}
                            </span>
                        )}
                        {selectedStatus && (
                            <span className="rounded bg-primary/10 px-2 py-0.5 font-medium text-primary">
                                Status: {selectedStatus.name}
                            </span>
                        )}
                    </div>

                    <div
                        role="tablist"
                        aria-label="Mode tampilan monitoring"
                        className="inline-flex items-center rounded-md border border-border bg-muted/50 p-0.5"
                    >
                        <button
                            type="button"
                            role="tab"
                            aria-selected={viewMode === 'visual'}
                            onClick={() => setViewMode('visual')}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-sm px-3 py-1.5 text-xs font-medium transition-colors',
                                viewMode === 'visual'
                                    ? 'bg-card text-primary shadow-2xs ring-1 ring-border'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <GitCommitHorizontal className="size-3.5" />
                            Visualisasi Tahapan
                            {tracker !== undefined && (
                                <span className="tabular rounded-full bg-primary/10 px-1.5 py-0.2 text-[10px] font-semibold text-primary">
                                    {trackerTotal}
                                </span>
                            )}
                        </button>

                        {partner === null && folders !== null && (
                            <button
                                type="button"
                                role="tab"
                                aria-selected={viewMode === 'folders'}
                                onClick={() => setViewMode('folders')}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-sm px-3 py-1.5 text-xs font-medium transition-colors',
                                    viewMode === 'folders'
                                        ? 'bg-card text-primary shadow-2xs ring-1 ring-border'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Folder className="size-3.5" />
                                Folder per Mitra
                                <span className="tabular rounded-full bg-muted px-1.5 py-0.2 text-[10px] font-semibold text-foreground">
                                    {foldersTotal}
                                </span>
                            </button>
                        )}

                        {partner !== null && page !== null && (
                            <button
                                type="button"
                                role="tab"
                                aria-selected={viewMode === 'table'}
                                onClick={() => setViewMode('table')}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-sm px-3 py-1.5 text-xs font-medium transition-colors',
                                    viewMode === 'table'
                                        ? 'bg-card text-primary shadow-2xs ring-1 ring-border'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Table2 className="size-3.5" />
                                Tabel Ringkas
                            </button>
                        )}
                    </div>
                </div>

                {/* 6A. Tampilan Visualisasi Tahapan Pengadaan (Default) */}
                {viewMode === 'visual' && tracker !== undefined && (
                    <VisualTrackerList
                        tracker={tracker}
                        filters={filters}
                        activeStage={activeStage}
                    />
                )}

                {/* 6B. Tampilan Tabel Ringkas saat di dalam Folder Mitra */}
                {partner !== null && page !== null && viewMode === 'table' && (
                    <ProcurementTable
                        page={page}
                        columns={[
                            'unit',
                            'status',
                            'approval',
                            'planningProgress',
                            'executionProgress',
                            'target',
                        ]}
                        emptyTitle="Tidak ada pekerjaan mitra ini yang cocok"
                    />
                )}

                {/* 6C. Tampilan Folder per Mitra */}
                {partner === null &&
                    folders !== null &&
                    (viewMode === 'folders' || tracker === undefined) && (
                        <PartnerFolderGrid
                            page={folders}
                            emptyTitle="Tidak ada pengadaan untuk dipantau"
                            href={(folder) =>
                                monitoring.index({
                                    query: queryOf({
                                        ...filters,
                                        ...(activeStage
                                            ? { stage: activeStage }
                                            : {}),
                                        mitra: folder.key,
                                    }),
                                }).url
                            }
                            details={(folder) => (
                                <>
                                    <span className="tabular block text-[11px] text-muted-foreground">
                                        {folder.total} pekerjaan
                                    </span>
                                    <FolderBadge
                                        done={folder.done}
                                        total={folder.total}
                                        label="selesai"
                                    />
                                </>
                            )}
                        />
                    )}
            </div>
        </>
    );
}

/**
 * Daftar kartu visualisasi tahapan setiap pengadaan lengkap dengan paginasi.
 */
function VisualTrackerList({
    tracker,
    filters,
    activeStage,
}: {
    tracker: Paginated<TrackerProcurement>;
    filters: ProcurementFilters;
    activeStage: string | null;
}) {
    if (tracker.data.length === 0) {
        return (
            <div className="rounded-md border border-border bg-card">
                <EmptyState
                    icon={GitCommitHorizontal}
                    title="Tidak ada pengadaan yang cocok dengan filter"
                    description="Ubah kata kunci pencarian, status progres, atau tahap untuk melihat visualisasi pengadaan lainnya."
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-3.5">
            {tracker.data.map((item) => (
                <ProcurementVisualCard
                    key={item.id}
                    item={item}
                    filters={filters}
                    activeStage={activeStage}
                />
            ))}

            <div className="mt-auto overflow-hidden rounded-md border border-border bg-card">
                <DataPagination page={tracker} />
            </div>
        </div>
    );
}

/**
 * Kartu visualisasi satu pengadaan: menampilkan identitas, alur 5 tahapan utama,
 * posisi status pada perjalanan menuju kontrak, serta rincian checklist tiap tahap.
 */
function ProcurementVisualCard({
    item,
    filters,
    activeStage,
}: {
    item: TrackerProcurement;
    filters: ProcurementFilters;
    activeStage: string | null;
}) {
    const defaultStageKey =
        item.stages.find((s) => s.state === 'current')?.key ??
        (item.current_stage.key === 'keuangan'
            ? 'penyerahan'
            : item.current_stage.key === 'selesai'
              ? 'pembayaran'
              : (item.stages[0]?.key ?? 'perencanaan'));

    const [expanded, setExpanded] = useState(false);
    const [selectedStageKey, setSelectedStageKey] = useState<string>(
        defaultStageKey,
    );

    const selectedStage =
        item.stages.find((s) => s.key === selectedStageKey) ?? item.stages[0];

    const effectiveValue = item.value_after_negotiation ?? item.hpe_value;
    const valueLabel =
        item.value_after_negotiation !== null ? 'Nilai Kontrak' : 'Nilai HPE';

    const handleStageClick = (stageKey: string) => {
        if (expanded && selectedStageKey === stageKey) {
            setExpanded(false);
        } else {
            setSelectedStageKey(stageKey);
            setExpanded(true);
        }
    };

    return (
        <article className="overflow-hidden rounded-md border border-border bg-card transition-shadow hover:shadow-xs">
            {/* Header Identitas & Status Saat Ini */}
            <div className="flex flex-col justify-between gap-3 border-b border-border p-4 lg:flex-row lg:items-start">
                <div className="min-w-0 flex-1 space-y-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                        {item.format && (
                            <span className="rounded bg-primary/10 px-2 py-0.5 text-[11px] font-semibold tracking-wide text-primary uppercase">
                                {item.format}
                            </span>
                        )}
                        <span className="tabular rounded border border-border bg-muted/50 px-2 py-0.5 text-xs font-medium text-foreground">
                            {item.number}
                        </span>
                        <span className="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-0.5 text-[11px] font-semibold text-secondary-foreground">
                            Tahap Aktif: {item.current_stage.label}
                        </span>
                    </div>

                    <div className="flex items-start gap-2">
                        <Link
                            href={item.url}
                            className="group inline-flex items-baseline gap-1.5 text-base font-semibold text-foreground hover:text-primary"
                        >
                            <span>{item.name}</span>
                            <ArrowUpRight className="size-4 shrink-0 translate-y-0.5 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary" />
                        </Link>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5">
                            <Building2 className="size-3.5 shrink-0 text-muted-foreground/80" />
                            <span>{item.work_director}</span>
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <MapPin className="size-3.5 shrink-0 text-muted-foreground/80" />
                            <span>{item.target_unit}</span>
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <Briefcase className="size-3.5 shrink-0 text-muted-foreground/80" />
                            {item.partner_name ? (
                                <Link
                                    href={
                                        monitoring.index({
                                            query: queryOf({
                                                ...filters,
                                                ...(activeStage
                                                    ? { stage: activeStage }
                                                    : {}),
                                                mitra: item.partner_name,
                                            }),
                                        }).url
                                    }
                                    className="font-medium text-foreground hover:text-primary hover:underline"
                                >
                                    {item.partner_name}
                                </Link>
                            ) : (
                                <span className="italic">Belum ada mitra</span>
                            )}
                        </span>
                        <span
                            className="tabular inline-flex items-center gap-1.5 font-medium text-foreground"
                            title={formatCurrency(effectiveValue)}
                        >
                            <Wallet className="size-3.5 shrink-0 text-muted-foreground/80" />
                            <span>
                                {valueLabel}:{' '}
                                {formatCompactCurrency(effectiveValue)}
                            </span>
                        </span>
                        {item.target_completion_date && (
                            <span className="tabular inline-flex items-center gap-1.5">
                                <Calendar className="size-3.5 shrink-0 text-muted-foreground/80" />
                                <span>
                                    Target:{' '}
                                    {formatDate(item.target_completion_date)}
                                </span>
                            </span>
                        )}
                    </div>
                </div>

                {/* Kolom Kanan: Status Progres & Bar Persentase Keseluruhan */}
                <div className="flex shrink-0 flex-col justify-between gap-2 sm:flex-row sm:items-center lg:w-64 lg:flex-col lg:items-end">
                    <StatusBadge
                        label={item.status.name}
                        category={item.status.category}
                        className="w-fit text-xs"
                    />

                    <div className="w-full space-y-1 sm:w-48 lg:w-full">
                        <div className="flex items-center justify-between text-[11px]">
                            <span className="text-muted-foreground">
                                {item.journey.position !== null
                                    ? `Status ${item.journey.position} dari ${item.journey.steps.length}`
                                    : 'Progres Tahapan'}
                            </span>
                            <span className="tabular font-semibold text-primary">
                                {item.overall_percentage}%
                            </span>
                        </div>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn(
                                    'h-full rounded-full transition-all',
                                    item.status.category === 'selesai'
                                        ? 'bg-status-selesai'
                                        : item.status.category === 'batal'
                                          ? 'bg-status-batal'
                                          : 'bg-primary',
                                )}
                                style={{
                                    width: `${item.overall_percentage}%`,
                                }}
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Visualisasi Horizontal 5 Tahapan Siklus Pengadaan */}
            <div className="bg-muted/15 px-4 py-3">
                <ol
                    aria-label="Tahapan pengadaan"
                    className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5"
                >
                    {item.stages.map((stage, index) => {
                        const isFocused =
                            expanded && selectedStageKey === stage.key;

                        return (
                            <li key={stage.key} className="relative flex">
                                <button
                                    type="button"
                                    onClick={() => handleStageClick(stage.key)}
                                    className={cn(
                                        'group flex w-full flex-col justify-between gap-2 rounded-md border p-2.5 text-left transition-all outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                        stage.state === 'done' &&
                                            'border-emerald-500/30 bg-emerald-500/[0.04] hover:border-emerald-500/50',
                                        stage.state === 'current' &&
                                            'border-primary bg-primary/[0.06] shadow-2xs ring-1 ring-primary/25',
                                        (stage.state === 'todo' ||
                                            stage.state === 'locked') &&
                                            'border-border bg-card/70 opacity-80 hover:opacity-100',
                                        isFocused &&
                                            'ring-2 ring-primary ring-offset-1',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={cn(
                                                    'tabular flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                                                    stage.state === 'done' &&
                                                        'bg-emerald-600 text-white',
                                                    stage.state === 'current' &&
                                                        'bg-primary text-primary-foreground',
                                                    (stage.state === 'todo' ||
                                                        stage.state ===
                                                            'locked') &&
                                                        'bg-muted text-muted-foreground',
                                                )}
                                            >
                                                {stage.state === 'done' ? (
                                                    <Check className="size-3.5" />
                                                ) : stage.state === 'locked' ? (
                                                    <Lock className="size-3" />
                                                ) : (
                                                    index + 1
                                                )}
                                            </span>
                                            <div className="min-w-0">
                                                <p
                                                    className={cn(
                                                        'text-xs leading-tight font-semibold',
                                                        stage.state ===
                                                            'current'
                                                            ? 'text-primary'
                                                            : stage.state ===
                                                                'done'
                                                              ? 'text-foreground'
                                                              : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {stage.label}
                                                </p>
                                                <p className="tabular truncate text-[11px] text-muted-foreground">
                                                    {stage.summary}
                                                </p>
                                            </div>
                                        </div>

                                        <StageStatePill state={stage.state} />
                                    </div>

                                    <div className="space-y-1.5 pt-1">
                                        <div className="h-1 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                className={cn(
                                                    'h-full transition-all',
                                                    stage.state === 'done'
                                                        ? 'bg-emerald-600'
                                                        : stage.state ===
                                                            'current'
                                                          ? 'bg-primary'
                                                          : 'bg-muted-foreground/30',
                                                )}
                                                style={{
                                                    width: `${stage.percentage}%`,
                                                }}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between gap-1 text-[10px] text-muted-foreground">
                                            <span className="inline-flex min-w-0 items-center gap-1 truncate">
                                                <User className="size-2.5 shrink-0" />
                                                <span className="truncate">
                                                    {stage.pic ?? 'Belum ada PIC'}
                                                </span>
                                            </span>
                                            {stage.steps.length > 0 && (
                                                <span className="tabular shrink-0 font-medium text-foreground">
                                                    {stage.completed}/
                                                    {stage.total}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </button>
                            </li>
                        );
                    })}
                </ol>

                {/* Alur Status Sistem Ringkas (Step-by-Step Status Pills) */}
                {item.journey.steps.length > 0 && (
                    <div className="mt-2.5 flex flex-wrap items-center gap-1 pt-1">
                        <span className="mr-1 text-[11px] font-medium text-muted-foreground">
                            Jejak Status:
                        </span>
                        {item.journey.steps.map((step, index) => (
                            <span
                                key={step.key}
                                className="inline-flex items-center gap-1"
                            >
                                {index > 0 && (
                                    <ChevronRight className="size-3 text-muted-foreground/50" />
                                )}
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px]',
                                        step.state === 'done' &&
                                            'bg-emerald-500/10 font-medium text-emerald-700',
                                        step.state === 'current' &&
                                            'bg-primary font-semibold text-primary-foreground shadow-2xs',
                                        step.state === 'upcoming' &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {step.state === 'done' && (
                                        <Check className="size-2.5 shrink-0" />
                                    )}
                                    <span>{step.name}</span>
                                </span>
                            </span>
                        ))}
                    </div>
                )}
            </div>

            {/* Footer Bar: Keterangan Posisi Saat Ini & Tombol Buka Detail */}
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border bg-card px-4 py-2.5">
                <div className="flex min-w-0 items-center gap-2 text-xs">
                    {item.journey.revision ? (
                        <span className="inline-flex items-center gap-1.5 font-medium text-destructive">
                            <RotateCcw className="size-3.5 shrink-0" />
                            <span>
                                Revisi: {item.waiting_note}
                            </span>
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                            <Sparkles className="size-3.5 shrink-0 text-primary" />
                            <span className="font-medium text-foreground">
                                Posisi saat ini:
                            </span>
                            <span>{item.waiting_note}</span>
                            {item.status.rule && (
                                <span className="hidden text-muted-foreground md:inline">
                                    ({item.status.rule})
                                </span>
                            )}
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setExpanded(!expanded)}
                        className="h-7 gap-1.5 px-2.5 text-xs"
                    >
                        <span>
                            {expanded
                                ? 'Tutup Rincian Tahapan'
                                : 'Lihat Rincian Checklist & Tahapan'}
                        </span>
                        <ChevronDown
                            className={cn(
                                'size-3.5 transition-transform',
                                expanded && 'rotate-180',
                            )}
                        />
                    </Button>

                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-7 gap-1 px-2.5 text-xs"
                    >
                        <Link href={item.url}>
                            <span>Buka Pengadaan</span>
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Panel Rincian Checklist per Tahap & Riwayat Alur Status */}
            {expanded && (
                <div className="grid gap-4 border-t border-border bg-muted/10 p-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    {/* Kolom Kiri: Rincian Checklist pada Tahap yang Dipilih */}
                    <div className="space-y-3 rounded-md border border-border bg-card p-3.5">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2.5">
                            <div
                                role="tablist"
                                aria-label="Pilih tahap checklist"
                                className="flex flex-wrap gap-1"
                            >
                                {item.stages.map((stage) => (
                                    <button
                                        key={stage.key}
                                        type="button"
                                        role="tab"
                                        aria-selected={
                                            selectedStage.key === stage.key
                                        }
                                        onClick={() =>
                                            setSelectedStageKey(stage.key)
                                        }
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-sm px-2.5 py-1 text-xs font-medium transition-colors',
                                            selectedStage.key === stage.key
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                                        )}
                                    >
                                        <span>{stage.label}</span>
                                        {stage.steps.length > 0 && (
                                            <span
                                                className={cn(
                                                    'tabular rounded-full px-1.5 text-[10px]',
                                                    selectedStage.key ===
                                                        stage.key
                                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                                        : 'bg-card text-foreground',
                                                )}
                                            >
                                                {stage.completed}/{stage.total}
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>

                            <Link
                                href={selectedStage.url}
                                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                            >
                                <span>
                                    Buka Tahap {selectedStage.label}
                                </span>
                                <ArrowUpRight className="size-3.5" />
                            </Link>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                            <span>
                                Status Tahap:{' '}
                                <strong className="text-foreground">
                                    {selectedStage.summary}
                                </strong>
                            </span>
                            <span>
                                {selectedStage.pic_role}:{' '}
                                <strong className="text-foreground">
                                    {selectedStage.pic ?? 'Belum ditentukan'}
                                </strong>
                            </span>
                        </div>

                        {selectedStage.steps.length === 0 ? (
                            <div className="rounded border border-dashed border-border px-3 py-4 text-center text-xs text-muted-foreground">
                                {selectedStage.key === 'usulan'
                                    ? `Tahap Usulan (${selectedStage.summary}). Klik "Buka Tahap Usulan" untuk melihat dokumen TOR dan Nota Dinas Usulan.`
                                    : 'Belum ada item checklist pada tahap ini.'}
                            </div>
                        ) : (
                            <ul className="divide-y divide-border rounded-md border border-border">
                                {selectedStage.steps.map((step, idx) => (
                                    <li
                                        key={step.id}
                                        className={cn(
                                            'flex items-center justify-between gap-3 px-3 py-2 text-xs',
                                            step.is_completed
                                                ? 'bg-emerald-500/[0.03]'
                                                : 'bg-card',
                                        )}
                                    >
                                        <div className="flex min-w-0 items-center gap-2.5">
                                            {step.is_completed ? (
                                                <CheckCircle2 className="size-4 shrink-0 text-emerald-600" />
                                            ) : (
                                                <Circle className="size-4 shrink-0 text-muted-foreground/50" />
                                            )}
                                            <span
                                                className={cn(
                                                    'tabular text-muted-foreground',
                                                )}
                                            >
                                                {idx + 1}.
                                            </span>
                                            <span
                                                className={cn(
                                                    'truncate',
                                                    step.is_completed
                                                        ? 'font-medium text-foreground'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {step.name}
                                            </span>
                                            {step.is_optional && (
                                                <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                                    Opsional
                                                </span>
                                            )}
                                        </div>

                                        <span
                                            className={cn(
                                                'tabular shrink-0 text-[11px]',
                                                step.is_completed
                                                    ? 'font-medium text-emerald-700'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {step.is_completed
                                                ? step.completed_at
                                                    ? `Selesai ${formatDate(step.completed_at)}`
                                                    : 'Selesai'
                                                : 'Belum selesai'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {/* Kolom Kanan: Timeline Urutan Status Sistem (Journey menuju Terkontrak & Selesai) */}
                    <div className="space-y-3 rounded-md border border-border bg-card p-3.5">
                        <div className="flex items-center justify-between border-b border-border pb-2.5">
                            <h3 className="text-xs font-semibold tracking-wider text-foreground uppercase">
                                Linimasa Status Progres
                            </h3>
                            {item.journey.remaining !== null &&
                                item.journey.remaining > 0 && (
                                    <span className="tabular rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">
                                        {item.journey.remaining} status lagi ke
                                        Terkontrak
                                    </span>
                                )}
                        </div>

                        <ol className="space-y-1.5">
                            {item.journey.steps.map((step) => (
                                <li
                                    key={step.key}
                                    className={cn(
                                        'flex items-start justify-between gap-2 rounded-sm px-2 py-1.5 text-xs',
                                        step.state === 'current' &&
                                            'bg-primary/10 ring-1 ring-primary/30',
                                    )}
                                >
                                    <div className="flex min-w-0 items-center gap-2">
                                        <span
                                            className={cn(
                                                'flex size-4 shrink-0 items-center justify-center rounded-full border',
                                                step.state === 'done' &&
                                                    'border-emerald-600 bg-emerald-600 text-white',
                                                step.state === 'current' &&
                                                    'border-primary bg-primary text-primary-foreground',
                                                step.state === 'upcoming' &&
                                                    'border-border bg-muted/40',
                                            )}
                                        >
                                            {step.state === 'done' && (
                                                <Check className="size-2.5" />
                                            )}
                                            {step.state === 'current' && (
                                                <Clock className="size-2.5" />
                                            )}
                                        </span>
                                        <span
                                            className={cn(
                                                'truncate',
                                                step.state === 'upcoming'
                                                    ? 'text-muted-foreground'
                                                    : 'font-medium text-foreground',
                                                step.state === 'current' &&
                                                    'font-semibold text-primary',
                                            )}
                                        >
                                            {step.name}
                                        </span>
                                    </div>

                                    <span className="tabular shrink-0 text-[11px] text-muted-foreground">
                                        {step.state === 'current' ? (
                                            <span className="rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground uppercase">
                                                Sekarang
                                            </span>
                                        ) : step.at ? (
                                            formatDateTime(step.at)
                                        ) : step.state === 'done' ? (
                                            'Selesai'
                                        ) : (
                                            'Menunggu'
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </div>
                </div>
            )}
        </article>
    );
}

function StageStatePill({
    state,
}: {
    state: 'done' | 'current' | 'todo' | 'locked';
}) {
    if (state === 'done') {
        return (
            <span className="shrink-0 rounded bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                Selesai
            </span>
        );
    }

    if (state === 'current') {
        return (
            <span className="shrink-0 rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                Berjalan
            </span>
        );
    }

    return (
        <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
            Menunggu
        </span>
    );
}

MonitoringIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitoring', href: monitoring.index() },
    ],
};
