# API Provider Management Platform — Build Plan and Research Register

Status: planning and repository audit in progress. This document is not evidence that any provider is production-ready.

## Decision and scope

Build the API Provider Management Platform first. Build the separate Developer API Platform afterward.

The provider platform manages SEMIZZY ONE's outbound integrations with third-party companies whose APIs supply services consumed by SEMIZZY ONE. It is not the public API portal for developers consuming SEMIZZY ONE; that is the later Developer API Platform.

## Repository audit: verified starting points

Inspected `main` on 2026-10-09.

- `docs/PROVIDER-ENGINE.md` documents a Core universal provider engine and explicit verification states.
- Core models already include `ApiProvider`, `ProviderServiceMapping`, `ProviderServiceProduct`, `ProviderConnection`, `ProviderEndpoint`, `ProviderCredential`, `ProviderHealthCheck`, `ProviderRoutingRule`, `ProviderOperationLog`, `ProviderRequestLog`, `ProviderSync`, and related records.
- Core services already include `ProviderManager`, `ProviderPresetRegistry`, `ProviderCapabilityRegistry`, `RestJsonProviderAdapter`, `ProviderTestService`, `ProviderUrlGuard`, and provider routing/idempotency services.
- The Core adapter supports configurable endpoints, request mappings and authentication, but each operation must be checked against the provider's actual API contract; generic REST support is not proof of a correct provider-specific integration.
- Existing service-specific integration paths exist. For example, `vtu.digital-services` declares the Core ProviderManager; `payments.gateway` has a dedicated payment gateway manager and provider migrations; KYC declares Core ProviderManager. These must be reconciled before adding overlapping provider systems.
- Existing addon directories include VTU/digital services, payments/funding, banking/financial integrations, KYC, education/exams, messaging, travel, gift cards, virtual cards, insurance, investments, loans, crypto payments, CAC/business services, and other service families.
- `docs/BLUEPRINT-TRACEABILITY.md` says provider credentials, API contracts and operational test evidence are not automatically certified by the existence of code or manifests.

## Architecture rules

1. Extend and reuse the Core provider registry, credentials, endpoint, mapping, catalogue, routing, idempotency, health and audit services. Do not create a second generic provider registry.
2. Implement provider-specific adapters only where generic configuration cannot accurately express the official contract or where security/signature/status behaviour requires dedicated code.
3. Admin setup should be minimal: import safe provider metadata, endpoint definitions, auth-field schema, service mappings, documented service/product IDs and catalogue-sync rules. Admin enters only the required secret/token when the provider genuinely supports token-only configuration. Never assume every vendor uses one token.
4. Credentials must remain encrypted and masked; logs must redact secrets and personal data. Use HTTPS, URL/SSRF protections, request timeouts, allow-listed redirect behaviour, signature validation, idempotency, status requery and duplicate-fulfilment safeguards as supported.
5. Imported prices and service IDs must carry source, currency, sync timestamp and provider/product identity. Dynamic prices must be refreshed from official catalogue endpoints where available. Do not invent product IDs or prices when a provider does not publish them.
6. No provider may be enabled for production routing merely because a preset exists. Keep draft, pending verification, configured, sandbox tested, production verification pending, live verified, unhealthy, disabled and archived states distinct.
7. The ten-provider target applies per individual API-dependent service, not per addon or broad category. Count only distinct, suitable integrations; a provider that supplies airtime does not automatically count for electricity, KYC, flights or card issuing. Do not count aliases, duplicate reseller brands under one backend, generic placeholders or unimplemented presets as verified providers.
8. If the market does not offer ten genuinely suitable independent providers for a service, document the shortage, preserve the target as a gap, and do not falsely mark that service fully covered.
9. Maintain cPanel/shared-hosting compatibility: Laravel/PHP, MySQL/MariaDB, database-backed queue patterns and cron where needed. Do not require Docker, Redis, RabbitMQ, Supervisor, systemd, root access or a permanent worker.

## Build phases

### Phase 1 — Complete repository inventory
Create a service-by-service register of every existing and planned service, current provider models/adapters, provider-specific managers, mappings, product catalogue imports, prices, status lookup, webhooks, refunds/reversals, tests, and current evidence. Identify overlap and missing operations before coding.

