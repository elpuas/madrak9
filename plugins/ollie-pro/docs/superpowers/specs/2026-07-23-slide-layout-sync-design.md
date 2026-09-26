# Slide Layout Sync — Design

**Date:** 2026-07-23 · **Branch:** carouselBlocks · **Status:** approved direction (one-shot first, live Smart Sync integration as fast-follow)

## Problem

Smart Sync propagates style changes across similar sibling blocks but not structural
changes (moving a heading above an image, adding/removing blocks). Carousel slides
usually share one layout with per-slide content, so structural edits should be able
to cascade — query-loop-style — while each slide keeps its own content.

## Approach

One reconciliation engine, two consumers:

1. **v1 (this build):** an "Apply layout to all slides" button on `ollie/slide` —
   takes the current slide as the layout source and rebuilds every sibling slide to
   match, preserving each sibling's content. Single undo step, no live watching.
2. **Fast-follow:** call the same engine from Smart Sync's live change pipeline when
   `smartSync` is enabled on the slides container.

## Reconciliation rules

Blocks are matched between source and sibling by **(block name, occurrence index)**
at each tree level. The sibling is rebuilt in the source's order:

- Matched block: source attributes win, EXCEPT the target's content attributes
  (see below), which are preserved. Children recurse with the matched pair.
- Source block with no match in the sibling (insertion): cloned into the sibling
  verbatim (content starts as a copy).
- Sibling block with no match in the source (removal): dropped.

Attribute categories:

- **Content attributes** (preserved per sibling):
  - `core/paragraph`: content
  - `core/heading`: content
  - `core/image`: url, id, alt, caption, title, href
  - `core/button`: text, url, title, rel, linkTarget
  - `core/cover`: url, id, alt, focalPoint
  - `core/video`: src, id, poster, caption
- **Structural types** (all attributes sync): core/group, core/columns, core/column,
  core/buttons, core/spacer, core/separator.
- **Unknown types** (conservative): position/existence syncs, but the sibling keeps
  ALL its own attributes — content preservation beats style sync when we can't tell
  which attributes are content.

Known limitation: two same-type siblings that swap positions in the source pair up
by occurrence order, so their contents follow the position, not the block identity.
Documented; acceptable for v1.

## UI

`ollie/slide` inspector gains a "Slide Layout" panel with an "Apply layout to all
slides" button + help text. Shown only when the parent `ollie/carousel-slides` has
more than one slide and is not in dynamic mode. Applied via a single registry batch
(one undo step).

## Files

- `src/carousel-slides/layout-sync.js` — pure engine (`buildSyncedBlocks`).
- `src/carousel-slides/layout-sync.test.js` — engine unit tests.
- `src/carousel-slide/index.js` — inspector panel + apply action.
