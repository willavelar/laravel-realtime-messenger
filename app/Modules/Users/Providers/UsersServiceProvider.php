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

    public function boot(): void {}
}
