import { useState } from 'react';
import { ChartEmpty } from '@/components/charts/chart-kit';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export type RingSlice = {
    key: string;
    label: string;
    value: number;
    color: string;
};

const SIZE = 168;
const STROKE = 22;
const RADIUS = (SIZE - STROKE) / 2;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * A donut of shares with the total in its middle; hovering a slice or its
 * legend row puts that slice's figure in the middle instead.
 */
export function RingChart({
    slices,
    centerLabel = 'Total',
    onSelect,
    emptyText,
}: {
    slices: RingSlice[];
    centerLabel?: string;
    /** Makes the legend rows clickable, e.g. to filter by that slice. */
    onSelect?: (key: string) => void;
    emptyText?: string;
}) {
    const [active, setActive] = useState<string | null>(null);
    const total = slices.reduce((sum, slice) => sum + slice.value, 0);

    if (total === 0) {
        return <ChartEmpty description={emptyText} />;
    }

    const segments = slices.reduce<
        { slice: RingSlice; length: number; offset: number }[]
    >((accumulated, slice) => {
        const previous = accumulated[accumulated.length - 1];
        const offset =
            previous === undefined ? 0 : previous.offset + previous.length;

        return [
            ...accumulated,
            { slice, length: (slice.value / total) * CIRCUMFERENCE, offset },
        ];
    }, []);

    const percent = (value: number) =>
        `${((value / total) * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
    const focused = slices.find((slice) => slice.key === active);

    return (
        <div className="flex flex-col items-center gap-5 sm:flex-row sm:items-center">
            <div className="relative shrink-0">
                <svg
                    viewBox={`0 0 ${SIZE} ${SIZE}`}
                    className="size-40 -rotate-90"
                    role="img"
                    aria-label={`${centerLabel}: ${formatNumber(total)}`}
                    onMouseLeave={() => setActive(null)}
                >
                    <circle
                        cx={SIZE / 2}
                        cy={SIZE / 2}
                        r={RADIUS}
                        fill="none"
                        strokeWidth={STROKE}
                        className="stroke-muted"
                    />
                    {segments.map(({ slice, length, offset }) =>
                        slice.value === 0 ? null : (
                            <circle
                                key={slice.key}
                                cx={SIZE / 2}
                                cy={SIZE / 2}
                                r={RADIUS}
                                fill="none"
                                stroke={slice.color}
                                strokeWidth={
                                    active === slice.key ? STROKE + 4 : STROKE
                                }
                                strokeDasharray={`${Math.max(0, length - 1.5)} ${CIRCUMFERENCE - Math.max(0, length - 1.5)}`}
                                strokeDashoffset={-offset}
                                opacity={
                                    active === null || active === slice.key
                                        ? 1
                                        : 0.35
                                }
                                onMouseEnter={() => setActive(slice.key)}
                                className="transition-opacity"
                            />
                        ),
                    )}
                </svg>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span className="tabular text-2xl leading-none font-semibold text-foreground">
                        {formatNumber(focused?.value ?? total)}
                    </span>
                    <span className="mt-1 max-w-24 truncate text-[0.6875rem] text-muted-foreground">
                        {focused === undefined
                            ? centerLabel
                            : `${focused.label} · ${percent(focused.value)}`}
                    </span>
                </div>
            </div>

            <ul className="w-full min-w-0 flex-1 space-y-1">
                {slices.map((slice) => {
                    const content = (
                        <>
                            <span className="flex min-w-0 items-center gap-2">
                                <span
                                    className="size-2.5 shrink-0 rounded-[2px]"
                                    style={{ backgroundColor: slice.color }}
                                    aria-hidden
                                />
                                <span className="truncate">{slice.label}</span>
                            </span>
                            <span className="tabular flex shrink-0 items-baseline gap-2">
                                <span className="font-semibold text-foreground">
                                    {formatNumber(slice.value)}
                                </span>
                                <span className="w-12 text-right text-xs text-muted-foreground">
                                    {percent(slice.value)}
                                </span>
                            </span>
                        </>
                    );
                    const rowClass = cn(
                        'flex w-full items-center justify-between gap-3 rounded-sm px-1.5 py-1 text-left text-sm',
                        active === slice.key && 'bg-muted',
                    );

                    return (
                        <li
                            key={slice.key}
                            onMouseEnter={() => setActive(slice.key)}
                            onMouseLeave={() => setActive(null)}
                        >
                            {onSelect ? (
                                <button
                                    type="button"
                                    onClick={() => onSelect(slice.key)}
                                    className={cn(rowClass, 'hover:bg-muted')}
                                >
                                    {content}
                                </button>
                            ) : (
                                <div className={rowClass}>{content}</div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
