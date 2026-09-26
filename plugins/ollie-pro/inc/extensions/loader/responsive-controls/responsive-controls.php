<?php
/**
 * Responsive Controls
 *
 * Handles server-side attribute registration, frontend rendering via render_block,
 * and static stylesheet enqueuing for responsive overrides (font size, padding,
 * margin, block gap, and min height).
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Target blocks that support responsive overrides.
 *
 * @return array Block type names.
 */
function ollie_responsive_get_target_blocks() {
	return apply_filters( 'ollie_responsive_target_blocks', array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/quote',
		'core/button',
		'core/site-title',
		'core/group',
		'core/columns',
		'core/column',
		'core/cover',
		'core/buttons',
		'core/post-content',
	) );
}

/**
 * Register the ollieResponsive attribute on target blocks server-side.
 *
 * @param array  $args       Block type arguments.
 * @param string $block_type Block type name.
 * @return array Modified block type arguments.
 */
function ollie_responsive_register_attributes( $args, $block_type ) {
	$targets = ollie_responsive_get_target_blocks();

	if ( ! in_array( $block_type, $targets, true ) ) {
		return $args;
	}

	if ( ! isset( $args['attributes'] ) ) {
		$args['attributes'] = array();
	}

	$args['attributes']['ollieResponsive'] = array(
		'type'    => 'object',
		'default' => new stdClass(),
	);

	return $args;
}
add_filter( 'register_block_type_args', 'ollie_responsive_register_attributes', 10, 2 );

/**
 * Convert a WordPress preset shorthand value to a CSS custom property.
 *
 * e.g. "var:preset|spacing|x-large" → "var(--wp--preset--spacing--x-large)"
 * Plain CSS values like "1rem" or "20px" pass through unchanged.
 *
 * @param string $value Raw value from block attributes.
 * @return string CSS-safe value.
 */
function ollie_responsive_resolve_preset_value( $value ) {
	if ( ! is_string( $value ) || strpos( $value, 'var:preset|' ) !== 0 ) {
		return $value;
	}

	// "var:preset|spacing|x-large" → "--wp--preset--spacing--x-large"
	$path = substr( $value, 4 ); // Remove "var:"
	$path = str_replace( '|', '--', $path );

	return 'var(--wp--' . $path . ')';
}

/**
 * Check whether the block's core style attribute already defines a value
 * for a property under a WP 7.1+ responsive viewport key ("@tablet" /
 * "@mobile"). When it does, core owns that property and the Ollie compat
 * output must stay silent to avoid two !important rules fighting.
 *
 * Uses key presence rather than truthiness: core treats an explicit null
 * as "user cleared an inherited value", which also counts as core-owned.
 *
 * @param array $style Block style attribute.
 * @param array $path  Property path inside a viewport, e.g. array( 'typography', 'fontSize' ).
 * @return bool Whether core defines the property for any viewport.
 */
function ollie_responsive_core_owns( $style, $path ) {
	foreach ( array( '@tablet', '@mobile' ) as $viewport ) {
		if ( ! isset( $style[ $viewport ] ) || ! is_array( $style[ $viewport ] ) ) {
			continue;
		}
		$node  = $style[ $viewport ];
		$found = true;
		foreach ( $path as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				$found = false;
				break;
			}
			$node = $node[ $key ];
		}
		if ( $found ) {
			return true;
		}
	}
	return false;
}

/**
 * Filter block content to add responsive CSS custom properties
 * and marker classes on the frontend.
 *
 * @param string $block_content The block content.
 * @param array  $block         The block data.
 * @return string Modified block content.
 */
