# Read-only addon structure audit

The Core command `php artisan addons:audit` checks the discovered flat and one-level categorized addon manifests without changing addon state or database records.

## Run

```bash
php artisan addons:audit
php artisan addons:audit --json
```

The command reports:
- Missing required manifest fields and duplicate identifiers.
- Unsafe route declarations, missing route files, and routes that resolve outside `addons/`.
- Invalid, missing, ambiguous, or multiply-declared migration filenames.
- Missing/unresolved dependency manifests and installer classes that are not autoloadable.
- Top-level directories containing nested folders but no root manifest, which require manual classification.

Exit status is non-zero when errors are found. Warnings require review but do not by themselves fail the audit. The JSON output includes a manifest count, error and warning counts, and a structured issue list.

## Safety and interpretation

- This is a diagnostic report only. It does not move directories, rewrite manifests, run migrations, install/activate addons, or alter production data.
- PHP manifests are loaded to inspect their returned arrays, so only run this against trusted repository code.
- A dependency absent from the addon registry may be an intentional Core capability; review warnings in context.
- A migration found in the application-level `database/migrations` directory is reported as present to preserve the existing VTU installer contract.
- The audit is not a substitute for automated tests, staging validation, or reviewing route/migration behavior before a physical folder move.
