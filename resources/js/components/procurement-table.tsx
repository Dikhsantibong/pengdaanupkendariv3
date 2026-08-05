import { Link } from '@inertiajs/react';
import { FileSearch } from 'lucide-react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { ApprovalBadge, StatusBadge } from '@/components/status-badge';
import { Progress } from '@/components/ui/progress';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency, formatDate } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { ChecklistProgress, Paginated, ProcurementRow } from '@/types';

export type ProcurementColumn =
    | 'unit'
    | 'director'
    | 'method'
    | 'budgetSource'
    | 'hpe'
    | 'status'
    | 'approval'
    | 'planner'
    | 'executor'
    | 'planningProgress'
    | 'executionProgress'
    | 'target';

const columnHeadings: Record<ProcurementColumn, string> = {
    unit: 'Unit Tujuan',
    director: 'Direksi Pekerjaan',
    method: 'Metode',
    budgetSource: 'Sumber Anggaran',
    hpe: 'Nilai HPE',
    status: 'Status Progres',
    approval: 'Persetujuan',
    planner: 'PIC Perencana',
    executor: 'PIC Pelaksana',
    planningProgress: 'Progres Perencanaan',
    executionProgress: 'Progres Pelaksanaan',
    target: 'Target Selesai',
};

export function ProcurementTable({
    page,
    columns = ['unit', 'director', 'hpe', 'status', 'planner', 'executor'],
    emptyTitle = 'Belum ada data pengadaan',
    emptyDescription,
}: {
    page: Paginated<ProcurementRow>;
    columns?: ProcurementColumn[];
    emptyTitle?: string;
    emptyDescription?: string;
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
                        <TableHead className="w-[22rem]">Pengadaan</TableHead>
                        {columns.map((column) => (
                            <TableHead
                                key={column}
                                className={
                                    column === 'hpe' ? 'text-right' : undefined
                                }
                            >
                                {columnHeadings[column]}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>

                <TableBody>
                    {page.data.map((procurement) => (
                        <TableRow key={procurement.id}>
                            <TableCell>
                                <Link
                                    href={procurements.show(procurement.id)}
                                    className="block max-w-[22rem] truncate font-medium text-foreground hover:text-primary hover:underline"
                                >
                                    {procurement.name}
                                </Link>
                                <span className="tabular text-xs text-muted-foreground">
                                    {procurement.number}
                                    {procurement.prk_number
                                        ? ` · PRK ${procurement.prk_number}`
                                        : ''}
                                </span>
                            </TableCell>

                            {columns.map((column) => (
                                <TableCell
                                    key={column}
                                    className={
                                        column === 'hpe'
                                            ? 'tabular text-right font-medium whitespace-nowrap'
                                            : undefined
                                    }
                                >
                                    {renderCell(column, procurement)}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            <DataPagination page={page} />
        </div>
    );
}

function renderCell(column: ProcurementColumn, row: ProcurementRow) {
    switch (column) {
        case 'unit':
            return <span className="whitespace-nowrap">{row.target_unit}</span>;
        case 'director':
            return (
                <span className="whitespace-nowrap">{row.work_director}</span>
            );
        case 'method':
            return (
                <span className="whitespace-nowrap">
                    {row.procurement_method ?? (
                        <span className="text-muted-foreground">—</span>
                    )}
                </span>
            );
        case 'budgetSource':
            return (
                <span className="whitespace-nowrap">
                    {row.budget_source ?? (
                        <span className="text-muted-foreground">—</span>
                    )}
                </span>
            );
        case 'hpe':
            return formatCurrency(row.hpe_value);
        case 'status':
            return (
                <StatusBadge
                    label={row.status.name}
                    category={row.status.category}
                />
            );
        case 'approval':
            return (
                <ApprovalBadge
                    state={row.planning_approval_state}
                    label={row.planning_approval_label}
                />
            );
        case 'planner':
            return (
                <span className="whitespace-nowrap">
                    {row.planner?.name ?? (
                        <span className="text-muted-foreground">
                            Belum ditunjuk
                        </span>
                    )}
                </span>
            );
        case 'executor':
            return (
                <span className="whitespace-nowrap">
                    {row.executor?.name ?? (
                        <span className="text-muted-foreground">
                            Belum ditunjuk
                        </span>
                    )}
                </span>
            );
        case 'planningProgress':
            return <ProgressCell progress={row.planning_progress} />;
        case 'executionProgress':
            return <ProgressCell progress={row.execution_progress} />;
        case 'target':
            return (
                <span className="tabular whitespace-nowrap">
                    {formatDate(row.target_completion_date)}
                </span>
            );
    }
}

function ProgressCell({ progress }: { progress?: ChecklistProgress }) {
    if (!progress) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="w-32 space-y-1">
            <div className="tabular flex items-center justify-between text-xs">
                <span className="font-medium">{progress.percentage}%</span>
                <span className="text-muted-foreground">
                    {progress.completed}/{progress.total}
                </span>
            </div>
            <Progress value={progress.percentage} />
        </div>
    );
}
