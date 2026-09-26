# Carousel Slides Ownership Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `ollie/carousel-slides` the sole owner of slide behavior, responsive settings, rendering configuration, and Embla runtime while retaining `ollie/carousel` as the composable Design Picker shell.

**Architecture:** The parent keeps composition, Core styles, pattern replacement, shared CSS, and the internal centered-navigation layout flag. Carousel Slides gains local behavior attributes, a dedicated inspector, validated multi-breakpoint controls, dynamic rendering data, and the view script. Navigation continues to discover the nearest Carousel boundary and controls that boundary's single direct Carousel Slides child.

**Tech Stack:** WordPress Block API v3, `@wordpress/block-editor`, `@wordpress/components`, `@wordpress/data`, PHP dynamic block rendering, Embla Carousel 8, Jest through `@wordpress/scripts`, SCSS.

## Global Constraints

- Carousel contains exactly one direct, removal-locked Carousel Slides block.
- Carousel Slides uses Core `style.spacing.blockGap` as the only slide-gap setting.
- Carousel supports arbitrary Core content and Carousel Navigation around the direct Slides child.
- Navigation nested inside slides remains functional.
- Responsive entries use max-width semantics, accept 320px through 3840px, reject duplicates, and are limited to five.
- Existing parent-owned behavior receives no compatibility layer because the feature is unreleased.
- The existing `npm run all` watch processes generate build artifacts; do not run a manual build.
- Do not create a git commit unless the user explicitly requests one.

---

### Task 1: Pure responsive model with unit coverage

**Files:**
- Create: `src/carousel-slides/responsive.js`
- Create: `src/carousel-slides/responsive.test.js`
- Modify: `package.json`

**Interfaces:**
- Produces: `normalizeBreakpoint(breakpoint) => object|null`
- Produces: `sortBreakpoints(breakpoints) => Array`
- Produces: `validateBreakpointWidth(width, breakpoints) => { valid: boolean, error: string }`
- Produces: `resolveResponsiveSettings(baseSettings, breakpoints, viewportWidth) => { slidesPerView: number, slidesPerGroup: number }`
- Consumed by: Carousel Slides breakpoint controls and frontend runtime.

- [ ] **Step 1: Add the unit-test script**

Add this script to `package.json`:

```json
"test:unit": "wp-scripts test-unit-js"
```

- [ ] **Step 2: Write failing responsive utility tests**

Create `src/carousel-slides/responsive.test.js` with cases for:

```javascript
import {
	resolveResponsiveSettings,
	sortBreakpoints,
	validateBreakpointWidth,
} from './responsive';

const breakpoints = [
	{ width: 1024, slidesPerView: 3, slidesPerGroup: 2 },
	{ width: 768, slidesPerView: 2, slidesPerGroup: 1 },
];

describe( 'carousel responsive settings', () => {
	it( 'sorts breakpoints from narrowest to widest', () => {
		expect( sortBreakpoints( breakpoints ).map( ( item ) => item.width ) )
			.toEqual( [ 768, 1024 ] );
	} );

	it( 'uses base settings above all breakpoints', () => {
		expect(
			resolveResponsiveSettings(
				{ slidesPerView: 5, slidesPerGroup: 3 },
				breakpoints,
				1200
			)
		).toEqual( { slidesPerView: 5, slidesPerGroup: 3 } );
	} );

	it( 'uses the most specific matching max-width entry', () => {
		expect(
			resolveResponsiveSettings(
				{ slidesPerView: 5, slidesPerGroup: 3 },
				breakpoints,
				600
			)
		).toEqual( { slidesPerView: 2, slidesPerGroup: 1 } );
	} );

	it( 'preserves a per-group-only override', () => {
		expect(
			resolveResponsiveSettings(
				{ slidesPerView: 3, slidesPerGroup: 1 },
				[ { width: 900, slidesPerView: 3, slidesPerGroup: 2 } ],
				800
			)
		).toEqual( { slidesPerView: 3, slidesPerGroup: 2 } );
	} );

	it.each( [ 319, 3841, '', 'abc' ] )(
		'rejects invalid width %p',
		( width ) => {
			expect( validateBreakpointWidth( width, [] ).valid ).toBe( false );
		}
	);

	it( 'rejects a duplicate and a sixth breakpoint', () => {
		expect(
			validateBreakpointWidth( 768, breakpoints ).valid
		).toBe( false );
		expect(
			validateBreakpointWidth(
				1200,
				[ 400, 600, 768, 900, 1024 ].map( ( width ) => ( { width } ) )
			).valid
		).toBe( false );
	} );
} );
```

