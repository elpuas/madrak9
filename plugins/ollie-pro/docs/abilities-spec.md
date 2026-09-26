# Ollie Abilities — Feature Spec

> **Version:** 2.0  
> **Target:** WordPress 7.0 (Abilities API in core)  
> **Plugin:** ollie-pro ≥ 3.2.0  
> **Status:** Phase 1 implemented (8 abilities); Phases 2–4 planned  
> **MCP Transport:** Official MCP Adapter plugin (no custom server)

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Validation Layers](#validation-layers)
4. [Abilities](#abilities)
5. [REST Endpoints](#rest-endpoints)
6. [Preview System](#preview-system)
7. [File Structure](#file-structure)
8. [Constraints Reference](#constraints-reference)
9. [Schema Validation Rules Reference](#schema-validation-rules-reference)
10. [Phased Rollout](#phased-rollout)

---

## Overview

Ollie Abilities brings AI-assisted design capabilities to the Ollie WordPress theme ecosystem. It leverages the WordPress 7.0 Abilities API to expose structured, validated tools that AI agents (via the official MCP Adapter plugin) can use to search patterns, build pages, and manage design tokens — all while enforcing Ollie's design system rules.

### Goals

- Let AI agents safely compose and edit Ollie-powered pages
- Enforce design system consistency via a multi-layer linter
- Provide preview-before-apply workflows for destructive operations
- Ship iteratively: Phase 1 with WP 7.0, later phases as updates

### Key Principles

- **Design token enforcement**: All colors, typography, and spacing must use Ollie design tokens — no arbitrary/hardcoded values
- **Two-step confirmation**: Pattern insertion and replacement require preview → confirm flow
- **Layered validation**: Multiple independent validation layers catch different error classes
- **Theme-adaptive**: Tokens load dynamically from the active theme's `theme.json` via `wp_get_global_settings()`

---

## Architecture

### Component Diagram

```
┌──────────────────────────────────────────────────────┐
│              Ollie_Abilities (Singleton)               │
│  - Bootstraps all components                          │
│  - Registers ability category "ollie-design"          │
│  - Registers 8 abilities on wp_abilities_api_init     │
│  - Registers REST routes on rest_api_init             │
├──────────────────────────────────────────────────────┤
│                                                        │
│  ┌──────────────────┐   ┌───────────────────────┐     │
│  │ Pattern Index     │   │ Design Linter         │     │
│  │ (cloud search +   │   │ (Layer 1 + Layer 2    │     │
│  │  fetch by ID)     │   │  orchestration)       │     │
│  └──────────────────┘   │  ┌───────────────────┐│     │
│                           │  │ Block Validator   ││     │
│  ┌──────────────────┐   │  │ (Layer 2 schema)  ││     │
│  │ Preview Handler   │   │  └───────────────────┘│     │
│  │ (transient-based) │   └───────────────────────┘     │
│  └──────────────────┘                                  │
│                                                        │
│  ┌──────────────────────────────────────────────────┐  │
│  │ 8 Abilities                                       │  │
│  │ manage-patterns, manage-global-styles,            │  │
│  │ manage-pages, manage-content, manage-blocks,      │  │
│  │ manage-navigation, manage-templates, create-page  │  │
│  └──────────────────────────────────────────────────┘  │
│                                                        │
│  ┌──────────────────────────────────────────────────┐  │
│  │ Global Styles Helper (shared utilities)           │  │
│  │ - Read/write wp_global_styles CPT                 │  │
│  └──────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────┘

                        ▼

┌──────────────────────────────────────────────────────┐
│          MCP Adapter Plugin (third-party)              │
│  - Exposes registered abilities as MCP tools          │
│  - Streamable HTTP transport                          │
│  - Endpoint: /wp-json/mcp/mcp-adapter-default-server  │
│  - Auth: WP Application Passwords (Basic Auth)        │
└──────────────────────────────────────────────────────┘
```

### Transport

MCP transport is handled by the official **MCP Adapter** plugin. Ollie only registers abilities via the WordPress Abilities API — the adapter exposes them to AI tools.

| Endpoint | Auth |
|----------|------|
| `POST /wp-json/mcp/mcp-adapter-default-server` | WP Application Passwords (Basic Auth) |

### Namespace

All classes live under `olpo\Abilities` with sub-namespaces:

- `olpo\Abilities` — Core (`Ollie_Abilities`, `Ollie_Pattern_Index`)
- `olpo\Abilities\Validators` — `Ollie_Block_Validator`, `Ollie_Design_Linter`
- `olpo\Abilities\Preview` — `Ollie_Preview_Handler`
- `olpo\Abilities\Abilities` — All 8 ability classes + `Global_Styles_Helper`

### Bootstrap

The module is loaded from `ollie-pro.php` with an Abilities API feature gate:

```php
if ( olpo\Extensions_Handler::is_extension_enabled_static( 'abilities' ) ) {
    if ( function_exists( 'wp_register_ability' ) || has_action( 'wp_abilities_api_init' ) !== false ) {
        require_once OLPO_PATH . '/inc/abilities/class-ollie-abilities.php';
        olpo\Abilities\Ollie_Abilities::get_instance();
    }
}
```

> **Note:** The official MCP Adapter plugin is required for AI tools to connect. Ollie registers abilities on the WordPress Abilities API; the MCP Adapter handles protocol transport.

---

## Validation Layers

The linter uses a layered architecture. Each layer catches a different class of issues and can be run independently.

| Layer | Name                 | Phase | Description                                                              |
|-------|----------------------|-------|--------------------------------------------------------------------------|
| 1     | Mutation Constraints | P1 ✅ | Ensures colors, type, and spacing use design tokens, not arbitrary values |
| 2     | Schema Validation    | P1 ✅ | Block grammar, registered blocks, attribute types, nesting depth          |
| 3     | Design Rules         | P2    | Contrast ratios, heading hierarchy, section rhythm, image alt text        |
| 4     | Visual QA            | P3    | Screenshot comparison, layout overflow detection, responsive checks       |
| 5     | Preview              | P1 ✅ | Transient-based rendered preview with auth token                          |

### Layer 1 — Mutation Constraints (Phase 1)

Enforced by `Ollie_Design_Linter`. Tokens are loaded dynamically from the active theme's `theme.json`.

| ID   | Rule                            | Severity | Autofix          |
|------|---------------------------------|----------|------------------|
| C-01 | Colors must be design tokens    | error    | `map-to-nearest` |
| C-02 | Font sizes must use type scale  | error    | `snap-to-scale`  |
| C-03 | Spacing must use spacing scale  | error    | `snap-to-scale`  |

**C-01** checks:
- Named color attributes (`backgroundColor`, `textColor`, `gradient`) against registered palette slugs
- Inline `style.color.*` values — `var:preset|color|*` references are allowed; raw `#hex`, `rgb()`, `hsl()` are flagged

**C-02** checks:
- Named `fontSize` attribute against registered font size slugs
- Inline `style.typography.fontSize` — `var:preset|font-size|*` references are allowed; arbitrary values are flagged

**C-03** checks:
- `style.spacing.padding.*`, `style.spacing.margin.*`, `style.spacing.blockGap` — `var:preset|spacing|*` references are allowed; arbitrary values are flagged

### Layer 2 — Schema Validation (Phase 1)

Enforced by `Ollie_Block_Validator`. See [Schema Validation Rules Reference](#schema-validation-rules-reference) for the full rule table.

### Layer 3 — Design Rules (Phase 2, planned)

Higher-level design quality rules:

- Contrast ratio compliance (WCAG AA minimum)
- Heading hierarchy (no skipped levels, e.g. H1 → H3)
- Section rhythm and visual balance
- Image alt text presence
- Button/link accessibility requirements

### Layer 4 — Visual QA (Phase 3, planned)

Automated visual regression and layout checks:

- Screenshot comparison against reference renders
- Layout overflow detection
- Responsive breakpoint validation
- Visual diff scoring

### AI Vision Integration (Phase 4, planned)

- Screenshot → AI model pipeline for subjective quality assessment
- Design consistency scoring
- Suggested improvements based on visual analysis

---

## Abilities

All 8 abilities are registered under the `ollie-design` category and require appropriate capabilities. Each ability uses an `action` parameter to support multiple operations within a single tool, keeping the tool count manageable.

### 1. `ollie/search-patterns`

Semantic vector search against the Ollie cloud pattern library. Uses OpenAI `text-embedding-3-small` embeddings and cosine-similarity matching via a Supabase edge function (`pattern-search`). Returns patterns ranked by similarity score.

| Parameter         | Type    | Required | Default | Description                                                        |
|-------------------|---------|----------|---------|--------------------------------------------------------------------|
| `query`           | string  | **Yes**  | —       | Free-text search prompt (e.g. "dark hero section with CTA button") |
| `limit`           | integer | No       | `5`     | Max results (1–100)                                                |
| `include_content` | boolean | No       | `true`  | Include full pattern block markup in results                       |
| `threshold`       | number  | No       | `0.5`   | Minimum similarity score (0–1)                                     |

**Output:** Object with `success`, `query`, `total_results`, `search_method` (`semantic_vector_search`), and `patterns` array. Each pattern contains `id`, `slug`, `title`, `content` (if requested), `meta` (`description`, `keywords`), `categories`, `collections`, and `similarity_score`.

**Implementation:** `Ollie_Pattern_Index::cloud_search()` calls the Supabase edge function at `https://vttiicmlzxzxrcyyewfn.supabase.co/functions/v1/pattern-search` via `wp_remote_post()` with the Supabase anon key for authorization. The edge function generates an embedding for the prompt, then performs an RPC call to `match_patterns_semantic` in the vector DB.

> **Note:** The local `search()` method (WP_Block_Patterns_Registry) is still available for offline/fallback use but is no longer the default for the `search-patterns` ability.

---

### 2. `ollie/apply-pattern`

Insert a block pattern into a page at a specified position. Two modes: pass `pattern_id` + `pattern_title` from `search-patterns` results (recommended — content is fetched server-side, avoiding large payloads through the MCP bridge), or pass `content` directly for small/custom patterns. Two-step preview/confirm flow.

| Parameter        | Type    | Required | Description                                                                     |
|------------------|---------|----------|---------------------------------------------------------------------------------|
| `pattern_id`     | integer | No*      | Cloud pattern ID from `search-patterns` results. Content fetched server-side.    |
| `pattern_title`  | string  | No*      | Pattern title from `search-patterns`. Used with `pattern_id` to locate pattern.  |
| `content`        | string  | No*      | Raw block markup to insert. Alternative to `pattern_id` for small/custom patterns.|
| `post_id`        | integer | Yes      | Target post/page ID                                                              |
| `position`       | string  | No       | `append` (default), `prepend`, or `after:{block_index}`                          |
| `confirm`        | boolean | No       | Set `true` to apply after previewing                                             |
| `preview_token`  | string  | No       | Token from preview step (required when `confirm` is true)                        |

> *Either `pattern_id` (with `pattern_title`) or `content` is required.

**Flow:**
1. First call (no `confirm`): Fetches content (if using `pattern_id`), auto-fixes block markup, validates via linter, generates preview URL + token
2. Second call (`confirm: true` + `preview_token`): Applies the pattern to the page content

**Auto-fix:** Before validation, markup is run through `Ollie_Block_Validator::sanitize_block_markup()` which fixes duplicate anchor IDs, strips unknown attributes, and cleans up whitespace. Applied fixes are returned in the `auto_fixes` array.

**Response:** Markup in the response is truncated to 500 characters to keep MCP payloads small.

---

### 3. `ollie/replace-pattern`

Replace a top-level section in a page with a different block pattern. Same two modes as `apply-pattern`: pass `pattern_id` + `pattern_title` (recommended) or `content` directly. Two-step preview/confirm flow.

| Parameter        | Type    | Required | Description                                                                     |
|------------------|---------|----------|---------------------------------------------------------------------------------|
| `pattern_id`     | integer | No*      | Cloud pattern ID from `search-patterns` results. Content fetched server-side.    |
| `pattern_title`  | string  | No*      | Pattern title from `search-patterns`. Used with `pattern_id` to locate pattern.  |
| `content`        | string  | No*      | Raw block markup for replacement. Alternative to `pattern_id`.                   |
| `post_id`        | integer | Yes      | Post/page ID containing the section                                              |
| `section_index`  | integer | Yes      | Zero-based index of the top-level block to replace                               |
| `confirm`        | boolean | No       | Set `true` to apply after previewing                                             |
| `preview_token`  | string  | No       | Token from preview step                                                          |

> *Either `pattern_id` (with `pattern_title`) or `content` is required.

**Flow:** Same two-step as `apply-pattern`. Uses `list-page-sections` to identify section indexes. Includes same auto-fix and markup truncation behavior.

---

### 4. `ollie/list-page-sections`

Returns top-level blocks (sections) of a page with index, type, and summary. Use before `replace-pattern`.

| Parameter | Type    | Required | Description          |
|-----------|---------|----------|----------------------|
| `post_id` | integer | Yes      | Post/page ID to inspect |

**Output:** Array of section objects with `index`, `blockName`, `summary` (first heading or content snippet).

---

### 5. `ollie/set-color-palette`

Update one or more color palette tokens in global styles.

| Parameter | Type   | Required | Description                                                |
|-----------|--------|----------|------------------------------------------------------------|
| `colors`  | object | Yes      | Map of slug → hex value (e.g. `{"primary": "#FF5733"}`)    |

**Behavior:** Only specified colors are updated; unmentioned tokens remain unchanged. Writes to the `wp_global_styles` CPT via `Global_Styles_Helper`.

---

### 6. `ollie/set-typography`

Update typography settings in global styles.

| Parameter       | Type   | Required | Description                                        |
|-----------------|--------|----------|----------------------------------------------------|
| `heading_font`  | string | No       | Font family slug for headings                      |
| `body_font`     | string | No       | Font family slug for body text                     |
| `font_sizes`    | array  | No       | Complete font size scale (slug, name, size per item)|

**Behavior:** Partial updates — only provided fields are changed.

---

### 7. `ollie/set-spacing-scale`

Update the spacing scale in global styles.

| Parameter | Type  | Required | Description                                           |
|-----------|-------|----------|-------------------------------------------------------|
| `scale`   | array | Yes      | Full spacing scale array (slug, name, size per step)  |

**Behavior:** Replaces the entire spacing scale.

---

### 8. `ollie/get-current-styles`

Read current design tokens and style settings from the active theme.

| Parameter  | Type  | Required | Description                                              |
|------------|-------|----------|----------------------------------------------------------|
| `sections` | array | No       | Limit to specific sections: `colors`, `typography`, `spacing`, `layout`. Omit for all. |

**Output:** Object with requested sections containing current palette, font families, font sizes, spacing scale, and layout dimensions.

---

## Phase 2 — Abilities (P2)

### 9. `ollie/create-page`

Creates a new WordPress page with optional content, status, template, and parent.

| Parameter   | Type    | Required | Default  | Description                                    |
|-------------|---------|----------|----------|------------------------------------------------|
| `title`     | string  | **Yes**  | —        | Page title                                     |
| `content`   | string  | No       | `""`     | Block markup content                           |
| `status`    | string  | No       | `draft`  | Post status (publish, draft, pending, private) |
| `template`  | string  | No       | `""`     | Page template slug (e.g. "page-no-title")      |
| `parent_id` | integer | No       | `0`      | Parent page ID for hierarchical pages          |

**Output:** `post_id`, `title`, `status`, `edit_url`, `view_url`

---

### 10. `ollie/list-pages`

Lists WordPress pages with filtering and searching.

| Parameter  | Type    | Required | Default | Description                        |
|------------|---------|----------|---------|------------------------------------|
| `status`   | string  | No       | `any`   | Filter by post status              |
| `search`   | string  | No       | `""`    | Search pages by title              |
| `per_page` | integer | No       | `50`    | Number of pages to return (1–100)  |

**Output:** `total`, `pages[]` with `id`, `title`, `slug`, `status`, `template`, `parent_id`, `edit_url`, `view_url`

---

### 11. `ollie/get-page-content`

Returns the full block markup content and metadata of a page or post.

| Parameter | Type    | Required | Description          |
|-----------|---------|----------|----------------------|
| `post_id` | integer | **Yes**  | Post/page ID to read |

**Output:** `post_id`, `title`, `slug`, `status`, `type`, `template`, `content` (raw block markup), `edit_url`

---

### 12. `ollie/update-page-content`

Updates content, title, and/or status of an existing page or post.

| Parameter | Type    | Required | Description                                    |
|-----------|---------|----------|------------------------------------------------|
| `post_id` | integer | **Yes**  | Post/page ID to update                         |
| `content` | string  | No       | New block markup (replaces entire post content) |
| `title`   | string  | No       | New page title                                 |
| `status`  | string  | No       | New post status                                |

**Output:** `post_id`, `title`, `status`, `updated[]`, `edit_url`

---

### 13. `ollie/read-theme-json`

Returns the full merged theme.json data with optional section filtering.

| Parameter | Type   | Required | Default | Description                                   |
|-----------|--------|----------|---------|-----------------------------------------------|
| `section` | string | No       | `all`   | Section to return (e.g. "settings.color", "styles.typography") |

**Output:** `section`, `data` (the requested theme.json subtree)

---

### 14. `ollie/update-theme-json`

Deep-merges data into the global styles (user-level theme.json overrides).

| Parameter | Type   | Required | Description                                                          |
|-----------|--------|----------|----------------------------------------------------------------------|
| `data`    | object | **Yes**  | Partial theme.json with `settings` and/or `styles` to deep-merge     |

**Output:** `success`, `merged[]` (list of top-level keys merged)

**Implementation:** Reads existing `wp_global_styles` CPT content, deep-merges incoming data (indexed arrays like palettes are replaced entirely, associative arrays merge recursively), writes back.

---

### 15. `ollie/list-starter-sites`

Lists all available Ollie starter sites.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| *(none)*  | —    | —        | —           |

**Output:** `total`, `sites[]` with `id`, `title`, `description`, `demo_url`, `features[]`, `pro` (boolean)

---

### 16. `ollie/list-css-classes`

Lists all custom CSS classes from the Ollie CSS Manager.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| *(none)*  | —    | —        | —           |

**Output:** `total`, `classes[]` with `className`, `css`, `hoverCss`, `focusCss`, `activeCss`, `disabledCss`, `loadGlobal`

---

### 17. `ollie/manage-css-class`

Creates, updates, or deletes a custom CSS class.

| Parameter     | Type    | Required | Default | Description                                       |
|---------------|---------|----------|---------|---------------------------------------------------|
| `action`      | string  | No       | `save`  | "save" (create/update) or "delete"                |
| `className`   | string  | **Yes**  | —       | CSS class name                                    |
| `css`         | string  | No       | `""`    | CSS rules for normal state                        |
| `hoverCss`    | string  | No       | `""`    | CSS rules for :hover state                        |
| `focusCss`    | string  | No       | `""`    | CSS rules for :focus state                        |
| `activeCss`   | string  | No       | `""`    | CSS rules for :active state                       |
| `disabledCss` | string  | No       | `""`    | CSS rules for :disabled state                     |
| `loadGlobal`  | boolean | No       | `false` | Load class globally on all pages                  |

**Output:** `success`, `action` (created/updated/deleted), `className`

---

### 18. `ollie/manage-navigation`

Lists, reads, creates, or updates WordPress navigation menus (wp_navigation CPT).

| Parameter | Type    | Required | Description                                                             |
|-----------|---------|----------|-------------------------------------------------------------------------|
| `action`  | string  | **Yes**  | "list", "get", "create", or "update"                                    |
| `nav_id`  | integer | No       | Navigation post ID (required for "get" and "update")                    |
| `title`   | string  | No       | Menu title (required for "create")                                      |
| `content` | string  | No       | Block markup with navigation-link, navigation-submenu, mega-menu blocks |

**Output:** Varies by action. Includes `mega_menu_available` boolean indicating if Ollie Menu Designer is active.

---

### 19. `ollie/update-block`

Updates a specific block within a post by its index path.

| Parameter    | Type    | Required | Description                                                              |
|--------------|---------|----------|--------------------------------------------------------------------------|
| `post_id`    | integer | **Yes**  | Post/page ID containing the block                                        |
| `index_path` | array   | **Yes**  | Array of zero-based indices navigating through nested blocks             |
| `attributes` | object  | No       | Block attributes to merge (e.g. ollieHoverColor, ollieAnimation, className) |
| `inner_html` | string  | No       | Replace block's inner HTML content                                       |

**Output:** `success`, `post_id`, `block_name`, `updated[]`

**Usage:** Use `list-page-sections` or `get-page-content` to identify block positions, then `list-extensions` to discover available Ollie Pro attributes.

---

### 20. `ollie/list-extensions`

Lists all available Ollie Pro extensions with their enabled status and block attributes.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| *(none)*  | —    | —        | —           |

**Output:** `total`, `extensions[]` with `id`, `label`, `description`, `enabled`, `block_attrs[]`

---

### 21. `ollie/manage-templates`

Lists, reads, or updates WordPress block templates and template parts.

| Parameter     | Type    | Required | Default            | Description                                    |
|---------------|---------|----------|--------------------|------------------------------------------------|
| `action`      | string  | **Yes**  | —                  | "list", "get", or "update"                     |
| `type`        | string  | No       | `wp_template_part` | "wp_template" or "wp_template_part"            |
| `template_id` | integer | No       | —                  | Template post ID (required for "get"/"update") |
| `slug`        | string  | No       | —                  | Template slug (alternative for "get")          |
| `content`     | string  | No       | —                  | New block markup (required for "update")       |

**Output:** Varies by action. Template objects include `id`, `slug`, `title`, `content`, `area`.

---

## REST Endpoints

### `POST /wp-json/ollie/v1/lint`

Standalone lint endpoint for validating block markup outside ability calls.

| Parameter | Type   | Required | Description          |
|-----------|--------|----------|----------------------|
| `markup`  | string | Yes      | Block markup to lint |

**Permission:** `edit_posts` capability required.

**Response:**
```json
{
  "valid": true|false,
  "issues": [
    {
      "id": "C-01",
      "severity": "error|warning",
      "message": "Human-readable description",
      "autofix": "map-to-nearest|snap-to-scale|coerce|regenerate|placeholder|"
    }
  ],
  "layers": {
    "layer1": { "name": "Mutation constraints", "passed": true, "count": 0 },
    "layer2": { "name": "Schema validation", "passed": true, "count": 0 }
  }
}
```

---

## Preview System

The preview system (`Ollie_Preview_Handler`) provides a safe way to preview changes before committing them.

### Flow

1. **Generate:** Ability creates preview content, calls `create_preview()` which stores markup + metadata in a WordPress transient
2. **Token:** Returns a unique auth token + preview URL
3. **View:** Frontend request with `?ollie_preview={token}` triggers `template_redirect` hook, validates token, renders full-page preview using active theme
4. **Expiry:** Transients expire after 5 minutes
5. **Confirm:** If the AI agent sends `confirm: true` with the `preview_token`, the change is applied to the actual post content

### Security

- Auth token required — random string generated per preview
- Transient-based storage — auto-expires, no persistent DB pollution
- User must have `edit_posts` capability
- Preview is read-only; changes only applied on explicit confirmation

---

## File Structure

```
inc/mcp/
├── class-ollie-mcp.php                              # Main loader / singleton
├── class-ollie-mcp-server.php                       # Self-contained MCP server (stdio + HTTP transports)
├── class-ollie-mcp-setup.php                        # MCP setup REST endpoints (app password creation)
├── class-ollie-pattern-index.php                    # Pattern search (cloud + local fallback)
├── abilities/
│   ├── class-ollie-global-styles-helper.php         # Shared wp_global_styles CPT utilities
│   ├── class-ollie-ability-search-patterns.php      # ollie/search-patterns (P1)
│   ├── class-ollie-ability-apply-pattern.php        # ollie/apply-pattern (P1, two-step)
│   ├── class-ollie-ability-replace-pattern.php      # ollie/replace-pattern (P1, two-step)
│   ├── class-ollie-ability-list-page-sections.php   # ollie/list-page-sections (P1)
│   ├── class-ollie-ability-set-color-palette.php    # ollie/set-color-palette (P1)
│   ├── class-ollie-ability-set-typography.php       # ollie/set-typography (P1)
│   ├── class-ollie-ability-set-spacing-scale.php    # ollie/set-spacing-scale (P1)
│   ├── class-ollie-ability-get-current-styles.php   # ollie/get-current-styles (P1)
│   ├── class-ollie-ability-create-page.php          # ollie/create-page (P2)
│   ├── class-ollie-ability-list-pages.php           # ollie/list-pages (P2)
│   ├── class-ollie-ability-get-page-content.php     # ollie/get-page-content (P2)
│   ├── class-ollie-ability-update-page-content.php  # ollie/update-page-content (P2)
│   ├── class-ollie-ability-read-theme-json.php      # ollie/read-theme-json (P2)
│   ├── class-ollie-ability-update-theme-json.php    # ollie/update-theme-json (P2)
│   ├── class-ollie-ability-list-starter-sites.php   # ollie/list-starter-sites (P2)
│   ├── class-ollie-ability-list-css-classes.php     # ollie/list-css-classes (P2)
│   ├── class-ollie-ability-manage-css-class.php     # ollie/manage-css-class (P2)
│   ├── class-ollie-ability-manage-navigation.php    # ollie/manage-navigation (P2)
│   ├── class-ollie-ability-update-block.php         # ollie/update-block (P2)
│   ├── class-ollie-ability-list-extensions.php      # ollie/list-extensions (P2)
│   └── class-ollie-ability-manage-templates.php     # ollie/manage-templates (P2)
├── validators/
│   ├── class-ollie-block-validator.php              # Layer 2 — schema validation
│   └── class-ollie-design-linter.php                # Layer 1 + orchestrator + REST callback
└── preview/
    └── class-ollie-preview-handler.php              # Transient-based preview with auth token
```

---

## Constraints Reference

Constraints are enforced rules that AI-generated markup must satisfy.

| ID   | Layer | Description                                 | Severity | Autofix          |
|------|-------|---------------------------------------------|----------|------------------|
| C-01 | 1     | Colors must reference design tokens          | error    | `map-to-nearest` |
| C-02 | 1     | Font sizes must use the type scale           | error    | `snap-to-scale`  |
| C-03 | 1     | Spacing must use the spacing scale           | error    | `snap-to-scale`  |
| C-07 | 2     | Only registered block types allowed          | error    | —                |
| C-08 | 2     | Max nesting depth of 4 levels                | error    | —                |

---

## Schema Validation Rules Reference

Schema rules validate structural correctness of block markup.

| ID   | Layer | Description                                          | Severity | Autofix       |
|------|-------|------------------------------------------------------|----------|---------------|
| S-01 | 2     | Block markup must parse (valid block grammar)        | error    | —             |
| S-02 | 2     | Required attributes must be present                  | error    | —             |
| S-03 | 2     | Attribute types must match block.json schema         | error    | `coerce`      |
| S-04 | 2     | Blocks without innerBlock support must have none     | error    | —             |
| S-05 | 2     | Inner block types must be in allowedBlocks           | error    | —             |
| S-06 | 2     | No duplicate anchor/ID values                        | warning  | `regenerate`  |
| S-08 | 2     | Required-content blocks must not be empty            | warning  | `placeholder` |

---

## Phased Rollout

| Phase | Target    | Layers  | Description                                          |
|-------|-----------|---------|------------------------------------------------------|
| P1    | WP 7.0    | 1, 2, 5 | 8 core abilities (patterns, styles), mutation constraints, schema validation, preview system |
| P2    | WP 7.0    | —       | 13 additional abilities (pages, theme.json, CSS manager, navigation, blocks, templates, extensions, starter sites) |
| P3    | Post-7.0  | 3       | Design rules (contrast, heading hierarchy, rhythm)   |
| P4    | Post-7.0  | 4       | Visual QA (screenshots, overflow, responsive)        |
| P5    | Post-7.0  | —       | AI vision integration for subjective quality scoring |
