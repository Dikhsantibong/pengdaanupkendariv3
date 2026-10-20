import { Check, ChevronRight, Lock } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Where a stage of the procurement stands. */
export type StageState = 'done' | 'current' | 'todo' | 'locked';

export type Stage = {
    value: string;
    label: string;
    state: StageState;
    /** A few words under the label: "5/8 langkah", "Menunggu TL ICC". */
    summary: string;
    /** The user has something to do here now. */
    attention?: boolean;
};

export type StageExtra = {
    value: string;
    label: string;
    icon?: ReactNode;
    count?: number;
    attention?: boolean;
};

/**
 * The stages of a procurement in their order, as a stepper: each with where
 * it stands and a few words on it, the selected one marked. What happens
 * alongside the stages (Amandemen, DO, the approval history) sits apart on
 * the right. Every stage stays open to read, a locked one too.
 */
export function StageStepper({
    stages,
    extras,
    active,
    onSelect,
}: {
    stages: Stage[];
    extras: StageExtra[];
    active: string;
    onSelect: (value: string) => void;
}) {
    const scroller = useRef<HTMLOListElement>(null);

    // On a narrow screen the stepper scrolls sideways; keep the selected
    // stage in view (also once the page has settled into its width),
    // without moving the page itself.
    useEffect(() => {
        const list = scroller.current;

        if (!list) {
            return;
        }

        const center = () => {
            const selected = list.querySelector<HTMLElement>(
                '[aria-selected="true"]',
            );

            if (!selected || list.scrollWidth <= list.clientWidth) {
                return;
            }

            const offset =
                selected.getBoundingClientRect().left -
                list.getBoundingClientRect().left;

            list.scrollLeft +=
                offset - (list.clientWidth - selected.offsetWidth) / 2;
        };

        center();
        const observer = new ResizeObserver(center);
        observer.observe(list);

        return () => observer.disconnect();
    }, [active]);

    return (
        <div className="flex flex-col gap-2 rounded-md border border-border bg-card p-2">
            <ol
                ref={scroller}
                role="tablist"
                aria-label="Tahap pengadaan"
                className="flex min-w-0 items-stretch overflow-x-auto"
            >
                {stages.map((stage, index) => {
                    const selected = stage.value === active;

                    return (
                        <li
                            key={stage.value}
                            className="flex min-w-fit flex-1 items-center"
                        >
                            {index > 0 && (
                                <ChevronRight
                                    className={cn(
                                        'mx-0.5 size-4 shrink-0',
                                        stage.state === 'locked' ||
                                            stage.state === 'todo'
                                            ? 'text-muted-foreground/40'
                                            : 'text-muted-foreground',
                                    )}
                                    aria-hidden="true"
                                />
                            )}
                            <button
                                type="button"
                                role="tab"
                                aria-selected={selected}
                                onClick={() => onSelect(stage.value)}
                                className={cn(
                                    'relative flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                    selected
                                        ? 'bg-primary/10 ring-1 ring-primary/40'
                                        : 'hover:bg-muted/60',
                                )}
                            >
                                <StageMark
                                    state={stage.state}
                                    number={index + 1}
                                />
                                <span className="min-w-0">
                                    <span
                                        className={cn(
                                            'block text-sm font-medium whitespace-nowrap',
                                            stage.state === 'locked' ||
                                                stage.state === 'todo'
                                                ? 'text-muted-foreground'
                                                : 'text-foreground',
                                            selected && 'text-primary',
                                        )}
                                    >
                                        {stage.label}
                                    </span>
                                    <span className="tabular block text-[11px] whitespace-nowrap text-muted-foreground">
                                        {stage.summary}
                                    </span>
                                </span>
                                {stage.attention && <AttentionDot />}
                            </button>
                        </li>
                    );
                })}
            </ol>

            <div
                role="tablist"
                aria-label="Lainnya"
                className="flex flex-wrap items-center gap-1.5 border-t border-border px-1 pt-2"
            >
                {extras.map((extra) => {
                    const selected = extra.value === active;

                    return (
                        <button
                            key={extra.value}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => onSelect(extra.value)}
                            className={cn(
                                'relative inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                selected
                                    ? 'border-primary/40 bg-primary/10 text-primary'
                                    : 'border-border text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                            )}
                        >
                            {extra.icon}
                            {extra.label}
                            {extra.count !== undefined && extra.count > 0 && (
                                <span className="tabular rounded-full bg-muted px-1.5 text-[10px] text-foreground">
                                    {extra.count}
                                </span>
                            )}
                            {extra.attention && <AttentionDot />}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function StageMark({ state, number }: { state: StageState; number: number }) {
    return (
        <span
            className={cn(
                'tabular flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                state === 'done' &&
                    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
                state === 'current' && 'bg-primary text-primary-foreground',
                state === 'todo' && 'bg-muted text-muted-foreground',
                state === 'locked' && 'bg-muted text-muted-foreground/70',
            )}
            aria-label={
                state === 'done'
                    ? 'selesai'
                    : state === 'current'
                      ? 'sedang berjalan'
                      : state === 'locked'
                        ? 'terkunci'
                        : 'belum'
            }
        >
            {state === 'done' ? (
                <Check className="size-3.5" />
            ) : state === 'locked' ? (
                <Lock className="size-3" />
            ) : (
                number
            )}
        </span>
    );
}

/** A small red dot: something here waits on the user. */
function AttentionDot() {
    return (
        <span
            className="absolute top-1 right-1 size-2 rounded-full bg-destructive ring-2 ring-card"
            title="Perlu tindakan Anda"
        />
    );
}
