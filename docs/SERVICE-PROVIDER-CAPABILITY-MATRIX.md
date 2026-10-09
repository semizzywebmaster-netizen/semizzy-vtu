# Service-to-Provider Capability Verification Matrix

Updated: 2026-10-09
Scope: SEMIZZY ONE Core provider engine and Addon #38 API Provider Platform.

## Non-negotiable rule

A provider may be recommended for a service only when its official documentation explicitly describes that product/operation. A preset, marketing page, successful authentication, or generic HTTP 200 is not proof that purchase, status, refund, pricing, or webhook operations work. Presets remain disabled and unverified until the operation-level acceptance checks pass. Never invent service IDs or infer refund support.

## Service families and initial evidence-based provider candidates

| SEMIZZY ONE service family | Candidate providers | What official documentation supports at research time | Gate before enabling |
|---|---|---|---|
| Nigerian airtime recharge | VTpass, VTU.ng, Reloadly, Husmodataapi, VTUAgent, CheapDataHub, VTUFast | Official docs support VTpass/VTU.ng/Reloadly; VTUAgent documents airtime purchase; CheapDataHub documents airtime purchase; VTUFast documents airtime plan lookup and purchase. Husmodataapi publicly advertises airtime but an authoritative current API reference was not found in this review. | Confirm each network/operator/product ID, request schema, amount limits, transaction reference, status/requery and sandbox purchase result. Husmodataapi stays disabled until current official API docs and endpoints are obtained. Reloadly audience/base URL must match airtime. |
| Mobile data bundles | VTpass, VTU.ng, Reloadly, Husmodataapi, VTUAgent, CheapDataHub, VTUFast | VTpass/VTU.ng/Reloadly document data products; VTUAgent documents data purchases; CheapDataHub documents data purchase and published plan IDs; VTUFast documents data plans and purchase. Husmodataapi advertises data bundles but a current authoritative API reference was not found in this review. | Sync product/variation IDs and provider cost; test each network's catalogue and purchase/status paths separately. Husmodataapi stays disabled until its current API contract is verified. |
| Electricity prepaid/postpaid | VTpass; other Nigerian bill aggregators only after documentation review | VTpass explicitly lists named Nigerian DISCOs and prepaid/postpaid electricity. | Verify each DISCO, meter validation, prepaid token response, postpaid flow, status/requery and any provider-specific customer fields. |
| Cable TV subscriptions | VTpass; VTU.ng where exact documented product endpoints confirm it | VTpass lists DStv, GOtv and Startimes subscriptions; VTU.ng publishes a bill-pay API catalogue. | Verify smart-card/IUC validation, plan IDs, renewal fields, purchase and final status. Do not infer provider-specific support from a broad category label. |
| Education PINs/payments | VTpass; VTU.ng only where exact product documentation confirms it | VTpass lists WAEC registration and result-checker PINs. | Verify exam type, quantity/recipient fields, PIN delivery contract and duplicate/idempotency behaviour. |
| Online payment collection / checkout | Paystack, Flutterwave, Monnify; Interswitch only after product-specific docs | Paystack documents transaction initialization; Flutterwave and Monnify publish collection products; Interswitch documents authentication but auth alone does not prove a payment product is enabled. | Separate payment initiation, callback/webhook signature, server-side verification, settlement and refund/reversal. Keep test/live keys separate. |
| Customer bank-transfer funding / virtual accounts | Paystack, Flutterwave, Monnify | Paystack documents dedicated virtual accounts; Flutterwave documents NGN virtual accounts; Monnify documents collection flows and account-related products. | Check merchant eligibility, account assignment, incoming-payment webhook signature, replay protection, ledger idempotency and reconciliation. Do not auto-credit on an unverified webhook. |
| Payouts / bank transfers | Paystack, Flutterwave, Monnify; Interswitch only after product-specific transfer onboarding | Paystack officially documents single and bulk transfers, recipient creation and transfer verification. Flutterwave and Monnify document transfer products; availability depends on business/account eligibility. | Verify recipient resolution, transfer initiation, final status/requery, reversal semantics, limits and webhook authenticity before enabling. |
| SMS notifications | Termii; VTpass messaging only as a separate product; other providers after docs review | Termii documents messaging APIs and account-specific base URLs. VTpass documents a separate messaging API with different authentication from its bill-pay API. | Verify sender ID, country/route, delivery reports, status codes, rate limits and whether messages are billable on failure. |
| OTP / token verification | Termii; other identity/verification providers only after product-specific review | Termii documents token/verification products. | Verify generation, expiry, attempts/rate limits, verification endpoint and secure handling; sending an SMS is not itself OTP verification. |
| Identity / KYC checks | Interswitch Developer API | Interswitch's official KYC/Identity Verification overview lists BVN, NIN, driver's licence, international passport and business registration number, and describes address/business verification. Access is business/onboarding dependent; exact endpoint contracts must be confirmed for the account. | Confirm exact identity products, permitted use, required consent, response fields, pricing, data residency/retention, compliance and sandbox access. |
| Bank/account-name verification | Interswitch Developer API | Interswitch documents a Nigerian account-number validation endpoint that validates bank code, account number and account name, using signed InterswitchAuth headers; it also documents KYC and identity verification products. | Verify bank list, account lookup schema, match result semantics, rate limits, price and fallback behaviour. |
| Gift cards / international top-up / other digital goods | Reloadly for documented product APIs; additional providers after review | Reloadly publishes separate API references for airtime, gift cards and utility payments. | Keep product-specific audience, API base URL, catalogue, currency and credentials separate; test purchase, status and refunds per product. |

