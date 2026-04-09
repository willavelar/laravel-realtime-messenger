<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\ChatService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class EditMessageMutation
{
    public function __construct(private ChatService $chatService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): Message
    {
        $message = Message::findOrFail($args['messageId']);
        return $this->chatService->editMessage($message, $context->user(), $args['body']);
    }
}
