<?php

namespace App\Modules\Users\GraphQL\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class UserPresenceSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return $subscriber->context->user() !== null;
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        return true;
    }
}
