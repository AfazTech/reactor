# Reactor Application Skeleton

A production-ready starter project for building high-performance, asynchronous Telegram bots with the **Reactor** PHP framework.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Powered by Amp](https://img.shields.io/badge/powered%20by-Amp-blueviolet.svg)](https://amphp.org/)
[![Powered by Neili](https://img.shields.io/badge/powered%20by-Neili-cyan.svg)](https://github.com/AfazTech/neili)
[![Latest Version](https://img.shields.io/badge/version-1.0.0-orange.svg)](https://github.com/afaztech/reactor/releases)

---

## Overview

This repository is the official application skeleton for **Reactor**, a modern, asynchronous PHP framework purpose-built for creating robust Telegram bots.

Under the hood, this skeleton is powered by three complementary technologies:

- **[Reactor](https://github.com/afaztech/reactor-framework)** — the framework itself. It provides the dependency injection container, attribute-based routing, middleware pipeline, service providers, config layering, migrations, queue system, scheduler, caching, event dispatcher, and the CLI.
- **[Amp](https://amphp.org/)** — the asynchronous concurrency framework. Reactor is built on Amp's fiber-based event loop, which enables truly non-blocking I/O. This means your bot can handle many concurrent requests without spawning threads or processes, and long-running operations (HTTP calls, database queries, file I/O) never block the event loop.
- **[Neili](https://github.com/AfazTech/neili)** — the asynchronous Telegram client library. Neili wraps the entire Telegram Bot API in non-blocking methods, provides a long-polling `Poller` with concurrency control, and handles webhook input parsing. It is what actually talks to Telegram on Reactor's behalf.

Together these three layers give you a bot that is **fast, memory-efficient, and easy to reason about** – while still feeling like a familiar, Laravel-inspired PHP application.

The skeleton ships with a fully configured project layout, working example handlers, middleware, migrations, localisation files, and CLI commands, so you can go from `composer create-project` to a running bot in under five minutes.

---

## Table of Contents

1. [Features](#features)
2. [Architecture Overview](#architecture-overview)
3. [Requirements](#requirements)
4. [Quick Start](#quick-start)
5. [Configuration](#configuration)
6. [Project Structure](#project-structure)
7. [The Update Lifecycle](#the-update-lifecycle)
8. [Writing Handlers](#writing-handlers)
9. [Writing Middleware](#writing-middleware)
10. [Keyboard Builder](#keyboard-builder)
11. [Working with the Database](#working-with-the-database)
12. [Queue System](#queue-system)
13. [Localisation](#localisation)
14. [Webhook Mode](#webhook-mode)
15. [CLI Commands](#cli-commands)
16. [Multi-Process Mode](#multi-process-mode)
17. [Production Checklist](#production-checklist)
18. [Contributing](#contributing)
19. [License](#license)

---

## Features

- **Attribute-based routing** — commands, text handlers, callbacks, step handlers, and fallbacks are all declared with PHP 8 attributes.
- **Asynchronous by design** — powered by Amp fibers and the Neili Telegram client, so I/O never blocks.
- **Middleware pipeline** — global, group, and local middleware, ordered by priority.
- **Dependency injection** — a powerful container with auto-resolution, contextual bindings, tags, and scoped instances.
- **Service providers** — modular registration and booting of services.
- **Multi-source configuration** — package defaults, application config, and runtime overrides are merged deterministically.
- **Eloquent database layer** — full ORM with migrations, seeders, and an interface-driven repository pattern.
- **Database-backed queue** — dispatch background jobs and process them via a worker command.
- **Scheduler** — cron-based task scheduling with overlap prevention.
- **Caching** — file, array, Redis, and Memcached drivers behind a single interface.
- **Localisation** — JSON translation files with fallback support.
- **Package ecosystem** — discover packages, auto-register their providers, and publish their assets.
- **Structured logging** — PSR-3 logger backed by Monolog with rotating file handler.
- **Centralised error handling** — user-friendly exceptions that respond in the user's own language.
- **Both polling and webhook modes** — switch with one environment variable.

---

## Architecture Overview

Understanding how the three layers fit together is the key to getting the most out of this skeleton.

```
┌──────────────────────────────────────────────────────────────┐
│                        TELEGRAM SERVERS                       │
└───────────────────────────┬──────────────────────────────────┘
                            │  getUpdates / webhook
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    NEILI  (Telegram client)                   │
│  • Async HTTP calls built on Amp                              │
│  • Long-polling Poller with concurrency control               │
│  • Webhook payload parsing                                    │
└───────────────────────────┬──────────────────────────────────┘
                            │  decoded update array
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    AMP  (event loop & fibers)                 │
│  • Non-blocking I/O                                           │
│  • Fiber scheduling                                           │
│  • Futures and async primitives                               │
└───────────────────────────┬──────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                  REACTOR  (your bot framework)                │
│  • Router → Middleware → Handler → Response                   │
│  • DI container, config, database, queue, scheduler, cache    │
└──────────────────────────────────────────────────────────────┘
```

When an update arrives:

1. **Neili** receives and decodes it.
2. **Amp** schedules the processing fiber on the event loop.
3. **Reactor** resolves a handler, runs the middleware pipeline, invokes the handler, and lets the handler send a reply (again via Neili, again async).
4. The whole thing happens without blocking, so other updates can be processed concurrently.

This is what makes Reactor well suited for bots that talk to external APIs, do database work, or send many messages in a short time.

---

## Requirements

- **PHP >= 8.1** (Reactor uses enums, readonly properties, first-class callable syntax, and fibers)
- **Composer**
- **PDO extension** for your chosen database
- **ext-json**, **ext-mbstring** (usually enabled by default)
- Optional: `ext-redis` or `ext-memcached` if you switch the cache driver
- SQLite is used by default; MySQL is also fully supported

---

## Quick Start

### 1. Create the project

```bash
composer create-project afaztech/reactor my-bot
cd my-bot
```

### 2. Configure your environment

```bash
cp .env.example .env
```

Open `.env` and set your bot token. The bare minimum:

```env
TOKEN=123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ
BOT_MODE=polling
DB_CONNECTION=sqlite
DB_DATABASE=database/test.sqlite
```

### 3. Run migrations

```bash
php reactor.php migrate
```

This creates the `users`, `jobs`, and `posts` tables.

### 4. Start the bot

```bash
php reactor.php start
```

You should see output like:

```
========================================
⚡ REACTOR BOT
========================================
✓ Bot initialized successfully
✓ Mode: POLLING
✓ Starting long polling...
========================================
🚀 Reactor is running and listening for messages
📝 Press Ctrl+C to stop
========================================
```

### 5. Test it

Open Telegram, find your bot, and send `/start`. You should receive a welcome message with the main menu keyboard.

---

## Configuration

All configuration lives in `config/` and reads from `.env` via the `env()` helper. The skeleton ships with four config files.

### `config/app.php`

| Key | Env variable | Default | Description |
|-----|--------------|---------|-------------|
| `name` | `APP_NAME` | `Reactor` | Application name |
| `env` | `APP_ENV` | `production` | Environment identifier |
| `debug` | `DEBUG_MODE` | `true` | Enable verbose logging |
| `default_language` | `DEFAULT_LANGUAGE` | `en` | Fallback language |

### `config/bot.php`

| Key | Env variable | Default | Description |
|-----|--------------|---------|-------------|
| `token` | `TOKEN` | — | Telegram bot token (**required**) |
| `api_url` | `API_URL` | `https://api.telegram.org/bot` | Telegram API base URL |
| `mode` | `BOT_MODE` | `polling` | `polling` or `webhook` |
| `multi_process` | `MULTI_PROCESS` | `false` | Enable multi-process handling |
| `webhook_secret` | `WEBHOOK_SECRET` | — | Secret token for webhook verification |
| `php_binary` | `PHP_BINARY` | `/usr/bin/php` | PHP binary for multi-process |
| `verify_ssl` | `TELEGRAM_VERIFY_SSL` | `true` | Verify TLS certificates (keep `true` in production) |

### `config/database.php`

| Key | Env variable | Default | Description |
|-----|--------------|---------|-------------|
| `default` | `DB_CONNECTION` | `sqlite` | Default connection name |
| `migrations.namespace` | `MIGRATIONS_NAMESPACE` | `App\Migrations` | Namespace for migration classes |
| `connections.sqlite.database` | `DB_DATABASE` | `database/test.sqlite` | SQLite file path |

MySQL and other drivers are supported through `illuminate/database`. Just add a connection entry and change `DB_CONNECTION`.

### `config/cache.php`

| Key | Env variable | Default | Description |
|-----|--------------|---------|-------------|
| `default` | `CACHE_DRIVER` | `file` | `array`, `file`, `redis`, or `memcached` |

---

## Project Structure

```
.
├── app/
│   ├── Contracts/
│   │   └── Repository/
│   │       └── UserRepositoryInterface.php   # Contract for user persistence
│   ├── Database/
│   │   └── EloquentManager.php               # Eloquent-backed DB manager
│   ├── Handlers/
│   │   ├── Admin/
│   │   │   └── PingHandler.php               # Nested admin handler example
│   │   ├── AboutHandler.php                  # "About" button handler
│   │   ├── BaseHandler.php                   # Base class for all handlers
│   │   ├── FallbackHandler.php               # Catch-all for unmatched updates
│   │   └── StartHandler.php                  # /start, /restart, back, start
│   ├── Jobs/
│   │   └── SendMessageJob.php                # Example queue job
│   ├── Keyboard.php                          # Reusable keyboard builder
│   ├── Middleware/
│   │   ├── LogMiddleware.php                 # Logs every incoming update
│   │   └── SyncUserMiddleware.php            # Upserts users, fires events
│   ├── Models/
│   │   ├── Job.php                           # Eloquent model for `jobs`
│   │   └── User.php                          # Eloquent model for `users`
│   ├── Providers/
│   │   └── AppServiceProvider.php            # Container bindings
│   └── Repositories/
│       └── EloquentUserRepository.php        # Eloquent user repository
├── bootstrap/
│   └── cache/
│       └── packages.php                      # Auto-generated package manifest
├── config/
│   ├── app.php
│   ├── bot.php
│   ├── cache.php
│   └── database.php
├── database/
│   ├── migrations/
│   │   ├── 2026_07_16_000002_create_users_table.php
│   │   ├── 2026_07_22_000000_create_jobs_table.php
│   │   └── 2026_09_29_142247_create_posts_table.php
│   ├── seeders/
│   │   └── DatabaseSeeder.php
│   └── test.sqlite                           # SQLite database (gitignored)
├── lang/
│   ├── en.json                               # English translations
│   └── fa.json                               # Persian translations
├── public_html/
│   └── webhook.php                           # Webhook entry point
├── .env.example
├── .gitignore
├── composer.json
├── LICENSE
├── README.md
└── reactor.php                               # CLI entry point
```

---

## The Update Lifecycle

Every incoming Telegram update passes through the same pipeline. Understanding it is essential before writing handlers.

```
Telegram
   │
   ▼
Neili Client (getUpdates / webhook)
   │
   ▼
App::processUpdate($update)
   │
   ▼
UpdateProcessor::process()
   │
   ├──▶ UpdateTypeResolver → e.g. "message"
   │
   ├──▶ Router::findHandler()
   │       ├─ 1. Callback match (exact / regex)
   │       ├─ 2. Command match (/start, /restart, …)
   │       ├─ 3. Text match (translated key or regex)
   │       ├─ 4. Step match (current user's step)
   │       └─ 5. Fallback (#[Fallback] or built-in)
   │
   ├──▶ MiddlewareProcessor::process()
   │       ├─ Global middleware
   │       ├─ Group middleware (matching handler's groups)
   │       └─ Local middleware (listed in #[UseMiddleware])
   │
   └──▶ HandlerInvoker::invoke()
           └── Handler::execute($update, $params)
                   └── $this->reply(...)  ──▶  Neili Client → Telegram
```

Key points:

- **Routing is priority-driven.** A higher `priority` value wins. Ties are broken deterministically by file discovery order (sorted).
- **Middleware can short-circuit.** Returning `true` from `MiddlewareInterface::handle()` stops processing; the handler will not run.
- **Handlers never need to construct the Telegram client.** The `BaseHandler` already injects it via the container, and `$this->reply(...)` handles all the plumbing.
- **Errors are caught centrally.** `ErrorHandler` logs them and, for `UserFriendlyException`, sends a localised message to the user.

---

## Writing Handlers

Handlers are plain PHP classes that extend `App\Handlers\BaseHandler` and use attributes to declare how they should be matched.

### The Base Class

`BaseHandler` (in `app/Handlers/BaseHandler.php`) provides:

| Member | Description |
|--------|-------------|
| `$this->client` | The `Neili\Client` instance |
| `$this->language` | The `LanguageInterface` service |
| `$this->logger` | The `LoggerInterface` service |
| `$this->userRepository` | The application's user repository |
| `$this->update` | The raw update array |
| `handle(array $params = [])` | Abstract method where your logic goes |
| `reply(string $message, ?array $keyboard = null, array $extra = [], bool $asReply = true)` | Send a message replying to the original |
| `send(string $message, ?array $keyboard = null, array $extra = [])` | Send a standalone message |
| `getUserLanguage(): string` | Resolve the current user's language |
| `getUserId(): ?int` | Extract the Telegram user ID |

### Available Attributes

| Attribute | Purpose | Applies to |
|-----------|---------|-----------|
| `#[Text]` | Match a command or a translated text key | Handler class |
| `#[Callback]` | Match callback query data (exact or regex) | Handler class |
| `#[Step]` | Register a step in a conversation flow | Handler class |
| `#[Fallback]` | Catch-all when no other handler matched | Handler class |
| `#[OnUpdate]` | Restrict to one or more update types | Handler class |
| `#[Group]` | Assign the handler to middleware groups | Handler class |
| `#[UseMiddleware]` | Attach local middleware | Handler class |

### Example 1 — A Command Handler

```php
<?php
namespace App\Handlers;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use Reactor\Attributes\Group;

#[Group('private')]
#[Text(name: '/start', isCommand: true, priority: 100)]
#[Text(name: '/restart', isCommand: true, priority: 90)]
#[OnUpdate('message')]
class StartHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $user = $this->extractUser($this->update);
        if (!$user) {
            return;
        }

        $lang = $this->getUserLanguage();
        $text = $this->language->get('start_message', $lang);
        $keyboard = (new \App\Keyboard($this->language))->mainMenu($lang);

        $this->reply($text, $keyboard);
    }
}
```

Notes:
- A class can carry **multiple `#[Text]` attributes** – the handler responds to all of them.
- `isCommand: true` tells the router to match against the parsed command name, stripping the `@BotUsername` suffix automatically.
- `priority: 100` beats anything with a lower priority on the same trigger.

### Example 2 — A Text/Button Handler

```php
<?php
namespace App\Handlers;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use Reactor\Attributes\Group;

#[Group('private')]
#[Text(name: 'about', priority: 5)]
#[OnUpdate('message')]
class AboutHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $lang = $this->getUserLanguage();
        $text = $this->language->get('about_text', $lang);
        $keyboard = (new \App\Keyboard($this->language))->mainMenu($lang);

        $this->reply($text, $keyboard);
    }
}
```

The `name: 'about'` refers to a **translation key**. The router compares the incoming message text against the translated value of that key in the user's language. This means the same handler responds to `"ℹ️ About"` in English and `"ℹ️ درباره"` in Persian – no extra code required.

### Example 3 — A Callback Handler

```php
<?php
namespace App\Handlers;

use Reactor\Attributes\Callback;
use Reactor\Attributes\OnUpdate;
use Reactor\Attributes\Group;

#[Group('private')]
#[Callback(data: 'cancel', priority: 10)]
#[OnUpdate('callback_query')]
class CancelHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $this->reply('Cancelled.');
    }
}
```

Callback handlers can also use a regex pattern:

```php
#[Callback(data: 'item', pattern: '/^item:(\d+)$/', priority: 20)]
```

When a regex matches, the captured groups are passed to `handle()` as `$params`:

```php
protected function handle(array $params = []): void
{
    $itemId = $params[0] ?? null;
    $this->reply("You selected item #{$itemId}");
}
```

### Example 4 — A Step Handler

Steps are used for multi-step conversations. When the user's `step` column matches a registered step name, that step's handler runs.

```php
<?php
namespace App\Handlers\Steps;

use Reactor\Attributes\Step;
use Reactor\Attributes\OnUpdate;

#[Step(name: 'awaiting_name', nextStep: 'awaiting_email', autoClear: false)]
#[OnUpdate('message')]
class AwaitingNameStep extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $userId = $this->getUserId();
        $name = trim($this->update['message']['text'] ?? '');

        $this->userRepository->setTemp($userId, ['name' => $name]);
        $this->userRepository->setStep($userId, 'awaiting_email');

        $this->reply("Thanks, {$name}! What's your email?");
    }
}
```

Generate step handlers with:

```bash
php reactor.php make:step AwaitingNameStep
```

### Example 5 — A Fallback Handler

The fallback runs when no other handler matches. Only one fallback is used — the one with the **highest priority**.

```php
<?php
namespace App\Handlers;

use Reactor\Attributes\Fallback;

#[Fallback(priority: 0)]
class FallbackHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $lang = $this->getUserLanguage();
        $this->reply($this->language->get('unknown_command', $lang));
    }
}
```

If you delete this file, Reactor will fall back to its built-in `UnknownCommandHandler`, which sends the same `unknown_command` translation. The application-level fallback exists so you can customise the response.

### Nested Handlers

Handlers are discovered **recursively**. Any PHP file under `app/Handlers/` (including subdirectories) is scanned. Subdirectory names become part of the class namespace.

For example, `app/Handlers/Admin/PingHandler.php`:

```php
<?php
namespace App\Handlers\Admin;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use App\Handlers\BaseHandler;

#[Text(name: '/ping_admin', isCommand: true, priority: 50)]
#[OnUpdate('message')]
class PingHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $this->reply('pong from admin subfolder');
    }
}
```

No additional registration is required — the router picks it up automatically.

### Generating a Handler

```bash
php reactor.php make:handler EchoHandler
```

This creates `app/Handlers/EchoHandler.php` with a starter template.

---

## Writing Middleware

Middleware runs **before** the handler and can inspect, modify, or abort the update. Middleware is executed in three phases, in this order:

1. **Global** — runs for every update.
2. **Group** — runs when the handler's `#[Group]` matches.
3. **Local** — runs when the handler lists the middleware via `#[UseMiddleware]`.

Within each phase, middleware is sorted by **descending priority** (higher runs first).

### The Interface

```php
interface MiddlewareInterface
{
    public function handle(array $update): bool;
}
```

Return `true` to **stop** processing. Return `false` to **continue**.

### Example — Logging Middleware

```php
<?php
namespace App\Middleware;

use Reactor\Attributes\Middleware;
use Reactor\Attributes\OnUpdate;
use Reactor\Enums\MiddlewareMode;
use Reactor\Core\Config;
use Reactor\Contracts\LoggerInterface;
use Reactor\Contracts\MiddlewareInterface;

#[Middleware(priority: 10, mode: MiddlewareMode::GLOBAL)]
#[OnUpdate('any')]
class LogMiddleware implements MiddlewareInterface
{
    protected LoggerInterface $logger;
    protected Config $config;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function handle(array $update): bool
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('Message received', $update);
        } else {
            $fromId = $update['message']['from']['id']
                ?? $update['callback_query']['from']['id']
                ?? null;
            $this->logger->info('Update received', [
                'type'    => array_key_first($update) ?: 'unknown',
                'user_id' => $fromId,
            ]);
        }
        return false;
    }
}
```

Middleware is auto-discovered from `app/Middleware/`, so no registration is required.

### Middleware Modes

```php
use Reactor\Enums\MiddlewareMode;

#[Middleware(priority: 50, mode: MiddlewareMode::GLOBAL)]
#[Middleware(priority: 50, mode: MiddlewareMode::GROUP, groups: ['admin'])]
#[Middleware(priority: 50, mode: MiddlewareMode::LOCAL)]
```

- **GLOBAL** — always runs.
- **GROUP** — runs only for handlers whose `#[Group]` intersects the middleware's `groups` list.
- **LOCAL** — runs only when a handler explicitly lists it in `#[UseMiddleware]`.

### Restricting by Update Type

```php
#[OnUpdate('message')]
#[OnUpdate('message,callback_query')]
#[OnUpdate(['message', 'edited_message'])]
#[OnUpdate('any')]
```

### Generating a Middleware

```bash
php reactor.php make:middleware AuthMiddleware
```

### Dependency Injection in Middleware

Middleware is resolved from the container, so you can type-hint any service in the constructor:

```php
public function __construct(
    LoggerInterface $logger,
    Config $config,
    UserRepositoryInterface $users
) { ... }
```

The container resolves all dependencies automatically.

---

## Keyboard Builder

`App\Keyboard` is a small helper that wraps Neili's `KeyboardBuilder` and translates button labels automatically.

### Main Menu

```php
public function mainMenu(?string $lang = null): array
{
    $lang = $lang ?? $this->language->getDefaultLanguage();
    $kb = new KeyboardBuilder();
    $kb->row(
        $this->language->get('start', $lang),
        $this->language->get('about', $lang)
    );
    return $kb->resize(true)->oneTime(false)->build();
}
```

### Back Button

```php
public function backButton(?string $lang = null): array
```

### Custom Menu

```php
public function customMenu(array $buttons, ?string $lang = null): array
```

Pass a flat array of translation keys or an array of `['label' => 'key']` entries; buttons are laid out two per row.

### Usage

```php
$keyboard = (new Keyboard($this->language))->mainMenu($lang);
$this->reply('Hello!', $keyboard);
```

### Inline Keyboards

For inline keyboards (callback buttons), build the array manually and pass it to `reply()`:

```php
$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => 'Confirm', 'callback_data' => 'confirm'],
            ['text' => 'Cancel',  'callback_data' => 'cancel'],
        ],
    ],
];
$this->reply('Are you sure?', $keyboard);
```

Handlers decorated with `#[Callback(data: 'confirm')]` will receive the callback.

---

## Working with the Database

The skeleton uses `illuminate/database` (Eloquent) via the `EloquentManager` class, which implements the framework's `DatabaseManagerInterface`. Migrations are run through the CLI, and models are standard Eloquent models.

### Models

Two models ship with the skeleton:

**`App\Models\User`** — stores Telegram users, language preference, current step, and temporary data.

```php
class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'user_id', 'username', 'first_name', 'last_name',
        'language', 'step', 'temp',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'status'  => 'boolean',
        'temp'    => 'array',   // JSON → array automatically
    ];
}
```

**`App\Models\Job`** — represents a queued job record.

### Repository Pattern

User persistence is abstracted behind `UserRepositoryInterface` (`app/Contracts/Repository/UserRepositoryInterface.php`). The concrete implementation is `EloquentUserRepository`.

The repository is registered in `AppServiceProvider` and both the framework-level `UserProviderInterface` and the application-level `UserRepositoryInterface` alias the **same singleton**, so consumers get a consistent instance.

Available methods:

| Method | Description |
|--------|-------------|
| `syncUser(int $userId, ?string $username, ?string $firstName, ?string $lastName)` | Upsert a user |
| `getLanguage(int $userId): string` | Get the user's preferred language |
| `getUser(int $userId): ?array` | Fetch the user as an array |
| `getStep(int $userId): ?string` | Get the user's current step |
| `setStep(int $userId, ?string $step)` | Set or clear the step |
| `getTemp(int $userId): ?array` | Get temporary data |
| `setTemp(int $userId, array $data)` | Store temporary data |
| `clearTemp(int $userId)` | Clear temporary data |

### Migrations

Migrations extend `Reactor\Database\Migrations\Migration`:

```php
<?php
namespace App\Migrations;

use Reactor\Database\Migrations\Migration;
use Reactor\Contracts\DatabaseManagerInterface;

class CreateUsersTable extends Migration
{
    public function __construct(DatabaseManagerInterface $db)
    {
        parent::__construct($db);
    }

    public function up(): void
    {
        if (!$this->db->schema()->hasTable('users')) {
            $this->db->schema()->create('users', function ($table) {
                $table->increments('id');
                $table->bigInteger('user_id')->unique();
                $table->string('username', 64)->nullable();
                $table->string('first_name', 64)->nullable();
                $table->string('last_name', 64)->nullable();
                $table->boolean('status')->default(true);
                $table->string('step', 255)->nullable();
                $table->json('temp')->nullable();
                $table->string('language', 10)->default('en');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $this->db->schema()->dropIfExists('users');
    }
}
```

Generate a new migration:

```bash
# Plain migration
php reactor.php migrate:make add_age_to_users_table

# Create a new table (generates a create_*_table stub)
php reactor.php migrate:make --create=posts

# Modify an existing table (generates an update_*_table stub)
php reactor.php migrate:make --table=users
```

Migration files are named `YYYY_MM_DD_HHMMSS_snake_case_name.php`. The class name is derived by CamelCasing the portion after the timestamp.

### Seeding

```php
<?php
namespace Reactor\Seeders;

use Reactor\Database\Seeders\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Insert initial data.
    }
}
```

Run with:

```bash
php reactor.php seed
```

You can run a specific seeder class:

```bash
php reactor.php seed --class="Reactor\Seeders\DatabaseSeeder"
```

### Using the Database Directly

Resolve the manager from the container:

```php
$db = $container->get(DatabaseManagerInterface::class);

$db->table('users')->where('user_id', $id)->update(['step' => null]);
$db->transaction(function () use ($db) {
    // ...
});
```

Or just use Eloquent models directly:

```php
User::where('user_id', $id)->first();
User::updateOrCreate(['user_id' => $id], ['username' => $username]);
```

---

## Queue System

Reactor ships with a database-backed queue. Jobs are dispatched through the `QueueManager` and processed by a worker command.

### Writing a Job

Extend `Reactor\Queue\BaseJob` and implement `JobInterface`:

```php
<?php
namespace App\Jobs;

use Reactor\Queue\BaseJob;
use Reactor\Contracts\JobInterface;

class SendMessageJob extends BaseJob implements JobInterface
{
    public function handle(array $data): void
    {
        $chatId   = $data['chatId']   ?? null;
        $text     = $data['text']     ?? '';
        $keyboard = $data['keyboard'] ?? null;

        if ($chatId === null) {
            $this->logger->warning('SendMessageJob skipped: missing chatId', ['data' => $data]);
            return;
        }

        $this->client->sendMessage($chatId, $text, $keyboard);
        $this->logger->info("Delayed message sent to {$chatId}");
    }
}
```

`BaseJob` provides:
- `$this->client` — the Neili client
- `$this->logger`
- `$this->language`
- `$this->userProvider`

### Dispatching a Job

```php
$queue = $container->get(QueueManager::class);

$queue->push(SendMessageJob::class, [
    'chatId' => 123456789,
    'text'   => 'Hello from the queue!',
], queue: 'default', delay: 60);
```

### Processing Jobs

```bash
php reactor.php queue:work --queue=default --max=10
```

- `--queue` (or `-q`): queue name (default: `default`)
- `--max` (or `-m`): maximum jobs to process before exiting (default: 10)

The worker:
1. Pops a job inside a transaction.
2. Resolves the job class from the container (so constructor DI works).
3. Calls `handle($payload['data'])`.
4. On success, deletes the job.
5. On failure, releases the job back onto the queue with a 60-second delay.

For production, run the worker under a supervisor (systemd, supervisord, etc.) so it restarts on crash.

---

## Localisation

Translations are stored as flat JSON files under `lang/`, keyed by language code.

### Adding Strings

`lang/en.json`:

```json
{
    "start": "🏠 Start",
    "about": "ℹ️ About",
    "back": "🔙 Back",
    "start_message": "Welcome to the bot!",
    "about_text": "This is a sample bot.",
    "unknown_command": "Unknown command",
    "error": "An error occurred. Please try again.",
    "blocked": "You have been blocked for {duration} seconds due to spam."
}
```

`lang/fa.json`:

```json
{
    "start": "🏠 شروع",
    "about": "ℹ️ درباره",
    "back": "🔙 بازگشت",
    "start_message": "به ربات خوش آمدید!",
    "about_text": "این یک ربات نمونه است.",
    "unknown_command": "دستور ناشناخته",
    "error": "خطایی رخ داد. لطفاً دوباره تلاش کنید.",
    "blocked": "شما به دلیل ارسال پیام‌های مکرر به مدت {duration} ثانیه مسدود شدید."
}
```

### Using Translations

```php
$lang = $this->getUserLanguage();
$text = $this->language->get('start_message', $lang);

// With placeholders
$text = $this->language->get('blocked', $lang, ['duration' => 30]);
```

If a key is missing in the requested language, the default language's value is used. If it's missing there too, the key itself is returned.

### Managing the User's Language

The `SyncUserMiddleware` sets the language automatically from Telegram's `language_code` the first time a user is seen. To update a user's language:

```php
User::where('user_id', $userId)->update(['language' => 'fa']);
```

### Adding a New Language

1. Create `lang/<code>.json` (e.g. `lang/ar.json`).
2. Add the translated keys.
3. Optionally add the language name to `app.language_names` in `config/app.php`.

No code changes are needed — the `Language` service loads all JSON files at boot.

---

## Webhook Mode

Webhook mode is faster and cheaper than polling because Telegram pushes updates directly to your server instead of your bot repeatedly asking for them. Use it in production.

### Enabling Webhook Mode

In `.env`:

```env
BOT_MODE=webhook
WEBHOOK_SECRET=your-secret-here
```

`public_html/webhook.php` is the entry point:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Reactor\Core\App;

$basePath = __DIR__ . '/..';
$appProviders = [ \App\Providers\AppServiceProvider::class ];
$app = new App($basePath, [], [], $appProviders);

$client = $app->getClient();
$logger = $app->getLogger();
$config = $app->getConfig();

$secret = $config->getWebhookSecret();

try {
    $update = $client->handleUpdate($secret);
    if (!empty($update)) {
        $app->processUpdate($update);
    }
} catch (\Throwable $e) {
    $logger->error("Webhook error: " . $e->getMessage());
}
```

### Registering the Webhook

Point Telegram at your public URL and pass the same secret:

```bash
curl -F "url=https://your-domain.com/webhook.php" \
     -F "secret_token=your-secret-here" \
     "https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook"
```

### Important Notes

- The webhook endpoint **must be HTTPS** with a valid certificate. Telegram rejects HTTP.
- `public_html/` is the document root on your server. Point your vhost (Apache, Nginx, Caddy) at it.
- No long-running process is needed in webhook mode. Every update is a fresh PHP request.
- The `webhook_secret` must match Telegram's `secret_token`. Reactor verifies the `X-Telegram-Bot-Api-Secret-Token` header automatically.

### Nginx Example

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    root /var/www/my-bot/public_html;
    index webhook.php;

    location / {
        try_files $uri /webhook.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## CLI Commands

The `reactor.php` script at the project root bootstraps Reactor and exposes a Symfony Console application.

```bash
php reactor.php <command> [options] [arguments]
```

### Migration Commands

| Command | Description |
|---------|-------------|
| `migrate` | Run all pending migrations |
| `migrate:rollback [--step=N]` | Roll back the last N batches (default 1) |
| `migrate:reset` | Roll back all migrations |
| `migrate:refresh` | Reset and re-run all migrations |
| `migrate:status` | Show each migration's status (ran / pending) |
| `migrate:make <name>` | Create a new migration file |
| `migrate:make --create=posts` | Shortcut for `create_posts_table` |
| `migrate:make --table=users` | Shortcut for `update_users_table` |

### Database Seeding

| Command | Description |
|---------|-------------|
| `seed` | Run the default seeder |
| `seed --class="App\Seeders\CustomSeeder"` | Run a specific seeder class |

### Code Generation

| Command | Description |
|---------|-------------|
| `make:handler <name>` | Create a new handler class |
| `make:middleware <name>` | Create a new middleware class |
| `make:step <name>` | Create a new step handler class |

### Queue & Scheduling

| Command | Description |
|---------|-------------|
| `queue:work [--queue=name] [--max=N]` | Process queued jobs |
| `schedule:run` | Run due scheduled tasks |

### Packages

| Command | Description |
|---------|-------------|
| `package:discover` | Rebuild the cached package manifest |
| `vendor:publish <provider>` | Publish a package's assets |
| `vendor:publish --all [--force]` | Publish every package's assets |

### Scheduling

Run `schedule:run` every minute via cron:

```cron
* * * * * cd /var/www/my-bot && php reactor.php schedule:run >> /dev/null 2>&1
```

Define scheduled tasks in a `ScheduleKernel` subclass (see `config/app.php` → `schedule_kernel`).

---

## Multi-Process Mode

Reactor can process updates using multiple concurrent worker processes. Enable it in `.env`:

```env
MULTI_PROCESS=true
PHP_BINARY=/usr/bin/php
```

When enabled, `TelegramBootstrapper` configures Neili's `Settings` with `setMultiProcess(true)` and passes the PHP binary path. Neili then forks worker processes to handle updates in parallel.

Choose multi-process mode when:

- You receive a very high volume of updates.
- Handlers perform blocking operations that you cannot easily make async.
- You want to isolate a crashing handler from the rest of the bot.

Leave it disabled when:

- Your handlers are already async (they usually are, since Reactor + Neili are async).
- You are running on shared hosting where `pcntl` or `proc_open` is restricted.
- You need a single, deterministic execution order.

---

## Production Checklist

Before deploying your bot, make sure you have:

- [ ] **Set `APP_ENV=production` and `DEBUG_MODE=false`** in `.env`.
- [ ] **Set `TELEGRAM_VERIFY_SSL=true`** (never disable TLS verification in production).
- [ ] **Protected your `.env`** – it should never be committed to Git and should be readable only by the web/CLI user.
- [ ] **Run migrations** with `php reactor.php migrate` before starting.
- [ ] **Configure a supervisor** for the queue worker and (in polling mode) for `bot.php`.
- [ ] **Set up log rotation** – Monolog writes to `storage/logs/app.log` with a 30-day rotation policy out of the box.
- [ ] **Add a cron entry** for `schedule:run` if you use the scheduler.
- [ ] **Prefer webhook mode** over polling on any serious deployment – it's cheaper and lower latency.
- [ ] **Firewall your SQLite or MySQL instance** so it's only reachable from your application.
- [ ] **Back up your database** regularly. For SQLite, a simple file copy is enough.

### Example: systemd Unit for the Polling Process

```ini
[Unit]
Description=My Telegram Bot (Reactor)
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/my-bot
ExecStart=/usr/bin/php /var/www/my-bot/reactor.php start
Restart=always
RestartSec=5
StandardOutput=append:/var/log/my-bot.log
StandardError=append:/var/log/my-bot.err

[Install]
WantedBy=multi-user.target
```

### Example: systemd Unit for the Queue Worker

```ini
[Unit]
Description=My Telegram Bot Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/my-bot
ExecStart=/usr/bin/php /var/www/my-bot/reactor.php queue:work --max=1000
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

---

## Contributing

Contributions to the skeleton are welcome. Please:

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/amazing-feature`.
3. Commit your changes: `git commit -m 'Add some amazing feature'`.
4. Push the branch: `git push origin feature/amazing-feature`.
5. Open a Pull Request.

Please keep your code consistent with the existing style and ensure existing tests still pass. If you add functionality, add a corresponding test where practical.

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.