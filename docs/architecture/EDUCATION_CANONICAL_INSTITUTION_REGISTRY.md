# Canonical Education Institution Registry

## Decision

`education_institutions` is the canonical institution identity for Education sync/import and future academic-unit/programme relationships. `education_reference_catalogue` remains the compatibility catalogue for exam bodies, exam types and legacy school references; it is not deleted or renamed in this change.

The separate `school_admission_institutions` table from the School Admission proposal is not ported. School Admission may retain its own programmes, application products and transaction records, but it must reference this canonical institution entity in a later, staged migration instead of creating a second institution master.

## Migration behavior

- Creates the canonical table with stable code/source identity, institution attributes, provenance, review state, sync timestamp, soft deletes and metadata.
- Backfills existing `kind=school` catalogue entries with deterministic codes and `source_key=catalogue:<catalogue_key>`.
- Keeps every legacy catalogue row intact for compatibility and rollback.
- Carries review state through when the source catalogue has review columns; otherwise current curated catalogue rows are treated as approved to preserve existing behavior.
- New sync/import records default to pending and inactive. No provider feed can auto-activate a record.
- Approved/admin-reviewed records are not overwritten by later feed payloads. Rejected records stay inactive and rejected. Repeated imports match external IDs first and then deterministic identity keys.

## Boundaries

This slice does not yet enable an official-source HTTP crawler or automatic catalogue activation. Source-specific parsers must be added only with fixtures, explicit pagination/completeness checks, minimum-result safeguards, and tests that represent unsupported parsers as partial/manual-review outcomes—not successful full syncs. No source should be marked fully synchronized merely because an adapter returned a result array.

## Validation required

- Education addon CI and core PHP/frontend/MySQL CI on this branch.
- Backfill tests preserving old catalogue rows and mapping all school rows.
- Idempotent sync tests, pending/inactive enforcement, approved-field preservation and rejected-record preservation.
- A later School Admission compatibility migration must use nullable canonical foreign keys during transition and preserve existing admission product/transaction IDs.
