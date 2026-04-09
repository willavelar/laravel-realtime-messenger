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

class SendPushNotification implements ShouldQueue
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
        $data = $this->notification->data ?? [];

        $success = $gateway->sendPush(
            deviceTokens: $this->recipient->device_tokens ?? [],
            title: $data['sender_name'] ?? 'Nova mensagem',
            body: $data['preview'] ?? '',
            data: ['notification_id' => (string) $this->notification->id],
        );

        if (! $success) {
            Log::warning('Push notification failed', ['notification_id' => $this->notification->id]);
            throw new \RuntimeException('Push notification delivery failed — will retry');
        }
    }
}
