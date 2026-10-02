# SEMIZZY ONE CORE V2

**SEMIZZY WEBMASTER** — *We Design. We Develop. We Deliver.*

A clean, integrated Laravel + React/TypeScript application designed for MySQL/MariaDB and cPanel/shared hosting. Core priorities are a database-driven universal API provider engine, safe addon lifecycle management, audited finance foundations, role-based access control, and a responsive PWA.

## Architecture commitments
- One integrated Laravel application using Inertia.js, React, TypeScript and Vite; no separate frontend/backend deployment.
- MySQL/MariaDB as the primary database.
- Database-backed queues and Laravel Scheduler via cPanel cron; no Docker, Redis, RabbitMQ, Supervisor, systemd, root access, or mandatory long-running workers.
- Universal provider registry and adapter/capability contracts are Core capabilities, not addons.
- Addons are trusted, versioned packages with validated lifecycle hooks; uploaded arbitrary PHP is never executed.
- Financial operations are feature-gated, idempotent, audited, and ledger-backed. Unknown provider outcomes remain pending until reconciled.
- No license keys, license server, domain binding, activation licensing, or anti-piracy subsystem.

## Repository state and execution honesty
This repository was confirmed empty when the rebuild began. An empty repository is not evidence of an installed Laravel application, a successful build, or a deployment. Build/test/deployment status must only be reported after the relevant commands or live checks actually run in an available environment.

## Local development prerequisites
- PHP 8.3+ (use the Laravel version selected in composer.json) and required PHP extensions.
- Composer 2, Node.js LTS/npm, and MySQL/MariaDB.
- Copy .env.example to .env, configure a dedicated non-production database, then run composer install, php artisan key:generate, php artisan migrate, npm install, and npm run build.
- Run automated checks with php artisan test and npm run typecheck when the relevant project files are present.
- Never point automated tests at production. Do not place .env, provider credentials, production dumps, or private logs in Git.

## PWA and offline behavior\n- The app provides an installable web manifest and an SVG app icon.\n- The service worker caches only the manifest, icon, and a static offline notice; it does not cache authenticated HTML, API responses, balances, provider data, or personal information.\n- Offline mode is an informational fallback, not an offline transaction mode. All live account and service actions require a network connection.\n\n## Initial administrator bootstrap\nAfter installing dependencies, configuring `.env`, and running `php artisan migrate --force`, create the first administrator from the cPanel terminal:\n\n    php artisan semizzy:admin:create\n\nThe command prompts for the name, email, and password (password input is hidden), requires a strong confirmed password, marks the account email-verified for bootstrap, and records a one-time database marker. It refuses to create a second initial administrator. Do not expose this command through a public web route.\n\n## cPanel deployment
Deploy the application outside public_html where possible and point the domain document root at the application's public/ directory. If the host cannot change document root, follow a carefully reviewed cPanel arrangement that keeps all application source, .env, vendor metadata, and storage outside public access. Require HTTPS and set APP_DEBUG=false.

Use the real hosting paths in cron; example only:

    * * * * * cd /home/ACCOUNT/APP && php artisan schedule:run >> /dev/null 2>&1

Replace ACCOUNT and APP with the actual cPanel account and application path. Confirm the correct PHP CLI binary with the host.

## Provider directory
Initial onboarding candidates include OTOBILL, Ufriends IT, Husmodataapi, VTpass, IACafe, Bigisub, Subpadi, CheapDataHub, VTU.ng, Blessdata, Fonpay, Ogdams SimHosting, Sim Hoster, 2FAST, VTUCreator, Reloadly, Interswitch, Paystack, Flutterwave, Dojah, Prembly and Termii. Directory presence is not proof of API support or a live integration. Official documentation, credentials, sandbox checks and authorised production verification are required before status can become Live Verified.

## Production safety
Never send real-money transactions during tests without explicit authorisation. Back up production before any migration. Do not automatically switch providers after a timeout until the original transaction has been queried or otherwise reconciled.