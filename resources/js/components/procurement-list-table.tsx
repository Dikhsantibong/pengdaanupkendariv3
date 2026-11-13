import { Link } from '@inertiajs/react';
import { Eye, Minus, Plus } from 'lucide-react';
import { Fragment, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
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
import { formatCurrency, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';
import type { Paginated, ProcurementRow } from '@/types';

/**
 * Every procurement in one flat list. A row opens in place (+ / −) to show
 * its identity at a glance; "Detail" opens the procurement itself.
 */
export function ProcurementListTable({
    page,
    emptyDescription,
}: {
    page: Paginated<ProcurementRow>;
    emptyDescription?: string;
}) {
    const [open, setOpen] = useState<Set<number>>(new Set());

    const toggle = (id: number) =>
        setOpen((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    if (page.data.length === 0) {
        return (
            <div className="rounded-md border border-border bg-card">
                <EmptyState
                    title="Tidak ada pengadaan"
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
                        <TableHead className="w-12" />
                        <TableHead>Nomor Kontrak</TableHead>
                        <TableHead className="min-w-56">
                            Nama Pengadaan
                        </TableHead>
                        <TableHead>Mitra</TableHead>
                        <TableHead>Unit Tujuan</TableHead>
                        <TableHead className="text-right">Nilai HPE</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Tanggal Buat</TableHead>
                        <TableHead className="text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {page.data.map((row) => {
                        const isOpen = open.has(row.id);

                        return (
                            <Fragment key={row.id}>
                                <TableRow
                                    className={cn(
                                        isOpen &&
                                            'border-b-0 bg-muted/40 hover:bg-muted/40',
                                    )}
                                >
                                    <TableCell>
                                        <button
                                            type="button"
                                            onClick={() => toggle(row.id)}
                                            aria-expanded={isOpen}
                                            aria-label={
                                                isOpen
                                                    ? 'Tutup rincian'
                                                    : 'Buka rincian'
                                            }
                                            className="flex size-7 items-center justify-center rounded-md border border-primary/40 text-primary transition-colors hover:bg-primary/10"
                                        >
                                            {isOpen ? (
                                                <Minus className="size-3.5" />
                                            ) : (
                                                <Plus className="size-3.5" />
                                            )}
                                        </button>
                                    </TableCell>
                                    <TableCell className="tabular max-w-44 text-xs break-all">
                                        {row.number}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        <Link
                                            href={procurements.show(row.id)}
                                            className="line-clamp-2 font-medium text-foreground hover:text-primary hover:underline"
                                        >
                                            {row.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="max-w-44 text-xs">
                                        <span className="line-clamp-2">
                                            {row.partner_name ?? '—'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="max-w-40 text-xs">
                                        <span className="line-clamp-2">
                                            {row.target_unit || '—'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="tabular text-right text-xs whitespace-nowrap">
                                        {formatCurrency(row.hpe_value)}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={row.status.name}
                                            category={row.status.category}
                                        />
                                    </TableCell>
                                    <TableCell className="tabular text-xs whitespace-nowrap">
                                        {formatDate(row.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="border-primary/50 text-primary hover:bg-primary/10 hover:text-primary"
                                        >
                                            <Link
                                                href={procurements.show(row.id)}
                                            >
                                                <Eye className="size-3.5" />
                                                Detail
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>

                                {isOpen && (
                                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                                        <TableCell
                                            colSpan={9}
                                            className="px-4 pt-1 pb-4"
                                        >
                                            <RowDetails row={row} />
                                        </TableCell>
                                    </TableRow>
                                )}
                            </Fragment>
                        );
                    })}
                </TableBody>
            </Table>
            <DataPagination page={page} />
        </div>
    );
}

/**
 * The identity of a procurement in three columns of label and value.
 */
function RowDetails({ row }: { row: ProcurementRow }) {
    const columns: Array<Array<[string, string]>> = [
        [
            ['Direksi Pekerjaan', row.work_director],
            ['Jenis Kontrak', row.contract_type ?? '—'],
            ['Metode Pengadaan', row.procurement_method ?? '—'],
            ['Sumber Anggaran', row.budget_source ?? '—'],
            ['Nomor PRK', row.prk_number ?? '—'],
        ],
        [
            ['Nilai HPE', formatCurrency(row.hpe_value)],
            [
                'Nilai Setelah Nego',
                row.value_after_negotiation === null
                    ? '—'
                    : formatCurrency(row.value_after_negotiation),
            ],
            ['No. PR / PO', row.pr_po_number ?? '—'],
            ['Target Selesai', formatDate(row.target_completion_date)],
            ['Tanggal Buat', formatDate(row.created_at)],
        ],
        [
            ['Tahap Saat Ini', row.current_stage_label],
            ['Perencanaan', row.planning_approval_label],
            ['Pelaksanaan', row.execution_approval_label],
            [
                'PIC Perencana / Pelaksana',
                `${row.planner?.name ?? '—'} / ${row.executor?.name ?? '—'}`,
            ],
        ],
    ];

    return (
        <dl className="grid gap-x-8 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
            {columns.map((column, index) => (
                <div key={index} className="space-y-3">
                    {column.map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-[0.6875rem] font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                {label}
                            </dt>
                            <dd className="mt-0.5 text-sm text-foreground">
                                {value}
                            </dd>
                        </div>
                    ))}
                </div>
            ))}
        </dl>
    );
}
