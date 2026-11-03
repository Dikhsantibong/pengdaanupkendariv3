import { useState } from 'react';
import {
    ChartEmpty,
    ChartLegend,
    ChartTooltip,
    niceMax,
    useElementWidth,
    VIZ,
} from '@/components/charts/chart-kit';
import type { ChartSeries } from '@/components/charts/chart-kit';
import { formatNumber } from '@/lib/format';

const HEIGHT = 260;
const PADDING = { top: 12, right: 8, bottom: 28, left: 40 };
const TICKS = 4;

/**
 * Grouped columns per category (month), one column per series, with a
 * value axis, hover tooltip and series toggles.
 */
export function GroupedColumnChart({
    labels,
    series,
    format = formatNumber,
    emptyText,
}: {
    labels: string[];
    series: ChartSeries[];
    format?: (value: number) => string;
    emptyText?: string;
}) {
    const [ref, width] = useElementWidth<HTMLDivElement>();
    const [hidden, setHidden] = useState<Set<string>>(new Set());
    const [active, setActive] = useState<number | null>(null);

    const hasData = series.some((entry) => entry.values.some((v) => v > 0));

    if (!hasData) {
        return <ChartEmpty description={emptyText} />;
    }

    const visible = series.filter((entry) => !hidden.has(entry.key));
    const max = niceMax(
        Math.max(0, ...visible.flatMap((entry) => entry.values)),
    );

    const plotWidth = Math.max(0, width - PADDING.left - PADDING.right);
    const plotHeight = HEIGHT - PADDING.top - PADDING.bottom;
    const groupWidth = labels.length > 0 ? plotWidth / labels.length : 0;
    const barWidth = Math.max(
        2,
        Math.min(16, (groupWidth * 0.72) / Math.max(1, visible.length)),
    );
    const y = (value: number) =>
        PADDING.top + plotHeight - (value / max) * plotHeight;

    // Thin the month labels out when they would run into each other.
    const labelEvery = Math.max(1, Math.ceil(46 / Math.max(1, groupWidth)));

    const toggle = (key: string) =>
        setHidden((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else if (next.size < series.length - 1) {
                next.add(key);
            }

            return next;
        });

    return (
        <div className="space-y-3">
            <div ref={ref} className="relative">
                {width > 0 && (
                    <svg
                        width={width}
                        height={HEIGHT}
                        role="img"
                        aria-label="Grafik kolom"
                        onMouseLeave={() => setActive(null)}
                    >
                        {Array.from({ length: TICKS + 1 }, (_, index) => {
                            const value = (max / TICKS) * index;

                            return (
                                <g key={index}>
                                    <line
                                        x1={PADDING.left}
                                        x2={width - PADDING.right}
                                        y1={y(value)}
                                        y2={y(value)}
                                        stroke={VIZ.grid}
                                        strokeDasharray={
                                            index === 0 ? undefined : '3 3'
                                        }
                                    />
                                    <text
                                        x={PADDING.left - 8}
                                        y={y(value)}
                                        dy="0.32em"
                                        textAnchor="end"
                                        className="fill-muted-foreground text-[10px] tabular-nums"
                                    >
                                        {format(value)}
                                    </text>
                                </g>
                            );
                        })}

                        {labels.map((label, index) => {
                            const groupX = PADDING.left + index * groupWidth;
                            const start =
                                groupX +
                                (groupWidth - barWidth * visible.length) / 2;

                            return (
                                <g key={label}>
                                    {active === index && (
                                        <rect
                                            x={groupX}
                                            y={PADDING.top}
                                            width={groupWidth}
                                            height={plotHeight}
                                            className="fill-muted"
                                        />
                                    )}
                                    {visible.map((entry, position) => {
                                        const value = entry.values[index] ?? 0;

                                        return (
                                            <rect
                                                key={entry.key}
                                                x={start + position * barWidth}
                                                y={y(value)}
                                                width={Math.max(
                                                    1,
                                                    barWidth - 1.5,
                                                )}
                                                height={
                                                    PADDING.top +
                                                    plotHeight -
                                                    y(value)
                                                }
                                                rx={1.5}
                                                fill={entry.color}
                                            />
                                        );
                                    })}
                                    {index % labelEvery === 0 && (
                                        <text
                                            x={groupX + groupWidth / 2}
                                            y={HEIGHT - 8}
                                            textAnchor="middle"
                                            className="fill-muted-foreground text-[10px]"
                                        >
                                            {label}
                                        </text>
                                    )}
                                    <rect
                                        x={groupX}
                                        y={PADDING.top}
                                        width={groupWidth}
                                        height={plotHeight}
                                        fill="transparent"
                                        onMouseEnter={() => setActive(index)}
                                    />
                                </g>
                            );
                        })}
                    </svg>
                )}

                {active !== null && (
                    <ChartTooltip
                        x={PADDING.left + (active + 0.5) * groupWidth}
                        y={PADDING.top}
                        containerWidth={width}
                        title={labels[active]}
                        rows={visible.map((entry) => ({
                            key: entry.key,
                            label: entry.label,
                            color: entry.color,
                            value: format(entry.values[active] ?? 0),
                        }))}
                    />
                )}
            </div>

            <ChartLegend series={series} hidden={hidden} onToggle={toggle} />
        </div>
    );
}
