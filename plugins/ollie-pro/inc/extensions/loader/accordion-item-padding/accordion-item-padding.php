<?php
/**
 * Enable padding support on core/accordion-item.
 *
 * Core ships the block with margin + blockGap only. Gutenberg already enables
 * padding; this filter backfills it for Core so Accordion Item matches panel UX.
 *
 * @package OlliePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add spacing.padding support to Accordion Item.
 *
 * @param array $metadata Block type metadata from block.json.
 * @return array
 */
function ollie_pro_accordion_item_padding_support( $metadata ) {
	if ( ( $metadata['name'] ?? '' ) !== 'core/accordion-item' ) {
		return $metadata;
	}

	if ( ! isset( $metadata['supports'] ) || ! is_array( $metadata['supports'] ) ) {
		$metadata['supports'] = array();
	}

	if ( ! isset( $metadata['supports']['spacing'] ) || ! is_array( $metadata['supports']['spacing'] ) ) {
		$metadata['supports']['spacing'] = array();
	}

	$metadata['supports']['spacing']['padding'] = true;

	if (
		! isset( $metadata['supports']['spacing']['__experimentalDefaultControls'] ) ||
		! is_array( $metadata['supports']['spacing']['__experimentalDefaultControls'] )
	) {
		$metadata['supports']['spacing']['__experimentalDefaultControls'] = array();
	}

	$metadata['supports']['spacing']['__experimentalDefaultControls']['padding'] = true;

	return $metadata;
}
add_filter( 'block_type_metadata', 'ollie_pro_accordion_item_padding_support' );
