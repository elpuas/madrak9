# Carousel Slides Ownership Design

## Goal

Move slide behavior, responsive controls, rendering configuration, and frontend runtime from `ollie/carousel` to `ollie/carousel-slides`. Keep `ollie/carousel` as a composable design shell and `ollie/carousel-nav` as the owner of navigation presentation.

The refactor should leave the blocks near release quality: clear ownership, no duplicate settings, predictable responsive behavior, minimal runtime work, accessible autoplay controls, and patterns that serialize the canonical architecture.

## Block Ownership

### Carousel

`ollie/carousel` is the composition boundary. It owns:

- The initial Design Picker and inspector Design button.
- Pattern discovery and replacement of the complete inner-block design.
- Outer Core block supports such as alignment, colors, typography, padding, margin, block gap, anchor, and layout.
- Arbitrary headings, text, groups, and Carousel Navigation blocks around the slides.
- One internal `centerNav` attribute used by the navigation toolbar to coordinate navigation branches with the outer composition.
- The shared carousel stylesheet, loaded once for the composed block.

The parent no longer owns slide behavior attributes, provides no slide behavior through block context, and loads no frontend carousel runtime.

### Carousel Slides

`ollie/carousel-slides` is the functional carousel viewport. It owns:

- `slidesPerView`
- `slidesPerGroup`
- `slideMaxWidth`
- `speed`
- `autoplay`
- `autoplaySpeed`
- `pauseOnMouseEnter`
- `fade`
- `overflowVisible`
- `rtl`
- `breakpoints`
- Slide spacing through Core `style.spacing.blockGap`
- The settings inspector
- Frontend runtime data and the Embla view script
- Slide add and duplicate actions
- Smart Sync state for synchronizing slide content

Carousel Slides reads its behavior directly from its attributes. The parent-to-slides behavior context is removed.

### Carousel Navigation

`ollie/carousel-nav` continues to own navigation presentation:

- Navigation type
- Arrow icon, weight, size, offset, and color
- Dot and bar size, width, fill, and color
- Navigation alignment and spacing
- Toolbar placement actions

Navigation can remain outside the viewport, in wrapper blocks, or inside individual slides. All matching navigation controls within the nearest Carousel boundary must continue to control that boundary's single Carousel Slides instance.

## Structural Invariants

Each Carousel must contain exactly one Carousel Slides block.

- Carousel Slides is a direct child of Carousel.
- It is removal-locked in blank and pattern-created carousels.
- Once it exists, it is excluded from the Carousel inserter.
- Other blocks remain freely composable before and after it.
- Carousel Navigation remains valid anywhere under the Carousel ancestor, including within slides.

The Design Picker remains available on the parent because selecting a design replaces both parent presentation and the complete inner-block composition.

## Inspector Experience

Selecting Carousel exposes only the Design Picker action and normal Core design controls.

Selecting Carousel Slides exposes:

1. Slide sizing and movement settings.
2. Autoplay and transition settings.
3. Overflow and direction settings.
4. Responsive Settings.
5. Core block spacing for the gap between slides.

The temporary parent `SpacingSizesControl` proxy is removed. Core block spacing on Carousel Slides is the sole source of truth for slide gap.

## Responsive Breakpoints

Carousel Slides stores base desktop values in its normal attributes. `breakpoints` stores up to five max-width overrides:

```json
[
	{
		"width": 1024,
		"slidesPerView": 3,
		"slidesPerGroup": 1
	},
	{
		"width": 768,
		"slidesPerView": 2,
		"slidesPerGroup": 1
	}
]
```

Responsive Settings mirrors the Advanced Grid interaction:

- Add Breakpoint action.
- Common 600px, 768px, and 1024px shortcuts.
- Numeric widths from 320px through 3840px.
- Duplicate prevention.
- Maximum of five breakpoints.
- Select an existing breakpoint to edit.
- Delete the selected breakpoint with confirmation.
- Automatically select a newly added breakpoint.
- Edit slides per view and slides per group for the selected breakpoint.

Base settings apply above every configured breakpoint. At narrower widths, the most specific matching max-width entry applies. For example, with 1024px and 768px entries, a 900px viewport uses 1024px settings and a 600px viewport uses 768px settings.

All breakpoints are serialized. The current first-breakpoint-only PHP encoding is removed. The responsive resolver is shared by initialization and media-query updates, and Embla reinitializes when either slides per view or slides per group changes.

## Rendering and Runtime

### Parent render

Carousel rendering becomes a minimal wrapper:

- Render the Core wrapper attributes and `ollie-carousel-block` class.
- Add `ollie-carousel-center-nav` when the internal composition flag is active.
- Render inner blocks.

