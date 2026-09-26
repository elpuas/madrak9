# ARCHETYPES.md — Section Archetypes & Markup Skeletons

Building blocks for composing a page from scratch. Each is a complete on-system section. **Vary the knobs (background, padding register, width, heading treatment) between sections** to satisfy the rhythm rule (D-02). All markup uses token slugs only — no inline hex, no px, no custom CSS. Look up exact token values in `../reference/TOKENS.md`.

---

## §0 — The standard section wrapper (use for EVERY section)

This is the canonical Ollie section wrapper (see `../reference/MARKUP.md`): a full-width semantic `section` group carrying the background + vertical padding, wrapping an inner constrained/wide group that holds the content.

```html
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"tertiary","style":{"spacing":{"padding":{"top":"var:preset|spacing|xx-large","bottom":"var:preset|spacing|xx-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"}}}} -->
<section class="wp-block-group alignfull has-tertiary-background-color has-background" style="padding-top:var(--wp--preset--spacing--xx-large);padding-bottom:var(--wp--preset--spacing--xx-large);padding-left:var(--wp--preset--spacing--medium);padding-right:var(--wp--preset--spacing--medium)">
  <!-- wp:group {"layout":{"type":"constrained"}} -->
  <div class="wp-block-group">
    <!-- inner content here -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
```
**Knobs to vary per section:** `backgroundColor` (base / tertiary / main / primary-alt-accent / a gradient) · padding `top`/`bottom` register (medium → xxxx-large) · inner `layout` width (constrained / wide via `contentSize` / full) · heading family+weight+size · `blockGap` · radius slug + shadow on cards.

---

## §1 — Hero (statement opener)

Lead the page. Go large on type and space; this is where the differentiator lives. Width constrained or full; padding `xxx-large`. Eyebrow → display heading (`x-large`/`xx-large`, heavy weight, a stretch) → lead paragraph (`medium`) → buttons (one brand + one light).

```html
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|xxx-large","bottom":"var:preset|spacing|xxx-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"},"blockGap":"var:preset|spacing|medium"}}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--xxx-large);padding-bottom:var(--wp--preset--spacing--xxx-large);padding-left:var(--wp--preset--spacing--medium);padding-right:var(--wp--preset--spacing--medium)">
  <!-- wp:group {"layout":{"type":"constrained"}} -->
  <div class="wp-block-group">
    <!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"1px"}},"textColor":"secondary","fontSize":"x-small"} -->
    <p class="has-secondary-color has-text-color has-x-small-font-size" style="text-transform:uppercase;letter-spacing:1px">Eyebrow label</p>
    <!-- /wp:paragraph -->
    <!-- wp:heading {"level":1,"style":{"typography":{"fontWeight":"800","lineHeight":"1.1","fontFamily":"var(--wp--preset--font-family--condensed)"}},"fontSize":"xx-large"} -->
    <h1 class="wp-block-heading has-xx-large-font-size" style="font-weight:800;line-height:1.1;font-family:var(--wp--preset--font-family--condensed)">A headline that commits to a point of view</h1>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"textColor":"secondary","fontSize":"medium"} -->
    <p class="has-secondary-color has-text-color has-medium-font-size">A lead sentence that earns the whitespace around it.</p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|small","margin":{"top":"var:preset|spacing|small"}}},"layout":{"type":"flex"}} -->
    <div class="wp-block-buttons">
      <!-- wp:button {"className":"is-style-button-brand"} --><div class="wp-block-button is-style-button-brand"><a class="wp-block-button__link wp-element-button">Primary action</a></div><!-- /wp:button -->
      <!-- wp:button {"className":"is-style-button-light"} --><div class="wp-block-button is-style-button-light"><a class="wp-block-button__link wp-element-button">Secondary</a></div><!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
```

---

## §2 — Feature split (asymmetric text + media)

Two unequal columns. **Use 40/60 or 60/40, not 50/50** (Rule DL-02). Alternate which side the media is on between successive feature splits. Inner group set to wide via `contentSize:"1260px"`.

```html
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-large","bottom":"var:preset|spacing|x-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"}}}} -->
<section class="wp-block-group alignfull" style="...">
  <!-- wp:group {"layout":{"type":"constrained","contentSize":"1260px"}} -->
  <div class="wp-block-group">
    <!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|x-large"}}}} -->
    <div class="wp-block-columns are-vertically-aligned-center">
      <!-- wp:column {"verticalAlignment":"center","width":"40%"} -->
      <div class="wp-block-column is-vertically-aligned-center" style="flex-basis:40%">
        <!-- wp:heading {"style":{"typography":{"fontWeight":"700","lineHeight":"1.1"}},"fontSize":"large"} -->
        <h2 class="wp-block-heading has-large-font-size" style="font-weight:700;line-height:1.1">A focused benefit</h2>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"secondary"} --><p class="has-secondary-color has-text-color">Supporting copy kept to a readable measure.</p><!-- /wp:paragraph -->
      </div>
      <!-- /wp:column -->
      <!-- wp:column {"width":"60%"} -->
      <div class="wp-block-column" style="flex-basis:60%">
        <!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"var:preset|border-radius|lg"}}} --><figure class="wp-block-image size-large has-custom-border"><img alt="" style="border-radius:var(--wp--preset--border-radius--lg)"/></figure><!-- /wp:image -->
      </div>
      <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
```

---

## §3 — Stat band (numbers)

A tight row of figures. Weighty `xx-large` numbers + small labels. Good as a rhythm beat; put it on `main` (dark) for a contrast moment (Rule DC-03).

