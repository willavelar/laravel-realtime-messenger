<?php

namespace App\Modules\Users\Providers;

use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\Users\Services\UserService::class,
        );
        $this->app->singleton(
            \App\Modules\Users\Services\PresenceService::class,
        );
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Users\Events\UserConnected::class,
            function (\App\Modules\Users\Events\UserConnected $event) {
                $presenceService = app(\App\Modules\Users\Services\PresenceService::class);
                $user = $presenceService->markOnline($event->user);
                \Nuwave\Lighthouse\Execution\Utils\Subscription::broadcast('onUserPresenceChanged', $user);
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\Users\Events\UserDisconnected::class,
            function (\App\Modules\Users\Events\UserDisconnected $event) {
                $presenceService = app(\App\Modules\Users\Services\PresenceService::class);
                $user = $presenceService->markOffline($event->user);
                \Nuwave\Lighthouse\Execution\Utils\Subscription::broadcast('onUserPresenceChanged', $user);
            }
        );
    }
}