It emits no Embla configuration data.

### Slides render

Carousel Slides rendering:

- Renders the viewport and inner Embla container.
- Emits sanitized local configuration through `get_block_wrapper_attributes()`.
- Emits CSS custom properties for base slide sizing and explicit block gap.
- Includes all breakpoint entries as JSON.
- Adds direction and presentation classes local to the viewport.

### View script

The view script moves to Carousel Slides and initializes from each viewport root. It finds the nearest `.ollie-carousel-block` only as a coordination boundary for navigation controls and shared layout state.

Runtime behavior:

- Initialize the single slides viewport in the boundary.
- Connect all arrow and pagination controls in that boundary, including controls nested inside slides.
- Resolve the initial responsive settings immediately.
- Use media-query listeners rather than one global debounced resize listener per carousel.
- Reinitialize Embla only when effective settings change.
- Update RTL coordination on the nearest Carousel boundary without making RTL parent-owned state.
- Respect `prefers-reduced-motion`.
- Provide a functional pause/play control whenever autoplay is active.
- Clean up Embla, plugins, event handlers, media-query listeners, and boundary state on teardown.

The localized frontend strings must follow the generated Carousel Slides view-script handle.

## Styles

The shared style asset remains registered on Carousel so the composed feature loads one stylesheet.

Cleanup includes:

- Replace `.ollie-carousel-shows-1` through `.ollie-carousel-shows-10` editor selectors with the existing `--ollie-slides-per-view` custom property.
- Scope viewport behavior to Carousel Slides.
- Preserve parent composition selectors for centered navigation.
- Coordinate RTL from slides-owned state.
- Remove obsolete `--ollie-slide-gap` fallbacks and use Core block gap as the only explicit slide gap.
- Remove selectors and classes with no source consumer.

## Patterns and New Blocks

Every bundled carousel pattern moves slide behavior attributes from the parent Carousel comment to the direct Carousel Slides comment. Existing responsive arrays are preserved and become fully functional.

Blank Carousel creation:

- Creates navigation, one locked Carousel Slides block with one Slide, and pagination.
- Uses Carousel Slides defaults for behavior.
- Selects the initial paragraph as it does today.

No backward-compatibility transform is required because the feature is unreleased. Parent-owned behavior attributes are removed rather than retained as deprecated aliases.

## Targeted Cleanup

The refactor also removes:

- Obsolete slide-behavior context providers and consumers.
- Parent behavior classes and data attributes.
- The temporary parent block-gap proxy.
- Manual HTML attribute concatenation in PHP.
- The lossy first-breakpoint encoding.
- The global resize-based breakpoint implementation.
- Dead or unused imports, classes, and CSS variables touched by the refactor.
- Dormant autoplay-toggle code by making it functional rather than leaving commented implementation.

Cleanup stays limited to code directly involved in carousel ownership, responsive behavior, rendering, and accessibility.

## Error Handling and Validation

- Invalid, duplicate, out-of-range, or sixth breakpoint additions show a dismissible warning and do not mutate attributes.
- Runtime parsing treats malformed breakpoint data as no responsive overrides.
- PHP normalizes slides per view and slides per group to integers from 1 through 10, slide max width to 100px through 1200px or unset, speed to 0ms through 1000ms, autoplay speed to a positive integer with a 3000ms default, and breakpoint widths to 320px through 3840px.
- Fade remains effective only when one slide is shown.
- Missing navigation is valid.
- Missing Carousel Slides is tolerated at runtime without JavaScript errors, although the editor prevents this state through locking and insertion rules.

## Verification

Add focused unit coverage for pure responsive utilities:

- Sorting breakpoint entries.
- Resolving the most specific max-width match.
- Base settings above configured breakpoints.
- Duplicate and range validation.
- Per-group-only changes triggering a settings update.
- Malformed breakpoint input.

Manual editor and frontend checks cover:

- Blank insertion and each bundled design.
- Parent Design Picker behavior.
- Exactly-one-slides enforcement.
- Core slide block gap.
- Multiple breakpoints and viewport transitions.
- Nested, duplicated, and sibling navigation controls.
- Centered navigation.
- RTL arrows and keyboard behavior.
- Fade constraints.
- Autoplay, pause/play, hover pause, and reduced motion.
- Overflow-visible mode.
- Pattern switching without leaked attributes.
- Multiple Carousel blocks on one page with isolated controls and teardown.

The existing watch processes regenerate all build artifacts. Verification must confirm successful compilation, block metadata validity, lint status for edited files, PHP syntax where a PHP binary is available, and absence of removed attribute/context names from source and generated bundles.
