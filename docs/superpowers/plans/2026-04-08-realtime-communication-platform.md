# Real-Time Communication Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build um monólito modular Laravel 11 para comunicação em tempo real com DM/grupo, notificações in-app/push/email, GraphQL (Lighthouse), WebSocket (Reverb) e gRPC como gateway de saída para serviços externos.

**Architecture:** Módulos independentes em `app/Modules/<Nome>/` (Auth, Users, Chat, Notifications), cada um com seus Models, Services, GraphQL resolvers, Events e Jobs. Reverb serve o WebSocket; Lighthouse expõe o GraphQL; Redis desacopla filas; gRPC clients chamam serviços externos de push e email dentro dos jobs.

**Tech Stack:** Laravel 11, Laravel Reverb, Lighthouse (nuwave/lighthouse), Laravel Sanctum, grpc/grpc (PHP extension), google/protobuf, predis/predis, Redis, SQLite (testes), MySQL/PostgreSQL (produção)

---

## Estrutura de Arquivos

```
app/
  Modules/
    Auth/
      Services/AuthService.php
      GraphQL/Mutations/LoginMutation.php
      GraphQL/Mutations/RegisterMutation.php
      GraphQL/Mutations/LogoutMutation.php
      Providers/AuthServiceProvider.php
    Users/
      Models/User.php
      Services/UserService.php
      Services/PresenceService.php
      Events/UserConnected.php
      Events/UserDisconnected.php
      GraphQL/Queries/MeQuery.php
      GraphQL/Queries/UserQuery.php
      GraphQL/Subscriptions/UserPresenceSubscription.php
      Providers/UsersServiceProvider.php
    Chat/
      Models/Conversation.php
      Models/ConversationParticipant.php
      Models/Message.php
      Services/ChatService.php
      Services/ConversationService.php
      Events/MessageSent.php
      Jobs/NotifyParticipants.php
      Channels/ConversationChannel.php
      GraphQL/Queries/ConversationQuery.php
      GraphQL/Queries/MessagesQuery.php
      GraphQL/Mutations/SendMessageMutation.php
      GraphQL/Mutations/EditMessageMutation.php
      GraphQL/Mutations/DeleteMessageMutation.php
      GraphQL/Subscriptions/MessageReceivedSubscription.php
      Providers/ChatServiceProvider.php
    Notifications/
      Models/AppNotification.php
      Services/NotificationService.php
      Contracts/NotificationGatewayInterface.php
      Gateways/GrpcNotificationGateway.php
      Gateways/FakeNotificationGateway.php
      Jobs/SendPushNotification.php
      Jobs/SendEmailNotification.php
      GraphQL/Queries/NotificationsQuery.php
      GraphQL/Queries/UnreadCountQuery.php
      GraphQL/Mutations/MarkAsReadMutation.php
      GraphQL/Mutations/MarkAllAsReadMutation.php
      GraphQL/Subscriptions/NotificationReceivedSubscription.php
      Providers/NotificationsServiceProvider.php

graphql/
  schema.graphql        ← root schema (imports all modules)
  auth.graphql
  users.graphql
  chat.graphql
  notifications.graphql

proto/
  notifications.proto

database/migrations/
  *_create_users_table.php          ← modify default
  *_add_presence_to_users_table.php
  *_create_conversations_table.php
  *_create_conversation_participants_table.php
  *_create_messages_table.php
  *_create_app_notifications_table.php

config/
  grpc.php

routes/
  channels.php   ← broadcasting auth

tests/
  Feature/
    Auth/AuthTest.php
    Chat/SendMessageTest.php
    Chat/ConversationTest.php
    Notifications/NotificationTest.php
  Unit/
    Chat/ChatServiceTest.php
    Notifications/NotifyParticipantsJobTest.php
```

---

## Task 1: Criação do Projeto Laravel e Instalação de Dependências

**Files:**
- Create: `composer.json` (via composer create-project)
- Modify: `bootstrap/providers.php`
- Modify: `config/broadcasting.php`
- Modify: `.env`

- [ ] **Step 1: Criar projeto Laravel 11**

```bash
cd /home/willavelar/Projects/MyOwn/PHP/Laravel/gRPC-GraphQL-Websocket
composer create-project laravel/laravel app "^11.0"
mv app/* app/.env.example .
rm -rf app
```

> Nota: se preferir criar direto na raiz, use `composer create-project laravel/laravel . "^11.0"` (mas confirme que o diretório está vazio antes).

- [ ] **Step 2: Instalar dependências principais**

```bash
composer require laravel/reverb:"@beta" nuwave/lighthouse laravel/sanctum predis/predis
```

- [ ] **Step 3: Instalar dependências gRPC**

```bash
composer require grpc/grpc google/protobuf
```

> Requer a extensão PHP `grpc`. Instale via PECL: `pecl install grpc` e adicione `extension=grpc.so` no `php.ini`.

- [ ] **Step 4: Publicar configs do Reverb, Sanctum e Lighthouse**

```bash
php artisan reverb:install
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider" --tag=lighthouse-config
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider" --tag=lighthouse-schema
```

- [ ] **Step 5: Configurar `.env` para Redis e Reverb**

```dotenv
APP_NAME="RealtimePlatform"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=realtime_platform
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

GRPC_NOTIFICATION_HOST=localhost
GRPC_NOTIFICATION_PORT=50051
```

- [ ] **Step 6: Configurar `config/broadcasting.php` para Reverb**

Verifique que o driver `reverb` está configurado (já vem após `reverb:install`). O arquivo deve conter:

```php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST'),
        'port' => env('REVERB_PORT', 443),
        'scheme' => env('REVERB_SCHEME', 'https'),
        'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
    ],
    'client_options' => [],
],
```

- [ ] **Step 7: Commitar setup inicial**

```bash
git init
git add .
git commit -m "chore: initial Laravel 11 setup with Reverb, Lighthouse, Sanctum, gRPC"
```

---

## Task 2: Estrutura de Módulos e Autoloading

**Files:**
- Modify: `composer.json`
- Create: `app/Modules/Auth/Providers/AuthServiceProvider.php`
- Create: `app/Modules/Users/Providers/UsersServiceProvider.php`
- Create: `app/Modules/Chat/Providers/ChatServiceProvider.php`
- Create: `app/Modules/Notifications/Providers/NotificationsServiceProvider.php`
- Modify: `bootstrap/providers.php`

- [ ] **Step 1: Adicionar PSR-4 para módulos no `composer.json`**

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "App\\Modules\\Auth\\": "app/Modules/Auth/",
        "App\\Modules\\Users\\": "app/Modules/Users/",
        "App\\Modules\\Chat\\": "app/Modules/Chat/",
        "App\\Modules\\Notifications\\": "app/Modules/Notifications/"
    }
}
```

- [ ] **Step 2: Criar diretórios dos módulos**

```bash
mkdir -p app/Modules/{Auth,Users,Chat,Notifications}/{Models,Services,GraphQL/{Queries,Mutations,Subscriptions},Events,Jobs,Providers}
mkdir -p app/Modules/Notifications/{Contracts,Gateways}
mkdir -p app/Modules/Chat/Channels
```

- [ ] **Step 3: Criar `AuthServiceProvider`**

```php
// app/Modules/Auth/Providers/AuthServiceProvider.php
<?php

