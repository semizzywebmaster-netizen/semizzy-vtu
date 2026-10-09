# Service-to-Provider Capability Verification Matrix

Updated: 2026-10-09
Scope: SEMIZZY ONE Core provider engine and Addon #38 API Provider Platform.

## Non-negotiable rule

A provider may be recommended for a service only when its official documentation explicitly describes that product/operation. A preset, marketing page, successful authentication, or generic HTTP 200 is not proof that purchase, status, refund, pricing, or webhook operations work. Presets remain disabled and unverified until the operation-level acceptance checks pass. Never invent service IDs or infer refund support.

## Service families and initial evidence-based provider candidates

| SEMIZZY ONE service family | Candidate providers | What official documentation supports at research time | Gate before enabling |
|---|---|---|---|
| Nigerian airtime recharge | VTpass, VTU.ng, Reloadly | VTpass lists Nigerian network airtime; VTU.ng publishes a VTU API; Reloadly documents airtime top-ups and operator lookup. | Confirm each network/operator/product ID, request schema, amount limits, transaction reference, status/requery and sandbox purchase result. Reloadly product audience and base URL must match airtime. |
| Mobile data bundles | VTpass, VTU.ng, Reloadly | VTpass lists data bundles; VTU.ng documents data variations; Reloadly's Airtime API documentation also describes data-bundle subscriptions. | Sync product/variation IDs and provider cost; test each network's catalogue and purchase/status paths separately. |
| Electricity prepaid/postpaid | VTpass; other Nigerian bill aggregators only after documentation review | VTpass explicitly lists named Nigerian DISCOs and prepaid/postpaid electricity. | Verify each DISCO, meter validation, prepaid token response, postpaid flow, status/requery and any provider-specific customer fields. |
| Cable TV subscriptions | VTpass; VTU.ng where exact documented product endpoints confirm it | VTpass lists DStv, GOtv and Startimes subscriptions; VTU.ng publishes a bill-pay API catalogue. | Verify smart-card/IUC validation, plan IDs, renewal fields, purchase and final status. Do not infer provider-specific support from a broad category label. |
| Education PINs/payments | VTpass; VTU.ng only where exact product documentation confirms it | VTpass lists WAEC registration and result-checker PINs. | Verify exam type, quantity/recipient fields, PIN delivery contract and duplicate/idempotency behaviour. |
| Online payment collection / checkout | Paystack, Flutterwave, Monnify; Interswitch only after product-specific docs | Paystack documents transaction initialization; Flutterwave and Monnify publish collection products; Interswitch documents authentication but auth alone does not prove a payment product is enabled. | Separate payment initiation, callback/webhook signature, server-side verification, settlement and refund/reversal. Keep test/live keys separate. |
| Customer bank-transfer funding / virtual accounts | Paystack, Flutterwave, Monnify | Paystack documents dedicated virtual accounts; Flutterwave documents NGN virtual accounts; Monnify documents collection flows and account-related products. | Check merchant eligibility, account assignment, incoming-payment webhook signature, replay protection, ledger idempotency and reconciliation. Do not auto-credit on an unverified webhook. |
| Payouts / bank transfers | Paystack, Flutterwave, Monnify; Interswitch after product-specific review | Candidate payment providers publish transfer/payout products, but each product requires separate account eligibility and operation-level confirmation. | Verify recipient resolution, transfer initiation, final status/requery, reversal semantics, limits and webhook authenticity before enabling. |
| SMS notifications | Termii; VTpass messaging only as a separate product; other providers after docs review | Termii documents messaging APIs and account-specific base URLs. VTpass documents a separate messaging API with different authentication from its bill-pay API. | Verify sender ID, country/route, delivery reports, status codes, rate limits and whether messages are billable on failure. |
| OTP / token verification | Termii; other identity/verification providers only after product-specific review | Termii documents token/verification products. | Verify generation, expiry, attempts/rate limits, verification endpoint and secure handling; sending an SMS is not itself OTP verification. |
| Identity / KYC checks | Provider to be selected after official product and regulatory review (e.g. Dojah/Prembly candidates) | Do not treat payment-provider authentication or a provider name in the preset list as evidence of KYC coverage. | Confirm exact identity products, permitted use, required consent, response fields, pricing, data residency/retention, compliance and sandbox access. |
| Bank/account-name verification | Provider to be selected after exact endpoint review (e.g. Dojah/Prembly or eligible payment providers) | Authentication documentation alone is insufficient evidence of an account-resolution operation. | Verify bank list, account lookup schema, match result semantics, rate limits, price and fallback behaviour. |
| Gift cards / international top-up / other digital goods | Reloadly for documented product APIs; additional providers after review | Reloadly publishes separate API references for airtime, gift cards and utility payments. | Keep product-specific audience, API base URL, catalogue, currency and credentials separate; test purchase, status and refunds per product. |

