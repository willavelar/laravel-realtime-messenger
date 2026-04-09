<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Services\ConversationService;
use App\Modules\Users\Models\User;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CreateDmMutation
{
    public function __construct(private ConversationService $conversationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        $recipient = User::findOrFail($args['recipientId']);
        return $this->conversationService->createDm($context->user(), $recipient);
    }
}
