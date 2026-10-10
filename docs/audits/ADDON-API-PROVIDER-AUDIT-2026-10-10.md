# SEMIZZY ONE Addon API Provider Audit

- Audit date: 2026-10-10
- Repository: semizzywebmaster-netizen/semizzy-vtu
- Branch reviewed: main
- Source tree revision reviewed: 2b0271cddaac469b0f22f0107f5d6bba5e285b7b
- Method: static source-tree and targeted-file review. No live credentials or real transactions were used; the full CI suite was not run as part of this source audit.

## Executive result

The repository contains 31 addons. Provider support falls into four patterns: dedicated provider adapters, Core Provider Engine consumers, generic/configurable HTTP or SMTP gateways, and addons where provider execution is incomplete or was not confirmed in reviewed files.

A provider table, seed record, manifest capability, or generic endpoint configuration is not proof of a production-verified integration. Keep providers disabled until documentation, authentication, capability coverage, idempotency, status/requery, webhook verification where applicable, automated tests, and environment-specific checks are complete.

## A. Dedicated provider infrastructure / direct integrations

| Addon | Evidence and operations | Authentication / status / webhook | Tests and gaps |
|---|---|---|---|
| Fiat Payment Gateway (addons/payments.gateway) | Seven adapters: Paystack, Flutterwave, Interswitch, Monnify, OPay, Kora, Squad. Shared contract exposes collection initialization/verification, bank name enquiry, single/bulk payouts, refund request/verification and health check. | Authentication varies by adapter: bearer/secret token, Basic Auth token acquisition, or signed OPay requests. Collection verification and refund verification exist in the contract. Contract has no payout-status/requery method. Webhook service exists; provider-specific signature behavior must be checked per adapter. Interswitch collection/refund are explicitly unsupported in its current implementation. | ProviderAdapterIntegrationTest covers selected Paystack and Interswitch operations; separate refund-settlement, refund-verification and webhook-replay regression tests exist. Not every adapter/method is proven covered. Add adapter-by-adapter auth, timeout, ambiguity, webhook, payout-status and capability tests. |
| Crypto Payment Gateway (addons/crypto-payments.gateway) | NOWPayments, Binance Pay and CoinPayments adapters; dedicated contract, registry, manager, webhook and settlement services. Contract exposes create/verify payment, invoice, checkout, payout, refund and health check. | NOWPayments uses API-key header; Binance Pay builds signed HMAC-SHA512 request headers; CoinPayments uses public/private keys and HMAC-SHA512. verifyPayment exists. One shared webhook service accepts several signature header names and uses common canonicalized JSON/HMAC logic; that is not proof of correct provider-specific signature verification for all three providers. | No dedicated crypto-specific test file was found in the reviewed test tree. High-priority gaps: provider-specific signature fixtures, duplicate/out-of-order events, asset/network checks, confirmations, idempotent settlement, payout/refund verification and ambiguous create-payment outcomes. |
| AI Chatbot (addons/ai.chatbot) | AIProviderService directly integrates OpenAI, Anthropic and Google Gemini. | OpenAI bearer token; Anthropic API-key header/version; Gemini API-key header. Model selection, timeouts, output limits, encrypted-key decryption and safe error messages are present. Provider errors fall through to the next AI provider; acceptable only for non-financial generation and should be bounded/observable. | AIChatbotAddonTest covers provider parsing/auth, fallback, encrypted credential retention and safe errors. Add rate-limit, malformed/empty response, timeout and usage-cost controls as needed. |
| Banking & Financial Integrations (addons/banking.financial-integrations) | BankingProvider model, BankingProviderGateway, account verification/name-enquiry/transfer services and provider migration. | The inspected gateway health check performs GET to configured health_url or base_url. A complete provider-specific authentication/transfer adapter was not confirmed. Internal transfer states exist, but they do not prove external status/requery. Provider-specific webhook verification was not confirmed. | Dedicated provider integration tests were not found in the targeted test-path scan. Implement/test actual account verification, name enquiry, transfer, status/requery, signatures/webhooks and reconciliation before enabling money movement. |
| WhatsApp & Communication (addons/communication.whatsapp) | Provider model/migration, configurable CommunicationProviderGateway, delivery attempts and webhook service. | Generic HTTP endpoint/method/headers/payload configuration; not a vendor-specific adapter per provider. Outbound send records external ID and attempt. A failed request may advance to another provider even if remote outcome is ambiguous. Webhook code supports verify-token challenge and SHA-256 HMAC body signature; confirm this matches each provider's exact scheme. | Dedicated gateway tests were not found in the targeted scan. Add vendor-specific payload/response mapping, delivery-status handling, dedupe, signature fixtures and ambiguity-safe retry rules. |
| Mailer SMTP (addons/mailer-smtp) | SMTP profile model and MailerSmtpService. | Configurable SMTP host, username/password, priority and weight; profiles configure Laravel mailers. Transaction status/requery and webhook semantics are generally not applicable in the same way as payment APIs. | Dedicated SMTP tests were not confirmed in the targeted scan. Test TLS modes, auth failure, timeouts, fallback, bounce/complaint handling and secret masking. |

