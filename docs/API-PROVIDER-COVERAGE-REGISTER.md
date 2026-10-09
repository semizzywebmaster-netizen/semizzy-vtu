# API Provider Coverage Register

Audit date: 2026-10-09
Branch: main
Status: initial static inventory of Core presets only. Counts below are **preset mappings**, not working integrations or verified market coverage.

## Existing Core provider presets

The Core `ProviderPresetRegistry` currently defines 13 provider records:

| Provider identifier | Display name | Service families currently mapped in preset | Current status meaning |
|---|---|---|---|
| `vtpass` | VTpass | Airtime, data, electricity, cable TV, education, utility bills | Preset only; not proof of verified adapter |
| `reloadly` | Reloadly | Airtime, data | Preset only; not proof of verified adapter |
| `vtung` | VTU.ng | Airtime, data, electricity, cable TV | Preset only; official v2 docs must be matched to each configured operation |
| `iacafe` | IA-Café | Airtime, data, electricity, cable TV | Preset only; verify current docs, endpoint paths and response mapping |
| `clubkonnect` | ClubKonnect | Airtime, data, electricity, cable TV, education | Preset only; no explicit endpoint paths in current preset |
| `rapidbills` | RapidBills | Airtime, data, electricity, cable TV | Preset only; no explicit endpoint paths in current preset |
| `smeapi` | SME API | Airtime, data, electricity, cable TV, education | Preset only; no explicit endpoint paths in current preset |
| `bigisub` | Bigisub | Airtime, data, electricity, cable TV, education, SMS | Preset only; verify current docs and operation mappings |
| `husmodataapi` | Husmodataapi | Airtime, data, electricity, cable TV, education, SMS | Preset only; no explicit endpoint paths in current preset |
| `monnify` | Monnify | Payment collection, bank transfers, account verification, utility bills | Preset only; do not assume each capability is available under the same account/product |
| `paystack` | Paystack | Payment collection, bank transfers, account verification | Preset only; verify product eligibility and API semantics separately |
| `flutterwave` | Flutterwave | Payment collection, bank transfers, account verification, utility bills | Preset only; verify product eligibility, endpoint correctness and each operation |
| `termii` | Termii | SMS, OTP | Preset only; OTP mapping requires actual OTP operation support, not merely SMS send |

## Existing preset mapping count by service

These are distinct provider identifiers present in the current preset mapping—not a verified count. Provider products may overlap, have commercial restrictions, or depend on different APIs. They do not meet the ten-verified-provider target.

| Service key | Preset-mapped provider identifiers | Count | Initial gap to target of 10 |
|---|---|---:|---:|
| `airtime` | vtpass, reloadly, vtung, iacafe, clubkonnect, rapidbills, smeapi, bigisub, husmodataapi | 9 | 1+ additional candidate needed; all nine still require verification |
| `data` | vtpass, reloadly, vtung, iacafe, clubkonnect, rapidbills, smeapi, bigisub, husmodataapi | 9 | 1+ additional candidate needed; all nine still require verification |
| `electricity` | vtpass, vtung, iacafe, clubkonnect, rapidbills, smeapi, bigisub, husmodataapi | 8 | 2+ additional candidates needed; all eight still require verification |
| `cable-tv` | vtpass, vtung, iacafe, clubkonnect, rapidbills, smeapi, bigisub, husmodataapi | 8 | 2+ additional candidates needed; all eight still require verification |
| `education` | vtpass, clubkonnect, smeapi, bigisub, husmodataapi | 5 | 5+ additional candidates needed; product coverage must be specific to exam/PIN types |
| `payment-collection` | monnify, paystack, flutterwave | 3 | Research suitable gateways and separately verify collection products and account eligibility |
| `bank-transfer` | monnify, paystack, flutterwave | 3 | Research payout-capable providers; transfer access and limits require separate verification |
| `account-verification` | monnify, paystack, flutterwave | 3 | Verify exact account-resolution endpoints and eligibility; do not infer from payment support |
| `kyc-verification` | none in current preset mappings | 0 | Research official identity providers, supported Nigerian identity types and lawful access requirements |
| `sms` | termii, bigisub, husmodataapi | 3 | Research dedicated SMS providers and compare delivered-message cost, sender ID and DND routes |
| `otp` | termii | 1 | Research dedicated OTP products; SMS-send capability alone does not prove OTP verification support |
| `utility-bills` | vtpass, monnify, flutterwave | 3 | Split into concrete biller services (electricity, TV, education, etc.) before counting coverage |

