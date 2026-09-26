<?php
/**
 * Server-side rendering for the ollie/carousel-nav block.
 *
 * Renders either navigation arrows or pagination dots based on navType attribute.
 *
 * @package ollie-pro
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert a spacing style value to a CSS length.
 *
 * @param mixed $value Spacing value (preset string or CSS length).
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

	// Allow lengths, zeros, and CSS variables only.
	if ( preg_match( '/^(0|[-]?[0-9]*\.?[0-9]+(px|em|rem|%|vh|vw)|var\(--[a-zA-Z0-9_-]+\))$/', $value ) ) {
		return $value;
	}

	return '';
};

/**
 * Resolve horizontal blockGap from block attributes (includes defaults).
 *
 * Layout support reads parsedattrs without defaults, so default 8px is never
 * emitted there — theme `.is-layout-flex { gap: medium }` would win instead.
 *
 * @param array $attributes Block attributes.
 * @return string CSS gap value.
 */
$resolve_nav_gap = static function ( $attributes ) use ( $spacing_to_css ) {
	$block_gap = $attributes['style']['spacing']['blockGap'] ?? '8px';

	if ( is_array( $block_gap ) ) {
		$raw = $block_gap['left'] ?? $block_gap['top'] ?? '8px';
	} else {
		$raw = $block_gap;
	}

	$css = $spacing_to_css( $raw );
	return $css ? $css : '8px';
};

$nav_type = $attributes['navType'] ?? 'arrows';
if ( ! in_array( $nav_type, array( 'arrows', 'dots', 'bars', 'scrollbar', 'autoplay' ), true ) ) {
	$nav_type = 'arrows';
}

$justify_content = $attributes['justifyContent'] ?? 'left';
if ( ! in_array( $justify_content, array( 'left', 'center', 'right', 'stretch' ), true ) ) {
	$justify_content = 'left';
}
$justify_class = 'is-content-justification-' . $justify_content;
$nav_gap       = $resolve_nav_gap( $attributes );

// Padding serialization is skipped in supports; apply it manually — on the
// nav wrapper for most types, on the button itself for the autoplay type.
$nav_padding    = $attributes['style']['spacing']['padding'] ?? null;
$padding_styles = array();
if ( is_array( $nav_padding ) ) {
	foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
		$padding_value = $spacing_to_css( $nav_padding[ $side ] ?? '' );
		if ( $padding_value ) {
			$padding_styles[] = 'padding-' . $side . ':' . $padding_value;
		}
	}
} elseif ( is_string( $nav_padding ) ) {
	$padding_value = $spacing_to_css( $nav_padding );
	if ( $padding_value ) {
		$padding_styles[] = 'padding:' . $padding_value;
	}
}

// Sanitize color values.
$sanitize_color = function( $color ) {
	if ( ! $color || ! is_string( $color ) ) {
		return '';
	}
	if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|var\(--[a-zA-Z0-9-]+\)|(rgb|hsl)a?\([0-9.,\s%]+\))$/', $color ) ) {
		return $color;
	}
	return sanitize_hex_color( $color ) ?: '';
};

