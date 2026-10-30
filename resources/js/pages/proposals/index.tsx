import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    Clock,
    FilePlus2,
    Inbox,
    Paperclip,
    RotateCcw,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import procurements from '@/routes/procurements';
import proposals from '@/routes/proposals';

export type ProposalRow = {
    id: number;
    number: string;
    name: string;
    state:
        | 'menunggu_persetujuan_tl'
        | 'menunggu_persetujuan'
        | 'dikembalikan'
        | 'disetujui';
    state_label: string;
    /** Waiting but incomplete: still with the proposer. */
    is_held?: boolean;
    division: string | null;
    proposer: string | null;
    proposer_position: string | null;
    target_units: string;
    hpe_value: number;
    prk_number: string | null;
    attachments_count: number;
    submitted_at: string | null;
    reviewed_at: string | null;
    review_note: string | null;
};

export const STATE_STYLE: Record<ProposalRow['state'], string> = {
    menunggu_persetujuan_tl:
        'bg-sky-500/10 text-sky-700 ring-sky-500/30 dark:text-sky-400',
    menunggu_persetujuan:
        'bg-amber-500/10 text-amber-700 ring-amber-500/30 dark:text-amber-400',
    dikembalikan:
        'bg-red-500/10 text-red-700 ring-red-500/30 dark:text-red-400',
    disetujui:
        'bg-emerald-500/10 text-emerald-700 ring-emerald-500/30 dark:text-emerald-400',
};

export function StateBadge({
    row,
}: {
    row: Pick<ProposalRow, 'state' | 'is_held'>;
}) {
    if (row.is_held) {
        return (
            <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground ring-1 ring-border ring-inset">
                Belum Lengkap
            </span>
        );
    }

    const label = {
        menunggu_persetujuan_tl: 'Menunggu Persetujuan TL',
        menunggu_persetujuan: 'Menunggu Persetujuan Direksi Pekerjaan',
        dikembalikan: 'Dikembalikan',
        disetujui: 'Disetujui',
    }[row.state];

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset',
                STATE_STYLE[row.state],
            )}
        >
            {label}
        </span>
    );
}

function AwaitingCard({ row }: { row: ProposalRow }) {
    return (
        <Link
            href={proposals.show(row.id).url}
            className="group flex flex-col rounded-lg border border-border bg-card p-4 shadow-xs transition hover:border-primary/50 hover:shadow-md"
        >
            <div className="flex items-start justify-between gap-3">
                <p className="line-clamp-2 text-base font-semibold text-foreground group-hover:text-primary">
                    {row.name}
                </p>
                <StateBadge row={row} />
            </div>

            <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">Pengusul</dt>
                    <dd className="font-medium">{row.proposer ?? '-'}</dd>
                    {row.proposer_position && (
                        <dd className="text-xs text-muted-foreground">
                            {row.proposer_position}
                        </dd>
                    )}
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Nomor PRK</dt>
                    <dd className="tabular line-clamp-2 font-medium">
                        {row.prk_number ?? '-'}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Unit Tujuan
                    </dt>
                    <dd className="line-clamp-1">{row.target_units || '-'}</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted-foreground">Diajukan</dt>
                    <dd className="tabular">
                        {formatDateTime(row.submitted_at)}
                    </dd>
                </div>
            </dl>

            <div className="mt-4 flex items-center justify-between border-t border-border pt-3 text-sm">
                <span className="flex items-center gap-1.5 text-muted-foreground">
                    <Paperclip className="size-4" />
                    {row.attachments_count} lampiran
                </span>
                <span className="flex items-center gap-1 font-semibold text-primary">
                    Tinjau &amp; Putuskan
                    <ChevronRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                </span>
            </div>
        </Link>
    );
}