namespace App\Modules\Auth\Providers;

use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\Auth\Services\AuthService::class,
        );
    }

    public function boot(): void {}
}
```

- [ ] **Step 4: Criar `UsersServiceProvider`**

```php
// app/Modules/Users/Providers/UsersServiceProvider.php
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
```

- [ ] **Step 5: Criar `ChatServiceProvider`**

```php
// app/Modules/Chat/Providers/ChatServiceProvider.php
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

    public function boot(): void
    {
        require_once base_path('routes/channels.php');
    }
}
```

- [ ] **Step 6: Criar `NotificationsServiceProvider`**

```php
// app/Modules/Notifications/Providers/NotificationsServiceProvider.php
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
```

- [ ] **Step 7: Registrar providers em `bootstrap/providers.php`**

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    App\Modules\Users\Providers\UsersServiceProvider::class,
    App\Modules\Chat\Providers\ChatServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
];
```

- [ ] **Step 8: Dump autoload**

```bash
composer dump-autoload
```

- [ ] **Step 9: Commitar estrutura de módulos**

```bash
git add .
git commit -m "feat: modular structure with service providers"
```

---

## Task 3: Migrations do Banco de Dados

**Files:**
- Modify: `database/migrations/*_create_users_table.php`
- Create: `database/migrations/*_add_presence_to_users_table.php`
- Create: `database/migrations/*_create_conversations_table.php`
- Create: `database/migrations/*_create_conversation_participants_table.php`
- Create: `database/migrations/*_create_messages_table.php`
- Create: `database/migrations/*_create_app_notifications_table.php`

- [ ] **Step 1: Modificar migration padrão de users para incluir campos de presença e device tokens**

```php
// database/migrations/0001_01_01_000000_create_users_table.php
// Adicionar no Schema::create('users'):
$table->string('avatar_url')->nullable();
$table->text('bio')->nullable();
$table->boolean('is_online')->default(false);
$table->timestamp('last_seen_at')->nullable();
$table->json('device_tokens')->nullable(); // tokens FCM por dispositivo
```

- [ ] **Step 2: Criar migration de conversations**

```bash
php artisan make:migration create_conversations_table
```

```php
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['dm', 'group']);
    $table->string('name')->nullable();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
});
```

- [ ] **Step 3: Criar migration de conversation_participants**

```bash
php artisan make:migration create_conversation_participants_table
```

```php
Schema::create('conversation_participants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamp('joined_at')->useCurrent();
    $table->timestamp('last_read_at')->nullable();
    $table->unique(['conversation_id', 'user_id']);
});
```

- [ ] **Step 4: Criar migration de messages**

```bash
php artisan make:migration create_messages_table
```

```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('body');
    $table->timestamp('edited_at')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

- [ ] **Step 5: Criar migration de app_notifications**

```bash
php artisan make:migration create_app_notifications_table
```

```php
Schema::create('app_notifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['message', 'mention', 'system']);
    $table->json('data');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 6: Rodar migrations (com banco de teste SQLite)**

```bash
php artisan migrate --database=sqlite
```

Para isso, configure temporariamente `.env.testing`:
```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

- [ ] **Step 7: Commitar migrations**

```bash
git add database/migrations/
git commit -m "feat: database migrations for users, conversations, messages, notifications"
```

---

## Task 4: Módulo Auth — Model User e AuthService

**Files:**
- Create: `app/Modules/Users/Models/User.php`
- Create: `app/Modules/Auth/Services/AuthService.php`
- Test: `tests/Feature/Auth/AuthTest.php`

- [ ] **Step 1: Escrever teste de registro e login**

```php
// tests/Feature/Auth/AuthTest.php
<?php

namespace Tests\Feature\Auth;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postGraphQL([
            'query' => '
                mutation {
                    register(input: {
                        name: "John Doe"
                        email: "john@example.com"
                        password: "password123"
                        password_confirmation: "password123"
                    }) {
                        token
                        user { id name email }
                    }
                }
            ',
        ]);

        $response->assertJsonPath('data.register.user.email', 'john@example.com');
        $this->assertNotNull($response->json('data.register.token'));
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postGraphQL([
            'query' => '
                mutation {
                    login(input: {
                        email: "john@example.com"
                        password: "password123"
                    }) {
                        token
                        user { id email }
                    }
                }
            ',
        ]);

        $response->assertJsonPath('data.login.user.email', 'john@example.com');
        $this->assertNotNull($response->json('data.login.token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postGraphQL([
            'query' => '
                mutation {
                    login(input: {
                        email: "john@example.com"
                        password: "wrongpassword"
                    }) {
                        token
                    }
                }
            ',
        ]);

        $this->assertNotNull($response->json('errors'));
    }
}
```

- [ ] **Step 2: Rodar testes para confirmar que falham**

```bash
php artisan test tests/Feature/Auth/AuthTest.php
```

Expected: FAIL (classes não existem ainda)

- [ ] **Step 3: Criar model User**

```php
// app/Modules/Users/Models/User.php
<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'bio',
        'is_online',
        'last_seen_at',
        'device_tokens',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_online' => 'boolean',
            'device_tokens' => 'array',
        ];
    }
}
```

- [ ] **Step 4: Apontar model padrão do Laravel para o módulo**

Em `config/auth.php`, ajustar:

```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Modules\Users\Models\User::class,
    ],
],
```

- [ ] **Step 5: Criar AuthService**

```php
// app/Modules/Auth/Services/AuthService.php
<?php

namespace App\Modules\Auth\Services;

use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(string $name, string $email, string $password): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
```

- [ ] **Step 6: Criar User Factory**

```php
// database/factories/UserFactory.php
<?php

namespace Database\Factories;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
            'is_online' => false,
        ];
    }
}
```

- [ ] **Step 7: Commitar Auth base**

```bash
git add .
git commit -m "feat: User model and AuthService with Sanctum"
```

---

## Task 5: GraphQL — Schema Base e Mutations de Auth

**Files:**
- Modify: `graphql/schema.graphql`
- Create: `graphql/auth.graphql`
- Create: `app/Modules/Auth/GraphQL/Mutations/LoginMutation.php`
- Create: `app/Modules/Auth/GraphQL/Mutations/RegisterMutation.php`
- Create: `app/Modules/Auth/GraphQL/Mutations/LogoutMutation.php`

- [ ] **Step 1: Configurar schema raiz com imports**

```graphql
# graphql/schema.graphql
"A datetime string with format `Y-m-d H:i:s`."
scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")

"Indicates what fields are available at the top level of a query operation."
type Query {
    _: Boolean
}

"Mutations for creating and updating data."
type Mutation {
    _: Boolean
}

type Subscription {
    _: Boolean
}

