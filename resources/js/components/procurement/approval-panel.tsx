import { router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    CircleDot,
    Hourglass,
    Lock,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import procurements from '@/routes/procurements';

/** Where one approval of the procurement stands. */
export type ApprovalStageState =
    'done' | 'active' | 'waiting' | 'returned' | 'locked';

/**
 * The procurement's approvals in one card, Perencanaan then Pelaksanaan:
 * a stage that is behind it is one line (open it for the details), a locked
 * one says when it opens, and the stage in hand is open in full with its
 * buttons.
 */
export function ApprovalPanel({
    id,
    children,
    footer,
}: {
    id?: string;
    children: ReactNode;
    footer?: ReactNode;
}) {
    return (
        <section
            id={id}
            className="scroll-mt-20 overflow-hidden rounded-md border border-border bg-card"
        >
            <p className="section-label border-b border-border px-4 py-2.5">
                Persetujuan
            </p>
            <div className="divide-y divide-border">{children}</div>
            {footer && (
                <div className="border-t border-border p-4">{footer}</div>
            )}
        </section>
    );
}

const STATE_MARK: Record<
    ApprovalStageState,
    { icon: ReactNode; className: string }
> = {
    done: {
        icon: <Check className="size-3.5" />,
        className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    },
    active: {
        icon: <CircleDot className="size-3.5" />,
        className: 'bg-primary text-primary-foreground',
    },
    waiting: {
        icon: <Hourglass className="size-3.5" />,
        className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    },
    returned: {
        icon: <RotateCcw className="size-3.5" />,
        className: 'bg-destructive/10 text-destructive',
    },
    locked: {
        icon: <Lock className="size-3" />,
        className: 'bg-muted text-muted-foreground',
    },
};

/** One approval in the panel: its line, and its card while it is open. */
export function ApprovalStage({
    title,
    state,
    summary,
    defaultOpen,
    children,
}: {
    title: string;
    state: ApprovalStageState;
    summary: string;
    defaultOpen: boolean;
    children?: ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const mark = STATE_MARK[state];
    const openable = children !== undefined && children !== null;

    const line = (
        <>
            <span
                className={cn(
                    'flex size-6 shrink-0 items-center justify-center rounded-full',
                    mark.className,
                )}
            >
                {mark.icon}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-medium text-foreground">
                    {title}
                </span>
                <span className="tabular block text-xs text-muted-foreground">
                    {summary}
                </span>
            </span>
        </>
    );

    return (
        <div>
            {openable ? (
                <button
                    type="button"
                    aria-expanded={open}
                    onClick={() => setOpen(!open)}
                    className="flex w-full items-center gap-2.5 px-4 py-3 text-left hover:bg-muted/40"
                >
                    {line}
                    <ChevronDown
                        className={cn(
                            'size-4 shrink-0 text-muted-foreground transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </button>
            ) : (
                <div className="flex items-center gap-2.5 px-4 py-3">
                    {line}
                </div>
            )}
            {openable && open && <div className="px-4 pb-4">{children}</div>}
        </div>
    );
}

/** Close a procurement that needs no Keuangan stage. */
export function CompleteProcurementButton({
    procurementId,
}: {
    procurementId: number;
}) {
    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            className="w-full"
            onClick={() =>
                router.post(
                    procurements.completion.store(procurementId).url,
                    {},
                    { preserveScroll: true },
                )
            }
        >
            Tandai Pengadaan Selesai
        </Button>
    );
}
