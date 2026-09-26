# Carousel Dynamic Slides Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Static/Dynamic content mode to `ollie/carousel-slides` so a carousel can render one slide per post from a configurable query, using the first `ollie/slide` as a designable per-post template.

**Architecture:** The block keeps its existing static path untouched. A new `sourceType` attribute switches `render.php` to run a `WP_Query` and render the first `ollie/slide` inner block once per post with `postId`/`postType` block context (the `core/post-template` mechanism, owned by us). The editor gains a "Content" inspector panel (mode toggle + query controls built from public `@wordpress/components` + `@wordpress/core-data` primitives) and a dynamic preview that fetches posts via `useEntityRecords` and renders the template per post — the first post editable, the rest as block previews. Zero changes to the Embla frontend runtime (`view.js`): dynamic slides land as `.ollie-carousel-slide` children of `.ollie-carousel-container`, which is all it requires.

**Tech Stack:** WordPress block API v3, `@wordpress/scripts` (watch processes already running via `npm run all` — **do not run manual builds**), Jest via `npm run test:unit`, PHP 7.4+ WordPress plugin conventions.

**Spec:** `docs/superpowers/specs/2026-07-21-carousel-dynamic-slides-design.md`

## Global Constraints

- Text domain is `ollie-pro` for every user-facing string (JS `__()` and PHP `__()`).
- Do not run `npm run build` / `wp-scripts build` — watch processes rebuild automatically.
- PHP follows the file's existing WPCS style (tabs, snake_case, Yoda conditions, escaping comments as in `src/carousel-slides/render.php`).
- Commit messages are short and plain (repo style: "Carousel work", "Fixes"). **No Co-Authored-By trailer.**
- `@wordpress/*` imports are externalized by wp-scripts (no package.json additions needed, including `@wordpress/core-data`).
- Frontend DOM contract (must never break): `.ollie-carousel-viewport` > `.ollie-carousel-container` > direct children each with class `ollie-carousel-slide`.
- Order options are the four core Query Loop combos (date/title × asc/desc). The spec mentioned menu order/random; those are dropped for v1 because the REST posts endpoint can't preview them (this plan amends the spec).
- Static mode behavior must be byte-identical to today (regression-checked in Task 7).

## File Structure

- `src/carousel-slides/block.json` — add `sourceType` + `query` attributes (modify)
- `src/carousel-slides/query-utils.js` — pure helpers: defaults, order options, attribute→REST arg mapping (create)
- `src/carousel-slides/query-utils.test.js` — Jest tests for the above (create)
- `inc/carousel/carousel.php` — `ollie_carousel_build_query_args()` PHP mapping + `ollie_carousel_query_args` filter (modify)
- `src/carousel-slides/render.php` — dynamic render branch (modify)
- `src/carousel-slides/content-settings.js` — "Content" inspector panel: mode toggle + query controls + filters (create)
- `src/carousel-slides/inspector.js` — mount the Content panel (modify)
- `src/carousel-slides/dynamic-preview.js` — editor preview for dynamic mode (create)
- `src/carousel-slides/edit.js` — branch static/dynamic, `is-dynamic` class (modify)
- `src/carousel/editor.scss` — dynamic-mode editor strip styles (modify)

---

### Task 1: Query attributes + `query-utils.js` (TDD)

**Files:**
- Modify: `src/carousel-slides/block.json`
- Create: `src/carousel-slides/query-utils.js`
- Test: `src/carousel-slides/query-utils.test.js`

**Interfaces:**
- Produces: `DEFAULT_QUERY` (object), `ORDER_OPTIONS` (array of `{ label, value }` where value is `'date/desc'` etc.), `queryToRestArgs( query, { taxonomies, currentPostId } )` → REST args object. Later tasks import all three from `./query-utils`.

- [ ] **Step 1: Write the failing tests**

Create `src/carousel-slides/query-utils.test.js`:

```js
import { DEFAULT_QUERY, ORDER_OPTIONS, queryToRestArgs } from './query-utils';

describe( 'queryToRestArgs', () => {
	it( 'maps the default query to REST args', () => {
		expect( queryToRestArgs( DEFAULT_QUERY ) ).toEqual( {
			per_page: 6,
			offset: 0,
			order: 'desc',
			orderby: 'date',
		} );
	} );

	it( 'clamps perPage and offset to sane values', () => {
		expect(
			queryToRestArgs( { ...DEFAULT_QUERY, perPage: 99, offset: -5 } )
		).toMatchObject( { per_page: 24, offset: 0 } );
		expect(
			queryToRestArgs( { ...DEFAULT_QUERY, perPage: 0 } )
		).toMatchObject( { per_page: 1 } );
	} );

	it( 'falls back to date/desc for unknown order values', () => {
		expect(
			queryToRestArgs( { ...DEFAULT_QUERY, orderBy: 'rand', order: 'sideways' } )
		).toMatchObject( { orderby: 'date', order: 'desc' } );
	} );

	it( 'includes search and author when set', () => {
		expect(
			queryToRestArgs( { ...DEFAULT_QUERY, search: 'hello', author: '3,7' } )
		).toMatchObject( { search: 'hello', author: [ 3, 7 ] } );
	} );

	it( 'omits search and author when empty', () => {
		const args = queryToRestArgs( DEFAULT_QUERY );
		expect( args ).not.toHaveProperty( 'search' );
		expect( args ).not.toHaveProperty( 'author' );
	} );

	it( 'maps sticky for the post type only', () => {
		expect(
			queryToRestArgs( { ...DEFAULT_QUERY, sticky: 'only' } )
		).toMatchObject( { sticky: true } );
		expect(
			queryToRestArgs( { ...DEFAULT_QUERY, sticky: 'exclude' } )
		).toMatchObject( { sticky: false } );
		expect(
			queryToRestArgs( {
				...DEFAULT_QUERY,
				postType: 'page',
				sticky: 'only',
			} )
		).not.toHaveProperty( 'sticky' );
	} );

	it( 'maps taxQuery term IDs onto taxonomy rest_base params', () => {
		const taxonomies = [
			{ slug: 'category', rest_base: 'categories' },
			{ slug: 'post_tag', rest_base: 'tags' },
		];
		expect(
			queryToRestArgs(
				{ ...DEFAULT_QUERY, taxQuery: { category: [ 4, 5 ], post_tag: [] } },
				{ taxonomies }
			)
		).toMatchObject( { categories: [ 4, 5 ] } );
	} );

	it( 'ignores taxQuery entries with no matching taxonomy', () => {
		const args = queryToRestArgs(
			{ ...DEFAULT_QUERY, taxQuery: { ghost_tax: [ 1 ] } },
			{ taxonomies: [] }
		);
		expect( args ).not.toHaveProperty( 'ghost_tax' );
	} );

	it( 'excludes the current post when excludeCurrent is on', () => {
		expect(
			queryToRestArgs(
				{ ...DEFAULT_QUERY, excludeCurrent: true },
				{ currentPostId: 42 }
			)
		).toMatchObject( { exclude: [ 42 ] } );
		expect(
			queryToRestArgs( { ...DEFAULT_QUERY, excludeCurrent: true }, {} )
		).not.toHaveProperty( 'exclude' );
	} );
} );

describe( 'ORDER_OPTIONS', () => {
	it( 'offers the four core order combos', () => {
		expect( ORDER_OPTIONS.map( ( o ) => o.value ) ).toEqual( [
			'date/desc',
			'date/asc',
			'title/asc',
			'title/desc',
		] );
	} );
} );
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npm run test:unit -- src/carousel-slides/query-utils.test.js`
Expected: FAIL — cannot find module `./query-utils`.

- [ ] **Step 3: Implement `query-utils.js`**

Create `src/carousel-slides/query-utils.js`:

```js
import { __ } from '@wordpress/i18n';

export const DEFAULT_QUERY = {
	postType: 'post',
	perPage: 6,
	offset: 0,
	order: 'desc',
	orderBy: 'date',
	sticky: '',
	taxQuery: {},
	author: '',
	search: '',
	excludeCurrent: false,
};

export const ORDER_OPTIONS = [
	{ label: __( 'Newest to oldest', 'ollie-pro' ), value: 'date/desc' },
	{ label: __( 'Oldest to newest', 'ollie-pro' ), value: 'date/asc' },
	{ label: __( 'A → Z', 'ollie-pro' ), value: 'title/asc' },
	{ label: __( 'Z → A', 'ollie-pro' ), value: 'title/desc' },
];

const ORDERBY_VALUES = [ 'date', 'title' ];
const ORDER_VALUES = [ 'asc', 'desc' ];

/**
 * Map the block's query attribute to REST collection args for
 * useEntityRecords( 'postType', query.postType, args ).
 *
 * @param {Object} query                 Query attribute (merge with DEFAULT_QUERY first).
 * @param {Object} options
 * @param {Array}  options.taxonomies    Taxonomy objects ({ slug, rest_base }) for the post type.
 * @param {?number} options.currentPostId Currently edited post ID, if any.
 * @return {Object} REST query args.
 */
export function queryToRestArgs( query, { taxonomies = [], currentPostId = null } = {} ) {
	const parsedPerPage = parseInt( query.perPage ?? 6, 10 );
	const perPage = Number.isNaN( parsedPerPage )
		? 6
		: Math.min( 24, Math.max( 1, parsedPerPage ) );
	const offset = Math.max( 0, parseInt( query.offset, 10 ) || 0 );
	const orderby = ORDERBY_VALUES.includes( query.orderBy ) ? query.orderBy : 'date';
	const order = ORDER_VALUES.includes( query.order ) ? query.order : 'desc';

	const args = {
		per_page: perPage,
		offset,
		order,
		orderby,
	};

	if ( query.search ) {
		args.search = query.search;
	}

	if ( query.author ) {
		const authorIds = query.author
			.split( ',' )
			.map( ( id ) => parseInt( id, 10 ) )
			.filter( Boolean );
		if ( authorIds.length ) {
			args.author = authorIds;
		}
	}

	// The `sticky` collection param only exists on the posts endpoint.
	if ( 'post' === query.postType && query.sticky ) {
		args.sticky = 'only' === query.sticky;
	}

	Object.entries( query.taxQuery ?? {} ).forEach( ( [ slug, termIds ] ) => {
		const taxonomy = taxonomies.find( ( t ) => t.slug === slug );
		if ( taxonomy?.rest_base && Array.isArray( termIds ) && termIds.length ) {
			args[ taxonomy.rest_base ] = termIds;
		}
	} );

	if ( query.excludeCurrent && currentPostId ) {
		args.exclude = [ currentPostId ];
	}

	return args;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npm run test:unit -- src/carousel-slides/query-utils.test.js`
Expected: PASS (10 tests).

- [ ] **Step 5: Add the attributes to `block.json`**

In `src/carousel-slides/block.json`, after the `"align"` attribute (line 11-14), add:

