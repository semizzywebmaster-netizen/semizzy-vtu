# SEMIZZY ONE — Service Groups & API Provider Documentation Audit

Audit date: 2026-10-10  
Repository: `semizzywebmaster-netizen/semizzy-vtu`  
Purpose: Group the Core service registry and identify provider candidates only where an official, provider-controlled API documentation page was found.

## Important status definitions

- **Official docs found** means a provider-controlled documentation page describes API operations relevant to the service. It does not mean credentials work, sandbox tests passed, commercial access was approved, the provider is cheap/fast, or production readiness has been established.
- **Candidate** means suitable for a service-specific contract review. Do not enable production routing based on this register.
- **Verified integration** requires the exact current endpoint, auth scheme, required fields, status/requery or webhook contract, idempotency/timeout behavior, error mapping, price/catalogue behavior where applicable, and successful sandbox/live checks where the provider supplies those environments.
- Do not count a provider for a service based only on its homepage or on a different capability. For example, SMS sending is not the same as managed OTP verification; payment collection is not payout; account-name lookup is not full identity/KYC verification.
- Provider brand names may share upstream supply. Confirm upstream/backend identity before treating them as independent failover routes.

## Core service groups from ProviderPresetRegistry

| Group | Service key | Purpose |
|---|---|---|
| Telecom | `airtime` | Mobile airtime top-up |
| Telecom | `data` | Mobile data bundles/plans |
| Bills | `electricity` | Meter validation, prepaid token and postpaid bills |
| Bills | `cable-tv` | Smartcard validation and TV packages |
| Education | `education` | Exam PINs and other education products; each product type needs its own mapping |
| Payments | `payment-collection` | Checkout, card/transfer collection, payment verification |
| Banking | `bank-transfer` | Outbound transfers/payouts; separate from pay-in bank transfer |
| Verification | `account-verification` | Bank account resolution/name enquiry |
| Verification | `kyc-verification` | Identity document, NIN/BVN and other approved KYC checks |
| Messaging | `sms` | SMS sending and delivery reports |
| Messaging | `otp` | OTP delivery and/or managed code verification; these are separate capabilities |
| Bills | `utility-bills` | Must be split into concrete biller/service types before routing or counting coverage |

## Candidate pool: airtime and mobile data

Official API documentation found for these provider candidates:

