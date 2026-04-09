<?php

namespace App\Modules\Chat\GraphQL\Subscriptions;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ConversationService;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class MessageReceivedSubscription extends GraphQLSubscription
{
    public function __construct(private ConversationService $conversationService) {}

    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        $user = $subscriber->context->user();
        $conversationId = $subscriber->args['conversationId'];
        $conversation = Conversation::find($conversationId);

        return $conversation && $this->conversationService->isParticipant($conversation, $user);
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        return (string) $root->conversation_id === (string) $subscriber->args['conversationId'];
    }
}