## High-priority findings

1. **The provider engine and admin UI already exist in Core.** Existing files include the Provider Management page, Provider Wizard, ProviderController, ProviderEngineController, ProviderPresetRegistry, RestJsonProviderAdapter, ProviderManager, mapping/product models and catalogue sync services. The first implementation should extend this foundation, not create a duplicate provider registry.
2. **The current presets are not a verified provider roster.** Several presets contain broad capability labels but have empty endpoint definitions. Every endpoint, authentication method, service ID, request/response mapping and price source must be checked against official documentation.
3. **A provider mapped to a service is not necessarily suitable for all product variants.** For example, education must distinguish exam PINs, result checking, institution payments and any other product. Utility bills must be split by actual service type.
4. **Payment collection, transfers, account verification and identity verification are separate capabilities.** Never count a gateway as a provider for a capability solely because it offers another capability.
5. **Provider identity and backend independence must be researched.** Multiple brands may resell the same upstream aggregator. Record upstream/backend identity where it can be confirmed so coverage is not inflated by aliases.
6. **Pricing must be evidence-based.** Use official live catalogue/variation endpoints where available, record currency and sync time, and preserve provider cost separately from SEMIZZY ONE selling price/profit rules.
7. **The Core currently correctly defaults newly installed presets to disabled and unverified.** Preserve this safe default and require auditable verification before live routing.

## Next implementation work

1. Inventory all service families from all addon manifests and identify current provider integration code, service-specific managers, seeded records, mappings and tests.
2. Review each existing preset against its official documentation; mark endpoint/auth/service-ID/pricing/status/webhook/refund details as verified, pending or unsupported.
3. Add a service coverage register to the admin provider centre, including the separate counts: discovered, documented, configured, sandbox-tested and live-verified.
4. Implement only real missing adapters and capabilities, reusing existing provider managers where safe.
5. Add service-specific provider research and sandbox test fixtures before marking any provider as verified.

## Official research started

- VTU.ng API v2: https://vtu.ng/api/
- VTU.ng reseller/API pricing: https://vtu.ng/pricing/
- VTU.ng legacy API v1 (discontinued): https://vtu.ng/legacy-api-v1/
- VTU.com.ng API documentation: https://vtu.com.ng/api-document
- Flutterwave NGN virtual accounts: https://developer.flutterwave.com/docs/ngn-virtual-accounts
- Flutterwave Nigeria transfers: https://developer.flutterwave.com/docs/nigerian-bank-account-transfer
- Paystack dedicated virtual accounts: https://paystack.com/docs/payments/dedicated-virtual-accounts/
- BulkSMSNigeria API and sandbox: https://www.bulksmsnigeria.com/api
- Termii developer documentation: https://developer.termii.com/
- Travelstart flight API documentation: https://docs.travelstart.com/api/

These are initial leads only; pricing, availability, documentation completeness, credentials and live behaviour must be checked before integration or production claims.


## Service-addon usability requirements

The central Core provider engine is the single source of truth, but administrators should manage providers from the service where they need them.

| Addon context | Show relevant provider capabilities |
|---|---|
| VTU / Telecom | Airtime top-up, data bundles, network/operator catalogue and plan prices |
| Electricity | DisCo/biller codes, meter validation, token purchase and supported tariff/product IDs |
| Cable TV | Provider/biller, package IDs, smart-card validation and package prices |
| Education / Exams | Exam types, PIN products, result-checking operations and their distinct product IDs |
| Payments / Banking | Payment collection, virtual-account issuance, transfer/payout, account resolution as separate capabilities |
| KYC / Identity | NIN/BVN/licence/passport/CAC verification only where officially supported and approved |
| Messaging | SMS sending, OTP initiation/verification, delivery reports as distinct operations |
| Travel | Flight/hotel search, booking, ticketing and cancellation capabilities individually |
| Gift / Virtual Cards | Issuing, balance, activation and redemption only where the vendor supports those operations |
| Other addons | Show only capabilities declared and reviewed for that addon's concrete service operations |

The names above are navigation examples; the final service list must come from the full addon-manifest audit.

