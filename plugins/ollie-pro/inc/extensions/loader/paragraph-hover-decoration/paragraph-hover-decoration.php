<?php
/**
 * Paragraph Hover Decoration Controls
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render hover decoration on the frontend for paragraphs and buttons.
 *
 * @since 0.1.0
 * @param string $block_content Block content.
 * @param array  $block Block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_render_hover_decoration( $block_content, $block ) {
	if ( ! isset( $block['attrs']['textDecorationHover'] ) || empty( $block['attrs']['textDecorationHover'] ) ) {
		return $block_content;
	}

	$hover_decoration = $block['attrs']['textDecorationHover'];
	$block_name = $block['blockName'];
	
	// Generate a unique class for this element
	$unique_class = 'has-hover-decoration-' . wp_unique_id();
	
	// Determine which tag to target based on block type
	$tag_name = 'core/paragraph' === $block_name ? 'p' : 'a';
	
	// Add the unique class to the element
	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( $processor->next_tag( $tag_name ) ) {
		$processor->add_class( $unique_class );
		$processor->add_class( 'has-hover-decoration' );
		$processor->set_attribute( 'data-hover-decoration', $hover_decoration );
	}
	$block_content = $processor->get_updated_html();
	
	// Add inline styles for hover effect
	if ( 'core/paragraph' === $block_name ) {
		$style = sprintf(
			'<style>
			.%1$s:hover,
			.%1$s:hover a {
				text-decoration: %2$s !important;
			}
			</style>',
			esc_attr( $unique_class ),
			esc_attr( $hover_decoration )
		);
	} else {
		// For buttons, only target the link itself
		$style = sprintf(
			'<style>
			.%1$s:hover {
				text-decoration: %2$s !important;
			}
			</style>',
			esc_attr( $unique_class ),
			esc_attr( $hover_decoration )
		);
	}
	
	return $style . $block_content;
}

/**
 * Check if block has hover decoration attributes
 *
 * @since 0.1.0
 * @access private
 * @param string $block_name Block name.
 * @param array  $attrs Block attributes.
 * @return bool Whether block has hover decoration attributes.
 */
function ollie_ui_helpers_has_hover_decoration_attributes( $block_name, $attrs ) {
	// Only process paragraph and button blocks
	if ( 'core/paragraph' !== $block_name && 'core/button' !== $block_name ) {
		return false;
	}
	
	// Check for hover decoration attribute
	return isset( $attrs['textDecorationHover'] ) && ! empty( $attrs['textDecorationHover'] );
}

/**
 * Filter block content to apply hover decoration
 *
 * @since 0.1.0
 * @param string $block_content The block content.
 * @param array  $block The block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_hover_decoration_render_block( $block_content, $block ) {
	$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
	
	// Check if this block has hover decoration attributes
	if ( ! ollie_ui_helpers_has_hover_decoration_attributes( $block_name, $attrs ) ) {
		return $block_content;
	}
	
	// Call the existing function to process hover decoration
	return ollie_ui_helpers_render_hover_decoration( $block_content, $block );
}
add_filter( 'render_block', 'ollie_ui_helpers_hover_decoration_render_block', 10, 2 );
