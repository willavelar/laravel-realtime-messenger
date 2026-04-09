<?php

namespace App\Modules\Chat\Providers;

use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\Chat\Services\ChatService::class,
        );
        $this->app->singleton(
            \App\Modules\Chat\Services\ConversationService::class,
        );
    }

    public function boot(): void {}
}
