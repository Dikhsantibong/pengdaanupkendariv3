<?php

namespace App\Support;

use App\Events\UnreadNotificationsChanged;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A user's notifications as the app shows them: the bell, the Dashboard
 * panel of unread notifications and the browser pop-ups, which poll for
 * the unread ones. Each carries where it leads: the procurement, on the tab
 * it is about (Amandemen, DO) when it says so.
 */
class NotificationFeed
{
    /**
     * The unread notifications, newest first, and how many there are.
     *
     * @return array{total: int, items: array<int, array{id: string, title: string, message: string, procurement_id: int|null, procurement_number: string|null, url: string, read_at: string|null, created_at: string}>}
     */
    public static function unread(User $user, int $limit = 5): array
    {
        return [
            'total' => $user->unreadNotifications()->count(),
            'items' => $user->unreadNotifications()
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => self::present($notification))
                ->values()
                ->all(),
        ];
    }

    /**
     * Push the user's unread notifications to their open tabs over Reverb,
     * naming the one that just arrived, if any.
     *
     * Never in the way of the work that caused it: when the Reverb server
     * cannot be reached the failure is only logged, and the open tabs catch
     * up by polling.
     */
    public static function broadcast(User $user, ?string $newId = null): void
    {
        rescue(fn () => event(new UnreadNotificationsChanged($user->id, self::unread($user, 10), $newId)));
    }

    /**
     * One notification as the screens show it.
     *
     * @return array{id: string, title: string, message: string, procurement_id: int|null, procurement_number: string|null, url: string, read_at: string|null, created_at: string}
     */
    public static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $procurement = $data['procurement_id'] ?? null;

        return [
            'id' => $notification->id,
            'title' => (string) ($data['title'] ?? 'Notifikasi'),
            'message' => (string) ($data['message'] ?? ''),
            // Still sent for a page loaded before `url` existed (an open tab
            // or a cached build), which links by it.
            'procurement_id' => $procurement,
            'procurement_number' => $data['procurement_number'] ?? null,
            'url' => $procurement === null
                ? route('notifications.index')
                : route('procurements.show', array_filter([$procurement, 'tab' => $data['tab'] ?? null])),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
