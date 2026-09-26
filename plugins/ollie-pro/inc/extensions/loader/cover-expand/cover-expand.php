<?php
/**
 * Cover Expand Control
 *
 * Handles frontend rendering for the cover expand functionality.
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter block content to apply cover expand class on frontend.
 *
 * @since 0.1.0
 * @param string $block_content The block content.
 * @param array  $block The block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_cover_expand_render_block( $block_content, $block ) {
	// Only process cover blocks.
	if ( 'core/cover' !== $block['blockName'] ) {
		return $block_content;
	}

	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();

	// Check if expand inside contents is enabled.
	if ( empty( $attrs['expandInsideContents'] ) ) {
		return $block_content;
	}

	// Use WP_HTML_Tag_Processor to add the class.
	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( $processor->next_tag() ) {
		$processor->add_class( 'has-expand-inside-contents' );
	}

	return $processor->get_updated_html();
}
add_filter( 'render_block', 'ollie_ui_helpers_cover_expand_render_block', 10, 2 );
