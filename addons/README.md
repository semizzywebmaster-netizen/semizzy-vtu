# SEMIZZY ONE Addon Architecture

## Non-negotiable layout

Each installable addon is a **direct child** of this directory:

```text
addons/
  ads.monetization/
  education/
  events-entertainment/
  forex-digital-assets/
  marketplace.commerce/
  smm.services/
  vtu.digital-services/
  ...
```

Do not place installable addon packages inside category subdirectories unless the Core addon discovery, installer, migration loader, route loader, and upgrade/uninstall flows are deliberately upgraded and tested for recursive discovery first. The current flat package layout avoids silently missing addons during discovery or upgrades.

## Package ownership

Each addon owns its package-specific files wherever supported by the current addon loader:

- `manifest.php` — stable identifier, version, compatibility, dependencies, permissions, navigation, migrations, and lifecycle metadata.
- `src/` — addon-specific PHP classes and controllers.
- `routes/` — addon-specific routes.
- `database/migrations/` — addon-owned schema changes.
- `resources/` — addon-owned views, translations, and package assets where the loader/build supports them.
- `tests/` — addon-specific tests where practical.
- `README.md` — purpose, dependencies, installation/upgrade notes, permissions, and limitations.

Keep one installable addon per package directory. Do not copy Core models, authentication, wallet/ledger, provider engine, or shared UI components into individual addons.

## Categories

Use category metadata in each manifest/catalog rather than nesting package directories. Categories are for admin browsing and documentation; the package identifier and directory name remain stable.

Suggested categories:
- Core services & VTU
- Education & examinations
- Commerce & marketplace
- Advertising & monetization
- Payments, banking & transfers
- Investments & digital assets
- Identity, KYC & compliance
- Communications & messaging
- Business & government services
- Travel, tickets & events
- Rewards & engagement
- Hosting & platform tools

An addon should have one primary category and may declare tags for secondary discovery. Do not change existing identifiers just to rename a category.

## Frontend / Inertia rule

The main Vite entrypoint currently discovers Inertia pages from `resources/js/Pages/**/*.tsx`. Keep page components there until a tested addon-page resolver is introduced. When adding a page for an addon, use a clear feature namespace (for example `Admin/Ads/*`, `Education/*`, or `Marketplace/*`) and keep route names aligned with the page resolver. Do not move these files into addon folders by hand: that can cause production builds to omit pages or break relative imports. Package-local frontend sources can be adopted later as one coordinated change to Vite globbing, TypeScript paths, page-name resolution, and build tests.

## Branching and delivery

All addons live in the same repository. Do not create one Git branch per addon. Use the agreed shared branch workflow, keep addon-specific commits focused, and do not report an addon as production-ready until migrations, permissions, route loading, page resolution, and relevant tests/build checks have been verified.