### Phase 2 — Official provider research
For each service, research official provider documentation and current published pricing. Compare:
- service/region/product coverage;
- setup, account approval, commercial contract and KYC requirements;
- fees, reseller rates, minimum funding and FX costs;
- authentication, IP allowlisting, signatures and credential rotation;
- product/variation IDs and catalogue/price endpoints;
- latency/SLA evidence, status lookup, webhook reliability, idempotency and reconciliation;
- sandbox access, support channels and production onboarding.
Record source URLs, access date, facts, unknowns and confidence. Community posts and aggregator lists may identify leads, but official documentation and direct provider confirmation govern integration decisions.

### Phase 3 — Provider administration
Build/complete the admin centre for directory search, service coverage, provider detail, secure credentials, endpoint/auth configuration, connection testing, service/product mappings, catalogue/price sync, routing priorities, enable/pause/disable, health, logs, sync history, audit history, and controlled verification status transitions.

### Phase 4 — Integration and coverage, service by service
Reuse existing verified adapters first. Implement and test the highest-value Nigerian services first: airtime, data, electricity, cable TV, education/exam products, payments/virtual accounts, transfers/payouts, identity verification, SMS/OTP, and then other existing/planned addon service families. Exact ordering may change after the repository inventory and provider research.

### Phase 5 — Verification
Unit and integration tests for auth variants, redaction, URL safety, catalogue parsing, mapping, stale prices, retries, idempotency, timeout/unknown outcomes, status requery, webhook signatures/replay, duplicate events, safe failover, provider disable/pause, audit events, and missing credentials. Use mocks/fixtures in CI and authorised sandbox credentials for real sandbox checks. Never claim live verification without authorised evidence.

### Phase 6 — Release gate
Report coverage by service, with separate counts for discovered candidates, documentation reviewed, configured, sandbox-tested and live-verified providers. Release only the service/provider combinations that meet their verification gates; leave gaps visible.

## Initial official-documentation research leads (not approved integrations)

These are starting leads only. Current pricing, terms, credentials, account eligibility and production support must be checked again before implementation.

### Nigerian airtime/data/bills
- VTU.ng API v2: https://vtu.ng/api/
  - Published API documentation describes airtime, data, electricity, cable TV, product variations, customer verification, balance and order status. It documents reseller-account requirements and JWT acquisition using account username/password; therefore setup may require more than a single static token.
- VTU.ng reseller/API pricing: https://vtu.ng/pricing/
  - Published rates include service-specific airtime discounts; dynamic data prices should come from its current variations endpoint rather than a hard-coded price.
- VTU.ng legacy API notice: https://vtu.ng/legacy-api-v1/
  - Explicitly identifies API v1 as discontinued and recommends v2. Do not implement the legacy contract.
- VTU.com.ng API documentation: https://vtu.com.ng/api-document
  - Published documentation describes a dashboard API key and data product codes. Independently verify official endpoint details, product coverage, fees and production status before counting it.
- VTU.com.ng API information: https://www.vtu.com.ng/API.php
  - Public page indicates some documentation access may require a one-time payment. Treat full contract and pricing as pending until obtained from the vendor.

### Payments, virtual accounts and transfers
- Flutterwave NGN virtual accounts: https://developer.flutterwave.com/docs/ngn-virtual-accounts
  - Documentation distinguishes dynamic and static virtual accounts; static issuance may require customer NIN/BVN, and feature access depends on merchant eligibility.
- Flutterwave Nigeria transfers: https://developer.flutterwave.com/docs/nigerian-bank-account-transfer
  - Documentation states KYC/approval, IP allowlisting, transfer enablement and sufficient wallet funds are prerequisites.
- Paystack dedicated virtual accounts: https://paystack.com/docs/payments/dedicated-virtual-accounts/
  - Documentation states availability is limited to registered businesses in Nigeria/Ghana that have completed go-live and may require customer validation.
- These examples show why provider setup must model account approvals and multiple credentials rather than promising universal token-only activation.

### SMS/OTP
- BulkSMSNigeria API: https://www.bulksmsnigeria.com/api
  - Published documentation provides a production API, a sandbox API and API-token onboarding.
- Termii developer documentation: https://developer.termii.com/
  - Documents messaging and verification products; the provider-specific account base URL is supplied in its dashboard.
- Compare delivered-message pricing, sender-ID approvals, DND handling, delivery reports, OTP support and measured Nigerian route performance before selecting a primary/fallback.

### Travel
- Travelstart API documentation: https://docs.travelstart.com/api/
  - Documents flight search/booking and a SOAP 1.2 session-based API; credentials and market-specific production certification are required. This is a different integration pattern from a simple bearer-token REST API.

