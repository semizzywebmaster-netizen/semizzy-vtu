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
