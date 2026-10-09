# Addon Inventory Audit — Bulk Action 2

Audit target: `main` tree at commit `6529d77b1c88988e6765d5dc55cdc57df85a5dbc`.
Scope: repository tree inventory and focused manifest review. This is not yet a full static analysis of every PHP reference or runtime discovery path.

## Findings

- 1,365 repository tree entries were returned; GitHub reported the tree was not truncated.
- 30 top-level addon directories currently exist directly under `addons/`.
- 29 of the 30 directories contain `manifest.php`.
- `addons/education/` has `database/` and `src/` directories but no `manifest.php`. This is a discovery/installability risk and must be investigated before migration. Do not assume it is a standalone installable addon or create a manifest without inspecting its code and references.
- The existing `vtu.digital-services` manifest uses the stable identifier `vtu.digital-services`, compatibility `>=2.0.0`, no declared addon dependencies, and explicit route/migration paths. Any physical move must account for these path references without changing its identifier or migration history.
- `exams.results` is a separate addon with its own manifest and migrations. Do not merge it into the future exam past-questions addon.
- `payments.gateway` and `banking.financial-integrations` are separate addons with distinct manifests, permissions, migration histories, and provider responsibilities. Keep them separate even if both are placed under a payments/finance category.
- Manifest category assignments below are provisional and should be confirmed against source code and cross-addon imports before moving directories.

## Current addon inventory and provisional destination

| Current path | Manifest present? | Provisional category path | Audit note |
|---|---:|---|---|
| `addons/banking.financial-integrations/` | Yes | `addons/payments/banking-financial-integrations/` | Keep distinct from payment checkout gateway |
| `addons/bulk-sms.communication/` | Yes | `addons/communication/bulk-sms/` | Messaging channel |
| `addons/business-agent-merchant-reseller/` | Yes | `addons/commerce/agent-merchant-reseller/` | Verify reseller and merchant scope |
| `addons/cac.business-services/` | Yes | `addons/business-services/cac-registration/` | Business registration |
| `addons/communication.whatsapp/` | Yes | `addons/communication/whatsapp-integration/` | Distinguish from WhatsApp bot |
| `addons/crypto-payments.gateway/` | Yes | `addons/payments/crypto-gateway/` | Financial gateway |
| `addons/education/` | **No** | Hold — do not move yet | Inspect code, references, and intended status |
| `addons/escrow.protection/` | Yes | `addons/finance/escrow/` | Financial protection |
| `addons/exams.results/` | Yes | `addons/education/exam-results/` | Results checking; not past questions |
| `addons/gift-cards/` | Yes | `addons/commerce/gift-cards/` | Commerce product |
| `addons/government-registration-certificates/` | Yes | `addons/business-services/government-certificates/` | Verify government service scope |
| `addons/insurance-protection/` | Yes | `addons/finance/insurance/` | Financial protection |
| `addons/investments.wealth/` | Yes | `addons/finance/investments-wealth/` | Financial product |
| `addons/kyc.identity-verification/` | Yes | `addons/identity-compliance/kyc/` | Identity verification |
| `addons/loans.credit/` | Yes | `addons/finance/loans-credit/` | Credit product |
| `addons/mailer-smtp/` | Yes | `addons/communication/mailer-smtp/` | Notification transport |
| `addons/marketplace.commerce/` | Yes | `addons/commerce/marketplace/` | Commerce |
| `addons/p2p.transfers/` | Yes | `addons/payments/p2p-transfers/` | Transfers |
| `addons/payments.gateway/` | Yes | `addons/payments/gateway/` | Checkout and funding |
| `addons/rewards-referrals-promotions/` | Yes | `addons/engagement/rewards-referrals-promotions/` | Engagement |
| `addons/savings.goals/` | Yes | `addons/finance/savings-goals/` | Savings |
| `addons/sim-hosting/` | Yes | `addons/digital-services/sim-hosting/` | Hosting-related digital service; verify product scope |
| `addons/smm.services/` | Yes | `addons/digital-services/social-media-marketing/` | Digital service |
| `addons/social.accounts-verification/` | Yes | `addons/identity-compliance/social-account-verification/` | Verification service |
| `addons/spin-to-win/` | Yes | `addons/engagement/spin-to-win/` | Engagement |
| `addons/travel-tickets/` | Yes | `addons/travel/tickets/` | Travel |
| `addons/virtual-cards/` | Yes | `addons/payments/virtual-cards/` | Financial instrument; inspect provider coupling |
| `addons/vtu-website-builder/` | Yes | `addons/site-tools/vtu-website-builder/` | Site-builder tool |
| `addons/vtu.digital-services/` | Yes | `addons/digital-services/vtu/` | Protect live addon ID and migrations |
| `addons/whatsapp-bot/` | Yes | `addons/communication/whatsapp-bot/` | Distinct from WhatsApp provider integration |

## Risks to resolve before moving code

1. **Path-based discovery:** locate every addon scanner, installer, migration loader, route loader, service provider, and test that assumes `addons/<id>/`.
2. **Manifest path coupling:** manifests may contain route and migration paths that include the current directory. Update them and test path resolution as part of each move.
3. **Cross-addon references:** search PHP imports, config references, tests, workflows, and documentation for hard-coded old paths and addon identifiers.
4. **Stable identity:** directory names may change; manifest identifiers, permission names, event names, API paths, table names, and migration filenames must not change merely for organization.
5. **Education ambiguity:** determine whether `addons/education/` is shared infrastructure, an incomplete addon, or legacy code. Keep it unmoved until confirmed.
6. **Workflow coverage:** compare per-addon GitHub Actions workflows with the full addon inventory; use a shared manifest validation job where dedicated workflows would otherwise be inconsistent.
7. **Production safety:** preserve all existing data; do not run rollback/uninstall migrations or remove installed-addon records as part of folder moves.

## Required next audit pass

- Search the repository for addon directory scanning and hard-coded `addons/` paths.
- Inspect all manifest schemas and compare declared migration/route files with files actually present.
- Build a machine-readable inventory of stable ID, path, manifest version, Core compatibility, dependencies, permissions, route files, migration files, and category.
- Add validation tests before implementing any physical move.
- Then migrate one low-risk addon at a time and verify CI and VTU regression tests.

## Completion status

Bulk Action 2 is an initial tree/manifest inventory, not proof that every dependency or path reference has been audited. No addon directories, runtime behavior, or database schema were changed by this audit document.