### User-interface rules
- Contextual service pages must show compatible providers only, with simple actions and clear statuses.
- The global provider centre remains available for cross-service administration, search and coverage reporting.
- Connecting a provider from a service page preselects the service, capability and environment.
- The same provider identity and credentials are reused safely across addons when appropriate; service-specific mappings and verification remain separate.
- Never duplicate provider records merely to show a provider in multiple addons. Use capability assignments/views into the shared Core registry.
- Providers without a verified capability stay visibly marked pending and cannot be selected for production routing.
- Every service page displays its own counts for discovered, documented, configured, sandbox-tested and live-verified providers, plus the genuine gap to the target of ten verified integrations.


## Required provider-specific service and price import/update controls

Every provider must have a **Services & Prices** area reachable from both the global provider centre and the relevant addon-specific provider view. Admins need category/subcategory filtering, live catalogue refresh where officially supported, preview-and-select import/update, source-price comparison, and sync history/reporting.

For every imported row, retain provider identity, category/subcategory, external product/service ID, provider label, currency, source cost, status, source timestamp and sync timestamp. Show new/changed/price-changed/unavailable/removed/unmapped records before changes are applied. Keep provider source price distinct from SEMIZZY ONE's customer selling price and profit rules; never silently replace selling prices, publish newly discovered products, or route transactions to an unverified provider.

Support category-level manual sync and explicit per-provider/category auto-sync approval. Incomplete or paginated catalogue responses must not trigger destructive removal. Use provider-specific auth, documented endpoint paths, pagination, rate limits and response parsing. Where a provider has no official catalogue endpoint, disclose that limitation and offer a validated manual/CSV mapping workflow if safe; never invent prices or IDs.

The Core routes for discovery, provider services, sync history/summary, import preview, approval and selected import already exist. Audit/reuse these routes first and only add missing behaviour after tests establish the gap. Addon pages are contextual views over the same Core provider and service records; do not duplicate provider or price registries.


## Addon-by-addon service inventory (repository manifest audit, 2026-10-09)

The repository contains 32 addon directories. This inventory identifies each addon’s provider-relevant boundary so the provider platform can be audited without treating every addon as an external API integration. “Not yet evidenced” means the manifest/code presence is not sufficient to claim a working or verified third-party integration. Each actual provider/service pair still needs endpoint, auth, operation, price/source and test evidence.

