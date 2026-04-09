<?php

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Models\AppNotification;

interface NotificationGatewayInterface
{
    public function send(AppNotification $notification): void;
}
