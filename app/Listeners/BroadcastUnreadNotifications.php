<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\NotificationFeed;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Pushes a stored notification to its user's open tabs over Reverb.
 *
 * Runs after the surrounding transaction commits, so nothing is announced
 * that is then rolled back.
 */
class BroadcastUnreadNotifications implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the event.
     */
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        $stored = $event->response instanceof Model ? (string) $event->response->getKey() : null;

        NotificationFeed::broadcast($event->notifiable, $stored);
    }
}
