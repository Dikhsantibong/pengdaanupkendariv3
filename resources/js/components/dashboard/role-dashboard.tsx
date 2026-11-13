import { Link } from '@inertiajs/react';
import {
    BadgeCheck,
    CircleDashed,
    ClipboardList,
    FileCheck2,
    FilePlus2,
    FolderKanban,
    Hourglass,
    Landmark,
    RotateCcw,
    Send,
    Users,
    Wallet,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import type { StatusCategory } from '@/types';

export type RoleProfile =
    | 'manajemen'
    | 'pengusul'
    | 'direksi'
    | 'perencana'
    | 'pelaksana'
    | 'keuangan'
    | 'umum';

export type FigureCard = {
    key: string;
    label: string;
    value: number;
    format: 'number' | 'currency';
    hint: string | null;
    tone: string;
};

export type StageCount = { key: string; label: string; total: number };

export type RoleJob = {
    id: number;
    number: string;
    name: string;
    division: string | null;
    stage: { key: string; label: string };
    note: string;
    status: { name: string | null; category: StatusCategory | null };
    target_date: string | null;
    hpe_value: number;
};

export type BudgetPlanTaskRow = {
    id: number;
    prk_induk: string;
    budget_type: string;
    budget_type_label: string;
    description: string;
    division: string | null;
    planned_value: number;
    wo_number: string | null;
    pr_number: string | null;
};

export type RoleDashboardData = {
    cards: FigureCard[];
    stages: StageCount[];
    jobs: RoleJob[];
    jobsTitle: string;
    activeCard?: string | null;
    budgetPlans?: BudgetPlanTaskRow[];
};

/** What each role's dashboard is about, shown under the greeting. */
export const PROFILE_DESCRIPTIONS: Record<RoleProfile, string> = {
    manajemen:
        'Ringkasan seluruh pengadaan barang dan jasa yang berjalan di UP Kendari.',
    pengusul:
        'Pantau usulan pekerjaan Anda: mana yang menunggu persetujuan, perlu diperbaiki, sedang diproses, atau sudah selesai.',
    direksi:
        'Usulan bidang Anda yang menunggu persetujuan, dan pengadaan bidang yang sedang berjalan.',
    perencana:
        'Pengadaan yang ditugaskan kepada Anda sebagai PIC Perencana dan posisi persetujuannya.',
    pelaksana:
        'Pengadaan yang ditugaskan kepada Anda sebagai PIC Pelaksana, sampai disetujui TL Pengadaan dan diteruskan ke Keuangan.',
    keuangan:
        'Tagihan yang pelaksanaannya sudah disetujui TL Pengadaan dan menunggu pembayaran.',
    umum: 'Ringkasan pengadaan yang Anda usulkan atau tangani.',
};

const TONES: Record<string, string> = {
    primary: 'bg-primary/10 text-primary',
    sky: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
    amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    red: 'bg-destructive/10 text-destructive',
    emerald: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
};

const ICONS: Record<string, LucideIcon> = {
    total: FolderKanban,
    waiting: Hourglass,
    returned: RotateCcw,
    running: CircleDashed,
    done: BadgeCheck,
    team: Users,
    value: Wallet,
    todo: ClipboardList,
    approved: FileCheck2,
    ready: Send,
    finance: Landmark,
    month: BadgeCheck,
    paid: Wallet,
};

const STAGE_ICONS: Record<string, LucideIcon> = {
    usulan: FilePlus2,
    perencanaan: ClipboardList,
    pelaksanaan: Wrench,
    keuangan: Landmark,
    selesai: BadgeCheck,
};

/**
 * The role's own figures, each with what it means for the reader.
 * Clicking a card filters the role's jobs list and table below.
 */
export function FigureCards({
    cards,
    activeCard = null,
    onSelect,
}: {
    cards: FigureCard[];
    activeCard?: string | null;
    onSelect?: (key: string | null) => void;
}) {
    const hasFilter = activeCard !== null && activeCard !== 'total';

    return (
        <div
            className={cn(
                'grid gap-3 sm:grid-cols-2',
                cards.length >= 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-4',
            )}
        >
            {cards.map((card) => {
                const Icon = ICONS[card.key] ?? FolderKanban;
                const isSelected =
                    card.key === 'total'
                        ? !hasFilter && activeCard === 'total'
                        : activeCard === card.key;

                const content = (
                    <>
                        <span
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-md',
                                TONES[card.tone] ?? TONES.primary,
                            )}
                        >
                            <Icon className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="section-label truncate group-hover:text-primary">
                                {card.label}
                            </p>
                            <p
                                className="tabular mt-1 text-2xl font-semibold text-foreground"
                                title={
                                    card.format === 'currency'
                                        ? formatCurrency(card.value)
                                        : undefined
                                }
                            >
                                {card.format === 'currency'
                                    ? formatCompactCurrency(card.value)
                                    : card.value}
                            </p>
                            {card.hint && (
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {card.hint}
                                </p>
                            )}
                        </div>
                    </>
                );

                if (!onSelect) {
                    return (
                        <section
                            key={card.key}
                            className="flex items-start gap-3 rounded-md border border-border bg-card p-4"
                        >
                            {content}
                        </section>
                    );
                }

                return (
                    <button
                        key={card.key}
                        type="button"
                        aria-pressed={isSelected}
                        onClick={() => {
                            if (card.key === 'total') {
                                if (hasFilter) {
                                    onSelect(null);
                                } else {
                                    document
                                        .getElementById('role-jobs-section')
                                        ?.scrollIntoView({
                                            behavior: 'smooth',
                                            block: 'nearest',
                                        });
                                }

                                return;
                            }

                            onSelect(
                                activeCard === card.key ? null : card.key,
                            );
                        }}
                        className={cn(
                            'group flex w-full cursor-pointer items-start gap-3 rounded-md border border-border bg-card p-4 text-left transition-colors hover:border-primary/50 hover:bg-accent/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            isSelected &&
                                'border-primary bg-primary/[0.04] ring-1 ring-primary',
                            hasFilter &&
                                !isSelected &&
                                'opacity-75 hover:opacity-100',
                        )}
                    >
                        {content}
                    </button>
                );
            })}
        </div>
    );
}

