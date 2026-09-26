<?php
/**
 * Server-side rendering for the ollie/carousel-slides block.
 *
 * @package ollie-pro
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$clamp_integer = static function ( $value, $minimum, $maximum, $fallback ) {
	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}

	return min( $maximum, max( $minimum, intval( $value ) ) );
};

$clamp_half_step = static function ( $value, $minimum, $maximum, $fallback ) {
	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}

	return min( $maximum, max( $minimum, round( floatval( $value ) * 2 ) / 2 ) );
};

$format_float = static function ( $value ) {
	return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
};

$slides_per_view  = $clamp_half_step( $attributes['slidesPerView'] ?? 3, 1, 10, 3 );
$slides_per_group = $clamp_integer( $attributes['slidesPerGroup'] ?? 1, 1, 10, 1 );
$speed            = $clamp_integer( $attributes['speed'] ?? 300, 0, 1000, 300 );
$autoplay_speed   = (
	is_numeric( $attributes['autoplaySpeed'] ?? null )
	&& intval( $attributes['autoplaySpeed'] ) > 0
) ? intval( $attributes['autoplaySpeed'] ) : 3000;
$autoplay_style   = 'scroll' === ( $attributes['autoplayStyle'] ?? 'slide' ) ? 'scroll' : 'slide';
$scroll_speed     = (
	is_numeric( $attributes['scrollSpeed'] ?? null )
) ? min( 10, max( 0.25, floatval( $attributes['scrollSpeed'] ) ) ) : 2;
$slide_max_width  = is_numeric( $attributes['slideMaxWidth'] ?? null )
	? intval( $attributes['slideMaxWidth'] )
	: 0;
$slide_max_width  = $slide_max_width > 0 ? min( 1200, max( 100, $slide_max_width ) ) : 0;
$slide_min_width  = is_numeric( $attributes['slideMinWidth'] ?? null )
	? (int) $attributes['slideMinWidth']
	: 0;
$slide_min_width  = $slide_min_width > 0 ? min( 800, max( 50, $slide_min_width ) ) : 0;
$loop             = ! empty( $attributes['loop'] );
$autoplay         = ! empty( $attributes['autoplay'] );
$pause_on_hover   = ! empty( $attributes['pauseOnMouseEnter'] );
$fade             = ! empty( $attributes['fade'] );
$overflow_visible = ! empty( $attributes['overflowVisible'] );
$fade_out         = ! empty( $attributes['fadeOut'] );
$fade_out_edges   = 'end' === ( $attributes['fadeOutEdges'] ?? 'both' ) ? 'end' : 'both';
$drag_cursor      = ! empty( $attributes['dragCursor'] );
$wheel_gestures   = ! empty( $attributes['wheelGestures'] );
$free_scroll      = ! empty( $attributes['freeScroll'] );
// Unset means "follow the site's language direction"; the block attribute
// is an explicit per-carousel override either way.
$rtl              = isset( $attributes['rtl'] ) ? ! empty( $attributes['rtl'] ) : is_rtl();

// Build responsive breakpoints from flat attributes.
// Tablet inherits from desktop; mobile defaults to a single slide unless
// explicitly set (carousels should collapse to one slide on phones).
$tablet_per_view  = isset( $attributes['tabletSlidesPerView'] ) ? $clamp_half_step( $attributes['tabletSlidesPerView'], 1, 10, null ) : null;
$tablet_per_group = isset( $attributes['tabletSlidesPerGroup'] ) ? $clamp_integer( $attributes['tabletSlidesPerGroup'], 1, 10, null ) : null;
$mobile_per_view  = isset( $attributes['mobileSlidesPerView'] ) ? $clamp_half_step( $attributes['mobileSlidesPerView'], 1, 10, null ) : null;
$mobile_per_group = isset( $attributes['mobileSlidesPerGroup'] ) ? $clamp_integer( $attributes['mobileSlidesPerGroup'], 1, 10, null ) : null;

$effective_tablet_per_view  = $tablet_per_view ?? $slides_per_view;
$effective_tablet_per_group = $tablet_per_group ?? $slides_per_group;
$effective_mobile_per_view  = $mobile_per_view ?? 1;
$effective_mobile_per_group = $mobile_per_group ?? 1;

$breakpoints = array();
if ( null !== $tablet_per_view || null !== $tablet_per_group ) {
	$breakpoints[] = array(
		'width'          => 781,
		'slidesPerView'  => $effective_tablet_per_view,
		'slidesPerGroup' => $effective_tablet_per_group,
	);
}
$breakpoints[] = array(
	'width'          => 480,
	'slidesPerView'  => $effective_mobile_per_view,
	'slidesPerGroup' => $effective_mobile_per_group,
);

/**
 * Convert a block-spacing value to a safe CSS length.
 *
 * @param mixed $value Spacing preset or CSS length.
 * @return string CSS value or empty string.
 */
