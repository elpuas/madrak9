<?php
/**
 * Server-side rendering for the ollie/carousel block.
 *
 * The carousel parent only renders the composition wrapper.
 * Child blocks (carousel-slides, carousel-nav) handle their own rendering.
 *
 * @package ollie-pro
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$classes = array( 'ollie-carousel-block' );
if ( ! empty( $attributes['centerNav'] ) ) {
	$classes[] = 'ollie-carousel-center-nav';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $classes ),
	)
);

echo '<div ' . $wrapper_attributes . '>';
echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '</div>';
