import { Head, Link, router, useForm } from '@inertiajs/react';
import { UserSquare2 } from 'lucide-react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ProcurementFilterBar } from '@/components/procurement-filter-bar';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import picAssignments from '@/routes/pic-assignments';
import procurements from '@/routes/procurements';
import type {
    FilterOptions,
    Option,
    Paginated,
    ProcurementFilters,
    ProcurementRow,
} from '@/types';

const NONE = 'none';

type AssignmentFilters = ProcurementFilters & { unassigned: boolean };

export default function PicAssignmentIndex({
    procurements: page,
    filters,
    options,
}: {
    procurements: Paginated<ProcurementRow>;
    filters: AssignmentFilters;
    options: FilterOptions & { planners: Option[]; executors: Option[] };
}) {
    return (
        <>
            <Head title="Penunjukan PIC" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pengadaan"
                    title="Penunjukan PIC"
                    description="Tunjuk PIC Perencana dan PIC Pelaksana untuk setiap pengadaan. Akses PIC lama otomatis dicabut saat diganti."
                    actions={
                        <Button
                            variant={filters.unassigned ? 'default' : 'outline'}
                            onClick={() =>
                                router.get(
                                    picAssignments.index().url,
                                    filters.unassigned ? {} : { unassigned: 1 },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {filters.unassigned
                                ? 'Tampilkan semua'
                                : 'Hanya yang belum lengkap'}
                        </Button>
                    }
                />

                <ProcurementFilterBar
                    url={picAssignments.index().url}
                    filters={filters}
                    options={options}
                />

                {page.data.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={UserSquare2}
                            title="Tidak ada pengadaan yang cocok"
                            description="Semua pengadaan pada filter ini sudah memiliki PIC."
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-[20rem]">
                                        Pengadaan
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Nilai HPE
                                    </TableHead>
                                    <TableHead className="w-56">
                                        PIC Perencana
                                    </TableHead>
                                    <TableHead className="w-56">
                                        PIC Pelaksana
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {page.data.map((row) => (
                                    <AssignmentRow
                                        key={row.id}
                                        row={row}
                                        planners={options.planners}
                                        executors={options.executors}
                                    />
                                ))}
                            </TableBody>
                        </Table>

                        <DataPagination page={page} />
                    </div>
                )}
            </div>
        </>
    );
}

function AssignmentRow({
    row,
    planners,
    executors,
}: {
    row: ProcurementRow;
    planners: Option[];
    executors: Option[];
}) {
    const form = useForm<{
        planner_id: number | null;
        executor_id: number | null;
    }>({
        planner_id: row.planner?.id ?? null,
        executor_id: row.executor?.id ?? null,
    });

    const isDirty =
        form.data.planner_id !== (row.planner?.id ?? null) ||
        form.data.executor_id !== (row.executor?.id ?? null);

    return (
        <TableRow>
            <TableCell>
                <Link
                    href={procurements.show(row.id)}
                    className="block max-w-[20rem] truncate font-medium hover:text-primary hover:underline"
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

            <TableCell>
                <PicSelect
                    value={form.data.planner_id}
                    options={planners}
                    onChange={(value) => form.setData('planner_id', value)}
                />
            </TableCell>

            <TableCell>
                <PicSelect
                    value={form.data.executor_id}
                    options={executors}
                    onChange={(value) => form.setData('executor_id', value)}
                />
            </TableCell>

            <TableCell className="text-right">
                <Button
                    size="sm"
                    variant="outline"
                    disabled={!isDirty || form.processing}
                    onClick={() =>
                        form.put(procurements.pic.update(row.id).url, {
                            preserveScroll: true,
                        })
                    }
                >
                    Simpan
                </Button>
            </TableCell>
        </TableRow>
    );
}

function PicSelect({
    value,
    options,
    onChange,
}: {
    value: number | null;
    options: Option[];
    onChange: (value: number | null) => void;
}) {
    return (
        <Select
            value={value === null ? NONE : String(value)}
            onValueChange={(next) =>
                onChange(next === NONE ? null : Number(next))
            }
        >
            <SelectTrigger className="w-full">
                <SelectValue placeholder="Belum ditunjuk" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NONE}>Belum ditunjuk</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={String(option.value)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

PicAssignmentIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penunjukan PIC', href: picAssignments.index() },
    ],
};
