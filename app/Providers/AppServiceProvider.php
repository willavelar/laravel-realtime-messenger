<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TODO: Reverb presence integration
        //
        // Laravel\Reverb\Events\ConnectionCreated does NOT exist in the installed
        // version of Reverb (beta). The available events are:
        //   - Laravel\Reverb\Events\ConnectionPruned  (carries ChannelConnection, no auth user)
        //   - Laravel\Reverb\Events\MessageReceived
        //   - Laravel\Reverb\Events\MessageSent
        //   - Laravel\Reverb\Events\ChannelCreated
        //   - Laravel\Reverb\Events\ChannelRemoved
        //
        // Reverb connection objects (Contracts\Connection) do not expose an
        // authenticated Laravel user — auth happens at the Pusher channel-auth
        // HTTP endpoint, not at the raw WebSocket level.
        //
        // Presence can be triggered by dispatching UserConnected / UserDisconnected
        // manually from application code or a future Reverb version that exposes
        // an auth-aware connection event.
        //
        // Example (for future use when the API stabilises):
        //
        // \Illuminate\Support\Facades\Event::listen(
        //     \Laravel\Reverb\Events\ConnectionPruned::class,
        //     function (\Laravel\Reverb\Events\ConnectionPruned $event) {
        //         // $event->connection is a ChannelConnection; user is not available here.
        //     }
        // );
    }
}
