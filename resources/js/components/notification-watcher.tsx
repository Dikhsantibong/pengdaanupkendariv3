import { usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { realtimeEnabled } from '@/lib/echo';
import {
    browserNotificationsSupported,
    fetchFeed,
    openNotification,
    setFeed,
} from '@/lib/notification-feed';
import type { Feed, FeedItem } from '@/lib/notification-feed';
import type { Auth } from '@/types';

/**
 * How often the open app asks for notifications itself: a fallback for
 * when Reverb is not running or the connection drops.
 */
const POLL_MS = 60_000;

/** Ids already announced, shared by every open tab so none repeats. */
const ANNOUNCED_KEY = 'notifikasi-diumumkan';

/** Claim an id for announcing; false when another tab already did. */
function claim(id: string): boolean {
    try {
        const announced = JSON.parse(
            window.localStorage.getItem(ANNOUNCED_KEY) ?? '[]',
        ) as string[];

        if (announced.includes(id)) {
            return false;
        }

        window.localStorage.setItem(
            ANNOUNCED_KEY,
            JSON.stringify([id, ...announced].slice(0, 100)),
        );
    } catch {
        // Without storage every tab announces; better than none.
    }

    return true;
}

/** Tell the user about a new notification: in the app, and in the browser. */
function announce(item: FeedItem): void {
    if (!claim(item.id)) {
        return;
    }

    toast(item.title, {
        description: item.message,
        duration: 10_000,
        action: { label: 'Buka', onClick: () => openNotification(item) },
    });

    // The browser's own pop-up when the app is not in front: another tab,
    // another window, or minimised.
    const inFront =
        document.visibilityState === 'visible' && document.hasFocus();

    if (
        !inFront &&
        browserNotificationsSupported() &&
        Notification.permission === 'granted'
    ) {
        const popup = new Notification(item.title, {
            body: item.message,
            icon: '/logo/icon.png',
            tag: item.id,
        });

        popup.onclick = () => {
            window.focus();
            void openNotification(item);
            popup.close();
        };
    }
}

/** What Reverb sends when the user's unread notifications change. */
type NotificationsChanged = { feed: Feed; new_id: string | null };

/**
 * Listens on the user's private channel: Reverb pushes the new snapshot the
 * moment a notification is stored or read, so the bell, the Dashboard and
 * the pop-up follow at once.
 */
function RealtimeChannel({
    userId,
    onNew,
}: {
    userId: number;
    onNew: (item: FeedItem) => void;
}) {
    useEcho<NotificationsChanged>(
        `App.Models.User.${userId}`,
        '.notifications.changed',
        (event) => {
            setFeed(event.feed);

            const item = event.feed.items.find(
                (candidate) => candidate.id === event.new_id,
            );

            if (item) {
                onNew(item);
            }
        },
        [userId],
    );

    return null;
}

/**
 * Keeps the notifications current while the app is open: over Reverb when
 * it is configured, and by polling the server as a fallback (and whenever
 * the tab comes back), so the bell and the Dashboard follow without a
 * reload. Each new notification is announced in the app and, when allowed,
 * as a browser pop-up. Mounted once in the app layout.
 */
export function NotificationWatcher() {
    const { auth, unreadNotifications } = usePage<{
        auth: Auth;
        unreadNotifications: number;
    }>().props;
    const known = useRef<Set<string> | null>(null);

    const announceOnce = (item: FeedItem) => {
        if (known.current?.has(item.id)) {
            return;
        }

        known.current?.add(item.id);
        announce(item);
    };

    useEffect(() => {
        let stopped = false;

        const check = async () => {
            const feed = await fetchFeed();

            if (feed === null || stopped) {
                return;
            }

            // The notifications already there when the app opened are shown
            // on the Dashboard; only the ones arriving later pop up.
            if (known.current === null) {
                known.current = new Set(feed.items.map((item) => item.id));

                return;
            }

            const fresh = feed.items.filter(
                (item) => !known.current?.has(item.id),
            );
            fresh.forEach((item) => known.current?.add(item.id));
            [...fresh].reverse().forEach(announce);
        };

        void check();
        const timer = window.setInterval(() => void check(), POLL_MS);
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                void check();
            }
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            stopped = true;
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, []);

    // A page visit that changed the count (marked read, say) refreshes the
    // store at once instead of at the next poll.
    useEffect(() => {
        void fetchFeed();
    }, [unreadNotifications]);

    return realtimeEnabled() ? (
        <RealtimeChannel userId={auth.user.id} onNew={announceOnce} />
    ) : null;
}