- [ ] **Step 3: Run the tests and confirm the expected failure**

Run:

```bash
npm run test:unit -- src/carousel-slides/responsive.test.js --runInBand
```

Expected: FAIL because `responsive.js` does not exist.

- [ ] **Step 4: Implement the responsive model**

Create `src/carousel-slides/responsive.js`. Keep validation messages translatable in the UI; this utility returns stable reason codes:

```javascript
export const MIN_BREAKPOINT_WIDTH = 320;
export const MAX_BREAKPOINT_WIDTH = 3840;
export const MAX_BREAKPOINTS = 5;

const clampInteger = ( value, minimum, maximum, fallback ) => {
	const parsed = Number.parseInt( value, 10 );
	return Number.isFinite( parsed )
		? Math.min( maximum, Math.max( minimum, parsed ) )
		: fallback;
};

export function normalizeBreakpoint( breakpoint ) {
	if ( ! breakpoint || typeof breakpoint !== 'object' ) {
		return null;
	}

	const width = Number.parseInt( breakpoint.width, 10 );
	if (
		! Number.isFinite( width ) ||
		width < MIN_BREAKPOINT_WIDTH ||
		width > MAX_BREAKPOINT_WIDTH
	) {
		return null;
	}

	return {
		width,
		slidesPerView: clampInteger(
			breakpoint.slidesPerView,
			1,
			10,
			1
		),
		slidesPerGroup: clampInteger(
			breakpoint.slidesPerGroup,
			1,
			10,
			1
		),
	};
}

export function sortBreakpoints( breakpoints = [] ) {
	return breakpoints
		.map( normalizeBreakpoint )
		.filter( Boolean )
		.sort( ( first, second ) => first.width - second.width );
}

export function validateBreakpointWidth( value, breakpoints = [] ) {
	const width = Number.parseInt( value, 10 );
	if (
		! Number.isFinite( width ) ||
		width < MIN_BREAKPOINT_WIDTH ||
		width > MAX_BREAKPOINT_WIDTH
	) {
		return { valid: false, error: 'range' };
	}
	if (
		breakpoints.some(
			( breakpoint ) =>
				Number.parseInt( breakpoint.width, 10 ) === width
		)
	) {
		return { valid: false, error: 'duplicate' };
	}
	if ( breakpoints.length >= MAX_BREAKPOINTS ) {
		return { valid: false, error: 'limit' };
	}
	return { valid: true, error: '' };
}

export function resolveResponsiveSettings(
	baseSettings,
	breakpoints,
	viewportWidth
) {
	const base = {
		slidesPerView: clampInteger( baseSettings.slidesPerView, 1, 10, 1 ),
		slidesPerGroup: clampInteger(
			baseSettings.slidesPerGroup,
			1,
			10,
			1
		),
	};
	const match = sortBreakpoints( breakpoints ).find(
		( breakpoint ) => viewportWidth <= breakpoint.width
	);
	return match
		? {
				slidesPerView: match.slidesPerView,
				slidesPerGroup: match.slidesPerGroup,
		  }
		: base;
}
```

- [ ] **Step 5: Run the focused tests**

Run the command from Step 3.

Expected: PASS.

- [ ] **Step 6: Review checkpoint**