| Addon manifest identifier | Provider-relevant service / operations to inventory | Existing shared system / integration boundary | Evidence state and next check |
|---|---|---|---|
| `ai.chatbot` | AI model requests, embeddings/knowledge where enabled, usage/balance and provider failover | AI-specific provider configuration; avoid a duplicate outbound provider registry | Audit adapter classes, supported model APIs, auth schema, redaction and failover tests |
| `api.provider-platform` | Cross-service provider directory, capabilities, catalogue import, service/product mapping, price sync and verification | Must reuse Core `ApiProvider`, mappings, credentials, routing, sync and audit | Admin coverage view, publication guard, paginated catalogue read, and safe select-for-review endpoint are implemented on development branch; admin catalogue UI, provider-specific sync adapters, and full operation matrix remain incomplete |
| `banking.financial-integrations` | Account/virtual-account operations, transfers/payouts, account resolution, reconciliation and status | Reconcile with Payments addon and Core provider engine; do not merge distinct capabilities | Inspect banking-specific adapters and official provider permissions per operation |
| `bulk-sms.communication` | SMS send, sender IDs, delivery reports, bulk campaigns, status reconciliation | Shared SMS providers with Communication and SIM Hosting views | Verify provider adapters, DND/sender-ID behaviour, per-message pricing and delivery status |
| `business.agent-merchant-reseller` | No inherent external provider operation; business tiers, limits and commercial pricing | Core users, tiers, price engine and audit | Normally internal; inventory only explicit provider-facing business services |
| `cac.business-services` | Business-name/company registration, CAC search/verification, application submission, documents and order status | CAC-specific catalogue/routes plus Core provider identity and audit | CAC route/catalogue migrations exist; provider endpoint and live evidence must be confirmed |
| `communication.whatsapp` | WhatsApp send/receive, templates, campaigns, consent, delivery status, SMS/email/push failover | Shared communication provider configuration; coordinate with Bulk SMS and Mailer | Verify each channel separately; no capability inferred from another channel |
| `crypto-payments.gateway` | Crypto payment creation, address/quote, confirmations, webhook verification, status/reconciliation/refund if supported | Dedicated crypto payment flow plus Core payment/ledger primitives | Verify each chain/provider and webhook/replay/confirmation rules; do not assume refunds |
| `education` | Paid educational/past-question catalogue and downloads; external provider only if a concrete fulfilment integration exists | Primarily internal content and Core wallet/pricing | No external provider count unless a documented external operation exists |
| `escrow.protection` | Buyer/seller hold, release, cancellation, disputes and refunds | Internal wallet/ledger and P2P integration | Not an external provider by default; audit payment boundary separately |
| `exams.results` | Result-checking tokens, exam PINs, result validation, fulfilment and requery | Core provider engine; distinct from education document downloads | Verify exam type/product IDs and provider-specific fulfilment/status support |
| `gift.cards` | Gift-card catalogue, rates, inventory, purchase/fulfilment, balance/status and refunds when supported | Core provider engine plus wallet and audit | Inventory supported brands/countries/denominations and exact provider operations |
| `government.registration-certificates` | Government application submission, document upload, status and certificate delivery; API or manual workflow | Government addon with explicit manual/API fulfilment boundary | Treat manual service as non-API; verify government-authorised endpoint and permitted operations before counting |
| `insurance.protection` | Quote, eligibility, policy issuance, renewal, cancellation and claims | Insurance-specific workflows using shared provider identity where applicable | Verify insurer/underwriter and each product/operation; no generic “insurance” coverage count |
| `investments.wealth` | Internal investment product configuration, funding, maturity, profit and redemption | Core ledger/pricing; provider only if a concrete regulated external product is integrated | Mark internal workflows separately; audit custody/partner boundary before adding provider coverage |
| `kyc.identity-verification` | NIN/BVN/licence/passport/CAC checks as legally supported, identity match, status and audit | Core provider engine and sensitive-data controls | Zero preset-mapped KYC providers in initial register; confirm lawful access, data minimisation, vendor approval and exact document support |
| `loans.credit` | Internal application, approval, schedule and repayment; external credit bureau/underwriting only if explicitly integrated | Core ledger and approval controls | Do not count internal loan workflow as an API provider; inventory any credit bureau integrations separately |
| `mailer.smtp` | Transactional email send, delivery/bounce signals where supported, SMTP health and failover | Dedicated SMTP profile pool; shared communication channel | Audit TLS/auth variants, secret masking, retry/failover and delivery evidence |
| `marketplace.commerce` | Marketplace inventory/order fulfilment; provider relevance only for external catalogue or shipping/payment integrations | Core payment/wallet and marketplace seller workflows | No provider count for seller listings alone; document any external fulfilment APIs individually |
| `p2p.transfers` | Internal wallet transfers and trading listings/offers | Core ledger; optional Escrow addon | Internal transfer is not a third-party provider integration; reconcile external bank payout only if implemented |
| `payments.gateway` | Payment collection, webhooks, virtual accounts, refunds and payment status | Existing payment-gateway manager; coordinate with Banking without duplicating it | Separate collection, virtual-account issuance, transfers and account verification; audit provider-specific tests |
| `rewards.referrals-promotions` | Internal referrals, rewards, coupons and campaign rules | Core wallet/ledger and pricing | Internal engine; external providers only if explicitly used to fulfil a reward |
| `savings.goals` | Internal goal-based savings, deposits and withdrawals | Core wallet/ledger | Not an external provider by default; inventory any external custody/partner boundary separately |
| `sim-hosting` | SIM Hosting API operations for airtime, data and SMS | Core Provider Engine; consumed by VTU and Bulk SMS | Verify provider docs, endpoint/auth schema, catalogue IDs, balance, order status and source prices |
| `smm.services` | Social media marketing catalogue, order creation, status/requery and cancellation where supported | Core Provider Engine | Verify each service type and vendor operation; do not assume cancellation/refund exists |
| `social.accounts-verification` | Social account services, foreign verification numbers, SMS inbox polling and manual/API fulfilment | Shared provider engine where APIs are used; manual providers remain distinct | Audit number country/service coverage, rental duration, polling, cancel/refund and data handling |
| `spin.to-win` | Internal weighted reward campaigns and reward fulfilment | Rewards addon and Core ledger | Internal game engine; no third-party API count unless prize fulfilment uses a real provider |
| `travel.tickets` | Flight/bus/hotel search, quote, booking, ticketing, cancellation and refunds | Core provider/payment/notification services plus travel-specific adapters | Audit each transport and operation; confirm SOAP/session vs REST needs and certification |
| `virtual.cards` | Card issuance, activation, freeze/unfreeze, balance, transactions, funding and closure when supported | Dedicated virtual-card lifecycle and Core payment/ledger | Verify issuer APIs, country/currency eligibility, KYC and exact lifecycle support |
| `vtu.website-builder` | Website templates/pages/domains/publishing; provider relevance only for explicitly integrated domain or infrastructure APIs | Internal website builder | Do not count templates or hosted pages as provider coverage; inventory domain registrar APIs only if implemented |
| `vtu.digital-services` | Airtime, data, electricity, cable TV, exam products, validation, purchase, status/requery, bulk and refunds where supported | Core ProviderManager, catalogue/mapping/routing and VTU addon workflows | Audit each service/product and provider operation separately; current presets are not proof of live adapters |
| `whatsapp.bot` | Account-number verification, service discovery, transaction requests, receipts and transaction updates | Depends on Communication/WhatsApp and VTU services; should not own a duplicate provider registry | Verify channel webhook/signature, account binding, idempotency and handoff to Core transaction services |

