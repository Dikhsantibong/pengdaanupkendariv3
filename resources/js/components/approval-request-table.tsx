import { Link } from '@inertiajs/react';
import { ArrowRight, FileSearch } from 'lucide-react';
import { stateTone } from '@/components/contract-monitoring';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
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
import type { Paginated } from '@/types';

/** An amendment or Delivery Order in the Approval queue. */
export type ApprovalRequestRow = {
    id: number;
    title: string;
    number: string | null;
    state: string;
    state_label: string;
    proposer: string | null;
    submitted_at: string | null;
    updated_at: string | null;
    procurement: {
        id: number;
        number: string;
        name: string;
        work_director: string;
    };
    url: string;
};

/**
 * The amendments or Delivery Orders waiting on the user (or already
 * decided), each opening the job's own Amandemen / DO tab, where the
 * decision is taken with the full request in view.
 */
export function ApprovalRequestTable({
    page,
    decided,
    emptyTitle,
    emptyDescription,
}: {
    page: Paginated<ApprovalRequestRow>;
    decided: boolean;
    emptyTitle: string;
    emptyDescription: string;
}) {
    if (page.data.length === 0) {
        return (
            <div className="rounded-md border border-border bg-card">
                <EmptyState
                    icon={FileSearch}
                    title={emptyTitle}
                    description={emptyDescription}
                />
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-md border border-border bg-card">
            <Table>
                <TableHeader className="bg-muted/60">
                    <TableRow className="hover:bg-transparent">
                        <TableHead>Permohonan</TableHead>
                        <TableHead>Pengadaan</TableHead>
                        <TableHead>Direksi Pekerjaan</TableHead>
                        <TableHead>Pengusul</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>
                            {decided ? 'Terakhir Diperbarui' : 'Diajukan'}
                        </TableHead>
                        <TableHead className="text-right">Tindakan</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {page.data.map((row) => (
                        <TableRow key={row.id}>
                            <TableCell>
                                <span className="block font-medium whitespace-nowrap">
                                    {row.title}
                                </span>
                                <span className="tabular text-xs text-muted-foreground">
                                    {row.number ?? 'Belum bernomor'}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span className="block max-w-[18rem] truncate">
                                    {row.procurement.name}
                                </span>
                                <span className="tabular text-xs text-muted-foreground">
                                    {row.procurement.number}
                                </span>
                            </TableCell>
                            <TableCell className="whitespace-nowrap">
                                {row.procurement.work_director}
                            </TableCell>
                            <TableCell className="whitespace-nowrap">
                                {row.proposer ?? '—'}
                            </TableCell>
                            <TableCell>
                                <span
                                    className={cn(
                                        'rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                                        stateTone(row.state),
                                    )}
                                >
                                    {row.state_label}
                                </span>
                            </TableCell>
                            <TableCell className="tabular text-xs whitespace-nowrap text-muted-foreground">
                                {(() => {
                                    const at = decided
                                        ? row.updated_at
                                        : row.submitted_at;

                                    return at ? formatDateTime(at) : '—';
                                })()}
                            </TableCell>
                            <TableCell className="text-right">
                                <Button
                                    asChild
                                    size="sm"
                                    variant={decided ? 'outline' : 'default'}
                                >
                                    <Link href={row.url}>
                                        {decided ? 'Lihat' : 'Tinjau'}
                                        <ArrowRight className="size-3.5" />
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <DataPagination page={page} />
        </div>
    );
}
