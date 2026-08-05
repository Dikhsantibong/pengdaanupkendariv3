import { EmptyState } from '@/components/empty-state';

export type ColumnPoint = {
    label: string;
    total: number;
};

/**
 * Compact monthly volume chart. Values sit above each column so the numbers
 * stay readable from a distance.
 */
export function ColumnChart({ points }: { points: ColumnPoint[] }) {
    if (points.length === 0) {
        return <EmptyState title="Belum ada data" className="py-8" />;
    }

    const max = Math.max(1, ...points.map((point) => point.total));

    return (
        <div className="flex h-44 items-end gap-1.5 px-4 pt-6 pb-3">
            {points.map((point) => (
                <div
                    key={point.label}
                    className="flex min-w-0 flex-1 flex-col items-center gap-1.5"
                >
                    <span className="tabular text-[11px] font-semibold text-foreground">
                        {point.total > 0 ? point.total : ''}
                    </span>
                    <div
                        className="w-full rounded-t-sm bg-primary/85 transition-[height] duration-700"
                        style={{
                            height: `${Math.max(2, (point.total / max) * 100)}%`,
                        }}
                        title={`${point.label}: ${point.total}`}
                    />
                    <span className="w-full truncate text-center text-[10px] text-muted-foreground">
                        {point.label}
                    </span>
                </div>
            ))}
        </div>
    );
}
