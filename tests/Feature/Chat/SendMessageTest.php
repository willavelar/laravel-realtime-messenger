<?php

namespace Tests\Feature\Chat;

use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Jobs\NotifyParticipants;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_message_broadcasts_event_and_queues_job(): void
    {
        Event::fake([MessageSent::class]);
        Queue::fake();

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $conversationService = new ConversationService();
        $conversation = $conversationService->createDm($sender, $receiver);

        $this->actingAs($sender)->postGraphQL([
            'query' => '
                mutation($conversationId: ID!, $body: String!) {
                    sendMessage(conversationId: $conversationId, body: $body) {
                        id body
                        sender { id name }
                    }
                }
            ',
            'variables' => [
                'conversationId' => $conversation->id,
                'body' => 'Hello!',
            ],
        ]);

        Event::assertDispatched(MessageSent::class);
        Queue::assertPushed(NotifyParticipants::class);
    }
}
