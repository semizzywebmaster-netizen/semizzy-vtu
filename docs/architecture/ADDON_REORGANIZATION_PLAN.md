# Addon Category Reorganization Plan

Status: Phase 1 — inventory and migration plan. No addon directories are moved by this change.

## Goals

- Keep every addon under `addons/<category>/<addon-id>/`.
- Keep Core application code outside `addons/` and avoid addon-specific logic in Core.
- Keep each addon independently versioned, testable, activatable, and releasable.
- Do not create a universal long-lived addon feature branch. Use one short-lived branch per addon/task.
- Preserve addon identifiers, manifest IDs, database tables, migration history, permissions, routes, provider configuration, and installed-site compatibility.

## Proposed categories

| Category | Directory | Existing addon IDs to assess |
|---|---|---|
| Education | `addons/education/` | `education`, `exams.results`; future school admission, school past questions, and exam past questions must be separate addons |
| Digital services | `addons/digital-services/` | `vtu.digital-services`, `smm.services`, `sim-hosting` |
| Payments | `addons/payments/` | `payments.gateway`, `banking.financial-integrations`, `crypto-payments.gateway`, `virtual-cards`, `p2p.transfers` |
| Finance | `addons/finance/` | `investments.wealth`, `savings.goals`, `loans.credit`, `escrow.protection` |
| Communication | `addons/communication/` | `bulk-sms.communication`, `communication.whatsapp`, `whatsapp-bot`, `mailer-smtp` |
| Commerce | `addons/commerce/` | `marketplace.commerce`, `gift-cards`, `business-agent-merchant-reseller` |
| Identity and compliance | `addons/identity-compliance/` | `kyc.identity-verification`, `social.accounts-verification` |
| Business services | `addons/business-services/` | `cac.business-services`, `government-registration-certificates` |
| Engagement | `addons/engagement/` | `rewards-referrals-promotions`, `spin-to-win` |
| Travel | `addons/travel/` | `travel-tickets` |
| Hosting and site tools | `addons/site-tools/` | `vtu-website-builder` |
| Review required | To be decided after manifest and code inspection | Any addon whose real responsibility or dependencies do not match the provisional category |

These mappings are provisional until each addon's manifest, routes, migrations, provider integrations, and references have been inspected. An addon must not be categorized solely by its folder name.

## Required addon contract

Each addon must document and validate:

- Stable, globally unique addon identifier (do not change it merely because its folder moves).
- Manifest schema/version and supported Core version range.
- Required and optional addon dependencies; reject circular dependencies.
- Permissions, routes, events, jobs, configuration, migrations, and public extension points.
- Install, activate, deactivate, upgrade, rollback, and uninstall behavior.
- Data-retention policy; uninstall must not silently delete business or financial records.
- Automated tests, security checks, and a cPanel/shared-hosting-compatible deployment path.

## Safe migration sequence

1. Inventory the full repository tree and every addon manifest; identify duplicate IDs, missing manifests, shared code, hard-coded paths, and cross-addon imports.
2. Build a machine-readable catalog mapping stable addon IDs to current paths, proposed categories, dependencies, permissions, migrations, and status.
3. Add compatibility tests for addon discovery, installation, activation, navigation, permissions, and migrations before moving any directories.
4. Implement category-aware discovery while retaining temporary legacy-path compatibility. Do not rename addon IDs or database tables.
5. Move one low-risk addon at a time with Git history preserved where possible; update all path references and CI/build packaging rules.
6. Run unit/integration tests and a production build after each migration batch. Verify the existing `vtu.digital-services` addon remains discoverable and its manifest/migration history remains unchanged.
7. Remove legacy path support only after all addons are migrated and tests prove no legacy references remain.

## Branch and review policy

- Core work: short-lived `feat/core-<task>` or `fix/core-<task>` branches.
- Addon work: short-lived `feat/<category>-<addon-id>` or `fix/<category>-<addon-id>` branches.
- Repository-wide organization work: short-lived `chore/addon-category-reorganization` branch.
- Merge through pull requests after checks pass. Do not push unrelated addon work onto a shared permanent feature branch.

## Acceptance criteria

- Every addon is catalogued exactly once and has a reviewed category.
- No duplicate addon IDs or unresolved dependency cycles.
- Core remains usable with optional addons disabled.
- Addon install/activate/deactivate and upgrades are covered by tests.
- Existing database data and migration history are preserved.
- The current VTU addon continues to load and pass its relevant tests.
- CI validates manifests, category paths, dependencies, permissions, migrations, and build output.
- Deployment instructions remain compatible with Laravel, MySQL/MariaDB, and cPanel/shared hosting; no Docker, Redis, RabbitMQ, Supervisor, systemd, or root-only requirement is introduced.
