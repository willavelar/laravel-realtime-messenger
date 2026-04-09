<?php

return [
    App\Providers\AppServiceProvider::class,
    Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider::class,
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    App\Modules\Users\Providers\UsersServiceProvider::class,
    App\Modules\Chat\Providers\ChatServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
];
