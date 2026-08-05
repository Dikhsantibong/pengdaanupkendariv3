import { History } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { formatDateTime } from '@/lib/format';
import type { ActivityRow } from '@/types';

export function ActivityTimeline({
    activities,
}: {
    activities: ActivityRow[];
}) {
    if (activities.length === 0) {
        return (
            <div className="rounded-md border border-border bg-card">
                <EmptyState
                    icon={History}
                    title="Belum ada aktivitas"
                    description="Setiap perubahan status, checklist, dan persetujuan akan tercatat di sini."
                />
            </div>
        );
    }

    return (
        <section className="rounded-md border border-border bg-card">
            <header className="border-b border-border px-4 py-3">
                <h2 className="text-sm font-semibold text-foreground">
                    Histori Aktivitas
                </h2>
            </header>

            <ol className="divide-y divide-border">
                {activities.map((activity) => (
                    <li key={activity.id} className="flex gap-3 px-4 py-3">
                        <span
                            className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                            aria-hidden
                        />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm text-foreground">
                                {activity.description}
                            </p>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {activity.type_label}
                                {activity.user
                                    ? ` · ${activity.user}`
                                    : ''} ·{' '}
                                {formatDateTime(activity.created_at)}
                            </p>
                        </div>
                    </li>
                ))}
            </ol>
        </section>
    );
}
