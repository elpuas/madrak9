# Carousel Composable Blocks Design

## Summary

Restructure the carousel block from a flat `ollie/carousel > ollie/slide` model to a composable architecture where navigation/pagination and slides are separate child blocks that can be freely positioned within the carousel container alongside any other blocks (headings, paragraphs, groups, etc.).

## Decisions

- No backward compatibility needed — the carousel block is unreleased
- Navigation blocks are "dumb" visual placeholders in the editor; `view.js` handles all interaction
- Any block type is allowed inside the carousel (open inserter)
- The slides container (`ollie/carousel-slides`) is required, direct, and locked against moving or deletion
- Arrow/dot styling settings live on `ollie/carousel-nav` (via `navType`: arrows | dots | bars)
- Slide behavior, responsive settings, block gap, rendering configuration, and Embla runtime live on `ollie/carousel-slides`
- The parent owns composition, the Design Picker, Core design supports, and centered-navigation layout state
- Unified nav block selected: one `ollie/carousel-nav` with `navType` instead of a separate pagination block (simpler mental model; still freely positionable)

## Block Architecture

### `ollie/carousel` (parent container)

**Role:** Outer composition wrapper. Owns the Design Picker, Core design supports, arbitrary surrounding content, and centered-navigation layout state.

**Custom attribute retained:** `centerNav`

**Default template:**
```js
[
  ['ollie/carousel-nav', {}],
  ['ollie/carousel-slides', { lock: { move: false, remove: true } }, [
    ['ollie/slide', {}, [['core/paragraph', {}]]]
  ]],
  ['ollie/carousel-nav', { navType: 'dots' }]
]
```

### `ollie/carousel-slides` (slides container)

**Role:** Functional carousel viewport. Owns slide behavior, block gap, multi-breakpoint responsive settings, dynamic rendering configuration, and the Embla view script. Locked against moving and deletion. Allowed children: `ollie/slide` only.

### `ollie/carousel-nav` (arrows / dots / bars)

**Role:** Visual placeholder + server-rendered controls. `navType` switches between arrows, dots, and bars.

### Frontend (`carousel-slides/view.js`)

- Mount Embla on `.ollie-carousel-viewport`
- Wire controls belonging to the nearest `.ollie-carousel-block` boundary
- Resolve all max-width breakpoints and reinitialize only when effective settings change
- Autoplay exposes an accessible pause/play control
- Strings localized via `ollieCarouselL10n`

## Status

Implementation uses composable blocks with unified `carousel-nav`, one direct Slides viewport, Slides-owned behavior/runtime, and patterns serialized to the canonical hierarchy.
