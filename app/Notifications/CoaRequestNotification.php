<?php

namespace App\Notifications;

use App\Models\CoaRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CoaRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CoaRequest $coaRequest,
        public string $title,
        public string $message,
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
            'coa_request_id' => $this->coaRequest->id,
            'coa_code' => $this->coaRequest->coa_code,
            'url' => route('coa-requests.index'),
        ];
    }
}
