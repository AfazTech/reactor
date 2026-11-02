# Reactor

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Reactor Framework](https://img.shields.io/badge/Reactor-Framework-orange.svg)](https://github.com/afaztech/reactor-framework)

**Reactor** is a modern, asynchronous PHP framework for building powerful, production-ready Telegram bots.

This repository is the official application skeleton for the [`afaztech/reactor-framework`](https://github.com/afaztech/reactor-framework) package. It ships with a complete project layout, working example handlers, middleware, migrations, localisation files, and CLI commands, so you can go from `composer create-project` to a running bot in under five minutes.

It is the fastest way to start a new project and the recommended path for exploring what the framework can do.

## Features

- Attribute-based routing
- Asynchronous by design
- Middleware pipeline
- Dependency injection
- Service providers
- Multi-source configuration
- Eloquent ORM
- Database-backed queue
- Cron scheduler
- Multi-driver caching
- JSON localisation
- Package ecosystem
- Polling and webhook modes
- Multi-process support

## Requirements

- PHP >= 8.4
- `afaztech/reactor-framework` ^0.8.0
- `afaztech/neili` ^4.0

## Installation

```bash
composer create-project afaztech/reactor my-bot
cd my-bot
cp .env.example .env
```

Set your bot token in `.env`, then:

```bash
./reactor migrate
./reactor start
```

## Usage

Send `/start` to your bot on Telegram. The bot replies with a welcome message and the main menu keyboard.

## Documentation

For detailed documentation, see [DOCUMENT.md](DOCUMENT.md).

## Contributing

Contributions are welcome. Please read the contribution guidelines.

## License

This project is licensed under the [MIT License](LICENSE).

## Async database access and optional Eloquent

User synchronization, language selection, routing state and temporary user data
use `AsyncUserRepository` by default. Its existing repository methods await
AMPHP database queries internally: the current fiber waits, while the bot event
loop remains free. The default database is SQLite with the async SQLite driver.

Both repositories are available at the same time through constructor injection:

```php
use App\Repositories\AsyncUserRepository;
use App\Repositories\EloquentUserRepository;

public function __construct(
    private AsyncUserRepository $asyncUsers,
    private EloquentUserRepository $eloquentUsers,
) {}
```

`UserRepositoryInterface` and the framework's `UserProviderInterface` resolve to
the same async repository by default. Request `EloquentUserRepository` explicitly
where synchronous ORM access is needed; no environment switch is required.
Both repositories use the same database and users table. Eloquent models such as
`App\Models\User` and `DatabaseManagerInterface` remain available for custom
code, migrations and worker operations. Direct Eloquent queries are synchronous;
prefer `AsyncDatabaseManagerInterface` for I/O in the polling process.

Run application tests with `composer test` after `composer install`.