## Provider coverage register template

One row per service/provider pair, with at least:
- canonical provider identity and legal/brand identity;
- official docs, pricing and terms URLs, last checked date;
- service key, operations and country/network/product coverage;
- auth schema and extra requirements (IP allowlist, signature, OAuth, username/password, account approval, contract);
- endpoint definitions and request/response mappings;
- provider service IDs/product IDs, price source, currency, last catalogue sync;
- capabilities individually verified (balance, validation, initiation, status, webhook, refund, reversal, reconciliation);
- sandbox/live environment and credential status;
- measured latency/error-rate evidence and health window;
- provider relationship/underlying backend, to avoid counting duplicate resellers as independent coverage;
- state, evidence links, test fixtures, reviewer and audit history;
- explicit gaps and blockers.

## Acceptance criteria

- Every existing and planned service has a coverage row, including services marked not applicable with a reason.
- Existing providers/adapters are inventoried before any duplicate is introduced.
- Every service shows separate counts for candidate, documented, configured, sandbox-tested and live-verified providers.
- Product IDs and prices are sourced from provider documentation/catalogue data, never guessed.
- Admin can add future providers without a hard-coded provider limit.
- A provider preset cannot bypass verification, credential masking, permission checks, audit logging or safe routing.
- Tests and current CI results are attached to each implementation phase.
- The Developer API Platform is started only after the outbound provider coverage and Core service contract have been reviewed; it must expose approved SEMIZZY ONE services, not vendor credentials or raw provider endpoints.


## Admin usability and service-addon integration (required)

The platform must be easy for a non-developer administrator to operate. Keep one Core provider engine and registry, but expose provider management contextually inside the relevant service addon.

### Admin experience
- Each service addon gets a clearly named **API Providers** section/tab, for example VTU → Airtime Providers, Electricity → Electricity Providers, Education → Exam/Pin Providers, Messaging → SMS/OTP Providers, Payments → Collection/Virtual Accounts/Transfers, and KYC → Identity Verification.
- Each contextual list shows only providers with a declared, researched capability for that exact service/operation. Clearly label unsupported or pending capabilities; never imply that a provider supports every service in its broad category.
- Provide simple actions: **Add Provider**, **Connect**, **Test Connection**, **Sync Services & Prices**, **Enable**, **Pause**, **Disable**, and **View Diagnostics**. Use guided setup, plain-language errors and a visible setup checklist.
- Show provider cards/rows with provider name, supported services/products, connection state, verification state, last successful test, last catalogue sync, price source/time, latency/health where measured, and a clear next action.
- Offer a global **API Providers** centre for cross-service search, provider health, coverage gaps and credentials/operations administration. This is an alternative view of the same registry, not a separate storage system.
- When adding a provider from a service addon, preselect that service and only show compatible provider presets. Admin may still discover/import a provider for additional services later through an explicit capability assignment flow.
- Avoid ambiguous labels such as simply “Provider API”. Use the actual service and operation names in navigation, filters and setup instructions.

### Shared engine and addon contract
- Core owns provider identity, encrypted credentials, endpoints, mappings, catalogue products/prices, health, routing, operation logs, verification states and audit trail.
- Addons declare their service/operation capability keys and consume the Core provider engine via stable contracts. An addon must not create its own duplicate provider registry, credential store, health system or routing engine.
- Provider configuration is shared by canonical provider identity. If one vendor serves multiple services, it can be connected once where credentials are shared, while service-specific mappings, capability checks and verification evidence remain separate. If vendor credentials differ by product/account/environment, store separate named connections securely.
- A provider added from one addon must appear in other applicable addon views only after its relevant capability has been assigned and reviewed. Do not automatically expose unrelated capabilities.
- Provider lifecycle and service-addon installation/activation must be handled safely: an addon may hide its contextual navigation when inactive, but must not delete shared provider credentials or break other addons that use the same provider.

