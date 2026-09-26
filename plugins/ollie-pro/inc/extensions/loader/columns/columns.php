<?php
/**
 * Column Controls
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if block has column-related attributes
 *
 * @since 0.1.0
 * @access private
 * @param string $block_name Block name.
 * @param array  $attrs Block attributes.
 * @return bool Whether block has column attributes.
 */
function ollie_ui_helpers_has_column_attributes( $block_name, $attrs ) {
	// Check for columns block attributes
	if ( 'core/columns' === $block_name && isset( $attrs['isReversedDirectionOnMobile'] ) ) {
		return true;
	}
	
	// Check for column block attributes
	if ( 'core/column' === $block_name && isset( $attrs['orderMobile'] ) ) {
		return true;
	}
	
	return false;
}

/**
 * Process column-related block attributes.
 *
 * @since 0.1.0
 * @access private
 * @param string $block_name Block name.
 * @param array  $attrs Block attributes.
 * @return array Array containing 'styles' and 'classes' arrays.
 */
function ollie_ui_helpers_process_column_attributes( $block_name, $attrs ) {
	$styles  = array();
	$classes = array();
	
	// Handle columns block attributes.
	if ( 'core/columns' === $block_name ) {
		if ( ! empty( $attrs['isReversedDirectionOnMobile'] ) ) {
			$classes[] = 'ollie-swap-order';
		}
	}
	
	// Handle column block attributes.
	if ( 'core/column' === $block_name ) {
		if ( ! empty( $attrs['orderMobile'] ) ) {
			$order_value = intval( $attrs['orderMobile'] );
			$styles[] = sprintf( '--order-mobile: %d;', $order_value );
		}
	}
	
	return array(
		'styles'  => $styles,
		'classes' => $classes,
	);
}

/**
 * Filter block content to apply column classes and styles
 *
 * @since 0.1.0
 * @param string $block_content The block content.
 * @param array  $block The block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_columns_render_block( $block_content, $block ) {
	$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
	
	// Check if this block has column attributes
	if ( ! ollie_ui_helpers_has_column_attributes( $block_name, $attrs ) ) {
		return $block_content;
	}
	
	// Process column attributes to get styles and classes
	$column_data = ollie_ui_helpers_process_column_attributes( $block_name, $attrs );
	
	// If we have something to process
	if ( ! empty( $column_data['styles'] ) || ! empty( $column_data['classes'] ) ) {
		// Create HTML processor
		$processor = new WP_HTML_Tag_Processor( $block_content );
		
		// Process the first HTML tag
		if ( $processor->next_tag() ) {
			// Add column classes
			foreach ( $column_data['classes'] as $class ) {
				$processor->add_class( $class );
			}
			
			// Add or merge column styles
			if ( ! empty( $column_data['styles'] ) ) {
				$style = implode( ' ', $column_data['styles'] );
				$existing_style = $processor->get_attribute( 'style' );
				if ( $existing_style ) {
					$style = $existing_style . ';' . $style;
				}
				$processor->set_attribute( 'style', $style );
			}
			
			// Return the modified content
			return $processor->get_updated_html();
		}
	}
	
	return $block_content;
}
add_filter( 'render_block', 'ollie_ui_helpers_columns_render_block', 10, 2 );
