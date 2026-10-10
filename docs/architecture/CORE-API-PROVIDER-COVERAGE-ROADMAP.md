# SEMIZZY ONE — Core API Provider Coverage & Addon Build Plan

- Date: 2026-10-10
- Repository: `semizzywebmaster-netizen/semizzy-vtu`
- Branch: `main`
- Status: Architecture/build requirements; not a claim that all adapters are implemented or live-verified.

## 1. Architecture rule

All addons/services that need an external third-party API must use the shared Core API Provider Engine by default, except the seven dedicated integration addons listed below. A single provider may be mapped to multiple addons when the provider's official API, contract, licence and credentials support each requested capability. Register provider credentials once in Core; keep per-addon product mappings, capability eligibility, priorities and fallback policy separate.

The seven dedicated integration addons are:
1. Fiat Payment Gateway — payment collection/payout/refund adapters.
2. Crypto Payment Gateway — crypto payment/invoice/payout/refund and settlement adapters.
3. Banking & Financial Integrations — banking-specific account, transfer and financial-operation adapters.
4. WhatsApp & Communication — messaging-channel transport, delivery and webhook integrations.
5. AI Chatbot — LLM/model integrations.
6. SMTP Mailer — SMTP/email delivery profiles.
7. WhatsApp Bot — bot conversation, inbound webhook, command and transaction workflow. It should reuse WhatsApp transport from WhatsApp & Communication rather than maintain a second set of provider credentials or duplicate send/retry logic.

These are specialized exceptions, not a blanket exclusion of every financial or communication feature. For example, stock trading, forex market data, investment brokers, crypto exchange trading, and lending/origination providers are business-service providers and should use Core API Provider Engine unless a separately approved specialized addon contract is explicitly required. The Crypto Payment Gateway must not be treated as a crypto-exchange trading adapter.

## 2. WhatsApp Bot: current source-level finding

The current `addons/whatsapp-bot/manifest.php` declares:
- dependencies on `communication.whatsapp` and `vtu.digital-services`;
- provider integrations named `Communication & WhatsApp`, `Core ProviderManager`, and `VTU & Digital Services`;
- provider capabilities `whatsapp_receive` and `whatsapp_send`;
- transaction commands, registered-number verification, transaction status, receipts, notifications and idempotent commands.

Therefore, **the WhatsApp Bot already declares provider integration/capability links in its manifest**. This is not sufficient evidence that a working vendor API adapter exists: the bot's service folder contains a transaction-notification service, while the manifest relies on the Communication addon for WhatsApp transport. Before calling this complete, trace the runtime path from inbound webhook through signature validation and command handling, and from outbound send through the Communication provider gateway. Add integration tests for the actual provider payloads, auth/signature, duplicate webhook/event handling, send status, delivery receipts, retries and ambiguous outcomes. Do not add a second WhatsApp provider registry inside the bot.

Source: `addons/whatsapp-bot/manifest.php`; inspect alongside `addons/communication.whatsapp`.

## 3. Provider capability coverage matrix — every current addon

