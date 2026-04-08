# Design: Plataforma de Comunicação em Tempo Real

**Data:** 2026-04-08
**Stack:** Laravel 11 + Reverb + GraphQL (Lighthouse) + gRPC + Redis

---

## Visão Geral

Plataforma de comunicação em tempo real construída como **monólito modular Laravel**. Suporta mensagens diretas (DM) e salas de grupo, com notificações in-app, push (Firebase) e email (Mailgun/SES). WebSocket via Laravel Reverb; API via GraphQL (Lighthouse); integrações externas via gRPC.

---

## Arquitetura Geral

```
┌─────────────────────────────────────────────────────┐
│                   Cliente (Web/Mobile)               │
│                                                     │
│  GraphQL HTTP ──────────────────────────────────┐  │
│  GraphQL WS (Subscriptions) ──────────────┐     │  │
│  WebSocket (Reverb) ───────────────────┐  │     │  │
└───────────────────────────────────────┼──┼─────┘  │
                                        │  │
┌───────────────────────────────────────▼──▼─────────┐
│                  Laravel Application                │
│                                                     │
│  ┌──────────┐  ┌──────────┐  ┌───────────────────┐ │
│  │  Module  │  │  Module  │  │      Module       │ │
│  │   Auth   │  │   Chat   │  │   Notifications   │ │
│  └──────────┘  └────┬─────┘  └────────┬──────────┘ │
│                     │                 │             │
│         ┌───────────▼─────────────────▼──────────┐ │
│         │          Laravel Events / Queue         │ │
│         │              (Redis)                    │ │
│         └────────────────────────────────────────┘ │
│                                                     │
│  ┌──────────────────────────────────────────────┐  │
│  │         Laravel Reverb (WebSocket Server)    │  │
│  └──────────────────────────────────────────────┘  │
└─────────────────────────────────────────┬───────────┘
                                          │ gRPC
              ┌───────────────────────────▼────────────┐
              │         Serviços Externos               │
              │  Firebase FCM  │  Mailgun/SES  │  etc.  │
              └────────────────────────────────────────┘
```

**Responsabilidades por camada:**
- **GraphQL HTTP** — queries e mutations (dados sob demanda)
- **WebSocket (Reverb)** — entrega de mensagens e notificações em tempo real
- **GraphQL Subscriptions** — alternativa declarativa ao WebSocket para eventos tipados
- **Redis Queue** — desacopla processamento de notificações do ciclo de request
- **gRPC** — gateway de saída para serviços externos de entrega (push, email)

---

## Módulos

Cada módulo vive em `app/Modules/<Nome>/` com estrutura interna própria: `Models/`, `Services/`, `GraphQL/`, `Events/`, `Jobs/`.

### Auth
- Registro, login, logout, refresh token
- Driver: Laravel Sanctum (tokens de API)
- GraphQL: mutations `login`, `register`, `logout`

### Users
- Perfil de usuário (nome, avatar, status online/offline)
- Presença em tempo real: status atualizado via evento ao conectar/desconectar do WebSocket
- GraphQL: queries `me`, `user`, `users`; subscription `onUserPresenceChanged`

### Chat
- **Conversas:** DM (2 participantes) e Grupos (N participantes)
- **Mensagens:** envio, edição, deleção, histórico paginado
- Ao enviar mensagem → dispara `MessageSent` event → Reverb broadcast + job de notificação
- GraphQL: queries `conversation`, `messages`; mutations `sendMessage`, `editMessage`, `deleteMessage`; subscription `onMessageReceived`

### Notifications
- Persiste notificações no banco (in-app)
- Consome jobs da queue para push (Firebase via gRPC) e email (Mailgun/SES via gRPC)
- GraphQL: queries `notifications`, `unreadCount`; mutations `markAsRead`, `markAllAsRead`; subscription `onNotificationReceived`

---

## Modelo de Dados

```sql
users
  id, name, email, password
  avatar_url, bio
  last_seen_at, is_online

conversations
  id, type (dm | group)
  name (nullable — só para grupos)
  created_by (FK users)

conversation_participants
  conversation_id (FK)
  user_id (FK)
  joined_at, last_read_at

messages
  id, conversation_id (FK)
  user_id (FK sender)
  body (text)
  edited_at (nullable)
  deleted_at (soft delete)

notifications
  id, user_id (FK destinatário)
  type (message | mention | system)
  data (JSON)
  read_at (nullable)
  created_at

personal_access_tokens (Sanctum)
  tokenable_id, tokenable_type, token, last_used_at
```

