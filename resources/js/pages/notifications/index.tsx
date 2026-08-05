import { Head, Link, router } from '@inertiajs/react';
import { BellOff, CheckCheck } from 'lucide-react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import notifications from '@/routes/notifications';
import procurements from '@/routes/procurements';
import type { Paginated } from '@/types';

type NotificationRow = {
    id: string;
    title: string;
    message: string;
    procurement_id: number | null;
    procurement_number: string | null;
    read_at: string | null;
    created_at: string;
};

export default function NotificationIndex({
    notifications: page,
}: {
    notifications: Paginated<NotificationRow>;
}) {
    const hasUnread = page.data.some((row) => row.read_at === null);

    return (
        <>
            <Head title="Notifikasi" />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow="Pemberitahuan"
                    title="Notifikasi"
                    description="Pemberitahuan penugasan PIC, pengajuan persetujuan, dan keputusan TL Perencanaan."
                    actions={
                        hasUnread && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.put(
                                        notifications.update().url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCheck className="size-4" />
                                Tandai semua dibaca
                            </Button>
                        )
                    }
                />

                {page.data.length === 0 ? (
                    <div className="rounded-md border border-border bg-card">
                        <EmptyState
                            icon={BellOff}
                            title="Belum ada notifikasi"
                            description="Notifikasi akan muncul saat Anda ditunjuk sebagai PIC atau ada perubahan persetujuan."
                        />
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border bg-card">
                        <ul className="divide-y divide-border">
                            {page.data.map((row) => (
                                <li
                                    key={row.id}
                                    className={cn(
                                        'flex gap-3 px-4 py-3',
                                        row.read_at === null && 'bg-accent/30',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'mt-1.5 size-1.5 shrink-0 rounded-full',
                                            row.read_at === null
                                                ? 'bg-primary'
                                                : 'bg-border',
                                        )}
                                        aria-hidden
                                    />

                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-foreground">
                                            {row.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {row.message}
                                        </p>
                                        <p className="tabular mt-1 text-xs text-muted-foreground">
                                            {formatDateTime(row.created_at)}
                                            {row.procurement_number
                                                ? ` · ${row.procurement_number}`
                                                : ''}
                                        </p>
                                    </div>

                                    {row.procurement_id !== null && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                            className="shrink-0"
                                        >
                                            <Link
                                                href={procurements.show(
                                                    row.procurement_id,
                                                )}
                                            >
                                                Buka
                                            </Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>

                        <DataPagination page={page} />
                    </div>
                )}
            </div>
        </>
    );
}

NotificationIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Notifikasi', href: notifications.index() },
    ],
};
