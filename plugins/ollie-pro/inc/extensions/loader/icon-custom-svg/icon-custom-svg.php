<?php
/**
 * Custom SVG support for the core Icon block.
 *
 * The block stores user-pasted SVG in the `ollieCustomSvg` attribute
 * (added editor-side). When present, this replaces core's registry-icon
 * output with the custom markup, run through wp_kses on every render.
 *
 * SECURITY: the stored attribute is untrusted — it lives in post content
 * and could be authored by any user who can edit posts, or injected
 * through crafted block markup. The client-side sanitizer is for preview
 * only; wp_kses here is the authoritative boundary. The allowlist admits
 * only inert shape/presentation elements and attributes — no script,
 * foreignObject, use, image, style, animate, a, href/xlink:href, event
 * handlers, or executable/external URL protocols.
 *
 * @package OlliePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowlist of SVG elements and attributes permitted in a custom icon.
 *
 * Kept deliberately narrow and in sync with the client sanitizer in
 * controls/icon-custom-svg/sanitize.js.
 *
 * @return array wp_kses-style allowed HTML array.
 */
function olpo_icon_custom_svg_allowed() {
	$paint = array(
		'fill'             => true,
		'fill-rule'        => true,
		'fill-opacity'     => true,
		'clip-rule'        => true,
		'stroke'           => true,
		'stroke-width'     => true,
		'stroke-linecap'   => true,
		'stroke-linejoin'  => true,
		'stroke-dasharray' => true,
		'stroke-dashoffset' => true,
		'stroke-opacity'   => true,
		'opacity'          => true,
		'transform'        => true,
	);

	return array(
		'svg'      => array(
			'viewbox'     => true,
			'xmlns'       => true,
			'class'       => true,
			'role'        => true,
			'aria-hidden' => true,
			'focusable'   => true,
		),
		'g'        => $paint,
		'path'     => array_merge( array( 'd' => true ), $paint ),
		'circle'   => array_merge( array( 'cx' => true, 'cy' => true, 'r' => true ), $paint ),
		'ellipse'  => array_merge( array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ), $paint ),
		'rect'     => array_merge( array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ), $paint ),
		'line'     => array_merge( array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ), $paint ),
		'polyline' => array_merge( array( 'points' => true ), $paint ),
		'polygon'  => array_merge( array( 'points' => true ), $paint ),
		'title'    => array(),
		'desc'     => array(),
	);
}

/**
 * Sanitize a custom icon SVG string for output.
 *
 * @param string $svg Raw SVG markup from the block attribute.
 * @return string Sanitized SVG, or '' if nothing usable remains.
 */
function olpo_icon_custom_svg_sanitize( $svg ) {
	if ( ! is_string( $svg ) || '' === trim( $svg ) ) {
		return '';
	}

	// Strip anything before the first <svg and after the last </svg> so a
	// stray prolog, doctype, or comment can't slip through kses quirks.
	if ( ! preg_match( '/<svg\b.*<\/svg>/is', $svg, $matches ) ) {
		return '';
	}
	$svg = $matches[0];

	// wp_kses removes disallowed TAGS but keeps their inner TEXT, so
	// <script>alert(1)</script> would leave "alert(1)" as bare (inert but
	// unwanted) text. Remove these container elements with their contents
	// first, plus comments, so no attacker-controlled text survives.
	$svg = preg_replace( '/<!--.*?-->/s', '', $svg );
	$svg = preg_replace(
		'#<(script|style|foreignObject|text|a)\b[^>]*>.*?</\1>#is',
		'',
		$svg
	);
	// Drop any self-closed instances of active/reference elements too.
	$svg = preg_replace(
		'#<(script|style|foreignObject|use|image|animate|animatetransform|animatemotion|set|a|text)\b[^>]*/?>#is',
		'',
		$svg
	);

	$clean = wp_kses( $svg, olpo_icon_custom_svg_allowed() );

	// Must still contain a drawable shape after sanitizing.
	if ( ! preg_match( '/<(path|circle|ellipse|rect|line|polyline|polygon)\b/i', $clean ) ) {
		return '';
	}

	return $clean;
}

/**
 * Apply the Icon block's flip and rotation attributes to an svg element,
 * mirroring render_block_core_icon so a custom SVG behaves identically.
 *
 * @param string $svg   Sanitized svg markup (single root <svg>).
 * @param array  $attrs Block attributes.
 * @return string Svg markup with flip classes and rotation applied.
 */
function olpo_icon_custom_svg_apply_transforms( $svg, $attrs ) {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $svg;
	}
	$processor = new WP_HTML_Tag_Processor( $svg );
	if ( ! $processor->next_tag( 'svg' ) ) {
		return $svg;
	}
	if ( ! empty( $attrs['flipHorizontal'] ) ) {
		$processor->add_class( 'is-flip-horizontal' );
	}
	if ( ! empty( $attrs['flipVertical'] ) ) {
		$processor->add_class( 'is-flip-vertical' );
	}
	$rotation = isset( $attrs['rotation'] ) ? (int) $attrs['rotation'] : 0;
	if ( $rotation ) {
		$processor->set_attribute( 'style', 'rotate: ' . $rotation . 'deg;' );
	}
	return $processor->get_updated_html();
}

/**
 * Replace the core Icon block's SVG with a sanitized custom SVG.
 *
 * When a library icon is also selected, core renders its wrapper and svg
 * and we swap only the svg, preserving every block support core applied.
 * When no library icon is selected core renders nothing, so we build the
 * wrapper ourselves via get_block_wrapper_attributes() (block supports
 * context is still live during the render_block filter).
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string Modified HTML.
 */
function olpo_icon_custom_svg_render_block( $block_content, $block ) {
	if ( 'core/icon' !== ( $block['blockName'] ?? '' ) ) {
		return $block_content;
	}

	$raw = $block['attrs']['ollieCustomSvg'] ?? '';
	if ( '' === trim( (string) $raw ) ) {
		return $block_content;
	}

	$svg = olpo_icon_custom_svg_sanitize( $raw );
	if ( '' === $svg ) {
		return $block_content;
	}

	$svg = olpo_icon_custom_svg_apply_transforms( $svg, $block['attrs'] );

	// Library icon present: core already rendered a wrapper + svg. Swap
	// the svg only, keeping core's wrapper and its supports intact.
	$svg_start = stripos( $block_content, '<svg' );
	if ( false !== $svg_start ) {
		$svg_end = stripos( $block_content, '</svg>', $svg_start );
		if ( false !== $svg_end ) {
			$svg_end += strlen( '</svg>' );
			return substr( $block_content, 0, $svg_start ) . $svg . substr( $block_content, $svg_end );
		}
	}

	// No library icon: core returned empty. Build the wrapper so color,
	// border, spacing, dimensions, and alignment supports still apply.
	if ( function_exists( 'get_block_wrapper_attributes' ) ) {
		return sprintf(
			'<div %s>%s</div>',
			get_block_wrapper_attributes( array( 'class' => 'wp-block-icon' ) ),
			$svg
		);
	}

	return '<div class="wp-block-icon">' . $svg . '</div>';
}
add_filter( 'render_block', 'olpo_icon_custom_svg_render_block', 10, 2 );
