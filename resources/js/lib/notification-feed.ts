import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import notifications from '@/routes/notifications';

/** One notification, as the bell, the Dashboard and the pop-ups show it. */
export type FeedItem = {
    id: string;
    title: string;
    message: string;
    procurement_number: string | null;
    /** Where it leads: the procurement, on the tab it is about. */
    url: string;
    read_at: string | null;
    created_at: string;
};

/** The unread notifications (newest first) and how many there are. */
export type Feed = { total: number; items: FeedItem[] };

/*
 * One store for the whole app: the polling watcher fills it, and the bell,
 * the Dashboard panel and the inbox read it, so they agree without a page
 * reload.
 */
let current: Feed | null = null;
const listeners = new Set<() => void>();

const getFeed = (): Feed | null => current;

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function setFeed(feed: Feed): void {
    current = feed;
    listeners.forEach((listener) => listener());
}

/** The latest unread notifications, or the given ones until polled. */
export function useNotificationFeed(fallback: Feed): Feed {
    return useSyncExternalStore(subscribe, getFeed, getFeed) ?? fallback;
}

export function xsrfHeader(): Record<string, string> {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match === null
        ? {}
        : { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) };
}

const jsonHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

/** Ask the server for the unread notifications; null when it cannot. */
export async function fetchFeed(): Promise<Feed | null> {
    try {
        const response = await fetch(notifications.feed().url, {
            headers: jsonHeaders,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return null;
        }

        const feed = (await response.json()) as Feed;
        setFeed(feed);

        return feed;
    } catch {
        return null;
    }
}

/** Mark one notification read; the store follows the server's answer. */
export async function markRead(id: string): Promise<void> {
    try {
        const response = await fetch(notifications.read(id).url, {
            method: 'PUT',
            headers: { ...jsonHeaders, ...xsrfHeader() },
            credentials: 'same-origin',
        });

        if (response.ok) {
            setFeed((await response.json()) as Feed);
        }
    } catch {
        // The next poll brings the store back in line.
    }
}

/** Open what a notification is about, marking it read on the way. */
export async function openNotification(item: FeedItem): Promise<void> {
    await markRead(item.id);
    router.visit(item.url);
}

/** Whether this browser can show notifications at all. */
export const browserNotificationsSupported = (): boolean =>
    typeof window !== 'undefined' && 'Notification' in window;
