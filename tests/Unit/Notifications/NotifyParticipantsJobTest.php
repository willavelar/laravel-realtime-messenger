<?php

namespace Tests\Unit\Notifications;

use App\Modules\Chat\Jobs\NotifyParticipants;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Notifications\Jobs\SendEmailNotification;
use App\Modules\Notifications\Jobs\SendPushNotification;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotifyParticipantsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_notifications_and_dispatches_push_and_email(): void
    {
        Queue::fake();

        $sender = User::factory()->create(['is_online' => true]);
        $receiver = User::factory()->create(['is_online' => false, 'last_seen_at' => now()->subMinutes(10)]);

        $conversationService = new ConversationService();
        $conversation = $conversationService->createDm($sender, $receiver);

        $message = \App\Modules\Chat\Models\Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'body' => 'Hello!',
        ]);
        $message->load('sender', 'conversation.participants');

        (new NotifyParticipants($message))->handle(
            app(\App\Modules\Notifications\Services\NotificationService::class)
        );

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $receiver->id,
            'type' => 'message',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $sender->id,
        ]);

        Queue::assertPushed(SendPushNotification::class);
        Queue::assertPushed(SendEmailNotification::class);
    }
}