### Service-specific configuration and imported data
- Import official service identifiers, product/variation IDs, supported operators/networks/billers, denominations, prices and catalogue sync rules when the provider documents an API or provides a trusted import format.
- Store mappings per provider + service + product/operation; do not use a single generic ID field where the vendor distinguishes products, billers, networks, denominations, meter types or account operations.
- Every imported value records source provider, external ID, currency, source price, import/sync timestamp and environment. Preserve a history of changes where price changes affect transaction quoting.
- Clearly distinguish provider cost, SEMIZZY ONE selling price and profit rule. Use existing Core pricing precedence; never overwrite admin selling prices silently when provider costs sync.
- If a provider lacks a catalogue endpoint, permit a documented admin import or manual mapping with validation and audit history; never invent service IDs or prices.
- Keep secrets encrypted and masked; the UI must never show raw tokens after saving. The token is the only required input only when official provider authentication genuinely supports that setup. Clearly list additional required credentials and onboarding actions when necessary.

### Usability acceptance checks
- An admin can navigate from a service to its relevant providers in no more than two clicks.
- The provider list is prefiltered to the selected service and operation.
- A new connection has a guided checklist for required credentials, account approvals, IP allowlisting, sandbox test and production verification.
- Failed tests explain the likely issue without exposing tokens, private keys, customer data or full sensitive payloads.
- Sync history shows what was added/updated/removed and requires safe handling of products no longer returned by a provider.
- Service addon pages and the global provider centre display consistent state because both read/write the same Core records.


## Provider-by-provider category catalogue and price import (required)

Every provider's own management page must expose a clear **Services & Prices** workflow, not merely a generic global sync button. From that provider, an admin must be able to:

1. **Fetch/refresh provider catalogue** using that provider's documented catalogue, products, services, plans, billers or pricing endpoint. Show when live discovery is unsupported and provide a validated documented manual/CSV import route where appropriate.
2. **Filter by service category and subcategory** (for example Airtime, Data, Electricity, Cable TV, Exam PINs, SMS, OTP, Payment Collection, Bank Transfer, KYC, Flights, Gift Cards). Category options must come from reviewed capabilities and discovered records, not a hard-coded claim that every provider supports every category.
3. **Preview before import/update** with provider name, category, external service/product ID, provider product name, currency, provider/source price, current stored price, proposed price, availability/status, source and last-sync timestamp. Clearly flag new, changed, unavailable, removed, duplicate and unmapped items.
4. **Select one category or selected products** to import/update, with explicit counts and confirmation. Also support a deliberate all-supported-categories action where safe. Never silently publish imported products or enable routing just because they were discovered.
5. **Update prices and services safely**: persist source prices separately from SEMIZZY ONE selling prices and profit rules; keep price-change history; show price increases/decreases; do not overwrite admin selling prices, product mappings, or customer-facing availability without the appropriate policy/approval.
6. **Control auto-sync per provider/category/import scope**. Manual sync remains available. Auto-sync may update source catalogue and source cost only when explicitly approved; disable destructive removal on incomplete/paginated catalogues. Mark unavailable items and require evidence before treating an item as permanently removed.
7. **Show a sync report**: discovered, new, changed, price-changed, unchanged, unavailable/removed, failed and pending-approval counts, with per-item errors and a timestamp. Record an audit event and sync history.
8. **Keep provider and category context throughout the addon UI**. For example, VTU > Airtime Providers > [provider] > Services & Prices > Airtime; Electricity and Data have their own filtered views. Payments, messaging, KYC and other addons follow the same pattern. These are views over the shared Core provider/service catalogue, not duplicate registries.
9. **Use the actual provider contract** for auth, endpoint paths, pagination, rate limits, response parsing and currency. Do not assume every API has a catalogue/price endpoint or that a connection test proves catalogue sync works. Never fabricate prices, IDs or provider results.
10. **Protect production data** with authorization, CSRF/throttling, safe URL checks, timeouts, secret redaction, idempotent syncs and tests for pagination, duplicate records, partial failure, stale prices, provider errors and incomplete discovery.

The existing Core engine already exposes provider discovery/sync and import-preview/approval/import routes. Before implementing a new UI or endpoint, audit and reuse those handlers; verify their category filtering, price-update semantics and product-history/audit behaviour. Do not duplicate existing sync/import logic without a confirmed gap.

### Acceptance criteria for this workflow

- An admin can open a provider from the relevant addon, choose a service category, preview the provider's current catalogue and source prices, select items, and import/update them.
- A repeat sync updates source cost only and preserves the configured selling price/profit policy.
- A partial or failed catalogue response never marks unrelated services as removed or silently disables live products.
- Each result identifies the provider, category, external ID, currency, source price and sync time.
- Unsupported catalogue APIs are shown honestly with the documented alternative, not a fake “sync successful” state.