Confirm the utility has no WordPress or DOM dependencies and no source files still implement separate breakpoint sorting.

---

### Task 2: Move metadata and inspector ownership to Carousel Slides

**Files:**
- Modify: `src/carousel/block.json`
- Modify: `src/carousel-slides/block.json`
- Create: `src/carousel-slides/inspector.js`
- Create: `src/carousel-slides/breakpoint-controls.js`
- Modify: `src/carousel-slides/edit.js`
- Move: `src/carousel/carousel-inspector.js` to `src/carousel/design-inspector.js`
- Modify: `src/carousel/edit.js`

**Interfaces:**
- Consumes: Task 1 responsive validation and sorting.
- Produces: `CarouselSlidesInspector({ attributes, setAttributes })`.
- Produces: local Carousel Slides attributes consumed by render and runtime tasks.

- [ ] **Step 1: Move behavior attributes in block metadata**

Remove these from `src/carousel/block.json`:

```text
slidesPerView, slidesPerGroup, slideMaxWidth, speed, autoplay,
autoplaySpeed, pauseOnMouseEnter, fade, overflowVisible, rtl, breakpoints
```

Remove all parent-to-slides context providers. Retain only:

```json
"providesContext": {
	"ollie/carousel/centerNav": "centerNav"
}
```

Add the behavior attributes with their current defaults to `src/carousel-slides/block.json`, change `ancestor` to:

```json
"parent": [ "ollie/carousel" ]
```

Remove `usesContext` and add:

```json
"viewScript": "file:./view.js"
```

- [ ] **Step 2: Build the breakpoint manager UI**

Create `src/carousel-slides/breakpoint-controls.js` using the Advanced Grid interaction:

- Local state: `showAddBreakpoint`, `newBreakpointValue`, `selectedBreakpoint`, `error`.
- Common-width buttons for 600, 768, and 1024.
- `validateBreakpointWidth()` before mutation.
- New entries default to one slide per view and one per group.
- Store each entry directly in the `breakpoints` array.
- Sort with `sortBreakpoints()`.
- Deleting an entry clears selection.
- Render selected entry controls with ranges 1 through 10.

Export:

```javascript
export default function BreakpointControls( {
	breakpoints = [],
	onChange,
} ) {
	// Advanced Grid-compatible add/select/delete interaction.
}
```

- [ ] **Step 3: Create the Slides inspector**

Move the behavior controls from `src/carousel/carousel-inspector.js` into `src/carousel-slides/inspector.js`, then rename the reduced parent component to `src/carousel/design-inspector.js` and update its import in `src/carousel/edit.js`.

Use:

```javascript
export default function CarouselSlidesInspector( {
	attributes,
	setAttributes,
} ) {
	return (
		<InspectorControls>
			<PanelBody title={ __( 'Carousel Settings', 'ollie-pro' ) }>
				{ /* local behavior controls */ }
			</PanelBody>
			<BreakpointControls
				breakpoints={ attributes.breakpoints }
				onChange={ ( breakpoints ) => setAttributes( { breakpoints } ) }
			/>
		</InspectorControls>
	);
}
```

Do not recreate slide spacing here. The Core spacing panel supplied by `spacing.blockGap` is the only gap control.

- [ ] **Step 4: Reduce the parent inspector to design selection**

Rename its conceptual responsibility to design only. Remove:

- `slidesBlock`
- `updateBlockAttributes`
- the experimental `SpacingSizesControl`
- every behavior panel and control

The component should accept only:

```javascript
{
	carouselPatterns,
	onPatternSelect,
}
```

- [ ] **Step 5: Read local attributes in Carousel Slides edit**

In `src/carousel-slides/edit.js`, replace parent context reads with:

```javascript
const {
	slidesPerView,
	slideMaxWidth,
	overflowVisible,
	rtl,
} = attributes;
```

