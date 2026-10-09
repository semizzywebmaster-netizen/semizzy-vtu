# Provider Research and Authentication Integration Register

Research checkpoint: 2026-10-09
Branch: `feat/admin-operations-command-center`
Purpose: provider-specific integration contracts for the existing Core provider engine. This is a research/configuration register, **not a claim that all ten providers are sandbox- or live-verified**.

## Rule for declaring a provider integrated

A provider is not considered operational merely because it appears in the preset registry or has a documentation URL. Each service/operation must have a documented endpoint, authentication contract, provider product/service identifier, request and response mapping, status/requery strategy, webhook verification (where used), refund/reversal policy, pricing provenance and repeatable sandbox evidence. Keep providers disabled and unverified until the checks pass.

## Ten researched provider candidates

| Provider | Official documentation | Authentication contract found in official docs | What SEMIZZY ONE must request/store | Integration boundary |
|---|---|---|---|---|
| VTpass | https://vtpass.com/documentation/authentication/ and https://vtpass.com/documentation/ | Standard VTpass API uses API key/public key on GET and API key/secret key on POST; separate Messaging API documents X-Token and X-Secret. | API key plus public and secret keys for standard API; messaging public/secret keys only when using that product. | Must implement method-specific header placement and separate product contracts. Don't treat its messaging auth as interchangeable with bill-pay auth. |
| VTU.ng | https://vtu.ng/api/ | JWT is obtained from POST `/wp-json/jwt-auth/v1/token` using account username/email and password, then sent as Bearer token. Docs say the token expires after 7 days and latest token invalidates earlier tokens. | Account username/email and password; token acquisition/refresh and expiry handling. | Must use official v2 endpoint paths and exact service/variation IDs. Do not call it a static API-key integration. |
| Reloadly | https://docs.reloadly.com/ and https://developers.reloadly.com/developer-tools/get-started/overview | OAuth 2.0 client credentials grant; POST to `https://auth.reloadly.com/oauth/token` with client_id, client_secret, grant_type and product/environment-specific audience. Product APIs use Bearer access tokens. | Client ID, client secret, environment and product audience; token lifecycle/cache. | Airtime, gift cards and utility payments have different audience/base URLs and token scopes. Don't reuse one product token for another. |
| Paystack | https://paystack.com/docs/api/authentication/ and https://paystack.com/docs/api/ | Backend requests use secret key in `Authorization: Bearer`; test/live keys are separate. Public keys are not backend secrets. | Secret key and environment; public key only for applicable frontend/checkout features. | Treat payment collection, transfer/payout and account-resolution as distinct capability grants. Confirm account/product eligibility. |
| Flutterwave | https://developer.flutterwave.com/docs/authentication | Server requests use secret key as Bearer token. Public and encryption keys exist for different use cases; encryption key is needed for specified direct-card-charge flows. Test/live modes are separate. | Secret key, environment; public/encryption keys only when required by the selected flow. | Do not require an encryption key for operations that don't use encrypted card payloads. Map each API version/endpoint separately. |
| Monnify | https://developers.monnify.com/ and https://monnify-docs.playground.monnify.com/docs/collections/quickstart | API key + secret key are Base64 encoded as Basic auth to obtain a Bearer token; docs report token expiry (typically one hour / `expiresIn`). | API key, secret key, environment, optional contract code where the product requires it; automatic token renewal. | Authenticated collection, transfers, verification and bill-payment products need separate endpoint/capability verification. |
| Interswitch | https://docs.interswitchgroup.com/docs/authentication | OAuth 2.0 client credentials is documented; legacy InterswitchAuth also requires timestamp, unique nonce, signature, signature method and authorization header. | Client ID + secret for OAuth; for legacy mode, client ID/secret and per-request signing configuration. | Implement OAuth and legacy signature modes distinctly; don't approximate legacy signatures with a static token. |
| Termii | https://developer.termii.com/ | Official developer documentation covers messaging and token/verification products; the account-specific base URL is shown in the dashboard. | API key plus account-specific base URL; product-specific fields as documented. | SMS send, OTP/token verification, delivery/reporting and webhook events are separate operations. Verify each response contract. |
| IA-Café | https://iacafe.com.ng/docs/getting-started | Official quick start says generate an API key and use Bearer authorization; it documents a `/devapi/v1/whoami` connection test. | API key and selected environment/base URL. | Do not infer all service endpoints or status/refund behaviour from the whoami endpoint; map each operation from the provider docs. |
| Bigisub | https://riffutures.github.io/bigisub-docs/docs/intro/ | Published quick start describes obtaining a token through the token endpoint using account username/password, then sending `Authorization: Token <token>`. | Username/password for token issuance or the issued token, depending on the final supported token lifecycle. | The available documentation is a versioned/static quick-start; recheck current official docs and actual endpoint schemas before production use. |

## Authentication methods the Core engine must support

The Core connection/authentication UI and server-side adapter should support these reusable methods, without hard-coding provider names into the runtime:

1. No authentication (only for explicitly public endpoints).
2. Static Bearer access token.
3. Token scheme (for example `Authorization: Token …`).
4. API key in a configurable header.
5. API key in query parameters (only when the provider explicitly documents it).
6. API key/public key/secret key with separate header names and per-HTTP-method placement.
7. HTTP Basic username/password.
8. Username/password/PIN submitted to a token/login endpoint.
9. OAuth 2.0 client-credentials token exchange, including configurable token URL, audience/scope, token response path, expiry and refresh-before-expiry.
10. Provider-specific configurable HMAC/signature authentication (algorithm, canonical-string template, timestamp/nonce, encoding and signature/header names), with safe defaults disabled until reviewed.
11. Configurable custom credential placement in header, query, JSON body or form body.
12. Webhook signature verification configured separately from outbound request authentication.

Never store credentials in frontend bundles, source control, plain logs or audit event payloads. Use encrypted Core credential storage, mask saved values, preserve an existing secret when the UI sends a mask, redact tokens/signatures/passwords, require HTTPS and retain TLS verification. Query/body credentials must be enabled only where official docs require them because URLs and request bodies are more likely to leak.

## Operation-by-operation evidence checklist

For each provider and each advertised service family, record evidence for:

- Authentication/token acquisition, expiry, renewal and sandbox/live separation.
- Base URL and exact endpoint/method for discovery, validation, purchase/transaction initiation, balance, status/requery and any refund/reversal.
- Stable provider product/service/variation IDs. Never infer identifiers from product names or map a conflicting provider ID to a different local product.
- Request/response/error mappings and provider transaction reference.
- Price source field, currency, source timestamp, retrieval time and raw-response provenance; never overwrite the SEMIZZY ONE selling price/profit rule with upstream cost.
- Webhook URL, event types, signature algorithm, replay protection, event idempotency and duplicate-event handling.
- Refund/reversal endpoint and evidence that the provider confirmed the final outcome. If absent or unverified, mark unsupported and do not simulate success.
- Repeatable tests using documented fixtures and sandbox credentials, then a separately approved live verification.

## Current status

The ten providers above are **researched candidates with documentation links and initial authentication contracts**, not ten completed production integrations. Core already contains a generic connection/credential/endpoint model and a configurable REST adapter; each provider still needs its exact endpoint schemas, token/signature behaviour, service mapping, operation support and sandbox evidence before it can be enabled. The Core provider catalogue manager remains the single source of truth for discovery, preview, approval, import and mapping.