```json
"sourceType": {
	"type": "string",
	"default": "static"
},
"query": {
	"type": "object",
	"default": {
		"postType": "post",
		"perPage": 6,
		"offset": 0,
		"order": "desc",
		"orderBy": "date",
		"sticky": "",
		"taxQuery": {},
		"author": "",
		"search": "",
		"excludeCurrent": false
	}
},
```

(Resulting order: `align`, `sourceType`, `query`, `slidesPerView`, …)

- [ ] **Step 6: Commit**

```bash
git add src/carousel-slides/block.json src/carousel-slides/query-utils.js src/carousel-slides/query-utils.test.js
git commit -m "Add carousel dynamic query attributes and REST arg mapping"
```

---

### Task 2: PHP query builder in `inc/carousel/carousel.php`

**Files:**
- Modify: `inc/carousel/carousel.php` (add function after `ollie_carousel_register_blocks()`)

**Interfaces:**
- Produces: `ollie_carousel_build_query_args( array $query, int $current_post_id = 0 ): array` — WP_Query args. Task 3's render.php calls it. It must mirror Task 1's `queryToRestArgs` semantics (same clamps, same fallbacks) so editor preview and frontend agree.

- [ ] **Step 1: Add the function**

In `inc/carousel/carousel.php`, after the `ollie_carousel_register_blocks()` function, add:

```php
/**
 * Build WP_Query args from the carousel-slides block's `query` attribute.
 *
 * Mirrors queryToRestArgs() in src/carousel-slides/query-utils.js — keep the
 * two in sync so the editor preview matches the frontend.
 *
 * @param array $query           Query attribute from the block.
 * @param int   $current_post_id Current singular post ID (for excludeCurrent), 0 if none.
 * @return array WP_Query args.
 */
function ollie_carousel_build_query_args( $query, $current_post_id = 0 ) {
	$query = is_array( $query ) ? $query : array();

	// A removed/invalid post type is passed through sanitized rather than
	// falling back to 'post': WP_Query then matches nothing, which mirrors
	// the editor's "unavailable" state (spec: no output, not wrong output).
	$post_type = isset( $query['postType'] ) && is_string( $query['postType'] ) && '' !== $query['postType']
		? sanitize_key( $query['postType'] )
		: 'post';

	$per_page = isset( $query['perPage'] ) && is_numeric( $query['perPage'] )
		? min( 24, max( 1, intval( $query['perPage'] ) ) )
		: 6;
	$offset   = isset( $query['offset'] ) && is_numeric( $query['offset'] )
		? max( 0, intval( $query['offset'] ) )
		: 0;

	$orderby = isset( $query['orderBy'] ) && in_array( $query['orderBy'], array( 'date', 'title' ), true )
		? $query['orderBy']
		: 'date';
	$order   = isset( $query['order'] ) && 'asc' === strtolower( $query['order'] ) ? 'ASC' : 'DESC';

	$args = array(
		'post_type'           => $post_type,
		'post_status'         => 'publish',
		'posts_per_page'      => $per_page,
		'offset'              => $offset,
		'orderby'             => $orderby,
		'order'               => $order,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	if ( ! empty( $query['search'] ) && is_string( $query['search'] ) ) {
		$args['s'] = $query['search'];
	}

	if ( ! empty( $query['author'] ) ) {
		$author_ids = wp_parse_id_list( $query['author'] );
		if ( ! empty( $author_ids ) ) {
			$args['author__in'] = $author_ids;
		}
	}

	$post_not_in = array();

	if ( 'post' === $post_type && ! empty( $query['sticky'] ) ) {
		$sticky_ids = get_option( 'sticky_posts', array() );
		if ( 'only' === $query['sticky'] ) {
			// An empty post__in returns all posts, so force no results instead.
			$args['post__in'] = ! empty( $sticky_ids ) ? array_map( 'intval', $sticky_ids ) : array( 0 );
		} elseif ( 'exclude' === $query['sticky'] && ! empty( $sticky_ids ) ) {
			$post_not_in = array_map( 'intval', $sticky_ids );
		}
	}

	if ( ! empty( $query['excludeCurrent'] ) && $current_post_id > 0 ) {
		$post_not_in[] = $current_post_id;
	}

	if ( ! empty( $post_not_in ) ) {
		$args['post__not_in'] = array_values( array_unique( $post_not_in ) );
	}

	if ( ! empty( $query['taxQuery'] ) && is_array( $query['taxQuery'] ) ) {
		$tax_query = array();
		foreach ( $query['taxQuery'] as $taxonomy => $term_ids ) {
			$term_ids = wp_parse_id_list( $term_ids );
			if ( taxonomy_exists( $taxonomy ) && ! empty( $term_ids ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_ids,
				);
			}
		}
		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
	}

	return $args;
}
```

- [ ] **Step 2: Lint**

Run: `php -l inc/carousel/carousel.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add inc/carousel/carousel.php
git commit -m "Add PHP query args builder for dynamic carousel slides"
```

---

### Task 3: Dynamic render path in `render.php`

**Files:**
- Modify: `src/carousel-slides/render.php` (replace lines 135-140, the final echo section)

**Interfaces:**
- Consumes: `ollie_carousel_build_query_args()` from Task 2; `sourceType`/`query` attributes from Task 1.
- Produces: the `ollie_carousel_query_args` filter (`apply_filters( 'ollie_carousel_query_args', array $args, WP_Block $block )`).

- [ ] **Step 1: Replace the output section**

In `src/carousel-slides/render.php`, replace:

```php
echo '<div ' . $wrapper_attributes . '>';
echo '<div class="ollie-carousel-container">';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks markup.
echo $content;
echo '</div>';
echo '</div>';
```

with:

```php
$slides_markup = $content;

if ( 'dynamic' === ( $attributes['sourceType'] ?? 'static' ) ) {
	$slides_markup  = '';
	$slide_template = null;

	foreach ( ( $block->parsed_block['innerBlocks'] ?? array() ) as $inner_block ) {
		if ( 'ollie/slide' === ( $inner_block['blockName'] ?? '' ) ) {
			$slide_template = $inner_block;
			break;
		}
	}

	if ( $slide_template && function_exists( 'ollie_carousel_build_query_args' ) ) {
		$current_post_id = is_singular() ? get_queried_object_id() : 0;
		$query_args      = ollie_carousel_build_query_args( $attributes['query'] ?? array(), $current_post_id );

		/**
		 * Filter the WP_Query args used for dynamic carousel slides.
		 *
		 * @param array    $query_args WP_Query arguments.
		 * @param WP_Block $block      The carousel-slides block instance.
		 */
		$query_args = apply_filters( 'ollie_carousel_query_args', $query_args, $block );

		$slides_query = new WP_Query( $query_args );

		while ( $slides_query->have_posts() ) {
			$slides_query->the_post();
			$slide_block = new WP_Block(
				$slide_template,
				array(
					'postId'   => get_the_ID(),
					'postType' => get_post_type(),
				)
			);
			$slides_markup .= $slide_block->render();
		}
		wp_reset_postdata();
	}
}

echo '<div ' . $wrapper_attributes . '>';
echo '<div class="ollie-carousel-container">';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks markup.
echo $slides_markup;
echo '</div>';
echo '</div>';
```

Notes for the implementer:
- `$block->parsed_block['innerBlocks']` is the raw parsed template — rendering it through `new WP_Block( ..., $available_context )` makes `postId`/`postType` context available to every descendant (Post Title, Post Featured Image, etc.). `ollie/slide` has a static save, so each rendered slide keeps its `.ollie-carousel-slide` wrapper — the Embla DOM contract holds with zero `view.js` changes.
- `the_post()` sets global post data so classic filters (e.g. `the_content`) behave inside post blocks.
- An empty query result renders an empty `.ollie-carousel-container` — Task 7 verifies the frontend script tolerates this.

- [ ] **Step 2: Lint**

Run: `php -l src/carousel-slides/render.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Smoke-test on the local site**

The watch process copies `render.php` to `build/` automatically (wait a few seconds after saving, then confirm):

Run: `grep -c "ollie_carousel_query_args" build/carousel-slides/render.php`
Expected: `1` (the apply_filters call). If `0`, the watcher hasn't picked it up — check `npm run all` is running; do not run a manual build.

- [ ] **Step 4: Commit**

```bash
git add src/carousel-slides/render.php
git commit -m "Render dynamic carousel slides from query with per-post block context"
```

---

### Task 4: Content panel — mode toggle + core query controls

**Files:**
- Create: `src/carousel-slides/content-settings.js`
- Modify: `src/carousel-slides/inspector.js`

**Interfaces:**
- Consumes: `DEFAULT_QUERY`, `ORDER_OPTIONS` from `./query-utils` (Task 1).
- Produces: `<ContentSettings attributes setAttributes />` default export, mounted by `inspector.js`. Task 5 adds the Filters section to this same file via the `QueryFilters` placeholder marked below.

- [ ] **Step 1: Create `content-settings.js`**

```js
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { DEFAULT_QUERY, ORDER_OPTIONS } from './query-utils';

