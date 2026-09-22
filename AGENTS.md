--------------------------------------------------
AGENT
--------------------------------------------------
  
  You are an expert in WordPress, PHP, and related web development technologies.
  
  Key Principles
  - Write concise, technical responses with accurate PHP examples.
  - Follow WordPress coding standards and best practices.
  - Use object-oriented programming when appropriate, focusing on modularity.
  - Prefer iteration and modularization over duplication.
  - Use descriptive function, variable, and file names.
  - Use lowercase with hyphens for directories (e.g., wp-content/themes/my-theme).
  - Favor hooks (actions and filters) for extending functionality.
  
  PHP/WordPress
  - Use PHP 7.4+ features when appropriate (e.g., typed properties, arrow functions).
  - Follow WordPress PHP Coding Standards.
  - Use strict typing when possible: declare(strict_types=1);
  - Utilize WordPress core functions and APIs when available.
  - File structure: Follow WordPress theme and plugin directory structures and naming conventions.
  - Implement proper error handling and logging:
    - Use WordPress debug logging features.
    - Create custom error handlers when necessary.
    - Use try-catch blocks for expected exceptions.
  - Use WordPress's built-in functions for data validation and sanitization.
  - Implement proper nonce verification for form submissions.
  - Utilize WordPress's database abstraction layer (wpdb) for database interactions.
  - Use prepare() statements for secure database queries.
  - Implement proper database schema changes using dbDelta() function.
  
  Dependencies
  - WordPress (latest stable version)
  - Composer for dependency management (when building advanced plugins or themes)
  
  WordPress Best Practices
  - Use WordPress hooks (actions and filters) instead of modifying core files.
  - Implement proper theme functions using functions.php.
  - Use WordPress's built-in user roles and capabilities system.
  - Utilize WordPress's transients API for caching.
  - Implement background processing for long-running tasks using wp_cron().
  - Use WordPress's built-in testing tools (WP_UnitTestCase) for unit tests.
  - Implement proper internationalization and localization using WordPress i18n functions.
  - Implement proper security measures (nonces, data escaping, input sanitization).
  - Use wp_enqueue_script() and wp_enqueue_style() for proper asset management.
  - Implement custom post types and taxonomies when appropriate.
  - Use WordPress's built-in options API for storing configuration data.
  - Implement proper pagination using functions like paginate_links().
  
  Key Conventions
  1. Follow WordPress's plugin API for extending functionality.
  2. Use WordPress's template hierarchy for theme development.
  3. Implement proper data sanitization and validation using WordPress functions.
  4. Use WordPress's template tags and conditional tags in themes.
  5. Implement proper database queries using $wpdb or WP_Query.
  6. Use WordPress's authentication and authorization functions.
  7. Implement proper AJAX handling using admin-ajax.php or REST API.
  8. Use WordPress's hook system for modular and extensible code.
  9. Implement proper database operations using WordPress transactional functions.
  10. Use WordPress's WP_Cron API for scheduling tasks.
  


--------------------------------------------------
SECTION 1 — Repository Overview
--------------------------------------------------
- This repository is the `wp-content` directory only.
- WordPress core is not versioned here; it is bootstrapped automatically in the Dev Container and linked to this repo’s `wp-content`.
- Development depends on the Dev Container for a consistent environment.

--------------------------------------------------
SECTION 2 — Development Environment Rules
--------------------------------------------------
- The repository MUST be opened using the Dev Container.
- Do not assume local PHP, MySQL, or Node installations.
- All work must assume PHP 8.3 and Node 20 as provided by the Dev Container.

--------------------------------------------------
SECTION 3 — Git Workflow Rules
--------------------------------------------------
Branching:
- Always branch off `main`.
- Branch naming convention (lowercase, hyphen-separated, no spaces):
  - `feature/<short-description>`
  - `fix/<short-description>`
  - `chore/<short-description>`
  - `refactor/<short-description>`
- Avoid vague names like `update` or `changes`.

Commits:
- Use conventional commits:
  - `feat: add block registration`
  - `fix: correct meta query`
  - `chore: update dependencies`
  - `refactor: improve service structure`
