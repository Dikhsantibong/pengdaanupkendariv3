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
const CX = SIZE / 2;
const CY = 74;
const RX = 72;
const RY = 48;
const RX_IN = 44;
const RY_IN = 29;
const DEPTH = 18;

const pt = (cx: number, cy: number, rx: number, ry: number, angle: number) => ({
    x: cx + rx * Math.cos(angle),
    y: cy + ry * Math.sin(angle),
});

/** Intersect [start, end] with front (sin >= 0) or back (sin <= 0) half-planes. */
function arcIntervals(
    start: number,
    end: number,
    front: boolean,
): Array<[number, number]> {
    const out: Array<[number, number]> = [];

    for (let k = -1; k <= 1; k++) {
        const a = k * 2 * Math.PI + (front ? 0 : Math.PI);
        const b = a + Math.PI;
        const lo = Math.max(start, a);
        const hi = Math.min(end, b);

        if (hi - lo > 0.005) {
            out.push([lo, hi]);
        }
    }

    return out;
}

/** 3D extruded wall along an elliptical arc from angle `a` to `b`. */
function wallPath(
    cx: number,
    cy: number,
    rx: number,
    ry: number,
    a: number,
    b: number,
    depth: number,
): string {
    const p1 = pt(cx, cy, rx, ry, a);
    const p2 = pt(cx, cy, rx, ry, b);
    const p3 = pt(cx, cy + depth, rx, ry, b);
    const p4 = pt(cx, cy + depth, rx, ry, a);
    const large = b - a > Math.PI ? 1 : 0;

    return [
        `M ${p1.x.toFixed(2)} ${p1.y.toFixed(2)}`,
        `A ${rx} ${ry} 0 ${large} 1 ${p2.x.toFixed(2)} ${p2.y.toFixed(2)}`,
        `L ${p3.x.toFixed(2)} ${p3.y.toFixed(2)}`,
        `A ${rx} ${ry} 0 ${large} 0 ${p4.x.toFixed(2)} ${p4.y.toFixed(2)}`,
        'Z',
    ].join(' ');
}

/** Annular top face path for an elliptical donut sector. */
function sectorPath(
    cx: number,
    cy: number,
    start: number,
    end: number,
): string {
    if (end - start >= Math.PI * 1.999) {
        const mid = start + Math.PI;

        return `${sectorPath(cx, cy, start, mid)} ${sectorPath(cx, cy, mid, end)}`;
    }

    const o1 = pt(cx, cy, RX, RY, start);
    const o2 = pt(cx, cy, RX, RY, end);
    const i2 = pt(cx, cy, RX_IN, RY_IN, end);
    const i1 = pt(cx, cy, RX_IN, RY_IN, start);
    const large = end - start > Math.PI ? 1 : 0;

    return [
        `M ${o1.x.toFixed(2)} ${o1.y.toFixed(2)}`,
        `A ${RX} ${RY} 0 ${large} 1 ${o2.x.toFixed(2)} ${o2.y.toFixed(2)}`,
        `L ${i2.x.toFixed(2)} ${i2.y.toFixed(2)}`,
        `A ${RX_IN} ${RY_IN} 0 ${large} 0 ${i1.x.toFixed(2)} ${i1.y.toFixed(2)}`,
        'Z',
    ].join(' ');
}

/**
 * A 3D tilted donut of shares with the total in its middle; hovering a slice
 * or its legend row puts that slice's figure in the middle instead.
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
        { slice: RingSlice; start: number; end: number; mid: number }[]
    >((accumulated, slice) => {
        const previous = accumulated[accumulated.length - 1];
        const start = previous === undefined ? -Math.PI / 2 : previous.end;
        const sweep = (slice.value / total) * Math.PI * 2;
        const end = start + sweep;

        return [
            ...accumulated,
            { slice, start, end, mid: start + sweep / 2 },
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
                    className="size-40 drop-shadow-[0_6px_10px_rgba(0,0,0,0.14)]"
                    role="img"
                    aria-label={`${centerLabel}: ${formatNumber(total)}`}
                    onMouseLeave={() => setActive(null)}
                >
                    {/* Back inner walls (visible through the 3D donut hole) */}
                    {segments.map(({ slice, start, end }) => {
                        if (slice.value === 0) {
                            return null;
                        }

                        const isFocused = active === slice.key;
                        const cy = isFocused ? CY - 3 : CY;
                        const opacity =
                            active === null || isFocused ? 1 : 0.35;

                        return arcIntervals(start, end, false).map(([a, b], i) => (
                            <path
                                key={`${slice.key}-in-${i}`}
                                d={wallPath(CX, cy, RX_IN, RY_IN, a, b, DEPTH)}
                                style={{
                                    fill: `color-mix(in srgb, ${slice.color} 74%, black)`,
                                }}
                                opacity={opacity}
                                onMouseEnter={() => setActive(slice.key)}
                                onClick={() => onSelect?.(slice.key)}
                                className={cn(
                                    'transition-opacity',
                                    onSelect && 'cursor-pointer',
                                )}
                            />
                        ));
                    })}

                    {/* Front outer 3D cylinder walls */}
                    {segments.map(({ slice, start, end }) => {
                        if (slice.value === 0) {
                            return null;
                        }

                        const isFocused = active === slice.key;
                        const cy = isFocused ? CY - 3 : CY;
                        const opacity =
                            active === null || isFocused ? 1 : 0.35;

                        return arcIntervals(start, end, true).map(([a, b], i) => (
                            <path
                                key={`${slice.key}-out-${i}`}
                                d={wallPath(CX, cy, RX, RY, a, b, DEPTH)}
                                style={{
                                    fill: `color-mix(in srgb, ${slice.color} 64%, black)`,
                                }}
                                opacity={opacity}
                                onMouseEnter={() => setActive(slice.key)}
                                onClick={() => onSelect?.(slice.key)}
                                className={cn(
                                    'transition-opacity',
                                    onSelect && 'cursor-pointer',
                                )}
                            />
                        ));
                    })}

                    {/* Top 3D faces */}
                    {segments.map(({ slice, start, end }) => {
                        if (slice.value === 0) {
                            return null;
                        }

                        const isFocused = active === slice.key;
                        const cy = isFocused ? CY - 3 : CY;
                        const opacity =
                            active === null || isFocused ? 1 : 0.35;

                        return (
                            <path
                                key={slice.key}
                                d={sectorPath(CX, cy, start, end)}
                                fill={slice.color}
                                stroke="var(--card)"
                                strokeWidth={1.25}
                                opacity={opacity}
                                onMouseEnter={() => setActive(slice.key)}
                                onClick={() => onSelect?.(slice.key)}
                                className={cn(
                                    'transition-opacity',
                                    onSelect && 'cursor-pointer',
                                )}
                            />
                        );
                    })}
                </svg>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center pb-2 text-center">
                    <span className="tabular text-xl leading-none font-semibold text-foreground">
                        {formatNumber(focused?.value ?? total)}
                    </span>
                    <span className="mt-1 max-w-20 truncate text-[0.625rem] text-muted-foreground">
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
