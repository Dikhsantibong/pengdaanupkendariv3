import { configureEcho } from '@laravel/echo-react';
import { xsrfHeader } from '@/lib/notification-feed';

/** Whether Reverb is configured for this build (its key reached Vite). */
export const realtimeEnabled = (): boolean =>
    typeof window !== 'undefined' &&
    Boolean(import.meta.env.VITE_REVERB_APP_KEY);

/**
 * Connect Laravel Echo to Reverb. The private channels are authorised with
 * the session's XSRF cookie, read at each request: the page carries no
 * csrf-token meta tag, and the cookie stays right after signing in or
 * switching accounts without a full reload.
 */
export function setupEcho(): void {
    if (!realtimeEnabled()) {
        return;
    }

    configureEcho({
        broadcaster: 'reverb',
        channelAuthorization: {
            customHandler: (
                {
                    socketId,
                    channelName,
                }: { socketId: string; channelName: string },
                callback: (
                    error: Error | null,
                    data: { auth: string } | null,
                ) => void,
            ) => {
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...xsrfHeader(),
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channelName,
                    }),
                })
                    .then((response) =>
                        response.ok
                            ? response.json()
                            : Promise.reject(
                                  new Error(
                                      `Otorisasi kanal gagal (${response.status})`,
                                  ),
                              ),
                    )
                    .then((data: { auth: string }) => callback(null, data))
                    .catch((error: Error) => callback(error, null));
            },
        },
    });
}
