import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { PicOption, PicWorkload } from '@/types';

const NONE = 'none';

export type PicRole = 'planner' | 'executor';

/** Workload at or above this many active procurements is flagged as busy. */
const BUSY_THRESHOLD = 5;

/** Workload at or above this many active procurements is flagged as heavy. */
const HEAVY_THRESHOLD = 8;

function loadTone(active: number): string {
    if (active >= HEAVY_THRESHOLD) {
        return 'bg-red-500/10 text-red-700 dark:text-red-400';
    }

    if (active >= BUSY_THRESHOLD) {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
}

/** The count that matters most for the role being assigned. */
function roleCount(workload: PicWorkload, role: PicRole): number {
    return role === 'planner' ? workload.planning : workload.execution;
}

function roleNoun(role: PicRole): string {
    return role === 'planner' ? 'perencanaan' : 'pelaksanaan';
}

/**
 * A compact badge with the number of procurements a PIC is working on.
 */
export function WorkloadBadge({
    workload,
    role,
    className,
}: {
    workload: PicWorkload;
    role: PicRole;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'tabular inline-flex shrink-0 items-center rounded-sm px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                loadTone(workload.active),
                className,
            )}
            title={`${roleCount(workload, role)} ${roleNoun(role)} berjalan · ${workload.active} pengadaan aktif`}
        >
            {roleCount(workload, role)} {roleNoun(role)} · {workload.active}{' '}
            aktif
        </span>
    );
}

/**
 * Pick a PIC while seeing how many procurements each candidate already holds.
 */
export function PicSelect({
    id,
    value,
    options,
    role,
    onChange,
    emptyLabel = 'Belum ditunjuk',
    showSummary = true,
    triggerClassName,
}: {
    id?: string;
    value: number | null;
    options: PicOption[];
    role: PicRole;
    onChange: (value: number | null) => void;
    emptyLabel?: string;
    showSummary?: boolean;
    triggerClassName?: string;
}) {
    const selected = options.find((option) => option.value === value);

    return (
        <div className="grid gap-1.5">
            <Select
                value={value === null ? NONE : String(value)}
                onValueChange={(next) =>
                    onChange(next === NONE ? null : Number(next))
                }
            >
                <SelectTrigger
                    id={id}
                    className={cn('w-full', triggerClassName)}
                >
                    {/* Only the name in the trigger; the badge stays in the list. */}
                    <SelectValue placeholder={emptyLabel}>
                        {selected?.label ?? emptyLabel}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>{emptyLabel}</SelectItem>
                    {options.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={String(option.value)}
                            textValue={option.label}
                        >
                            <span className="flex w-full min-w-0 items-center justify-between gap-3">
                                <span className="truncate">{option.label}</span>
                                {option.workload && (
                                    <WorkloadBadge
                                        workload={option.workload}
                                        role={role}
                                        className="pointer-events-none"
                                    />
                                )}
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {showSummary && selected?.workload && (
                <p className="text-xs text-muted-foreground">
                    Sedang menangani{' '}
                    <span className="font-medium text-foreground">
                        {roleCount(selected.workload, role)} {roleNoun(role)}
                    </span>{' '}
                    berjalan dan{' '}
                    <span className="font-medium text-foreground">
                        {selected.workload.active} pengadaan aktif
                    </span>{' '}
                    secara keseluruhan.
                </p>
            )}
        </div>
    );
}
