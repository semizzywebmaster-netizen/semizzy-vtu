# Education Addons: Scope and Build Order

## Directory rule

Every education addon lives in its own folder under the education category:

- `addons/education/school-admission/`
- `addons/education/school-past-questions/`
- `addons/education/exam-past-questions/`

Each addon owns its manifest, migrations, source, routes, tests and UI. Do not put unrelated education features into one shared manifest, and do not create addon feature branches for individual addons. The active implementation branch is shared; the source folders and addon IDs remain separate.

## 1. School Admission Services

Identifier: `education.school-admission`

Owns:
- Institutions and optional programmes/courses
- Configurable admission products: application forms, post-UTME, screening, acceptance fees, admission-status services and future institution-defined services
- Currency/price in minor units, product activation, provider mapping and structured requirements
- Customer transaction references, provider references, status and reconciliation metadata
- Admin catalog management and user-facing catalog/transaction pages

Safety requirements:
- Never seed fictional institutions, provider connections, prices or admission portals.
- Do not present a product as provider-connected unless a real enabled provider mapping exists.
- Candidate/application data and provider payloads must not be exposed in ordinary page props or logs.
- Wallet debits must be idempotent and ledger-backed. Unknown provider outcomes remain pending/requeryable; never automatically refund a possibly successful request without provider reconciliation.
- Store any uploaded documents privately and authorize every download.
- Institution/programme relationships must be validated; prices use integer minor units, not floating-point values.

## 2. School Past Questions

Identifier: `education.school-past-questions`

Owns school-specific past-paper catalogues, institution/course context, subjects, academic sessions/years, paper files, publication status, access rules, digital purchases, download authorization and download audit events. It must not reuse admission product tables for papers.

## 3. Exam Past Questions

Identifier: `education.exam-past-questions`

Owns exam-body-specific paper catalogues (for example WAEC, NECO, JAMB and NABTEB only when configured), exam/session/year/subject metadata, paper files, publication status, access rules, digital purchases and download authorization. Exam bodies and papers are admin-configured; do not seed assumed provider-backed inventory.

## Shared implementation contracts

- Core wallet and ledger are authoritative; addons must not create competing wallet balances.
- Provider selection and credentials use Core provider management. Provider secrets remain encrypted and masked.
- Each addon declares unique migration filenames and only its own route files.
- Admin routes require role and permission middleware; customer routes must be ownership-scoped.
- Feature tests cover manifest discovery, migration/schema, permission checks, duplicate/idempotent purchases, access denial for private files and failure/requery behavior.
- Category reorganization must preserve public addon identifiers and migration history. Do not rename or relocate already-applied migrations during the initial directory move.

## Build order

1. Establish School Admission schema, manifest, catalog UI and access controls.
2. Complete School Admission provider execution, wallet reservation/settlement, idempotency, requery/refund and tests before enabling purchases.
3. Build School Past Questions as an independent addon, including private file storage and purchase/download controls.
4. Build Exam Past Questions independently, sharing proven patterns without sharing tables or identifiers.
5. Run the full Core and addon CI matrix, then resume the category moves in small audited batches.
