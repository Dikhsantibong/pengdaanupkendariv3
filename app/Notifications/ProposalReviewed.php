<?php

namespace App\Notifications;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProposalReviewed extends Notification
{
    use Queueable;

    public function __construct(
        public Procurement $procurement,
        public bool $approved,
        public ?string $note = null,
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
            'title' => $this->approved ? 'Usulan pekerjaan disetujui' : 'Usulan pekerjaan dikembalikan',
            'message' => $this->approved
                ? "Usulan {$this->procurement->name} disetujui Direksi Pekerjaan dan diteruskan ke perencanaan."
                : "Usulan {$this->procurement->name} dikembalikan untuk diperbaiki.".($this->note !== null ? " Catatan: {$this->note}" : ''),
            'procurement_id' => $this->procurement->id,
            'procurement_number' => $this->procurement->number,
        ];
    }
}