**Decisões:**
- `last_read_at` em `conversation_participants` calcula mensagens não lidas sem tabela extra
- `data` JSON em `notifications` acomoda tipos diferentes sem colunas por tipo
- Soft delete em `messages` preserva histórico enquanto oculta mensagem deletada

---

## Fluxo de Dados em Tempo Real

### Envio de Mensagem

```
1. Cliente → GraphQL mutation sendMessage(conversationId, body)
2. ChatService → persiste Message no banco
3. Dispara evento MessageSent (payload: message + participantes)
4. Reverb Broadcasting → broadcast no channel conversation.{id}
   └── Clientes conectados recebem a mensagem instantaneamente
5. Job NotifyParticipants enfileirado no Redis
6. Worker processa job:
   ├── Persiste Notification para cada participante
   ├── gRPC call → Firebase FCM (push mobile/browser)
   └── gRPC call → Mailgun/SES (email, se usuário offline > 5 minutos)
```

### Presença (Online/Offline)

```
- Conexão WebSocket aberta  → evento UserConnected → is_online=true, broadcast
- Conexão WebSocket fechada → evento UserDisconnected → is_online=false, broadcast
- Clientes recebem via subscription GraphQL onUserPresenceChanged
```

### Autenticação no WebSocket

```
- Cliente autentica via GraphQL mutation login → recebe Sanctum token
- Ao conectar ao Reverb: passa token no handshake
- Laravel autentica via broadcasting auth route (/broadcasting/auth)
- Canais privados: conversation.{id} exige que usuário seja participante
```

---

## Camada gRPC

O gRPC opera como **gateway de saída** — Laravel chama serviços externos via clientes gRPC gerados a partir de `.proto` files. As chamadas ocorrem dentro de Queue Jobs, nunca no ciclo de request.

### Definição de Serviço

```protobuf
// notifications.proto

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

message EmailRequest {
  string to = 1;
  string subject = 2;
  string template = 3;
  map<string, string> variables = 4;
}
```

### Implementação

- Package: `spiral/roadrunner-grpc` + `google/protobuf`
- Clients gerados em `app/Modules/Notifications/gRPC/`
- Timeout configurável por chamada, com fallback para log de falha
- Falha de push/email não bloqueia persistência da mensagem

---

## Tratamento de Erros

| Camada | Estratégia |
|---|---|
| GraphQL | Erros retornam no formato `errors[]` do GraphQL — validação via Form Requests, exceções mapeadas para tipos GraphQL |
| WebSocket | Reconexão automática no cliente; canais privados rejeitados retornam 403 na auth route |
| Queue Jobs | `tries: 3` + `backoff: [10, 60, 300]` segundos; após esgotar tentativas → `JobFailed` event logado |
| gRPC | Timeout por chamada + try/catch no job; falha de push/email não bloqueia persistência da mensagem |
| Auth | Token inválido → 401 no GraphQL e no broadcasting auth |

---

## Testes

| Tipo | O que cobre |
|---|---|
| Feature (HTTP) | Mutations/queries GraphQL — ciclo completo com banco real (SQLite in-memory) |
| Unit | Services isolados (ChatService, NotificationService) |
| Broadcasting | `Event::fake()` + asserções de canal e payload |
| Queue | `Queue::fake()` → verifica jobs enfileirados; testes de job com mocks dos clientes gRPC |
| gRPC clients | Mockados nos testes de job — sem chamada real a serviços externos |

---

## Dependências Principais

| Package | Propósito |
|---|---|
| `laravel/reverb` | Servidor WebSocket nativo Laravel |
| `nuwave/lighthouse` | GraphQL server para Laravel |
| `laravel/sanctum` | Autenticação via tokens de API |
| `spiral/roadrunner-grpc` | Cliente/servidor gRPC em PHP |
| `google/protobuf` | Serialização protobuf |
| `predis/predis` | Redis (queues + broadcasting) |
