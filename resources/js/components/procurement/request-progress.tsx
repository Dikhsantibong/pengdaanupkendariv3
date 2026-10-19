import { Check, ChevronDown, Download, Eye, Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * Where a request (an amendment or a Delivery Order) stands, as a row of
 * steps: done ones ticked, the current one highlighted, the rest muted.
 * `current` equal to the number of steps means every step is done.
 */
export function RequestProgress({
    steps,
    current,
    returned = false,
}: {
    steps: string[];
    current: number;
    /** Sent back to the proposer: the first step is shown as the one waiting. */
    returned?: boolean;
}) {
    return (
        <ol className="flex flex-wrap items-center gap-x-1 gap-y-1.5 text-[11px]">
            {steps.map((step, index) => {
                const done = index < current;
                const active = index === current;

                return (
                    <li key={step} className="flex items-center gap-1">
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 font-medium whitespace-nowrap',
                                done &&
                                    'border-primary/30 bg-primary/10 text-primary',
                                active &&
                                    (returned
                                        ? 'border-destructive/40 bg-destructive/10 text-destructive'
                                        : 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-300'),
                                !done &&
                                    !active &&
                                    'border-border text-muted-foreground',
                            )}
                        >
                            {done && <Check className="size-3" />}
                            {step}
                        </span>
                        {index < steps.length - 1 && (
                            <span className="text-muted-foreground/60">›</span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

/**
 * The generated document's buttons, as on the checklist: Preview PDF (a new
 * tab), Unduh PDF and, for those who may correct it, Edit.
 */
export function RequestDocumentActions({
    document,
}: {
    document: {
        preview_url: string;
        edit_url: string;
        download_url?: string;
    };
}) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <Button asChild size="sm" variant="outline">
                <a
                    href={document.preview_url}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <Eye className="size-3.5" />
                    Preview PDF
                </a>
            </Button>
            {document.download_url && (
                <Button asChild size="sm" variant="outline">
                    <a href={document.download_url}>
                        <Download className="size-3.5" />
                        Unduh PDF
                    </a>
                </Button>
            )}
            <Button asChild size="sm" variant="outline">
                <a href={document.edit_url}>
                    <Pencil className="size-3.5" />
                    Edit
                </a>
            </Button>
        </div>
    );
}

/** A small Detail / Tutup button for a row that opens below itself. */
export function DetailToggle({
    open,
    onToggle,
}: {
    open: boolean;
    onToggle: () => void;
}) {
    return (
        <Button
            type="button"
            size="sm"
            variant="ghost"
            aria-expanded={open}
            onClick={onToggle}
        >
            {open ? 'Tutup' : 'Detail'}
            <ChevronDown
                className={cn(
                    'size-3.5 transition-transform',
                    open && 'rotate-180',
                )}
            />
        </Button>
    );
}
