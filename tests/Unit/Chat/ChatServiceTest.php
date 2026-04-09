<?php

namespace Tests\Unit\Chat;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ChatService;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatService $chatService;
    private ConversationService $conversationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversationService = new ConversationService();
        $this->chatService = new ChatService();
    }

    public function test_can_create_dm_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = $this->conversationService->createDm($userA, $userB);

        $this->assertEquals('dm', $conversation->type);
        $this->assertCount(2, $conversation->participants);
    }

    public function test_cannot_create_duplicate_dm(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->conversationService->createDm($userA, $userB);
        $second = $this->conversationService->createDm($userA, $userB);

        $this->assertCount(1, Conversation::all());
        $this->assertEquals($second->id, Conversation::first()->id);
    }

    public function test_can_send_message(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = $this->conversationService->createDm($sender, $receiver);

        $message = $this->chatService->sendMessage(
            sender: $sender,
            conversationId: $conversation->id,
            body: 'Hello!',
        );

        $this->assertEquals('Hello!', $message->body);
        $this->assertEquals($sender->id, $message->user_id);
    }

    public function test_can_edit_message(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = $this->conversationService->createDm($sender, $receiver);
        $message = $this->chatService->sendMessage($sender, $conversation->id, 'Original');

        $edited = $this->chatService->editMessage($message, $sender, 'Edited body');

        $this->assertEquals('Edited body', $edited->body);
        $this->assertNotNull($edited->edited_at);
    }

    public function test_non_sender_cannot_edit_message(): void
    {
        $sender = User::factory()->create();
        $other = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = $this->conversationService->createDm($sender, $receiver);
        $message = $this->chatService->sendMessage($sender, $conversation->id, 'Hello');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->chatService->editMessage($message, $other, 'Hacked');
    }

    public function test_can_soft_delete_message(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = $this->conversationService->createDm($sender, $receiver);
        $message = $this->chatService->sendMessage($sender, $conversation->id, 'Bye');

        $this->chatService->deleteMessage($message, $sender);

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }
}
