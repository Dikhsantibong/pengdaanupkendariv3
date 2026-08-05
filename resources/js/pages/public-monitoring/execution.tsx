import { BarList } from '@/components/charts/bar-list';
import { ColumnChart } from '@/components/charts/column-chart';
import type { ColumnPoint } from '@/components/charts/column-chart';
import { DonutChart } from '@/components/charts/donut-chart';
import type { DonutSlice } from '@/components/charts/donut-chart';
import { SCurveChart } from '@/components/charts/s-curve-chart';
import type { SCurveData } from '@/components/charts/s-curve-chart';
import { EmptyState } from '@/components/empty-state';
import { BoardPanel, PublicBoardShell } from '@/components/public-board-shell';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Progress } from '@/components/ui/progress';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { StatusCategory } from '@/types';

type ChecklistRow = {
    name: string;
    is_optional: boolean;
    completed: number;
    total: number;
    percentage: number;
};

type ExecutionRow = {
    id: number;
    number: string;
    name: string;
    target_unit: string;
    work_director: string;
    status: string;
    category: StatusCategory;
    completed: number;
    total: number;
    percentage: number;
    target_date: string | null;
    remaining_days: number | null;
    running_days: number;
};

type ExecutionBoardProps = {
    summary: {
        total: number;
        selesai: number;
        rataRataProgres: number;
        rataRataUsia: number;
        terlambat: number;
        mendekatiTarget: number;
        tahapanPelaksanaan: number;
        hampirTuntas: number;
    };
    sCurve: SCurveData;
    checklistBreakdown: ChecklistRow[];
    byStatus: { name: string; category: StatusCategory; total: number }[];
    byUnit: { name: string; total: number; percentage: number }[];
    monthlyCompleted: ColumnPoint[];
    scheduleComposition: DonutSlice[];
    rows: ExecutionRow[];
    completed: {
        id: number;
        number: string;
        name: string;
        target_unit: string;
        completed_at: string | null;
    }[];
    generatedAt: string;
};