/**
 * The journey of a job, with how many of the reader's jobs stand at each step.
 */
export function StageFlow({
    stages,
    title = 'Posisi Pengadaan',
}: {
    stages: StageCount[];
    title?: string;
}) {
    const total = stages.reduce((sum, stage) => sum + stage.total, 0);

    return (
        <section className="rounded-md border border-border bg-card">
            <header className="flex items-baseline justify-between border-b border-border px-4 py-3">
                <h2 className="text-sm font-semibold text-foreground">
                    {title}
                </h2>
                <span className="tabular text-xs text-muted-foreground">
                    {total} pengadaan
                </span>
            </header>
            <ol className="grid grid-cols-2 gap-px bg-border sm:grid-cols-5">
                {stages.map((stage, index) => {
                    const Icon = STAGE_ICONS[stage.key] ?? FolderKanban;

                    return (
                        <li
                            key={stage.key}
                            className={cn(
                                'flex items-center gap-3 bg-card px-3 py-3 sm:px-4',
                                // Two to a row on a phone: the last of an odd
                                // number takes the whole row, leaving no gap.
                                index === stages.length - 1 &&
                                    stages.length % 2 === 1 &&
                                    'col-span-2 sm:col-span-1',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-full',
                                    stage.total > 0
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                <Icon className="size-4" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-xs leading-tight break-words text-muted-foreground">
                                    {index + 1}. {stage.label}
                                </p>
                                <p className="tabular text-lg font-semibold text-foreground">
                                    {stage.total}
                                </p>
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

/**
 * The jobs the reader works on, each with where it stands and what it waits for.
 */
export function RoleJobs({
    title,
    jobs,
    allHref,
    showDivision = false,
    activeFilterLabel = null,
    onResetFilter,
}: {
    title: string;
    jobs: RoleJob[];
    allHref: string | null;
    showDivision?: boolean;
    activeFilterLabel?: string | null;
    onResetFilter?: () => void;
}) {
    return (
        <section
            id="role-jobs-section"
            className="overflow-hidden rounded-md border border-border bg-card"
        >
            <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-sm font-semibold text-foreground">
                        {title}
                    </h2>
                    {activeFilterLabel && (
                        <span className="inline-flex items-center gap-1.5 rounded-sm bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                            Filter: {activeFilterLabel}
                            {onResetFilter && (
                                <button
                                    type="button"
                                    onClick={onResetFilter}
                                    className="ml-0.5 font-semibold underline hover:text-primary/80"
                                >
                                    Reset
                                </button>
                            )}
                        </span>
                    )}
                </div>
                {allHref !== null && jobs.length > 0 && (
                    <Link
                        href={allHref}
                        className="text-xs font-medium text-primary hover:underline"
                    >
                        Lihat semua
                    </Link>
                )}
            </header>

            {jobs.length === 0 ? (
                <EmptyState title="Belum ada pengadaan" />
            ) : (
                <Table>
                    <TableHeader className="bg-muted/60">
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Pengadaan</TableHead>
                            <TableHead>Tahap</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="text-right">Target</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {jobs.map((job) => (
                            <TableRow key={job.id}>
                                <TableCell>
                                    <Link
                                        href={procurements.show(job.id)}
                                        className="block max-w-[22rem] truncate font-medium hover:text-primary hover:underline"
                                    >
                                        {job.name}
                                    </Link>
                                    <span className="tabular text-xs text-muted-foreground">
                                        {job.number}
                                        {showDivision && job.division
                                            ? ` · ${job.division}`
                                            : ''}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <p className="text-sm font-medium">
                                        {job.stage.label}
                                    </p>
                                    <p className="max-w-[16rem] truncate text-xs text-muted-foreground">
                                        {job.note}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    {job.status.name && job.status.category ? (
                                        <StatusBadge
                                            label={job.status.name}
                                            category={job.status.category}
                                        />
                                    ) : (
                                        '—'
                                    )}
                                </TableCell>
                                <TableCell className="tabular text-right text-xs whitespace-nowrap">
                                    {job.target_date
                                        ? formatDate(job.target_date)
                                        : '—'}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}

/**
 * PRK entered by the Anggaran role for this division that have not yet been
 * turned into a work proposal.
 */
export function DivisionBudgetPlans({
    plans,
    canPropose,
}: {
    plans: BudgetPlanTaskRow[];
    canPropose: boolean;
}) {
    if (plans.length === 0) {
        return null;
    }

    return (
        <section
            id="division-budget-plans"
            aria-label="PRK perlu ditindaklanjuti"
            className="overflow-hidden rounded-md border border-border bg-card"
        >
            <header className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                <div className="flex items-center gap-2.5">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                        <FilePlus2 className="size-4" />
                    </span>
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-sm font-semibold text-foreground">
                                PRK Perlu Ditindaklanjuti / Diusulkan
                            </h2>
                            <span className="tabular rounded-sm bg-primary/10 px-1.5 py-0.5 text-xs font-semibold text-primary">
                                {plans.length} PRK
                            </span>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            PRK dari bagian Anggaran yang ditujukan untuk bidang
                            Anda dan belum diajukan menjadi usulan pekerjaan.
                        </p>
                    </div>
                </div>
            </header>

            <Table>
                <TableHeader className="bg-muted/60">
                    <TableRow className="hover:bg-transparent">
                        <TableHead>Nomor PRK</TableHead>
                        <TableHead>Program / Sub Program</TableHead>
                        <TableHead>No. PR / No. WO</TableHead>
                        <TableHead className="text-right">
                            Nilai Rencana
                        </TableHead>
                        {canPropose && (
                            <TableHead className="w-[11rem] text-right">
                                Aksi
                            </TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {plans.map((plan) => (
                        <TableRow key={plan.id}>
                            <TableCell className="align-top">
                                <span className="tabular font-semibold text-foreground">
                                    {plan.prk_induk}
                                </span>
                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                    Anggaran {plan.budget_type_label}
                                    {plan.division ? ` · ${plan.division}` : ''}
                                </span>
                            </TableCell>
                            <TableCell className="align-top">
                                <p className="max-w-[26rem] font-medium whitespace-normal text-foreground">
                                    {plan.description}
                                </p>
                            </TableCell>
                            <TableCell className="tabular align-top text-xs text-muted-foreground">
                                <div>PR: {plan.pr_number ?? '—'}</div>
                                <div>WO: {plan.wo_number ?? '—'}</div>
                            </TableCell>
                            <TableCell className="tabular text-right align-top font-medium whitespace-nowrap">
                                {plan.planned_value > 0
                                    ? formatCurrency(plan.planned_value)
                                    : '—'}
                            </TableCell>
                            {canPropose && (
                                <TableCell className="text-right align-top">
                                    <Button size="sm" asChild>
                                        <Link
                                            href={
                                                procurements.create({
                                                    query: {
                                                        prk: plan.prk_induk,
                                                    },
                                                }).url
                                            }
                                        >
                                            <FilePlus2 className="size-3.5" />
                                            Usulkan Pekerjaan
                                        </Link>
                                    </Button>
                                </TableCell>
                            )}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}