export default function ContentSettings( { attributes, setAttributes } ) {
	const sourceType = attributes.sourceType ?? 'static';
	const query = { ...DEFAULT_QUERY, ...( attributes.query ?? {} ) };

	const updateQuery = ( patch ) =>
		setAttributes( { query: { ...query, ...patch } } );

	const postTypes = useSelect( ( select ) => {
		const types =
			select( coreStore ).getPostTypes( { per_page: -1 } ) ?? [];
		return types.filter(
			( type ) =>
				type.viewable && type.rest_base && 'attachment' !== type.slug
		);
	}, [] );

	const postTypeOptions = postTypes.map( ( type ) => ( {
		label: type.labels?.singular_name ?? type.name,
		value: type.slug,
	} ) );
	// Keep a stored-but-unavailable post type visible rather than silently switching.
	if ( ! postTypeOptions.some( ( option ) => option.value === query.postType ) ) {
		postTypeOptions.push( {
			label: `${ query.postType } (${ __( 'unavailable', 'ollie-pro' ) })`,
			value: query.postType,
		} );
	}

	return (
		<PanelBody title={ __( 'Content', 'ollie-pro' ) } initialOpen>
			<ToggleGroupControl
				label={ __( 'Slide content', 'ollie-pro' ) }
				value={ sourceType }
				isBlock
				onChange={ ( value ) =>
					setAttributes( { sourceType: value } )
				}
				help={
					'dynamic' === sourceType
						? __(
								'Slides are generated from the query below. Your first slide is used as the template for each post.',
								'ollie-pro'
						  )
						: undefined
				}
			>
				<ToggleGroupControlOption
					value="static"
					label={ __( 'Static', 'ollie-pro' ) }
				/>
				<ToggleGroupControlOption
					value="dynamic"
					label={ __( 'Dynamic', 'ollie-pro' ) }
				/>
			</ToggleGroupControl>
			{ 'dynamic' === sourceType && (
				<>
					<SelectControl
						label={ __( 'Post type', 'ollie-pro' ) }
						value={ query.postType }
						options={ postTypeOptions }
						onChange={ ( value ) =>
							updateQuery( {
								postType: value,
								taxQuery: {},
								sticky: '',
							} )
						}
					/>
					<SelectControl
						label={ __( 'Order by', 'ollie-pro' ) }
						value={ `${ query.orderBy }/${ query.order }` }
						options={ ORDER_OPTIONS }
						onChange={ ( value ) => {
							const [ orderBy, order ] = value.split( '/' );
							updateQuery( { orderBy, order } );
						} }
					/>
					{ 'post' === query.postType && (
						<SelectControl
							label={ __( 'Sticky posts', 'ollie-pro' ) }
							value={ query.sticky }
							options={ [
								{
									label: __( 'Include', 'ollie-pro' ),
									value: '',
								},
								{
									label: __( 'Exclude', 'ollie-pro' ),
									value: 'exclude',
								},
								{
									label: __( 'Only', 'ollie-pro' ),
									value: 'only',
								},
							] }
							onChange={ ( value ) =>
								updateQuery( { sticky: value } )
							}
						/>
					) }
					<RangeControl
						label={ __( 'Number of slides', 'ollie-pro' ) }
						value={ query.perPage }
						onChange={ ( value ) =>
							updateQuery( { perPage: value } )
						}
						min={ 1 }
						max={ 24 }
					/>
					<TextControl
						type="number"
						label={ __( 'Offset', 'ollie-pro' ) }
						value={ query.offset }
						onChange={ ( value ) =>
							updateQuery( {
								offset: Math.max(
									0,
									Number.parseInt( value, 10 ) || 0
								),
							} )
						}
					/>
					{ /* Task 5 mounts <QueryFilters> here. */ }
				</>
			) }
		</PanelBody>
	);
}
```

- [ ] **Step 2: Mount it in `inspector.js`**

In `src/carousel-slides/inspector.js`, add the import at the top:

```js
import ContentSettings from './content-settings';
```

and inside the returned `<InspectorControls>` (line 136), add the panel **above** the existing "Carousel Settings" `PanelBody`:

```js
	return (
		<InspectorControls>
			<ContentSettings
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>
			<PanelBody
				title={ __( 'Carousel Settings', 'ollie-pro' ) }
				initialOpen
			>
```

- [ ] **Step 3: Verify in the editor**

With the watch running, open the local site's editor (a page containing a Carousel block), select the Carousel Slides block (click a slide, then select parent). Confirm:
- A "Content" panel appears above "Carousel Settings" with a Static/Dynamic toggle.
- Switching to Dynamic reveals Post type / Order by / Sticky / Number of slides / Offset controls, and the canvas still shows the static slides (dynamic preview arrives in Task 6).
- Switching back to Static hides the query controls; no console errors in the browser devtools.

- [ ] **Step 4: Run the existing test suite (guard against regressions)**

Run: `npm run test:unit`
Expected: PASS (all suites, including the ones that existed before this plan).

- [ ] **Step 5: Commit**

```bash
git add src/carousel-slides/content-settings.js src/carousel-slides/inspector.js
git commit -m "Add Content panel with static/dynamic toggle and query controls"
```

---

### Task 5: Filters — taxonomy, author, keyword, exclude current

**Files:**
- Modify: `src/carousel-slides/content-settings.js` (add components + mount at the Task 4 placeholder)

**Interfaces:**
- Consumes: `updateQuery`/`query` from Task 4's component scope.
- Produces: `QueryFilters` component (internal to this file) writing `query.taxQuery` (`{ [taxonomySlug]: number[] }`), `query.author` (comma-joined ID string), `query.search`, `query.excludeCurrent`.

- [ ] **Step 1: Add imports**

In `src/carousel-slides/content-settings.js`, extend the existing imports:

```js
import {
	FormTokenField,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
```

(`useSelect`, `coreStore`, `__`, and the query-utils imports are already present.)

- [ ] **Step 2: Add the filter components**

Add above `export default function ContentSettings`:

```js
function TaxonomyFilter( { taxonomy, termIds, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const debouncedSetSearch = useDebounce( setSearch, 250 );

	const { searchResults, existingTerms } = useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			return {
				searchResults: search
					? getEntityRecords( 'taxonomy', taxonomy.slug, {
							search,
							per_page: 20,
							_fields: 'id,name',
							context: 'view',
					  } )
					: [],
				existingTerms: termIds.length
					? getEntityRecords( 'taxonomy', taxonomy.slug, {
							include: termIds,
							per_page: termIds.length,
							_fields: 'id,name',
							context: 'view',
					  } )
					: [],
			};
		},
		[ search, termIds, taxonomy.slug ]
	);

	const nameToId = {};
	[ ...( searchResults ?? [] ), ...( existingTerms ?? [] ) ].forEach(
		( term ) => {
			nameToId[ term.name ] = term.id;
		}
	);

	const onTermsChange = ( tokens ) => {
		const ids = tokens
			.map( ( token ) =>
				'string' === typeof token ? nameToId[ token ] : token.id
			)
			.filter( Boolean );
		onChange( [ ...new Set( ids ) ] );
	};

	return (
		<FormTokenField
			label={ taxonomy.name }
			value={ ( existingTerms ?? [] ).map( ( term ) => ( {
				id: term.id,
				value: term.name,
			} ) ) }
			suggestions={ ( searchResults ?? [] ).map(
				( term ) => term.name
			) }
			onInputChange={ debouncedSetSearch }
			onChange={ onTermsChange }
			__experimentalShowHowTo={ false }
		/>
	);
}

function AuthorFilter( { value, onChange } ) {
	const authorIds = value
		? value.split( ',' ).map( ( id ) => parseInt( id, 10 ) )
		: [];
	const authors = useSelect(
		( select ) =>
			select( coreStore ).getUsers( {
				per_page: -1,
				_fields: 'id,name',
				context: 'view',
			} ) ?? [],
		[]
	);

	const nameToId = {};
	authors.forEach( ( author ) => {
		nameToId[ author.name ] = author.id;
	} );

	return (
		<FormTokenField
			label={ __( 'Authors', 'ollie-pro' ) }
			value={ authors
				.filter( ( author ) => authorIds.includes( author.id ) )
				.map( ( author ) => ( { id: author.id, value: author.name } ) ) }
			suggestions={ authors.map( ( author ) => author.name ) }
			onChange={ ( tokens ) => {
				const ids = tokens
					.map( ( token ) =>
						'string' === typeof token ? nameToId[ token ] : token.id
					)
					.filter( Boolean );
				onChange( [ ...new Set( ids ) ].join( ',' ) );
			} }
			__experimentalShowHowTo={ false }
		/>
	);
}

function QueryFilters( { query, updateQuery } ) {
	const taxonomies = useSelect(
		( select ) =>
			(
				select( coreStore ).getTaxonomies( {
					type: query.postType,
					per_page: -1,
				} ) ?? []
			).filter(
				( taxonomy ) =>
					taxonomy.rest_base &&
					false !== taxonomy.visibility?.show_ui
			),
		[ query.postType ]
	);

	const hasTaxQuery = Object.values( query.taxQuery ?? {} ).some(
		( termIds ) => termIds?.length
	);

	return (
		<ToolsPanel
			label={ __( 'Filters', 'ollie-pro' ) }
			resetAll={ () =>
				updateQuery( {
					taxQuery: {},
					author: '',
					search: '',
					excludeCurrent: false,
				} )
			}
		>
			{ taxonomies.length > 0 && (
				<ToolsPanelItem
					label={ __( 'Taxonomies', 'ollie-pro' ) }
					hasValue={ () => hasTaxQuery }
					onDeselect={ () => updateQuery( { taxQuery: {} } ) }
				>
					{ taxonomies.map( ( taxonomy ) => (
						<TaxonomyFilter
							key={ taxonomy.slug }
							taxonomy={ taxonomy }
							termIds={ query.taxQuery?.[ taxonomy.slug ] ?? [] }
							onChange={ ( termIds ) =>
								updateQuery( {
									taxQuery: {
										...query.taxQuery,
										[ taxonomy.slug ]: termIds,
									},
								} )
							}
						/>
					) ) }
				</ToolsPanelItem>
			) }
			<ToolsPanelItem
				label={ __( 'Authors', 'ollie-pro' ) }
				hasValue={ () => !! query.author }
				onDeselect={ () => updateQuery( { author: '' } ) }
			>
				<AuthorFilter
					value={ query.author }
					onChange={ ( value ) => updateQuery( { author: value } ) }
				/>
			</ToolsPanelItem>
			<ToolsPanelItem
				label={ __( 'Keyword', 'ollie-pro' ) }
				hasValue={ () => !! query.search }
				onDeselect={ () => updateQuery( { search: '' } ) }
			>
				<TextControl
					label={ __( 'Keyword', 'ollie-pro' ) }
					value={ query.search }
					onChange={ ( value ) => updateQuery( { search: value } ) }
				/>
			</ToolsPanelItem>
			<ToolsPanelItem
				label={ __( 'Exclude current post', 'ollie-pro' ) }
				hasValue={ () => !! query.excludeCurrent }
				onDeselect={ () => updateQuery( { excludeCurrent: false } ) }
			>
				<ToggleControl
					label={ __( 'Exclude current post', 'ollie-pro' ) }
					help={ __(
						'Hide the post being viewed from the carousel.',
						'ollie-pro'
					) }
					checked={ query.excludeCurrent }
					onChange={ ( value ) =>
						updateQuery( { excludeCurrent: value } )
					}
				/>
			</ToolsPanelItem>
		</ToolsPanel>
	);
}
```

- [ ] **Step 3: Mount at the Task 4 placeholder**

Replace `{ /* Task 5 mounts <QueryFilters> here. */ }` with:

```js
					<QueryFilters
						query={ query }
						updateQuery={ updateQuery }
					/>
```

- [ ] **Step 4: Verify in the editor**

In the editor with a Dynamic carousel selected:
- The "Filters" tools panel appears; enabling Taxonomies shows a Categories and a Tags token field for the `post` type; typing shows term suggestions after a pause; selecting adds a token.
- Authors token field suggests site users; Keyword and Exclude current post work.
- Switching Post type to Page removes the taxonomy fields (pages have no public taxonomies) and clears prior taxQuery.
- No console errors.

- [ ] **Step 5: Commit**

```bash
git add src/carousel-slides/content-settings.js
git commit -m "Add taxonomy, author, keyword, and exclude-current query filters"
```

---

### Task 6: Dynamic editor preview

**Files:**
- Create: `src/carousel-slides/dynamic-preview.js`
- Modify: `src/carousel-slides/edit.js`
- Modify: `src/carousel/editor.scss`

**Interfaces:**
- Consumes: `queryToRestArgs`, `DEFAULT_QUERY` from `./query-utils`.
- Produces: `<DynamicSlides clientId attributes />` default export used by `edit.js` when `sourceType === 'dynamic'`.

- [ ] **Step 1: Create `dynamic-preview.js`**

```js
import { __ } from '@wordpress/i18n';
import { useState, useEffect, memo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore, useEntityRecords } from '@wordpress/core-data';
import {
	BlockContextProvider,
	useInnerBlocksProps,
	store as blockEditorStore,
	__experimentalUseBlockPreview as useBlockPreview,
} from '@wordpress/block-editor';
import { Spinner, Notice } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { queryToRestArgs, DEFAULT_QUERY } from './query-utils';

/**
 * Editable slide template. Renders the block's inner blocks (the first slide
 * is the visible template; extra preserved static slides are hidden via CSS).
 */
function TemplateSlide() {
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'ollie-carousel-dynamic-template' },
		{ templateLock: 'insert', renderAppender: false }
	);
	return <div { ...innerBlocksProps } />;
}