### Service-level evidence ledger required for every API-backed row

For each concrete service and operation above, record: (1) Core/addon route and controller; (2) provider adapter/manager and auth types; (3) official docs and price source; (4) product/service identifiers and currency; (5) request/response mapping; (6) initiation, validation, status/requery, webhook, refund/reversal and reconciliation support individually; (7) fixtures/unit/feature tests; (8) sandbox result and live verification evidence; (9) upstream/backend independence; (10) known blockers and reviewer/date. Use `supported`, `unsupported`, or `unknown` per operation—never infer support from a broad capability label.

This table is the addon/service-family inventory baseline, not a claim that all underlying source files and every provider operation have already been fully audited. The remaining Phase 1 work is to walk the controllers, adapters, migrations, sync jobs and tests for each API-backed row and attach evidence links per operation before calling the inventory complete.

## Addon #38 implementation checkpoint — 2026-10-09

The current development branch now has an initial safe catalogue-review API slice:

- `GET /admin/provider-platform/catalogue`: paginated provider-sourced catalogue records with optional provider, search and review-state filters. The response excludes raw provider payloads and makes source price/currency and last-sync timestamp visible when recorded.
- `POST /admin/provider-platform/catalogue/{providerService}/select`: creates a review record in `awaiting_approval` state with import and auto-sync disabled by default. Repeating the selection does not reset an existing approved/imported record.
- Both endpoints reuse the existing Core provider catalogue and `provider_service_imports` tables; no second provider registry or automatic product publication was introduced.
- Feature tests cover safe catalogue output, review selection defaults, audit recording, and preservation of existing approval/import state.

This slice is not a complete catalogue integration. There is not yet a provider-specific discovery/sync implementation or admin-facing catalogue review screen. Selection is not import approval, mapping, live capability evidence, or production routing. Those must remain separate gated steps.

### First operation-level audit queue

| Service family | First operations to audit independently | Required evidence before marking supported |
|---|---|---|
| Airtime | Catalogue/denomination discovery, purchase, status/requery, balance | Official product IDs and auth contract; successful sandbox purchase/status fixtures |
| Data | Catalogue/plan sync, purchase, status/requery, balance | Exact network/plan IDs, current source-price provenance and sandbox result |
| Electricity | Meter/customer validation, vend, token/result retrieval, status/requery | Supported meter types, validation/vend contract and provider-confirmed transaction outcomes |
| Cable TV | Smartcard/customer validation, package sync, renewal/purchase, status | Package IDs, customer-validation contract and provider-confirmed status handling |
| Education/exam products | Product/PIN sync, purchase, fulfilment/requery, refund/reversal if supported | Exam type/product identifiers and provider-specific fulfilment/refund documentation |
| Payment collection | Intent/session creation, webhook signature/replay, status, refund | Provider-specific webhook/signature tests, idempotency and settlement evidence |
| Virtual accounts and transfers | Account creation, account resolution, transfer/payout, status, reconciliation | Separate permission/contract evidence per operation; do not infer payout from collection support |
| Identity verification | Identity/document-specific check, match result, status, audit | Official eligible document list, lawful access, approved account, data-minimisation and sandbox evidence |
| SMS/OTP | Send, delivery report, sender-ID status, balance, failover | Channel-specific auth, sender-ID rules, pricing, delivery status and retry behaviour |

