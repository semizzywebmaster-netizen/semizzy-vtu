# SEMIZZY ONE — Addon Roadmap & Continuation State

> Canonical continuation note for future AI coding sessions. Read this file before proposing or building addons.

## Current project
- Repository: `semizzywebmaster-netizen/semizzy-vtu`
- Current work branch: `feat/stocks-investments-addon`
- **Current active addon: #3 Stocks & Investments Marketplace**
- Do not rebuild, rename, or treat existing addons as missing.

## Remaining addon sequence

There are **8 remaining roadmap positions: 1 in progress + 7 not yet built**.

1. **Education** — completed enough to move forward
2. **Events & Entertainment** — foundation implemented; API-provider confirmation intentionally held until a real provider is configured
3. **Stocks & Investments Marketplace** — IN PROGRESS
4. **Forex / Digital Assets** — NOT BUILT
5. **Digital Products / Services Marketplace** — NOT BUILT
6. **Ads & Monetization** — NOT BUILT
7. **AI Chatbot / Platform Assistant** — NOT BUILT
8. **Developer / API Provider Platform** — NOT BUILT; **FINAL ADDON**

Required ordering constraints:
- Education stays #1 and is the current addon.
- Ads & Monetization is #6, the **3rd-to-last** addon.
- AI Chatbot / Platform Assistant is #7, **2nd-to-last**.
- Developer / API Provider Platform is #8, **last/final**.
- Do not insert Logistics or another replacement into this sequence unless the user explicitly changes the roadmap.

## Already-built addons — DO NOT COUNT AS REMAINING

The repository already contains the following addon directories and they must not be reported as missing merely because they are not in the remaining roadmap:

1. banking.financial-integrations
2. bulk-sms.communication
3. business-agent-merchant-reseller
4. cac.business-services
5. communication.whatsapp
6. crypto-payments.gateway
7. education (current work/in progress)
8. escrow.protection
9. exams.results
10. gift-cards
11. government-registration-certificates
12. insurance-protection
13. investments.wealth
14. kyc.identity-verification
15. loans.credit
16. mailer-smtp
17. marketplace.commerce
18. p2p.transfers
19. payments.gateway
20. rewards-referrals-promotions
21. savings.goals
22. sim-hosting
23. smm.services
24. social.accounts-verification
25. spin-to-win
26. travel-tickets
27. virtual-cards
28. vtu-website-builder
29. vtu.digital-services
30. whatsapp-bot

The list above reflects the repository state checked while establishing this roadmap. Verify the actual branch/tree before making claims about implementation status.

## Events & Entertainment — previous addon

Bulk action status: foundation + discovery + event draft/venue/ticket management implemented. Public discovery now supports search, category, format and city filtering; published event detail exposes active ticket types. Admin can create event drafts, venues and ticket types. Publication/verification, checkout/payment, QR issuance/check-in, refunds, payouts, organiser verification and advanced recurring schedules remain to be implemented and tested.

Events & Entertainment is no longer the active addon. Its API-provider confirmation path remains intentionally held until a real provider is configured; no simulated API payment confirmation is permitted. The initial architecture is based on researched ticketing/event-platform patterns: physical, virtual and recurring events; event categories; organiser verification; ticket types; order/ticket lifecycle; QR-based check-in; and event discovery. Do not copy any third-party platform implementation; use these capabilities only as product research.

Research references used for the initial model included Tix Africa's live/virtual/recurring event model, ticket types and QR tickets, and Eventbrite's category/format discovery filters. citeturn0search0turn0search3turn0search8turn0search12

## Stocks & Investments Marketplace — current focus

The existing `investments.wealth` addon is the canonical base for this roadmap position; do not create a duplicate investments addon directory. It is being extended into the Stocks & Investments Marketplace.

Bulk action #1 established the researched regulatory/product boundary and added the initial securities, market-quotes, investment-provider, order, execution, holdings and corporate-actions schema. Trading is disabled by default until a verified execution provider and required KYC/regulatory controls are configured. Market data is disabled by default until a verified licensed data source is configured. No fake prices, fake trades or guaranteed returns.

Research basis: SEC Nigeria's current registered-operator/public-warning material and digital-intermediary rules, NGX Trading License Holder requirements and market-data licensing information, and CSCS investor account/portfolio services. citeturn0search0turn0search2turn0search4turn0search1turn0search10

## Stocks & Investments Marketplace — bulk action #3 completed

The admin control layer now covers the marketplace foundation:
- Admin can create/update securities as drafts with symbol, market, exchange, ISIN, asset type, currency and provenance metadata.
- Admin can publish/unpublish securities; only published securities are exposed by the public marketplace endpoints.
- Admin can register investment providers with explicit provider type/capabilities; new providers start disabled and unverified.
- Provider verification explicitly enables the provider; disabling a provider is available separately.
- Admin can record market quote snapshots with source, observed timestamp, expiry and raw metadata.
- No provider is fabricated or auto-enabled, and no live price is generated by the platform.
- Existing investment-product activation remains intact.
- Admin permissions are separated between general investment management and marketplace management.

Next bulk action should continue the active addon from this state, with the user-facing marketplace/admin UI and provider/execution abstraction built only from actual repository patterns; trading and live market-data ingestion remain gated until real verified providers exist.

## Education — previous addon


Education is being developed as four strictly separated categories:

1. School Admission
2. School Past Questions
3. Exam Past Questions
4. Exam Registration

Separation rules:
- School Admission must not depend on generic past-question tables or exam-body relationships.
- School Admission uses the canonical `education_institutions` table.
- Do not create a second institution table.
- Do not fabricate institution, programme, admission, cutoff, screening, O-Level, UTME, Direct Entry, or other requirements.
- Admission research must use official/verified sources and retain provenance.
- Published admission requirements require verification/source provenance.
- Historical admission sessions must not be overwritten.
- Exam result checking remains owned by the separate `exams.results` addon.

## Continuation rule

When the user says **“Next bulk action”**, first inspect the current active addon branch and continue from the actual repository state. Fix pending errors before moving to the next implementation action. Do not restart completed work or jump ahead of the active addon.