Render `CarouselSlidesInspector` beside the viewport. Add local classes for overflow and RTL and retain `--ollie-slides-per-view`, `--ollie-slide-max-width`, and the resolved Core block-gap property.

- [ ] **Step 6: Remove parent behavior plumbing**

In `src/carousel/edit.js`:

- Remove behavior destructuring and behavior-derived classes.
- Remove Slides block discovery used only by the temporary gap proxy.
- Keep slide count only for Design Picker state.
- Keep pattern replacement and blank creation.
- Pass no behavior data to the parent inspector.

- [ ] **Step 7: Check editor diagnostics**

Run IDE diagnostics on all files changed in this task. Fix introduced lint, import, and formatting errors without broad unrelated formatting.

---

### Task 3: Enforce the single direct Slides child and canonical creation

**Files:**
- Modify: `src/carousel/edit.js`
- Modify: `inc/carousel/patterns/hero-carousel.php`
- Modify: `inc/carousel/patterns/featured-testimonial-carousel.php`
- Modify: `inc/carousel/patterns/quote-testimonial-carousel.php`
- Modify: `inc/carousel/patterns/testimonial-carousel.php`
- Modify: `inc/carousel/patterns/team-member-carousel.php`
- Modify: `inc/carousel/patterns/feature-cards-carousel.php`
- Modify: `inc/carousel/patterns/logos-carousel.php`

**Interfaces:**
- Consumes: Carousel Slides local attribute schema from Task 2.
- Produces: one direct, locked Slides child in every bundled design and blank insertion.

- [ ] **Step 1: Normalize blank creation**

Create Carousel Slides with:

```javascript
const slidesContainer = createBlock(
	'ollie/carousel-slides',
	{ lock: { move: false, remove: true } },
	[ slide ]
);
```

Keep Carousel Navigation blocks movable and preserve selection of the first paragraph.

- [ ] **Step 2: Move pattern behavior attributes**

For every pattern:

1. Remove behavior JSON from `<!-- wp:ollie/carousel ... -->`.
2. Add the same behavior JSON to its direct `<!-- wp:ollie/carousel-slides ... -->`.
3. Add:

```json
"lock":{"move":false,"remove":true}
```

4. Keep `style.spacing.blockGap` on Carousel Slides when a pattern needs an explicit gap; otherwise omit it to inherit Core's global block gap.

- [ ] **Step 3: Correct pattern breakpoint data**

Preserve all existing entries, sort them by width, and ensure each includes:

```json
{
	"width": 768,
	"slidesPerView": 2,
	"slidesPerGroup": 1
}
```

Do not retain duplicate or malformed entries.

- [ ] **Step 4: Verify pattern grammar**

Search all carousel patterns for parent-owned behavior keys. Expected: no matches on Carousel opening comments and matching values present on Slides comments.

Use WordPress block parsing if the local WordPress CLI/environment provides it; otherwise inspect opening/closing block grammar and rely on editor insertion checks during final verification.

---

### Task 4: Move dynamic rendering and frontend runtime

**Files:**
- Modify: `src/carousel/render.php`
- Modify: `src/carousel-slides/render.php`
- Move: `src/carousel/view.js` to `src/carousel-slides/view.js`
- Modify: `inc/carousel/carousel.php`
- Delete stale generated files after the watcher stops emitting them: `build/carousel/view.js`, `build/carousel/view.js.map`, `build/carousel/view.asset.php`

**Interfaces:**
- Consumes: local Slides attributes and Task 1 responsive resolver.
- Produces: one independently initialized viewport per Carousel boundary.
- Coordinates: all Carousel Navigation descendants in the nearest parent boundary.

- [ ] **Step 1: Minimize the parent render**

`src/carousel/render.php` should:

```php
$classes = array( 'ollie-carousel-block' );
if ( ! empty( $attributes['centerNav'] ) ) {
	$classes[] = 'ollie-carousel-center-nav';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array( 'class' => implode( ' ', $classes ) )
);

echo '<div ' . $wrapper_attributes . '>';
echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</div>';
```

