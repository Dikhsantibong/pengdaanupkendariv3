import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    accent = false,
    className,
}: {
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    accent?: boolean;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-start justify-between gap-3 rounded-md border border-border bg-card p-4',
                accent && 'border-l-2 border-l-primary',
                className,
            )}
        >
            <div className="min-w-0 space-y-1">
                <p className="section-label truncate">{label}</p>
                <p className="tabular text-2xl leading-tight font-semibold text-foreground">
                    {value}
                </p>
                {hint && (
                    <p className="truncate text-xs text-muted-foreground">
                        {hint}
                    </p>
                )}
            </div>

            {Icon && (
                <span className="mt-0.5 shrink-0 text-muted-foreground/70">
                    <Icon className="size-5" strokeWidth={1.75} />
                </span>
            )}
        </div>
    );
}
