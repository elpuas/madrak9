# Carousel Dynamic Slides — Design

**Date:** 2026-07-21
**Branch:** carouselBlocks
**Status:** Approved direction (owned block + block-template slides); spec pending review

## Summary

Add a **Static / Dynamic** content mode to `ollie/carousel-slides`. Static mode is the current behavior (hand-built `ollie/slide` blocks). Dynamic mode runs a post query and renders one slide per post, using a designable `ollie/slide` block as the per-post template — the same block-context mechanism `core/post-template` uses, but inside our own block so the carousel's DOM, editor UX, and inspector stay fully under our control.

We deliberately do **not** build this as a `core/query` block variation. Research findings (2026-07-21): post-template hardcodes `ul/li` markup that conflicts with the Embla structure (`.ollie-carousel-container` > `.ollie-carousel-slide` direct children), its editor layout controls can't be hidden, the query block's internals (enhanced pagination, Interactivity API, Instant Search) churn every WP release on exactly the DOM surfaces a carousel couples to, and a variation would be a second block rather than a Static/Dynamic toggle on the existing one. Commercial suites (Kadence, GenerateBlocks, PostX) all own their dynamic carousel/query blocks for the same reasons.

## Goals

- One block, two modes: existing static carousels are untouched; dynamic mode is opt-in per carousel.
- Full layout control of dynamic slides via inner blocks: core post blocks (Post Title, Post Featured Image, Post Excerpt, Post Date, Post Terms, Read More, etc.) work inside the slide template through block context.
- Query controls comparable to the Query Loop's "Custom" mode: post type, order, sticky, taxonomy filters, author, keyword, item count, offset.
- Zero frontend JS changes: Embla, nav, autoplay, breakpoints already read options from data attributes on the viewport and treat every direct child of `.ollie-carousel-container` as a slide.
- Lossless mode switching: toggling Static ↔ Dynamic never destroys the user's slide blocks.

## Non-Goals (v1)

- No pagination or "inherit query from template" (Default/inherit mode). Carousels aren't archives; `perPage` + `offset` cover the use cases. Can be revisited if template-part usage demands it.
- No "no results" designable state — an empty query renders no slides on the frontend and shows a notice in the editor.
- No multiple templates / alternating slide designs.
- No editor-side Embla initialization (unchanged from today — the editor shows the horizontal strip preview).

## Attributes (`ollie/carousel-slides`)

```json
"sourceType": { "type": "string", "default": "static" },   // "static" | "dynamic"
"query": {
  "type": "object",
  "default": {
    "postType": "post",
    "perPage": 6,
    "offset": 0,
    "order": "desc",
    "orderBy": "date",
    "sticky": "",              // "" | "exclude" | "only"
    "taxQuery": {},            // { taxonomySlug: [termIds] }
    "author": "",              // comma-joined author IDs, matching core's shape
    "search": "",
    "excludeCurrent": false    // exclude the currently viewed post (related-posts carousels)
  }
}
```

The `query` object mirrors `core/query`'s attribute shape (minus `inherit`/`pages`) so the mental model and any future migration stay familiar.

## Inspector UI

New **"Content"** panel at the top of the carousel-slides inspector (above "Carousel Settings", in `src/carousel-slides/inspector.js`):

