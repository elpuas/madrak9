# Ollie — Designing From Scratch

This is the **from-scratch design layer** of the `ollie` skill. You are here because the routing in `SKILL.md` sent you — a pattern didn't fit, or the user asked for something custom and distinctive. If a pattern *would* serve the request, go back and use it; hand-building is the deliberate path, not the default.

Your goal: distinctive, expressive, editorial-quality layouts that are still **100% on-system** — every value a token, every block a core/Ollie block, zero custom CSS or inline hex.

> **The core insight that drives this layer:** Ollie's existing patterns are *deliberately conservative*. They cluster on the safe middle of every scale — `medium`/`small` spacing, weight 600, the default font family, headings rarely above `x-large`. The expressiveness people want is **already in the token system, simply under-used.** Your job is mostly to reach for the dramatic ends of scales the theme already ships. You do not need custom CSS to break out of the "Ollie look" — you need to use Ollie's own range boldly.

Companion files (load as needed):
- **`../reference/TOKENS.md`** — the canonical token values. *Don't restate them; look them up.* This file tells you *which* tokens to reach for; TOKENS.md tells you their exact values.
- **`../reference/MARKUP.md`** — the canonical section wrapper (`tagName:"section"`) that every archetype builds on.
- **`PRESETS.md`** — named art-direction points of view (the style variations + typography presets). Pick one before you build.
- **`ARCHETYPES.md`** — section archetypes with copy-ready block-markup skeletons.
- **`RUBRIC.md`** — the self-check you run against your output before finishing.

---

## Workflow

### Step 1 — Read the active design context first

Before designing, know what's already set globally so you stay coherent with the rest of the site. Check the active palette / style variation / typography preset (via `ollie/manage-global-styles` → `get`, see `../reference/ABILITIES.md`). **Identity-level decisions (palette, heading treatment, default spacing feel) should defer to the active global styles** — you express *per-page* variation on top of that, you don't re-invent the site's identity each page.

### Step 2 — Decide (do this before any markup)

Write **one short paragraph** stating:
1. **Purpose** — what this page/section is for and who reads it.
2. **Point of view** — the art-direction preset you're committing to (from `PRESETS.md`), or the site's active variation. Honor the active variation rather than fighting it.
3. **Differentiator** — the one memorable move that makes this not generic (an oversized condensed headline, a full-bleed dark statement section, a single sharp accent against neutrals).

> **Rule D-01:** Commit to a clear conceptual direction and execute it precisely. Bold and minimal both work — the failure mode is *no* direction, which produces the generic "AI slop" average. State the POV before generating because it tells you *which* token combinations to reach for.

### Step 3 — Sequence the page

Choose and order section archetypes from `ARCHETYPES.md` to fit the purpose. Think about **rhythm down the page**, not just individual sections.

> **Rule D-02 (rhythm):** No two adjacent sections may share the same combination of background color + vertical padding + content width. Alternate. Uniformity is the #1 tell of templated output. This is a *within-page* rule — apply it across the sections you generate now (you can't see prior pages or prior runs, so don't write rules that depend on them).

### Step 4 — Build each section by composing tokens

Use the standard wrapper (`../reference/MARKUP.md`) and vary only these knobs between sections: background slug, padding register, content width, heading treatment, blockGap, radius slug, shadow preset. Apply the dimension rules below.

### Step 5 — Self-check

Run `RUBRIC.md` against the output. Fix anything that fails before presenting. If you can't fix a failure on-system, the design is wrong — rework it, don't reach for custom CSS.

---

## Dimension rules (each stated with its reason)

**Typography** — the highest-signal lever, and the most under-used.
- **Rule T-01:** Use *contrast*, not timidity. Pair a heavy display heading against light body — heading at `var:custom|fontWeight|extra-bold` (800) or `black` (900), body at `regular` (425). The patterns mostly use 600 everywhere; that flatness is exactly the generic look. Extremes read as intentional.
- **Rule T-02:** Use the variable stretches — Ollie's biggest advantage over generic agents. `expanded` for airy/confident, `condensed` or `narrow` for editorial/dense headlines. The `narrow` stretch is almost never used in stock patterns; using it instantly differentiates.
- **Rule T-03:** For statement/hero headings, go large — `x-large` or `xx-large`. Size jumps of 2–3× between heading and body create hierarchy; 1.5× reads as muddy.
- **Rule T-04:** Heading line-height stays tight (`tight` 1.1 / `snug` 1.2); body stays readable (`body` 1.5 / `relaxed` 1.625). Body line length 45–85 characters — that's what the 740 constrained width is for.

**Color**
- **Rule DC-01:** Dominant neutrals + one sharp accent (≈60-30-10). The dominant surface is `base`/`tertiary`/`main`; text is `main`; reserve `primary` as the single expressive accent. Never spread `primary` evenly across a section — one accent moment per zone.
- **Rule DC-02:** Every background slug needs a paired, contrast-passing text slug (see pairing rules in `../reference/TOKENS.md`). Dark statement sections use `main` bg + `base`/`main-accent` text.
- **Rule DC-03:** Use a dark full-bleed section (`main` background) at least once on a long page as a rhythm/contrast beat — it's an on-system way to add drama.

**Space**
- **Rule DS-01:** Use the high end of the spacing scale for statement sections — `xx-large`, `xxx-large`, even `xxxx-large` vertical padding. These steps exist and are nearly unused in stock patterns. Generous space around a single element *is* the design.
- **Rule DS-02:** Vary `blockGap` deliberately for internal rhythm rather than defaulting everything to `small`.

**Layout / width**
- **Rule DL-01:** Move between the three width registers — constrained (740, for reading), wide (1260, for galleries/grids), full (for backgrounds/heroes). Shifting register between sections is a primary rhythm tool.
- **Rule DL-02:** Asymmetry reads as designed when it keeps a latent grid. Prefer unequal columns (e.g. 40/60) over always-equal thirds. A "three equal cards in a row" default is a generic tell — use it only when the content is genuinely a parallel set.

**Depth & detail**
- **Rule DX-01:** Use radius **slugs**, never raw px (the stock patterns hardcode px — do not copy that). Nested radius: child radius ≤ parent radius.
- **Rule DX-02:** Use separators (`is-style-separator-thin`), hairline borders, and shadow presets *compositionally*, not decoratively. One shadow weight per surface tier.
- **Rule DX-03:** Prefer one well-orchestrated effect (a single dark statement section, or one oversized headline) over many scattered flourishes.

---

## Hard "off-system / AI-slop" don'ts (on top of the skill's global Hard Rules)

- **No** uniform `medium` padding on every section (the flatness tell).
- **No** weight 600 on everything (use contrast).
- **No** default three-equal-cards unless the content is truly parallel.
- **No** `primary` accent on every element — one accent moment per zone.
- **No** custom CSS classes / Class Manager to achieve layout — everything must be visible and editable in the editor.
- **No** cross-generation memory rules — you can't see prior runs; vary *within* the page you're building now.

(The global Hard Rules in `SKILL.md` still apply: no `core/html`, no inline `<style>`, no hardcoded hex/size/spacing, no raw px radius, token slugs only.)

---

## Output contract

Emit valid WordPress block markup using `var:preset|…` / `var:custom|…` tokens throughout, structured with the canonical section wrapper from `../reference/MARKUP.md`. When the page is ready, persist it via the Abilities (`ollie/manage-posts` `create` with prepared markup, or `create_from_pattern` when seeding from a pattern). This layer produces the *design*; the Abilities persist it.
