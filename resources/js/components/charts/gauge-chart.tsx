import { VIZ } from '@/components/charts/chart-kit';

const WIDTH = 220;
const HEIGHT = 124;
const STROKE = 18;
const RADIUS = (WIDTH - STROKE) / 2 - 4;

/**
 * A half-circle meter from 0 to 100 percent.
 */
export function GaugeChart({
    percent,
    label,
    color = VIZ.blue,
}: {
    percent: number;
    label: string;
    color?: string;
}) {
    const value = Math.min(100, Math.max(0, percent));
    const length = Math.PI * RADIUS;
    const cx = WIDTH / 2;
    const cy = HEIGHT - 12;
    const depth = 6;
    const arc = `M${cx - RADIUS},${cy} A${RADIUS},${RADIUS} 0 0 1 ${cx + RADIUS},${cy}`;
    const backArc = `M${cx - RADIUS},${cy + depth} A${RADIUS},${RADIUS} 0 0 1 ${cx + RADIUS},${cy + depth}`;
    const angle = Math.PI * (1 - value / 100);
    const tipX = cx + RADIUS * Math.cos(angle);
    const tipY = cy - RADIUS * Math.sin(angle);

    return (
        <div className="relative mx-auto w-full max-w-60">
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="w-full drop-shadow-[0_4px_6px_rgba(0,0,0,0.12)]"
                role="img"
                aria-label={`${label}: ${value}%`}
            >
                {/* 3D extruded bottom track */}
                <path
                    d={backArc}
                    fill="none"
                    strokeWidth={STROKE}
                    className="stroke-muted-foreground/25"
                />
                {/* Main background track */}
                <path
                    d={arc}
                    fill="none"
                    strokeWidth={STROKE}
                    className="stroke-muted"
                />
                {/* 3D extruded bottom progress */}
                <path
                    d={backArc}
                    fill="none"
                    strokeWidth={STROKE}
                    strokeDasharray={`${(value / 100) * length} ${length}`}
                    style={{
                        stroke: `color-mix(in srgb, ${color} 60%, black)`,
                    }}
                />
                {/* Front progress face */}
                <path
                    d={arc}
                    fill="none"
                    stroke={color}
                    strokeWidth={STROKE}
                    strokeDasharray={`${(value / 100) * length} ${length}`}
                />
                {/* Subtle 3D specular highlight rim */}
                <path
                    d={arc}
                    fill="none"
                    strokeWidth={3}
                    strokeDasharray={`${(value / 100) * length} ${length}`}
                    style={{
                        stroke: `color-mix(in srgb, ${color} 55%, white)`,
                    }}
                    opacity={0.7}
                />
                {/* 3D end cap marker */}
                {value > 0 && (
                    <circle
                        cx={tipX}
                        cy={tipY}
                        r={4.5}
                        fill="var(--card)"
                        stroke={color}
                        strokeWidth={2.5}
                    />
                )}
                <text
                    x={cx - RADIUS}
                    y={HEIGHT}
                    textAnchor="middle"
                    className="fill-muted-foreground text-[9px]"
                >
                    0
                </text>
                <text
                    x={cx + RADIUS}
                    y={HEIGHT}
                    textAnchor="middle"
                    className="fill-muted-foreground text-[9px]"
                >
                    100
                </text>
            </svg>
            <div className="absolute inset-x-0 bottom-1 flex flex-col items-center">
                <span className="tabular text-3xl leading-none font-semibold text-foreground">
                    {value.toLocaleString('id-ID', {
                        maximumFractionDigits: 1,
                    })}
                    %
                </span>
                <span className="mt-1 text-[0.6875rem] text-muted-foreground">
                    {label}
                </span>
            </div>
        </div>
    );
}