| Addon/service | Core API provider requirement / capability examples | Important controls |
|---|---|---|
| `vtu.digital-services` — VTU & Digital Services | Airtime, data, cable TV, electricity, bill payments, catalogue, balance, purchase, status/requery, reversal/refund where supported | Per-product mapping, network/plan validation, idempotency, safe pending state, no duplicate purchase |
| `bulk-sms.communication` — Bulk SMS | SMS sending, sender ID, balance, delivery report/status | Provider-specific sender rules, delivery callbacks, message ID, opt-out/compliance |
| `kyc.identity-verification` — KYC & Identity | NIN/BVN and other permitted identity/document checks from eligible licensed providers | Consent, data minimization, encryption, audit, identity-type restrictions, provider response normalization |
| `sim-hosting` — SIM Hosting | Supported SIM/telecom operations, airtime/data/SMS, balance, status/requery | Device/SIM authorization, provider capability checks, privacy and fraud controls |
| `exams.results` — Exams & Results | Exam pins/tokens, result checker products, purchase and verification | Product catalogue, pin/token delivery protection, requery and safe refund |
| `education` — Education | School/education product catalogue, school fee payment/verification, admission or result services only where provider supports them | Missing adapter must fail closed; map each operation and response schema |
| `travel-tickets` — Travel & Tickets | Search, quote, booking, booking lookup, ticket issue, cancellation/refund where supported | Hold/confirm lifecycle, booking reference persistence, requery, no duplicate booking after ambiguous timeout |
| `insurance-protection` — Insurance | Quotes, eligibility, policy issuance, policy status, renewal, cancellation, claim submission/status | Insurer/aggregator capability contract, policy document delivery, reconciliation; one provider source of truth |
| `gift-cards` — Gift Cards | Provider catalogue, rates, purchase, delivery, balance, redemption/validation, status, void/refund where supported | Region/currency/brand restrictions, inventory/manual fulfilment distinction, no fake live fulfilment |
| `government-registration-certificates` — Government Registration & Certificates | Approved agency/aggregator service catalogue, application submission, fee quote, status, document retrieval | Only authorized APIs; consent, reference numbers, secure documents, audit and status requery |
| `cac.business-services` — CAC Business Services | Authorized CAC/aggregator name search, availability, filing/submission, status, certificate/document retrieval | Do not claim direct CAC access without credentials/authorization; protect applicant data |
| `social.accounts-verification` — Social Accounts Verification | Supported phone/SMS/OTP/account verification vendors, issue/check OTP, lookup/status | Rate limits, abuse prevention, expiry, delivery and verification result |
| `smm.services` — SMM Services | Service catalogue, quote, order, order status, refill/cancel only if documented by vendor | Terms/platform compliance, idempotency, quantity limits and provider response mapping |
| `virtual-cards` — Virtual Cards | Issuer create/issue, KYC eligibility, fund, balance, freeze/unfreeze, terminate, transaction list, status/webhook | Issuer permissions, PCI/security boundaries, limits, ledger reconciliation and idempotency |
| `investments.wealth` — Investments & Wealth | If using external assets: provider product catalogue, quotes, account opening, eligibility/KYC, subscribe/buy, portfolio, valuation, maturity/redemption, transactions and reconciliation | Existing wallet-backed products can remain internal; external brokerage/asset-manager operations require provider adapters and regulatory approval |
| **Stocks / securities trading (service capability, not a separate addon folder currently confirmed)** | Market/instrument catalogue, delayed/real-time quotes per licence, historical prices, company/reference data, broker account, order quote/place/replace/cancel, order status, positions, portfolio, corporate actions, statements and reconciliation | Do not invent a separate existing addon; implement under Investments & Wealth or a separately approved addon. Broker/custodian and market-data licensing requirements must be verified. |
| **Forex / FX (service capability, not a separate addon folder currently confirmed)** | FX rates/quotes, currency conversion, spread/fees, quote expiry, execution/order status, balance/settlement and reconciliation if actual trading/remittance is offered | Separate read-only exchange-rate APIs from regulated FX trading or remittance. Do not use a quote API as proof of execution capability. |
| `loans.credit` — Loans & Credit | Optional external lender/credit-scoring/identity/income verification, eligibility/offer quote, application submission, decision/status, disbursement reference, repayment status and reconciliation | Internal loan engine is not itself a provider. Use consent, fair-lending controls, affordability, audit and authorized lender contracts |
| `savings.goals` — Savings Goals | Usually internal Core ledger/domain logic; external bank/open-banking/savings partner APIs only for actual external accounts, standing orders or account linking | Never duplicate Core wallet/ledger; reconcile partner balances and consent |
| `banking.financial-integrations` | Specialized exception: account verification/name enquiry, transfers, bank/financial status and reconciliation | Provider-specific auth, idempotency, status/requery, webhook signatures, maker-checker and limits |
| `payments.gateway` — Fiat Payment Gateway | Specialized exception: collection, verification, payout, refund and reconciliation | Do not treat a payment initialization response as settlement; provider-specific payout requery |
| `crypto-payments.gateway` — Crypto Payment Gateway | Specialized exception: crypto payment creation/verification, invoices, supported payout/refund, chain/network/confirmations and settlement | Distinct from crypto-exchange market/trading APIs |
| **Crypto exchange/trading (if offered as a service)** | Market data, pairs/assets, quotes, account balances, order create/cancel/status, fills, deposits/withdrawals and reconciliation via authorized exchange API | Use Core provider engine as a business-service capability; enforce asset/network allowlists, signing security and strict transaction limits |
| `communication.whatsapp` — WhatsApp & Communication | Specialized exception: WhatsApp and other configured communication transports, send, delivery status, inbound webhooks | Single transport source of truth; vendor-specific signature and payload tests |
| `whatsapp-bot` — WhatsApp Bot | Specialized exception for bot logic; reuse Communication transport and Core provider routing for service transactions | Inbound webhook verification, verified phone, command idempotency, send/delivery receipts, no duplicate transaction |
| `ai.chatbot` — AI Chatbot | Specialized exception: OpenAI/Anthropic/Gemini or additional model APIs | Model-specific auth, timeouts, cost limits, privacy, output validation |
| `mailer-smtp` — SMTP Mailer | Specialized exception: SMTP profiles and email delivery | Secret masking, TLS/auth checks, bounce/complaint processing |
| `business-agent-merchant-reseller` | External reseller/wholesale APIs only if actual external partner exists; otherwise internal business workflow | Confirm external adapter versus internal abstraction |
| `marketplace.commerce` | Shipping, tax, catalogue, fulfilment or supplier APIs where enabled | Keep payment processing in Fiat/Crypto Gateway; vendor fulfilment status/requery |
| `escrow.protection` | Optional external escrow/custody/legal partner API only if an authorized partner is used | Internal escrow logic must not be mislabelled as external provider integration; maker-checker and immutable audit |
| `p2p.transfers` | Usually internal user-to-user transfer logic; external bank/verification APIs only for bank payout or identity capabilities | Shared Core ledger, concurrency-safe locking, reconciliation; no provider for internal transfer itself |
| `rewards-referrals-promotions` | Usually internal logic; optional external voucher/reward catalogue/fulfilment provider | No duplicate wallet ledger; reward idempotency and abuse controls |
| `spin-to-win` | Usually internal game/reward logic; external prize fulfilment provider only where used | Fairness/audit, prize inventory, fulfilment status |
| `vtu-website-builder` | Usually internal website-building and publishing logic; optional domain registrar/DNS/hosting/email providers | Verify domain/hosting operations individually; secrets and domain ownership controls |
| `whatsapp-bot` | See dedicated bot row above; no second vendor provider registry | Reuse `communication.whatsapp` transport |
| `ai.chatbot` | See dedicated AI row above | Do not route model calls through generic VTU providers |
| `mailer-smtp` | See dedicated SMTP row above | Email-specific delivery semantics |
| `business-agent-merchant-reseller` | See reseller row above | Don't count an internal adapter as a live provider |
| `cac.business-services` | See CAC row above | Authorized integration only |

