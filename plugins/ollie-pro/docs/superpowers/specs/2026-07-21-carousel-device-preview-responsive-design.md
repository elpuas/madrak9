# Carousel Device-Preview Responsive Settings

## Summary

Replace the custom breakpoint system in the carousel-slides block with device-preview-based responsive controls that match Ollie Pro's existing responsive settings pattern. When a user switches the editor's device preview (Desktop/Tablet/Mobile), the Slides Per View and Slides To Scroll controls swap to show the value for that breakpoint.

## Motivation

The carousel block currently uses a custom breakpoint UI (select a pixel width, add/delete breakpoints) that is inconsistent with how every other Ollie Pro responsive setting works. Other blocks use the WordPress device preview toolbar to swap controls between desktop, tablet, and mobile. Aligning the carousel to this pattern gives users a familiar, consistent experience.

## Design

### Attributes

Remove the `breakpoints` array attribute. Add four flat attributes:

| Attribute | Type | Default |
|---|---|---|
| `tabletSlidesPerView` | `number` | — (unset) |
| `tabletSlidesPerGroup` | `number` | — (unset) |
| `mobileSlidesPerView` | `number` | — (unset) |
| `mobileSlidesPerGroup` | `number` | — (unset) |

Existing `slidesPerView` (default 3) and `slidesPerGroup` (default 1) remain as desktop/base values.

**Cascade:** When a responsive value is unset, it inherits. Mobile inherits from tablet, tablet inherits from desktop. This matches how the responsive controls extension cascades values.

### Fixed Breakpoints

- Tablet: `max-width: 768px`
- Mobile: `max-width: 480px`

These match the breakpoints used by the responsive controls extension stylesheet.

### Inspector UI

Import `useDeviceType` from `inc/extensions/src/controls/responsive-controls/use-device-type.js`.

In the "Carousel Settings" panel:

- **Desktop:** Show `slidesPerView` and `slidesPerGroup` RangeControls as-is.
- **Tablet:** Show RangeControls bound to `tabletSlidesPerView` / `tabletSlidesPerGroup`. Labels suffixed with " (Tablet)". `allowReset` enabled so users can clear a value to inherit from desktop.
- **Mobile:** Show RangeControls bound to `mobileSlidesPerView` / `mobileSlidesPerGroup`. Labels suffixed with " (Mobile)". `allowReset` enabled. Placeholder/initial value shows the effective inherited value (from tablet, or desktop if tablet is also unset).

The `BreakpointControls` component and its panel are removed.

### Server-Side Rendering (render.php)

Read the four new attributes. Build a breakpoints array from them (same format the frontend JS already consumes):

```php
$breakpoints = [];
if (isset tablet values) {
    $breakpoints[] = ['width' => 768, 'slidesPerView' => ..., 'slidesPerGroup' => ...];
}
if (isset mobile values) {
    $breakpoints[] = ['width' => 480, 'slidesPerView' => ..., 'slidesPerGroup' => ...];
}
```

Apply cascade: if mobile values are unset, they inherit from tablet; if tablet values are unset, they inherit from desktop. Output via `data-ollie-breakpoints` as before.

### Frontend JS (view.js)

No changes needed. The `setupResponsiveSettings` function already reads `data-ollie-breakpoints` and creates media query listeners. It will receive 0-2 breakpoints instead of 0-5, but the logic is identical.

### responsive.js

Simplify: remove `validateBreakpointWidth`, `MAX_BREAKPOINTS`, `MIN_BREAKPOINT_WIDTH`, `MAX_BREAKPOINT_WIDTH` since breakpoints are no longer user-defined. Keep `resolveResponsiveSettings`, `sortBreakpoints`, and `normalizeBreakpoint` as they're used by view.js.

### Files Changed

1. `src/carousel-slides/block.json` - Remove `breakpoints`, add 4 attributes
2. `src/carousel-slides/inspector.js` - Device-preview control swapping
3. `src/carousel-slides/breakpoint-controls.js` - Delete
4. `src/carousel-slides/responsive.js` - Remove unused validation exports
5. `src/carousel-slides/render.php` - Read new attributes, build breakpoints

### Files Unchanged

- `src/carousel-slides/view.js` - Consumes breakpoints the same way
- `src/carousel-slides/style.scss` / `editor.scss` (if any) - No CSS changes

### Migration

The `breakpoints` attribute will be silently dropped (not in schema). No crash. Existing carousels revert to desktop-only values until the user sets tablet/mobile values. Acceptable for a block in active development.
