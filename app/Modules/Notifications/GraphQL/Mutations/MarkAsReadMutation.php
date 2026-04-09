<?php

namespace App\Modules\Notifications\GraphQL\Mutations;

use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Services\NotificationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MarkAsReadMutation
{
    public function __construct(private NotificationService $notificationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): AppNotification
    {
        $notification = AppNotification::findOrFail($args['id']);
        return $this->notificationService->markAsRead($notification, $context->user());
    }
}
