<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public AppNotification $notification,
        public User $recipient,
    ) {}

    public function handle(NotificationGatewayInterface $gateway): void
    {
        $data = $this->notification->data;

        $success = $gateway->sendEmail(
            to: $this->recipient->email,
            subject: "Nova mensagem de {$data['sender_name']}",
            template: 'new_message',
            variables: [
                'recipient_name' => $this->recipient->name,
                'sender_name' => $data['sender_name'] ?? '',
                'preview' => $data['preview'] ?? '',
            ],
        );

        if (! $success) {
            Log::warning('Email notification failed', ['notification_id' => $this->notification->id]);
        }
    }
}
