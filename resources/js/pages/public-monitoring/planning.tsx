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
import { formatDate } from '@/lib/format';
import type { StatusCategory } from '@/types';

type ChecklistRow = {
    name: string;
    is_optional: boolean;
    completed: number;
    total: number;
    percentage: number;
};

type PlanningRow = {
    id: number;
    number: string;
    name: string;
    target_unit: string;
    work_director: string;
    status: string;
    category: StatusCategory;
    approval_label: string;
    approval_state: string;
    completed: number;
    total: number;
    percentage: number;
    age_days: number;
    target_date: string | null;
};

type PlanningBoardProps = {
    summary: {
        total: number;
        belumDiajukan: number;
        menungguPersetujuan: number;
        ditolak: number;
        disetujui: number;
        rataRataProgres: number;
        rataRataUsia: number;
        langkahDokumen: number;
        siapDiajukan: number;
    };
    sCurve: SCurveData;
    checklistBreakdown: ChecklistRow[];
    byStatus: { name: string; category: StatusCategory; total: number }[];
    byUnit: { name: string; total: number; percentage: number }[];
    byDirector: { name: string; total: number }[];
    monthlyIntake: ColumnPoint[];
    approvalComposition: DonutSlice[];
    rows: PlanningRow[];
    generatedAt: string;
};

export default function PlanningBoardPage({
    summary,
    sCurve,
    checklistBreakdown,
    byStatus,
    byUnit,
    byDirector,
    monthlyIntake,
    approvalComposition,
    rows,
    generatedAt,
}: PlanningBoardProps) {
    return (
        <PublicBoardShell
            title="Papan Monitoring Perencanaan"
            subtitle="Progres penyusunan dokumen perencanaan pengadaan"
            active="planning"
            generatedAt={generatedAt}
        >
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7">
                <StatCard
                    label="Dalam Perencanaan"
                    value={summary.total}
                    hint={`${summary.langkahDokumen} langkah dokumen`}
                    accent
                />
                <StatCard
                    label="Belum Diajukan"
                    value={summary.belumDiajukan}
                />
                <StatCard
                    label="Menunggu Persetujuan"
                    value={summary.menungguPersetujuan}
                />
                <StatCard label="Perlu Perbaikan" value={summary.ditolak} />
                <StatCard
                    label="Siap Diajukan"
                    value={summary.siapDiajukan}
                    hint="Checklist wajib lengkap"
                />
                <StatCard
                    label="Rata-rata Progres"
                    value={`${summary.rataRataProgres}%`}
                />
                <StatCard
                    label="Rata-rata Usia"
                    value={`${summary.rataRataUsia} hr`}
                    hint="Sejak pengadaan dibuat"
                />
            </div>

            <div className="grid gap-4 2xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <BoardPanel
                    title="Kurva S Perencanaan"
                    caption="Rencana vs realisasi kumulatif"
                    bodyClassName="p-4"
                >
                    <SCurveChart data={sCurve} />
                </BoardPanel>

                <BoardPanel title="Komposisi Persetujuan">
                    <DonutChart
                        slices={approvalComposition}
                        centerLabel="Status Pengajuan"
                    />
                </BoardPanel>
            </div>

            <div className="grid gap-4 xl:grid-cols-2 2xl:grid-cols-4">
                <BoardPanel
                    title="Kelengkapan Dokumen Perencanaan"
                    caption="% pengadaan yang sudah melengkapi"
                    className="2xl:col-span-2"
                >
                    {checklistBreakdown.length === 0 ? (
                        <EmptyState
                            title="Belum ada checklist"
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
                    title="Pengadaan Masuk per Bulan"
                    caption="12 bulan terakhir"
                >
                    <ColumnChart points={monthlyIntake} />
                </BoardPanel>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <BoardPanel
                    title="Perencanaan per Unit Tujuan"
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

                <BoardPanel title="Perencanaan per Direksi Pekerjaan">
                    <BarList
                        rows={byDirector.map((director) => ({
                            name: director.name,
                            total: director.total,
                        }))}
                        limit={10}
                    />
                </BoardPanel>
            </div>

            <BoardPanel
                title="Detail Pengadaan Tahap Perencanaan"
                caption={`${rows.length} pengadaan`}
            >
                {rows.length === 0 ? (
                    <EmptyState
                        title="Tidak ada pengadaan pada tahap perencanaan"
                        description="Seluruh pengadaan sudah disetujui, selesai, atau dibatalkan."
                    />
                ) : (
                    <Table>
                        <TableHeader className="bg-muted/60">
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Pengadaan</TableHead>
                                <TableHead>Unit Tujuan</TableHead>
                                <TableHead>Direksi Pekerjaan</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Pengajuan</TableHead>
                                <TableHead className="w-48">
                                    Progres Dokumen
                                </TableHead>
                                <TableHead>Usia</TableHead>
                                <TableHead>Target</TableHead>
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
                                        <ApprovalBadge
                                            state={row.approval_state}
                                            label={row.approval_label}
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
                                        {row.age_days} hr
                                    </TableCell>
                                    <TableCell className="tabular whitespace-nowrap">
                                        {formatDate(row.target_date)}
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
