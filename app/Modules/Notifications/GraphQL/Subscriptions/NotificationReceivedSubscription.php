<?php

namespace App\Modules\Notifications\GraphQL\Subscriptions;

use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class NotificationReceivedSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return $subscriber->context->user() !== null;
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        /** @var AppNotification $root */
        return (int) $root->user_id === (int) $subscriber->context->user()->id;
    }
}
