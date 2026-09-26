<?php
/**
 * Arrow icon helpers for server-side rendering.
 *
 * @package ollie-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the arrow SVG markup for the current icon settings.
 *
 * @param string $icon_name  Icon name or 'custom'.
 * @param string $weight     Icon weight (regular, bold, fill).
 * @param string $custom_svg Custom SVG markup.
 * @return string SVG markup.
 */
if ( function_exists( 'ollie_carousel_get_arrow_svg' ) ) {
	return;
}

function ollie_carousel_get_arrow_svg( $icon_name, $weight, $custom_svg ) {
	if ( 'custom' === $icon_name && $custom_svg && is_string( $custom_svg ) ) {
		$allowed_svg = array(
			'svg'      => array( 'viewbox' => true, 'xmlns' => true, 'fill' => true, 'class' => true, 'width' => true, 'height' => true ),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'transform' => true, 'opacity' => true, 'fill-rule' => true, 'clip-rule' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'opacity' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'opacity' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'opacity' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'opacity' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'opacity' => true ),
			'ellipse'  => array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'opacity' => true ),
			'g'        => array( 'transform' => true, 'fill' => true, 'stroke' => true, 'opacity' => true, 'clip-path' => true ),
		);
		return '<span class="ollie-carousel-custom-icon" aria-hidden="true">' . wp_kses( $custom_svg, $allowed_svg ) . '</span>';
	}

	$icon_data = ollie_carousel_get_icon_data( $icon_name );
	if ( ! $icon_data ) {
		return ollie_carousel_get_fallback_arrow_svg();
	}

	$path = $icon_data['paths'][ $weight ] ?? $icon_data['paths']['regular'] ?? '';
	if ( ! $path ) {
		return ollie_carousel_get_fallback_arrow_svg();
	}

	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . esc_attr( $icon_data['viewBox'] ) . '" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . esc_attr( $path ) . '"/></svg>';
}

/**
 * Fallback caret glyph so an unknown/renamed icon never produces an
 * empty, invisible arrow button.
 *
 * @return string SVG markup.
 */
function ollie_carousel_get_fallback_arrow_svg() {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true" focusable="false"><path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"/></svg>';
}

/**
 * Get icon data from the arrow-icons.json file.
 *
 * @param string $name Icon name.
 * @return array|null Icon data array or null.
 */
function ollie_carousel_get_icon_data( $name ) {
	static $icons = null;

	if ( null === $icons ) {
		$json_path = OLPO_PATH . '/build/carousel/arrow-icons.json';
		if ( ! file_exists( $json_path ) ) {
			$json_path = OLPO_PATH . '/src/carousel/arrow-icons.json';
		}
		if ( file_exists( $json_path ) ) {
			$icons = json_decode( file_get_contents( $json_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		if ( ! is_array( $icons ) ) {
			$icons = array();
		}
	}

	foreach ( $icons as $icon ) {
		if ( ( $icon['name'] ?? '' ) === $name ) {
			return $icon;
		}
	}

	return null;
}