- Keep commits atomic.
- No multi-purpose commits.
- Do not include unrelated file changes.

Pull Requests:
- Describe what changed.
- Describe why.
- List commands executed if relevant.

--------------------------------------------------
SECTION 4 — Allowed Commands
--------------------------------------------------
Composer (root):
- `composer install`
- `composer require`
- `composer run lint`
- `composer run fix`
- `composer run stan`
- `composer run test`

Node (theme tooling):
- `npm install`
- `npm run start`
- `npm run build`

Rules:
- Run `composer dump-autoload` when adding PHP classes.
- Never modify lock files unless dependency changes are intentional.
- Never manually download plugins.
- Always use Composer (WPackagist) for plugins.

--------------------------------------------------
SECTION 5 — Coding Standards
--------------------------------------------------
PHP:
- WordPress Coding Standards (WPCS) via PHPCS.
- Short array syntax.
- Prefer namespaced plugin architecture.
- Avoid global function pollution.
- No direct DB queries without preparation.

JavaScript:
- ES6+.
- `const`/`let` only.
- Explicit ternary expressions.
- No `var`.
- Use `@wordpress/scripts` tooling only.

Formatting:
- Tabs.
- No semicolons.
- Single quotes.

--------------------------------------------------
SECTION 6 — Safety Rules
--------------------------------------------------
The agent must never:
- Modify Dev Container configuration unless explicitly instructed.
- Add Docker Compose if not present.
- Install global packages.
- Introduce new tooling without approval.
- Change project structure.

--------------------------------------------------
SECTION 7 — MadraK9 Ecommerce Context
--------------------------------------------------

- MadraK9 is a WooCommerce store for canine products: training equipment, collars, leashes, harnesses, protective equipment, rewards, grooming products, detection and working-dog equipment, food, supplements, and related accessories.
- The catalog is migrating from SpecialK9. Prioritize reliable catalog architecture; accurate product and variation data; product discovery; mobile usability; WCAG 2.1 AA; performance; image optimization; and secure, maintainable code.
- The local WordPress site is `http://localhost:8300`; wp-admin is `http://localhost:8300/wp-admin`.
- WordPress core runs at `/var/www/html`; this repository is its linked `wp-content` directory.
- The Dev Container is defined in `.devcontainer/devcontainer.json`; `.devcontainer/start-wp.sh` starts services; `.devcontainer/wp-setup.sh` bootstraps WordPress; `plugins/` is Composer-managed; `themes/epdc-base/` is the active block theme; its source is `themes/epdc-base/src/`; `src/` is the `EPDC\\` PSR-4 root when present.
- Official WordPress skills are project-local in `.agents/skills/` for Codex discovery and `.codex/skills/` for the WordPress skillpack layout. Source: `https://github.com/WordPress/agent-skills`, commit `d87ee6916e740c7960b6959220c0481a41b320c7`.

--------------------------------------------------
SECTION 8 — Safe Working Agreement
--------------------------------------------------

- Inspect `AGENTS.md`, `git status`, relevant configuration, and affected implementation before changing code.
- Preserve unrelated working-tree changes. Do not revert, reformat, stage, or include them in a commit.
- Audit the current implementation and data paths before broad changes, dependency changes, migrations, performance work, or bulk operations. Prefer the smallest safe change.
- Never commit, push, deploy, release, reset an environment, or import the complete catalog without explicit user approval.
- Do not import products or modify WordPress content during setup, code review, or validation.
- Migration tooling must have immutable source input, source identifiers, idempotency, dry-run support, observability, a small test fixture/sample import, validation, and rollback/cleanup guidance before a full catalog import is proposed.
- Keep credentials, API keys, browser profiles, exports, and tokens out of tracked files. Use environment variables or the supported authentication flow.

--------------------------------------------------
SECTION 9 — Commands and Validation
--------------------------------------------------

Run these inside the Dev Container from the repository root unless stated otherwise:

```bash
curl -fsS http://localhost:8300
wp core version --path=/var/www/html
wp plugin list --path=/var/www/html
wp theme list --path=/var/www/html
composer install
composer dump-autoload
composer run lint
composer run stan
composer run test
cd themes/epdc-base
npm run build
codex mcp list
```

