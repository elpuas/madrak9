# Ollie — Design Tokens & Style Reference

> Canonical source of truth for all token values (colors, type, spacing, radius, shadows, variations). Verified against theme.json. Never use a value not derived from these tables.
> For *design judgment* about which tokens to reach for when building from scratch, see `../design/DESIGN.md` — this file is the lookup; that file is the taste.

## Color Palette System

### The 11 Color Slots

Every color palette in Ollie defines exactly these 11 slots. The **slug** is what you reference in block attributes and theme.json styles; the **name** is the human-readable label.

| Name | Slug | Role | Usage Notes |
|------|------|------|-------------|
| **Brand** | `primary` | Main brand color | The hero color. "Use my brand color" = this. |
| **Brand Accent** | `primary-accent` | Light tint of brand | Pairs with Brand. Use for light backgrounds behind brand-colored text. |
| **Brand Alt** | `primary-alt` | Secondary brand color | Pairs with Brand Alt Accent. A complementary or secondary color. |
| **Brand Alt Accent** | `primary-alt-accent` | Dark companion to Brand Alt | Text color that works on Brand Alt backgrounds and vice versa. |
| **Contrast** | `main` | Primary dark/black | Always a very dark color. Used for text, dark sections, footers. |
| **Contrast Accent** | `main-accent` | Light color for dark backgrounds | An off-white/light that is readable on `main` backgrounds. |
| **Base** | `base` | White / page background | Always white or near-white. The default page background. |
| **Base Accent** | `secondary` | Muted text color | A tinted mid-tone — good for secondary text, subtle highlights, bylines. |
| **Tint** | `tertiary` | Very light gray background | Used for surfaces, cards, alternating sections. "Light background" often means this. |
| **Border Base** | `border-light` | Light border color | Standard border for cards, inputs, dividers. |
| **Border Contrast** | `border-dark` | Darker border color | Heavier borders, active states, emphasis. |

> **Rule C-01**: Never use hardcoded hex/rgb/hsl colors in block attributes. Always reference a design token slug. The design linter will reject hardcoded values.

### Color Pairing Rules

These pairs are designed to work together (foreground/background swappable):
- **Brand** (`primary`) + **Brand Accent** (`primary-accent`)
- **Brand Alt** (`primary-alt`) + **Brand Alt Accent** (`primary-alt-accent`)
- **Contrast** (`main`) + **Contrast Accent** (`main-accent`)
- **Base** (`base`) + **Contrast** (`main`) — white bg, dark text
- **Tint** (`tertiary`) + **Contrast** (`main`) — light gray bg, dark text

When setting a background color, always set a compatible text color from the pairing rules above.

### Interpreting User Requests

| User says... | Use this slug |
|-------------|---------------|
| "brand color" / "primary color" / "main color" | `primary` |
| "secondary color" / "accent color" | `primary-alt` |
| "dark background" / "dark section" | `main` (bg) + `base` or `main-accent` (text) |
| "light background" | `tertiary` (tint) or `base` (white) |
| "white background" | `base` |
| "muted text" / "subtle text" | `secondary` |
| "border" | `border-light` (default) or `border-dark` (emphasis) |

### CSS Variable Pattern

In block attributes and theme.json styles, reference colors as:
```
"var:preset|color|primary"
```
This becomes the CSS variable `--wp--preset--color--primary` at render time.

### Available Color Palettes

Pre-built palettes in `styles/colors/`. To switch palettes, apply the palette's JSON to `settings.color.palette` in theme.json or use the Site Editor.

| File | Title | Brand Color | Character |
|------|-------|------------|-----------|
| `blue.json` | Blue | #1b4cff | Professional, tech |
| `green.json` | Green | #00786f | Nature, health |
| `pink.json` | Pink | #FF50A9 | Creative, playful |
| `orange.json` | Orange | #FF6637 | Warm, eCommerce |
| `red.json` | Red | #F82F58 | Bold, energetic |
| `teal.json` | Teal | #45A1B8 | Calm, modern |
| `neon.json` | Neon | #495148 (sage + lime accents) | Agency, bold |