#import auth.graphql
#import users.graphql
#import chat.graphql
#import notifications.graphql
```

- [ ] **Step 2: Criar `graphql/auth.graphql`**

```graphql
# graphql/auth.graphql
type AuthPayload {
    token: String!
    user: User!
}

input RegisterInput {
    name: String!
    email: String! @rules(apply: ["email", "unique:users,email"])
    password: String! @rules(apply: ["min:8", "confirmed"])
    password_confirmation: String!
}

input LoginInput {
    email: String!
    password: String!
}

extend type Mutation {
    register(input: RegisterInput! @spread): AuthPayload!
        @field(resolver: "App\\Modules\\Auth\\GraphQL\\Mutations\\RegisterMutation")

    login(input: LoginInput! @spread): AuthPayload!
        @field(resolver: "App\\Modules\\Auth\\GraphQL\\Mutations\\LoginMutation")

    logout: Boolean!
        @field(resolver: "App\\Modules\\Auth\\GraphQL\\Mutations\\LogoutMutation")
        @guard
}
```

- [ ] **Step 3: Criar `RegisterMutation`**

```php
// app/Modules/Auth/GraphQL/Mutations/RegisterMutation.php
<?php

namespace App\Modules\Auth\GraphQL\Mutations;

use App\Modules\Auth\Services\AuthService;

class RegisterMutation
{
    public function __construct(private AuthService $authService) {}

    public function __invoke(mixed $root, array $args): array
    {
        return $this->authService->register(
            name: $args['name'],
            email: $args['email'],
            password: $args['password'],
        );
    }
}
```

- [ ] **Step 4: Criar `LoginMutation`**

```php
// app/Modules/Auth/GraphQL/Mutations/LoginMutation.php
<?php

namespace App\Modules\Auth\GraphQL\Mutations;

use App\Modules\Auth\Services\AuthService;

class LoginMutation
{
    public function __construct(private AuthService $authService) {}

    public function __invoke(mixed $root, array $args): array
    {
        return $this->authService->login(
            email: $args['email'],
            password: $args['password'],
        );
    }
}
```

- [ ] **Step 5: Criar `LogoutMutation`**

```php
// app/Modules/Auth/GraphQL/Mutations/LogoutMutation.php
<?php

namespace App\Modules\Auth\GraphQL\Mutations;

use App\Modules\Auth\Services\AuthService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class LogoutMutation
{
    public function __construct(private AuthService $authService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        $this->authService->logout($context->user());
        return true;
    }
}
```

- [ ] **Step 6: Rodar testes de auth**

```bash
php artisan test tests/Feature/Auth/AuthTest.php
```

Expected: PASS (3 testes verdes)

- [ ] **Step 7: Commitar GraphQL Auth**

```bash
git add .
git commit -m "feat: GraphQL auth mutations (register, login, logout)"
```

---

## Task 6: Módulo Users — Queries e Presença

**Files:**
- Create: `graphql/users.graphql`
- Create: `app/Modules/Users/GraphQL/Queries/MeQuery.php`
- Create: `app/Modules/Users/GraphQL/Queries/UserQuery.php`
- Create: `app/Modules/Users/Services/UserService.php`
- Create: `app/Modules/Users/Services/PresenceService.php`
- Create: `app/Modules/Users/Events/UserConnected.php`
- Create: `app/Modules/Users/Events/UserDisconnected.php`
- Create: `app/Modules/Users/GraphQL/Subscriptions/UserPresenceSubscription.php`
- Modify: `routes/channels.php`

- [ ] **Step 1: Criar `graphql/users.graphql`**

```graphql
# graphql/users.graphql
type User {
    id: ID!
    name: String!
    email: String!
    avatar_url: String
    bio: String
    is_online: Boolean!
    last_seen_at: DateTime
}

extend type Query {
    me: User! @guard @field(resolver: "App\\Modules\\Users\\GraphQL\\Queries\\MeQuery")
    user(id: ID! @eq): User @find(model: "App\\Modules\\Users\\Models\\User") @guard
    users: [User!]! @all(model: "App\\Modules\\Users\\Models\\User") @guard
}

extend type Subscription {
    onUserPresenceChanged: User
        @subscription(class: "App\\Modules\\Users\\GraphQL\\Subscriptions\\UserPresenceSubscription")
        @guard
}
```

- [ ] **Step 2: Criar `MeQuery`**

```php
// app/Modules/Users/GraphQL/Queries/MeQuery.php
<?php

namespace App\Modules\Users\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MeQuery
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        return $context->user();
    }
}
```

- [ ] **Step 3: Criar `UserService`**

```php
// app/Modules/Users/Services/UserService.php
<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\User;

class UserService
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->update(array_filter($data));
        return $user->fresh();
    }
}
```

- [ ] **Step 4: Criar `PresenceService`**

```php
// app/Modules/Users/Services/PresenceService.php
<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\User;

class PresenceService
{
    public function markOnline(User $user): User
    {
        $user->update(['is_online' => true, 'last_seen_at' => now()]);
        return $user->fresh();
    }

    public function markOffline(User $user): User
    {
        $user->update(['is_online' => false, 'last_seen_at' => now()]);
        return $user->fresh();
    }
}
```

- [ ] **Step 5: Criar eventos de presença**

```php
// app/Modules/Users/Events/UserConnected.php
<?php

namespace App\Modules\Users\Events;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserConnected
{
    use Dispatchable;

    public function __construct(public User $user) {}
}
```

```php
// app/Modules/Users/Events/UserDisconnected.php
<?php

namespace App\Modules\Users\Events;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserDisconnected
{
    use Dispatchable;

    public function __construct(public User $user) {}
}
```

- [ ] **Step 6: Criar `UserPresenceSubscription`**

```php
// app/Modules/Users/GraphQL/Subscriptions/UserPresenceSubscription.php
<?php

namespace App\Modules\Users\GraphQL\Subscriptions;

use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class UserPresenceSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return $subscriber->context->user() !== null;
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        return true;
    }
}
```

- [ ] **Step 7: Adicionar listener de presença no `UsersServiceProvider`**

Os eventos `UserConnected` e `UserDisconnected` precisam ser ouvidos para atualizar o status e disparar a subscription. Em `UsersServiceProvider::boot()`:

```php
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
```

Para disparar esses eventos quando o Reverb conecta/desconecta, registre um listener no `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php — dentro de boot()
\Laravel\Reverb\Events\ConnectionCreated::listen(function ($event) {
    if ($user = optional($event->connection->request)->user()) {
        \App\Modules\Users\Events\UserConnected::dispatch($user);
    }
});

