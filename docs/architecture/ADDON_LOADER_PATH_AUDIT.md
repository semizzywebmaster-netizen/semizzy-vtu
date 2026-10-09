# Addon Loader and Hard-Coded Path Audit

**Branch:** `chore/addon-category-reorganization`  
**Scope:** Read-only code audit for safe category-based addon moves.  
**Status:** Findings recorded; physical addon directories remain unmoved.

## Confirmed loader and path assumptions

### 1. Manifest discovery and PHP autoloading
File: `app/Services/Addons/AddonRegistry.php`

- The category-aware change now discovers legacy `addons/*/manifest.php` and one-level nested `addons/<category>/<addon>/manifest.php`.
- `source` remains the legacy directory basename. `source_path` is the normalized path relative to `addons/`.
- The addon autoloader uses `source_path` where present and confirms resolved source directories remain inside the addon root.
- The discovery depth is intentionally limited to one category level. Do not create deeper category nesting without an explicit requirement and tests.

### 2. Route loading
File: `app/Services/Addons/AddonRouteRegistrar.php`

- Core web and API route files are registered through the generic registrar from `routes/web.php` and `routes/api.php`.
- Manifests currently store route file paths as literal `addons/<legacy-folder>/routes/...` values.
- The registrar rejects paths outside `addons/` and paths containing `..`, but it does not yet resolve the real path and verify that a symlink cannot escape the addon root.
- Moving a directory without updating its manifest route declarations will cause route registration to fail because the declared file no longer exists.
- The registrar currently reads only `web_route_files` and `api_route_files`; additional route declarations (for example console or trading routes) need an explicit loading contract before being assumed to register automatically.

### 3. Migration loading
File: `app/Services/Addons/AddonLifecycleService.php`

- Migration declarations are treated as migration filenames; directory information in a declaration is discarded with `basename()`.
- The lookup checks `addons/*/database/migrations/<filename>`, then falls back to `database/migrations/<filename>`.
- It rejects ambiguous filenames if multiple flat addon folders contain the same filename.
- This lookup cannot discover migrations under `addons/<category>/<addon>/database/migrations/`.
- VTU's current declared migration filenames are in the application-level `database/migrations/` location, and the dedicated VTU installer also references that legacy location. Preserve these filenames and the installed migration history; do not relocate or rerun production migrations as part of folder organization.

### 4. Installer hooks and service-provider wiring
Files: `app/Services/Addons/AddonLifecycleService.php`, `app/Providers/AppServiceProvider.php`

- Installer hooks are class names stored in manifests and resolved through Laravel's container; folder moves must not change these stable class names or make their classes undiscoverable.
- `AppServiceProvider` invokes the registry's autoload registration and discovers commercial adapters through manifests. Adapter class loading depends on the autoloader path being correct.
- Core has optional explicit imports for some integrations/observers. These should be reviewed individually; not every integration is automatically controlled by addon manifest discovery.

### 5. Admin registry and lifecycle
Files: `app/Http/Controllers/Admin/AddonController.php`, `app/Services/Addons/AddonLifecycleService.php`

- The admin registry lists manifests discovered from disk and stores the manifest data in the addon database record.
- The lifecycle service validates the manifest and applies migrations through the filename lookup above.
- Keep addon identifiers, permissions, navigation URLs, dependency declarations, version history, and lifecycle records unchanged when changing physical paths.

### 6. Legacy VTU installer
File: `app/Services/Vtu/VtuAddonInstaller.php`

- Its compatibility entry point calls migrations using `database/migrations/<filename>`.
- When invoked through the generic lifecycle, it bootstraps the catalogue after the generic migration step.
- Preserve the existing fallback contract for direct installer calls until callers and tests have been migrated deliberately.

## Required implementation sequence before moving folders

1. Make route declarations path-aware: resolve declared paths relative to a manifest's validated `source_path`, while supporting legacy absolute-from-project-root `addons/...` declarations during transition.
2. Harden route path checks with `realpath()` containment so symlinks cannot escape the addon root.
3. Make migration resolution support flat and categorized addon migration directories, reject unsafe paths, and preserve current root-level migration fallback.
4. Add tests for nested route loading, missing route files, traversal/symlink escapes, flat and nested migration discovery, duplicate migration names, and the current VTU root-level migration contract.
5. Add an audit/validation command that reports each manifest, category, source path, missing declared files, duplicate IDs, migration ambiguity, and undeclared route files without changing the database.
6. Run the relevant PHP test suite and all required CI checks. Review failures before moving any addon.
7. Move one low-risk addon at a time, update its manifest paths and tests in the same commit, and retain a reversible compatibility path during the migration window.

## Explicitly not done in this audit

- No addon directories moved.
- No database migrations or production data changed.
- No addon identifiers, permissions, routes, or migration filenames renamed.
- No claim that tests pass; automated checks must complete before merge.
