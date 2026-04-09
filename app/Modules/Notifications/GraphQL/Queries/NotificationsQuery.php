<?php

namespace App\Modules\Notifications\GraphQL\Queries;

use App\Modules\Notifications\Services\NotificationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NotificationsQuery
{
    public function __construct(private NotificationService $notificationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        return $this->notificationService->getForUser($context->user());
    }
}
