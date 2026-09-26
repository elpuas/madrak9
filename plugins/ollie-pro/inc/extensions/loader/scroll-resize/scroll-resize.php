<?php
/**
 * Scroll Resize - PHP Loader
 *
 * Enqueues frontend script for scroll-based scale effect on sticky elements.
 *
 * @package OlliePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue frontend scripts for scroll resize.
 */
function ollie_pro_scroll_resize_enqueue_scripts() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_script(
		'ollie-pro-scroll-resize',
		OLPO_URL . '/inc/extensions/loader/scroll-resize/scroll-resize-frontend.js',
		array(),
		OLPO_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ollie_pro_scroll_resize_enqueue_scripts' );
