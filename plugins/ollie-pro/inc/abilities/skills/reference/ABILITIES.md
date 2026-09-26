# Ollie — Abilities Tools & Validation Reference

> The Ollie Abilities are how you read and write WordPress. This file covers the validation linter and every `ollie/*` tool. The spine (SKILL.md) tells you *which* tool to reach for; this file is the detail.

## Guardrails & Validation

Ollie Abilities runs a **two-layer design linter** on all block markup before writing to WordPress.

### Layer 1 — Mutation Constraints
- **C-01**: All colors must reference registered design token slugs (no hardcoded hex/rgb/hsl)
- **C-02**: All font sizes must use the type scale slug or `var:preset|font-size|{slug}`
- **C-03**: All spacing (padding, margin, blockGap) must use `var:preset|spacing|{slug}`

### Layer 2 — Schema Validation
- **S-01**: Block markup must be valid block grammar (parseable by `parse_blocks`)
- **S-02**: Required block attributes must be present
- **S-03**: Attribute types and enum values must be valid
- **S-04**: Blocks that don't support innerBlocks must not contain them
- **S-05**: Inner block types must be valid per `allowedBlocks`
- **S-06**: No duplicate block anchor IDs
- **S-08**: No empty required content fields

If the linter rejects markup, check that all colors, font sizes, and spacing use design tokens. Autofix issues labeled `snap-to-scale` are safe to proceed through.

---

## Abilities Tools Reference

### Available Abilities

These are the currently registered abilities (slash-namespaced as `ollie/*`):

| Ability | Purpose |
|---------|---------|
| `ollie/manage-posts` | CRUD for WordPress posts, pages, and custom post types |
| `ollie/manage-content` | Block-level operations on post content |
| `ollie/manage-blocks` | Surgical block attribute/innerHTML edits + batch content replacement |
| `ollie/manage-patterns` | Search, apply, replace block patterns |
| `ollie/manage-templates` | Block templates and template parts |
| `ollie/manage-navigation` | WordPress navigation menus |
| `ollie/manage-global-styles` | Site-wide design tokens and fonts |

### Post Management

#### `ollie/manage-posts`
Create, list, get, update, and delete WordPress posts, pages, and custom post types. **Always use `list` first** to find post IDs. When creating new content, always try `create_from_pattern` first — it's the fastest path. The `post_type` parameter defaults to `post`. Set it to `page` for pages, or any registered custom post type slug (e.g. `product`, `portfolio`).

- `list` — Get all posts with IDs (use `search` param to filter by title, `post_type` to filter by type)
- `create` — Requires `title`; optional `content` (block markup), `status`, `template`, `post_type`. **Only use this when you already have prepared block markup** (e.g. after merging custom content into a pattern). For new content, use `create_from_pattern` instead.
- `create_from_pattern` — **The preferred action for creating new content.** Searches the pattern library, picks the best match, and creates the post in one call. Accepts `title` + `query` (broad queries like "pricing page", "about page"), or `pattern_slug`/`pattern_slugs` for known cached patterns. For pages, template defaults to `page-no-title`. Other post types use site defaults.
- `get` — Read post content by `post_id`
- `update` — Modify post by `post_id`
- `delete` — Remove post by `post_id` (set `force: true` to permanently delete)

#### `ollie/manage-content`
Block-level operations on post content. **Best for swapping sections, restructuring layouts, or bulk changes.**

- `list_blocks` — See all top-level blocks on a post
- `get_block` — Read a single block's full markup by index
- `update_block` — Replace a block with new markup
- `insert_block` — Add a block at a position
- `delete_block` — Remove a block
- `batch_update` — Replace multiple blocks in one call (indices refer to original positions)

#### `ollie/manage-blocks`
**Surgical** attribute and innerHTML edits on individual blocks. Best for tweaking styles, adding animations, changing classes, or editing text within existing blocks without replacing the full section. **Preferred tool for content replacement** — use `list-text` + `batch-update` to swap text across multiple blocks in one call.

- `list-sections` — Returns all top-level blocks with index, blockName, label, and text summary
- `list-text` — Returns every text block inside a section (or all sections) with its pre-computed `index_path`, `block_type`, and `inner_html`. Use `section_index` to scope to a single section. **Use this before batch-update** — it gives you the exact paths and current content without needing to parse raw markup.
- `update` — Modify a single block's attributes and/or innerHTML by `index_path`
- `batch-update` — Modify multiple blocks' attributes and/or innerHTML in one call. Each item in the `updates` array takes an `index_path` and at least one of `attributes` or `inner_html`. All changes are applied to the in-memory block tree, then serialized, validated, and saved once. **Use this for content replacement** — swap text while preserving block structure.

`index_path` is an array of zero-based integers navigating the block tree:
- `[0]` = first top-level block
- `[0, 2]` = third inner block inside the first top-level block

**Recommended content-replace workflow:**
1. `list-sections` → identify the target section index
2. `list-text` with `section_index` → get all text blocks with pre-computed paths
3. `batch-update` → replace text using the paths from step 2

