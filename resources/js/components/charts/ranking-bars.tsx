import { ChartEmpty, ChartLegend, VIZ } from '@/components/charts/chart-kit';
import { formatNumber } from '@/lib/format';

export type RankingRow = { label: string; total: number };

export type PairedRow = { label: string; planner: number; executor: number };

/**
 * Horizontal bars ranked from the highest count, each with its share of
 * the total. Long names read better across than under a column.
 */
export function RankingBars({
    rows,
    limit = 8,
    color = VIZ.blue,
    unit = 'pengadaan',
    emptyText,
}: {
    rows: RankingRow[];
    limit?: number;
    color?: string;
    unit?: string;
    emptyText?: string;
}) {
    if (rows.length === 0) {
        return <ChartEmpty description={emptyText} />;
    }

    const sorted = [...rows].sort((a, b) => b.total - a.total);
    const shown = sorted.slice(0, limit);
    const rest = sorted.slice(limit);
    const total = rows.reduce((sum, row) => sum + row.total, 0);
    const max = Math.max(1, ...shown.map((row) => row.total));

    return (
        <div className="space-y-2.5">
            <ol className="space-y-2.5">
                {shown.map((row, index) => {
                    const share = total > 0 ? (row.total / total) * 100 : 0;

                    return (
                        <li
                            key={row.label}
                            className="group"
                            title={`${row.label}: ${formatNumber(row.total)} ${unit} (${share.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%)`}
                        >
                            <div className="flex items-baseline justify-between gap-3 text-sm">
                                <span className="flex min-w-0 items-baseline gap-2">
                                    <span className="tabular w-4 shrink-0 text-right text-xs text-muted-foreground">
                                        {index + 1}
                                    </span>
                                    <span className="truncate text-foreground">
                                        {row.label}
                                    </span>
                                </span>
                                <span className="tabular flex shrink-0 items-baseline gap-2">
                                    <span className="font-semibold">
                                        {formatNumber(row.total)}
                                    </span>
                                    <span className="w-11 text-right text-xs text-muted-foreground">
                                        {share.toLocaleString('id-ID', {
                                            maximumFractionDigits: 1,
                                        })}
                                        %
                                    </span>
                                </span>
                            </div>
                            <div className="mt-1 ml-6 h-2 overflow-hidden rounded-[2px] bg-muted">
                                <div
                                    className="h-full rounded-[2px] transition-opacity group-hover:opacity-80"
                                    style={{
                                        width: `${(row.total / max) * 100}%`,
                                        backgroundColor: color,
                                    }}
                                />
                            </div>
                        </li>
                    );
                })}
            </ol>
            {rest.length > 0 && (
                <p className="text-xs text-muted-foreground">
                    +{rest.length} lainnya (
                    {formatNumber(
                        rest.reduce((sum, row) => sum + row.total, 0),
                    )}{' '}
                    {unit})
                </p>
            )}
        </div>
    );
}

const PAIR = [
    { key: 'planner', label: 'PIC Perencana', color: VIZ.blue },
    { key: 'executor', label: 'PIC Pelaksana', color: VIZ.navy },
] as const;

/**
 * Two bars per person, planner and executor, on one scale so the counts
 * compare at a glance.
 */
export function PairedBars({
    rows,
    limit = 10,
    emptyText,
}: {
    rows: PairedRow[];
    limit?: number;
    emptyText?: string;
}) {
    if (rows.length === 0) {
        return <ChartEmpty description={emptyText} />;
    }

    const shown = rows.slice(0, limit);
    const max = Math.max(
        1,
        ...shown.flatMap((row) => [row.planner, row.executor]),
    );

    return (
        <div className="space-y-3">
            <ChartLegend series={[...PAIR]} />
            <ol className="space-y-2.5">
                {shown.map((row) => (
                    <li
                        key={row.label}
                        title={`${row.label}: ${row.planner} sebagai PIC Perencana, ${row.executor} sebagai PIC Pelaksana`}
                        className="grid grid-cols-[minmax(0,9rem)_minmax(0,1fr)] items-center gap-3 text-sm sm:grid-cols-[minmax(0,11rem)_minmax(0,1fr)]"
                    >
                        <span className="truncate text-foreground">
                            {row.label}
                        </span>
                        <span className="space-y-1">
                            {PAIR.map((series) => (
                                <span
                                    key={series.key}
                                    className="flex items-center gap-2"
                                >
                                    <span className="h-2 min-w-0 flex-1 overflow-hidden rounded-[2px] bg-muted">
                                        <span
                                            className="block h-full rounded-[2px]"
                                            style={{
                                                width: `${(row[series.key] / max) * 100}%`,
                                                backgroundColor: series.color,
                                            }}
                                        />
                                    </span>
                                    <span className="tabular w-6 shrink-0 text-right text-xs font-semibold">
                                        {row[series.key]}
                                    </span>
                                </span>
                            ))}
                        </span>
                    </li>
                ))}
            </ol>
            {rows.length > limit && (
                <p className="text-xs text-muted-foreground">
                    +{rows.length - limit} PIC lainnya
                </p>
            )}
        </div>
    );
}