## 4. Additional service families that must be included in service discovery

The product/service catalogue and addon requirements review must explicitly look for these capabilities even if no separate addon directory currently exists:
- Foreign exchange (FX) quotes, conversions, and—only if legally authorized—trading/execution.
- Stocks, ETFs, bonds, funds, local and international securities, market data, portfolios, brokerage account and orders.
- Investment products, asset managers, custodians, broker-dealers and portfolio valuation.
- Loans/credit: internal loans, external lender offers, credit bureau/scoring, affordability and income verification, loan status and repayment collection.
- Crypto exchange/trading and market data, distinct from crypto payment acceptance.
- Remittance, open banking, bank account linking, direct debit/standing orders, where licensed and implemented.
- Commodity/precious-metal pricing or trading if present in the planned catalogue.
- Utility, telecom, education, travel, insurance, identity, government/CAC, gift-card, social verification, SMM, virtual card and marketplace fulfilment APIs.
- Domain registrar, DNS, hosting, and website publishing providers if Website Builder sells those services.

For each family, first establish whether it exists in the product catalogue, UI, routes, manifest, or prior requirements. If it exists or is planned, add it to the provider capability matrix. If there is no separate addon yet, record the owning addon or create a separately scoped addon proposal rather than silently omitting the service.

## 5. Core provider contract requirements

