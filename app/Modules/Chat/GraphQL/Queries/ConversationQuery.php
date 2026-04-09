<?php

namespace App\Modules\Chat\GraphQL\Queries;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ConversationService;
use Illuminate\Auth\Access\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ConversationQuery
{
    public function __construct(private ConversationService $conversationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): Conversation
    {
        $conversation = Conversation::with(['participants'])
            ->findOrFail($args['id']);

        if (! $this->conversationService->isParticipant($conversation, $context->user())) {
            throw new AuthorizationException('You are not a participant of this conversation.');
        }

        return $conversation;
    }
}
