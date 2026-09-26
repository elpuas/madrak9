# PRESETS.md — Art-Direction Points of View

Before building (Step 1 / Rule D-01), commit to one POV. These are **real, shipped style variations** — applying one is a single-click, fully on-system act, and it proves dramatic expression needs no custom CSS. **If the site already has a variation/typography preset active, honor it.** Otherwise pick the one that fits the brief.

The variations come in two tiers:
- **Color variations** — palette only (swap the 11-slot palette; overrides *replace*, they don't merge, so a palette must restate all 11 slots).
- **Personality variations** — palette **plus** heading/button typography treatment. These are the real "looks."

---

## Personality presets (palette + typography) — pick one as your POV

| Preset | Accent (`primary`) | Headings | Vibe / when to use |
|---|---|---|---|
| **Agency** | #495148 w/ neon `primary-alt` #CEF453 | `narrow`, weight **700**, `tight` LH, **UPPERCASE**; buttons `narrow` 700 uppercase. **Rescales type:** large 2.75 / x-large **4.25** / xx-large **6.5rem** | Bold, brutalist-editorial, confident. Big uppercase display. Great for agencies, studios, statement marketing. |
| **Creator** | #5A20FF (violet) | `condensed`, weight 700, `tight` LH, size `large` | Punchy, personal-brand, content-creator energy. Dense editorial headlines. |
| **Startup** | #454DFF (indigo) | `expanded`, weight 500, `snug` LH, size `large` | Airy, modern, SaaS/product. Open and optimistic. |
| **Studio** | #FF50A9 (pink) | `primary`, weight **800**, `snug` LH; buttons semi-bold | Heavy, expressive, creative-studio. Maximal weight contrast. |
| **eCommerce** | #FF6637 (orange) | **family → Geist**, weight semi-bold, `snug`, letter-spacing -.5px | Clean, commerce, product-forward. Only preset that changes the body family. |

> To execute a preset *manually* on a from-scratch section (without switching the whole site), apply its heading treatment via element/block typography attributes using the tokens above — e.g. for Agency, headings get `var:preset|font-family|narrow` + `var:custom|fontWeight|bold` + `var:custom|lineHeight|tight` + `textTransform:uppercase`, and you lean on the large end of the type scale.

## Color-only variations (palette swaps)
Blue #1b4cff · Green #00786f · Neon #495148/#CEF453 · Orange #FF6637 · Pink #FF50A9 · Red #F82F58 · Teal #45A1B8. Each restates all 11 slots; design against slugs so a palette swap "just works."

## Typography presets (body/heading pairings) — `styles/typography/*.json`
Name one to anchor type choices. Confirm the font is registered/loaded before committing to a non-Mona option.

| # | Heading | Body | Note |
|---|---|---|---|
| 1 | Mona Sans Expanded (500) | Mona Sans | all-Mona, airy |
| 2 | DM Sans (700) | DM Sans (400) | friendly geometric |
| 3 | Big Shoulders | Mona Sans | tall display + neutral body |
| 4 | Space Grotesk (700) | Space Grotesk (400) | techy |
| 5 | Montagu Slab | Source Serif 4 | editorial serif pairing |
| 6 | Fraunces (700) | Mona Sans | expressive serif display |
| 7 | Source Serif 4 (800) | Source Serif 4 | classic literary |
| 8 | Mona Sans (800) | Mona Sans | heavy all-Mona |
| 9 | Mona Sans Narrow (700) | Mona Sans | condensed-headline editorial |
| 10 | Geist | Geist | clean product/commerce |

## Choosing quickly
- "Make it feel like an agency / bold / not generic" → **Agency** (uppercase narrow display, big type).
- "Modern SaaS / product" → **Startup** (expanded, airy) or typography preset 4/10.
- "Creative / expressive / personal" → **Studio** or **Creator**.
- "Shop / store" → **eCommerce**.
- "Editorial / publication" → typography preset 5, 6, or 9.
