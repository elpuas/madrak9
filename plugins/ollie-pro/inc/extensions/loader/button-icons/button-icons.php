<?php
/**
 * Button Icons Controls - Simplified Version
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include the icons registry
require_once __DIR__ . '/icons-registry.php';

/**
 * Render button icons on the frontend.
 *
 * @since 0.1.0
 * @param string $block_content Block content.
 * @param array  $block Block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_render_button_icons( $block_content, $block ) {
	$has_icon        = isset( $block['attrs']['icon'] );
	$has_custom_icon = ! empty( $block['attrs']['customIconSvg'] );

	if ( ! $has_icon && ! $has_custom_icon ) {
		return $block_content;
	}

	$icon = isset( $block['attrs']['icon'] ) ? $block['attrs']['icon'] : '';
	$position_left = isset( $block['attrs']['iconPositionLeft'] ) ? $block['attrs']['iconPositionLeft'] : false;
	$icon_weight = isset( $block['attrs']['iconWeight'] ) ? $block['attrs']['iconWeight'] : 'regular';
	$icon_color = isset( $block['attrs']['iconColor'] ) ? $block['attrs']['iconColor'] : '';
	$custom_icon_color = isset( $block['attrs']['customIconColor'] ) ? $block['attrs']['customIconColor'] : '';
	$icon_size = isset( $block['attrs']['iconSize'] ) ? $block['attrs']['iconSize'] : '1.2em';
	$icon_spacing = isset( $block['attrs']['iconSpacing'] ) ? $block['attrs']['iconSpacing'] : '.5em';

	// Custom SVG takes priority over registry icon.
	if ( $has_custom_icon ) {
		$icon_svg = ollie_sanitize_svg( $block['attrs']['customIconSvg'] );
	} else {
		$icon_svg = Ollie_Button_Icons_Registry::get_svg( $icon, $icon_weight );
	}

	if ( empty( $icon_svg ) ) {
		return $block_content;
	}

	// Build style attribute for the SVG
	$svg_styles = array();
	
	// Apply color to the SVG if specified
	if ( $icon_color || $custom_icon_color ) {
		$color_value = '';
		
		// If it's a theme color, get the CSS variable
		if ( $icon_color ) {
			$color_value = 'var(--wp--preset--color--' . $icon_color . ')';
		} elseif ( $custom_icon_color ) {
			$color_value = $custom_icon_color;
		}
		
		if ( $color_value ) {
			$svg_styles[] = 'color: ' . esc_attr( $color_value );
		}
	}
	
	// Apply size to the SVG if specified
	if ( $icon_size ) {
		$svg_styles[] = 'width: ' . esc_attr( $icon_size );
		$svg_styles[] = 'height: ' . esc_attr( $icon_size );
	}
	
	// Add style attribute to the SVG if we have any styles
	if ( ! empty( $svg_styles ) ) {
		$style_string = implode( '; ', $svg_styles );
		$icon_svg = str_replace( '<svg', '<svg style="' . $style_string . ';"', $icon_svg );
	}

	// Add classes to the button block
	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( $processor->next_tag() ) {
		if ( $has_custom_icon ) {
			$processor->add_class( 'has-icon__custom' );
		} elseif ( $icon ) {
			$processor->add_class( 'has-icon__' . $icon );
		}
		if ( $icon_weight !== 'regular' ) {
			$processor->add_class( 'has-icon-weight__' . $icon_weight );
		}
		if ( $position_left ) {
			$processor->add_class( 'has-icon-position__left' );
		}
		if ( $icon_color ) {
			$processor->add_class( 'has-icon-color' );
			$processor->add_class( 'has-icon-color--' . $icon_color );
		}
	}
	$block_content = $processor->get_updated_html();

	// Add inline style for spacing to the link/button element
	$processor = new WP_HTML_Tag_Processor( $block_content );

	// Try to find an <a> tag first, then fall back to <button>
	$found_element = false;
	$element_type = 'a';

	if ( $processor->next_tag( 'a' ) ) {
		$found_element = true;
		$element_type = 'a';
	} else {
		// Reset and try button
		$processor = new WP_HTML_Tag_Processor( $block_content );
		if ( $processor->next_tag( 'button' ) ) {
			$found_element = true;
			$element_type = 'button';
		}
	}

	if ( $found_element ) {
		$existing_style = $processor->get_attribute( 'style' ) ?: '';
		$new_style = $existing_style . ( $existing_style ? '; ' : '' ) . 'gap: ' . $icon_spacing;
		$processor->set_attribute( 'style', $new_style );
		$block_content = $processor->get_updated_html();
	}

	// Insert the SVG icon either to the left or right of the button text
	// Handle both <a> and <button> elements
	if ( $element_type === 'button' ) {
		$pattern = '/(<button[^>]*>)(.*?)(<\/button>)/is';
	} else {
		$pattern = '/(<a[^>]*>)(.*?)(<\/a>)/is';
	}

	if ( $position_left ) {
		$replacement = '$1' . $icon_svg . '$2$3';
	} else {
		$replacement = '$1$2' . $icon_svg . '$3';
	}

	$block_content = preg_replace( $pattern, $replacement, $block_content );

	return $block_content;
}

/**
 * Check if block has button icon attributes
 *
 * @since 0.1.0
 * @access private
 * @param string $block_name Block name.
 * @param array  $attrs Block attributes.
 * @return bool Whether block has button icon attributes.
 */
