<?php

namespace App\Notifications;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A procurement reached a new stage: planning approved, handed to Keuangan,
 * or paid and closed. Keeps the proposing division and Keuangan informed.
 */
class ProcurementMilestone extends Notification
{
    use Queueable;

    public function __construct(
        public Procurement $procurement,
        public string $title,
        public string $message,
        // The tab of the procurement it is about (amandemen, do), if any.
        public ?string $tab = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'procurement_id' => $this->procurement->id,
            'procurement_number' => $this->procurement->number,
            'tab' => $this->tab,
        ];
    }
}
