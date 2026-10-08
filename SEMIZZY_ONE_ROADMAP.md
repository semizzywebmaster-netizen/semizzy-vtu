# SEMIZZY ONE — Addon Roadmap & Continuation State

> Canonical continuation note for future AI coding sessions. Read this file before proposing or building addons.

## Current project
- Repository: `semizzywebmaster-netizen/semizzy-vtu`
- Current work branch: `feat/education-addon`
- **Current active addon: #1 Education**
- Do not rebuild, rename, or treat existing addons as missing.

## Remaining addon sequence

There are **8 remaining roadmap positions: 1 in progress + 7 not yet built**.

1. **Education** — IN PROGRESS
2. **Events & Entertainment** — NOT BUILT
3. **Stocks & Investments Marketplace** — NOT BUILT
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

## Education — current focus

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

When the user says **“Next bulk action”** or **“Continue with #1”**, first inspect the current `feat/education-addon` branch and continue Education from the actual repository state. Fix pending errors before moving to the next implementation action. Do not restart completed work or jump to another addon.

