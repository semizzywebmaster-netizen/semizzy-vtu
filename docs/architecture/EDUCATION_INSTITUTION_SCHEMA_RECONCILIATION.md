# Education institution schema reconciliation (2026-10-10)

## Current-main baseline

Current `main` has the `education_reference_catalogue` table. It is a curated/admin-maintained lookup catalogue for `school`, `exam_body`, and `exam_type`, keyed by `catalogue_key`. It is used by the Education Reference Catalogue UI and must remain available for past-question and exam-body lookups. It is not yet a transaction-ready institution aggregate: it lacks a stable institution code/external ID, LGA/city, ownership/accreditation/classification fields, and institution relationships.

## Competing proposed schemas

- PR #18 introduces `education_institutions` with stable code, type/category, ownership, accrediting body, establishment year, city/state/LGA/country, website, external ID, active flag, metadata, classification and soft deletes. It also adds academic units/departments/programmes and institution/product/transaction models.
- PR #19 introduces `school_admission_institutions` with slug, institution type, country/state, official website, admissions URL, logo and metadata, and its own programme/product/transaction tables.

Do not merge either institution table wholesale. Both create a separate institution identity and would cause duplicate records and divergent activation states. Keep PR #19's School Admission product and transaction concepts separate from past-question categories and payment history; do not import or move the addon as part of this slice.

## Canonical direction (decision before schema port)

1. Keep `education_reference_catalogue` as a compatibility lookup for exam bodies, exam types and legacy reference entries.
2. Establish `education_institutions` as the canonical institution entity for institution sync, Education products, academic units/programmes and future School Admission relationships.
3. Migrate existing `kind=school` catalogue rows into the canonical table with deterministic, reviewable mapping. Preserve original catalogue IDs/keys and references; do not delete the old catalogue rows as part of the initial migration.
4. Model School Admission-specific programmes, application products and transactions in its own addon namespace, but reference the canonical institution by nullable FK during a staged compatibility period. Do not create a second school-institution master.
5. Only activate newly synchronized institutions after review. Existing curated/approved entries remain available unless explicitly deactivated.

## Synchronization defects to fix before porting PR #18

- `EducationInstitutionImportService::import()` defaults records to active and overwrites existing institution fields, including active state, without a review boundary.
- Provider sync calls that importer directly, so a provider feed can activate/overwrite an institution without review.
- The official sync uses regex-based HTML row parsing, which is brittle against markup changes and does not demonstrate complete pagination.
- `syncAll()` can return `manual_parser_required` for unsupported sources, while `DataSyncService::sync()` still records the run as completed and the UI can report successful synchronization.
- The `requires_review` dataset flag is stored but is not enforced at the institution-import boundary.
- Stable identity should prefer a trusted source-specific external ID; normalized code/name should be a documented fallback with collision detection, not an unconditional overwrite key.

## Work included in draft PR #34

PR #34 adds review state to the existing reference catalogue and ensures CSV-imported rows are inactive/pending until an administrator explicitly approves them. Existing curated rows default to approved for backward compatibility. This is an isolated safety improvement, not the canonical institution-table migration or the port of the sync engine.

## Required next validation gate

Before a canonical schema/sync PR is merge-ready:

- Add deterministic mapping/collision tests for catalogue-to-institution migration.
- Test repeated imports are idempotent and preserve approved/manual fields unless an explicit update policy allows replacement.
- Test pending/rejected records remain inactive; review approval is audited.
- Test unsupported/manual-parser sources produce partial/blocked status, never a false completed status.
- Test source failures, empty parse results, duplicate source IDs, and changed source rows.
- Verify School Admission can reference the canonical institution without mixing its products/transactions into past-question catalogue data.
- Run the Education feature workflow and all applicable core/addon CI checks on the final commit. No production deployment until the migration and tests are reviewed.
