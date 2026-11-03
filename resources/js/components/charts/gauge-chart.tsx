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
    const cy = HEIGHT - 8;
    const arc = `M${cx - RADIUS},${cy} A${RADIUS},${RADIUS} 0 0 1 ${cx + RADIUS},${cy}`;

    return (
        <div className="relative mx-auto w-full max-w-60">
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="w-full"
                role="img"
                aria-label={`${label}: ${value}%`}
            >
                <path
                    d={arc}
                    fill="none"
                    strokeWidth={STROKE}
                    className="stroke-muted"
                />
                <path
                    d={arc}
                    fill="none"
                    stroke={color}
                    strokeWidth={STROKE}
                    strokeDasharray={`${(value / 100) * length} ${length}`}
                />
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
