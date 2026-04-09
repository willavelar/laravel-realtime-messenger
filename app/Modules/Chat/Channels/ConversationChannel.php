<?php

namespace App\Modules\Chat\Channels;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Users\Models\User;

class ConversationChannel
{
    public function __construct(private ConversationService $conversationService) {}

    public function join(User $user, int $conversationId): array|bool
    {
        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            return false;
        }

        if (! $this->conversationService->isParticipant($conversation, $user)) {
            return false;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }
}