**Which tool to use:**
- Replace/update text content across a post → `ollie/manage-blocks` `list-text` + `batch-update` (preserves layout)
- Tweak a single block's color, animation, or text → `ollie/manage-blocks` `update`
- Replace or restructure an entire section → `ollie/manage-content`
- Create/delete/list posts → `ollie/manage-posts`

---

### Patterns

#### `ollie/manage-patterns`
Search, apply, and replace block patterns from the Ollie cloud pattern library. **Do NOT use this tool to compose a new page from multiple section patterns** — use `ollie/manage-posts` `create_from_pattern` instead, which finds full-page designs in a single call. This tool is for: adding sections to an existing post, replacing sections on an existing post, or browsing available patterns.

**Workflow**:
1. `search` — Semantic cloud search. Describe what you need (e.g., "hero section with image and CTA"). Returns patterns with full block markup, auto-cached locally. Always search before building from scratch.
2. `apply` — Insert a pattern into a post. **Requires `post_id`.** Use the `pattern_slug` from search results (preferred — fast and reliable) or pass raw `content`.
3. `replace` — Swap a top-level section with a new pattern. **Requires `post_id` and `section_index`.**

**Preview/Confirm flow** (applies to both `apply` and `replace`):
1. Call with `post_id` + `pattern_slug` (confirm defaults to false) → returns `preview_url`, `preview_token`, and validation summary.
2. Call again with `post_id` + `pattern_slug` + `confirm: true` + `preview_token` → finalizes the change.

**Important**: Pattern `pattern_slug` values look like `cloud/agency/05-pricing-page`. Always run a `search` first to cache the pattern locally before attempting to apply — otherwise the apply call will fail with a "not found in local cache" error.

You can also pass a `template` param on apply/replace to set the page template (e.g. `"page-no-title"`, `"blank"`).

#### `ollie/manage-posts` `create_from_pattern`
**The preferred action for creating new content.** Always use this first when a user asks to create, build, or add a page or post. One tool call handles everything — search, pattern selection, and post creation.

**How it works internally:**
1. Searches the pattern library and picks the **single best match**.
2. Creates the post with that one pattern. One call, one pattern, done.
3. To compose multiple specific sections, use the `pattern_slugs` array param — this is the only way to get multi-section composition, and it requires you to choose the slugs deliberately.

**Parameters:**
- `title` + `query` — use **broad, page-level queries** like "pricing page", "about page", "contact page".
- `post_type` — defaults to `post`. Set to `page` for pages, or any registered CPT slug.
- `pattern_slug` — skip search, use a single known cached pattern.
- `pattern_slugs` — array of cached pattern slugs to compose into a post. Use when you already know exactly which sections to combine (e.g. from a prior `ollie/manage-patterns` search). All patterns are concatenated in order and the post is created in one shot.
- `status` (`draft`/`publish`) — optional.
- `template` — for pages, defaults to `"page-no-title"` (full width, no title). Other post types use site defaults.

**When to use:**
- User says "create a pricing page" → `create_from_pattern` with `post_type: "page"`, query "pricing page". One call, done. Do NOT ask what their pricing tiers are — use placeholder content.
- User says "create a blog post" → `create_from_pattern` with query "blog post". Do NOT ask for the topic — just create it with placeholders.
- User says "create a page with pricing, testimonials, and a CTA" → still use `create_from_pattern` with `post_type: "page"` and a broad query like "pricing page". The tool handles composition internally.
- You already searched and know the slugs → pass `pattern_slugs` array. One call, all sections composed, post created.
- User already provided specific content in their request → pass `custom_content`. The tool returns the markup for you to merge, then use `ollie/manage-posts` `create`. (Only use this when the user volunteered the content — never prompt for it.)

---

### Templates & Navigation

#### `ollie/manage-templates`
Manage WordPress block templates and template parts (headers, footers, sidebars).

- `list` — Returns all templates/parts with id, slug, title, area
- `get` — Read full block markup by `template_id` or `slug`
- `update` — Replace a template's block markup by `template_id`

Set `type` to `wp_template` for page templates or `wp_template_part` (default) for headers/footers/sidebars.

#### `ollie/manage-navigation`
Manage WordPress navigation menus.

- `list` — Returns all navigation menus with id/title
- `get` — Read full block markup of a menu by `nav_id`
- `create` — Create a new navigation menu with title and optional items
- `update` — Replace menu content by `nav_id`. Use structured `items` array for simple menus or raw block markup for `content`.

Supports `core/navigation-link`, `core/navigation-submenu`, and `ollie-menu-designer/mega-menu` blocks (when Mega Menu extension is active).

---

### Global Styles & Design

#### `ollie/manage-global-styles`
Read and update site-wide design tokens and fonts. **Always call `get` first** to see current values before making changes.

- `get` — Returns current colors, typography, spacing, layout (use `sections` param to limit)
- `update` — Modify color palette hex values, font families, font sizes, spacing, layout widths
- `read-raw` — Returns the raw theme.json overrides object
- `update-raw` — Deep-merge arbitrary theme.json overrides
- `list-fonts` — All installed font families with faces
- `install-font` — Install from URL, file upload, or Google Fonts by name
- `remove-font` — Delete a font family by slug
- `list-font-collections` — Browse available font collections (Google Fonts, etc.)

---
