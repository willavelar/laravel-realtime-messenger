<?php

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;
use App\Modules\Notifications\Models\AppNotification;

class GrpcNotificationGateway implements NotificationGatewayInterface
{
    public function send(AppNotification $notification): void
    {
        // gRPC implementation — Task 11
    }
}
