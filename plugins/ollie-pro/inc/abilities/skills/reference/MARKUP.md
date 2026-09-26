# Ollie — Block Markup Best Practices

> Correct block-markup mechanics. The "Standard Page Section Pattern" below is the canonical section wrapper used everywhere — both pattern-based work and from-scratch design (`../design/ARCHETYPES.md`) build on it.

## Block Markup Best Practices

### Block Comment ↔ Inner HTML Sync Rule

WordPress block markup has two parts that **must stay in sync**: the block comment JSON (`<!-- wp:block {…} -->`) and the inner HTML. The block comment is the source of truth for the editor — it determines which settings, styles, and variations are shown as active in the sidebar. The inner HTML is what renders on the front end.

When setting a block style variation (`is-style-*`), `className` must appear in **both** places:
- In the comment JSON: `"className":"is-style-button-brand"`
- In the inner HTML class attribute: `class="… is-style-button-brand"`

If you only add it to the inner HTML, the front end will look correct but the editor will show no style selected, confusing users.

This rule applies to all block attributes — colors (`backgroundColor`, `textColor`), spacing, font sizes, `className`, `align`, etc. must be set in the comment JSON, with corresponding classes/styles in the inner HTML.

### Correct Color Usage
```html
<!-- ✅ Correct: using design token slug -->
<!-- wp:group {"backgroundColor":"primary","textColor":"base"} -->

<!-- ❌ Wrong: hardcoded hex -->
<!-- wp:group {"style":{"color":{"background":"#5344F4"}}} -->
```

### Correct Spacing Usage
```html
<!-- ✅ Correct: using spacing preset -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}}} -->

<!-- ❌ Wrong: hardcoded value -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"40px","bottom":"40px"}}}} -->
```

### Correct Font Size Usage
```html
<!-- ✅ Correct: using type scale slug -->
<!-- wp:heading {"fontSize":"x-large"} -->

<!-- ❌ Wrong: custom size -->
<!-- wp:heading {"style":{"typography":{"fontSize":"42px"}}} -->
```

### Standard Page Section Pattern
```html
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|x-large","bottom":"var:preset|spacing|x-large","left":"var:preset|spacing|medium","right":"var:preset|spacing|medium"}}}} -->
<section class="wp-block-group alignfull has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--x-large);padding-bottom:var(--wp--preset--spacing--x-large);padding-left:var(--wp--preset--spacing--medium);padding-right:var(--wp--preset--spacing--medium)">

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">

<!-- Content blocks go here -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->
```

Standard structure: outer full-width group with background color and vertical padding, containing an inner constrained-width group that holds the actual content blocks.

---

