import { Check, Lock } from 'lucide-react';
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
                    const isFirst = index === 0;
                    const isLast = index === stages.length - 1;
                    const pending =
                        stage.state === 'locked' || stage.state === 'todo';

                    return (
                        <li
                            key={stage.value}
                            className={cn(
                                'flex min-w-fit flex-1',
                                // The notch of each ribbon tucks under the
                                // point of the one before, a thin gap between.
                                !isFirst && '-ml-3.5',
                            )}
                        >
                            <button
                                type="button"
                                role="tab"
                                aria-selected={selected}
                                onClick={() => onSelect(stage.value)}
                                style={{
                                    clipPath: ribbonShape(isFirst, isLast),
                                }}
                                className={cn(
                                    'relative flex min-h-20 w-full items-center gap-2.5 py-3.5 text-left transition-[filter,background-color] outline-none hover:brightness-105 focus-visible:brightness-110 2xl:gap-3.5',
                                    isFirst
                                        ? 'pl-4 2xl:pl-5'
                                        : 'pl-8 2xl:pl-10',
                                    isLast ? 'pr-4 2xl:pr-5' : 'pr-7 2xl:pr-10',
                                    stage.state === 'done' &&
                                        'bg-[#0a5596] text-white dark:bg-[#1d5f99]',
                                    stage.state === 'current' &&
                                        'bg-primary text-primary-foreground',
                                    pending &&
                                        'bg-primary/10 text-muted-foreground hover:bg-primary/15',
                                )}
                            >
                                <StageMark
                                    state={stage.state}
                                    number={index + 1}
                                />
                                <span className="min-w-0">
                                    <span
                                        className={cn(
                                            'block text-base leading-tight font-semibold whitespace-nowrap',
                                            pending && 'text-foreground/70',
                                        )}
                                    >
                                        {stage.label}
                                    </span>
                                    <span
                                        className={cn(
                                            'tabular mt-1 block text-xs whitespace-nowrap',
                                            pending
                                                ? 'text-muted-foreground'
                                                : 'text-white/80',
                                        )}
                                    >
                                        {stage.summary}
                                    </span>
                                </span>
                                {selected && (
                                    // The stage on screen: a bar along its foot.
                                    <span
                                        className={cn(
                                            'absolute inset-x-0 bottom-0 h-1.5',
                                            !isFirst && 'ml-7',
                                            !isLast && 'mr-7',
                                            pending
                                                ? 'bg-primary'
                                                : 'bg-white/85',
                                        )}
                                        aria-hidden="true"
                                    />
                                )}
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
                'tabular flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold 2xl:size-10',
                state === 'done' && 'border border-white/70 text-white',
                state === 'current' && 'bg-white text-primary',
                state === 'todo' &&
                    'border border-muted-foreground/40 text-muted-foreground',
                state === 'locked' &&
                    'border border-muted-foreground/30 text-muted-foreground/70',
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
                <Check className="size-5" strokeWidth={3} />
            ) : state === 'locked' ? (
                <Lock className="size-4" />
            ) : (
                number
            )}
        </span>
    );
}

/** Depth of the point on each ribbon. */
const POINT = '22px';

/**
 * The ribbon outline: a point on the right and a notch on the left, the
 * first flat on its left and the last flat on its right.
 */
function ribbonShape(isFirst: boolean, isLast: boolean): string {
    const right = isLast
        ? ['100% 0', '100% 100%']
        : [`calc(100% - ${POINT}) 0`, '100% 50%', `calc(100% - ${POINT}) 100%`];
    const left = isFirst ? ['0 100%'] : ['0 100%', `${POINT} 50%`];

    return `polygon(0 0, ${[...right, ...left].join(', ')})`;
}

/** A small red dot: something here waits on the user. */
function AttentionDot() {
    return (
        <span
            className="absolute top-2.5 right-9 size-2.5 rounded-full bg-destructive ring-2 ring-white"
            title="Perlu tindakan Anda"
        />
    );
}