\Laravel\Reverb\Events\ConnectionPruned::listen(function ($event) {
    if ($user = optional($event->connection->request)->user()) {
        \App\Modules\Users\Events\UserDisconnected::dispatch($user);
    }
});
```

- [ ] **Step 9: Configurar Lighthouse para usar Pusher (Reverb) nas subscriptions**

Em `config/lighthouse.php`:

```php
'subscriptions' => [
    'broadcaster' => 'pusher',
    'storage' => [
        'driver' => 'redis',
    ],
    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'redis'),
        'queue' => 'default',
    ],
],
```

- [ ] **Step 10: Commitar módulo Users**

```bash
git add .
git commit -m "feat: Users module with presence service and GraphQL queries"
```

---

## Task 7: Módulo Chat — Models e Services

**Files:**
- Create: `app/Modules/Chat/Models/Conversation.php`
- Create: `app/Modules/Chat/Models/ConversationParticipant.php`
- Create: `app/Modules/Chat/Models/Message.php`
- Create: `app/Modules/Chat/Services/ConversationService.php`
- Create: `app/Modules/Chat/Services/ChatService.php`
- Test: `tests/Unit/Chat/ChatServiceTest.php`

- [ ] **Step 1: Escrever testes unitários para ChatService**

```php
// tests/Unit/Chat/ChatServiceTest.php
<?php

namespace Tests\Unit\Chat;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\ChatService;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatService $chatService;
    private ConversationService $conversationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversationService = new ConversationService();
        $this->chatService = new ChatService();
    }

    public function test_can_create_dm_conversation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = $this->conversationService->createDm($userA, $userB);

        $this->assertEquals('dm', $conversation->type);
        $this->assertCount(2, $conversation->participants);
    }

    public function test_cannot_create_duplicate_dm(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->conversationService->createDm($userA, $userB);
        $second = $this->conversationService->createDm($userA, $userB);

        $this->assertCount(1, Conversation::all());
        $this->assertEquals($second->id, Conversation::first()->id);
    }

    public function test_can_send_message(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = $this->conversationService->createDm($sender, $receiver);

        $message = $this->chatService->sendMessage(
            sender: $sender,
            conversationId: $conversation->id,
            body: 'Hello!',
        );

        $this->assertEquals('Hello!', $message->body);
        $this->assertEquals($sender->id, $message->user_id);
    }

    public function test_can_edit_message(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = $this->conversationService->createDm($sender, $receiver);
        $message = $this->chatService->sendMessage($sender, $conversation->id, 'Original');

        $edited = $this->chatService->editMessage($message, $sender, 'Edited body');

        $this->assertEquals('Edited body', $edited->body);
        $this->assertNotNull($edited->edited_at);
    }

    public function test_non_sender_cannot_edit_message(): void
    {
        $sender = User::factory()->create();
        $other = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = $this->conversationService->createDm($sender, $receiver);
        $message = $this->chatService->sendMessage($sender, $conversation->id, 'Hello');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->chatService->editMessage($message, $other, 'Hacked');
    }

    public function test_can_soft_delete_message(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = $this->conversationService->createDm($sender, $receiver);
        $message = $this->chatService->sendMessage($sender, $conversation->id, 'Bye');

        $this->chatService->deleteMessage($message, $sender);

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }
}
```

- [ ] **Step 2: Rodar testes para confirmar que falham**

```bash
php artisan test tests/Unit/Chat/ChatServiceTest.php
```

Expected: FAIL

- [ ] **Step 3: Criar model `Conversation`**

```php
// app/Modules/Chat/Models/Conversation.php
<?php

