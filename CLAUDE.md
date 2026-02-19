# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Kimai is an open-source time-tracking application built on **Symfony 6.4** (PHP 8.1+) with a **Doctrine ORM** backend (MariaDB/MySQL), **Twig** templates, and **Webpack Encore** for frontend assets.

## Commands

### PHP / Backend

```bash
composer install                  # Install PHP dependencies
composer linting                  # Lint YAML, Twig, XLIFF, container, and Doctrine schema
composer codestyle                # Check code style (dry-run, no changes)
composer codestyle-fix            # Fix code style issues
composer phpstan                  # Run PHPStan static analysis (src + tests)
composer phpstan-src              # PHPStan on src/ only
composer phpstan-tests            # PHPStan on tests/ only
composer tests                    # Run all tests (unit + integration)
composer tests-unit               # Run unit tests only
composer tests-integration        # Run integration tests only
composer pre-commit               # codestyle + phpstan + linting + unit tests
composer code-check               # pre-commit + integration tests (full CI suite)
```

Run a single test file:
```bash
vendor/bin/phpunit tests/Path/To/SomeTest.php
```

Run a specific test method:
```bash
vendor/bin/phpunit --filter testMethodName tests/Path/To/SomeTest.php
```

### Frontend Assets

```bash
yarn install       # Install Node dependencies
yarn dev           # Webpack build (development)
yarn watch         # Webpack watch mode
yarn build         # Webpack build (production)
yarn lint          # ESLint for JS assets
```

### Symfony Console

```bash
bin/console doctrine:migrations:migrate    # Run pending migrations
bin/console doctrine:schema:validate       # Validate schema
bin/console cache:clear                    # Clear application cache
bin/console kimai:user:create              # Create a user
```

## Test Database Setup

Tests require a dedicated MariaDB database. One-time setup:

```sql
CREATE DATABASE IF NOT EXISTS `kimai2_test`;
CREATE USER IF NOT EXISTS `kimai2_test`@127.0.0.1;
ALTER USER `kimai2_test`@127.0.0.1 IDENTIFIED BY "kimai2_test";
GRANT execute,select,insert,update,delete,create,alter,drop,index,references ON `kimai2_test`.* TO kimai2_test@127.0.0.1;
```

Connection string (in `phpunit.xml.dist`):
```
mysql://kimai2_test:kimai2_test@127.0.0.1:3306/kimai2_test?charset=utf8mb4&serverVersion=10.5.8-MariaDB
```

To rebuild the test schema (e.g., after migrations), set `BOOTSTRAP_RESET_DATABASE=true` in `phpunit.xml.dist`, run tests once, then set it back to `false`.

Integration tests use `DAMA\DoctrineTestBundle` for transaction-based isolation.

## Architecture

### Directory Structure

| Path | Purpose |
|------|---------|
| `src/` | Application source code (PSR-4 namespace `App\`) |
| `tests/` | PHPUnit tests, mirroring `src/` structure (namespace `App\Tests\`) |
| `templates/` | Twig templates |
| `translations/` | XLIFF translation files (30+ locales) |
| `migrations/` | Doctrine migration files |
| `assets/` | JS/CSS source files (compiled by Webpack) |
| `public/` | Web root; compiled assets go to `public/build/` |
| `config/` | Symfony configuration (YAML) |
| `var/plugins/` | Kimai plugins (PSR-4 namespace `KimaiPlugin\`) |
| `var/packages/` | Local Composer artifact repository for plugins |

### Key Source Modules (`src/`)

- **Entity/** — Doctrine ORM entities: `Timesheet`, `User`, `Customer`, `Project`, `Activity`, `Invoice`, etc.
- **Repository/** — Doctrine repositories with complex query builders for filtering/searching
- **Controller/** — Symfony controllers handling web routes; API controllers are in **API/**
- **API/** — REST API endpoints (FOSRestBundle), request/response models, OpenAPI annotations
- **Form/** — Symfony form types for all entities
- **Security/** + **Voter/** — Role-based access control; permissions are defined in `config/packages/kimai.yaml`
- **Timesheet/** — Core business logic: `RateService`, `RateCalculator`, `RoundingService`, `TimesheetService`
- **Invoice/** — Invoice rendering using Twig/Docx/ODS templates; `InvoiceService`, `InvoiceItemFactory`
- **Export/** — CSV/Excel/PDF export system; `TimesheetExportRepository`
- **Reporting/** — Report definitions and data aggregation
- **Event/** + **EventSubscriber/** — Symfony event system used extensively for extensibility
- **Configuration/** — `SystemConfiguration` wraps all Kimai settings from `kimai.yaml`
- **Plugin/** — Plugin discovery and management
- **Twig/** — Twig extensions, runtime services, and custom filters/functions
- **Widget/** — Dashboard widget definitions and renderers
- **WorkingTime/** — Working time calculations and approval workflows

### Key Config Files

| File | Purpose |
|------|---------|
| `config/packages/kimai.yaml` | All Kimai-specific settings (permissions, timesheet rules, currencies) |
| `config/packages/security.yaml` | Firewall, voter, SAML/LDAP auth config |
| `config/packages/doctrine.yaml` | DB connection, entity mappings, type registration |
| `config/services.yaml` | Service autowiring, tagged service collections |
| `.php-cs-fixer.dist.php` | PHP-CS-Fixer rules (PSR-12-based) |
| `phpstan.neon` / `tests/phpstan.neon` | PHPStan analysis config |
| `webpack.config.js` | Webpack Encore entry points |
| `.env.dist` | Template for `.env` — copy and configure `DATABASE_URL`, `APP_SECRET` |

### Authentication

Kimai supports multiple auth backends configured in `security.yaml`:
- Database (default)
- LDAP (`laminas/laminas-ldap` — optional dependency)
- SAML (`onelogin/php-saml`)
- Two-factor auth via `scheb/2fa-bundle` (TOTP + backup codes)

### API

The REST API uses FOSRestBundle with JMS Serializer. OpenAPI/Swagger docs are generated via NelmioApiDocBundle. API routes are prefixed with `/api/`. Authentication uses API tokens (passed as `X-AUTH-TOKEN` header).

### Frontend

Assets are built with Webpack Encore. The UI is based on **Tabler** (Bootstrap 5) via `kevinpapst/tabler-bundle`. Key JS libraries: FullCalendar 5, Chart.js 4, FontAwesome 6.

### Plugin System

Plugins live in `var/plugins/` under the `KimaiPlugin\` namespace. Each plugin is a Symfony bundle discovered automatically. Local Composer packages for plugins are stored as artifacts in `var/packages/`.

## Code Style

- PHP: PSR-12 with customizations defined in `.php-cs-fixer.dist.php`. Always run `composer codestyle-fix` before committing.
- JS: ESLint config in `eslint.config.mjs`.
- PHPStan is set to a strict level — check `phpstan.neon` for baseline and ignored patterns.