## B. Addons using Core Provider Engine or generic Core endpoints

| Addon | Current code path | Audit result / required checks |
|---|---|---|
| VTU & Digital Services (addons/vtu.digital-services) | Manifest declares Core ProviderManager integration; VTU operations use Core provider routing. | Keep vendor-specific adapters/contracts in Core provider integrations or a VTU adapter layer. Validate each provider's real purchase, catalogue, balance, status/requery, refund and idempotency support. A preset is not an adapter. |
| Bulk SMS (addons/bulk-sms.communication) | BulkSmsService injects Core App Services Providers ProviderManager. | Shared engine is appropriate. Verify real SMS provider mappings, sender-ID rules, delivery reports, per-message idempotency, accepted-vs-failed outcomes and provider requery. |
| SIM Hosting (addons/sim-hosting) | SimHostingProviderAdapter delegates airtime, data, catalogue, SMS, balance, transaction status and requery to Core ProviderManager. PROVIDER-INTEGRATION.md explicitly documents this. | This is a Core capability facade, not a separate vendor implementation. Verify Core endpoint mappings and each vendor's documentation before enabling capabilities. |
| KYC & Identity Verification (addons/kyc.identity-verification) | KycProviderResolver selects Core API providers; KycVerificationService executes kyc.identity-verification / kyc_verification through Core ProviderManager. | No dedicated identity-vendor adapter was confirmed. Add/test provider-specific identity request/response mapping, consent, identity-type restrictions, result handling and signatures where relevant. |
| Travel & Tickets (addons/travel-tickets) | TravelProviderGateway selects Core ApiProvider and enabled ProviderEndpoint records and sends configured requests. | Generic endpoint model, not a vendor-specific adapter. Booking path treats server errors, 408/429 and exceptions as ambiguous and avoids another provider. Add provider-specific booking lookup/requery, cancellation/refund and webhook verification; test idempotency and response mapping. |
| Exams & Results (addons/exams.results) | ExamResultService uses Core ProviderManager. | Shared engine integration exists. Verify product operation mappings and provider verification of result/token; test status/requery, failed delivery and wallet refund. |
| Insurance Protection (addons/insurance-protection) | InsuranceService uses Core ProviderManager and exposes purchase/requery/cancel/renew/claim methods; it also has an InsuranceProvider model/table. | Potential overlap between addon-specific provider records and Core provider execution. Choose one source of truth; add insurer-specific adapters and tests for policy issuance, status/requery, cancellation, renewal and claims. Do not infer wiring from method names alone. |

## C. Provider records or provider-like abstractions, but complete external API integration not confirmed