- `composer run fix` changes files; use it only for intentional, in-scope PHP edits.
- WP-CLI commands that write data, including imports, database operations, cache flushing, and plugin/theme activation, require explicit task authorization.
- Source lint, static analysis, or a build does not prove rendered WordPress or WooCommerce behavior. Perform source validation and rendered browser validation.

--------------------------------------------------
SECTION 10 — Browser Tool Responsibilities
--------------------------------------------------

- Playwright MCP: repeatable flows, screenshots, responsive/mobile testing, accessibility-oriented interaction checks, and end-to-end validation.
- Chrome DevTools MCP: browser console errors, network requests, runtime inspection, and performance diagnostics.
- Figma MCP: inspect supplied design context, components, variables, and layout before Figma-based implementation. Figma authentication is user-scoped and must not be stored in this repository.
- Validate customer-facing changes in a real browser at desktop and mobile widths. Check keyboard access, visible focus, semantic heading order, labels and errors, image alt text, critical console/network errors, and affected cart/checkout behavior.

--------------------------------------------------
SECTION 11 — WooCommerce Catalog and Migration
--------------------------------------------------

- Use WooCommerce product types and CRUD/data APIs. Keep product, variation, stock, backorder, price, tax, and visibility data in WooCommerce's supported model; do not create parallel product storage.
- Use global product attributes and WooCommerce taxonomies for shared, filterable dimensions. Preserve source-to-destination mappings for categories, brands, attributes, and related products.
- Treat each variation as a complete sellable record when applicable: stable SKU, selected attribute values, pricing, stock status and quantity, backorder policy, image, weight/dimensions, and source identifier.
- Migration tooling must use a known test dataset and no full-catalog import occurs without explicit approval.
- Validate post-import counts, duplicate SKUs, variation parent/attribute consistency, stock and backorder values, image attachment resolution, category/brand mappings, and related-product links. Record discrepancies rather than guessing.
- Design categories, navigation, search, filters, breadcrumbs, empty states, and product cards for product discovery and mobile use without compromising accessible names, focus order, or keyboard operation.

--------------------------------------------------
SECTION 12 — Quality, Accessibility, and Security
--------------------------------------------------

- Follow WPCS, `phpcs.xml`, PHP 8.0+ compatible syntax, namespaces where appropriate, WordPress hooks and APIs, and WooCommerce CRUD/data APIs.
- Use strict types in new standalone PHP files when compatible with WordPress entry points and project conventions.
- Sanitize and validate input, verify nonces, check capabilities, escape output by context, and use prepared statements for necessary `$wpdb` queries.
- Use WordPress i18n, enqueue APIs, debug logging that excludes customer data and secrets, and idempotent/observable WP-Cron jobs with a manual run path.
- Write modern JavaScript with `const` and `let`; do not use `var`; use `@wordpress/scripts` tooling.
- Meet WCAG 2.1 AA with semantic native controls, keyboard operation, visible focus, associated labels and errors, meaningful alternative text, sufficient contrast, and no color-only status.
- Build mobile-first. Use appropriately sized, responsive images and measure before and after performance changes.
- Never trust client-provided product, price, inventory, order, or privileged action data.

--------------------------------------------------
SECTION 13 — Staging Deployment Scope
--------------------------------------------------

- This repository is a `wp-content` source tree, not a complete WordPress installation.
- The approved staging site is `https://staging2.madrak9.com`.
- The staging WordPress root is `/home/customer/www/staging2.madrak9.com/public_html`.
- The staging deployment destination is `/home/customer/www/staging2.madrak9.com/public_html/wp-content`.
- The repository root maps directly to that remote `wp-content` directory only after a clean deployment artifact has been created.
- Production is outside the authorized deployment scope.
- Preserve unrelated working-tree changes in all deployment preparation work.

--------------------------------------------------
SECTION 14 — Deployment Toolchain and Theme Build
--------------------------------------------------

- Use PHP 8.3, Composer 2, Node.js 20, and npm for deployment preparation.
- The theme npm lockfile is `themes/epdc-base/package-lock.json`.
- Install theme dependencies with:

