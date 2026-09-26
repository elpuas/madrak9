<?php
/**
 * Cover Term Image
 *
 * Enables cover blocks to use term thumbnail images when placed inside Terms Query blocks.
 * Works with any taxonomy that has term images (WooCommerce product categories, custom taxonomies, etc.).
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register usesContext for cover blocks to receive term context.
 * This allows cover blocks to access termId and taxonomy from parent terms query blocks.
 *
 * @since 0.1.0
 * @param array  $args       Block registration arguments.
 * @param string $block_type Block type name.
 * @return array Modified block arguments.
 */
function ollie_cover_term_image_register_context( $args, $block_type ) {
	if ( 'core/cover' !== $block_type ) {
		return $args;
	}

	// Ensure usesContext is an array
	if ( ! isset( $args['uses_context'] ) ) {
		$args['uses_context'] = array();
	}

	// Add term context if not already present
	if ( ! in_array( 'termId', $args['uses_context'], true ) ) {
		$args['uses_context'][] = 'termId';
	}
	if ( ! in_array( 'taxonomy', $args['uses_context'], true ) ) {
		$args['uses_context'][] = 'taxonomy';
	}

	return $args;
}
add_filter( 'register_block_type_args', 'ollie_cover_term_image_register_context', 10, 2 );

/**
 * Get term thumbnail URL.
 * Supports any taxonomy with term meta 'thumbnail_id' (including WooCommerce product categories).
 *
 * @since 0.1.0
 * @param int    $term_id  The term ID.
 * @param string $taxonomy The taxonomy name.
 * @param string $size     Image size (default 'full').
 * @return string|false The image URL or false if not found.
 */
function ollie_get_term_thumbnail_url( $term_id, $taxonomy, $size = 'full' ) {
	// Validate inputs
	if ( ! $term_id || ! $taxonomy ) {
		return false;
	}

	// Check for thumbnail_id meta (works for WooCommerce product_cat and generic taxonomies)
	$thumbnail_id = get_term_meta( $term_id, 'thumbnail_id', true );
	if ( $thumbnail_id ) {
		$image_url = wp_get_attachment_image_url( absint( $thumbnail_id ), $size );
		if ( $image_url ) {
			return $image_url;
		}
	}

	// Additional fallback for other common term image meta keys
	// Fetch all meta at once for better performance
	$meta_keys = array( 'image', 'term_image', 'category_image' );
	foreach ( $meta_keys as $meta_key ) {
		$meta_value = get_term_meta( $term_id, $meta_key, true );
		if ( $meta_value ) {
			// Check if it's an attachment ID
			if ( is_numeric( $meta_value ) ) {
				$image_url = wp_get_attachment_image_url( absint( $meta_value ), $size );
				if ( $image_url ) {
					return $image_url;
				}
			}
			// Check if it's already a URL (only allow http/https for security)
			if ( filter_var( $meta_value, FILTER_VALIDATE_URL ) &&
			     preg_match( '/^https?:\/\//i', $meta_value ) ) {
				// Use esc_url_raw for storage, will be escaped again on output
				return esc_url_raw( $meta_value );
			}
		}
	}

	return false;
}

/**
 * Filter cover block rendering to inject term thumbnail image.
 *
 * When useFeaturedImage is enabled on a cover block inside a Terms Query,
 * we inject the term's thumbnail image instead.
 *
 * @since 0.1.0
 * @param string   $block_content The block content.
 * @param array    $block         The block data.
 * @param WP_Block $instance      The block instance.
 * @return string Modified block content.
 */
function ollie_cover_term_image_render( $block_content, $block, $instance ) {
	// Only process cover blocks
	if ( 'core/cover' !== $block['blockName'] ) {
		return $block_content;
	}

	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();

	// Check if useFeaturedImage or useTermImage is enabled
	// We use this standard WordPress attribute to trigger term image injection
	if ( empty( $attrs['useFeaturedImage'] ) && empty( $attrs['useTermImage'] ) ) {
		return $block_content;
	}

	// Get term context - if present, we're inside a Terms Query
	$context = isset( $instance->context ) ? $instance->context : array();

	// Validate and sanitize term_id
	$term_id = isset( $context['termId'] ) ? absint( $context['termId'] ) : null;
	if ( ! $term_id ) {
		return $block_content;
	}

	// Sanitize and validate taxonomy
	$taxonomy = isset( $context['taxonomy'] ) ? sanitize_key( $context['taxonomy'] ) : null;
	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return $block_content;
	}

	// Get the term thumbnail URL
	$image_url = ollie_get_term_thumbnail_url( $term_id, $taxonomy );

	if ( ! $image_url ) {
		return $block_content;
	}

	// Check if there's already an img element in the cover
	$has_img = strpos( $block_content, 'wp-block-cover__image-background' ) !== false;

	// Use WP_HTML_Tag_Processor to add our class to the cover wrapper
	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( $processor->next_tag() ) {
		// Add the has-term-image class
		$processor->add_class( 'has-term-image' );
		$block_content = $processor->get_updated_html();
	}

	// If there's an existing img element, update its src
	if ( $has_img ) {
		// Reuse processor for efficiency
		$processor = new WP_HTML_Tag_Processor( $block_content );
		while ( $processor->next_tag( 'img' ) ) {
			$class = $processor->get_attribute( 'class' );
			if ( $class && strpos( $class, 'wp-block-cover__image-background' ) !== false ) {
				$processor->set_attribute( 'src', esc_url( $image_url ) );
				// Remove srcset to prevent browser from loading wrong image
				$processor->remove_attribute( 'srcset' );
				$processor->remove_attribute( 'sizes' );
				$block_content = $processor->get_updated_html();
				break;
			}
		}
	} else {
		// No img element exists - inject one after the opening tag
		// The cover block uses an img element for the background image
		// Get term name for accessible alt text (only fetch if needed)
		$term = get_term( $term_id, $taxonomy );
		$alt_text = $term && ! is_wp_error( $term )
			? sprintf( __( 'Cover image for %s', 'ollie-pro' ), esc_html( $term->name ) )
			: __( 'Term cover image', 'ollie-pro' );

		// Build img element with proper escaping
		$img_element = sprintf(
			'<img class="wp-block-cover__image-background" alt="%s" src="%s" style="object-fit: cover;" data-object-fit="cover" />',
			esc_attr( $alt_text ),
			esc_url( $image_url )
		);

		// Find the first > and insert after it (safe since we've already escaped the img element)
		$first_tag_end = strpos( $block_content, '>' );
		if ( $first_tag_end !== false ) {
			$block_content = substr( $block_content, 0, $first_tag_end + 1 ) . $img_element . substr( $block_content, $first_tag_end + 1 );
		}
	}

	return $block_content;
}
add_filter( 'render_block', 'ollie_cover_term_image_render', 10, 3 );