| Addon | Evidence / finding | Required action |
|---|---|---|
| Gift Cards (addons/gift-cards) | GiftCardProvider model and product/provider relation exist; inspected GiftCardPurchaseService handles wallet-backed purchase/inventory flow. Vendor-specific HTTP adapter/execution contract was not confirmed. | Add provider adapter contract, secure credentials/configuration, purchase/delivery status, requery, void/refund and vendor response tests—or explicitly document manual/inventory fulfillment. |
| Education (addons/education) | EducationProviderExecutionService::initiate() throws LogicException when no registered adapter exists for the selected provider. | Confirmed gap: register real adapters or disable external-provider purchase execution until done. Test pending/accepted/success/failure/requery transitions. |
| Business Agent / Merchant Reseller (addons/business-agent-merchant-reseller) | BusinessCommercialAdapter exists, but it was not confirmed as an external API provider adapter. | Determine whether it is internal abstraction or vendor integration; do not count it as a working external API until real requests/tests are located. |
| Virtual Cards (addons/virtual-cards) | Service/model code exists; dedicated issuer API adapter was not found in the provider-path scan. | Add issuer-specific issue/fund/freeze/terminate/status/webhook integration or keep in non-live/manual mode. |
| Social Accounts Verification (addons/social.accounts-verification) | Service and inventory/order models exist; dedicated vendor API adapter was not confirmed. | Add SMS/number/account vendor adapters and verify status/requery, delivery callbacks, authentication and refund rules. |
| SMM Services (addons/smm.services) | Service/order models exist; dedicated external SMM provider adapter was not confirmed. | Add documented provider order/status/refill/cancel adapters and tests, or label as manual fulfillment. |
| Government Registration & Certificates (addons/government-registration-certificates) | Application/document/service code exists; external government/vendor adapter not confirmed. | Confirm direct, manual or aggregator connectivity; add vendor-specific API adapter and verification tests where applicable. |
| CAC Business Services (addons/cac.business-services) | Manifest and service routes exist; external API adapter not confirmed. | Verify actual CAC/aggregator connectivity, credential handling, application status/requery and document delivery. |
| Investments, Loans, Savings, Escrow, P2P, Marketplace, Rewards/Referrals, Spin-to-Win, Website Builder | Mostly domain/business services in reviewed paths; no separate external API provider adapter was confirmed in the provider-path scan. | These do not automatically need provider engines. Add vendor APIs only for genuine external capabilities; keep Core wallet, ledger, audit and risk controls shared. |
| WhatsApp Bot (addons/whatsapp-bot) | Webhook controller/transaction notifications exist; separate vendor-specific outbound adapter was not confirmed. | Reuse Communication addon/provider gateway where appropriate; do not duplicate WhatsApp credentials, send/retry and webhook systems. |

## D. All 31 addons — inventory classification

| Addon | Classification from reviewed tree |
|---|---|
| ai.chatbot | Direct external API integrations; dedicated service |
| banking.financial-integrations | Provider model/gateway; external adapter completeness unverified |
| bulk-sms.communication | Core ProviderManager consumer |
| business-agent-merchant-reseller | Provider-like business adapter; external API status unverified |
| cac.business-services | External provider not confirmed |
| communication.whatsapp | Generic HTTP provider gateway + webhook |
| crypto-payments.gateway | Dedicated crypto adapters and settlement/webhook stack |
| education | Provider execution incomplete; explicit missing-adapter failure |
| escrow.protection | No external provider adapter confirmed; mostly domain logic |
| exams.results | Core ProviderManager consumer |
| gift-cards | Provider model exists; vendor adapter not confirmed |
| government-registration-certificates | External provider not confirmed |
| insurance-protection | Core ProviderManager consumer plus addon provider model; source-of-truth/adapter audit needed |
| investments.wealth | No external provider adapter confirmed |
| kyc.identity-verification | Core ProviderManager consumer |
| loans.credit | No external provider adapter confirmed |
| mailer-smtp | Dedicated SMTP profile/configuration service |
| marketplace.commerce | No external provider adapter confirmed |
| p2p.transfers | No external provider adapter confirmed; internal transfer/reconciliation code |
| payments.gateway | Dedicated fiat adapters and reconciliation/webhook stack |
| rewards-referrals-promotions | No external provider adapter confirmed |
| savings.goals | No external provider adapter confirmed |
| sim-hosting | Core ProviderManager capability facade |
| smm.services | External provider adapter not confirmed |
| social.accounts-verification | External provider adapter not confirmed |
| spin-to-win | No external provider adapter confirmed |
| travel-tickets | Core API provider + generic configured endpoint gateway |
| virtual-cards | Issuer adapter not confirmed |
| vtu-website-builder | No external provider adapter confirmed |
| vtu.digital-services | Core ProviderManager consumer |
| whatsapp-bot | Webhook/bot workflow; should reuse communication transport |

