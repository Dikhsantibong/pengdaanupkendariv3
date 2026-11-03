import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** One coloured series of a chart. */
export type ChartSeries = {
    key: string;
    label: string;
    /** A CSS colour, usually one of the `--viz-*` tokens. */
    color: string;
    values: number[];
};

/** The dashboard palette, read from the theme so dark mode follows. */
export const VIZ = {
    blue: 'var(--viz-blue)',
    navy: 'var(--viz-navy)',
    sky: 'var(--viz-sky)',
    pale: 'var(--viz-pale)',
    yellow: 'var(--viz-yellow)',
    green: 'var(--viz-green)',
    red: 'var(--viz-red)',
    grey: 'var(--viz-grey)',
    grid: 'var(--viz-grid)',
} as const;

/** Colours for categories without a meaning of their own. */
export const CATEGORICAL = [VIZ.blue, VIZ.navy, VIZ.sky, VIZ.pale, VIZ.grey];

/**
 * The rendered width of an element, kept current as the layout changes, so
 * SVG charts draw at their real size and their text stays crisp.
 */
export function useElementWidth<T extends HTMLElement>() {
    const ref = useRef<T>(null);
    const [width, setWidth] = useState(0);

    useEffect(() => {
        const element = ref.current;

        if (element === null) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setWidth(Math.floor(entry.contentRect.width)),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return [ref, width] as const;
}

/**
 * A round upper bound for an axis, so its ticks fall on even numbers.
 */
export function niceMax(value: number): number {
    if (value <= 0) {
        return 4;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const step = [1, 2, 2.5, 5, 10].find(
        (factor) => factor * magnitude >= value,
    );

    return (step ?? 10) * magnitude;
}

/**
 * Series toggles under a chart: clicking one hides or shows it.
 */
export function ChartLegend({
    series,
    hidden,
    onToggle,
}: {
    series: Pick<ChartSeries, 'key' | 'label' | 'color'>[];
    hidden?: Set<string>;
    onToggle?: (key: string) => void;
}) {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1.5">
            {series.map((entry) => {
                const isHidden = hidden?.has(entry.key) ?? false;

                return (
                    <li key={entry.key}>
                        <button
                            type="button"
                            onClick={() => onToggle?.(entry.key)}
                            disabled={onToggle === undefined}
                            aria-pressed={!isHidden}
                            className={cn(
                                'flex items-center gap-1.5 text-xs text-muted-foreground transition-opacity enabled:hover:text-foreground',
                                isHidden && 'opacity-40',
                            )}
                        >
                            <span
                                className="size-2.5 shrink-0 rounded-[2px]"
                                style={{ backgroundColor: entry.color }}
                                aria-hidden
                            />
                            {entry.label}
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * The floating box naming the values under the pointer.
 */
export function ChartTooltip({
    x,
    y,
    containerWidth,
    title,
    rows,
}: {
    x: number;
    y: number;
    containerWidth: number;
    title: ReactNode;
    rows: { key: string; label: string; color: string; value: string }[];
}) {
    // Keep the box inside the chart: flip to the left near the right edge.
    const flip = x > containerWidth - 190;

    return (
        <div
            role="status"
            className="pointer-events-none absolute z-10 min-w-40 rounded-md border border-border bg-popover px-3 py-2 text-xs shadow-sm"
            style={{
                left: flip ? undefined : x + 12,
                right: flip ? containerWidth - x + 12 : undefined,
                top: Math.max(0, y),
            }}
        >
            <p className="mb-1 font-semibold text-foreground">{title}</p>
            <ul className="space-y-0.5">
                {rows.map((row) => (
                    <li
                        key={row.key}
                        className="flex items-center justify-between gap-4"
                    >
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                            <span
                                className="size-2 rounded-[2px]"
                                style={{ backgroundColor: row.color }}
                                aria-hidden
                            />
                            {row.label}
                        </span>
                        <span className="tabular font-semibold text-foreground">
                            {row.value}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * What a chart shows when it has nothing to draw.
 */
export function ChartEmpty({
    title = 'Belum ada data',
    description = 'Belum ada pengadaan yang sesuai dengan filter ini.',
    className,
}: {
    title?: string;
    description?: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex min-h-40 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border px-4 py-8 text-center',
                className,
            )}
        >
            <p className="text-sm font-medium text-foreground">{title}</p>
            <p className="max-w-xs text-xs text-muted-foreground">
                {description}
            </p>
        </div>
    );
}
