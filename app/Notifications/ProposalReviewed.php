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
        /** Approved by the team leader and passed on to the Asman. */
        public bool $forwarded = false,
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
            'title' => match (true) {
                ! $this->approved => 'Usulan pekerjaan dikembalikan',
                $this->forwarded => 'Usulan pekerjaan disetujui TL',
                default => 'Usulan pekerjaan disetujui',
            },
            'message' => $this->forwarded
                ? "Usulan {$this->procurement->name} disetujui TL dan menunggu persetujuan Asman."
                : ($this->approved
                ? "Usulan {$this->procurement->name} disetujui Direksi Pekerjaan dan diteruskan ke perencanaan."
                : "Usulan {$this->procurement->name} dikembalikan untuk diperbaiki.".($this->note !== null ? " Catatan: {$this->note}" : '')),
            'procurement_id' => $this->procurement->id,
            'procurement_number' => $this->procurement->number,
        ];
    }
}
