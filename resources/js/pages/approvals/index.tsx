import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { ProcurementTable } from '@/components/procurement-table';
import { ApprovalBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';
import { dashboard } from '@/routes';
import approvals from '@/routes/approvals';
import procurements from '@/routes/procurements';
import type { Paginated, ProcurementRow } from '@/types';

export default function ApprovalIndex({
    procurements: page,
    recentDecisions,
}: {
    procurements: Paginated<ProcurementRow>;
    recentDecisions: ProcurementRow[];
}) {
    return (
        <>
            <Head title="Approval" />

            <div className="flex flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    eyebrow="Persetujuan"
                    title="Antrean Approval Perencanaan"
                    description="Dokumen perencanaan yang telah diajukan PIC Perencana dan menunggu keputusan Team Leader."
                />

                <ProcurementTable
                    page={page}
                    columns={[
                        'unit',
                        'hpe',
                        'planner',
                        'planningProgress',
                        'status',
                    ]}
                    emptyTitle="Tidak ada antrean persetujuan"
                    emptyDescription="Semua dokumen perencanaan sudah ditinjau."
                />

                {recentDecisions.length > 0 && (
                    <section className="overflow-hidden rounded-md border border-border bg-card">
                        <header className="border-b border-border px-4 py-3">
                            <h2 className="text-sm font-semibold text-foreground">
                                Keputusan Terakhir
                            </h2>
                        </header>

                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Pengadaan</TableHead>
                                    <TableHead>PIC Perencana</TableHead>
                                    <TableHead>Keputusan</TableHead>
                                    <TableHead className="text-right">
                                        Nilai HPE
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentDecisions.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <Link
                                                href={procurements.show(row.id)}
                                                className="block max-w-[20rem] truncate font-medium hover:text-primary hover:underline"
                                            >
                                                {row.name}
                                            </Link>
                                            <span className="tabular text-xs text-muted-foreground">
                                                {row.number}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {row.planner?.name ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <ApprovalBadge
                                                state={
                                                    row.planning_approval_state
                                                }
                                                label={
                                                    row.planning_approval_label
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="tabular text-right font-medium whitespace-nowrap">
                                            {formatCurrency(row.hpe_value)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </section>
                )}
            </div>
        </>
    );
}

ApprovalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Approval', href: approvals.index() },
    ],
};