export default function ExecutionBoardPage({
    summary,
    sCurve,
    checklistBreakdown,
    byStatus,
    byUnit,
    monthlyCompleted,
    scheduleComposition,
    rows,
    completed,
    generatedAt,
}: ExecutionBoardProps) {
    return (
        <PublicBoardShell
            title="Papan Monitoring Pelaksanaan"
            subtitle="Progres pelaksanaan pengadaan hingga masa pemeliharaan"
            active="execution"
            generatedAt={generatedAt}
        >
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7">
                <StatCard
                    label="Dalam Pelaksanaan"
                    value={summary.total}
                    hint={`${summary.tahapanPelaksanaan} tahapan`}
                    accent
                />
                <StatCard
                    label="Rata-rata Progres"
                    value={`${summary.rataRataProgres}%`}
                />
                <StatCard
                    label="Hampir Tuntas"
                    value={summary.hampirTuntas}
                    hint="Progres di atas 80%"
                />
                <StatCard
                    label="Mendekati Target"
                    value={summary.mendekatiTarget}
                    hint="Jatuh tempo ≤ 14 hari"
                />
                <StatCard label="Melewati Target" value={summary.terlambat} />
                <StatCard
                    label="Rata-rata Berjalan"
                    value={`${summary.rataRataUsia} hr`}
                    hint="Sejak perencanaan disetujui"
                />
                <StatCard label="Total Selesai" value={summary.selesai} />
            </div>

            <div className="grid gap-4 2xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <BoardPanel
                    title="Kurva S Pelaksanaan"
                    caption="Rencana vs realisasi kumulatif"
                    bodyClassName="p-4"
                >
                    <SCurveChart data={sCurve} />
                </BoardPanel>

                <BoardPanel title="Kepatuhan Jadwal">
                    <DonutChart
                        slices={scheduleComposition}
                        centerLabel="Posisi terhadap target"
                    />
                </BoardPanel>
            </div>

            <div className="grid gap-4 xl:grid-cols-2 2xl:grid-cols-4">
                <BoardPanel
                    title="Capaian Tahapan Pelaksanaan"
                    caption="% pengadaan yang sudah melewati tahapan"
                    className="2xl:col-span-2"
                >
                    {checklistBreakdown.length === 0 ? (
                        <EmptyState
                            title="Belum ada tahapan"
                            className="py-8"
                        />
                    ) : (
                        <BarList
                            mode="percentage"
                            rows={checklistBreakdown.map((item) => ({
                                name: item.is_optional
                                    ? `${item.name} (opsional)`
                                    : item.name,
                                total: item.completed,
                                percentage: item.percentage,
                                caption: `${item.completed}/${item.total}`,
                            }))}
                        />
                    )}
                </BoardPanel>

                <BoardPanel title="Sebaran Status Progres">
                    <BarList
                        rows={byStatus.map((row) => ({
                            name: row.name,
                            total: row.total,
                            category: row.category,
                        }))}
                    />
                </BoardPanel>

                <BoardPanel
                    title="Pengadaan Selesai per Bulan"
                    caption="12 bulan terakhir"
                >
                    <ColumnChart points={monthlyCompleted} />
                </BoardPanel>
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <BoardPanel
                    title="Pelaksanaan per Unit Tujuan"
                    caption="Jumlah & rata-rata progres"
                >
                    <BarList
                        rows={byUnit.map((unit) => ({
                            name: unit.name,
                            total: unit.total,
                            caption: `${unit.percentage}% progres`,
                        }))}
                        limit={10}
                    />
                </BoardPanel>

                <BoardPanel title="Pengadaan Selesai Terbaru">
                    {completed.length === 0 ? (
                        <EmptyState
                            title="Belum ada pengadaan selesai"
                            className="py-8"
                        />
                    ) : (
                        <ul className="divide-y divide-border">
                            {completed.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex items-center justify-between gap-3 px-4 py-2.5"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {row.name}
                                        </p>
                                        <p className="tabular text-xs text-muted-foreground">
                                            {row.number} · {row.target_unit}
                                        </p>
                                    </div>
                                    <span className="tabular shrink-0 text-xs text-muted-foreground">
                                        {formatDateTime(row.completed_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </BoardPanel>
            </div>

            <BoardPanel
                title="Detail Pengadaan Tahap Pelaksanaan"
                caption={`${rows.length} pengadaan`}
            >
                {rows.length === 0 ? (
                    <EmptyState
                        title="Tidak ada pengadaan pada tahap pelaksanaan"
                        description="Pengadaan muncul di sini setelah dokumen perencanaannya disetujui."
                    />
                ) : (
                    <Table>
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Pengadaan</TableHead>
                                <TableHead>Unit Tujuan</TableHead>
                                <TableHead>Direksi Pekerjaan</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-48">
                                    Progres Pelaksanaan
                                </TableHead>
                                <TableHead>Berjalan</TableHead>
                                <TableHead>Target</TableHead>
                                <TableHead>Sisa Waktu</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell>
                                        <span className="block max-w-[24rem] truncate font-medium">
                                            {row.name}
                                        </span>
                                        <span className="tabular text-xs text-muted-foreground">
                                            {row.number}
                                        </span>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {row.target_unit}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {row.work_director}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={row.status}
                                            category={row.category}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="tabular flex items-center justify-between text-xs">
                                            <span className="font-semibold">
                                                {row.percentage}%
                                            </span>
                                            <span className="text-muted-foreground">
                                                {row.completed}/{row.total}
                                            </span>
                                        </div>
                                        <Progress
                                            value={row.percentage}
                                            className="mt-1"
                                        />
                                    </TableCell>
                                    <TableCell className="tabular whitespace-nowrap">
                                        {row.running_days} hr
                                    </TableCell>
                                    <TableCell className="tabular whitespace-nowrap">
                                        {formatDate(row.target_date)}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        <RemainingBadge
                                            days={row.remaining_days}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </BoardPanel>
        </PublicBoardShell>
    );
}

function RemainingBadge({ days }: { days: number | null }) {
    if (days === null) {
        return <span className="text-xs text-muted-foreground">—</span>;
    }

    const isLate = days < 0;
    const isDueSoon = days >= 0 && days <= 14;

    return (
        <span
            className={cn(
                'tabular rounded-sm px-2 py-0.5 text-xs font-medium',
                isLate && 'bg-status-batal-surface text-status-batal',
                isDueSoon && 'bg-status-berjalan-surface text-status-berjalan',
                !isLate &&
                    !isDueSoon &&
                    'bg-status-selesai-surface text-status-selesai',
            )}
        >
            {isLate ? `Telat ${Math.abs(days)} hr` : `${days} hr lagi`}
        </span>
    );
}
