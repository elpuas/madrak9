# RUBRIC.md — Self-Check Before Finishing

Run this against your output before presenting. Fix every failure. If you can't fix a failure on-system, the design is wrong — rework it, don't reach for custom CSS.

## On-system integrity (hard gates — any failure = reject)
- [ ] **No inline hex/rgb/hsl** anywhere. Every color is a `var:preset|color|<slug>`.
- [ ] **No custom font sizes** in px/rem. Every size is a `font-size` slug.
- [ ] **No raw px border radius.** Every radius is a `var:preset|border-radius|<slug>`; child radius ≤ parent.
- [ ] **No custom CSS classes for layout**, no `<style>`, no raw HTML. Core + Ollie blocks only.
- [ ] Every section uses the standard full → constrained/wide wrapper (ARCHETYPES §0).
- [ ] Every background slug has a paired, contrast-passing text slug set.

## Direction & differentiation
- [ ] A clear POV was committed to (one of the PRESETS) and is visible in the output.
- [ ] There is one identifiable differentiator (oversized headline, dark statement section, single sharp accent, distinctive stretch) — the page isn't a flat average.

## Rhythm (within this page)
- [ ] No two **adjacent** sections share the same background + padding register + content width.
- [ ] The page moves between width registers (constrained / wide / full).
- [ ] At least one contrast beat on a long page (a `main`/dark or gradient section).
- [ ] Spacing varies; not every section is `medium` or `large` padding. The high end (`xx-large`+) appears where a statement is intended.

## Typography
- [ ] Heading↔body weight contrast is real (e.g. 700–900 vs 425–500), not uniform 600.
- [ ] A variable stretch (`expanded`/`condensed`/`narrow`) is used intentionally where the POV calls for it.
- [ ] Heading sizes step clearly above body (2–3× on hero/statement headings).
- [ ] Heading line-height tight/snug; body line-height body/relaxed.
- [ ] Reading-width text sits in the constrained (740) register (≈45–85 chars/line).

## Color & emphasis
- [ ] Neutrals dominate; `primary` accent is used sparingly (≈one accent moment per zone), not on every element.
- [ ] One primary CTA per section (no section with competing equal CTAs).

## Detail & polish
- [ ] Separators/borders/shadows used compositionally, one shadow tier per surface level.
- [ ] Headings are hierarchical (single h1; h2/h3 nest correctly) for accessibility.
- [ ] Cards/grids only used for genuinely parallel content; unequal columns preferred for asymmetric content.

## Final
- [ ] Markup is valid block markup that will render identically front-end and in the editor, and is fully editable there (nothing hidden).
- [ ] Ready to persist via `ollie/manage-posts` (`create` with prepared markup, or `create_from_pattern` when seeding from a pattern).
