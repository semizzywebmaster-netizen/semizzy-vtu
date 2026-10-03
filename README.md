# SEMIZZY ONE CORE V2

**SEMIZZY WEBMASTER** — *We Design. We Develop. We Deliver.*

SEMIZZY ONE CORE V2 is a single integrated Laravel + React/TypeScript application designed for MySQL/MariaDB and cPanel/shared hosting. Core provides the platform foundation: authentication, permissions, provider registry, catalogue/pricing foundations, addon lifecycle contracts, security/audit, support, notifications, finance foundations and PWA shell.

## Architecture commitments

- One integrated Laravel application using Inertia.js, React, TypeScript and Vite; no separately deployed frontend/backend.
- MySQL/MariaDB as the primary database.
- Database-backed queues and Laravel Scheduler via cPanel cron; no Docker, Kubernetes, Redis, RabbitMQ, Supervisor, systemd, root access or mandatory long-running workers.
- Universal provider registry and adapter/capability contracts are Core capabilities, not addons. Provider integrations are not considered live until their real documentation, credentials, test results and operational status are verified.
- Addons are trusted, versioned packages with validated lifecycle contracts; uploaded arbitrary PHP is never executed.
- Financial operations are feature-gated, idempotent, audited and ledger-backed. Unknown provider outcomes must remain unresolved until safely reconciled.
- No license keys, license server, domain binding, activation licensing or anti-piracy subsystem.
- Business functionality such as VTU, bill payments, SIM hosting, wallets, savings/loans, marketplace and AI remains for separately installed addons.

## Requirements

- PHP 8.4+ with Laravel-required extensions
- Composer 2
- Node.js 22/npm for frontend build
- MySQL or MariaDB for deployment (SQLite is used by CI tests)

## Local setup

1. Copy .env.example to .env.
2. Configure a dedicated non-production database and mail transport.
3. Run composer install, php artisan key:generate, and php artisan migrate.
4. Run npm install and npm run build.
5. Run checks with php artisan test, npm run typecheck, and npm run build.

Never point automated tests at production. Never commit .env, real provider credentials, production database dumps or private logs.

## Initial administrator bootstrap

After configuring the environment and running migrations, create the first administrator from the terminal:

    php artisan semizzy:admin:create

The command is available only from the server CLI, is registered from `app/Console/Commands`, and refuses to create a second administrator. It prompts for the name, email and password, requires a strong confirmed password, marks the bootstrap account email-verified, and prevents creating a second initial administrator. Do not expose this command through a public web route.

## Production preflight

Before enabling live traffic, run the following from the application directory after configuring the production `.env`:

    php artisan migrate:status
    php artisan schedule:list
    php artisan semizzy:admin:create

Run the administrator command only once and only when no administrator exists. Do not place its invocation in a cron job or public route.

CI also validates PHP syntax, application tests, frontend type checking and the production frontend build. A green CI run is necessary but does not replace a real cPanel smoke test.

## cPanel deployment

- Prefer setting the domain document root to the application's public/ directory. The tracked public/.htaccess provides Apache rewrite rules for Laravel routes.
- Keep application source, .env, Composer metadata and storage outside public web access wherever possible.
- Set APP_DEBUG=false, a unique APP_KEY, HTTPS APP_URL, secure session settings, database credentials and a real mail transport.
- Build frontend assets in a compatible environment and deploy public/build with the application.
- Run migrations only after reviewing the migration plan and taking a verified backup.
- Configure cPanel cron to run php artisan schedule:run every minute if scheduled tasks are used.
- Do not assume shared hosting supports a permanent queue worker. Select a queue execution strategy supported by the actual host and test it before relying on queued work.
- Verify login, email delivery, role permissions, writable storage, database/cache health, backups, HTTPS and error logging on the real host before enabling live services.

## Verification and release status

GitHub Actions runs PHP lint and feature tests plus frontend TypeScript checking and Vite builds. Passing CI proves only those configured checks. It does not prove a real cPanel deployment, live email/SMS delivery, external provider integration, restore-tested backups, load capacity or production security readiness. Consult docs/IMPLEMENTATION-STATUS.md for the current verified scope and limitations.
