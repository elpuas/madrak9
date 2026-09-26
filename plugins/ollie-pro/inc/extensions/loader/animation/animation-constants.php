<?php
/**
 * Animation Constants
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get animation default values
 * 
 * @return array Default animation values
 */
function ollie_ui_helpers_get_animation_defaults() {
	return array(
		'duration'            => 1,
		'delay'               => 0,
		'distance'            => 30,
		'animateOnScroll'     => false,
		'animateOnce'         => true,
		'scrollOffset'        => -50,
		'animateSequentially' => false,
		'sequentialDelay'     => 0.2,
	);
}

/**
 * Get allowed animation types
 * 
 * @return array List of allowed animation types
 */
function ollie_ui_helpers_get_allowed_animations() {
	return array( 
		'fadeIn', 
		'fadeInUp', 
		'fadeInDown', 
		'fadeInLeft', 
		'fadeInRight',
		'zoomIn', 
		'pulse', 
		'scaleOnHover' 
	);
}

/**
 * Get scale-based animation types
 * 
 * @return array List of animations that use scale instead of distance
 */
function ollie_ui_helpers_get_scale_animations() {
	return array( 'zoomIn', 'pulse', 'scaleOnHover' );
}
