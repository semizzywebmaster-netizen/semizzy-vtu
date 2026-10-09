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

## CI trigger marker

<!-- ci-trigger: 2026-10-05T09:00:00Z -->

## Local setup

1. Copy .env.example to .env.
2. Configure a dedicated non-production database and mail transport.
3. Run composer install.
4. Run npm install and npm run build.
5. Start the application and open the configured APP_URL. On a fresh database, the installation guard automatically redirects the browser to /setup.
6. Complete the web installer: server checks, APP_KEY, database migrations, and initial administrator.
7. Run checks with php artisan test, npm run typecheck, and npm run build.

Never point automated tests at production. Never commit .env, real provider credentials, production database dumps or private logs.

## Initial administrator bootstrap

The web installer at /setup is the normal production bootstrap path. It is automatically enforced on fresh or partially installed deployments.

For emergency/CLI-only provisioning, the first administrator can also be created from the terminal after configuring the environment and running migrations:

    php artisan semizzy:admin:create

The command is available only from the server CLI, is registered from `app/Console/Commands`, and refuses to create a second administrator. It prompts for the name, email and password, requires a strong confirmed password, marks the bootstrap account email-verified, and prevents creating a second initial administrator. Do not expose this command through a public web route.

## Production preflight

Before enabling live traffic, run the following from the application directory after configuring the production `.env`:

    php artisan migrate:status
    php artisan schedule:list
    # only for a fresh install with no administrator yet
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


## SEMIZZY ONE Addon Roadmap / Registry

Status is intentionally separated into **BUILT**, **IN PROGRESS / HARDENING**, and **NOT BUILT**. Addons remain modular and are activated through Core.

### BUILT
1. VTU & Digital Services — `vtu.digital-services`
2. CAC Business Services
3. SIM Hosting
4. Bulk SMS & Communication
5. Exams & Results
6. KYC & Identity Verification
7. Payments Gateway
8. Savings & Goals
9. Loans & Credit
10. Investments & Wealth
11. Marketplace & Commerce
12. P2P Transfers & Trading
13. Escrow Protection
14. Government Registration & Certificates
15. Rewards, Referrals & Promotions
16. Spin to Win / Rewards Game — `spin.to-win`
17. Mailer SMTP / SMTP Mailer — `mailer.smtp`

### IN PROGRESS / HARDENING
1. KYC & Identity Verification — billing/provider-flow hardening
2. P2P Transfers & Trading — security/financial hardening
3. Escrow Protection — reconciliation/financial hardening
4. SMM Services — foundation hardening
5. Social Media Accounts & Foreign Verification Numbers — foundation/rebuild
6. Government Registration & Certificates — payment/refund/provider hardening
7. Rewards, Referrals & Promotions — approval/reward hardening
8. Spin to Win / Rewards Game — production hardening after CI and database validation
9. Mailer SMTP / SMTP Mailer — production health/failover hardening after CI and cPanel SMTP verification
10. Education & Past Questions — School Past Questions and Exam Past Questions, imported exam-body/programme references, categorised school directory, admin autocomplete, private uploads/previews, and wallet-backed paid downloads (CI verification in progress)

### NOT BUILT
1. Travel & Tickets Booking
2. Gift Cards Marketplace
3. Communication & WhatsApp
4. Insurance & Protection
5. Business, Agent, Merchant & Reseller
6. VTU Website Builder
7. Virtual Cards
8. Data & Airtime Conversion
9. Banking & Financial Integrations
10. Digital Assets & Crypto
11. Community & Discussion
12. Group Savings / Contributions
13. Ads & Monetization
14. Help & Support Center
15. AI Assistant — second-to-last
16. Developer / API Provider — absolute last

Spin to Win and Mailer SMTP are addons, not Core hard-coded features. The Mailer SMTP addon supports an unbounded number of SMTP profiles; any UI pagination or operational limit must not impose a product maximum.