$spacing_to_css = static function ( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}

	if ( str_contains( $value, 'var:preset|spacing|' ) ) {
		$raw_slug = substr( $value, strrpos( $value, '|' ) + 1 );
		$slug     = function_exists( 'ollie_carousel_kebab_case' )
			? ollie_carousel_kebab_case( $raw_slug )
			: sanitize_title( $raw_slug );
		return "var(--wp--preset--spacing--{$slug})";
	}

	if ( preg_match( '/^(0|[0-9]*\.?[0-9]+(px|em|rem|%|vh|vw)|var\(--[a-zA-Z0-9_-]+\))$/', $value ) ) {
		return $value;
	}

	return '';
};

$block_gap = $attributes['style']['spacing']['blockGap'] ?? '';
if ( is_array( $block_gap ) ) {
	$block_gap = $block_gap['left'] ?? $block_gap['top'] ?? '';
}
$block_gap = $spacing_to_css( $block_gap );

$styles   = array();
$styles[] = '--ollie-slides-per-view:' . $format_float( $slides_per_view );
if ( $block_gap ) {
	$styles[] = '--ollie-slide-block-gap:' . $block_gap;
}
if ( $slide_max_width ) {
	$styles[] = '--ollie-slide-max-width:' . $slide_max_width . 'px';
}

$viewport_classes = array( 'ollie-carousel-viewport' );
if ( $overflow_visible ) {
	$viewport_classes[] = 'ollie-carousel-overflow-visible';
}
if ( $fade_out ) {
	$viewport_classes[] = 'ollie-carousel-fade-out';
	if ( 'end' === $fade_out_edges ) {
		$viewport_classes[] = 'ollie-carousel-fade-out--end';
	}
}
if ( $drag_cursor ) {
	$viewport_classes[] = 'ollie-carousel-drag-cursor';
}
if ( $rtl ) {
	$viewport_classes[] = 'ollie-carousel-rtl';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                       => implode( ' ', $viewport_classes ),
		'style'                       => implode( ';', $styles ),
		'dir'                         => $rtl ? 'rtl' : 'ltr',
		'role'                        => 'group',
		'aria-roledescription'        => _x( 'carousel', 'aria role description announced by screen readers', 'ollie-pro' ),
		'aria-label'                  => __( 'Carousel', 'ollie-pro' ),
		'data-ollie-slides-per-view'  => $format_float( $slides_per_view ),
		'data-ollie-min-slide-width'  => (string) $slide_min_width,
		'data-ollie-slides-per-group' => (string) $slides_per_group,
		'data-ollie-speed'            => (string) $speed,
		'data-ollie-loop'             => $loop ? 'true' : 'false',
		'data-ollie-autoplay'         => $autoplay ? 'true' : 'false',
		'data-ollie-autoplay-speed'   => (string) $autoplay_speed,
		'data-ollie-autoplay-style'   => $autoplay_style,
		'data-ollie-scroll-speed'     => $format_float( $scroll_speed ),
		'data-ollie-pause-on-hover'   => $pause_on_hover ? 'true' : 'false',
		'data-ollie-wheel-gestures'   => $wheel_gestures ? 'true' : 'false',
		'data-ollie-free-scroll'      => $free_scroll ? 'true' : 'false',
		'data-ollie-fade'             => $fade ? 'true' : 'false',
		'data-ollie-rtl'              => $rtl ? 'true' : 'false',
		'data-ollie-breakpoints'      => wp_json_encode( $breakpoints ),
	)
);

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

	// Halt runaway recursion: a slide template that (directly or through a
	// pattern/post content) contains another dynamic carousel would otherwise
	// render perPage^depth slides. Depth 2 allows one legitimate nesting level.
	$within_depth_limit = ! function_exists( 'ollie_carousel_render_depth' )
		|| ollie_carousel_render_depth( 1 ) <= 2;

	if ( $slide_template && $within_depth_limit && function_exists( 'ollie_carousel_build_query_args' ) ) {
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

	if ( function_exists( 'ollie_carousel_render_depth' ) ) {
		ollie_carousel_render_depth( -1 );
	}
}

echo '<div ' . $wrapper_attributes . '>';
echo '<div class="ollie-carousel-container">';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks markup.
echo $slides_markup;
echo '</div>';
echo '</div>';
