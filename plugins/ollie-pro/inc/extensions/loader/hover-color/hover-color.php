<?php
/**
 * Hover Color Controls
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if block has hover color attributes
 *
 * @since 0.1.0
 * @access private
 * @param array $attrs Block attributes.
 * @return bool Whether block has hover color attributes.
 */
function ollie_ui_helpers_has_hover_color_attributes( $attrs ) {
	return isset( $attrs['hoverTextColor'] ) || 
	       isset( $attrs['customHoverTextColor'] ) ||
	       isset( $attrs['hoverBackgroundColor'] ) ||
	       isset( $attrs['customHoverBackgroundColor'] ) ||
	       isset( $attrs['hoverBorderColor'] ) ||
	       isset( $attrs['customHoverBorderColor'] );
}

/**
 * Process hover color attributes and return styles and classes.
 *
 * @since 0.1.0
 * @access private
 * @param array $attrs Block attributes.
 * @return array Array containing 'styles' and 'classes' arrays.
 */
function ollie_ui_helpers_process_hover_colors( $attrs ) {
	$hover_styles  = array();
	$hover_classes = array();
	
	// Helper function to get color value.
	$get_preset_color_value = function ( $preset_color ) {
		// Sanitize the preset color slug to ensure it's safe for CSS
		$safe_slug = sanitize_html_class( $preset_color );
		return sprintf( 'var(--wp--preset--color--%s)', $safe_slug );
	};
	
	// Add transition duration.
	if ( ! empty( $attrs['hoverTransitionDuration'] ) ) {
		$duration       = absint( $attrs['hoverTransitionDuration'] );
		$hover_styles[] = sprintf( '--hover-transition-duration: %dms;', $duration );
	}
	
	// Add transition timing function.
	if ( ! empty( $attrs['hoverTransitionTiming'] ) ) {
		$timing         = sanitize_text_field( $attrs['hoverTransitionTiming'] );
		$hover_styles[] = sprintf( '--hover-transition-timing: %s;', $timing );
	}
	
	// Process color attributes.
	$color_mappings = array(
		'hoverTextColor'             => array( 'class' => 'has-hover__color', 'var' => '--hover-color', 'preset' => true ),
		'customHoverTextColor'       => array( 'class' => 'has-hover__color', 'var' => '--hover-color', 'preset' => false ),
		'hoverBackgroundColor'       => array( 'class' => 'has-hover__background-color', 'var' => '--hover-background-color', 'preset' => true ),
		'customHoverBackgroundColor' => array( 'class' => 'has-hover__background-color', 'var' => '--hover-background-color', 'preset' => false ),
		'hoverBorderColor'           => array( 'class' => 'has-hover__border-color', 'var' => '--hover-border', 'preset' => true ),
		'customHoverBorderColor'     => array( 'class' => 'has-hover__border-color', 'var' => '--hover-border', 'preset' => false ),
	);
	
	foreach ( $color_mappings as $attr_key => $config ) {
		if ( ! empty( $attrs[ $attr_key ] ) ) {
			$hover_classes[] = $config['class'];
			$color = $config['preset'] ? $get_preset_color_value( $attrs[ $attr_key ] ) : $attrs[ $attr_key ];
			if ( $color ) {
				$hover_styles[] = sprintf( '%s: %s;', $config['var'], $color );
			}
		}
	}
	
	return array(
		'styles'  => $hover_styles,
		'classes' => $hover_classes,
	);
}

/**
 * Filter block content to apply hover color classes and styles
 *
 * @since 0.1.0
 * @param string $block_content The block content.
 * @param array  $block The block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_hover_color_render_block( $block_content, $block ) {
	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
	
	// Check if this block has hover color attributes
	if ( ! ollie_ui_helpers_has_hover_color_attributes( $attrs ) ) {
		return $block_content;
	}
	
	// Process hover color attributes to get styles and classes
	$hover_data = ollie_ui_helpers_process_hover_colors( $attrs );
	
	// If we have something to process
	if ( ! empty( $hover_data['styles'] ) || ! empty( $hover_data['classes'] ) ) {
		// Create HTML processor
		$processor = new WP_HTML_Tag_Processor( $block_content );
		
		// Process the first HTML tag
		if ( $processor->next_tag() ) {
			// Add hover classes
			foreach ( $hover_data['classes'] as $class ) {
				$processor->add_class( $class );
			}
			
			// Add or merge hover styles
			if ( ! empty( $hover_data['styles'] ) ) {
				$style = implode( ' ', $hover_data['styles'] );
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
add_filter( 'render_block', 'ollie_ui_helpers_hover_color_render_block', 10, 2 );

/**
 * Register hover color attributes on the server side
 *
 * This is necessary because WordPress REST API validates attributes when
 * server-side rendering blocks (like core/archives). If attributes are only
 * registered in JavaScript, the REST API will reject them with a 400 error.
 *
 * @since 0.1.0
 * @param array  $args       Block type arguments.
 * @param string $block_type Block type name.
 * @return array Modified block type arguments.
 */
function ollie_ui_helpers_register_hover_color_attributes( $args, $block_type ) {
	// Get the block type to check for color support
	$block_registry = WP_Block_Type_Registry::get_instance();
	$registered_block = $block_registry->get_registered( $block_type );

	// Check if block supports text color
	$has_text_color_support = false;

	if ( $registered_block ) {
		$supports = isset( $registered_block->supports ) ? $registered_block->supports : array();
		$color_support = isset( $supports['color'] ) ? $supports['color'] : false;

		// Check if color support exists and text is not explicitly disabled
		if ( $color_support && ( ! is_array( $color_support ) || ! isset( $color_support['text'] ) || $color_support['text'] !== false ) ) {
			$has_text_color_support = true;
		}
	}

	// Also check the args being registered (for blocks being registered now)
	if ( ! $has_text_color_support && isset( $args['supports']['color'] ) ) {
		$color_support = $args['supports']['color'];
		if ( $color_support && ( ! is_array( $color_support ) || ! isset( $color_support['text'] ) || $color_support['text'] !== false ) ) {
			$has_text_color_support = true;
		}
	}

	// Also check for legacy textColor attribute
	if ( ! $has_text_color_support && isset( $args['attributes']['textColor'] ) ) {
		$has_text_color_support = true;
	}

	if ( ! $has_text_color_support ) {
		return $args;
	}

	// Ensure attributes array exists
	if ( ! isset( $args['attributes'] ) ) {
		$args['attributes'] = array();
	}

	// Register hover color attributes (matching the JavaScript definitions)
	$hover_attributes = array(
		'hoverTextColor' => array(
			'type' => 'string',
		),
		'customHoverTextColor' => array(
			'type' => 'string',
		),
		'hoverBackgroundColor' => array(
			'type' => 'string',
		),
		'customHoverBackgroundColor' => array(
			'type' => 'string',
		),
		'hoverBorderColor' => array(
			'type' => 'string',
		),
		'customHoverBorderColor' => array(
			'type' => 'string',
		),
		'hoverTransitionDuration' => array(
			'type'    => 'number',
			'default' => 200,
		),
		'hoverTransitionTiming' => array(
			'type'    => 'string',
			'default' => 'ease',
		),
	);

	$args['attributes'] = array_merge( $args['attributes'], $hover_attributes );

	return $args;
}
add_filter( 'register_block_type_args', 'ollie_ui_helpers_register_hover_color_attributes', 10, 2 );
