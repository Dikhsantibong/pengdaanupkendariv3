<?php

namespace App\Notifications;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProposalSubmitted extends Notification
{
    use Queueable;

    public function __construct(public Procurement $procurement) {}

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
            'title' => 'Usulan pekerjaan menunggu persetujuan',
            'message' => "Usulan {$this->procurement->name} menunggu persetujuan Anda.",
            'procurement_id' => $this->procurement->id,
            'procurement_number' => $this->procurement->number,
        ];
    }
}
