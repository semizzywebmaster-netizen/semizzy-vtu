# SEMIZZY ONE Core V2 — implementation status

This document records committed foundations and the limits of what CI or repository inspection can prove. A green CI run is not a live-hosting or production-readiness certification.

## Implemented foundations in main

- Integrated Laravel 12, Inertia, React, TypeScript and Vite application; PHP 8.3+ and MySQL/MariaDB target.
- Authentication flows for registration, login, admin login, email verification, password reset and password change, with request IDs and security-event auditing.
- Four core roles: ADMIN, STAFF, SUPPORT, USER; permission middleware and permission-gated administration routes.
- Provider registry, encrypted credentials, endpoint/capability configuration, REST adapter, safe URL validation, provider request logs, verification/testing controls, service mappings and catalogue synchronization foundations.
- Catalogue and pricing-rule foundations, including provider-aware price quoting and a requirement for explicit selling-price rules before issuing quotes.
- Addon registry and lifecycle contracts for validation, dependencies, version checks, install/update/enable/disable/archive states, diagnostics and audit history. Uploaded arbitrary PHP is not executed.
- Finance schema and ledger service foundations with finance disabled by default.
- Personal API token management, token abilities, security-event logging/redaction, webhook signature/replay-protection foundations.
- Database-backed notifications, support tickets/replies/status changes and audit/notification integration.
- Admin system settings, user management, audit/security event views, and operational health checks.
- PWA manifest, static offline page, service worker that does not cache authenticated pages or financial/personal API data, and persistent mobile navigation.
- cPanel-oriented Apache rewrite rules, tracked runtime storage directories, .env.example, CI workflow and deployment documentation.

## Verification status

The latest GitHub Actions run observed during this repository audit is run 282, commit 9e400c0be86ba0115b79f6dfb9b0e28d73b67bfd, and completed successfully. That run verifies the repository's PHP lint, automated PHP feature tests, frontend TypeScript check, and Vite production build as configured in .github/workflows/ci.yml.

Repository/CI inspection does not verify:
- a fresh install on a real cPanel account;
- live SMTP, SMS/OTP, push delivery or external provider credentials;
- external provider API contracts or live failover behavior;
- production database backup/restore, monitoring, incident response or load testing;
- a production security audit or live-domain HTTPS/rewrite behavior.

Run the full workflow again after every change. Never point automated tests at production. Do not put .env, provider credentials, production dumps or private logs in Git.

## Core boundaries

- Core must not contain a license server, license keys, domain binding, activation licensing or anti-piracy subsystem.
- Business-specific services such as airtime/data, bill payments, SIM hosting, wallets/payments, savings/loans, marketplace and AI features are future installable addons, not silently hard-coded into Core.
- Provider records can be configured by administrators, but credentials, prices and capabilities must come from verified documentation and account access; repository presence alone does not mean a provider is live or ready to transact.
- Finance stays disabled by default until explicitly configured and reviewed. Unknown external transaction outcomes must not be treated as success or blindly retried.

## cPanel deployment checklist

1. Use PHP 8.3+ with the required Laravel extensions and Composer 2.
2. Configure the domain document root to the application's public/ directory whenever the host permits.
3. Configure a dedicated MySQL/MariaDB database and environment variables; keep .env outside public access.
4. Set a unique APP_KEY, APP_DEBUG=false, HTTPS APP_URL, secure session settings and an appropriate mail transport.
5. Install Composer/npm dependencies and build the frontend in a compatible build environment; deploy the resulting application and public/build assets.
6. Run migrations only after a verified backup and review; create the initial administrator with php artisan semizzy:admin:create.
7. Configure cPanel cron to run php artisan schedule:run every minute if scheduled tasks are used. Database queue jobs require a supported execution strategy; do not assume shared hosting runs a permanent queue worker.
8. Verify HTTPS, login, email delivery, permissions, storage writes, database/cache health, backups and error logging on the actual host before enabling real services.
