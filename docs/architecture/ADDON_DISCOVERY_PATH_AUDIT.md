# Addon Discovery and Path-Coupling Audit — Bulk Action 3

Repository: `semizzywebmaster-netizen/semizzy-vtu`
Branch: `chore/addon-category-reorganization`
Scope: direct inspection of `app/Services/Addons/AddonRegistry.php`, `app/Services/Addons/AddonCapabilityRegistry.php`, `tests/Feature/AddonLifecycleTest.php`, and `tests/Feature/VtuAddonInstallerTest.php`. This is a focused source review, not a complete repository-wide static analysis or test run.

## Confirmed current discovery behavior

### 1. Addon discovery assumes a flat directory layout

`AddonRegistry::all()` sets its root to `base_path('addons')` and scans only:

```php
glob($root . '/*/manifest.php')
```

This finds `addons/<addon-folder>/manifest.php` but does not recursively discover `addons/<category>/<addon-folder>/manifest.php`.

**Impact:** moving addons into category subdirectories before changing discovery would make those addons disappear from the runtime registry. This is a confirmed blocker for physical folder moves.

### 2. The folder name is also used as the runtime source path

After validating a manifest, the registry assigns:

```php
$manifest['source'] = basename(dirname($file));
```

The autoloader then resolves addon source code through:

```php
base_path('addons/' . $source . '/src/')
```

**Impact:** the current `source` value assumes the addon folder is the immediate child of `addons/`. A nested folder needs a relative path such as `education/exam-results`, not just the basename `exam-results`. The implementation must normalize and validate relative paths before using them.

### 3. Manifest route paths are currently explicit strings

The inspected `vtu.digital-services` manifest contains route entries such as `addons/vtu.digital-services/routes/web.php`, `routes/admin.php`, and `routes/api.php`. It also declares migration filenames separately. Its stable identifier is `vtu.digital-services`, and its Core compatibility is `>=2.0.0`.

**Impact:** a folder move may require updating path-bearing manifest fields and any loader that consumes them. Keep the stable addon identifier, route URLs, permission names, migration filenames, and existing database schema unchanged unless a separately reviewed feature explicitly requires otherwise.

### 4. Registry manifest validation is minimal

The inspected `AddonRegistry::validate()` currently checks only that `identifier`, `name`, and `version` are non-empty strings. Invalid manifests are caught and reported, then skipped during discovery.

**Impact:** missing route files, invalid dependencies, duplicate identifiers, unsafe paths, malformed permission arrays, or missing migration files may not be caught by this registry validation. Add a separate diagnostic/CI validator rather than making runtime discovery brittle or silently skipping addons.

### 5. Addon lifecycle tests exist, but they do not prove nested discovery works

The inspected lifecycle feature tests cover dependency constraints, compatibility, duplicate registration, dependency cycles, activation/deactivation guards, updates, uninstall and lifecycle events. They do not demonstrate discovery of nested category paths.

The VTU installer test checks two explicitly named migration paths and confirms catalogue bootstrap stops if a migration fails. It does not establish that a moved VTU directory will be found by the addon registry.

**Impact:** add path-resolution and nested-discovery tests before any physical moves.

## Required design before migration

### A. Separate stable identity from physical source path

- Keep `identifier` as the permanent addon identity.
- Represent the physical addon path as a normalized relative path from the `addons/` root.
- Do not derive physical paths from the identifier, since legacy IDs include dots and may not match future folder names.
- Reject absolute paths, traversal segments (`..`), symlink escapes, and paths resolving outside the addons root.
- Ensure route, migration, and autoload paths resolve only inside the intended addon directory, except for explicitly supported Core paths.

### B. Support both flat and categorized paths during transition

- Discover both legacy `addons/*/manifest.php` and categorized `addons/*/*/manifest.php` layouts during a compatibility period.
- Prefer an explicit, normalized source-relative path in the discovery result.
- Detect duplicate manifest identifiers across both layouts and report a blocking diagnostic; never silently let one overwrite another.
- Keep the legacy flat layout working until every addon has migrated and compatibility tests pass.
- Only remove flat discovery support in a separate, reviewed change after confirming no flat addons remain.

### C. Add an audit command and CI validation

Create a read-only validation command that reports, at minimum:

- Discovered addon folder and stable manifest identifier.
- Manifest parse/validation failures and duplicate IDs.
- Missing declared route, migration, installer, and autoload paths.
- Unsupported or unsafe path values.
- Invalid/missing dependency identifiers and dependency cycles.
- Category policy violations and legacy-path counts.
- Manifest file counts compared with discovered addon counts.

The command should return a non-zero exit code in CI for blocking errors, but should not mutate production data or run migrations.

### D. Regression tests required

1. Existing flat addon manifests remain discoverable.
2. A nested category addon is discoverable and reports the correct relative source path.
3. Two manifests with the same identifier fail validation with a useful diagnostic.
4. Traversal/absolute paths cannot load files outside the addon root.
5. Missing route/migration files are reported before deployment.
6. Addon class autoloading resolves files from nested paths.
7. VTU manifest discovery and route/migration references remain valid.
8. Legacy and nested paths cannot create duplicate registry entries.
9. Category names do not alter stable IDs, permissions, route URLs, or database table names.

## Migration gates

Do not physically move any addon until:
- the nested-discovery implementation is merged and tested;
- the read-only audit command reports all existing addon paths accurately;
- the education directory's purpose and status are resolved;
- all path-bearing manifest fields and code references are mapped;
- CI checks the same rules; and
- the existing VTU regression suite and production build pass.

No runtime source, addon directory, database schema, or production data was changed by this audit document. No tests are claimed to have run as part of this source inspection.
