<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Services\ConversationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CreateGroupMutation
{
    public function __construct(private ConversationService $conversationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        return $this->conversationService->createGroup(
            creator: $context->user(),
            name: $args['name'],
            userIds: array_map('intval', $args['userIds']),
        );
    }
}
