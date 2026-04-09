<?php

namespace App\Modules\Notifications\GraphQL\Mutations;

use App\Modules\Notifications\Services\NotificationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MarkAllAsReadMutation
{
    public function __construct(private NotificationService $notificationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        $this->notificationService->markAllAsRead($context->user());
        return true;
    }
}
