<?php
/**
 * Text Wrap Control
 *
 * Handles frontend rendering for the text-wrap functionality.
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter block content to apply text-wrap class on frontend.
 *
 * @since 0.1.0
 * @param string $block_content The block content.
 * @param array  $block The block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_text_wrap_render_block( $block_content, $block ) {
	// Only process paragraph, heading, post-title, and post-excerpt blocks.
	if ( ! in_array( $block['blockName'], array( 'core/paragraph', 'core/heading', 'core/post-title', 'core/post-excerpt' ), true ) ) {
		return $block_content;
	}

	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();

	// Check if text-wrap is set.
	if ( empty( $attrs['textWrap'] ) ) {
		return $block_content;
	}

	$text_wrap = sanitize_html_class( $attrs['textWrap'] );

	// Only allow valid values.
	if ( ! in_array( $text_wrap, array( 'pretty', 'balance' ), true ) ) {
		return $block_content;
	}

	// Use WP_HTML_Tag_Processor to add the class.
	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( $processor->next_tag() ) {
		$processor->add_class( 'has-text-wrap-' . $text_wrap );
	}

	return $processor->get_updated_html();
}
add_filter( 'render_block', 'ollie_ui_helpers_text_wrap_render_block', 10, 2 );
