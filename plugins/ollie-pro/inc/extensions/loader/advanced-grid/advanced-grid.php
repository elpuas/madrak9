<?php
/**
 * Advanced Grid Controls PHP Handler
 * 
 * Handles server-side rendering of responsive grid styles for the frontend.
 * Generates CSS media queries based on breakpoint settings defined in the editor.
 * 
 * @package OlliePro
 * @since 1.0.0
 */

namespace olpo;

// Blocks that support grid breakpoints
const GRID_SUPPORTED_BLOCKS = array( 'core/group', 'core/post-template', 'core/term-template' );

// Hook into block rendering to add responsive styles
add_filter( 'render_block', __NAMESPACE__ . '\\render_grid_responsive_styles', 10, 2 );

/**
 * Add responsive CSS to Grid blocks on the frontend
 */
function render_grid_responsive_styles( $block_content, $block ) {
	// Only process supported blocks with grid layout and breakpoint settings
	if (
		! in_array( $block['blockName'], GRID_SUPPORTED_BLOCKS, true ) ||
		empty( $block['attrs']['layout']['type'] ) ||
		$block['attrs']['layout']['type'] !== 'grid' ||
		empty( $block['attrs']['gridBreakpointSettings'] ) ||
		empty( $block['attrs']['gridBreakpoints'] )
	) {
		return $block_content;
	}

	$breakpoints = $block['attrs']['gridBreakpoints'];
	$settings = $block['attrs']['gridBreakpointSettings'];
	
	// Sort breakpoints from largest to smallest so smaller breakpoints override larger ones
	usort( $breakpoints, function( $a, $b ) {
		return $b['value'] - $a['value'];
	} );
	
	// Determine if core grid is in auto or manual mode
	$is_auto_mode = !empty( $block['attrs']['layout']['minimumColumnWidth'] );
	
	// Generate unique class for this block
	$block_id = 'ollie-grid-' . wp_unique_id();

	// Add the class to the block - only replace the FIRST occurrence to avoid affecting child elements
	$class_pos = strpos( $block_content, 'class="' );
	if ( $class_pos !== false ) {
		// Replace only the first occurrence of class="
		$block_content = substr_replace(
			$block_content,
			'class="' . esc_attr( $block_id ) . ' ',
			$class_pos,
			strlen( 'class="' )
		);
	} else {
		// If no class attribute exists, add it to the first <div
		$div_pos = strpos( $block_content, '<div' );
		if ( $div_pos !== false ) {
			$block_content = substr_replace(
				$block_content,
				'<div class="' . esc_attr( $block_id ) . '"',
				$div_pos,
				strlen( '<div' )
			);
		}
	}
	
	// Generate responsive CSS
	$responsive_css = '';
	
	foreach ( $breakpoints as $breakpoint ) {
		// Validate and sanitize breakpoint value
		$bp_value = absint( $breakpoint['value'] );
		if ( $bp_value < 320 || $bp_value > 3840 ) {
			continue;
		}

		// Try both string and integer keys for compatibility
		$bp_key = strval( $bp_value );
		$bp_settings = null;

		if ( isset( $settings[ $bp_key ] ) ) {
			$bp_settings = $settings[ $bp_key ];
		} elseif ( isset( $settings[ $bp_value ] ) ) {
			$bp_settings = $settings[ $bp_value ];
		}

		if ( ! $bp_settings ) {
			continue;
		}

		$responsive_css .= '@media (max-width: ' . $bp_value . 'px) {';
		$responsive_css .= '.' . esc_attr( $block_id ) . ' {';

		// Apply grid mode settings based on core grid mode
		if ( ! $is_auto_mode && ! empty( $bp_settings['columnCount'] ) ) {
			// Manual mode: set fixed column count
			$column_count = absint( $bp_settings['columnCount'] );
			if ( $column_count >= 1 && $column_count <= 12 ) {
				$responsive_css .= 'grid-template-columns: repeat(' . $column_count . ', 1fr) !important;';

				// If set to 1 column, also collapse multi-span grid items
				if ( $column_count === 1 ) {
					$responsive_css .= '}';
					$responsive_css .= '.' . esc_attr( $block_id ) . ' > * {';
					$responsive_css .= 'grid-column: span 1 !important;';
					$responsive_css .= 'grid-row: span 1 !important;';
				}
			}
		} elseif ( $is_auto_mode && ! empty( $bp_settings['minimumColumnWidth'] ) ) {
			// Auto mode: set minimum column width - sanitize CSS value
			$min_width = sanitize_text_field( $bp_settings['minimumColumnWidth'] );
			// Basic validation for CSS units
			if ( preg_match( '/^[0-9.]+(px|em|rem|%|vw|vh)$/', $min_width ) ) {
				$responsive_css .= 'grid-template-columns: repeat(auto-fit, minmax(' . esc_attr( $min_width ) . ', 1fr)) !important;';
			}
		}

		$responsive_css .= '}';
		$responsive_css .= '}';
	}
	
	// Add the CSS inline
	if ( ! empty( $responsive_css ) ) {
		$block_content .= '<style>' . $responsive_css . '</style>';
	}
	
	return $block_content;
}

/**
 * Register block attributes on the server side
 */
add_filter( 'register_block_type_args', function( $args, $block_type ) {
	if ( in_array( $block_type, GRID_SUPPORTED_BLOCKS, true ) ) {
		if ( ! isset( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}

		$args['attributes']['gridBreakpoints'] = array(
			'type'    => 'array',
			'default' => array(),
		);

		$args['attributes']['gridBreakpointSettings'] = array(
			'type'    => 'object',
			'default' => array(),
		);
	}

	return $args;
}, 10, 2 );