# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
php artisan test

# Run a single test file
php artisan test tests/Feature/Chat/SendMessageTest.php

# Run a single test method
php artisan test --filter test_can_send_message

# Run tests by directory
php artisan test tests/Unit/Chat/

# Code style (Laravel Pint)
./vendor/bin/pint

# Clear GraphQL schema cache (required after changing .graphql files)
php artisan lighthouse:clear-cache

# Start Reverb WebSocket server (requires Docker for Redis)
php artisan reverb:start

# Start queue worker
php artisan queue:work
```

## Architecture

### Modular Monolith

All application code lives under `app/Modules/{Auth,Users,Chat,Notifications}/`. Each module has its own `Models/`, `Services/`, `GraphQL/`, `Events/`, `Jobs/`, `Providers/`. Modules register themselves via service providers in `bootstrap/providers.php` — that file is the canonical list of what is active.

**Provider registration order matters:** `SubscriptionServiceProvider` must come before module providers (it is second in `bootstrap/providers.php`).

### GraphQL Layer (Lighthouse)

Schema is split across `graphql/*.graphql` files and composed via `#import` directives in `graphql/schema.graphql`. Each module owns its own `.graphql` file. Resolvers are plain PHP classes with `__invoke(mixed $root, array $args, GraphQLContext $context)`. All protected operations use `@guard` (Sanctum token required).

Custom scalar `Mixed` (at `app/GraphQL/Scalars/MixedScalar.php`) is used for JSON fields — declared in `schema.graphql`, used as `Mixed!` in `notifications.graphql`.

### Service Layer

Services contain all business logic. GraphQL resolvers are thin wrappers that: validate auth/access, call a service, return the result. Direct Eloquent queries in resolvers are acceptable only for fetching by primary key (`findOrFail`).

`NotificationGatewayInterface` is the seam between the application and external push/email services. In production it resolves to `GrpcNotificationGateway`; in all tests it is overridden to `FakeNotificationGateway` via `TestCase::setUp()`.

### Broadcasting (Reverb)

`MessageSent` implements `ShouldBroadcastNow` (synchronous, bypasses queue) and broadcasts to the private channel `conversation.{id}`. Channel authorization is in `app/Modules/Chat/Channels/ConversationChannel.php`, registered in `routes/channels.php`.

Presence events (`UserConnected`, `UserDisconnected`) are dispatched manually — there is no Reverb lifecycle hook wired up yet (Reverb beta limitation). The listeners in `UsersServiceProvider::boot()` handle the `is_online` update and broadcast the `onUserPresenceChanged` subscription.

### Async Notification Pipeline

```
sendMessage()
  → event(MessageSent)          // synchronous Reverb broadcast
  → NotifyParticipants::dispatch // enqueued
      → NotificationService::createForUser()   // persists in app_notifications
      → SendPushNotification::dispatch          // gRPC via NotificationGatewayInterface
      → SendEmailNotification::dispatch         // only if offline > 5 minutes
```

Jobs use `$tries = 3`, `$backoff = [10, 60, 300]`. Gateway `false` responses throw `RuntimeException` to trigger retries.

### gRPC Stubs

`app/Modules/Notifications/gRPC/Generated/` contains **hand-written stubs** that mimic `protoc` output without requiring the `grpc` PHP extension locally. These must be regenerated with `protoc` inside Docker before deploying. The real proto definition is `proto/notifications.proto`. `GrpcNotificationGateway` uses lazy client initialization (via `getClient()`) to avoid extension-load failures at container build time.

## Testing

Tests use SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Configuration is in both `phpunit.xml` (env vars) and `.env.testing` (additional Lighthouse-specific vars like `LIGHTHOUSE_SUBSCRIPTION_STORAGE=array`).

`Tests\TestCase` includes two things all tests need:
- `MakesGraphQLRequests` trait (Lighthouse) — provides `postGraphQL()` and `actingAs()->postGraphQL()`
- `setUp()` binding `FakeNotificationGateway` — prevents real gRPC calls in every test

Feature tests that dispatch jobs or events typically use `Queue::fake()` and/or `Event::fake()` before acting.

## Key Conventions

- **Resolver authorization:** always check `isParticipant()` in Chat resolvers; throw `AuthorizationException` on failure (not return null).
- **Eager loading:** load only what Lighthouse cannot handle lazily. Do not pre-load paginated relations in query resolvers — Lighthouse's `@hasMany @orderBy` handles them.
- **AppNotification.data** is cast as `array` in the model. The GraphQL field is typed `Mixed!` (not `String!`) — do not revert this.
- **New modules** need: a service provider registered in `bootstrap/providers.php`, a `.graphql` file imported in `schema.graphql`, and a binding check if they introduce an interface.
