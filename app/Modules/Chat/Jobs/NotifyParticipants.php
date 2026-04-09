<?php

namespace App\Modules\Chat\Jobs;

use App\Modules\Chat\Models\Message;
use App\Modules\Notifications\Jobs\SendEmailNotification;
use App\Modules\Notifications\Jobs\SendPushNotification;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyParticipants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public Message $message) {}

    public function handle(NotificationService $notificationService): void
    {
        $sender = $this->message->sender;
        $participants = $this->message->conversation->participants
            ->where('id', '!=', $sender->id);

        foreach ($participants as $recipient) {
            $notification = $notificationService->createForUser(
                user: $recipient,
                type: 'message',
                data: [
                    'message_id' => $this->message->id,
                    'conversation_id' => $this->message->conversation_id,
                    'sender_name' => $sender->name,
                    'preview' => substr($this->message->body, 0, 100),
                ],
            );

            SendPushNotification::dispatch($notification, $recipient);

            $offlineSince = $recipient->last_seen_at;
            if (! $recipient->is_online && $offlineSince && $offlineSince->lt(now()->subMinutes(5))) {
                SendEmailNotification::dispatch($notification, $recipient);
            }
        }
    }
}