**When a user asks to "switch to blue" or "use the blue palette":** read `styles/colors/blue.json` and apply its `settings.color.palette` array to the main `theme.json`. Do NOT create new colors — use the existing palette file.

---

## Button Styles

Pre-built button styles in `styles/blocks/button/`. These are registered block styles for `core/button`. Apply via `className: "is-style-{slug}"`.

| Style | Slug | Background | Text |
|-------|------|------------|------|
| Brand | `button-brand` | `primary` | `base` |
| Brand Alt | `button-brand-alt` | `primary-alt` | `primary-alt-accent` |
| Dark | `button-dark` | `main` | `base` |
| Light | `button-light` | `base` | `main` |
| Tint | `secondary-button` | `tertiary` | `main` |

Default button (set in theme.json): `main` background, `base` text, 5px border-radius, font-weight 500, font-size small, padding 0.6em 1em.

**When a user asks to change a button color**, first check if one of these existing styles matches. If so, apply the variation by setting `className` in **both** the block comment JSON **and** the inner HTML `class` attribute. Both must match or the editor won't reflect the style.

**Correct example** — applying the "Brand" button style:

```html
<!-- wp:button {"className":"is-style-button-brand"} -->
<div class="wp-block-button is-style-button-brand"><a class="wp-block-button__link wp-element-button">Get Started</a></div>
<!-- /wp:button -->
```

**Wrong** — class only on the inner div (front-end works, editor does not):

```html
<!-- wp:button {} -->
<div class="wp-block-button is-style-button-brand"><a class="wp-block-button__link wp-element-button">Get Started</a></div>
<!-- /wp:button -->
```

The `className` in the block comment is what WordPress uses to set the active variation in the editor sidebar. Without it, the editor shows no style selected even though the front-end renders correctly.

When combining with other attributes like `width`, merge them:

```html
<!-- wp:button {"width":100,"className":"is-style-button-brand"} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100 is-style-button-brand"><a class="wp-block-button__link wp-element-button">Get Started</a></div>
<!-- /wp:button -->
```

---

## Typography System

### Font Sizes (Fluid)

All font sizes use `clamp()` for responsive scaling. Reference as `var:preset|font-size|{slug}` or just the slug in a `fontSize` attribute.

| Slug | Size | Fluid Range |
|------|------|-------------|
| `x-small` | 0.95rem | 0.825–0.95rem |
| `small` | 1.05rem | 0.9–1.05rem |
| `base` | 1.165rem | 1–1.165rem |
| `medium` | 1.65rem | 1.2–1.65rem |
| `large` | 2.35rem | 1.5–2.35rem |
| `x-large` | 2.9rem | 1.875–2.9rem |
| `xx-large` | 3.75rem | 2.25–3.75rem |

> **Rule C-02**: Never use custom font sizes like `18px` or `2rem`. Always use a `fontSize` slug from the type scale above.

### Font Families

| Slug | Font | Use |
|------|------|-----|
| `primary` | Mona Sans | Default body + headings |
| `expanded` | Mona Sans Expanded | Wide, impactful headings |
| `condensed` | Mona Sans Condensed | Compact headings |
| `narrow` | Mona Sans Narrow | Tight, editorial headings |
| `monospace` | Monospace system stack | Code blocks |

Reference as `var:preset|font-family|{slug}`. Additional fonts can be installed via `ollie/manage-global-styles` → `install-font`.

### Font Weights (Custom)

Reference as `var:custom|fontWeight|{slug}`.

thin (100), extra-light (200), light (300), regular (425), medium (500), semi-bold (600), bold (700), extra-bold (800), black (900)

### Line Heights (Custom)

Reference as `var:custom|lineHeight|{slug}`.

none (1), tight (1.1), snug (1.2), body (1.5), relaxed (1.625), loose (2)

