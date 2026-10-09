# SEMIZZY ONE Admin Operations & Usability Roadmap

This document is the acceptance checklist for the professional admin-operations work. Existing pages/services must be extended rather than duplicated. A feature is complete only after its backend, authorization, UI, automated tests and CI evidence agree.

## Six core usability improvements

### 1. Unified admin command centre
- [ ] One landing view for provider health, pending/uncertain transactions, failed operations, sync errors, approval queues, active addons, security alerts and actionable revenue/profit summaries.
- [ ] Every alert links to the exact permitted record/action; no fabricated or placeholder counts.
- [ ] Use live database/provider evidence and show the timestamp/source of each metric.
- [ ] Role and permission filtering must be applied server-side.

### 2. Guided provider setup
- [x] Existing Core provider wizard and shared provider registry are present.
- [ ] Guided steps: connection/environment, required auth fields, safe credential storage, connection test, operation-level verification, catalogue discovery, mapping, source-cost sync and live-readiness checklist.
- [ ] Distinguish HTTP connectivity from a verified operation; show missing IP allowlisting, account approval, signatures or required credentials.
- [ ] Do not expose saved secrets or claim presets are working integrations.

### 3. Product catalogue and tier pricing
- [x] Existing catalogue import, provider-product mapping, PriceRule and PriceEngine foundations are present.
- [x] Provide the explicit **Save as Draft** and **Add to My Services** lifecycle with server-side readiness checks.
- [x] Show the selected provider source cost separately from PriceEngine selling prices and expected gross profit for USER, AGENT, RESELLER and MERCHANT in the publication readiness preview.
- [x] Validate every required tier through the production PriceEngine before publication; fail closed if a tier cannot be priced or the result is below provider cost.
- [ ] Bulk edits need preview, validation, confirmation and audit records.
- [ ] Catalogue sync must never silently publish, enable routing or overwrite selling-price rules.

### 4. Transaction recovery centre
- [ ] One operational view that connects the customer transaction, immutable wallet ledger movement, provider reference, status/requery history, refund/reversal evidence and reconciliation.
- [ ] A timeout must not be interpreted as provider failure.
- [ ] Requery must use the original reference; purchase retries and refunds must be idempotent.
- [ ] Refund completion requires the provider-confirmed state where supported and a reconciled ledger entry.
- [ ] Keep financial mutations permission-gated, auditable and subject to the existing maker-checker/approval requirements.

### 5. Organized settings
- [x] Existing System Settings, SMTP profiles, platform controls, backup/restore and cache-clear actions are present.
- [ ] Group settings into General, Branding, Providers, Pricing, Payments, Notifications, Security, Addons, Backups and Maintenance.
- [ ] Explain each setting, validate before save, show a preview where relevant and provide safe test actions.
- [ ] Secret values remain masked; all critical changes are audited.
- [ ] Backups must be restorable and verified; do not claim backup health merely because a ZIP exists.

### 6. Mobile-first administration and customer experience
- [x] Existing admin navigation includes a mobile drawer.
- [ ] Ensure all essential forms, filters, tables, approval flows and provider setup steps work on small screens.
- [ ] Use responsive tables/cards, readable financial amounts, clear empty/loading/error states and accessible controls.
- [ ] No essential admin operation should require desktop-only hover behavior.

## Additional requested capabilities

### Global search
- [x] Add a responsive, permission-aware search entry in the admin top bar for users, API providers, services, products, support tickets and active addons.
- [x] Add VTU transaction references and audit request IDs to global search, linking transaction results to a filtered recovery screen. Provider operation logs and other addon-specific records remain future additions.
- [ ] Keep queries bounded, escape SQL LIKE wildcards, return minimal fields and use no-store responses.
- [ ] Never expose KYC documents, identity numbers, credentials, OTPs or full provider payloads in search results.

### Safe rollout controls
- [x] Add an audited per-addon enabled/percentage control with stable per-user assignment and administrator recovery access.
- [ ] Add rollout history, preview cohort counts, scheduled windows and an explicit rollback action.
- [ ] Connect rollout eligibility to the relevant addon entry points and any future customer-facing publication gates; rollout controls must not bypass permissions, provider verification, pricing readiness or financial state checks.
- [ ] API-token rollout must use the authenticated API account identity, not an IP guess; verify this before applying percentage rollouts to API routes.

### Operational runbooks
- [x] Add searchable in-admin runbooks for provider outages, pending transactions, refunds, catalogue price changes, notification delivery and backup/restore.
- [ ] Add links from actual health alerts/errors to the relevant runbook.
- [ ] Review runbooks whenever a transaction state machine, provider contract or deployment procedure changes.

### Feature-aware navigation
- [x] Keep addon navigation sourced from active addon manifests and filter by the current user's permission.
- [x] Hide VTU addon navigation when the platform VTU control is disabled.
- [ ] Verify every navigation URL resolves, disabled/uninstalled addons cannot leave dead links, and backend routes independently enforce the same lifecycle/permission policy.
- [ ] Use the same Core provider records from global and contextual addon navigation; do not create per-addon provider registries.

## Operational standards applying to every phase
- No hard-coded fake providers, provider prices, health data or test success.
- No provider is production-ready merely because it exists as a preset or responds to an HTTP request.
- Use Laravel + Inertia React/TypeScript + MySQL/MariaDB and cPanel-compatible operation; do not introduce Docker, Redis, RabbitMQ, Supervisor, systemd, root-only deployment or permanent-worker requirements.
- Preserve immutable wallet ledger history; never silently delete transactions or rewrite balances.
- Add tests for authorization denial, partial failure, idempotency, rollback, stale source data and safe recovery.
- Keep the release gate OPEN until real production-engine wallet/P2P concurrency tests and provider-confirmed refund/reconciliation tests have passed. Admin usability work is not a substitute for those tests.

## Evidence and status discipline
- Implemented means code exists.
- Verified means relevant automated tests/CI passed and the result was inspected.
- Production-ready additionally requires deployment-specific verification, real provider/account prerequisites and operational acceptance.
- Document known gaps and exact CI status with every implementation phase.
