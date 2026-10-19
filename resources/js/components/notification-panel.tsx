import { Link, router } from '@inertiajs/react';
import { ArrowRight, Bell, Check, CheckCheck } from 'lucide-react';
import { BrowserNotificationToggle } from '@/components/browser-notification-toggle';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import {
    fetchFeed,
    markRead,
    openNotification,
    useNotificationFeed,
} from '@/lib/notification-feed';
import type { Feed } from '@/lib/notification-feed';
import notifications from '@/routes/notifications';

/**
 * The unread notifications at the top of the Dashboard. They stay here,
 * every time the app is opened, until each is opened or marked read, and
 * new ones appear without a reload.
 */
export function NotificationPanel({ initial }: { initial: Feed }) {
    const feed = useNotificationFeed(initial);

    if (feed.total === 0) {
        return null;
    }

    const markAll = () =>
        router.put(
            notifications.update().url,
            {},
            { preserveScroll: true, onSuccess: () => void fetchFeed() },
        );

    return (
        <section className="overflow-hidden rounded-md border border-primary/30 bg-card">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-primary/5 px-4 py-3">
                <div className="flex items-center gap-2">
                    <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <Bell className="size-4" />
                    </span>
                    <div className="leading-tight">
                        <p className="text-sm font-semibold">
                            Notifikasi Belum Dibaca
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {feed.total} notifikasi menunggu Anda baca
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <BrowserNotificationToggle />
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={markAll}
                    >
                        <CheckCheck className="size-4" />
                        Tandai semua dibaca
                    </Button>
                </div>
            </div>

            <ul className="divide-y divide-border">
                {feed.items.map((item) => (
                    <li
                        key={item.id}
                        className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start"
                    >
                        <span
                            className="mt-1.5 hidden size-2 shrink-0 rounded-full bg-primary sm:block"
                            aria-hidden
                        />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium text-foreground">
                                {item.title}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {item.message}
                            </p>
                            <p className="tabular mt-1 text-xs text-muted-foreground">
                                {formatDateTime(item.created_at)}
                                {item.procurement_number
                                    ? ` · ${item.procurement_number}`
                                    : ''}
                            </p>
                        </div>
                        <div className="flex shrink-0 gap-1.5">
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => void openNotification(item)}
                            >
                                Buka
                                <ArrowRight className="size-3.5" />
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                title="Tandai dibaca"
                                aria-label="Tandai dibaca"
                                onClick={() => void markRead(item.id)}
                            >
                                <Check className="size-4" />
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>

            {feed.total > feed.items.length && (
                <div className="border-t border-border px-4 py-2.5 text-right">
                    <Link
                        href={notifications.index()}
                        className="text-xs font-medium text-primary hover:underline"
                    >
                        Lihat semua ({feed.total - feed.items.length} lainnya)
                    </Link>
                </div>
            )}
        </section>
    );
}
