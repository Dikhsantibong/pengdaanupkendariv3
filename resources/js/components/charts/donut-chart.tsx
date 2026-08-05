import { EmptyState } from '@/components/empty-state';
import { cn } from '@/lib/utils';
import type { StatusCategory } from '@/types';

export type DonutSlice = {
    label: string;
    total: number;
    category: StatusCategory;
};

const sliceStroke: Record<StatusCategory, string> = {
    pending: 'stroke-status-pending',
    batal: 'stroke-status-batal',
    berjalan: 'stroke-status-berjalan',
    selesai: 'stroke-status-selesai',
};

const legendDot: Record<StatusCategory, string> = {
    pending: 'bg-status-pending',
    batal: 'bg-status-batal',
    berjalan: 'bg-status-berjalan',
    selesai: 'bg-status-selesai',
};

const RADIUS = 60;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * Composition donut drawn with stroke-dasharray segments on a single circle.
 */
export function DonutChart({
    slices,
    centerLabel,
}: {
    slices: DonutSlice[];
    centerLabel?: string;
}) {
    const total = slices.reduce((sum, slice) => sum + slice.total, 0);

    if (total === 0) {
        return <EmptyState title="Belum ada data" className="py-8" />;
    }

    // Pre-compute each slice's arc so nothing is mutated while rendering.
    const segments = slices.reduce<
        { slice: DonutSlice; length: number; offset: number }[]
    >((accumulated, slice) => {
        const previous = accumulated[accumulated.length - 1];
        const offset =
            previous === undefined ? 0 : previous.offset + previous.length;

        return [
            ...accumulated,
            { slice, length: (slice.total / total) * CIRCUMFERENCE, offset },
        ];
    }, []);

    return (
        <div className="flex flex-wrap items-center gap-6 px-4 py-4">
            <svg
                viewBox="0 0 160 160"
                className="size-36 shrink-0 -rotate-90"
                role="img"
                aria-label="Komposisi"
            >
                <circle
                    cx={80}
                    cy={80}
                    r={RADIUS}
                    fill="none"
                    strokeWidth={20}
                    className="stroke-muted"
                />
                {segments.map(({ slice, length, offset }) =>
                    slice.total === 0 ? null : (
                        <circle
                            key={slice.label}
                            cx={80}
                            cy={80}
                            r={RADIUS}
                            fill="none"
                            strokeWidth={20}
                            strokeDasharray={`${length} ${CIRCUMFERENCE - length}`}
                            strokeDashoffset={-offset}
                            className={sliceStroke[slice.category]}
                        />
                    ),
                )}
            </svg>

            <div className="min-w-0 flex-1 space-y-1.5">
                {centerLabel !== undefined && (
                    <p className="section-label">{centerLabel}</p>
                )}
                <ul className="space-y-1.5">
                    {slices.map((slice) => (
                        <li
                            key={slice.label}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <span className="flex min-w-0 items-center gap-2">
                                <span
                                    className={cn(
                                        'size-2 shrink-0 rounded-full',
                                        legendDot[slice.category],
                                    )}
                                    aria-hidden
                                />
                                <span className="truncate">{slice.label}</span>
                            </span>
                            <span className="tabular flex shrink-0 items-baseline gap-1.5">
                                <span className="font-semibold">
                                    {slice.total}
                                </span>
                                <span className="text-muted-foreground">
                                    &middot;{' '}
                                    {Math.round((slice.total / total) * 100)}%
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
