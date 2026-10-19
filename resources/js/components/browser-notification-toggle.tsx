import { BellRing } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import { browserNotificationsSupported } from '@/lib/notification-feed';

type Permission = NotificationPermission | 'unsupported';

const permissionListeners = new Set<() => void>();

function subscribe(listener: () => void): () => void {
    permissionListeners.add(listener);

    return () => permissionListeners.delete(listener);
}

const readPermission = (): Permission =>
    browserNotificationsSupported() ? Notification.permission : 'unsupported';

/*
 * The server knows nothing of the browser's permission, so it renders
 * nothing; the browser fills the real state in after hydration instead of
 * rendering something else during it (a hydration mismatch).
 */
const serverPermission = (): Permission => 'unsupported';

async function requestPermission(): Promise<void> {
    await Notification.requestPermission();
    permissionListeners.forEach((listener) => listener());
}

/**
 * Asks the browser for leave to show notification pop-ups. Browsers only
 * ask on a click, so this is a button; once decided it says how things
 * stand (allowed, or blocked in the site settings).
 */
export function BrowserNotificationToggle() {
    const permission = useSyncExternalStore(
        subscribe,
        readPermission,
        serverPermission,
    );

    if (permission === 'unsupported') {
        return null;
    }

    if (permission === 'granted') {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                <BellRing className="size-3.5 text-primary" />
                Notifikasi browser aktif
            </span>
        );
    }

    if (permission === 'denied') {
        return (
            <span className="text-xs text-muted-foreground">
                Notifikasi browser diblokir. Izinkan dari pengaturan situs di
                browser.
            </span>
        );
    }

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => void requestPermission()}
        >
            <BellRing className="size-4" />
            Aktifkan Notifikasi Browser
        </Button>
    );
}