1. [VTU.ng API v2](https://vtu.ng/api/) — airtime, data, plan variations, balance, customer verification and order requery.
2. [VTpass API docs](https://vtpass.com/documentation/) — airtime/data and other bills; includes buying services, status requery and webhook docs.
3. [Paybeta developer docs](https://docs.paybeta.ng/) — airtime/data with authentication, live product catalogue examples, and transaction-query documentation.
4. [VTUGATE API reference](https://vtugate.com/docs) — airtime/data and a documented transaction-status endpoint.
5. [VTUAgent API reference](https://docs.vtuagent.com/api-reference) — airtime/data purchases, plans, status/requery and webhook details.
6. [IA-Café API docs](https://iacafe.com.ng/developer) — airtime/data, balance, order lookup/requery and webhooks.
7. [SME API docs](https://www.smeapi.com.ng/apidocumentation.html) — airtime/data, live plans, authentication and unique references.
8. [ClubKonnect API docs](https://www.clubkonnect.com/apidocs.asp) — airtime/data, balance and service operations.

**Minimum documented candidate set:** five or more for both airtime and data. This is documentation coverage, not five independent upstreams or five tested integrations.

## Candidate pool: electricity and cable TV

Official documentation pages explicitly describe these services for:

1. [VTU.ng API v2](https://vtu.ng/api/) — electricity and TV, customer verification, variations and requery.
2. [VTpass API docs](https://vtpass.com/documentation/) — electricity and TV subscriptions, service IDs, buying and requery.
3. [Paybeta developer docs](https://docs.paybeta.ng/) — electricity meter validation/token purchase and TV package/validation flows.
4. [VTUGATE API reference](https://vtugate.com/docs) — electricity and TV, customer verification and transaction status.
5. [SME API docs](https://www.smeapi.com.ng/apidocumentation.html) — electricity and cable verification/purchase endpoints.
6. [IA-Café API docs](https://iacafe.com.ng/developer) — electricity and cable TV with documented verification/order flow.
7. [ClubKonnect API docs](https://www.clubkonnect.com/apidocs.asp) — electricity and cable TV API sections.

**Minimum documented candidate set:** five or more for each. For live routing, validate each DisCo, meter type, TV provider/package and provider-specific product ID separately.

## Candidate pool: education / exam PINs

1. [VTpass API docs](https://vtpass.com/documentation/) — education products; check exact product and variation availability.
2. [Paybeta developer docs](https://docs.paybeta.ng/) — JAMB/WAEC PIN products and education operations.
3. [VTUGATE API reference](https://vtugate.com/docs) — education PIN API, including WAEC/NECO/JAMB/NABTEB in its service reference.
4. [SME API docs](https://www.smeapi.com.ng/apidocumentation.html) — exam PIN endpoint and additional data/recharge PIN operations.
5. [ClubKonnect API docs](https://www.clubkonnect.com/apidocs.asp) — WAEC and JAMB e-PIN operations.

**Minimum documented candidate set:** five. Keep exam registration PINs, result-checking PINs and other education products as distinct product mappings; do not assume all exam bodies/products are available on every provider account.

## Candidate pool: payment collection

1. [Paystack API docs](https://paystack.com/docs/api/) — transaction initialization/verification and supported collection methods.
2. [Flutterwave developer docs](https://developer.flutterwave.com/) — collections and payment verification.
3. [Monnify docs](https://developers.monnify.com/) — collections, transaction verification and virtual accounts.
4. [Kora developer docs](https://developers.korapay.com/docs/accept-payments) — card/transfer and other pay-in channels; account/product enablement may be required.
5. [Interswitch API Marketplace](https://developer.interswitchgroup.com/) — official API catalogue includes Payments; select the exact product and contract before integration.

## Candidate pool: bank transfers / payouts

1. [Paystack Transfers API](https://paystack.com/docs/api/transfer/) — initiate and verify transfers.
2. [Flutterwave Nigerian bank transfers](https://developer.flutterwave.com/docs/nigerian-bank-account-transfer) — Nigeria transfer flow, prerequisites and transfer fields.
3. [Monnify docs](https://developers.monnify.com/) — disbursements/transfers; note current account-name and API-version requirements.
4. [Kora Payout API](https://developers.korapay.com/docs/payout-via-api) — payout initiation, bank details and transaction query.
5. [Interswitch Send Money overview](https://docs.interswitchgroup.com/v1.1/docs/send-money-overview) — single/bulk transfer and name-enquiry references.

**Access caveat:** transfer/payout APIs often require business approval, funded wallets, IP allowlisting, enabled product scopes or compliance review. A public doc page does not establish that SEMIZZY ONE's merchant account has access.

## Candidate pool: bank/account verification

1. [Paystack account resolution](https://paystack.com/docs/identity-verification/verify-account-number/) — Nigeria/Ghana account-name resolution for eligible businesses.
2. [Monnify customer verification](https://developers.monnify.com/docs/verification-api/verifying-your-customers) — account name enquiry and separately scoped BVN/NIN verification products.
3. [Kora Nigerian bank-account verification](https://developers.korapay.com/docs/nigerian-bank-account-verification) — basic and premium account lookup; consent required for premium personal-data access.
4. [Dojah Resolve NUBAN](https://docs.dojah.io/api-reference/endpoint/general_service/resolve_nuban) — account name resolution.
5. [Interswitch API Marketplace](https://developer.interswitchgroup.com/) — official catalogue includes Identity Verification; select and verify the exact account-validation product/API contract before mapping it.

## Candidate pool: identity / KYC verification

1. [Prembly BVN Basic](https://docs.prembly.com/reference/bvn-basic) and [NIN API reference](https://docs.prembly.com/reference/nin-and-virtual-nin) — explicit BVN/NIN operations.
2. [Dojah API docs](https://docs.dojah.io/) — identity/fraud verification products; confirm each ID type, lawful purpose, consent and commercial access.
3. [Monnify customer verification](https://developers.monnify.com/docs/verification-api/verifying-your-customers) — BVN/NIN products are separately scoped and may be live-only/paid.
4. [Kora BVN lookup](https://developers.korapay.com/docs/nigeria-bvn) and [NIN lookup](https://developers.korapay.com/docs/nigeria-nin) — explicit identity endpoints; consent and account eligibility must be confirmed.
5. [Interswitch API Marketplace](https://developer.interswitchgroup.com/) — identity verification is listed as an API family, but the exact product docs, identity types and access scope must be checked before integration.

**Compliance gate:** never send identity data until SEMIZZY ONE has an approved lawful purpose, explicit user consent where required, data minimization, encryption, access controls, retention/deletion policy and an approved provider account. Do not treat a general identity API catalogue as proof that every ID type is supported.

## Candidate pool: SMS sending

1. [Termii developer docs](https://developer.termii.com/) — messaging API, delivery reports and related products.
2. [BulkSMSNigeria API docs](https://www.bulksmsnigeria.com/api-documentation) — send SMS, bearer token, callback and gateway options.
3. [Sendchamp API reference](https://sendchamp.readme.io/reference/send-otp-api) — official reference includes message/verification APIs; review its messaging endpoints for the exact send-SMS contract.
4. [Infobip SMS API docs](https://www.infobip.com/docs/tutorials/send-your-first-sms-message-using-infobip-api) — send API and delivery-report query.
5. [Twilio Messaging docs](https://www.twilio.com/docs/messaging) — official programmable messaging API; Nigeria route, sender and price eligibility must be tested separately.

## Candidate pool: OTP

1. [Termii developer docs](https://developer.termii.com/) — token/verification product documented; confirm supported OTP lifecycle operations.
2. [Sendchamp Send OTP API](https://sendchamp.readme.io/reference/send-otp-api) — OTP create operation with channel, token length/type and expiry.
3. [Twilio Verify API](https://www.twilio.com/docs/verify/api) — create and check verifications with multiple channels.
4. [Infobip 2FA / SMS docs](https://www.infobip.com/docs/sms) — official docs distinguish SMS from the dedicated 2FA API; use the 2FA product for managed verification.
5. [BulkSMSNigeria API docs](https://www.bulksmsnigeria.com/api-documentation) — documents an OTP gateway for message delivery. Count this only as OTP-message delivery unless SEMIZZY ONE manages the verification lifecycle itself.

## Registry changes made after documentation review

The Core preset registry now includes disabled/unverified candidate records for Kora, Interswitch, Dojah, Prembly, Sendchamp, BulkSMSNigeria, Twilio Verify and Infobip, plus a Monnify KYC service mapping. These records intentionally have empty operation capabilities and endpoint maps. New records are created disabled and paused; their mapping rows are also disabled by default. This makes the providers discoverable for admin review without pretending that the provider-specific adapters are complete.

The existing telecom and education candidates are retained rather than duplicated. Documentation links and service mappings are discovery evidence only. Admins must configure the provider's real base URL/authentication and reviewed service contract before enabling any operation. Infobip in particular may require a tenant-specific base URL. BulkSMSNigeria is a documented SMS delivery candidate; it must not be treated as managed OTP verification unless SEMIZZY ONE owns the OTP generation, expiry, attempt limits and code validation lifecycle.

## Documentation review result and safe next steps

- The links above resolve to official provider-controlled documentation or API reference pages, and the listed service families are supported by those pages.
- This audit did **not** execute authenticated provider calls, confirm account eligibility, benchmark live prices/latency, or establish that providers are independent upstreams.
- Keep existing and newly added provider records disabled/unverified until each adapter has an explicit endpoint/auth/request/response/status contract and automated tests.
- For candidate presets that lack exact service-specific endpoint details, store the official docs URL and relevant service group only. Do not invent endpoint paths, capabilities, prices, auth type or balance fields.
- Admin coverage should show distinct counts: candidate with official docs, contract reviewed, configured, sandbox tested, live verified. Only the final count is eligible for production routing.
- Before declaring five providers available for any service, inspect the exact docs and access prerequisites for that service, then test its unique transaction reference, status requery, duplicate protection, timeout recovery, refund semantics, webhook signature and price/catalogue update flow.

## Contract verification follow-up — 2026-10-10

### Confirmed official contract details

- **Interswitch account name enquiry:** the official account-validation documentation describes `GET /api/v1/nameenquiry/banks/accounts/names` on the sandbox host `https://sandbox.interswitchng.com`, with `bankCode` and `accountId` request headers and the legacy `InterswitchAuth` signature headers (`Timestamp`, `Nonce`, `Signature`, `SignatureMethod: SHA1`, and `TerminalID`). This is a distinct contract from the OAuth-authenticated Interswitch Payouts API. Do not route account verification through the payouts `customer-lookup` endpoint or reuse its OAuth token flow as if the contracts were identical. Official sources: https://docs.interswitchgroup.com/v1.1/docs/validate-account-number and https://docs.interswitchgroup.com/docs/authentication
- **Paystack payouts:** the existing `PaystackPaymentGatewayAdapter` already creates transfer recipients, initiates single and bulk payouts, and validates references/amounts. The official API uses `POST /transfer` and provides `GET /transfer/verify/:reference` for transfer-status verification. Current adapter work must add/verify payout-status reconciliation and tests before payouts are treated as complete. Official source: https://paystack.com/docs/api/transfer/
- **VTUFast:** official documentation confirms account/balance lookup, airtime/data plan catalogue retrieval, and purchase requests to `https://vtufast.com/api.php`. The currently published documentation reviewed on 2026-10-10 does not describe a transaction-status/requery endpoint or a definitive post-purchase lookup contract. Keep VTUFast purchases disabled/unverified until the vendor provides and confirms that contract; a successful HTTP response alone is not a safe substitute for status reconciliation. Official source: https://vtufast.com/api-docs.php
- **Husmodataapi:** no official, provider-controlled API contract was confirmed in this review. Endpoint paths, auth fields, product IDs, transaction-status/requery behavior, pricing/catalogue sync, and error semantics remain unknown. Do not invent them from third-party snippets; keep the provider disabled/unverified until the official documentation or vendor-supplied contract is obtained.

### Airtime/data implementation gate

The official documentation review identified service-specific candidate contracts for VTU.ng v2, VTpass, Paybeta, VTUGATE, VTUAgent, IA-Café, SME API and ClubKonnect. These are candidate contracts, not eight completed adapters. For each service/provider pair, implement and fixture-test its actual authentication, network/product IDs, amount units, request fields, provider reference extraction, pending/success/failure normalization, status requery, timeout/unknown handling, and live catalogue pricing. Keep all new or changed routes disabled until the exact contract and authorised sandbox checks pass.

### CI repair checkpoint

The latest `SEMIZZY ONE CI` run failed in PHP syntax validation at `app/Services/Providers/ProviderCapabilityRegistry.php:3` because namespace backslashes were double-escaped. That source file has been corrected. A second malformed namespace/fully-qualified class reference was found and corrected in `tests/Unit/ProviderCapabilityRegistryTest.php`. CI for the follow-up commits was queued at the time of this update; no green result is claimed until the new runs complete.