function SlidePreviewInner( { blocks, onSelect } ) {
	const blockPreviewProps = useBlockPreview
		? useBlockPreview( {
				blocks,
				props: { className: 'ollie-carousel-slide-preview' },
		  } )
		: null;

	if ( ! blockPreviewProps ) {
		return null;
	}

	return (
		<div
			{ ...blockPreviewProps }
			tabIndex={ 0 }
			role="button"
			onClick={ onSelect }
			onKeyDown={ ( event ) => {
				if ( 'Enter' === event.key || ' ' === event.key ) {
					onSelect();
				}
			} }
		/>
	);
}
const SlidePreview = memo( SlidePreviewInner );

export default function DynamicSlides( { clientId, attributes } ) {
	const query = { ...DEFAULT_QUERY, ...( attributes.query ?? {} ) };

	const { templateBlocks, slideCount } = useSelect(
		( select ) => {
			const innerBlocks =
				select( blockEditorStore ).getBlock( clientId )?.innerBlocks ??
				[];
			const firstSlide = innerBlocks.find(
				( block ) => 'ollie/slide' === block.name
			);
			return {
				templateBlocks: firstSlide ? [ firstSlide ] : [],
				slideCount: innerBlocks.length,
			};
		},
		[ clientId ]
	);

	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

	// Insert a default template when switching to dynamic with no slides.
	useEffect( () => {
		if ( 0 === slideCount ) {
			const slide = createBlock( 'ollie/slide', {}, [
				createBlock( 'core/post-featured-image' ),
				createBlock( 'core/post-title' ),
				createBlock( 'core/post-excerpt' ),
			] );
			replaceInnerBlocks( clientId, [ slide ], false );
		}
	}, [ slideCount, clientId, replaceInnerBlocks ] );

	const { taxonomies, currentPostId } = useSelect(
		( select ) => ( {
			taxonomies:
				select( coreStore ).getTaxonomies( {
					type: query.postType,
					per_page: -1,
				} ) ?? [],
			currentPostId:
				select( 'core/editor' )?.getCurrentPostId?.() ?? null,
		} ),
		[ query.postType ]
	);

	const { records: posts, isResolving } = useEntityRecords(
		'postType',
		query.postType,
		queryToRestArgs( query, { taxonomies, currentPostId } )
	);

	const [ activePostId, setActivePostId ] = useState( null );
	const resolvedActiveId = posts?.some(
		( post ) => post.id === activePostId
	)
		? activePostId
		: posts?.[ 0 ]?.id;

	if ( isResolving ) {
		return (
			<div className="ollie-carousel-dynamic-loading">
				<Spinner />
			</div>
		);
	}

	if ( ! posts?.length ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __(
					'No posts found. Adjust the query in the Content panel.',
					'ollie-pro'
				) }
			</Notice>
		);
	}

	return (
		<>
			{ posts.map( ( post ) => (
				<BlockContextProvider
					key={ post.id }
					value={ { postType: post.type, postId: post.id } }
				>
					{ post.id === resolvedActiveId ? (
						<TemplateSlide />
					) : (
						<SlidePreview
							blocks={ templateBlocks }
							onSelect={ () => setActivePostId( post.id ) }
						/>
					) }
				</BlockContextProvider>
			) ) }
		</>
	);
}
```

Implementation notes:
- `__experimentalUseBlockPreview` is the same API `core/post-template` relies on; the `useBlockPreview ? … : null` guard degrades to showing only the editable slide if a future Gutenberg removes it (conditional hook call is acceptable here because availability cannot change within a session; add `// eslint-disable-next-line react-hooks/rules-of-hooks` above the call if the linter objects).
- `select( 'core/editor' )` is accessed by string so the site editor (no current post) resolves to `null` without a hard dependency.

