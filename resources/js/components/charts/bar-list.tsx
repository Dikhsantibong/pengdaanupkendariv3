import { EmptyState } from '@/components/empty-state';
import { cn } from '@/lib/utils';
import type { StatusCategory } from '@/types';

export type BarListRow = {
    name: string;
    total: number;
    percentage?: number;
    category?: StatusCategory;
    caption?: string;
};

const categoryBar: Record<StatusCategory, string> = {
    pending: 'bg-status-pending',
    batal: 'bg-status-batal',
    berjalan: 'bg-status-berjalan',
    selesai: 'bg-status-selesai',
};

/**
 * Horizontal bars keyed by count or by percentage. Long Indonesian labels read
 * better horizontally than in a column chart.
 */
export function BarList({
    rows,
    mode = 'count',
    limit,
    emptyTitle = 'Belum ada data',
}: {
    rows: BarListRow[];
    mode?: 'count' | 'percentage';
    limit?: number;
    emptyTitle?: string;
}) {
    if (rows.length === 0) {
        return <EmptyState title={emptyTitle} className="py-8" />;
    }

    const visible = limit === undefined ? rows : rows.slice(0, limit);
    const maxTotal = Math.max(1, ...rows.map((row) => row.total));

    return (
        <ul className="divide-y divide-border">
            {visible.map((row) => {
                const width =
                    mode === 'percentage'
                        ? (row.percentage ?? 0)
                        : (row.total / maxTotal) * 100;

                return (
                    <li key={row.name} className="px-4 py-2">
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="truncate">{row.name}</span>
                            <span className="tabular flex shrink-0 items-baseline gap-1.5">
                                <span className="font-semibold">
                                    {mode === 'percentage'
                                        ? `${row.percentage ?? 0}%`
                                        : row.total}
                                </span>
                                {row.caption !== undefined && (
                                    <span className="text-muted-foreground">
                                        &middot; {row.caption}
                                    </span>
                                )}
                            </span>
                        </div>
                        <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn(
                                    'h-full transition-[width] duration-700',
                                    row.category
                                        ? categoryBar[row.category]
                                        : 'bg-primary',
                                )}
                                style={{ width: `${width}%` }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