### Typography Presets

Pre-built typography combinations in `styles/typography/`. Each defines body + heading fonts, weights, and sometimes custom sizes.

| File | Body | Headings | Character |
|------|------|----------|-----------|
| `typography-preset-1` | Mona Sans | Mona Sans Expanded | Modern, wide headings |
| `typography-preset-2` | DM Sans | DM Sans (bold) | Geometric, neutral |
| `typography-preset-3` | Mona Sans | Big Shoulders | Display contrast |
| `typography-preset-4` | Space Grotesk | Space Grotesk | Monospace-inspired |
| `typography-preset-5` | Source Serif 4 | Montagu Slab | Serif + slab headings |
| `typography-preset-6` | Mona Sans | Fraunces (bold) | Modern + elegant serif |
| `typography-preset-7` | Source Serif 4 | Source Serif 4 (extra-bold) | Full serif |
| `typography-preset-8` | Mona Sans | Mona Sans (extra-bold) | Bold, tight headings |
| `typography-preset-9` | Mona Sans | Mona Sans Narrow (bold) | Narrow, editorial |
| `typography-preset-10` | Geist | Geist (semi-bold) | Modern tech |

---

## Spacing System

Fluid spacing presets. Reference as `var:preset|spacing|{slug}`.

| Slug | Value |
|------|-------|
| `small` | clamp(0.5rem, 2.5vw, 1rem) |
| `medium` | clamp(1.5rem, 4vw, 2rem) |
| `large` | clamp(2rem, 5vw, 3rem) |
| `x-large` | clamp(3rem, 7vw, 5rem) |
| `xx-large` | clamp(4rem, 9vw, 7rem) |
| `xxx-large` | clamp(5rem, 12vw, 9rem) |
| `xxxx-large` | clamp(6rem, 14vw, 13rem) |

> **Rule C-03**: Never use hardcoded spacing like `20px` or `3rem`. Always use `var:preset|spacing|{slug}` for padding, margin, and blockGap.

---

## Border Radius

Reference as `var:preset|border-radius|{slug}`.

| Slug | Value |
|------|-------|
| `xs` | 0.25rem |
| `sm` | 0.375rem |
| `md` | 0.5rem |
| `lg` | 0.75rem |
| `xl` | 1rem |
| `2xl` | 1.5rem |
| `full` | 100rem |

---

## Shadows

8 shadow presets: `small`, `medium`, `large`, `extra-large` (each in light and dark variants like `small-dark`). Reference as `var:preset|shadow|{slug}`.

---

## Style Variations

Complete design presets in `styles/`. Each bundles a color palette + typography + element overrides.

| Variation | File | Brand Color | Heading Font | Character |
|-----------|------|-------------|-------------|-----------|
| **Studio** | `studio.json` | #FF50A9 (pink) | Mona Sans (extra-bold) | Creative, rounded buttons |
| **Startup** | `startup.json` | #454DFF (blue) | Mona Sans Expanded (medium) | Tech, clean |
| **eCommerce** | `ecommerce.json` | #FF6637 (orange) | Geist (semi-bold) | Warm, commercial |
| **Creator** | `creator.json` | #5A20FF (purple) | Mona Sans Condensed (bold) | Expressive, compact |
| **Agency** | `agency.json` | #495148 (sage) | Mona Sans Narrow (bold) | Uppercase, neon accents, editorial |

When a user asks to "switch to the agency style" or "use the startup variation," read the corresponding file and apply its settings.

---

## Layout

- Content width: 740px
- Wide width: 1260px

---

## Key Files

| What | Where |
|------|-------|
| Main config | `theme.json` |
| Color palettes | `styles/colors/*.json` |
| Button styles | `styles/blocks/button/*.json` |
| Typography presets | `styles/typography/*.json` |
| Style variations | `styles/*.json` (agency, creator, ecommerce, startup, studio) |
| Fonts | `assets/fonts/` |

---
