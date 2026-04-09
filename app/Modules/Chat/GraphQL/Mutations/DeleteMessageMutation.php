<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\ChatService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class DeleteMessageMutation
{
    public function __construct(private ChatService $chatService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        $message = Message::findOrFail($args['messageId']);
        $this->chatService->deleteMessage($message, $context->user());
        return true;
    }
}