Every row starts as `unknown` until the matching adapter/controller, official contract, tests and authorised sandbox evidence are linked. Provider preset counts are leads only and must not be counted as successful integrations.

## First code-to-contract audit — airtime/data and SIM Hosting

Audit status is intentionally split into code-path evidence versus provider-contract/live evidence. A Core operation being implemented does not certify a provider integration.

| Provider/service path | Operation | Code evidence | Contract / live status | Finding / next fix |
|---|---|---|---|---|
| Core generic REST adapter | Transaction initiation/status, catalogue, balance, SMS send | `RestJsonProviderAdapter::supports`, endpoint configuration, auth dispatch, idempotency header and safe ambiguous-state handling | Generic code path: supported; vendor-specific payload/auth mapping: unknown | Generic POST/GET is not a provider-specific adapter. Each provider still needs exact field mappings, response normalization, credentials and sandbox tests. |
| Reloadly airtime/data | Catalogue/operators | Preset points to `GET /operators`; Core catalogue sync expects `catalogue_retrieval` | Official API docs describe bearer-token auth and operator catalogue; no authenticated sandbox run recorded | Source: https://docs.reloadly.com/airtime/Top-ups . OAuth token acquisition/refresh and response normalization remain unknown in this app. |
| Reloadly airtime/data | Purchase | Preset points to `POST /topups`; generic adapter can dispatch the operation if the endpoint and capability are configured | Official API docs describe `operatorId`, amount, custom identifier and recipient data; no verified request mapper or sandbox evidence in this repository | Must map local network/plan identifiers to Reloadly operator IDs and normalize amount/recipient fields before enabling. |
| Reloadly airtime/data | Status/requery | Current preset uses a generic `/transactions` status path | Official docs expose `GET /topups/{transactionId}/status`; exact path and response mapping are not represented by the simple static endpoint preset | Mark provider status as unknown until a dynamic path/endpoint adapter and fixture-backed tests are added. Refund/reversal/webhook support remains unknown. |
| VTpass airtime/data/bills | Catalogue, purchase, requery | Preset includes `/services`, `/pay`, `/requery`; generic adapter has no VTpass-specific payload/response mapper | Official docs list airtime, data, electricity, TV and education products; the requery contract uses `request_id`. No sandbox result is recorded | Source: https://vtpass.com/documentation/introduction/ and https://vtpass.com/documentation/foreign-airtime/ . Validate auth signatures, required fields, service IDs and transaction status normalisation with fixtures before enabling. |
| VTU.ng airtime/data/bills | Purchase/status/requery/webhook | Preset declares transaction capabilities but lacks a static `transaction_initiation` endpoint; its status preset is a generic `/api/v2/orders` path | Official docs describe airtime/data endpoints, `/api/v2/requery`, and HMAC-SHA256 webhook signatures; no verified adapter/test result recorded | Source: https://vtu.ng/api/ . This preset is not evidence of working purchase/status/webhook integration. Implement request IDs, correct endpoint mapping, requery, signature verification and status fixtures before enabling. |
| SIM Hosting addon → Core | Airtime/data/catalogue/balance/status/requery | Addon used operation names such as `airtime_purchase`, `data_purchase`, `data_catalogue`, `provider_balance` and `transaction_requery`, which do not match Core's shared operation vocabulary. Those calls would be rejected by the Core capability registry/REST adapter. | Code defect confirmed; fix and tests are on the development branch, awaiting CI | Adapter now translates these business operation names to Core's `transaction_initiation`, `catalogue_retrieval`, `balance_inquiry`, and `transaction_status`. Core REST support for number reserve/release is also added. Do not mark this fix verified until the SIM Hosting workflow passes. |

### Operation ledger rules applied

- `supported` means an app code path is present and covered by a relevant test; it does not mean a vendor operation is live-verified.
- `unsupported` means the current app explicitly rejects the operation or has no route/adapter path.
- `unknown` means code or docs are insufficient to confirm exact operation semantics, identifiers, auth, refund behavior or live result.
- For Reloadly and VTU.ng, refund/reversal is still `unknown`; no generic refund capability should be inferred from purchase/status.
- For every provider, the next evidence artifacts are: official endpoint/schema link, redacted request/response fixture, test result, sandbox verification record, and a reviewer/date. No credentials or live secrets belong in this register.

