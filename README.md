# Reactor

Application starter for [Reactor](https://github.com/afaztech/reactor-framework) — a PHP framework for building Telegram bots. This repository is a ready-to-run project skeleton: clone it, configure your bot token, and start building handlers.

Looking for the framework internals (DI container, router, middleware pipeline, etc.) instead? See [afaztech/reactor-framework](https://github.com/afaztech/reactor-framework).

## Requirements

* PHP >= 8.1
* Composer
* SQLite or MySQL (or any database supported by `illuminate/database`)
* Optional: Redis or Memcached, if you switch the cache driver

## Quick Start

```bash
composer create-project afaztech/reactor my-bot
cd my-bot
cp .env.example .env
```

Open `.env` and set your bot token:

```
TOKEN=your-telegram-bot-token
BOT_MODE=polling
DB_CONNECTION=sqlite
DB_DATABASE=database/test.sqlite
```

Run the initial migrations:

```bash
php reactor.php migrate
```

Start the bot:

```bash
php bot.php
```

Send `/start` to your bot in Telegram — you should get a reply with the main menu keyboard.

## Project Structure

```
.
├── app/
│   ├── Contracts/Repository/   # Application-level repository interfaces
│   ├── Database/                # Eloquent-based DatabaseManagerInterface implementation
│   ├── Handlers/                 # Your bot's command/callback/step handlers
│   ├── Jobs/                     # Queue jobs
│   ├── Middleware/               # Global/group/handler middleware
│   ├── Models/                   # Eloquent models
│   ├── Providers/                # Service providers (DI bindings)
│   ├── Repositories/             # Repository implementations
│   └── Keyboard.php              # Reusable Telegram keyboard builder
├── config/                       # app, bot, cache, database config files
├── database/
│   ├── migrations/               # Schema migrations
│   └── seeders/                  # Database seeders
├── lang/                         # en.json / fa.json translation files
├── public_html/webhook.php       # Entry point for webhook mode
├── bot.php                       # Entry point for polling mode
└── reactor.php                   # CLI (migrations, seeders, queue worker, scaffolding)
```

## Configuration

All config files live in `config/` and read from `.env` via the `env()` helper.

| File | Purpose |
|---|---|
| `config/app.php` | App name, environment, debug mode, default language |
| `config/bot.php` | Bot token, API URL, polling/webhook mode, multi-process, webhook secret |
| `config/database.php` | SQLite/MySQL connections |
| `config/cache.php` | Cache driver: `array`, `file`, `redis`, or `memcached` |

Key `.env` variables:

```
TOKEN=                # Telegram bot token
BOT_MODE=polling       # polling | webhook
MULTI_PROCESS=false    # enable multi-process update handling
DB_CONNECTION=sqlite   # sqlite | mysql
CACHE_DRIVER=file      # array | file | redis | memcached
```

## Creating a Handler

Handlers are plain classes decorated with attributes; Reactor's router discovers them automatically:

```bash
php reactor.php make:handler EchoHandler
```

```php
<?php
namespace App\Handlers;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use Reactor\Attributes\Group;

#[Group('private')]
#[Text(name: '/echo', isCommand: true, priority: 10)]
#[OnUpdate('message')]
class EchoHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $this->reply('You said something!');
    }
}
```

The skeleton ships with three working examples:

* **`StartHandler`** — responds to `/start`, `/restart`, and the "back"/"start" keyboard buttons with the main menu.
* **`AboutHandler`** — responds to the "about" keyboard button.
* **`FallbackHandler`** — catches any update no other handler matched.

## Middleware

* **`LogMiddleware`** (priority 10, global) — logs every incoming update.
* **`SyncUserMiddleware`** (priority 50, global) — upserts the Telegram user into the database on every update and dispatches a `USER_REGISTERED` event for new users.

Register new middleware in `app/Middleware/` with the `#[Middleware]` attribute; no manual registration needed.

## Database

Migrations and seeders follow a Laravel-style workflow:

```bash
php reactor.php migrate                 # run pending migrations
php reactor.php migrate:make create_x    # scaffold a new migration
php reactor.php migrate:rollback         # roll back the last batch
php reactor.php migrate:refresh          # reset + re-run all migrations
php reactor.php seed                     # run database seeders
```

The skeleton includes `users` and `jobs` tables out of the box. User records track `step` and `temp` (JSON) columns, used for conversational multi-step flows.

## Queue

A simple database-backed queue is wired up via `AppServiceProvider`:

```bash
php reactor.php queue:work
```

Dispatch jobs like `SendMessageJob` (included as an example) to send messages asynchronously.

## Running in Webhook Mode

Set in `.env`:

```
BOT_MODE=webhook
WEBHOOK_SECRET=your-secret
```

Point your Telegram webhook at `public_html/webhook.php` on your server, and call Telegram's `/setwebhook` endpoint with the same secret.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.