function ollie_responsive_render_block( $block_content, $block ) {
	$responsive = $block['attrs']['ollieResponsive'] ?? null;

	if ( ! $responsive || ! is_array( $responsive ) ) {
		return $block_content;
	}

	$core_style = isset( $block['attrs']['style'] ) && is_array( $block['attrs']['style'] ) ? $block['attrs']['style'] : array();

	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( ! $processor->next_tag() ) {
		return $block_content;
	}

	$style_additions = '';
	$classes         = array();

	// --- Font size ---
	// Tablet values cascade to mobile unless mobile has its own value.
	$font_size = $responsive['fontSize'] ?? null;
	if ( ollie_responsive_core_owns( $core_style, array( 'typography', 'fontSize' ) ) ) {
		$font_size = null;
	}
	if ( is_array( $font_size ) ) {
		$fs_tablet = isset( $font_size['tablet'] ) && '' !== $font_size['tablet'] ? $font_size['tablet'] : null;
		$fs_mobile = isset( $font_size['mobile'] ) && '' !== $font_size['mobile'] ? $font_size['mobile'] : $fs_tablet;

		if ( null !== $fs_tablet ) {
			$style_additions .= '--ollie-fs-tablet:' . esc_attr( ollie_responsive_resolve_preset_value( $fs_tablet ) ) . ';';
			$classes[]        = 'has-ollie-fs-tablet';
		}
		if ( null !== $fs_mobile ) {
			$style_additions .= '--ollie-fs-mobile:' . esc_attr( ollie_responsive_resolve_preset_value( $fs_mobile ) ) . ';';
			$classes[]        = 'has-ollie-fs-mobile';
		}
	}

	// --- Spacing properties (padding, margin) ---
	// Per-side: tablet values cascade to mobile unless that side has a mobile value.
	$spacing_props = array( 'padding', 'margin' );
	$sides         = array( 'top', 'right', 'bottom', 'left' );

	foreach ( $spacing_props as $prop ) {
		$prop_data = $responsive[ $prop ] ?? null;
		if ( ! is_array( $prop_data ) || ollie_responsive_core_owns( $core_style, array( 'spacing', $prop ) ) ) {
			continue;
		}

		$tablet_data = is_array( $prop_data['tablet'] ?? null ) ? $prop_data['tablet'] : array();
		$mobile_data = is_array( $prop_data['mobile'] ?? null ) ? $prop_data['mobile'] : array();

		foreach ( $sides as $side ) {
			$tablet_val = isset( $tablet_data[ $side ] ) && '' !== $tablet_data[ $side ] ? $tablet_data[ $side ] : null;
			$mobile_val = isset( $mobile_data[ $side ] ) && '' !== $mobile_data[ $side ] ? $mobile_data[ $side ] : $tablet_val;

			if ( null !== $tablet_val ) {
				$resolved         = ollie_responsive_resolve_preset_value( $tablet_val );
				$style_additions .= "--ollie-{$prop}-{$side}-tablet:" . esc_attr( $resolved ) . ';';
				$classes[]        = "has-ollie-{$prop}-{$side}-tablet";
			}
			if ( null !== $mobile_val ) {
				$resolved         = ollie_responsive_resolve_preset_value( $mobile_val );
				$style_additions .= "--ollie-{$prop}-{$side}-mobile:" . esc_attr( $resolved ) . ';';
				$classes[]        = "has-ollie-{$prop}-{$side}-mobile";
			}
		}
	}

	// --- Block gap ---
	// Tablet cascades to mobile unless mobile has its own value.
	$block_gap = $responsive['blockGap'] ?? null;
	if ( ollie_responsive_core_owns( $core_style, array( 'spacing', 'blockGap' ) ) ) {
		$block_gap = null;
	}
	if ( is_array( $block_gap ) ) {
		$gap_tablet = isset( $block_gap['tablet'] ) && '' !== $block_gap['tablet'] ? $block_gap['tablet'] : null;
		$gap_mobile = isset( $block_gap['mobile'] ) && '' !== $block_gap['mobile'] ? $block_gap['mobile'] : $gap_tablet;

		if ( null !== $gap_tablet ) {
			$style_additions .= '--ollie-gap-tablet:' . esc_attr( ollie_responsive_resolve_preset_value( $gap_tablet ) ) . ';';
			$classes[]        = 'has-ollie-gap-tablet';
		}
		if ( null !== $gap_mobile ) {
			$style_additions .= '--ollie-gap-mobile:' . esc_attr( ollie_responsive_resolve_preset_value( $gap_mobile ) ) . ';';
			$classes[]        = 'has-ollie-gap-mobile';
		}
	}

	// --- Min height ---
	// Tablet cascades to mobile unless mobile has its own value.
	$min_height = $responsive['minHeight'] ?? null;
	if ( ollie_responsive_core_owns( $core_style, array( 'dimensions', 'minHeight' ) ) ) {
		$min_height = null;
	}
	if ( is_array( $min_height ) ) {
		$mh_tablet = isset( $min_height['tablet'] ) && '' !== $min_height['tablet'] ? $min_height['tablet'] : null;
		$mh_mobile = isset( $min_height['mobile'] ) && '' !== $min_height['mobile'] ? $min_height['mobile'] : $mh_tablet;

		if ( null !== $mh_tablet ) {
			$style_additions .= '--ollie-mh-tablet:' . esc_attr( ollie_responsive_resolve_preset_value( $mh_tablet ) ) . ';';
			$classes[]        = 'has-ollie-mh-tablet';
		}
		if ( null !== $mh_mobile ) {
			$style_additions .= '--ollie-mh-mobile:' . esc_attr( ollie_responsive_resolve_preset_value( $mh_mobile ) ) . ';';
			$classes[]        = 'has-ollie-mh-mobile';
		}
	}

	// --- Text align ---
	// Tablet cascades to mobile unless mobile has its own value.
	$text_align = $responsive['textAlign'] ?? null;
	if ( ollie_responsive_core_owns( $core_style, array( 'typography', 'textAlign' ) ) ) {
		$text_align = null;
	}
	if ( is_array( $text_align ) ) {
		$ta_tablet = isset( $text_align['tablet'] ) && '' !== $text_align['tablet'] ? $text_align['tablet'] : null;
		$ta_mobile = isset( $text_align['mobile'] ) && '' !== $text_align['mobile'] ? $text_align['mobile'] : $ta_tablet;

		// Only allow valid alignment values.
		$valid_aligns = array( 'left', 'center', 'right' );

		if ( $ta_tablet && in_array( $ta_tablet, $valid_aligns, true ) ) {
			$classes[] = 'has-ollie-ta-tablet';
			$style_additions .= '--ollie-ta-tablet:' . esc_attr( $ta_tablet ) . ';';
		}
		if ( $ta_mobile && in_array( $ta_mobile, $valid_aligns, true ) ) {
			$classes[] = 'has-ollie-ta-mobile';
			$style_additions .= '--ollie-ta-mobile:' . esc_attr( $ta_mobile ) . ';';
		}
	}

	// --- Justify content ---
	// Tablet cascades to mobile unless mobile has its own value.
	$justify_content = $responsive['justifyContent'] ?? null;
	if ( ollie_responsive_core_owns( $core_style, array( 'layout', 'justifyContent' ) ) ) {
		$justify_content = null;
	}
	if ( is_array( $justify_content ) ) {
		$jc_tablet = isset( $justify_content['tablet'] ) && '' !== $justify_content['tablet'] ? $justify_content['tablet'] : null;
		$jc_mobile = isset( $justify_content['mobile'] ) && '' !== $justify_content['mobile'] ? $justify_content['mobile'] : $jc_tablet;

		// Only allow valid justify-content values.
		$valid_jc = array( 'flex-start', 'center', 'flex-end', 'space-between', 'stretch' );

		if ( $jc_tablet && in_array( $jc_tablet, $valid_jc, true ) ) {
			$classes[] = 'has-ollie-jc-tablet';
			$style_additions .= '--ollie-jc-tablet:' . esc_attr( $jc_tablet ) . ';';
		}
		if ( $jc_mobile && in_array( $jc_mobile, $valid_jc, true ) ) {
			$classes[] = 'has-ollie-jc-mobile';
			$style_additions .= '--ollie-jc-mobile:' . esc_attr( $jc_mobile ) . ';';
		}
	}

	// --- Orientation (flex-direction) ---
	// Tablet cascades to mobile unless mobile has its own value.
	$orientation = $responsive['orientation'] ?? null;
	if ( ollie_responsive_core_owns( $core_style, array( 'layout', 'orientation' ) ) ) {
		$orientation = null;
	}
	if ( is_array( $orientation ) ) {
		$ori_tablet = isset( $orientation['tablet'] ) && '' !== $orientation['tablet'] ? $orientation['tablet'] : null;
		$ori_mobile = isset( $orientation['mobile'] ) && '' !== $orientation['mobile'] ? $orientation['mobile'] : $ori_tablet;

		$valid_ori = array( 'horizontal', 'vertical' );

		if ( $ori_tablet && in_array( $ori_tablet, $valid_ori, true ) ) {
			$dir_tablet = 'vertical' === $ori_tablet ? 'column' : 'row';
			$classes[] = 'has-ollie-ori-tablet';
			$classes[] = 'ollie-ori-tablet-' . $dir_tablet;
			$style_additions .= '--ollie-ori-tablet:' . $dir_tablet . ';';
		}
		if ( $ori_mobile && in_array( $ori_mobile, $valid_ori, true ) ) {
			$dir_mobile = 'vertical' === $ori_mobile ? 'column' : 'row';
			$classes[] = 'has-ollie-ori-mobile';
			$classes[] = 'ollie-ori-mobile-' . $dir_mobile;
			$style_additions .= '--ollie-ori-mobile:' . $dir_mobile . ';';
		}
	}

	// --- Max width ---
	// All three breakpoints stored in ollieResponsive.maxWidth.
	// A single class (has-ollie-mw) is added when any value is present;
	// CSS var() fallbacks cascade from wider breakpoints in the stylesheet.
	$max_width = $responsive['maxWidth'] ?? null;
	if ( is_array( $max_width ) ) {
		$mw_desktop = isset( $max_width['desktop'] ) && '' !== $max_width['desktop'] ? $max_width['desktop'] : null;
		$mw_tablet  = isset( $max_width['tablet'] ) && '' !== $max_width['tablet'] ? $max_width['tablet'] : null;
		$mw_mobile  = isset( $max_width['mobile'] ) && '' !== $max_width['mobile'] ? $max_width['mobile'] : null;

		if ( null !== $mw_desktop || null !== $mw_tablet || null !== $mw_mobile ) {
			$classes[] = 'has-ollie-mw';
		}
		if ( null !== $mw_desktop ) {
			$style_additions .= '--ollie-mw-desktop:' . esc_attr( ollie_responsive_resolve_preset_value( $mw_desktop ) ) . ';';
		}
		if ( null !== $mw_tablet ) {
			$style_additions .= '--ollie-mw-tablet:' . esc_attr( ollie_responsive_resolve_preset_value( $mw_tablet ) ) . ';';
		}
		if ( null !== $mw_mobile ) {
			$style_additions .= '--ollie-mw-mobile:' . esc_attr( ollie_responsive_resolve_preset_value( $mw_mobile ) ) . ';';
		}
	}

	// Apply if we have anything.
	if ( ! empty( $style_additions ) ) {
		$existing = $processor->get_attribute( 'style' ) ?? '';
		$processor->set_attribute( 'style', $style_additions . $existing );
	}

	foreach ( $classes as $cls ) {
		$processor->add_class( $cls );
	}

	return $processor->get_updated_html();
}
add_filter( 'render_block', 'ollie_responsive_render_block', 10, 2 );

/**
 * Enqueue the static responsive stylesheet on the frontend.
 */
function ollie_responsive_enqueue_styles() {
	wp_enqueue_style(
		'ollie-responsive',
		OLPO_URL . '/inc/extensions/loader/responsive-controls/responsive-controls.css',
		array( 'global-styles' ),
		OLPO_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ollie_responsive_enqueue_styles' );