Remove behavior parsing, breakpoint encoding, RTL classes, and runtime data.

- [ ] **Step 2: Render sanitized Slides configuration**

In `src/carousel-slides/render.php`:

- Read all behavior from `$attributes`.
- Clamp values to the specification ranges.
- Filter breakpoint entries to valid widths and values.
- Preserve all valid breakpoints.
- Resolve explicit Core block gap and CSS custom properties.
- Pass `data-ollie-*` values through `get_block_wrapper_attributes()` rather than concatenating strings.
- Add viewport classes for overflow and RTL.

The wrapper data must include:

```text
slides-per-view, slides-per-group, speed, autoplay, autoplay-speed,
pause-on-hover, fade, rtl, breakpoints
```

- [ ] **Step 3: Re-root the view script**

Move the runtime entry to `src/carousel-slides/view.js`.

Change initialization from:

```javascript
root.querySelectorAll( '.ollie-carousel-block' )
```

to:

```javascript
root.querySelectorAll( '.ollie-carousel-viewport' )
```

Each viewport finds:

```javascript
const boundary = viewport.closest( '.ollie-carousel-block' );
```

All arrows and pagination are queried from `boundary`, preserving nested navigation. Store teardown state on the viewport, not the parent.

- [ ] **Step 4: Replace resize behavior with media-query listeners**

Use Task 1's resolver and create one `matchMedia('(max-width: Npx)')` listener per valid breakpoint. Every listener calls a single `applyResponsiveSettings()` function.

That function:

1. Resolves effective settings from `window.innerWidth`.
2. Compares both `slidesPerView` and `slidesPerGroup` with current values.
3. Updates CSS custom properties.
4. Reinitializes Embla only when a value changed.
5. Reconciles fade so it is active only when effective `slidesPerView === 1`.

Cleanup removes every media-query listener.

- [ ] **Step 5: Restore accessible autoplay controls**

Enable the existing `setupAutoplayToggle()` call when autoplay is active and reduced motion is not requested. Confirm:

- Button text and `aria-label` switch between localized pause/play strings.
- Keyboard activation works through the native button.
- Autoplay stays disabled for reduced-motion users.
- Teardown removes listeners.

- [ ] **Step 6: Update script localization**

In `inc/carousel/carousel.php`, change:

```php
$handle = 'ollie-carousel-view-script';
```

to:

```php
$handle = 'ollie-carousel-slides-view-script';
```

Keep the existing localized strings.

- [ ] **Step 7: Verify runtime isolation**

Test with two Carousels on one page. Controls in the first boundary must never move the second viewport. Nested duplicate navigation in a slide must remain synchronized with sibling controls.

- [ ] **Step 8: Remove stale parent runtime artifacts**

After the watcher emits `build/carousel-slides/view.js`, delete the three old generated parent view files listed above if they remain. Confirm the parent `build/carousel/block.json` no longer references them.

---

### Task 5: Simplify editor and frontend styles

**Files:**
- Modify: `src/carousel/editor.scss`
- Modify: `src/carousel/style.scss`

**Interfaces:**
- Consumes: Slides-owned classes and custom properties from Tasks 2 and 4.
- Produces: one shared stylesheet loaded by Carousel.

- [ ] **Step 1: Remove generated per-view classes**

Delete `.ollie-carousel-shows-1` through `.ollie-carousel-shows-10` editor loops. Use one formula:

```scss
$slide-width: calc(
	100% / var(--ollie-slides-per-view, 1) -
	var(--ollie-effective-slide-gap) *
		(var(--ollie-slides-per-view, 1) - 1) /
		var(--ollie-slides-per-view, 1)
);
```

Apply it to slide flex basis and appender sizing.

- [ ] **Step 2: Scope slide state locally**

Move overflow and RTL viewport selectors to Slides-owned classes. Keep `.ollie-carousel-center-nav` on the parent because it is composition state.