## Provider-specific authentication and service boundaries

- **VTpass:** standard bill-pay authentication and its separate messaging authentication are not interchangeable. Support method-specific headers/credentials as documented.
- **VTU.ng:** obtain a JWT using the documented login flow and use the documented bearer token lifecycle; don't ask for a static API key as if it were equivalent.
- **Reloadly:** client-credentials OAuth token audience and API host are product-specific. Don't reuse an airtime token for gift cards or utility payments.
- **Paystack:** secret key is for server-side API calls; public key is not a backend secret. Collection, dedicated virtual accounts, transfers and verification must each have their own capability evidence.
- **Flutterwave:** secret bearer key and any public/encryption keys serve different flows. Don't demand card encryption fields for unrelated operations.
- **Monnify:** API key and secret are exchanged for a bearer token; token renewal and any contract code must be handled when the selected product requires them.
- **Interswitch:** OAuth client-credentials and legacy signed authentication are distinct modes; never emulate a signature with a static bearer token.
- **Termii:** use the account-specific base URL and distinguish SMS delivery from token generation/verification.
- **IA-Café:** the documented `/devapi/v1/whoami` endpoint is useful for identity/connection checks, but it does not prove every VTU service or transaction lifecycle works.
- **Bigisub:** published quick-start authentication needs endpoint/version revalidation and a sandbox smoke test before any mapping is advertised as production-ready.

## Admin extensibility requirements

Use the existing Core provider manager, connection credentials, endpoint configuration, service catalogue, provider-service mappings, discovery/sync preview, approval and audit systems. Do not build a second provider registry in Addon #38.

Admins must be able to add a provider without a code release by entering:
- provider name, identifier, official docs URL and base URL per environment;
- authentication type and the fields required by that scheme (API key/token, username/password/PIN, client ID/secret, OAuth audience/scope, or provider-specific signature inputs);
- multiple connections and credentials without exposing saved secrets;
- endpoint/method/content type, request/response/error mapping, timeout, idempotency and documented status/requery path;
- supported service families and explicit provider product IDs;
- webhook verification configuration separately from outbound authentication.

New providers start **disabled, draft and unverified**. Admin can test connection, preview/sync a catalogue, review operation coverage, map provider product IDs to SEMIZZY ONE services/products, run sandbox acceptance tests, and request approval. A successful connection test must not automatically mark purchases, refunds, webhooks, or all services verified. Keep an audit trail for changes and approvals.

## Release acceptance checklist (per provider × service × operation)

- [ ] Official documentation URL and version/last-reviewed date recorded.
- [ ] Exact endpoint, method, authentication, request and response schema documented.
- [ ] Sandbox/test credentials and environment verified; no secrets committed or logged.
- [ ] Catalogue/product identifiers and source cost/currency/timestamp are validated.
- [ ] Purchase/transaction initiation passes repeatable sandbox tests with idempotency.
- [ ] Status/requery handles pending, success, failure, timeout and ambiguous outcomes safely.
- [ ] Webhook signatures, replay protection and event idempotency tested where applicable.
- [ ] Refund/reversal marked supported only after the provider documents it and confirmed outcomes are tested.
- [ ] Provider remains disabled until required checks pass and an authorized admin approves it.

## Official documentation starting points

- VTpass services and API: https://vtpass.com/documentation/introduction/
- Reloadly API reference and airtime: https://docs.reloadly.com/ and https://docs.reloadly.com/airtime/Top-ups
- VTU.ng: https://vtu.ng/api/
- Paystack transaction and dedicated accounts: https://paystack.com/docs/api/transaction/ and https://paystack.com/docs/api/dedicated-virtual-account/
- Flutterwave NGN virtual accounts: https://developer.flutterwave.com/docs/ngn-virtual-accounts
- Monnify: https://developers.monnify.com/
- Interswitch authentication (auth reference only; not proof of all product capabilities): https://docs.interswitchgroup.com/docs/authentication
- Termii: https://developer.termii.com/
- IA-Café: https://iacafe.com.ng/docs/getting-started
- Bigisub: https://riffutures.github.io/bigisub-docs/docs/intro/