```html
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"main","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-large","bottom":"var:preset|spacing|x-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"}}}} -->
<section class="wp-block-group alignfull has-base-color has-main-background-color has-text-color has-background" style="...">
  <!-- wp:group {"layout":{"type":"constrained","contentSize":"1260px"}} -->
  <div class="wp-block-group">
    <!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|large"}}}} -->
    <div class="wp-block-columns">
      <!-- repeat per stat -->
      <!-- wp:column -->
      <div class="wp-block-column">
        <!-- wp:heading {"style":{"typography":{"fontWeight":"800","lineHeight":"1"}},"fontSize":"xx-large"} --><h3 class="wp-block-heading has-xx-large-font-size" style="font-weight:800;line-height:1">12k+</h3><!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"main-accent","fontSize":"small"} --><p class="has-main-accent-color has-text-color has-small-font-size">Label for the figure</p><!-- /wp:paragraph -->
      </div>
      <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
```

---

## §4 — Editorial text block (constrained statement)

A single, narrow, oversized passage. Pure whitespace + type — the most on-system way to feel "designed." Constrained width, `xxx-large` padding.

```html
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|xxx-large","bottom":"var:preset|spacing|xxx-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"}}}} -->
<section class="wp-block-group alignfull" style="...">
  <!-- wp:group {"layout":{"type":"constrained"}} -->
  <div class="wp-block-group">
    <!-- wp:heading {"textAlign":"center","style":{"typography":{"fontWeight":"500","lineHeight":"1.2","fontFamily":"var(--wp--preset--font-family--expanded)"}},"fontSize":"large"} -->
    <h2 class="wp-block-heading has-text-align-center has-large-font-size" style="font-weight:500;line-height:1.2;font-family:var(--wp--preset--font-family--expanded)">One idea, given room to breathe, says more than a wall of features.</h2>
    <!-- /wp:heading -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
```

---

## §5 — Card grid (parallel set)

Only when content is genuinely parallel (Rule DL-02). Wide register. Cards use a radius **slug** + one shadow tier; `is-style-column-box-shadow` gives the built-in card treatment.

```html
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"tertiary","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-large","bottom":"var:preset|spacing|x-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"}}}} -->
<section class="wp-block-group alignfull has-tertiary-background-color has-background" style="...">
  <!-- wp:group {"layout":{"type":"constrained","contentSize":"1260px"}} -->
  <div class="wp-block-group">
    <!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|medium"}}}} -->
    <div class="wp-block-columns">
      <!-- repeat per card -->
      <!-- wp:column {"className":"is-style-column-box-shadow","backgroundColor":"base","style":{"spacing":{"padding":"var:preset|spacing|large"},"border":{"radius":"var:preset|border-radius|xl"}}} -->
      <div class="wp-block-column is-style-column-box-shadow has-base-background-color has-background" style="border-radius:var(--wp--preset--border-radius--xl);padding:var(--wp--preset--spacing--large)">
        <!-- wp:outermost/icon-block /-->
        <!-- wp:heading {"fontSize":"medium","style":{"typography":{"fontWeight":"700"}}} --><h3 class="wp-block-heading has-medium-font-size" style="font-weight:700">Card title</h3><!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"secondary","fontSize":"small"} --><p class="has-secondary-color has-text-color has-small-font-size">Card body copy.</p><!-- /wp:paragraph -->
      </div>
      <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
```

---

## §6 — CTA (closer)

A focused conversion moment. One primary action (a section with 3 CTAs has no CTA). Often on `primary-alt-accent` or `main` for contrast, or a gradient.

```html
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"primary-alt-accent","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|xx-large","bottom":"var:preset|spacing|xx-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"},"blockGap":"var:preset|spacing|medium"}}} -->
<section class="wp-block-group alignfull has-base-color has-primary-alt-accent-background-color has-text-color has-background" style="...">
  <!-- wp:group {"layout":{"type":"constrained"}} -->
  <div class="wp-block-group">
    <!-- wp:heading {"textAlign":"center","style":{"typography":{"fontWeight":"700","lineHeight":"1.1"}},"fontSize":"x-large"} --><h2 class="wp-block-heading has-text-align-center has-x-large-font-size" style="font-weight:700;line-height:1.1">Ready to start?</h2><!-- /wp:heading -->
    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-button-light"} --><div class="wp-block-button is-style-button-light"><a class="wp-block-button__link wp-element-button">One clear action</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
```

---

## Other archetypes (compose from the above)
- **Logo wall / social proof** — wide group, muted, small logos.
- **Testimonial** — constrained, oversized quote (`medium`/`large`, lighter weight), attribution in `secondary` `small`.
- **FAQ** — constrained, `core/details` blocks, `is-style-separator-thin` between rows.
- **Pricing** — wide columns; highlight one tier via `primary` border/background; one CTA per tier.

## Example page sequences (note the rhythm — no two adjacent sections match)
- **Landing:** Hero (constrained, xxx-large, base) → Logo wall (wide, large, tertiary) → Feature split A (wide, x-large, base, media-right) → Feature split B (wide, x-large, tertiary, media-left) → Stat band (constrained, x-large, **main/dark**) → Pricing (wide, x-large, base) → CTA (constrained, xx-large, **primary-alt-accent**).
- **About:** Hero (constrained, xxx-large) → Editorial statement (constrained, xxx-large, tertiary) → Team grid (wide, x-large, base) → Values stat band (constrained, x-large, main) → CTA.
