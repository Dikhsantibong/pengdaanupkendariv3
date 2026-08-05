import { router } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Progress } from '@/components/ui/progress';
import { formatDateTime } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { ChecklistRow } from '@/types';

export function ChecklistPanel({
    procurementId,
    title,
    description,
    rows,
    editable,
    lockedReason,
}: {
    procurementId: number;
    title: string;
    description?: string;
    rows: ChecklistRow[];
    editable: boolean;
    lockedReason?: string;
}) {
    const completed = rows.filter((row) => row.is_completed).length;
    const percentage =
        rows.length > 0 ? Math.round((completed / rows.length) * 100) : 0;

    const toggle = (row: ChecklistRow, next: boolean) => {
        router.put(
            procurements.checklists.update({
                procurement: procurementId,
                checklist: row.id,
            }).url,
            { is_completed: next, notes: row.notes ?? '' },
            { preserveScroll: true },
        );
    };

    return (
        <section className="rounded-md border border-border bg-card">
            <header className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h2 className="text-sm font-semibold text-foreground">
                        {title}
                    </h2>
                    {description && (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>

                <div className="w-full space-y-1.5 sm:w-48">
                    <div className="tabular flex items-center justify-between text-xs">
                        <span className="font-semibold">{percentage}%</span>
                        <span className="text-muted-foreground">
                            {completed}/{rows.length} selesai
                        </span>
                    </div>
                    <Progress value={percentage} />
                </div>
            </header>

            {!editable && lockedReason && (
                <p className="flex items-center gap-2 border-b border-border bg-muted/40 px-4 py-2 text-xs text-muted-foreground">
                    <Lock className="size-3.5" />
                    {lockedReason}
                </p>
            )}

            <ul className="divide-y divide-border">
                {rows.map((row) => (
                    <li
                        key={row.id}
                        className="flex items-start gap-3 px-4 py-3"
                    >
                        <Checkbox
                            id={`checklist-${row.id}`}
                            checked={row.is_completed}
                            disabled={!editable}
                            onCheckedChange={(checked) =>
                                toggle(row, checked === true)
                            }
                            className="mt-0.5"
                        />

                        <div className="min-w-0 flex-1">
                            <label
                                htmlFor={`checklist-${row.id}`}
                                className="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground"
                            >
                                {row.name}
                                {row.is_optional && (
                                    <span className="rounded-sm bg-muted px-1.5 py-0.5 text-[0.6875rem] font-normal text-muted-foreground">
                                        Opsional
                                    </span>
                                )}
                            </label>

                            {row.description && (
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {row.description}
                                </p>
                            )}

                            {row.is_completed && row.completed_by && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Ditandai oleh {row.completed_by} ·{' '}
                                    {formatDateTime(row.completed_at)}
                                </p>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
