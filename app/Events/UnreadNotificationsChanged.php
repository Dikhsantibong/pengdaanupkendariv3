<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user's unread notifications changed: a new one arrived, or some were
 * read. Sent at once over Reverb to the user's private channel, carrying
 * the whole snapshot, so every open tab updates its bell, its Dashboard
 * and (for a new one) shows the pop-up without asking the server again.
 */
class UnreadNotificationsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array{total: int, items: array<int, array<string, mixed>>}  $feed
     */
    public function __construct(
        public int $userId,
        public array $feed,
        public ?string $newId = null,
    ) {}

    /**
     * The user's own private channel.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    /**
     * The name the app listens for.
     */
    public function broadcastAs(): string
    {
        return 'notifications.changed';
    }

    /**
     * @return array{feed: array{total: int, items: array<int, array<string, mixed>>}, new_id: string|null}
     */
    public function broadcastWith(): array
    {
        return ['feed' => $this->feed, 'new_id' => $this->newId];
    }
}