- `ToggleGroupControl`: **Static | Dynamic** (`sourceType`).
- When Dynamic, below the toggle:
  - Post Type — `SelectControl` fed by `getPostTypes({ per_page: -1 })`, filtered to viewable types.
  - Order By / Order — selects (date, title, menu order, random × asc/desc).
  - Sticky Posts — select (Include as normal / Exclude / Only), shown for `post` type only.
  - Items — `RangeControl` (1–24) for `perPage`.
  - Offset — `NumberControl`.
  - **Filters** — a `ToolsPanel` (matches core's progressive-disclosure pattern):
    - Taxonomies — one `FormTokenField` per public taxonomy of the selected post type, with debounced term search via `getEntityRecords('taxonomy', slug, { search })`.
    - Author — `FormTokenField` fed by `getUsers`.
    - Keyword — `TextControl` (`search`).
    - Exclude Current Post — `ToggleControl` (`excludeCurrent`).

Core's query inspector components aren't exported, so these are built from public `@wordpress/components` + `@wordpress/core-data` primitives. All labels run through the plugin's existing i18n domain.

## Dynamic mode: template mechanism

**The first `ollie/slide` child is the slide template.** Its inner blocks define the per-post layout. This is the same conceptual model as `core/post-template`, scoped to our block.

### Server (`src/carousel-slides/render.php`)

1. If `sourceType !== 'dynamic'`, current behavior — echo `$content` inside the viewport/container (no change).
2. If dynamic:
   - Map `query` attributes to `WP_Query` args (sticky handling mirrors `build_query_vars_from_query_block`'s approach: `ignore_sticky_posts`, `post__not_in` for exclude, `post__in` for only). `excludeCurrent` adds the current post ID to `post__not_in` when on a singular view.
   - Apply a filter `ollie_carousel_query_args` (`apply_filters( 'ollie_carousel_query_args', $args, $block )`) so developers can customize queries — same escape hatch Kadence provides.
   - Locate the first `ollie/slide` in `$block->parsed_block['innerBlocks']` as the template.
   - Loop the query: for each post, render the template with per-post context —
     `( new WP_Block( $template_parsed_block, [ 'postId' => $post_id, 'postType' => $post_type ] ) )->render()` — wrapped in `setup_postdata()` / `wp_reset_postdata()` for classic-filter compatibility. `ollie/slide`'s static save keeps emitting the `.ollie-carousel-slide` wrapper, so Embla's DOM contract (`view.js` queries `.ollie-carousel-slide` for a11y) is satisfied automatically.
   - Emit the rendered slides as the children of `.ollie-carousel-container`. Data attributes on `.ollie-carousel-viewport` are built exactly as today — no changes to option plumbing.
   - Empty result: render the viewport with an empty container (frontend JS already tolerates zero slides; verify during implementation and guard `initCarousel` if not).

### Editor (`src/carousel-slides/edit.js`)

Dynamic mode replaces the plain `InnerBlocks` strip with a post-template-style preview inside the same horizontal strip styling (CSS vars for slides-per-view etc. unchanged):

- Fetch posts with `useEntityRecords( 'postType', query.postType, mappedQueryArgs )`. Editor-side arg mapping lives in a small `query-utils.js` shared by the inspector (single source of truth for attribute → REST args; the PHP mapping in render.php is the attribute → WP_Query counterpart).
- Render one strip item per post:
  - **Active post** (first, or last-clicked): the real, editable `ollie/slide` inner blocks wrapped in `BlockContextProvider` with that post's `postId`/`postType` — this is where the user designs the template.
  - **Other posts**: memoized `BlockPreview` of the same template, each wrapped in its own `BlockContextProvider`, non-interactive, click-to-activate. (Same pattern as `post-template/edit.js`.)
- The "Add New Slide" / "Duplicate Previous" appender is hidden in dynamic mode.
- Loading state: `Spinner` in the strip. Empty result: inline `Notice` ("No posts found. Adjust the query settings.").
- Sticky `"only"` has no REST equivalent; the editor preview approximates it (fetch sticky IDs via `include`) — mild preview divergence is acceptable and noted in code.

### Mode switching (lossless)

- Static → Dynamic: inner blocks are untouched. The **first** slide becomes the template; any additional slides are preserved in markup but visually hidden in the editor (with a hint in the Content panel: "Dynamic mode uses your first slide as the template") and skipped by render.php.
- Dynamic → Static: all original slides reappear exactly as they were.
- A carousel switched to dynamic with **zero** slides gets a default template inserted: `ollie/slide` containing Post Featured Image + Post Title + Post Excerpt.

## What doesn't change

- `ollie/carousel`, `ollie/carousel-nav`, `ollie/slide` blocks: no changes (slide keeps its static save; it simply renders under post context in dynamic mode).
- `src/carousel-slides/view.js` and all frontend behavior: no changes expected (verify zero-slide guard).
- Registration, extension gating, build setup (wp-scripts): unchanged. New editor code compiles into the existing `build/carousel-slides/index.js`.

## Error handling

- Invalid/removed post type stored in attributes: render.php falls back to no output for the slides (empty container), editor shows the "No posts found" notice; post type select shows the stored value flagged as unavailable.
- Term/author IDs that no longer exist: WP_Query ignores them naturally; FormTokenFields simply won't resolve the tokens.
- Template slide missing in dynamic mode (e.g. removed via code editor): render.php outputs empty container; editor re-inserts the default template.

## Testing

- **JS unit tests** (existing `wp-scripts test-unit-js` setup): `query-utils.js` attribute→REST arg mapping, including sticky and taxQuery shapes; mode-switch behavior helpers.
- **Manual verification** on the local site: static carousels unchanged (regression pass on existing patterns), dynamic carousel with core post blocks in the template, taxonomy/author/keyword filters, offset + exclude-current on a single post, RTL + fade + breakpoints with dynamic slides, empty-query behavior front and back.
- **PHP:** no existing PHP test infra in the plugin; render.php query mapping is covered by the manual pass plus the shared-shape JS tests.

## New pattern opportunity (follow-up, not v1 scope)

Once shipped, add a "Blog Posts Carousel" pattern to `inc/carousel/patterns/` preconfigured with `sourceType: dynamic` and the default template — likely the primary way users discover the feature.
