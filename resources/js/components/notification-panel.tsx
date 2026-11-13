import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Bell,
    Check,
    CheckCheck,
    ChevronRight,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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

const panelListeners = new Set<() => void>();
let panelDismissed = false;

function setPanelDismissed(next: boolean): void {
    panelDismissed = next;
    panelListeners.forEach((listener) => listener());
}

export function toggleNotificationPanel(): void {
    setPanelDismissed(!panelDismissed);
}

export function openNotificationPanel(): void {
    setPanelDismissed(false);
}

/**
 * Panel notifikasi belum dibaca di sudut kanan atas.
 * Mengikuti sistem desain SIM-SCM (rounded-md, border tegas, palet Biru PLN
 * dengan aksen strip kuning status berjalan agar kontras dan mudah terlihat).
 */
export function NotificationPanel({ initial }: { initial?: Feed }) {
    const page = usePage<{ unreadNotifications?: number }>();
    const fallback: Feed = initial ?? {
        total: page.props.unreadNotifications ?? 0,
        items: [],
    };
    const feed = useNotificationFeed(fallback);
    const [dismissed, setDismissed] = useState(panelDismissed);
    const latestId = feed.items[0]?.id ?? null;

    useEffect(() => {
        const listener = () => setDismissed(panelDismissed);
        panelListeners.add(listener);

        return () => {
            panelListeners.delete(listener);
        };
    }, []);

    // Buka kembali panel secara otomatis saat ada notifikasi baru masuk.
    useEffect(() => {
        if (latestId !== null) {
            setPanelDismissed(false);
        }
    }, [latestId]);

    if (feed.total === 0 || dismissed) {
        return null;
    }

    const markAll = () =>
        router.put(
            notifications.update().url,
            {},
            { preserveScroll: true, onSuccess: () => void fetchFeed() },
        );

    return (
        <aside
            aria-label="Panel Notifikasi Belum Dibaca"
            className="fixed top-16 right-4 z-50 w-[calc(100vw-2rem)] sm:w-[28rem] md:w-[32rem]"
        >
            <section className="relative overflow-hidden rounded-md border border-primary/50 border-l-2 border-l-primary bg-card shadow-sm">
                <span
                    className="block h-1 w-full bg-viz-yellow"
                    aria-hidden
                />

                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-primary px-4 py-3 text-primary-foreground">
                    <div className="flex items-center gap-2.5">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-white/15 text-primary-foreground">
                            <Bell className="size-4" />
                        </span>
                        <div className="leading-tight">
                            <div className="flex items-center gap-2">
                                <h2 className="text-sm font-semibold text-primary-foreground">
                                    Notifikasi Belum Dibaca
                                </h2>
                                <span className="tabular inline-flex items-center rounded-full bg-viz-yellow px-2 py-0.5 text-[0.6875rem] leading-none font-semibold text-foreground">
                                    {feed.total}
                                </span>
                            </div>
                            <p className="mt-0.5 text-xs text-primary-foreground/85">
                                {feed.total} pemberitahuan menunggu tindak
                                lanjut Anda
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-1.5">
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="h-8 px-2.5 text-xs font-medium"
                            onClick={markAll}
                        >
                            <CheckCheck className="size-3.5" />
                            Tandai semua dibaca
                        </Button>
                        <button
                            type="button"
                            onClick={() => setPanelDismissed(true)}
                            title="Sembunyikan panel sementara"
                            aria-label="Sembunyikan panel notifikasi"
                            className="inline-flex size-8 items-center justify-center rounded-md text-primary-foreground/85 transition-colors hover:bg-white/15 hover:text-primary-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                </header>

                {feed.items.length > 0 && (
                    <ul className="max-h-[65vh] divide-y divide-border overflow-y-auto">
                        {feed.items.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-col gap-3 bg-secondary/45 px-4 py-3.5 transition-colors hover:bg-accent/60 sm:flex-row sm:items-start"
                            >
                                <span
                                    className="mt-1.5 hidden size-2 shrink-0 rounded-full bg-primary sm:block"
                                    aria-hidden
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold text-foreground">
                                        {item.title}
                                    </p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {item.message}
                                    </p>
                                    <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span className="tabular">
                                            {formatDateTime(item.created_at)}
                                        </span>
                                        {item.procurement_number && (
                                            <span className="tabular rounded-sm bg-status-berjalan-surface px-1.5 py-0.5 font-medium text-status-berjalan">
                                                {item.procurement_number}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-1.5 sm:self-center">
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() =>
                                            void openNotification(item)
                                        }
                                    >
                                        Buka
                                        <ArrowRight className="size-3.5" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
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
                )}

                <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-border bg-muted/60 px-4 py-2.5">
                    <BrowserNotificationToggle />
                    <Link
                        href={notifications.index()}
                        onClick={() => setPanelDismissed(true)}
                        className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        Lihat semua
                        {feed.total > feed.items.length
                            ? ` (${feed.total - feed.items.length} lainnya)`
                            : ''}
                        <ChevronRight className="size-3.5" />
                    </Link>
                </footer>
            </section>
        </aside>
    );
}