Core must support configurable authentication schemes (API key/header/query only where safe, bearer token, Basic, OAuth2 client credentials, HMAC/signature, username/password, public/private key and provider-specific token acquisition), encrypted credentials, secret masking, environment/base URL separation, URL/SSRF protections, health checks, structured request/response redaction, timeouts, rate limits, circuit/health state, priority and capability-based eligibility.

For every provider × capability, the registry should store:
- owning provider ID and vendor;
- addon/service capability key and operation name;
- official documentation URL and reviewed API version;
- auth scheme and required credential-field schema;
- request/response mapping version;
- supported status/requery endpoint and its limitations;
- webhook/event type and provider-specific signature verifier, where applicable;
- idempotency behavior and ambiguity/reconciliation policy;
- sandbox availability and last test evidence;
- enabled/disabled, verified/unverified, last health/test result and failure reason.

A generic configured endpoint is useful for safe simple integrations, but must not be presented as a completed vendor adapter when operation mapping, signing, requery or webhooks need provider-specific code.

## 6. Release and safety gates

- A provider is registered once and reused across addons only through explicitly granted, tested capabilities.
- Per-addon product mappings, priority, fallback, price/profit rules and activation remain separate.
- Unsupported operations fail closed and are not displayed as available.
- Keep providers disabled/unverified until official docs, credential validation, operation tests and applicable sandbox checks pass.
- A timeout or connection error after a money-moving or irreversible request is an unknown outcome. Requery/reconcile before retrying; never automatically try another provider if that can duplicate the transaction.
- Use Core wallet, ledger, transaction idempotency and audit systems; addons must not create competing wallet/ledger sources of truth.
- Add HTTP-fake/contract tests for authentication, success, provider errors, malformed responses, timeouts, duplicate calls, status/requery, duplicate/out-of-order webhooks, signature rejection, and reconciliation.
- Record live verification separately from sandbox tests; do not claim real connectivity without authorized credentials and evidence.
- Do not add invented provider names or fake endpoints. Provider candidates must be backed by official docs, partner access and supported operations.
- Keep deployment compatible with Laravel/cPanel shared hosting; no mandatory Docker, Redis, RabbitMQ, Supervisor, systemd, root or Nginx-only runtime.

## 7. Implementation order

1. Confirm WhatsApp Bot's real runtime provider path and add end-to-end provider contract tests.
2. Close P0 payment/crypto safety gaps and repair currently failing CI before declaring release readiness.
3. Build the cross-addon provider capability registry/mapping and show provider eligibility by addon.
4. Close confirmed missing adapters: Education, Gift Cards, Banking, Insurance, Virtual Cards, KYC, Travel, Exams, Social Verification, SMM, CAC/Government.
5. Add finance-market service adapters in scope: Investments/Brokerage, Stocks/Securities, Forex/FX, Crypto Exchange/Trading, and external Lending. Confirm regulated counterparties and data licences before live trading or lending.
6. Audit every addon manifest, route, UI service catalogue, database seed and admin page against this matrix. Add any discovered service not yet listed before enabling its provider.
7. Run full CI, migrations, contract/security tests, sandbox verification and then separately controlled production verification.

## 8. Evidence and limits

