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

const HEIGHT = 260;
const PADDING = { top: 12, right: 12, bottom: 28, left: 56 };
const TICKS = 4;

/**
 * Lines over months with a light fill under each, a value axis, a guide
 * line and tooltip under the pointer, and series toggles.
 */
export function AreaChart({
    labels,
    series,
    format,
    axisFormat = format,
    emptyText,
}: {
    labels: string[];
    series: ChartSeries[];
    format: (value: number) => string;
    axisFormat?: (value: number) => string;
    emptyText?: string;
}) {
    const [ref, width] = useElementWidth<HTMLDivElement>();
    const [hidden, setHidden] = useState<Set<string>>(new Set());
    const [active, setActive] = useState<number | null>(null);

    const hasData = series.some((entry) => entry.values.some((v) => v > 0));

    if (!hasData || labels.length === 0) {
        return <ChartEmpty description={emptyText} />;
    }

    const visible = series.filter((entry) => !hidden.has(entry.key));
    const max = niceMax(
        Math.max(0, ...visible.flatMap((entry) => entry.values)),
    );

    const plotWidth = Math.max(0, width - PADDING.left - PADDING.right);
    const plotHeight = HEIGHT - PADDING.top - PADDING.bottom;
    const step = labels.length > 1 ? plotWidth / (labels.length - 1) : 0;
    const x = (index: number) =>
        PADDING.left + (labels.length > 1 ? index * step : plotWidth / 2);
    const y = (value: number) =>
        PADDING.top + plotHeight - (value / max) * plotHeight;
    const labelEvery = Math.max(1, Math.ceil(52 / Math.max(1, step)));

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

    const pointerAt = (clientX: number, left: number) => {
        if (labels.length === 1) {
            return 0;
        }

        const index = Math.round((clientX - left - PADDING.left) / step);

        return Math.min(labels.length - 1, Math.max(0, index));
    };

    return (
        <div className="space-y-3">
            <div ref={ref} className="relative">
                {width > 0 && (
                    <svg
                        width={width}
                        height={HEIGHT}
                        role="img"
                        aria-label="Grafik area"
                        onMouseMove={(event) =>
                            setActive(
                                pointerAt(
                                    event.clientX,
                                    event.currentTarget.getBoundingClientRect()
                                        .left,
                                ),
                            )
                        }
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
                                        {(axisFormat ?? format)(value)}
                                    </text>
                                </g>
                            );
                        })}

                        {labels.map(
                            (label, index) =>
                                index % labelEvery === 0 && (
                                    <text
                                        key={label}
                                        x={x(index)}
                                        y={HEIGHT - 8}
                                        textAnchor="middle"
                                        className="fill-muted-foreground text-[10px]"
                                    >
                                        {label}
                                    </text>
                                ),
                        )}

                        {visible.map((entry) => {
                            const line = entry.values
                                .map(
                                    (value, index) =>
                                        `${index === 0 ? 'M' : 'L'}${x(index)},${y(value)}`,
                                )
                                .join(' ');
                            const area = `${line} L${x(entry.values.length - 1)},${y(0)} L${x(0)},${y(0)} Z`;

                            return (
                                <g key={entry.key}>
                                    <path
                                        d={area}
                                        fill={entry.color}
                                        fillOpacity={0.1}
                                    />
                                    <path
                                        d={line}
                                        fill="none"
                                        stroke={entry.color}
                                        strokeWidth={2}
                                        strokeLinejoin="round"
                                    />
                                </g>
                            );
                        })}

                        {active !== null && (
                            <g>
                                <line
                                    x1={x(active)}
                                    x2={x(active)}
                                    y1={PADDING.top}
                                    y2={PADDING.top + plotHeight}
                                    className="stroke-muted-foreground/40"
                                />
                                {visible.map((entry) => (
                                    <circle
                                        key={entry.key}
                                        cx={x(active)}
                                        cy={y(entry.values[active] ?? 0)}
                                        r={3.5}
                                        fill={entry.color}
                                        className="stroke-card"
                                        strokeWidth={1.5}
                                    />
                                ))}
                            </g>
                        )}
                    </svg>
                )}

                {active !== null && (
                    <ChartTooltip
                        x={x(active)}
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