if ( 'arrows' === $nav_type ) {
	// Arrow navigation.
	$arrow_icon        = $attributes['arrowIcon'] ?? 'arrow-circle';
	$arrow_icon_weight = $attributes['arrowIconWeight'] ?? 'regular';
	if ( ! in_array( $arrow_icon_weight, array( 'regular', 'bold', 'fill' ), true ) ) {
		$arrow_icon_weight = 'regular';
	}
	$custom_arrow_svg = isset( $attributes['customArrowSvg'] ) && is_string( $attributes['customArrowSvg'] )
		? $attributes['customArrowSvg']
		: '';
	$arrow_size       = absint( $attributes['arrowSize'] ?? 36 );
	$arrow_offset     = intval( $attributes['arrowOffset'] ?? 10 );
	$arrow_color      = $sanitize_color( $attributes['arrowColor'] ?? '' );
	$center_nav       = ! empty( $block->context['ollie/carousel/centerNav'] );

	$styles   = array();
	$styles[] = '--ollie-carousel-nav-gap:' . $nav_gap;
	if ( $arrow_size ) {
		$styles[] = '--ollie-carousel-arrow-size:' . $arrow_size . 'px';
	}
	if ( $center_nav ) {
		$styles[] = '--ollie-carousel-arrow-offset:' . $arrow_offset . 'px';
	}
	if ( $arrow_color ) {
		$styles[] = '--ollie-carousel-arrow-color:' . $arrow_color;
	}

	$styles = array_merge( $styles, $padding_styles );

	$wrapper_attributes = get_block_wrapper_attributes( array(
		'class' => 'ollie-carousel-nav ' . $justify_class,
		'style' => implode( ';', $styles ),
		'role'  => 'group',
		'aria-label' => __( 'Slide controls', 'ollie-pro' ),
	) );

	$arrow_svg  = function_exists( 'ollie_carousel_get_arrow_svg' )
		? ollie_carousel_get_arrow_svg( $arrow_icon, $arrow_icon_weight, $custom_arrow_svg )
		: '';
	$prev_label = esc_attr__( 'Previous slide', 'ollie-pro' );
	$next_label = esc_attr__( 'Next slide', 'ollie-pro' );

	echo '<div ' . $wrapper_attributes . '>';
	echo '<button class="ollie-carousel-prev" type="button" aria-label="' . $prev_label . '">' . $arrow_svg . '</button>';
	echo '<button class="ollie-carousel-next" type="button" aria-label="' . $next_label . '">' . $arrow_svg . '</button>';
	echo '</div>';

} elseif ( 'dots' === $nav_type ) {
	// Pagination dots.
	$dot_color = $sanitize_color( $attributes['dotColor'] ?? '' );
	$dot_size  = absint( $attributes['dotSize'] ?? 8 );

	$styles   = array();
	$styles[] = '--ollie-carousel-nav-gap:' . $nav_gap;
	if ( $dot_color ) {
		$styles[] = '--ollie-carousel-dot-color:' . $dot_color;
	}
	if ( $dot_size ) {
		$styles[] = '--ollie-carousel-dot-size:' . $dot_size . 'px';
	}

	$styles = array_merge( $styles, $padding_styles );

	$wrapper_attributes = get_block_wrapper_attributes( array(
		'class' => 'ollie-carousel-pagination ' . $justify_class,
		'style' => implode( ';', $styles ),
	) );

	echo '<div ' . $wrapper_attributes . ' role="group" aria-label="' . esc_attr__( 'Slide navigation', 'ollie-pro' ) . '"></div>';

} elseif ( 'bars' === $nav_type ) {
	// Pagination bars.
	$dot_color  = $sanitize_color( $attributes['dotColor'] ?? '' );
	$bar_height = absint( $attributes['barSize'] ?? 4 );
	$bar_width  = absint( $attributes['barWidth'] ?? 32 );
	$bar_fill   = ! empty( $attributes['barFill'] );

	$styles   = array();
	$styles[] = '--ollie-carousel-nav-gap:' . $nav_gap;
	if ( $dot_color ) {
		$styles[] = '--ollie-carousel-dot-color:' . $dot_color;
	}
	if ( $bar_height ) {
		$styles[] = '--ollie-carousel-bar-height:' . $bar_height . 'px';
	}
	if ( $bar_width && ! $bar_fill ) {
		$styles[] = '--ollie-carousel-bar-width:' . $bar_width . 'px';
	}

	$classes = 'ollie-carousel-pagination ollie-carousel-pagination--bars ' . $justify_class;
	if ( $bar_fill ) {
		$classes .= ' ollie-carousel-pagination--bars-fill';
	}

	$styles = array_merge( $styles, $padding_styles );

	$wrapper_attributes = get_block_wrapper_attributes( array(
		'class' => $classes,
		'style' => implode( ';', $styles ),
	) );

	echo '<div ' . $wrapper_attributes . ' role="group" aria-label="' . esc_attr__( 'Slide navigation', 'ollie-pro' ) . '"></div>';

} elseif ( 'scrollbar' === $nav_type ) {
	// Scroll progress bar.
	$dot_color  = $sanitize_color( $attributes['dotColor'] ?? '' );
	$bar_height = absint( $attributes['barSize'] ?? 4 );

	$styles   = array();
	$styles[] = '--ollie-carousel-nav-gap:' . $nav_gap;
	if ( $dot_color ) {
		$styles[] = '--ollie-carousel-dot-color:' . $dot_color;
	}
	if ( $bar_height ) {
		$styles[] = '--ollie-carousel-bar-height:' . $bar_height . 'px';
	}

	$styles = array_merge( $styles, $padding_styles );

	$wrapper_attributes = get_block_wrapper_attributes( array(
		'class' => 'ollie-carousel-scrollbar ' . $justify_class,
		'style' => implode( ';', $styles ),
	) );

	// Interactive scroll bar; role/value semantics and pointer handling are
	// wired up by the frontend runtime.
	echo '<div ' . $wrapper_attributes . '>';
	echo '<div class="ollie-carousel-scrollbar__track"><div class="ollie-carousel-scrollbar__thumb"></div></div>';
	echo '</div>';

} elseif ( 'autoplay' === $nav_type ) {
	// Autoplay pause/play button. The frontend runtime wires this button up
	// and hides it when autoplay is off; nothing is injected automatically.
	$button_text_color = $sanitize_color( $attributes['buttonTextColor'] ?? '' );
	$button_bg_color   = $sanitize_color( $attributes['buttonBackgroundColor'] ?? '' );

	// Font size may be a number (legacy px) or a CSS length from the theme's
	// presets (rem, clamp(), var(), …).
	$button_font_size = $attributes['buttonFontSize'] ?? '';
	if ( is_numeric( $button_font_size ) && $button_font_size > 0 ) {
		$button_font_size = $button_font_size . 'px';
	}
	$button_font_size = is_string( $button_font_size ) ? trim( $button_font_size ) : '';
	if (
		$button_font_size
		&& ! preg_match(
			'/^([0-9]*\.?[0-9]+(px|em|rem|%)|var\(--[a-zA-Z0-9_-]+\)|(clamp|min|max)\([^;{}<>]*\))$/',
			$button_font_size
		)
	) {
		$button_font_size = '';
	}

	$button_styles = array();
	if ( $button_text_color ) {
		$button_styles[] = 'color:' . $button_text_color;
	}
	if ( $button_bg_color ) {
		$button_styles[] = 'background-color:' . $button_bg_color;
	}
	if ( $button_font_size ) {
		$button_styles[] = 'font-size:' . $button_font_size;
	}

	$border_radius = $attributes['style']['border']['radius'] ?? null;
	if ( $border_radius && function_exists( 'wp_style_engine_get_styles' ) ) {
		$radius_styles = wp_style_engine_get_styles(
			array(
				'border' => array(
					'radius' => $border_radius,
				),
			)
		);

		if ( ! empty( $radius_styles['declarations'] ) ) {
			foreach ( $radius_styles['declarations'] as $property => $value ) {
				$button_styles[] = $property . ':' . $value;
			}
		}
	}

	$button_styles = array_merge( $button_styles, $padding_styles );
	$button_style_attr = $button_styles
		? ' style="' . esc_attr( implode( ';', $button_styles ) ) . '"'
		: '';

	$wrapper_attributes = get_block_wrapper_attributes( array(
		'class' => 'ollie-carousel-nav ollie-carousel-nav--autoplay ' . $justify_class,
		'style' => '--ollie-carousel-nav-gap:' . $nav_gap,
	) );

	echo '<div ' . $wrapper_attributes . '>';
	echo '<div class="wp-block-button"><button class="wp-block-button__link wp-element-button ollie-carousel-autoplay-toggle" type="button"' . $button_style_attr . '>' . esc_html__( 'Pause autoplay', 'ollie-pro' ) . '</button></div>';
	echo '</div>';
}
