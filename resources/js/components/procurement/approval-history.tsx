import { History, Hourglass } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

export type ApprovalHistoryRow = {
    id: number;
    stage:
        | 'Usulan'
        | 'Perencanaan'
        | 'Pelaksanaan'
        | 'Penyerahan'
        | 'Pembayaran'
        | 'Keuangan';
    step: string;
    outcome:
        | 'dibuat'
        | 'diajukan'
        | 'disetujui'
        | 'dikembalikan'
        | 'ditunjuk'
        | 'selesai';
    description: string;
    user: string | null;
    user_role: string | null;
    created_at: string | null;
};

export type ApprovalHistory = {
    rows: ApprovalHistoryRow[];
    /** What the job waits for now; null once it is closed. */
    waiting: string | null;
};

const OUTCOMES: Record<
    ApprovalHistoryRow['outcome'],
    { label: string; className: string }
> = {
    dibuat: {
        label: 'Dibuat',
        className: 'bg-muted text-foreground',
    },
    diajukan: {
        label: 'Diajukan',
        className: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
    },
    disetujui: {
        label: 'Disetujui',
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    },
    dikembalikan: {
        label: 'Dikembalikan',
        className: 'bg-destructive/10 text-destructive',
    },
    ditunjuk: {
        label: 'Ditunjuk',
        className: 'bg-violet-500/10 text-violet-700 dark:text-violet-400',
    },
    selesai: {
        label: 'Selesai',
        className: 'bg-primary/10 text-primary',
    },
};

/**
 * Every decision and hand-over of the job, from the proposal to the
 * payment, the latest on top: where the job stands now first, then each
 * step back to the proposal. The numbers keep the order things happened.
 */
export function ApprovalHistoryTable({
    history,
}: {
    history: ApprovalHistory;
}) {
    if (history.rows.length === 0 && history.waiting === null) {
        return (
            <div className="rounded-md border border-border bg-card">
                <EmptyState
                    icon={History}
                    title="Belum ada riwayat approval"
                    description="Pengajuan, persetujuan, dan pengembalian dari usulan sampai pembayaran akan tercatat di sini."
                />
            </div>
        );
    }

    const newestFirst = [...history.rows].reverse();

    return (
        <section className="overflow-hidden rounded-md border border-border bg-card">
            <header className="border-b border-border px-4 py-3">
                <h2 className="text-sm font-semibold text-foreground">
                    Riwayat Approval
                </h2>
                <p className="text-xs text-muted-foreground">
                    Proses persetujuan dari usulan pengguna, perencanaan,
                    pelaksanaan, hingga pembayaran oleh Keuangan.
                </p>
            </header>

            <Table>
                <TableHeader className="bg-muted/60">
                    <TableRow className="hover:bg-transparent">
                        <TableHead className="w-10 text-center">No</TableHead>
                        <TableHead>Tahap</TableHead>
                        <TableHead>Proses</TableHead>
                        <TableHead>Hasil</TableHead>
                        <TableHead>Oleh</TableHead>
                        <TableHead>Waktu</TableHead>
                        <TableHead className="min-w-[16rem]">
                            Keterangan
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {history.waiting !== null && (
                        <TableRow className="bg-muted/30 hover:bg-muted/30">
                            <TableCell className="text-center">
                                <Hourglass className="mx-auto size-3.5 text-muted-foreground" />
                            </TableCell>
                            <TableCell
                                colSpan={6}
                                className="text-sm text-muted-foreground"
                            >
                                <span className="font-medium text-foreground">
                                    Posisi saat ini:
                                </span>{' '}
                                {history.waiting}
                            </TableCell>
                        </TableRow>
                    )}

                    {newestFirst.map((row, index) => {
                        const outcome = OUTCOMES[row.outcome];

                        return (
                            <TableRow key={row.id} className="align-top">
                                <TableCell className="tabular text-center text-xs text-muted-foreground">
                                    {newestFirst.length - index}
                                </TableCell>
                                <TableCell className="text-sm font-medium whitespace-nowrap">
                                    {row.stage}
                                </TableCell>
                                <TableCell className="text-sm whitespace-nowrap">
                                    {row.step}
                                </TableCell>
                                <TableCell>
                                    <span
                                        className={cn(
                                            'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                                            outcome.className,
                                        )}
                                    >
                                        {outcome.label}
                                    </span>
                                </TableCell>
                                <TableCell className="text-sm whitespace-nowrap">
                                    {row.user ?? 'Sistem'}
                                    {row.user_role && (
                                        <span className="block text-xs text-muted-foreground">
                                            {row.user_role}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="tabular text-xs whitespace-nowrap text-muted-foreground">
                                    {formatDateTime(row.created_at)}
                                </TableCell>
                                <TableCell className="text-sm whitespace-normal">
                                    {row.description}
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </section>
    );
}
