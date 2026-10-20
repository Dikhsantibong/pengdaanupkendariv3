import { ArrowRight, Check, CircleAlert } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * The building blocks the procurement's step tables share: the checklist
 * stages, Amandemen and DO. One banner names what to do next, one table lists
 * the rows, and a side panel holds the forms of the row that is open.
 */

/** The panel open before a save reloaded the page, and since when. */
let remembered: { scope: string; id: number | 'newest'; at: number } | null =
    null;

/** How long an open panel is brought back after a reload, in ms. */
const REMEMBER_FOR = 60_000;

/**
 * Which row's side panel is open. Every save reloads the page and remounts
 * it, so the open row is remembered for a minute and opened again when the
 * save comes back. `ids` are the rows there are now; a panel waiting for the
 * newest row (one just created) opens the highest id.
 */
export function useRememberedPanel(
    scope: string,
    ids: number[],
): [number | null, (id: number | null) => void] {
    const [openId, setOpenId] = useState<number | null>(() => {
        if (
            remembered === null ||
            remembered.scope !== scope ||
            Date.now() - remembered.at > REMEMBER_FOR
        ) {
            return null;
        }

        if (remembered.id === 'newest') {
            const newest = ids.length > 0 ? Math.max(...ids) : null;
            remembered =
                newest === null ? null : { scope, id: newest, at: Date.now() };

            return newest;
        }

        return ids.includes(remembered.id) ? remembered.id : null;
    });

    const open = useCallback(
        (id: number | null) => {
            remembered = id === null ? null : { scope, id, at: Date.now() };
            setOpenId(id);
        },
        [scope],
    );

    // Opened from elsewhere on the page (a pending step named beside the
    // Ajukan button, say) while this table is on screen.
    useEffect(() => {
        const onOpen = (event: Event) => {
            const { detail } = event as CustomEvent<{
                scope: string;
                id: number;
            }>;

            if (detail.scope === scope) {
                setOpenId(detail.id);
            }
        };

        window.addEventListener(OPEN_EVENT, onOpen);

        return () => window.removeEventListener(OPEN_EVENT, onOpen);
    }, [scope]);

    return [openId, open];
}

const OPEN_EVENT = 'remembered-panel:open';

/**
 * Open a row's side panel from outside its table: at once if the table is
 * on screen, or as soon as it is (after switching to its tab).
 */
export function openRememberedPanel(scope: string, id: number): void {
    remembered = { scope, id, at: Date.now() };
    window.dispatchEvent(
        new CustomEvent(OPEN_EVENT, { detail: { scope, id } }),
    );
}

/** One thing holding a submission back, and where to go to finish it. */
export type PendingItem = {
    label: string;
    /** Where it is done, when the page can take the user there. */
    onOpen?: () => void;
};

/**
 * What still holds a submission back, each a link to where it is done,
 * shown next to the button it holds back.
 */