```bash
cd themes/epdc-base && npm ci
```

- Build production theme assets with:

```bash
cd themes/epdc-base && npm run build
```

- The generated theme build directory is `themes/epdc-base/build/`; it is not tracked and must be produced in CI.
- Verify required runtime build outputs after every build, including `themes/epdc-base/build/index.css`, the editor stylesheet referenced by the theme.

--------------------------------------------------
SECTION 15 — Composer Plugins and Runtime Dependencies
--------------------------------------------------

- Manage WordPress plugins through root Composer. Composer installs WordPress plugins into `plugins/{$name}/`.
- Install production Composer dependencies with:

```bash
composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
```

- WooCommerce 11.1.0 is installed through Composer.
- Current production Composer packages are AI, Create Block Theme, Performance Lab, Query Monitor, and WooCommerce. Do not change a package's dependency classification without explicit authorization.
- No private Composer repository or required repository-level Composer authentication is currently configured.
- Root `vendor/` is not a runtime deployment path. Plugin-owned `vendor/` directories and packaged plugin `build/` directories can be runtime requirements and must be retained.
- There are currently no MU plugins.

--------------------------------------------------
SECTION 16 — Deployment Artifact Requirements
--------------------------------------------------

- CI must export the approved Git commit to a temporary source directory, install production Composer dependencies, run `npm ci` and the production theme build, verify required theme build outputs, and copy only allowlisted runtime files into a separate artifact directory.
- Validate the artifact before synchronization. Use the artifact, never the repository working tree, as the rsync source.
- Runtime deployment paths are:
  - `index.php`
  - `plugins/**`
  - `themes/epdc-base/functions.php`
  - `themes/epdc-base/style.css`
  - `themes/epdc-base/theme.json`
  - `themes/epdc-base/screenshot.png`
  - `themes/epdc-base/parts/**`
  - `themes/epdc-base/templates/**`
  - `themes/epdc-base/styles/**`
  - `themes/epdc-base/build/**`
- The deployment artifact must exclude Git metadata; `.agents/`; `.codex/`; `.devcontainer/`; `.github/`; root `vendor/`; `src/`; tests; documentation; `uploads/`; `upgrade/`; theme source and `node_modules`; package and Composer manifests not required at runtime; development and WP-CLI configuration; `AGENTS.md`; README and Dev Container documentation; source maps; logs; CSV and SQL files; backups and archives; environment files; authentication files; private keys; and certificates.
- Do not use broad `**/vendor/**` or `**/build/**` exclusions: plugin-owned vendor and packaged build directories may be runtime dependencies.
- Do not deploy WordPress core, databases, `wp-config.php`, uploads, credentials, catalog CSVs, backups, caches, or local development artifacts.
- Recheck the completed artifact for symlinks before every deployment.
- Run and review `rsync --dry-run` before every first or materially changed deployment.
- Do not use `rsync --delete` unless Alfredo explicitly authorizes a reviewed deletion policy. Preserve unmanaged remote content and `uploads/`.
- The staging workflow triggers on pushes to `staging` and by manual dispatch. It uses the GitHub `staging` environment.
- `STAGING_DEPLOY_ENABLED` must remain `false` by default. The workflow always performs the dry-run and performs real rsync only when that environment variable is exactly `true`.

--------------------------------------------------
SECTION 17 — Deployment and Catalog Operations
--------------------------------------------------

- Code deployment and WooCommerce catalog import are separate operations. Catalog imports must never run automatically during a code deployment.
- Staging currently contains a clean/default WordPress installation. WooCommerce must be deployed, activated, and verified before catalog import.
- Production access, deployment, DNS changes, database replacement, catalog import, and payment activation each require separate explicit authorization.

--------------------------------------------------
SECTION 18 — Unresolved Deployment Decisions
--------------------------------------------------

- Root `composer.lock` is intentionally no longer ignored and must be included in the CI/CD commit. Do not regenerate it without explicit dependency-change authorization.
- Confirm whether Create Block Theme, Performance Lab, and Query Monitor should remain production Composer dependencies.
- The remote-deletion policy remains undecided. Initial deployments must not use `--delete`.
