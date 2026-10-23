# Reactor — Documentation

Complete reference for the Reactor application skeleton.

---

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Project Structure](#project-structure)
- [The Update Lifecycle](#the-update-lifecycle)
- [Writing Handlers](#writing-handlers)
- [Writing Middleware](#writing-middleware)
- [Keyboard Builder](#keyboard-builder)
- [Working with the Database](#working-with-the-database)
- [Queue System](#queue-system)
- [Localisation](#localisation)
- [Webhook Mode](#webhook-mode)
- [CLI Commands](#cli-commands)
- [Multi-Process Mode](#multi-process-mode)
- [Production Checklist](#production-checklist)
- [License](#license)

---

## Overview

Reactor is a modern, asynchronous PHP framework for building production-ready Telegram bots. This application skeleton is the recommended starting point for any new Reactor project: it ships with a complete project layout, working example handlers, middleware, migrations, localisation files, and CLI commands, so you can go from `composer create-project` to a running bot in under five minutes.

### Architecture

Under the hood, the skeleton is powered by three complementary technologies:

- **[afaztech/reactor-framework](https://github.com/afaztech/reactor-framework)** — the framework itself. It provides the dependency injection container, attribute-based routing, middleware pipeline, service providers, config layering, migrations, queue system, scheduler, caching, event dispatcher, and the CLI.
- **[Amp](https://amphp.org/)** — the asynchronous concurrency framework. Reactor is built on Amp's fiber-based event loop, which enables truly non-blocking I/O. Your bot can handle many concurrent requests without spawning threads or processes, and long-running operations (HTTP calls, database queries, file I/O) never block the event loop.
- **[Neili](https://github.com/AfazTech/neili)** — the asynchronous Telegram client library. Neili wraps the entire Telegram Bot API in non-blocking methods, provides a long-polling `Poller` with concurrency control, and handles webhook input parsing. It is what actually talks to Telegram on Reactor's behalf.

Together these three layers give you a bot that is **fast, memory-efficient, and easy to reason about** – while still feeling like a familiar, Laravel-inspired PHP application.

```
┌──────────────────────────────────────────────────────────────┐
│                        TELEGRAM SERVERS                       │
└───────────────────────────┬──────────────────────────────────┘
                            │  getUpdates / webhook
                            ▼
┌──────────────────────────────────────────────────────────────┐
│              Neili  (async Telegram client)                   │
│  • Async HTTP calls built on Amp                              │
│  • Long-polling Poller with concurrency control               │
│  • Webhook payload parsing                                    │
└───────────────────────────┬──────────────────────────────────┘
                            │  decoded update array
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    Amp  (event loop & fibers)                 │
│  • Non-blocking I/O                                           │
│  • Fiber scheduling                                           │
│  • Futures and async primitives                               │
└───────────────────────────┬──────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                  Reactor  (your bot framework)                │
│  • Router → Middleware → Handler → Response                   │
│  • DI container, config, database, queue, scheduler, cache    │
└──────────────────────────────────────────────────────────────┘
```

When an update arrives:

1. **Neili** receives and decodes it.
2. **Amp** schedules the processing fiber on the event loop.
3. **Reactor** resolves a handler, runs the middleware pipeline, invokes the handler, and lets the handler send a reply (again via Neili, again async).
4. The whole thing happens without blocking, so other updates can be processed concurrently.

---

## Requirements

- **PHP >= 8.1** — Reactor uses enums, readonly properties, first-class callable syntax, and fibers
- **Composer**
- **PDO extension** for your chosen database
- **ext-json**, **ext-mbstring** — usually enabled by default
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
./reactor migrate
```

This creates the `users`, `jobs`, and `failed_jobs` tables.

### 4. Start the bot

```bash
./reactor start
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

MySQL and other drivers are supported through `illuminate/database`. Add a connection entry and change `DB_CONNECTION`.

### `config/cache.php`

| Key | Env variable | Default | Description |
|-----|--------------|---------|-------------|
| `default` | `CACHE_DRIVER` | `file` | `array`, `file`, `redis`, or `memcached` |

---

## Project Structure

```
.
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── Concerns/
│   │   │   │   └── ConfirmsDestructiveCommands.php
│   │   │   ├── MigrateRefreshCommand.php
│   │   │   ├── MigrateResetCommand.php
│   │   │   ├── MigrateRollbackCommand.php
│   │   │   ├── QueueFlushCommand.php
│   │   │   └── StartCommand.php
│   │   └── ReactorProcessManager.php
│   ├── Contracts/
│   │   └── Repository/
│   │       └── UserRepositoryInterface.php
│   ├── Database/
│   │   └── EloquentManager.php
│   ├── Handlers/
│   │   ├── AboutHandler.php
│   │   ├── BaseHandler.php
│   │   ├── FallbackHandler.php
│   │   ├── LanguageSelectionHandler.php
│   │   └── StartHandler.php
│   ├── Jobs/
│   │   └── SendMessageJob.php
│   ├── Keyboard.php
│   ├── Middleware/
│   │   ├── LogMiddleware.php
│   │   └── SyncUserMiddleware.php
│   ├── Models/
│   │   ├── Job.php
│   │   └── User.php
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Repositories/
│       └── EloquentUserRepository.php
├── bootstrap/
│   └── cache/
│       └── packages.php
├── config/
│   ├── app.php
│   ├── bot.php
│   ├── cache.php
│   └── database.php
├── database/
│   ├── migrations/
│   │   ├── 2026_07_16_000002_create_users_table.php
│   │   ├── 2026_07_22_000000_create_jobs_table.php
│   │   └── 2026_10_09_100000_create_failed_jobs_table.php
│   ├── seeders/
│   │   └── DatabaseSeeder.php
│   └── test.sqlite
├── lang/
│   ├── en.json
│   └── fa.json
├── public_html/
│   └── webhook.php
├── .env.example
├── .gitignore
├── composer.json
├── DOCUMENT.md
├── LICENSE
├── README.md
└── reactor
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
| `$this->client` | The [Neili](https://github.com/AfazTech/neili) `Client` instance |
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
use App\Keyboard;

#[Group('private')]
#[Text(name: '/start', isCommand: true, priority: 100)]
#[Text(name: '/restart', isCommand: true, priority: 90)]
#[OnUpdate('message')]
class StartHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return;
        }

        $lang = $this->getUserLanguage();
        $text = $this->language->get('start_message', $lang);
        $keyboard = (new Keyboard($this->language))->mainMenu($lang);

        $this->reply($text, $keyboard);
    }
}
```

Notes:
- A class can carry **multiple `#[Text]` attributes** — the handler responds to all of them.
- `isCommand: true` tells the router to match against the parsed command name, stripping the `@BotUsername` suffix automatically.
- `priority: 100` beats anything with a lower priority on the same trigger.

### Example 2 — A Text/Button Handler

```php
<?php
namespace App\Handlers;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use Reactor\Attributes\Group;
use App\Keyboard;

#[Group('private')]
#[Text(name: 'about', priority: 5)]
#[OnUpdate('message')]
class AboutHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $lang = $this->getUserLanguage();
        $text = $this->language->get('about_text', $lang);
        $keyboard = (new Keyboard($this->language))->mainMenu($lang);

        $this->reply($text, $keyboard);
    }
}
```

The `name: 'about'` refers to a **translation key**. The router compares the incoming message text against the translated value of that key in the user's language. This means the same handler responds to `"ℹ️ About"` in English and `"ℹ️ درباره"` in Persian — no extra code required.

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
./reactor make:step AwaitingNameStep
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

If you delete this file, Reactor falls back to its built-in `UnknownCommandHandler`, which sends the same `unknown_command` translation. The application-level fallback exists so you can customise the response.

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
./reactor make:handler EchoHandler
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
./reactor make:middleware AuthMiddleware
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

### Language Selection

```php
public function languageSelection(?array $languages = null): array
```

Builds an inline keyboard with one button per available language. The button label is resolved by `resolveLanguageName()`, which prefers the `language_name` key inside each `lang/<code>.json` file over the framework's built-in name map.

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

For Neili's full keyboard builder API — reply rows, inline rows, URL buttons, web-app buttons, and more — see the [Neili documentation](https://github.com/AfazTech/neili).

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
        'status', 'language', 'step', 'temp', 'last_interaction_at',
    ];

    protected $casts = [
        'user_id'             => 'integer',
        'status'              => 'boolean',
        'temp'                => 'array',   // JSON → array automatically
        'last_interaction_at' => 'datetime',
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
| `setLanguage(int $userId, string $language)` | Persist a language preference |
| `getUser(int $userId): ?array` | Fetch the user as an array |
| `getStep(int $userId): ?string` | Get the user's current step |
| `setStep(int $userId, ?string $step)` | Set or clear the step |
| `getTemp(int $userId): ?array` | Get temporary data |
| `setTemp(int $userId, array $data)` | Store temporary data |
| `clearTemp(int $userId)` | Clear temporary data |
| `setStatus(int $userId, int $status)` | Update the activity flag |
| `setLastInteraction(int $userId)` | Refresh the last-interaction timestamp |

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
                // ...
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
./reactor migrate:make add_age_to_users_table

# Create a new table (generates a create_*_table stub)
./reactor migrate:make --create=posts

# Modify an existing table (generates an update_*_table stub)
./reactor migrate:make --table=users
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
./reactor seed
```

You can run a specific seeder class:

```bash
./reactor seed --class="Reactor\Seeders\DatabaseSeeder"
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
- `$this->client` — the [Neili](https://github.com/AfazTech/neili) client
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
./reactor queue:work --queue=default --max=10
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
2. Add the translated keys, including `"language_name": "Arabic"` so it shows up correctly in the language-selection keyboard.
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

The `reactor` script at the project root bootstraps Reactor and exposes a Symfony Console application.

```bash
./reactor <command> [options] [arguments]
```

### Migration Commands

| Command | Description |
|---------|-------------|
| `migrate` | Run all pending migrations |
| `migrate:rollback [--step=N]` | Roll back the last N batches (default 1); asks for confirmation |
| `migrate:reset` | Roll back all migrations; asks for confirmation |
| `migrate:refresh` | Reset and re-run all migrations; asks for confirmation |
| `migrate:status` | Show each migration's status (ran / pending) |
| `migrate:make <name>` | Create a new migration file |
| `migrate:make --create=posts` | Shortcut for `create_posts_table` |
| `migrate:make --table=users` | Shortcut for `update_users_table` |

The destructive migration commands (`migrate:rollback`, `migrate:reset`, `migrate:refresh`) are overridden by the skeleton to require explicit confirmation on an interactive terminal, or a `--force` flag in non-interactive environments. This prevents a misconfigured cron or systemd unit from wiping the database silently.

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
| `queue:flush` | Delete every failed job; asks for confirmation |
| `schedule:run` | Run due scheduled tasks |

### Packages

| Command | Description |
|---------|-------------|
| `package:discover` | Rebuild the cached package manifest |
| `vendor:publish <provider>` | Publish a package's assets |
| `vendor:publish --all [--force]` | Publish every package's assets |

### Lifecycle

| Command | Description |
|---------|-------------|
| `start` | Start the bot in polling mode (refuses to start while a webhook is active) |
| `stop` | Stop the polling process |
| `restart` | Restart the polling process |

The `start` command is overridden by the skeleton to check the current webhook state before polling. Polling (`getUpdates`) and webhooks are mutually exclusive in the Telegram Bot API: while a webhook is registered, `getUpdates` returns a `409 Conflict` and the bot appears silently dead. When the skeleton detects an active webhook, it either deletes it (interactive prompt, or automatic in non-interactive mode), or aborts with a clear error if `--keep-webhook` was passed.

### Scheduling

Run `schedule:run` every minute via cron:

```cron
* * * * * cd /var/www/my-bot && ./reactor schedule:run >> /dev/null 2>&1
```

Define scheduled tasks in a `ScheduleKernel` subclass (see `config/app.php` → `schedule_kernel`).

---

## Multi-Process Mode

Reactor can process updates using multiple concurrent worker processes. Enable it in `.env`:

```env
MULTI_PROCESS=true
PHP_BINARY=/usr/bin/php
```

When enabled, `TelegramBootstrapper` configures [Neili](https://github.com/AfazTech/neili)'s `Settings` with `setMultiProcess(true)` and passes the PHP binary path. Neili then forks worker processes to handle updates in parallel.

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
- [ ] **Protected your `.env`** — it should never be committed to Git and should be readable only by the web/CLI user.
- [ ] **Run migrations** with `./reactor migrate` before starting.
- [ ] **Configure a supervisor** for the queue worker and (in polling mode) for the bot process.
- [ ] **Set up log rotation** — Monolog writes to `storage/logs/app.log` with a 30-day rotation policy out of the box.
- [ ] **Add a cron entry** for `schedule:run` if you use the scheduler.
- [ ] **Prefer webhook mode** over polling on any serious deployment — it's cheaper and lower latency.
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
ExecStart=/usr/bin/php /var/www/my-bot/reactor start
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
ExecStart=/usr/bin/php /var/www/my-bot/reactor queue:work --max=1000
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

---

## Further Reading

- **[afaztech/reactor-framework](https://github.com/afaztech/reactor-framework)** — the framework itself
- **[Amp](https://amphp.org/)** — the asynchronous concurrency framework
- **[Neili](https://github.com/AfazTech/neili)** — the async Telegram Bot API client

---

## License

This project is licensed under the [MIT License](LICENSE).
EOF