Coordinate frontend RTL by toggling a runtime class on the nearest boundary; coordinate editor RTL with a boundary `:has(.ollie-carousel-rtl)` selector.

- [ ] **Step 3: Remove obsolete styles**

Search and remove:

```text
--ollie-slide-gap
ollie-carousel-single
behavior-only parent classes
unused autoplay styles after the control is restored
```

Do not remove navigation selectors required by nav blocks nested inside slides.

- [ ] **Step 4: Check compiled CSS**

Wait for the watcher and verify generated LTR and RTL CSS contain the custom-property formula without malformed multiline custom-property output.

---

### Task 6: Documentation and complete verification

**Files:**
- Modify: `docs/superpowers/specs/2026-07-13-carousel-composable-blocks-design.md`
- Keep until implementation is accepted: `docs/superpowers/specs/2026-07-20-carousel-slides-ownership-design.md`
- Keep until implementation is accepted: `docs/superpowers/plans/2026-07-20-carousel-slides-ownership.md`
- Verify generated files under `build/carousel/` and `build/carousel-slides/`

**Interfaces:**
- Consumes: all earlier tasks.
- Produces: release-ready source, generated assets, accurate architecture documentation, and verification evidence.

- [ ] **Step 1: Update the original architecture document**

Replace its parent-owned behavior description with the approved ownership boundary. Remove stale references to removed settings such as `loop` and `spaceBetween`.

- [ ] **Step 2: Run focused unit tests**

Run:

```bash
npm run test:unit -- src/carousel-slides/responsive.test.js --runInBand
```

Expected: PASS.

- [ ] **Step 3: Validate metadata**

Run:

```bash
node -e "for (const file of ['src/carousel/block.json','src/carousel-slides/block.json','src/carousel-nav/block.json']) JSON.parse(require('fs').readFileSync(file)); console.log('Block metadata JSON valid')"
```

Expected: `Block metadata JSON valid`.

- [ ] **Step 4: Check edited JavaScript and IDE diagnostics**

Run the repository's WordPress lint command only on changed source files. Separate newly introduced errors from known baseline lint issues. Run IDE diagnostics on all edited source files and fix introduced errors.

- [ ] **Step 5: Check PHP syntax**

Run `php -l` on changed PHP files when a PHP binary is available. If the shell lacks PHP, use the Local site's PHP binary or clearly report that syntax verification was unavailable.

- [ ] **Step 6: Confirm watcher output**

Inspect the existing `npm run all` watcher output. Expected: successful compilation after the final source edit.

Confirm:

- `build/carousel/block.json` has no slide behavior attributes or Slides context.
- `build/carousel/` no longer owns a view entry after stale generated artifacts are removed.
- `build/carousel-slides/block.json` owns behavior and `viewScript`.
- `build/carousel-slides/view.js` exists.
- Generated bundles contain no temporary parent gap proxy.

- [ ] **Step 7: Search for stale ownership**

Search source and generated files for:

```text
ollie/carousel/slidesPerView
ollie/carousel/slideMaxWidth
ollie/carousel/overflowVisible
ollie/carousel/breakpoints
data-ollie-space-between
--ollie-slide-gap
```

Expected: no matches except historical documentation that is intentionally retained and clearly marked.

- [ ] **Step 8: Complete manual acceptance checks**

In both editor and frontend, verify every scenario listed in the approved design specification, especially multiple breakpoints, nested navigation, centered navigation, RTL, reduced motion, pattern switching, and two independent Carousel blocks.

- [ ] **Step 9: Remove temporary planning artifacts if requested**

After the user accepts the implementation, remove this plan and the 2026-07-20 design specification if they want no temporary specs retained. Do not remove the permanent composable-block architecture documentation.

- [ ] **Step 10: Final review checkpoint**

Summarize source ownership changes, verification results, any unavailable checks, and generated artifacts. Do not commit unless the user explicitly requests it.
