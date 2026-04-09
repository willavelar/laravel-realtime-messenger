# Real-Time Communication Platform

A modular Laravel 11 monolith for real-time messaging with direct messages (DMs) and group conversations. Exposes a GraphQL API via [Lighthouse](https://lighthouse-php.com), delivers real-time events via [Laravel Reverb](https://reverb.laravel.com) (WebSocket), and sends push/email notifications through external services via a gRPC gateway.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Authentication | Laravel Sanctum (API tokens) |
| GraphQL API | Lighthouse 6 |
| WebSocket | Laravel Reverb |
| Queue / Cache | Redis |
| Database | MySQL 8 (production), SQLite in-memory (tests) |
| Push / Email gateway | gRPC (`grpc/grpc` + `google/protobuf`) |
| Containerization | Docker + Docker Compose |

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Client (Web/Mobile)                │
│                                                      │
│  GraphQL HTTP  ─────────────────────────────────┐   │
│  GraphQL WS (Subscriptions) ──────────────┐     │   │
│  WebSocket (Reverb) ───────────────────┐  │     │   │
└───────────────────────────────────────┼──┼─────┘   │
                                        │  │
┌───────────────────────────────────────▼──▼──────────┐
│                  Laravel Application                 │
│                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │
│  │  Module  │  │  Module  │  │      Module      │   │
│  │   Auth   │  │   Chat   │  │  Notifications   │   │
│  └──────────┘  └────┬─────┘  └────────┬─────────┘   │
│                     │                 │              │
│         ┌───────────▼─────────────────▼───────────┐  │
│         │        Laravel Events / Queue (Redis)    │  │
│         └────────────────────────────────────────┘  │
│                                                      │
│  ┌───────────────────────────────────────────────┐   │
│  │        Laravel Reverb (WebSocket Server)      │   │
│  └───────────────────────────────────────────────┘   │
└─────────────────────────────────────────┬────────────┘
                                          │ gRPC
              ┌───────────────────────────▼───────────┐
              │          External Services             │
              │  Firebase FCM  │  Mailgun / SES        │
              └────────────────────────────────────────┘
```

**Each layer's responsibility:**

- **GraphQL HTTP** — queries and mutations (on-demand data)
- **WebSocket (Reverb)** — real-time message and notification delivery
- **GraphQL Subscriptions** — typed event streams for clients
- **Redis Queue** — decouples notification processing from the request cycle
- **gRPC** — outbound gateway to external push and email services (called from queue jobs, never in the request cycle)

## Modules

```
app/Modules/
├── Auth/          — register, login, logout (Sanctum tokens)
├── Users/         — user profiles, online presence
├── Chat/          — conversations (DM & group), messages, broadcasting
└── Notifications/ — in-app notifications, push & email via gRPC
```

Each module owns its `Models/`, `Services/`, `GraphQL/`, `Events/`, `Jobs/`, and `Providers/`.

## GraphQL API

### Auth

```graphql
mutation Register {
  register(input: {
    name: "Alice"
    email: "alice@example.com"
    password: "secret123"
    password_confirmation: "secret123"
  }) {
    token
    user { id name email }
  }
}

mutation Login {
  login(input: { email: "alice@example.com", password: "secret123" }) {
    token
    user { id email }
  }
}

mutation Logout {
  logout  # requires Authorization: Bearer <token>
}
```

### Chat

```graphql
# Create a DM or group conversation
mutation { createDm(recipientId: "2") { id type } }
mutation { createGroup(name: "Team Alpha", userIds: ["2", "3"]) { id name } }

# Send, edit, and delete messages
mutation { sendMessage(conversationId: "1", body: "Hello!") { id body sender { name } } }
mutation { editMessage(messageId: "5", body: "Hello, world!") { id body editedAt } }
mutation { deleteMessage(messageId: "5") }

# Query a conversation with paginated messages
query {
  conversation(id: "1") {
    id type
    participants { id name isOnline }
    messages(first: 20, page: 1) { id body sender { name } createdAt }
  }
}

# Real-time subscription
subscription {
  onMessageReceived(conversationId: "1") {
    id body sender { id name }
  }
}
```

### Notifications

```graphql
query { notifications { id type data readAt } }
query { unreadNotificationsCount }

mutation { markNotificationAsRead(id: "3") { id readAt } }
mutation { markAllNotificationsAsRead }

subscription {
  onNotificationReceived {
    id type data createdAt
  }
}
```

### Presence

```graphql
query { me { id name isOnline lastSeenAt } }

subscription {
  onUserPresenceChanged {
    id name isOnline lastSeenAt
  }
}
```

## Real-Time Message Flow

```
1. Client  →  GraphQL mutation sendMessage(conversationId, body)
2. ChatService persists Message in the database
3. MessageSent event dispatched
   └── Reverb broadcasts to private channel conversation.{id}
       └── Connected clients receive the message instantly
4. NotifyParticipants job enqueued in Redis
5. Worker processes job:
   ├── Persists AppNotification for each recipient
   ├── Dispatches SendPushNotification job  →  gRPC → Firebase FCM
   └── Dispatches SendEmailNotification job →  gRPC → Mailgun/SES
       (only if recipient has been offline for more than 5 minutes)
```

## gRPC Notification Service

The proto definition lives at `proto/notifications.proto`:

```protobuf
service NotificationService {
  rpc SendPush(PushRequest)   returns (PushResponse);
  rpc SendEmail(EmailRequest) returns (EmailResponse);
}
```

The gateway is abstracted behind `NotificationGatewayInterface`, making it trivially swappable in tests (`FakeNotificationGateway`) and replaceable with any other implementation without touching the job logic.

## Getting Started

### Prerequisites

- Docker and Docker Compose

### Setup

```bash
git clone https://github.com/your-username/laravel-grpc-graphql-websocket.git
cd laravel-grpc-graphql-websocket

cp .env.example .env
# Edit .env — set DB_ROOT_PASSWORD, DB_PASSWORD, and any other secrets

docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The following services will be running:

| Service | URL |
|---|---|
| GraphQL API | `http://localhost:8000/graphql` |
| GraphQL Playground | `http://localhost:8000/graphql-playground` |
| WebSocket (Reverb) | `ws://localhost:8080` |
| MySQL | `localhost:3306` |
| Redis | `localhost:6379` |

### Running Tests

Tests use SQLite in-memory — no external database required:

```bash
# Locally (requires PHP 8.2+ and Composer)
php artisan test

# Inside Docker
docker compose exec app php artisan test
```

Expected output:

```
PASS  Tests\Unit\Chat\ChatServiceTest                      6 tests
PASS  Tests\Unit\Notifications\NotifyParticipantsJobTest   3 tests
PASS  Tests\Feature\Auth\AuthTest                          3 tests
PASS  Tests\Feature\Chat\SendMessageTest                   1 test
PASS  Tests\Feature\Notifications\NotificationTest         3 tests

Tests: 16 passed (28 assertions)
```

### Regenerating gRPC Stubs

The repository ships hand-written proto stubs under `app/Modules/Notifications/gRPC/Generated/` so local development works without the `grpc` PHP extension. Inside Docker (where the extension is installed), regenerate them from the proto definition:

```bash
docker compose exec app bash -c "
  protoc --proto_path=proto \
    --php_out=app/Modules/Notifications/gRPC/Generated \
    --grpc_out=app/Modules/Notifications/gRPC/Generated \
    --plugin=protoc-gen-grpc=\$(which grpc_php_plugin) \
    proto/notifications.proto
"
```

## Project Structure

```
app/
  GraphQL/Scalars/MixedScalar.php         ← custom JSON scalar
  Modules/
    Auth/
      Services/AuthService.php
      GraphQL/Mutations/{Login,Register,Logout}Mutation.php
      Providers/AuthServiceProvider.php
    Users/
      Models/User.php
      Services/{UserService,PresenceService}.php
      Events/{UserConnected,UserDisconnected}.php
      GraphQL/Queries/MeQuery.php
      GraphQL/Subscriptions/UserPresenceSubscription.php
      Providers/UsersServiceProvider.php
    Chat/
      Models/{Conversation,ConversationParticipant,Message}.php
      Services/{ChatService,ConversationService}.php
      Events/MessageSent.php
      Jobs/NotifyParticipants.php
      Channels/ConversationChannel.php
      GraphQL/{Queries,Mutations,Subscriptions}/...
      Providers/ChatServiceProvider.php
    Notifications/
      Models/AppNotification.php
      Services/NotificationService.php
      Contracts/NotificationGatewayInterface.php
      Gateways/{GrpcNotificationGateway,FakeNotificationGateway}.php
      Jobs/{SendPushNotification,SendEmailNotification}.php
      gRPC/Generated/...
      GraphQL/{Queries,Mutations,Subscriptions}/...
      Providers/NotificationsServiceProvider.php

graphql/
  schema.graphql         ← root schema (imports all modules)
  auth.graphql
  users.graphql
  chat.graphql
  notifications.graphql

proto/
  notifications.proto

database/migrations/
  *_create_users_table.php
  *_create_conversations_table.php
  *_create_conversation_participants_table.php
  *_create_messages_table.php
  *_create_app_notifications_table.php
```

## WebSocket Authentication

Private channel access follows the standard Reverb/Pusher auth flow:

```
1. Client logs in  →  receives Sanctum token
2. Client connects to WebSocket with token in the handshake
3. Laravel authenticates via /broadcasting/auth
4. Private channels (conversation.{id}) verify the user is a participant
```

## Error Handling

| Layer | Strategy |
|---|---|
| GraphQL | Errors returned in the `errors[]` field; validation via `@rules` directives |
| WebSocket | Auto-reconnect on client; private channels return 403 on unauthorized auth |
| Queue jobs | `tries: 3`, `backoff: [10, 60, 300]` s; gateway failures throw to trigger retries |
| gRPC | Per-call timeout; failures logged and retried via job backoff; never blocks message persistence |
| Auth | Invalid token → 401 on both GraphQL and the broadcasting auth route |

## License

MIT