- [ ] **Step 2: Branch in `edit.js`**

In `src/carousel-slides/edit.js`:

Add the import:

```js
import DynamicSlides from './dynamic-preview';
```

In the `Edit` component, read the mode (after line 38's destructure):

```js
	const isDynamic = 'dynamic' === attributes.sourceType;
```

Add the modifier class to `blockProps` (the `className` array in `useBlockProps`, line 121):

```js
		className: [
			'ollie-carousel-viewport',
			isDynamic && 'is-dynamic',
			overflowVisible && 'ollie-carousel-overflow-visible',
			rtl && 'ollie-carousel-rtl',
		]
```

Replace the `<InnerBlocks …/>` usage (lines 148-155) with:

```js
			<div { ...blockProps } ref={ mergedRef }>
				{ isDynamic ? (
					<DynamicSlides
						clientId={ clientId }
						attributes={ attributes }
					/>
				) : (
					<InnerBlocks
						orientation="horizontal"
						allowedBlocks={ ALLOWED_BLOCKS }
						templateLock={ false }
						renderAppender={ renderAppender }
					/>
				) }
			</div>
```

(The "Add New Slide"/"Duplicate Previous" appender disappears in dynamic mode automatically because `<InnerBlocks>` is not rendered.)

- [ ] **Step 3: Editor styles**

In `src/carousel/editor.scss`, add at the end of the file:

```scss
/* Dynamic mode: the block wrapper itself becomes the horizontal strip. */
.wp-block-ollie-carousel-slides.is-dynamic {
	display: flex;
	align-items: stretch;
	gap: var( --ollie-slide-block-gap, 1rem );
	overflow-x: auto;

	> .ollie-carousel-dynamic-template,
	> .ollie-carousel-slide-preview {
		flex: 0 0
			calc(
				( 100% - ( var( --ollie-slides-per-view, 3 ) - 1 ) *
					var( --ollie-slide-block-gap, 1rem ) ) /
					var( --ollie-slides-per-view, 3 )
			);
		min-width: 0;
	}

	> .ollie-carousel-slide-preview {
		cursor: pointer;
	}

	/* Hide preserved static slides — only the first slide is the template. */
	.ollie-carousel-dynamic-template
		> [data-type='ollie/slide']
		~ [data-type='ollie/slide'] {
		display: none;
	}

	.ollie-carousel-dynamic-loading {
		display: flex;
		justify-content: center;
		padding: 2rem;
		width: 100%;
	}
}
```

- [ ] **Step 4: Verify in the editor**

On the local site with the watch running:
- Insert a Carousel, pick a design, select Carousel Slides, switch Content → Dynamic: the strip shows real posts; the first is editable (add a Post Date block inside — every preview updates); clicking another post's preview makes it the active editable one.
- With zero slides (delete all slides first, then switch to Dynamic): the default Featured Image + Title + Excerpt template is inserted automatically.
- Switch Dynamic → Static: the original slides reappear unchanged (check with a carousel that had 3 distinct static slides before toggling).
- Change Number of slides / Order by / a Category filter: the preview refetches accordingly.
- No console errors; if the layout looks broken (nested layout CSS from the static rules at `src/carousel/editor.scss:53` leaking in), adjust those selectors to exclude `.is-dynamic` rather than restructuring the DOM.

- [ ] **Step 5: Frontend verification**

View a page with a Dynamic carousel on the frontend:
- One slide per post renders with real post data; Embla arrows/dots/autoplay work; each slide element has class `ollie-carousel-slide`.
- Set a query that matches zero posts (e.g. impossible keyword): the page must not throw a JS error. Check `src/carousel-slides/view.js` `initCarousel` — if Embla init with an empty container throws or misbehaves, add a guard at the top of `initCarousel` (after the container is resolved):

```js
	const container = viewport.querySelector( '.ollie-carousel-container' );
	if ( ! container || container.children.length === 0 ) {
		return;
	}
```

(Adapt to the actual local variable names in `initCarousel`; only add this if the empty case actually errors or renders broken nav.)

- [ ] **Step 6: Run the full test suite**

Run: `npm run test:unit`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/carousel-slides/dynamic-preview.js src/carousel-slides/edit.js src/carousel/editor.scss
git commit -m "Add dynamic slides editor preview with per-post template rendering"
```

---

### Task 7: Full verification pass + static regression

**Files:**
- No planned changes — fixes only if verification fails.

- [ ] **Step 1: Static regression pass**

On the local site: open an existing page using a static carousel (or insert one from each of the 7 carousel patterns). Confirm editor and frontend behave exactly as before this branch's changes: slides add/duplicate, autoplay, fade, RTL, breakpoints, nav arrows/dots.

- [ ] **Step 2: Dynamic end-to-end matrix**

Verify on the frontend (and skim the editor preview for parity):

| Scenario | Expected |
| --- | --- |
| Default dynamic query (6 latest posts) | 6 slides, newest first |
| `perPage` 3 + `offset` 2 | Skips 2 newest, shows next 3 |
| Category filter | Only posts in that category |
| Author filter | Only that author's posts |
| Keyword filter | Only matching posts |
| Sticky "Only" with no sticky posts | Zero slides, no JS errors |
| Exclude current post, carousel in a post's content | That post absent |
| Post type: Page | Pages render; sticky control hidden |
| RTL + fade (1 per view) + tablet/mobile breakpoints | All behave as with static slides |
| Two carousels on one page (one static, one dynamic) | Both init independently |

- [ ] **Step 3: Editor↔frontend parity spot-check**

For the category-filter scenario, confirm the same posts appear in the same order in editor preview and frontend. If they differ, the `queryToRestArgs` (JS) vs `ollie_carousel_build_query_args` (PHP) mapping has drifted — fix the divergent side and add a Jest case pinning the JS behavior.

- [ ] **Step 4: Run everything**

Run: `npm run test:unit && php -l inc/carousel/carousel.php && php -l src/carousel-slides/render.php`
Expected: all PASS / no syntax errors.

- [ ] **Step 5: Commit any verification fixes**

```bash
git add -A src/ inc/
git commit -m "Carousel dynamic slides fixes from verification pass"
```

(Skip if no fixes were needed.)