This document records intended coverage and source-level findings, not completion of all integrations. The WhatsApp Bot manifest declares `whatsapp_send` and `whatsapp_receive` and depends on the Communication addon; that alone does not prove the full vendor send/webhook/status path works. The repo currently has `investments.wealth` and `loans.credit` addon directories; no separate `forex` or `stocks` addon directory was found in the checked top-level addon listing. Forex and stock trading are therefore explicitly captured as required service capabilities to assign to Investments & Wealth or a separately approved addon after a full catalogue/requirements audit.


## 9. Repository service-catalogue audit snapshot — 2026-10-10

The current `addons/` directory contains exactly 31 addon folders. The service-family coverage table above maps these current addon identifiers:

`ai.chatbot`, `banking.financial-integrations`, `bulk-sms.communication`, `business-agent-merchant-reseller`, `cac.business-services`, `communication.whatsapp`, `crypto-payments.gateway`, `education`, `escrow.protection`, `exams.results`, `gift-cards`, `government-registration-certificates`, `insurance-protection`, `investments.wealth`, `kyc.identity-verification`, `loans.credit`, `mailer-smtp`, `marketplace.commerce`, `p2p.transfers`, `payments.gateway`, `rewards-referrals-promotions`, `savings.goals`, `sim-hosting`, `smm.services`, `social.accounts-verification`, `spin-to-win`, `travel-tickets`, `virtual-cards`, `vtu-website-builder`, `vtu.digital-services`, and `whatsapp-bot`.

The inspected Inertia page catalogue includes service surfaces for Bulk SMS, Communication, Government Services, Insurance, Investments, Loans, Savings and SIM Hosting, plus subdirectories for CAC, Education, Escrow, Exams, Marketplace, P2P, Social Services, Spin-to-Win, Travel Tickets, VTU and WhatsApp Bot. **There is an existing FX-rate feature** (`FxRateProvider`, `FxRateService`, `FxProviderController`, and `Admin/FxProviders`), but it uses a separate `fx_rate_providers` table and direct HTTP calls rather than the shared Core API Provider Engine. It provides reference rates only; it does not prove FX trading/execution support. No separate Stocks/Securities or Crypto Exchange/Trading addon/page was found in the inspected listing. FX-rate provider consolidation is therefore an explicit architecture gap, while stocks/securities and crypto exchange/trading remain service capabilities to assign to Investments & Wealth or separately scoped addons.

### Implementation progress in this bulk action

- WhatsApp Bot now matches inbound HMAC signatures against the actual configured provider rather than blindly selecting the highest-priority provider, has a GET verification handshake, processes all inbound message entries in the payload, and derives a stable transaction idempotency key from the provider message ID.
- WhatsApp inbound payload persistence redacts a 4-digit transaction PIN before storing message body/metadata. The communication send gateway now stops failover on connection timeouts and HTTP 408/5xx ambiguous outcomes, leaving the message pending/unknown for reconciliation instead of risking duplicate sends.
- Automated tests were added for provider signature selection, webhook verification, duplicate inbound event handling, PIN redaction, and no-failover behavior after an ambiguous send. These tests still require a successful CI run.
- Core provider capability vocabulary now includes configurable endpoint operations for investments, stocks/securities, FX, external lending, crypto exchange/trading, cards, insurance, travel, education, gift cards, government/CAC, marketplace and domain/hosting services. The Core REST adapter accepts these operations; mutating operations require stable idempotency keys and live-verified providers, and money-moving/order-creation operations require a matching status/requery capability or generic transaction-status support.
- The confirmed MySQL refund-settlement savepoint failure was addressed by returning a blocked-accounting result from the nested transaction and recording manual reconciliation after that transaction returns. A subsequent CI run reported the refund tests passing but exposed a nondeterministic P2P concurrency assertion that incorrectly assumed which recipient process would win. The assertion was corrected to accept either valid outcome while still requiring exactly one successful transfer. The latest CI result must be checked before treating these fixes as verified.

**Remaining implementation gap:** broad operation names and generic endpoint execution do not replace vendor-specific request/response mapping, provider documentation review, signed authentication, order/booking status requery, webhook validation, sandbox contract tests, or addon service integration. Keep each unimplemented capability disabled/unverified until those are complete.
