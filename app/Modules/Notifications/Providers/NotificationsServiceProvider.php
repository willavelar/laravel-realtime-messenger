<?php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;
use App\Modules\Notifications\Gateways\GrpcNotificationGateway;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\Notifications\Services\NotificationService::class,
        );
        $this->app->bind(
            NotificationGatewayInterface::class,
            GrpcNotificationGateway::class,
        );
    }

    public function boot(): void {}
}
