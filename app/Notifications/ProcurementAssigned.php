<?php

namespace App\Notifications;

use App\Enums\ProcurementStage;
use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcurementAssigned extends Notification
{
    use Queueable;

    public function __construct(
        public Procurement $procurement,
        public ProcurementStage $stage,
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
            'title' => 'Penugasan PIC '.$this->stage->label(),
            'message' => "Anda ditunjuk sebagai PIC {$this->stage->label()} untuk {$this->procurement->name}.",
            'procurement_id' => $this->procurement->id,
            'procurement_number' => $this->procurement->number,
        ];
    }
}