function ollie_ui_helpers_has_button_icon_attributes( $block_name, $attrs ) {
	// Only process button blocks
	if ( 'core/button' !== $block_name ) {
		return false;
	}
	
	// Check for icon or custom icon attribute.
	return isset( $attrs['icon'] ) || ! empty( $attrs['customIconSvg'] );
}

/**
 * Filter block content to apply button icons
 *
 * @since 0.1.0
 * @param string $block_content The block content.
 * @param array  $block The block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_button_icons_render_block( $block_content, $block ) {
	$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
	
	// Check if this block has button icon attributes
	if ( ! ollie_ui_helpers_has_button_icon_attributes( $block_name, $attrs ) ) {
		return $block_content;
	}
	
	// Call the existing function to process the button
	return ollie_ui_helpers_render_button_icons( $block_content, $block );
}
add_filter( 'render_block', 'ollie_ui_helpers_button_icons_render_block', 10, 2 );

/**
 * Enqueue icons data for the editor
 *
 * @since 0.1.0
 */
function ollie_ui_helpers_enqueue_button_icons_data() {
	if ( ! is_admin() ) {
		return;
	}
	
	// Make icons data available to JavaScript
	wp_localize_script(
		'ollie-extensions-editor',
		'ollieButtonIcons',
		array(
			'icons' => Ollie_Button_Icons_Registry::get_icons(),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'ollie_ui_helpers_enqueue_button_icons_data' );

/**
 * Sanitize an SVG string by whitelisting elements and attributes.
 *
 * Strips all elements and attributes not in the allowed lists,
 * removes event handlers, script elements, and dangerous URIs.
 *
 * @param string $svg Raw SVG markup.
 * @return string Sanitized SVG or empty string on failure.
 */
function ollie_sanitize_svg( $svg ) {
	if ( empty( $svg ) || ! is_string( $svg ) ) {
		return '';
	}

	// Must contain an <svg tag.
	if ( stripos( $svg, '<svg' ) === false ) {
		return '';
	}

	// Allowed elements.
	$allowed_elements = array(
		'svg', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon',
		'ellipse', 'g', 'defs', 'clippath', 'use', 'title', 'desc',
	);

	// Allowed attributes per element category.
	$shape_attrs = array(
		'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
		'width', 'height', 'points', 'fill', 'stroke', 'stroke-width',
		'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule',
		'opacity', 'transform', 'class', 'fill-opacity', 'stroke-opacity',
		'stroke-dasharray', 'stroke-dashoffset', 'stroke-miterlimit',
	);

	$allowed_attrs = array(
		'svg'      => array( 'viewbox', 'xmlns', 'width', 'height', 'fill', 'class', 'xmlns:xlink' ),
		'path'     => $shape_attrs,
		'circle'   => $shape_attrs,
		'rect'     => $shape_attrs,
		'line'     => $shape_attrs,
		'polyline' => $shape_attrs,
		'polygon'  => $shape_attrs,
		'ellipse'  => $shape_attrs,
		'g'        => array( 'id', 'clip-path', 'transform', 'fill', 'class', 'opacity', 'stroke', 'stroke-width' ),
		'defs'     => array( 'id' ),
		'clippath' => array( 'id', 'clippathunits' ),
		'use'      => array( 'href', 'xlink:href', 'x', 'y', 'width', 'height' ),
		'title'    => array(),
		'desc'     => array(),
	);

	// Suppress errors for malformed XML.
	$prev = libxml_use_internal_errors( true );

	$doc = new DOMDocument();
	// Wrap in XML declaration to handle encoding.
	$loaded = $doc->loadXML( '<?xml version="1.0" encoding="UTF-8"?>' . $svg, LIBXML_NONET | LIBXML_NOBLANKS );
	libxml_use_internal_errors( $prev );

	if ( ! $loaded ) {
		return '';
	}

	// Find the SVG element.
	$svg_elements = $doc->getElementsByTagName( 'svg' );
	if ( 0 === $svg_elements->length ) {
		return '';
	}

	// Recursively sanitize.
	ollie_sanitize_svg_node( $svg_elements->item( 0 ), $allowed_elements, $allowed_attrs );

	// Serialize and extract just the SVG.
	$output = $doc->saveXML( $svg_elements->item( 0 ) );

	if ( empty( $output ) ) {
		return '';
	}

	// Add our standard attributes.
	$output = str_replace( '<svg', '<svg class="wp-block-button__link-icon" aria-hidden="true"', $output );

	return $output;
}

/**
 * Recursively sanitize a DOMNode.
 *
 * @param DOMNode $node             The node to sanitize.
 * @param array   $allowed_elements Allowed element names (lowercase).
 * @param array   $allowed_attrs    Allowed attributes per element.
 */
function ollie_sanitize_svg_node( $node, $allowed_elements, $allowed_attrs ) {
	if ( ! $node->hasChildNodes() ) {
		return;
	}

	// Collect children first to avoid modifying the list while iterating.
	$children = array();
	foreach ( $node->childNodes as $child ) {
		$children[] = $child;
	}

	foreach ( $children as $child ) {
		// Keep text nodes (for <title>/<desc>).
		if ( $child->nodeType === XML_TEXT_NODE ) {
			continue;
		}

		// Remove non-element nodes (comments, CDATA, PI).
		if ( $child->nodeType !== XML_ELEMENT_NODE ) {
			$node->removeChild( $child );
			continue;
		}

		$tag = strtolower( $child->nodeName );

		// Remove disallowed elements.
		if ( ! in_array( $tag, $allowed_elements, true ) ) {
			$node->removeChild( $child );
			continue;
		}

		// Sanitize attributes.
		if ( $child->hasAttributes() ) {
			$element_allowed = isset( $allowed_attrs[ $tag ] ) ? $allowed_attrs[ $tag ] : array();
			$attrs_to_remove = array();

			foreach ( $child->attributes as $attr ) {
				$attr_name  = strtolower( $attr->nodeName );
				$attr_value = $attr->nodeValue;

				// Block all event handlers.
				if ( strpos( $attr_name, 'on' ) === 0 ) {
					$attrs_to_remove[] = $attr->nodeName;
					continue;
				}

				// Block dangerous URI schemes in any attribute value.
				if ( preg_match( '/^\s*(javascript|data|vbscript)\s*:/i', $attr_value ) ) {
					$attrs_to_remove[] = $attr->nodeName;
					continue;
				}

				// Remove attributes not in the whitelist.
				if ( ! in_array( $attr_name, $element_allowed, true ) ) {
					$attrs_to_remove[] = $attr->nodeName;
				}
			}

			foreach ( $attrs_to_remove as $attr_name ) {
				$child->removeAttribute( $attr_name );
			}
		}

		// Recurse into children.
		ollie_sanitize_svg_node( $child, $allowed_elements, $allowed_attrs );
	}
}
