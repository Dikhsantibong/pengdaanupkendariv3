import { Head, router } from '@inertiajs/react';
import { BellOff, Check, CheckCheck } from 'lucide-react';
import { BrowserNotificationToggle } from '@/components/browser-notification-toggle';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { markRead, openNotification } from '@/lib/notification-feed';
import type { FeedItem } from '@/lib/notification-feed';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import notifications from '@/routes/notifications';
import type { Paginated } from '@/types';

type NotificationRow = FeedItem;

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
                    description="Pemberitahuan penugasan PIC, pengajuan persetujuan, dan keputusan Team Leader."
                    actions={
                        <>
                            <BrowserNotificationToggle />
                            {hasUnread && (
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
                            )}
                        </>
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

                                    <div className="flex shrink-0 gap-1">
                                        {row.read_at === null && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                title="Tandai dibaca"
                                                aria-label="Tandai dibaca"
                                                onClick={() =>
                                                    void markRead(row.id).then(
                                                        () =>
                                                            router.reload({
                                                                only: [
                                                                    'notifications',
                                                                ],
                                                            }),
                                                    )
                                                }
                                            >
                                                <Check className="size-4" />
                                            </Button>
                                        )}
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                void openNotification(row)
                                            }
                                        >
                                            Buka
                                        </Button>
                                    </div>
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
