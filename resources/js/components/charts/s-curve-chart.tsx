import { EmptyState } from '@/components/empty-state';

export type SCurvePoint = {
    label: string;
    date: string;
    rencana: number;
    realisasi: number | null;
};

export type SCurveData = {
    points: SCurvePoint[];
    totalItems: number;
    includedCount: number;
    excludedCount: number;
    currentPlan: number;
    currentActual: number;
    deviation: number;
};

const WIDTH = 1000;
const HEIGHT = 320;
const PADDING = { top: 16, right: 20, bottom: 34, left: 44 };

const plotWidth = WIDTH - PADDING.left - PADDING.right;
const plotHeight = HEIGHT - PADDING.top - PADDING.bottom;

/**
 * Cumulative plan vs actual progress ("kurva S") drawn as inline SVG so the
 * board stays dependency free and scales cleanly on a wall display.
 */
export function SCurveChart({ data }: { data: SCurveData }) {
    if (data.points.length === 0) {
        return (
            <EmptyState
                title="Kurva S belum dapat dihitung"
                description="Kurva memerlukan pengadaan yang sudah memiliki target penyelesaian dan checklist tahap ini."
            />
        );
    }

    const x = (index: number) =>
        PADDING.left +
        (data.points.length === 1
            ? plotWidth / 2
            : (index / (data.points.length - 1)) * plotWidth);

    const y = (value: number) =>
        PADDING.top +
        plotHeight -
        (Math.min(100, Math.max(0, value)) / 100) * plotHeight;

    const planPath = data.points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'}${x(index)},${y(point.rencana)}`,
        )
        .join(' ');

    const actualPoints = data.points
        .map((point, index) => ({ point, index }))
        .filter((entry) => entry.point.realisasi !== null);

    const actualPath = actualPoints
        .map(
            (entry, position) =>
                `${position === 0 ? 'M' : 'L'}${x(entry.index)},${y(entry.point.realisasi ?? 0)}`,
        )
        .join(' ');

    const actualArea =
        actualPoints.length > 1
            ? `${actualPath} L${x(actualPoints[actualPoints.length - 1].index)},${y(0)} L${x(actualPoints[0].index)},${y(0)} Z`
            : '';

    const lastActual = actualPoints[actualPoints.length - 1];
    const isBehind = data.deviation < 0;

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-end gap-x-8 gap-y-2">
                <Readout label="Rencana" value={data.currentPlan} tone="plan" />
                <Readout
                    label="Realisasi"
                    value={data.currentActual}
                    tone="actual"
                />
                <div>
                    <p className="section-label">Deviasi</p>
                    <p
                        className={
                            isBehind
                                ? 'tabular text-2xl leading-tight font-semibold text-status-batal'
                                : 'tabular text-2xl leading-tight font-semibold text-status-selesai'
                        }
                    >
                        {data.deviation > 0 ? '+' : ''}
                        {data.deviation.toFixed(1)}%
                    </p>
                </div>
                <p className="ml-auto text-xs text-muted-foreground">
                    {data.includedCount} pengadaan · {data.totalItems} item
                    checklist
                    {data.excludedCount > 0
                        ? ` · ${data.excludedCount} tanpa target penyelesaian tidak diplot`
                        : ''}
                </p>
            </div>

            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="h-auto w-full"
                role="img"
                aria-label="Kurva S rencana dan realisasi"
                preserveAspectRatio="none"
            >
                {[0, 25, 50, 75, 100].map((tick) => (
                    <g key={tick}>
                        <line
                            x1={PADDING.left}
                            x2={WIDTH - PADDING.right}
                            y1={y(tick)}
                            y2={y(tick)}
                            className="stroke-border"
                            strokeWidth={1}
                        />
                        <text
                            x={PADDING.left - 8}
                            y={y(tick) + 4}
                            textAnchor="end"
                            className="fill-muted-foreground text-[11px]"
                        >
                            {tick}%
                        </text>
                    </g>
                ))}

                {data.points.map((point, index) =>
                    index % 3 === 0 || index === data.points.length - 1 ? (
                        <text
                            key={point.date}
                            x={x(index)}
                            y={HEIGHT - 12}
                            textAnchor="middle"
                            className="fill-muted-foreground text-[11px]"
                        >
                            {point.label}
                        </text>
                    ) : null,
                )}

                {actualArea !== '' && (
                    <path d={actualArea} className="fill-primary/12" />
                )}

                <path
                    d={planPath}
                    fill="none"
                    strokeWidth={2}
                    strokeDasharray="6 5"
                    className="stroke-muted-foreground"
                />

                {actualPath !== '' && (
                    <path
                        d={actualPath}
                        fill="none"
                        strokeWidth={3}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="stroke-primary"
                    />
                )}

                {lastActual && (
                    <circle
                        cx={x(lastActual.index)}
                        cy={y(lastActual.point.realisasi ?? 0)}
                        r={5}
                        className="fill-primary stroke-card"
                        strokeWidth={2}
                    />
                )}
            </svg>

            <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                <span className="flex items-center gap-2">
                    <span className="h-0 w-6 border-t-2 border-dashed border-muted-foreground" />
                    Rencana kumulatif
                </span>
                <span className="flex items-center gap-2">
                    <span className="h-0.5 w-6 rounded-full bg-primary" />
                    Realisasi kumulatif
                </span>
            </div>
        </div>
    );
}

function Readout({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: 'plan' | 'actual';
}) {
    return (
        <div>
            <p className="section-label">{label}</p>
            <p
                className={
                    tone === 'actual'
                        ? 'tabular text-2xl leading-tight font-semibold text-primary'
                        : 'tabular text-2xl leading-tight font-semibold text-foreground'
                }
            >
                {value.toFixed(1)}%
            </p>
        </div>
    );
}
