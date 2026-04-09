<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ChatService;
use App\Modules\Chat\Services\ConversationService;
use Illuminate\Auth\Access\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SendMessageMutation
{
    public function __construct(
        private ChatService $chatService,
        private ConversationService $conversationService,
    ) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        $conversation = Conversation::findOrFail($args['conversationId']);

        if (! $this->conversationService->isParticipant($conversation, $context->user())) {
            throw new AuthorizationException('You are not a participant of this conversation.');
        }

        return $this->chatService->sendMessage(
            sender: $context->user(),
            conversationId: (int) $args['conversationId'],
            body: $args['body'],
        );
    }
}