## E. Automated test inventory located

- tests/Feature/Payments/ProviderAdapterIntegrationTest.php — targeted Paystack and Interswitch HTTP-fake integration tests.
- tests/Feature/Payments/PaymentRefundSettlementServiceTest.php
- tests/Feature/Payments/PaymentRefundVerificationRegressionTest.php
- tests/Feature/Payments/PaymentWebhookReplayRegressionTest.php
- tests/Feature/AIChatbotAddonTest.php — OpenAI/Anthropic/Gemini provider behavior, fallback and credential/error handling.
- Core provider tests: ProviderCatalogueSyncTest.php, ProviderIdempotencyTest.php, ProviderManagementTest.php, ProviderRequestLoggingTest.php, ProviderRoutingTest.php, RestJsonProviderAdapterTest.php, ProviderUrlGuardTest.php, VtuProviderGatewayFailoverTest.php.
- No dedicated crypto provider test file, and no dedicated banking/communication/SMTP/travel/gift-card/insurance/education adapter integration test file was found in the targeted filename scan. This is a filename/path finding, not proof that no indirect coverage exists.

## F. Required release checklist before enabling any provider

For every provider × capability, record evidence for all applicable items:

1. Official documentation URL and API/product version reviewed.
2. Exact authentication scheme and required credential fields; credentials encrypted at rest and never logged.
3. Request URL, method, headers, payload, response schema and provider error mapping.
4. Capability is genuinely supported by the adapter; unsupported operations fail closed.
5. Timeout, connection reset, 5xx or 429 after submission is treated as unknown/ambiguous, not automatically failed; no duplicate payout, payment, booking or purchase.
6. Provider reference is persisted before subsequent processing where possible; idempotency rules are documented.
7. Provider-specific status/requery endpoint is implemented and tested for delayed outcomes.
8. Provider-specific webhook signature verification, replay/idempotency and event ordering are implemented where webhooks exist.
9. Success/failure/pending/unknown transitions and wallet/ledger effects are idempotent.
10. Tests cover happy path, malformed response, bad credentials, timeout, retry/failover, duplicate request, duplicate webhook and reconciliation.
11. Sandbox evidence recorded; production credentials and live test are separate gates.
12. Admin UI accurately shows capability support, last test, last successful operation and verification status.
13. Provider remains disabled/unverified until required gates pass; presets/seeds must not silently enable it.

## Recommended implementation order

1. P0 — Money movement: add Fiat payout-status/requery contract and provider coverage; ensure Interswitch unsupported methods stay disabled; inspect latest CI and resolve the known P2P MySQL concurrency failure before claiming release readiness.
2. P0 — Crypto: provider-specific webhook verification, ambiguous payment-create reconciliation, settlement replay tests and dedicated tests for NOWPayments/Binance Pay/CoinPayments.
3. P1 — Banking: implement/test provider-specific account verification, transfer, status/requery and webhook contracts; do not show live transfers when only internal state methods exist.
4. P1 — Communication/SMTP: provider-specific delivery semantics and ambiguity-safe retry; align WhatsApp Bot with Communication transport.
5. P1 — Education/Gift Cards/Insurance/Virtual Cards/KYC/Travel/Exams/Social/SMM/CAC/Government: close adapter gaps or explicitly label provider routes manual/not available.
6. P2 — Normalize architecture: keep Core provider credentials, registry, eligibility, logging, URL safety and common operations shared; keep service-specific API adapters and webhook verification in the owning addon. Avoid competing provider tables as sources of truth.
7. Run full CI, security checks, migration checks and targeted HTTP-fake suites; then test each configured provider against its sandbox and only enable capabilities with evidence.

## Limitations

This is a source-level audit, not certification of live connectivity. No external credentials were used and no real payments, payouts, identity checks, bookings, messages or crypto transfers were submitted. Turn findings into regression tests and fix confirmed gaps one by one.
