<?php
/**
 * Carousel block registration and pattern registration.
 *
 * @package ollie-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load arrow icon helpers for server-side rendering.
require_once __DIR__ . '/arrow-icons.php';

/**
 * Register the carousel and slide blocks.
 */
function ollie_carousel_register_blocks() {
	// The legacy standalone Ollie Carousel plugin registers the same block
	// name with a conflicting layout model. Registering twice triggers
	// _doing_it_wrong and mixes both stylesheets; defer to it when active.
	if ( WP_Block_Type_Registry::get_instance()->is_registered( 'ollie/carousel' ) ) {
		return;
	}

	register_block_type( OLPO_PATH . '/build/carousel' );
	register_block_type( OLPO_PATH . '/build/carousel-nav' );
	register_block_type( OLPO_PATH . '/build/carousel-slides' );
	register_block_type( OLPO_PATH . '/build/carousel-slide' );
}
add_action( 'init', 'ollie_carousel_register_blocks' );

/**
 * Kebab-case a spacing preset slug without depending on WP's private
 * `_wp_to_kebab_case()` (underscore-prefixed core internals carry no
 * back-compat guarantee, and this runs on every carousel render).
 *
 * @param string $slug Raw preset slug.
 * @return string Kebab-cased slug.
 */
function ollie_carousel_kebab_case( $slug ) {
	if ( function_exists( '_wp_to_kebab_case' ) ) {
		return _wp_to_kebab_case( $slug );
	}

	$slug = preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', (string) $slug );
	$slug = preg_replace( '/[^a-zA-Z0-9]+/', '-', $slug );

	return strtolower( trim( $slug, '-' ) );
}

/**
 * Track nesting depth of dynamic carousel renders.
 *
 * A dynamic carousel's slide template can itself contain a dynamic carousel
 * (directly, or indirectly through a synced pattern or post content). Without
 * a cutoff, that recursion renders roughly perPage^depth slides and exhausts
 * memory — the same class of bug WP core guards against in post-content and
 * template-part blocks.
 *
 * @param int $delta +1 when entering a dynamic render, -1 when leaving.
 * @return int Current depth after applying the delta.
 */
function ollie_carousel_render_depth( $delta ) {
	static $depth = 0;
	$depth = max( 0, $depth + intval( $delta ) );
	return $depth;
}

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

	// Only viewable post types may be queried. This blocks 'any' and private
	// CPTs whose published posts hold sensitive titles (e.g. coupon codes),
	// and prevents matching orphaned rows of deregistered post types.
	if ( ! post_type_exists( $post_type ) || ! is_post_type_viewable( $post_type ) ) {
		$post_type = 'ollie-carousel-none';
	}

	$per_page = isset( $query['perPage'] ) && is_numeric( $query['perPage'] )
		? min( 24, max( 1, intval( $query['perPage'] ) ) )
		: 6;
	$offset   = isset( $query['offset'] ) && is_numeric( $query['offset'] )
		? min( 10000, max( 0, intval( $query['offset'] ) ) )
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
		$args['s'] = mb_substr( $query['search'], 0, 256 );
	}

	if ( ! empty( $query['author'] ) ) {
		$author_ids = array_slice( wp_parse_id_list( $query['author'] ), 0, 100 );
		if ( ! empty( $author_ids ) ) {
			$args['author__in'] = $author_ids;
		}
	}

	$exclude_current = ! empty( $query['excludeCurrent'] ) && $current_post_id > 0;
	$post_not_in     = array();

	if ( 'post' === $post_type && ! empty( $query['sticky'] ) ) {
		$sticky_ids = get_option( 'sticky_posts', array() );
		if ( 'only' === $query['sticky'] ) {
			$sticky_only = array_map( 'intval', (array) $sticky_ids );
			// WP_Query ignores post__not_in when post__in is set, so the
			// current-post exclusion has to happen inside the ID list.
			if ( $exclude_current ) {
				$sticky_only = array_diff( $sticky_only, array( $current_post_id ) );
			}
			// An empty post__in returns all posts, so force no results instead.
			$args['post__in'] = ! empty( $sticky_only ) ? array_values( $sticky_only ) : array( 0 );
			$exclude_current  = false;
		} elseif ( 'exclude' === $query['sticky'] && ! empty( $sticky_ids ) ) {
			$post_not_in = array_map( 'intval', $sticky_ids );
		}
	}

	if ( $exclude_current ) {
		$post_not_in[] = $current_post_id;
	}

	if ( ! empty( $post_not_in ) && empty( $args['post__in'] ) ) {
		$args['post__not_in'] = array_values( array_unique( $post_not_in ) );
	}

	if ( ! empty( $query['taxQuery'] ) && is_array( $query['taxQuery'] ) ) {
		$tax_query = array();
		foreach ( $query['taxQuery'] as $taxonomy => $term_ids ) {
			if ( count( $tax_query ) >= 10 ) {
				break;
			}
			$term_ids = array_slice( wp_parse_id_list( $term_ids ), 0, 100 );
			if (
				taxonomy_exists( $taxonomy )
				&& is_taxonomy_viewable( $taxonomy )
				&& ! empty( $term_ids )
			) {
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

/**
 * Localize frontend carousel strings for view.js.
 */
function ollie_carousel_localize_view_script() {
	$handle = 'ollie-carousel-slides-view-script';
	if ( ! wp_script_is( $handle, 'registered' ) && ! wp_script_is( $handle, 'enqueued' ) ) {
		return;
	}

	wp_localize_script(
		$handle,
		'ollieCarouselL10n',
		array(
			/* translators: 1: current slide number, 2: total slide count. */
			'slideLabel' => __( '%1$d / %2$d', 'ollie-pro' ),
			/* translators: 1: slide number, 2: total slide count. */
			'goToSlide'  => __( 'Go to slide %1$d of %2$d', 'ollie-pro' ),
			'pause'      => __( 'Pause autoplay', 'ollie-pro' ),
			'play'       => __( 'Play autoplay', 'ollie-pro' ),
			'scrollbar'  => __( 'Carousel scroll bar', 'ollie-pro' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'ollie_carousel_localize_view_script', 20 );