export function PendingList({
    title,
    items,
    limit = 6,
}: {
    title: string;
    items: PendingItem[];
    limit?: number;
}) {
    const [all, setAll] = useState(false);

    if (items.length === 0) {
        return null;
    }

    const shown = all ? items : items.slice(0, limit);

    return (
        <div className="space-y-1.5 rounded-sm border border-amber-500/30 bg-amber-500/5 p-2.5 text-xs">
            <p className="flex items-center gap-1.5 font-medium text-foreground">
                <CircleAlert className="size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                {title}
            </p>
            <ul className="space-y-0.5 pl-5">
                {shown.map((item) => (
                    <li key={item.label} className="list-disc">
                        {item.onOpen ? (
                            <button
                                type="button"
                                onClick={item.onOpen}
                                className="text-left text-primary underline-offset-2 hover:underline"
                            >
                                {item.label}
                            </button>
                        ) : (
                            <span className="text-muted-foreground">
                                {item.label}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
            {items.length > limit && (
                <button
                    type="button"
                    onClick={() => setAll(!all)}
                    className="pl-5 text-muted-foreground underline-offset-2 hover:underline"
                >
                    {all
                        ? 'Tampilkan lebih sedikit'
                        : `dan ${items.length - limit} lainnya`}
                </button>
            )}
        </div>
    );
}

/** The submission can go: nothing holds it back. */
export function ReadyNote({ children }: { children: ReactNode }) {
    return (
        <p className="flex items-start gap-1.5 rounded-sm border border-emerald-500/30 bg-emerald-500/5 p-2.5 text-xs text-emerald-800 dark:text-emerald-300">
            <Check className="mt-px size-3.5 shrink-0" />
            <span>{children}</span>
        </p>
    );
}

/** Keep the open panel remembered while it is being worked in. */
export function touchRememberedPanel(): void {
    if (remembered !== null) {
        remembered.at = Date.now();
    }
}

/** Open the newest row once the page comes back (after creating one). */
export function rememberNewestPanel(scope: string): void {
    remembered = { scope, id: 'newest', at: Date.now() };
}

export function forgetRememberedPanel(): void {
    remembered = null;
}

/** The one thing to do next, above a table of steps or requests. */
export function NextActionBanner({
    badge,
    label = 'Langkah berikutnya',
    title,
    detail,
    action,
    onAction,
    icon,
}: {
    /** A number or short mark in the circle. */
    badge: ReactNode;
    label?: string;
    title: string;
    detail?: string;
    action: string;
    onAction: () => void;
    icon?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 rounded-md border border-primary/30 bg-primary/5 px-4 py-3 sm:flex-row sm:items-center">
            <span className="tabular flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                {badge}
            </span>
            <div className="min-w-0 flex-1">
                <p className="section-label text-primary">{label}</p>
                <p className="text-sm font-semibold text-foreground">{title}</p>
                {detail && (
                    <p className="text-xs text-muted-foreground">{detail}</p>
                )}
            </div>
            <Button onClick={onAction} className="shrink-0">
                {icon}
                {action}
                {!icon && <ArrowRight className="size-4" />}
            </Button>
        </div>
    );
}

/** A numbered part of a side panel: what to do, in the order it is done. */
export function PanelSection({
    number,
    title,
    hint,
    children,
}: {
    number: number;
    title: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-2">
            <div className="flex items-start gap-2">
                <span className="tabular flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
                    {number}
                </span>
                <div>
                    <h3 className="text-sm font-semibold">{title}</h3>
                    {hint && (
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    )}
                </div>
            </div>
            {children}
        </section>
    );
}

/**
 * Where a request stands among its steps, small enough for a table cell: a
 * dot per step, the current one wider, and "langkah 3 dari 6".
 */
export function StageDots({
    steps,
    current,
    returned = false,
}: {
    steps: string[];
    current: number;
    returned?: boolean;
}) {
    const finished = current >= steps.length;

    return (
        <div
            className="flex items-center gap-1.5"
            title={steps
                .map((step, index) =>
                    index < current
                        ? `✓ ${step}`
                        : index === current
                          ? `● ${step}`
                          : step,
                )
                .join(' › ')}
        >
            <span className="flex items-center gap-0.5" aria-hidden="true">
                {steps.map((step, index) => (
                    <span
                        key={step}
                        className={cn(
                            'h-1.5 rounded-full',
                            index === current ? 'w-3' : 'w-1.5',
                            index < current
                                ? 'bg-emerald-500'
                                : index === current
                                  ? returned
                                      ? 'bg-destructive'
                                      : 'bg-amber-500'
                                  : 'bg-muted-foreground/25',
                        )}
                    />
                ))}
            </span>
            <span className="tabular text-[11px] text-muted-foreground">
                {finished ? (
                    <span className="inline-flex items-center gap-0.5 text-emerald-700 dark:text-emerald-400">
                        <Check className="size-3" />
                        Selesai
                    </span>
                ) : (
                    `${steps[current]} · ${current + 1}/${steps.length}`
                )}
            </span>
        </div>
    );
}