function ListRow({ row }: { row: ProposalRow }) {
    return (
        <li>
            <Link
                href={proposals.show(row.id).url}
                className="group flex flex-col gap-2 px-4 py-3 transition-colors hover:bg-accent/40 sm:flex-row sm:items-center sm:gap-4"
            >
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-foreground group-hover:text-primary">
                        {row.name}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {row.division ?? '-'} ·{' '}
                        {row.state === 'dikembalikan' && row.review_note
                            ? `Catatan: ${row.review_note}`
                            : `Diajukan ${formatDateTime(row.submitted_at)}`}
                    </p>
                </div>
                <span className="tabular hidden max-w-[16rem] truncate text-sm font-medium md:block">
                    {row.prk_number ?? '-'}
                </span>
                <StateBadge row={row} />
                <ChevronRight className="hidden size-4 text-muted-foreground sm:block" />
            </Link>
        </li>
    );
}

function Section({
    title,
    icon: Icon,
    count,
    children,
}: {
    title: string;
    icon: typeof Inbox;
    count?: number;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3">
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
                <Icon className="size-5 text-primary" />
                {title}
                {count !== undefined && (
                    <span className="tabular rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                        {count}
                    </span>
                )}
            </h2>
            {children}
        </section>
    );
}

export default function ProposalIndex({
    canApprove,
    canPropose,
    division,
    awaiting,
    decided,
    mine,
}: {
    canApprove: boolean;
    canPropose: boolean;
    division: string | null;
    awaiting: ProposalRow[];
    decided: ProposalRow[];
    mine: ProposalRow[];
}) {
    return (
        <>
            <Head title="Usulan Pekerjaan" />

            <div className="flex w-full flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={
                        division ? `Bidang ${division}` : 'Usulan Pekerjaan'
                    }
                    title="Usulan Pekerjaan"
                    description={
                        canApprove
                            ? 'Tinjau usulan pekerjaan yang menunggu keputusan Anda. Usulan staf disetujui TL lebih dulu, lalu Asman bidang, kemudian masuk perencanaan untuk penunjukan PIC.'
                            : 'Pantau usulan pekerjaan Anda. Usulan disetujui TL Anda, lalu Asman bidang, kemudian masuk perencanaan untuk penunjukan PIC Perencana.'
                    }
                    actions={
                        canPropose ? (
                            <Button asChild size="lg">
                                <Link href={procurements.create().url}>
                                    <FilePlus2 className="size-4" />
                                    Usulkan Pekerjaan
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                {canApprove && (
                    <Section
                        title="Menunggu Persetujuan Anda"
                        icon={Clock}
                        count={awaiting.length}
                    >
                        {awaiting.length === 0 ? (
                            <div className="flex items-center gap-3 rounded-lg border border-dashed border-border bg-card px-5 py-8 text-sm text-muted-foreground">
                                <CheckCircle2 className="size-6 text-emerald-600" />
                                Tidak ada usulan yang menunggu. Usulan baru dari
                                bidang Anda akan muncul di sini.
                            </div>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                                {awaiting.map((row) => (
                                    <AwaitingCard key={row.id} row={row} />
                                ))}
                            </div>
                        )}
                    </Section>
                )}

                {(canPropose || mine.length > 0) && (
                    <Section
                        title="Usulan Saya"
                        icon={Inbox}
                        count={mine.length}
                    >
                        {mine.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-border bg-card px-5 py-8 text-center text-sm text-muted-foreground">
                                Anda belum mengusulkan pekerjaan. Klik{' '}
                                <b>Usulkan Pekerjaan</b> untuk memulai.
                            </div>
                        ) : (
                            <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                                {mine.map((row) => (
                                    <ListRow key={row.id} row={row} />
                                ))}
                            </ul>
                        )}
                    </Section>
                )}

                {canApprove && decided.length > 0 && (
                    <Section title="Riwayat Keputusan Anda" icon={RotateCcw}>
                        <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                            {decided.map((row) => (
                                <ListRow key={row.id} row={row} />
                            ))}
                        </ul>
                    </Section>
                )}
            </div>
        </>
    );
}

ProposalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Usulan Pekerjaan', href: proposals.index() },
    ],
};
