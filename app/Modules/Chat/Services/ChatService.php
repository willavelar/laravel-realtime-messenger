<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Models\Message;
use App\Modules\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ChatService
{
    public function sendMessage(User $sender, int $conversationId, string $body): Message
    {
        $message = Message::create([
            'conversation_id' => $conversationId,
            'user_id' => $sender->id,
            'body' => $body,
        ]);

        $message->load('sender', 'conversation.participants');

        // Events and jobs dispatched in Task 8
        if (class_exists(\App\Modules\Chat\Events\MessageSent::class)) {
            event(new \App\Modules\Chat\Events\MessageSent($message));
        }
        if (class_exists(\App\Modules\Chat\Jobs\NotifyParticipants::class)) {
            \App\Modules\Chat\Jobs\NotifyParticipants::dispatch($message);
        }

        return $message;
    }

    public function editMessage(Message $message, User $editor, string $newBody): Message
    {
        if ($message->user_id !== $editor->id) {
            throw new AuthorizationException('You cannot edit this message.');
        }

        $message->update(['body' => $newBody, 'edited_at' => now()]);

        return $message->fresh();
    }

    public function deleteMessage(Message $message, User $deleter): void
    {
        if ($message->user_id !== $deleter->id) {
            throw new AuthorizationException('You cannot delete this message.');
        }

        $message->delete();
    }
}