namespace App\Modules\Chat\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['joined_at', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
```

- [ ] **Step 4: Criar model `ConversationParticipant`**

```php
// app/Modules/Chat/Models/ConversationParticipant.php
<?php

namespace App\Modules\Chat\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'user_id', 'joined_at', 'last_read_at'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
```

- [ ] **Step 5: Criar model `Message`**

```php
// app/Modules/Chat/Models/Message.php
<?php

namespace App\Modules\Chat\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = ['conversation_id', 'user_id', 'body', 'edited_at'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
```

- [ ] **Step 6: Criar `ConversationService`**

```php
// app/Modules/Chat/Services/ConversationService.php
<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Users\Models\User;

class ConversationService
{
    public function createDm(User $userA, User $userB): Conversation
    {
        $existing = Conversation::where('type', 'dm')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userA->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userB->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::create([
            'type' => 'dm',
            'created_by' => $userA->id,
        ]);

        $conversation->participants()->attach([
            $userA->id => ['joined_at' => now()],
            $userB->id => ['joined_at' => now()],
        ]);

        return $conversation->load('participants');
    }

    public function createGroup(User $creator, string $name, array $userIds): Conversation
    {
        $conversation = Conversation::create([
            'type' => 'group',
            'name' => $name,
            'created_by' => $creator->id,
        ]);

        $participants = array_fill_keys(
            array_unique(array_merge([$creator->id], $userIds)),
            ['joined_at' => now()]
        );

        $conversation->participants()->attach($participants);

        return $conversation->load('participants');
    }

    public function isParticipant(Conversation $conversation, User $user): bool
    {
        return $conversation->participants()->where('user_id', $user->id)->exists();
    }
}
```

- [ ] **Step 7: Criar `ChatService`**

```php
// app/Modules/Chat/Services/ChatService.php
<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Jobs\NotifyParticipants;
use App\Modules\Chat\Models\Message;
use App\Modules\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ChatService
{
    public function sendMessage(User $sender, int $conversationId, string $body): Message
    {
        $message = Message::create([
            'conversation_id' => $conversationId,
            'user_id' => $sender->id,
            'body' => $body,
        ]);

        $message->load('sender', 'conversation.participants');

        event(new MessageSent($message));
        NotifyParticipants::dispatch($message);

        return $message;
    }

    public function editMessage(Message $message, User $editor, string $newBody): Message
    {
        if ($message->user_id !== $editor->id) {
            throw new AuthorizationException('You cannot edit this message.');
        }

        $message->update(['body' => $newBody, 'edited_at' => now()]);

        return $message->fresh();
    }

    public function deleteMessage(Message $message, User $deleter): void
    {
        if ($message->user_id !== $deleter->id) {
            throw new AuthorizationException('You cannot delete this message.');
        }

        $message->delete();
    }
}
```

- [ ] **Step 8: Rodar testes unitários**

```bash
php artisan test tests/Unit/Chat/ChatServiceTest.php
```

Expected: PASS (5 testes verdes)

- [ ] **Step 9: Commitar Chat models e services**

```bash
git add .
git commit -m "feat: Chat module — Conversation, Message models and services"
```

---

## Task 8: Módulo Chat — Broadcasting e Event MessageSent

**Files:**
- Create: `app/Modules/Chat/Events/MessageSent.php`
- Create: `app/Modules/Chat/Channels/ConversationChannel.php`
- Modify: `routes/channels.php`

- [ ] **Step 1: Criar evento `MessageSent`**

```php
// app/Modules/Chat/Events/MessageSent.php
<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->message->conversation_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'body' => $this->message->body,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ],
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Criar `ConversationChannel` para autorização**

```php
// app/Modules/Chat/Channels/ConversationChannel.php
<?php

namespace App\Modules\Chat\Channels;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Users\Models\User;

class ConversationChannel
{
    public function __construct(private ConversationService $conversationService) {}

    public function join(User $user, int $conversationId): array|bool
    {
        $conversation = Conversation::find($conversationId);

        if (! $conversation) {
            return false;
        }

        if (! $this->conversationService->isParticipant($conversation, $user)) {
            return false;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }
}
```

- [ ] **Step 3: Registrar canal em `routes/channels.php`**

```php
// routes/channels.php
<?php

use App\Modules\Chat\Channels\ConversationChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', ConversationChannel::class);
```

- [ ] **Step 4: Escrever teste de broadcasting**

```php
// tests/Feature/Chat/SendMessageTest.php
<?php

namespace Tests\Feature\Chat;

use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Jobs\NotifyParticipants;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_message_broadcasts_event_and_queues_job(): void
    {
        Event::fake([MessageSent::class]);
        Queue::fake();

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $conversationService = new ConversationService();
        $conversation = $conversationService->createDm($sender, $receiver);

        $this->actingAs($sender)->postGraphQL([
            'query' => '
                mutation($conversationId: ID!, $body: String!) {
                    sendMessage(conversationId: $conversationId, body: $body) {
                        id body
                        sender { id name }
                    }
                }
            ',
            'variables' => [
                'conversationId' => $conversation->id,
                'body' => 'Hello!',
            ],
        ]);

        Event::assertDispatched(MessageSent::class);
        Queue::assertPushed(NotifyParticipants::class);
    }
}
```

- [ ] **Step 5: Commitar broadcasting**

```bash
git add .
git commit -m "feat: MessageSent event with Reverb broadcasting and channel authorization"
```

---

## Task 9: Módulo Chat — GraphQL Schema e Resolvers

**Files:**
- Create: `graphql/chat.graphql`
- Create: `app/Modules/Chat/GraphQL/Queries/ConversationQuery.php`
- Create: `app/Modules/Chat/GraphQL/Queries/MessagesQuery.php`
- Create: `app/Modules/Chat/GraphQL/Mutations/SendMessageMutation.php`
- Create: `app/Modules/Chat/GraphQL/Mutations/EditMessageMutation.php`
- Create: `app/Modules/Chat/GraphQL/Mutations/DeleteMessageMutation.php`
- Create: `app/Modules/Chat/GraphQL/Subscriptions/MessageReceivedSubscription.php`

- [ ] **Step 1: Criar `graphql/chat.graphql`**

```graphql
# graphql/chat.graphql
type Conversation {
    id: ID!
    type: String!
    name: String
    participants: [User!]! @hasMany
    messages(first: Int = 20, page: Int): [Message!]! @hasMany @orderBy(column: "created_at", direction: DESC)
    created_at: DateTime!
}

type Message {
    id: ID!
    conversation_id: ID!
    body: String!
    sender: User! @belongsTo(relation: "sender")
    edited_at: DateTime
    created_at: DateTime!
}

extend type Query {
    conversation(id: ID!): Conversation
        @field(resolver: "App\\Modules\\Chat\\GraphQL\\Queries\\ConversationQuery")
        @guard
}

extend type Mutation {
    createDm(recipientId: ID!): Conversation!
        @field(resolver: "App\\Modules\\Chat\\GraphQL\\Mutations\\CreateDmMutation")
        @guard

    createGroup(name: String!, userIds: [ID!]!): Conversation!
        @field(resolver: "App\\Modules\\Chat\\GraphQL\\Mutations\\CreateGroupMutation")
        @guard

    sendMessage(conversationId: ID!, body: String!): Message!
        @field(resolver: "App\\Modules\\Chat\\GraphQL\\Mutations\\SendMessageMutation")
        @guard

    editMessage(messageId: ID!, body: String!): Message!
        @field(resolver: "App\\Modules\\Chat\\GraphQL\\Mutations\\EditMessageMutation")
        @guard

    deleteMessage(messageId: ID!): Boolean!
        @field(resolver: "App\\Modules\\Chat\\GraphQL\\Mutations\\DeleteMessageMutation")
        @guard
}

extend type Subscription {
    onMessageReceived(conversationId: ID!): Message
        @subscription(class: "App\\Modules\\Chat\\GraphQL\\Subscriptions\\MessageReceivedSubscription")
        @guard
}
```

- [ ] **Step 2: Criar `ConversationQuery`**

```php
// app/Modules/Chat/GraphQL/Queries/ConversationQuery.php
<?php

namespace App\Modules\Chat\GraphQL\Queries;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\ConversationService;
use Illuminate\Auth\Access\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ConversationQuery
{
    public function __construct(private ConversationService $conversationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): Conversation
    {
        $conversation = Conversation::with(['participants', 'messages.sender'])
            ->findOrFail($args['id']);

        if (! $this->conversationService->isParticipant($conversation, $context->user())) {
            throw new AuthorizationException('You are not a participant of this conversation.');
        }

        return $conversation;
    }
}
```

- [ ] **Step 3: Criar `SendMessageMutation`**

```php
// app/Modules/Chat/GraphQL/Mutations/SendMessageMutation.php
<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Services\ChatService;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Chat\Models\Conversation;
use Illuminate\Auth\Access\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SendMessageMutation
{
    public function __construct(
        private ChatService $chatService,
        private ConversationService $conversationService,
    ) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        $conversation = Conversation::findOrFail($args['conversationId']);

        if (! $this->conversationService->isParticipant($conversation, $context->user())) {
            throw new AuthorizationException('You are not a participant of this conversation.');
        }

        return $this->chatService->sendMessage(
            sender: $context->user(),
            conversationId: (int) $args['conversationId'],
            body: $args['body'],
        );
    }
}
```

- [ ] **Step 4: Criar `EditMessageMutation`**

```php
// app/Modules/Chat/GraphQL/Mutations/EditMessageMutation.php
<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\ChatService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class EditMessageMutation
{
    public function __construct(private ChatService $chatService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): Message
    {
        $message = Message::findOrFail($args['messageId']);
        return $this->chatService->editMessage($message, $context->user(), $args['body']);
    }
}
```

- [ ] **Step 5: Criar `DeleteMessageMutation`**

```php
// app/Modules/Chat/GraphQL/Mutations/DeleteMessageMutation.php
<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\ChatService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class DeleteMessageMutation
{
    public function __construct(private ChatService $chatService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        $message = Message::findOrFail($args['messageId']);
        $this->chatService->deleteMessage($message, $context->user());
        return true;
    }
}
```

- [ ] **Step 6: Criar `CreateDmMutation`**

```php
// app/Modules/Chat/GraphQL/Mutations/CreateDmMutation.php
<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Services\ConversationService;
use App\Modules\Users\Models\User;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CreateDmMutation
{
    public function __construct(private ConversationService $conversationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        $recipient = User::findOrFail($args['recipientId']);
        return $this->conversationService->createDm($context->user(), $recipient);
    }
}
```

- [ ] **Step 7: Criar `CreateGroupMutation`**

```php
// app/Modules/Chat/GraphQL/Mutations/CreateGroupMutation.php
<?php

namespace App\Modules\Chat\GraphQL\Mutations;

use App\Modules\Chat\Services\ConversationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CreateGroupMutation
{
    public function __construct(private ConversationService $conversationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        return $this->conversationService->createGroup(
            creator: $context->user(),
            name: $args['name'],
            userIds: array_map('intval', $args['userIds']),
        );
    }
}
```

- [ ] **Step 8: Criar `MessageReceivedSubscription`**

```php
// app/Modules/Chat/GraphQL/Subscriptions/MessageReceivedSubscription.php
<?php

namespace App\Modules\Chat\GraphQL\Subscriptions;

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Chat\Models\Conversation;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class MessageReceivedSubscription extends GraphQLSubscription
{
    public function __construct(private ConversationService $conversationService) {}

    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        $user = $subscriber->context->user();
        $conversationId = $subscriber->args['conversationId'];
        $conversation = Conversation::find($conversationId);

        return $conversation && $this->conversationService->isParticipant($conversation, $user);
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        /** @var Message $root */
        return (string) $root->conversation_id === (string) $subscriber->args['conversationId'];
    }
}
```

- [ ] **Step 9: Rodar testes de chat**

```bash
php artisan test tests/Feature/Chat/
```

Expected: PASS

- [ ] **Step 10: Commitar GraphQL Chat**

```bash
git add .
git commit -m "feat: Chat module GraphQL schema, mutations, queries and subscription"
```

---

## Task 10: Módulo Notifications — Model, Service e GraphQL

**Files:**
- Create: `app/Modules/Notifications/Models/AppNotification.php`
- Create: `app/Modules/Notifications/Services/NotificationService.php`
- Create: `graphql/notifications.graphql`
- Create: `app/Modules/Notifications/GraphQL/Queries/NotificationsQuery.php`
- Create: `app/Modules/Notifications/GraphQL/Queries/UnreadCountQuery.php`
- Create: `app/Modules/Notifications/GraphQL/Mutations/MarkAsReadMutation.php`
- Create: `app/Modules/Notifications/GraphQL/Mutations/MarkAllAsReadMutation.php`
- Create: `app/Modules/Notifications/GraphQL/Subscriptions/NotificationReceivedSubscription.php`
- Test: `tests/Feature/Notifications/NotificationTest.php`

- [ ] **Step 1: Escrever testes de notifications**

```php
// tests/Feature/Notifications/NotificationTest.php
<?php

namespace Tests\Feature\Notifications;

use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications(): void
    {
        $user = User::factory()->create();
        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'message',
            'data' => ['message' => 'Hello'],
        ]);

        $response = $this->actingAs($user)->postGraphQL([
            'query' => '{ notifications { id type data } }',
        ]);

        $this->assertCount(1, $response->json('data.notifications'));
    }

    public function test_user_can_get_unread_count(): void
    {
        $user = User::factory()->create();
        AppNotification::create(['user_id' => $user->id, 'type' => 'message', 'data' => []]);
        AppNotification::create(['user_id' => $user->id, 'type' => 'system', 'data' => [], 'read_at' => now()]);

        $response = $this->actingAs($user)->postGraphQL([
            'query' => '{ unreadNotificationsCount }',
        ]);

        $this->assertEquals(1, $response->json('data.unreadNotificationsCount'));
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'message',
            'data' => [],
        ]);

        $response = $this->actingAs($user)->postGraphQL([
            'query' => 'mutation($id: ID!) { markNotificationAsRead(id: $id) { id read_at } }',
            'variables' => ['id' => $notification->id],
        ]);

        $this->assertNotNull($response->json('data.markNotificationAsRead.read_at'));
    }
}
```

- [ ] **Step 2: Rodar testes para confirmar falha**

```bash
php artisan test tests/Feature/Notifications/NotificationTest.php
```

Expected: FAIL

- [ ] **Step 3: Criar model `AppNotification`**

```php
// app/Modules/Notifications/Models/AppNotification.php
<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
```

- [ ] **Step 4: Criar `NotificationService`**

```php
// app/Modules/Notifications/Services/NotificationService.php
<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function createForUser(User $user, string $type, array $data): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);
    }

    public function getForUser(User $user): Collection
    {
        return AppNotification::where('user_id', $user->id)
            ->latest()
            ->get();
    }

    public function countUnread(User $user): int
    {
        return AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(AppNotification $notification, User $user): AppNotification
    {
        if ($notification->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        $notification->update(['read_at' => now()]);
        return $notification->fresh();
    }

    public function markAllAsRead(User $user): void
    {
        AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
```

- [ ] **Step 5: Criar `graphql/notifications.graphql`**

```graphql
# graphql/notifications.graphql
type AppNotification {
    id: ID!
    type: String!
    data: String!
    read_at: DateTime
    created_at: DateTime!
}

extend type Query {
    notifications: [AppNotification!]!
        @field(resolver: "App\\Modules\\Notifications\\GraphQL\\Queries\\NotificationsQuery")
        @guard

    unreadNotificationsCount: Int!
        @field(resolver: "App\\Modules\\Notifications\\GraphQL\\Queries\\UnreadCountQuery")
        @guard
}

extend type Mutation {
    markNotificationAsRead(id: ID!): AppNotification!
        @field(resolver: "App\\Modules\\Notifications\\GraphQL\\Mutations\\MarkAsReadMutation")
        @guard

    markAllNotificationsAsRead: Boolean!
        @field(resolver: "App\\Modules\\Notifications\\GraphQL\\Mutations\\MarkAllAsReadMutation")
        @guard
}

extend type Subscription {
    onNotificationReceived: AppNotification
        @subscription(class: "App\\Modules\\Notifications\\GraphQL\\Subscriptions\\NotificationReceivedSubscription")
        @guard
}
```

- [ ] **Step 6: Criar resolvers de queries**

```php
// app/Modules/Notifications/GraphQL/Queries/NotificationsQuery.php
<?php

namespace App\Modules\Notifications\GraphQL\Queries;

use App\Modules\Notifications\Services\NotificationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NotificationsQuery
{
    public function __construct(private NotificationService $notificationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): mixed
    {
        return $this->notificationService->getForUser($context->user());
    }
}
```

```php
// app/Modules/Notifications/GraphQL/Queries/UnreadCountQuery.php
<?php

namespace App\Modules\Notifications\GraphQL\Queries;

use App\Modules\Notifications\Services\NotificationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UnreadCountQuery
{
    public function __construct(private NotificationService $notificationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): int
    {
        return $this->notificationService->countUnread($context->user());
    }
}
```

- [ ] **Step 7: Criar resolvers de mutations**

```php
// app/Modules/Notifications/GraphQL/Mutations/MarkAsReadMutation.php
<?php

namespace App\Modules\Notifications\GraphQL\Mutations;

use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Services\NotificationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MarkAsReadMutation
{
    public function __construct(private NotificationService $notificationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): AppNotification
    {
        $notification = AppNotification::findOrFail($args['id']);
        return $this->notificationService->markAsRead($notification, $context->user());
    }
}
```

```php
// app/Modules/Notifications/GraphQL/Mutations/MarkAllAsReadMutation.php
<?php

namespace App\Modules\Notifications\GraphQL\Mutations;

use App\Modules\Notifications\Services\NotificationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MarkAllAsReadMutation
{
    public function __construct(private NotificationService $notificationService) {}

    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        $this->notificationService->markAllAsRead($context->user());
        return true;
    }
}
```

- [ ] **Step 8: Criar `NotificationReceivedSubscription`**

```php
// app/Modules/Notifications/GraphQL/Subscriptions/NotificationReceivedSubscription.php
<?php

namespace App\Modules\Notifications\GraphQL\Subscriptions;

use App\Modules\Notifications\Models\AppNotification;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class NotificationReceivedSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return $subscriber->context->user() !== null;
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        /** @var AppNotification $root */
        return (int) $root->user_id === (int) $subscriber->context->user()->id;
    }
}
```

- [ ] **Step 9: Rodar testes de notifications**

```bash
php artisan test tests/Feature/Notifications/NotificationTest.php
```

Expected: PASS (3 testes verdes)

- [ ] **Step 10: Commitar módulo Notifications**

```bash
git add .
git commit -m "feat: Notifications module — model, service and GraphQL"
```

---

## Task 11: gRPC — Contrato, Proto e Client Gateway

**Files:**
- Create: `proto/notifications.proto`
- Create: `app/Modules/Notifications/Contracts/NotificationGatewayInterface.php`
- Create: `app/Modules/Notifications/Gateways/GrpcNotificationGateway.php`
- Create: `app/Modules/Notifications/Gateways/FakeNotificationGateway.php`
- Create: `config/grpc.php`

- [ ] **Step 1: Criar arquivo `.proto`**

```protobuf
// proto/notifications.proto
syntax = "proto3";

package notifications;

option php_namespace = "App\\Modules\\Notifications\\gRPC\\Generated";
option php_metadata_namespace = "App\\Modules\\Notifications\\gRPC\\Generated\\GPBMetadata";

service NotificationService {
  rpc SendPush(PushRequest) returns (PushResponse);
  rpc SendEmail(EmailRequest) returns (EmailResponse);
}

message PushRequest {
  repeated string device_tokens = 1;
  string title = 2;
  string body = 3;
  map<string, string> data = 4;
}

message PushResponse {
  bool success = 1;
  string message = 2;
}

message EmailRequest {
  string to = 1;
  string subject = 2;
  string template = 3;
  map<string, string> variables = 4;
}

message EmailResponse {
  bool success = 1;
  string message = 2;
}
```

- [ ] **Step 2: Gerar stubs PHP a partir do proto**

```bash
# Instale protoc: https://grpc.io/docs/protoc-installation/
# Instale o plugin PHP: grpc_php_plugin

mkdir -p app/Modules/Notifications/gRPC/Generated

protoc --proto_path=proto \
  --php_out=app/Modules/Notifications/gRPC/Generated \
  --grpc_out=app/Modules/Notifications/gRPC/Generated \
  --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
  proto/notifications.proto
```

> Os arquivos gerados incluem: `PushRequest.php`, `PushResponse.php`, `EmailRequest.php`, `EmailResponse.php`, `NotificationServiceClient.php`.

- [ ] **Step 3: Criar `config/grpc.php`**

```php
// config/grpc.php
<?php

return [
    'notification_service' => [
        'host' => env('GRPC_NOTIFICATION_HOST', 'localhost'),
        'port' => env('GRPC_NOTIFICATION_PORT', 50051),
        'timeout' => env('GRPC_NOTIFICATION_TIMEOUT', 5000), // ms
    ],
];
```

- [ ] **Step 4: Criar contrato `NotificationGatewayInterface`**

```php
// app/Modules/Notifications/Contracts/NotificationGatewayInterface.php
<?php

namespace App\Modules\Notifications\Contracts;

interface NotificationGatewayInterface
{
    /**
     * @param string[] $deviceTokens
     * @param array<string, string> $data
     */
    public function sendPush(array $deviceTokens, string $title, string $body, array $data = []): bool;

    /**
     * @param array<string, string> $variables
     */
    public function sendEmail(string $to, string $subject, string $template, array $variables = []): bool;
}
```

- [ ] **Step 5: Criar `GrpcNotificationGateway`**

```php
// app/Modules/Notifications/Gateways/GrpcNotificationGateway.php
<?php

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;
use App\Modules\Notifications\gRPC\Generated\EmailRequest;
use App\Modules\Notifications\gRPC\Generated\NotificationServiceClient;
use App\Modules\Notifications\gRPC\Generated\PushRequest;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;

class GrpcNotificationGateway implements NotificationGatewayInterface
{
    private NotificationServiceClient $client;

    public function __construct()
    {
        $host = config('grpc.notification_service.host');
        $port = config('grpc.notification_service.port');

        $this->client = new NotificationServiceClient(
            "{$host}:{$port}",
            ['credentials' => ChannelCredentials::createInsecure()],
        );
    }

    public function sendPush(array $deviceTokens, string $title, string $body, array $data = []): bool
    {
        $request = new PushRequest();
        $request->setDeviceTokens($deviceTokens);
        $request->setTitle($title);
        $request->setBody($body);
        $request->setData($data);

        $timeout = config('grpc.notification_service.timeout') * 1000; // to microseconds

        [$response, $status] = $this->client->SendPush($request, [], ['timeout' => $timeout])->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            Log::error('gRPC SendPush failed', ['code' => $status->code, 'details' => $status->details]);
            return false;
        }

        return $response->getSuccess();
    }

    public function sendEmail(string $to, string $subject, string $template, array $variables = []): bool
    {
        $request = new EmailRequest();
        $request->setTo($to);
        $request->setSubject($subject);
        $request->setTemplate($template);
        $request->setVariables($variables);

        $timeout = config('grpc.notification_service.timeout') * 1000;

        [$response, $status] = $this->client->SendEmail($request, [], ['timeout' => $timeout])->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            Log::error('gRPC SendEmail failed', ['code' => $status->code, 'details' => $status->details]);
            return false;
        }

        return $response->getSuccess();
    }
}
```

- [ ] **Step 6: Criar `FakeNotificationGateway` para testes**

```php
// app/Modules/Notifications/Gateways/FakeNotificationGateway.php
<?php

namespace App\Modules\Notifications\Gateways;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;

class FakeNotificationGateway implements NotificationGatewayInterface
{
    public array $pushedNotifications = [];
    public array $sentEmails = [];

    public function sendPush(array $deviceTokens, string $title, string $body, array $data = []): bool
    {
        $this->pushedNotifications[] = compact('deviceTokens', 'title', 'body', 'data');
        return true;
    }

    public function sendEmail(string $to, string $subject, string $template, array $variables = []): bool
    {
        $this->sentEmails[] = compact('to', 'subject', 'template', 'variables');
        return true;
    }
}
```

- [ ] **Step 7: Commitar camada gRPC**

```bash
git add .
git commit -m "feat: gRPC notification gateway interface, real and fake implementations"
```

---

## Task 12: Jobs — NotifyParticipants, SendPush e SendEmail

**Files:**
- Create: `app/Modules/Chat/Jobs/NotifyParticipants.php`
- Create: `app/Modules/Notifications/Jobs/SendPushNotification.php`
- Create: `app/Modules/Notifications/Jobs/SendEmailNotification.php`
- Test: `tests/Unit/Notifications/NotifyParticipantsJobTest.php`

- [ ] **Step 1: Escrever testes do job `NotifyParticipants`**

```php
// tests/Unit/Notifications/NotifyParticipantsJobTest.php
<?php

namespace Tests\Unit\Notifications;

use App\Modules\Chat\Jobs\NotifyParticipants;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Notifications\Jobs\SendEmailNotification;
use App\Modules\Notifications\Jobs\SendPushNotification;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotifyParticipantsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_notifications_and_dispatches_push_and_email(): void
    {
        Queue::fake();

        $sender = User::factory()->create(['is_online' => true]);
        $receiver = User::factory()->create(['is_online' => false, 'last_seen_at' => now()->subMinutes(10)]);

        $conversationService = new ConversationService();
        $conversation = $conversationService->createDm($sender, $receiver);

        $message = \App\Modules\Chat\Models\Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'body' => 'Hello!',
        ]);
        $message->load('sender', 'conversation.participants');

        (new NotifyParticipants($message))->handle(
            app(\App\Modules\Notifications\Services\NotificationService::class)
        );

        // Notificação persistida para o destinatário (não para o sender)
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $receiver->id,
            'type' => 'message',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $sender->id,
        ]);

        // Push e email dispatched para o receiver offline
        Queue::assertPushed(SendPushNotification::class);
        Queue::assertPushed(SendEmailNotification::class);
    }
}
```

- [ ] **Step 2: Rodar para confirmar falha**

```bash
php artisan test tests/Unit/Notifications/NotifyParticipantsJobTest.php
```

Expected: FAIL

- [ ] **Step 3: Criar `NotifyParticipants` job**

```php
// app/Modules/Chat/Jobs/NotifyParticipants.php
<?php

namespace App\Modules\Chat\Jobs;

use App\Modules\Chat\Models\Message;
use App\Modules\Notifications\Jobs\SendEmailNotification;
use App\Modules\Notifications\Jobs\SendPushNotification;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyParticipants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public Message $message) {}

    public function handle(NotificationService $notificationService): void
    {
        $sender = $this->message->sender;
        $participants = $this->message->conversation->participants
            ->where('id', '!=', $sender->id);

        foreach ($participants as $recipient) {
            $notification = $notificationService->createForUser(
                user: $recipient,
                type: 'message',
                data: [
                    'message_id' => $this->message->id,
                    'conversation_id' => $this->message->conversation_id,
                    'sender_name' => $sender->name,
                    'preview' => substr($this->message->body, 0, 100),
                ],
            );

            SendPushNotification::dispatch($notification, $recipient);

            // Só envia email se o usuário está offline há mais de 5 minutos
            $offlineSince = $recipient->last_seen_at;
            if (! $recipient->is_online && $offlineSince && $offlineSince->lt(now()->subMinutes(5))) {
                SendEmailNotification::dispatch($notification, $recipient);
            }
        }
    }
}
```

- [ ] **Step 4: Criar `SendPushNotification` job**

```php
// app/Modules/Notifications/Jobs/SendPushNotification.php
<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public AppNotification $notification,
        public User $recipient,
    ) {}

    public function handle(NotificationGatewayInterface $gateway): void
    {
        $data = $this->notification->data;

        $success = $gateway->sendPush(
            deviceTokens: $this->recipient->device_tokens ?? [],
            title: $data['sender_name'] ?? 'Nova mensagem',
            body: $data['preview'] ?? '',
            data: ['notification_id' => (string) $this->notification->id],
        );

        if (! $success) {
            Log::warning('Push notification failed', ['notification_id' => $this->notification->id]);
        }
    }
}
```

- [ ] **Step 5: Criar `SendEmailNotification` job**

```php
// app/Modules/Notifications/Jobs/SendEmailNotification.php
<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Contracts\NotificationGatewayInterface;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public AppNotification $notification,
        public User $recipient,
    ) {}

    public function handle(NotificationGatewayInterface $gateway): void
    {
        $data = $this->notification->data;

        $success = $gateway->sendEmail(
            to: $this->recipient->email,
            subject: "Nova mensagem de {$data['sender_name']}",
            template: 'new_message',
            variables: [
                'recipient_name' => $this->recipient->name,
                'sender_name' => $data['sender_name'] ?? '',
                'preview' => $data['preview'] ?? '',
            ],
        );

        if (! $success) {
            Log::warning('Email notification failed', ['notification_id' => $this->notification->id]);
        }
    }
}
```

- [ ] **Step 6: Vincular `FakeNotificationGateway` nos testes**

Em `tests/TestCase.php`, adicionar:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->app->bind(
        \App\Modules\Notifications\Contracts\NotificationGatewayInterface::class,
        \App\Modules\Notifications\Gateways\FakeNotificationGateway::class,
    );
}
```

- [ ] **Step 7: Rodar testes de jobs**

```bash
php artisan test tests/Unit/Notifications/NotifyParticipantsJobTest.php
```

Expected: PASS

- [ ] **Step 8: Commitar jobs**

```bash
git add .
git commit -m "feat: NotifyParticipants, SendPushNotification, SendEmailNotification jobs"
```

---

## Task 13: Suite de Testes Final e Verificação

**Files:**
- Modify: `phpunit.xml` (configurar SQLite in-memory)

- [ ] **Step 1: Configurar `phpunit.xml` para SQLite**

```xml
<!-- phpunit.xml — dentro de <php> -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="BROADCAST_CONNECTION" value="log"/>
<env name="CACHE_STORE" value="array"/>
```

- [ ] **Step 2: Rodar suite completa**

```bash
php artisan test
```

Expected: Todos os testes passando (AuthTest, ChatServiceTest, SendMessageTest, NotificationTest, NotifyParticipantsJobTest)

- [ ] **Step 3: Verificar que o servidor Reverb sobe corretamente**

```bash
php artisan reverb:start
```

Expected: `Starting Reverb server on 0.0.0.0:8080`

- [ ] **Step 4: Verificar que o worker de queue processa jobs**

```bash
php artisan queue:work --once
```

Expected: sem erros

- [ ] **Step 5: Commit final**

```bash
git add .
git commit -m "test: full test suite passing, phpunit configured for SQLite in-memory"
```

---

## Referências

- [Laravel Reverb Docs](https://laravel.com/docs/reverb)
- [Lighthouse GraphQL Docs](https://lighthouse-php.com)
- [gRPC PHP Quickstart](https://grpc.io/docs/languages/php/quickstart/)
- Spec: `docs/superpowers/specs/2026-04-08-realtime-communication-platform-design.md`