## Provider-specific authentication and service boundaries

- **VTpass:** standard bill-pay authentication and its separate messaging authentication are not interchangeable. Support method-specific headers/credentials as documented.
- **VTU.ng:** obtain a JWT using the documented login flow and use the documented bearer token lifecycle; don't ask for a static API key as if it were equivalent.
- **Reloadly:** client-credentials OAuth token audience and API host are product-specific. Don't reuse an airtime token for gift cards or utility payments.
- **Paystack:** secret key is for server-side API calls; public key is not a backend secret. Collection, dedicated virtual accounts, transfers and verification must each have their own capability evidence.
- **Flutterwave:** secret bearer key and any public/encryption keys serve different flows. Don't demand card encryption fields for unrelated operations.
- **Monnify:** API key and secret are exchanged for a bearer token; token renewal and any contract code must be handled when the selected product requires them.
- **Interswitch:** OAuth client-credentials and legacy signed authentication are distinct modes; account-name validation uses a signed request contract. KYC products require Interswitch business onboarding and endpoint/credential confirmation; never emulate a signature with a static bearer token.
- **VTUAgent:** official docs use `https://api.vtuagent.com/v1` and Bearer API key; docs describe airtime/data, cable TV, electricity, status reconciliation and signed webhooks. Test each service contract before enabling.
- **CheapDataHub:** official docs use Bearer API key at `https://www.cheapdatahub.ng/api/v1/resellers/`; docs describe airtime/data, electricity, cable, exam PINs, transaction history and webhooks. Docs state a public sandbox is not yet available, so production activation needs controlled verification.
- **VTUFast:** official docs use Bearer API key plus a 4-digit transaction PIN for airtime/data purchases, with plan lookup and wallet balance routes. Store PIN encrypted and never log it.
- **Husmodataapi:** official website advertises airtime and data, but current API reference/auth/endpoint details were not verified from official docs in this research pass. Keep it as an admin-addable draft candidate; don't infer endpoint paths from third-party packages.
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
- VTUAgent: https://docs.vtuagent.com/api-reference
- CheapDataHub: https://www.cheapdatahub.ng/api_documentation/
- VTUFast: https://vtufast.com/api-docs.php
- Husmodataapi official service overview (not sufficient as API contract): https://www.husmodataapi.com/
- Paystack transaction and dedicated accounts: https://paystack.com/docs/api/transaction/ and https://paystack.com/docs/api/dedicated-virtual-account/
- Flutterwave NGN virtual accounts: https://developer.flutterwave.com/docs/ngn-virtual-accounts and https://developer.flutterwave.com/docs/introduction-6
- Monnify: https://developers.monnify.com/ and https://developers.monnify.com/docs/disbursements/single-transfers
- Interswitch account validation: https://docs.interswitchgroup.com/v1.1/docs/validate-account-number
- Interswitch KYC and identity verification overview: https://docs.interswitchgroup.com/v1.1/docs/kyc-and-identity-verification-overview
- Interswitch authentication: https://docs.interswitchgroup.com/docs/authentication
- Termii: https://developer.termii.com/
- IA-Café: https://iacafe.com.ng/docs/getting-started
- Bigisub: https://riffutures.github.io/bigisub-docs/docs/intro/
