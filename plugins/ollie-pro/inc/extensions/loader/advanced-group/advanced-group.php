<?php
/**
 * Group Controls - handles both linking and stacking for group blocks
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if block has group control attributes (linking or stacking)
 *
 * @since 0.1.0
 * @access private
 * @param string $block_name Block name.
 * @param array  $attrs Block attributes.
 * @return bool Whether block has group control attributes.
 */
function ollie_ui_helpers_has_group_attributes( $block_name, $attrs ) {
	// Process group, column, and cover blocks for linking
	$supports_linking = in_array( $block_name, array( 'core/group', 'core/column', 'core/cover' ), true );
	// Only group blocks support stacking and sticky features
	$supports_stacking = 'core/group' === $block_name;

	if ( ! $supports_linking && ! $supports_stacking ) {
		return false;
	}

	// Check for link attributes (both group and column)
	if ( $supports_linking && ( isset( $attrs['href'] ) || isset( $attrs['linkDestination'] ) ) ) {
		return true;
	}

	// Check for stacking attributes (group only)
	if ( $supports_stacking && isset( $attrs['isStackedOnMobile'] ) ) {
		return true;
	}

	// Check for sticky position attributes (group only)
	if ( $supports_stacking && ( isset( $attrs['stickyScrollOffset'] ) || isset( $attrs['stickyZIndex'] ) || isset( $attrs['stickyOnScrollUp'] ) || isset( $attrs['unstickOnMobile'] ) ) ) {
		return true;
	}

	return false;
}

/**
 * Process group link attributes and generate link markup
 *
 * @since 0.1.0
 * @access private
 * @param array    $attrs   Block attributes.
 * @param array    $context Block context (termId, taxonomy, etc.).
 * @return array|false Array with 'link_markup' and 'classes' or false if no link.
 */
function ollie_ui_helpers_process_group_link( $attrs, $context = array() ) {
	$href             = $attrs['href'] ?? '';
	$link_destination = $attrs['linkDestination'] ?? '';
	$link_target      = $attrs['linkTarget'] ?? '_self';
	$link_rel         = '_blank' === $link_target ? 'noopener noreferrer' : 'follow';

	$link       = '';
	$aria_label = trim( (string) ( $attrs['linkAriaLabel'] ?? '' ) );

	if ( 'custom' === $link_destination && $href ) {
		$link = $href;
	} elseif ( 'post' === $link_destination ) {
		$link = get_permalink();
		if ( ! $aria_label ) {
			$aria_label = wp_strip_all_tags( get_the_title() );
		}
	} elseif ( 'term' === $link_destination ) {
		// Get term link from block context (provided by terms query block)
		$term_id  = $context['termId'] ?? null;
		$taxonomy = $context['taxonomy'] ?? null;

		if ( $term_id && $taxonomy ) {
			$term_link = get_term_link( (int) $term_id, $taxonomy );
			if ( ! is_wp_error( $term_link ) ) {
				$link = $term_link;
				if ( ! $aria_label ) {
					$term       = get_term( (int) $term_id, $taxonomy );
					$aria_label = ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
				}
			}
		}
	}

	if ( ! $link ) {
		return false;
	}

	// A focusable link must never be nameless; the raw URL is the last
	// resort when no label, post title, or term name applies.
	if ( ! $aria_label ) {
		$aria_label = $link;
	}

	return array(
		'classes'          => array( 'is-linked' ),
		'url'              => $link,
		'link_target'      => $link_target,
		'link_rel'         => $link_rel,
		'aria_label'       => $aria_label,
		// An author-typed label is an explicit request for the overlay to
		// be the announced link — it always renders the accessible variant.
		'has_custom_label' => '' !== trim( (string) ( $attrs['linkAriaLabel'] ?? '' ) ),
	);
}

/**
 * Whether the card content contains a real link to the group URL.
 *
 * Video modal triggers are excluded: their href may match the group URL,
 * but they open a modal instead of navigating, so they can't serve as
 * the card's accessible link.
 *
 * @since 2.8.0
 * @access private
 * @param string $block_content Rendered inner HTML (before overlay insertion).
 * @param string $url           Group link URL.
 * @return bool Whether a matching real link exists.
 */
function ollie_ui_helpers_content_links_to( $block_content, $url ) {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return false !== strpos( $block_content, 'href="' . esc_url( $url ) . '"' );
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	while ( $processor->next_tag( 'A' ) ) {
		if ( $processor->get_attribute( 'href' ) !== esc_url( $url ) ) {
			continue;
		}
		$class = (string) $processor->get_attribute( 'class' );
		if ( false !== strpos( $class, 'ollie-video-modal-trigger' ) ) {
			continue;
		}
		if ( null !== $processor->get_attribute( 'data-video-source' ) ) {
			continue;
		}
		return true;
	}

	return false;
}

/**
 * Build the group link overlay markup.
 *
 * Two variants: when the card already contains a real link to the group
 * URL (e.g. a synced Button), the overlay stays out of the tab order and
 * hidden from assistive tech so it doesn't duplicate that link. When the
 * card has no such link, the overlay IS the card's link — focusable and
 * labeled — otherwise keyboard and screen-reader users have no way to
 * follow it (the overlay is visually hidden and clicks are forwarded by
 * JS, which a keyboard can't trigger).
 *
 * @since 2.8.0
 * @access private
 * @param array $link_data  Link data from ollie_ui_helpers_process_group_link().
 * @param bool  $accessible Whether the overlay is the card's accessible link.
 * @return string Anchor markup.
 */
function ollie_ui_helpers_build_group_link_markup( $link_data, $accessible ) {
	if ( $accessible ) {
		return sprintf(
			'<a class="wp-block__link ollie-group-link" href="%1$s" target="%2$s" rel="%3$s" data-expand-click-area aria-label="%4$s">&nbsp;</a>',
			esc_url( $link_data['url'] ),
			esc_attr( $link_data['link_target'] ),
			esc_attr( $link_data['link_rel'] ),
			esc_attr( $link_data['aria_label'] )
		);
	}

	return sprintf(
		'<a class="wp-block__link ollie-group-link" href="%1$s" target="%2$s" rel="%3$s" data-expand-click-area tabindex="-1" aria-hidden="true">&nbsp;</a>',
		esc_url( $link_data['url'] ),
		esc_attr( $link_data['link_target'] ),
		esc_attr( $link_data['link_rel'] )
	);
}

/**
 * Give empty Button blocks inside a linked group the group's URL.
 *
 * Core buttons can render as <a> or <button>. Empty/hash anchors get an href;
 * <button> elements are converted to anchors so they remain a real, focusable
 * link for keyboard and screen-reader users.
 *
 * @since 1.0.0
 * @access private
 * @param string $block_content Rendered block HTML.
 * @param string $url           Group link URL.
 * @param string $link_target   Link target (_self|_blank).
 * @param string $link_rel      Link rel attribute.
 * @return string Modified HTML.
 */
function ollie_ui_helpers_sync_empty_button_links( $block_content, $url, $link_target = '_self', $link_rel = 'follow' ) {
	if ( ! $block_content || ! $url ) {
		return $block_content;
	}

	$escaped_url = esc_url( $url );
	$target_attr = esc_attr( $link_target );
	$rel_attr    = esc_attr( $link_rel );

	// Convert Core button elements (<button>) into anchors with the group URL.
	$block_content = preg_replace_callback(
		'/<button\b([^>]*\bwp-block-button__link\b[^>]*)>(.*?)<\/button>/is',
		static function ( $matches ) use ( $escaped_url, $target_attr, $rel_attr ) {
			$attrs = $matches[1];
			$inner = $matches[2];

			// type="button" is invalid on anchors.
			$attrs = preg_replace( '/\s*type\s*=\s*([\'"])button\1/i', '', $attrs );

			return sprintf(
				'<a href="%1$s" target="%2$s" rel="%3$s"%4$s>%5$s</a>',
				$escaped_url,
				$target_attr,
				$rel_attr,
				$attrs,
				$inner
			);
		},
		$block_content
	);

	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $block_content;
	}

	$processor = new WP_HTML_Tag_Processor( $block_content );
	$updated   = false;

	while ( $processor->next_tag( 'A' ) ) {
		$class = $processor->get_attribute( 'class' );
		if ( ! is_string( $class ) || false === strpos( $class, 'wp-block-button__link' ) ) {
			continue;
		}

		$href = $processor->get_attribute( 'href' );
		if ( null !== $href && '' !== $href && '#' !== $href ) {
			continue;
		}

		$processor->set_attribute( 'href', $escaped_url );
		$processor->set_attribute( 'target', $link_target );
		$processor->set_attribute( 'rel', $link_rel );
		$updated = true;
	}

	return $updated ? $processor->get_updated_html() : $block_content;
}

/**
 * Process group stacking attributes
 *
 * @since 0.1.0
 * @access private
 * @param array $attrs Block attributes.
 * @return array Array containing 'classes' array.
 */
function ollie_ui_helpers_process_group_stacking( $attrs ) {
	$classes = array();
	
	// Handle group stacking attributes
	if ( ! empty( $attrs['isStackedOnMobile'] ) && ! empty( $attrs['layout'] ) ) {
		$layout = $attrs['layout'];
		$is_row_layout = isset( $layout['type'] ) && 'flex' === $layout['type'] && 
		                ( ! isset( $layout['orientation'] ) || 'vertical' !== $layout['orientation'] );
		
		if ( $is_row_layout ) {
			$classes[] = 'ollie-row-stack';
		}
	}
	
	return array(
		'classes' => $classes,
	);
}

/**
 * Process sticky position attributes
 *
 * @since 0.1.0
 * @access private
 * @param array $attrs Block attributes.
 * @return array|false Array containing 'styles' string or false if no sticky settings.
 */
function ollie_ui_helpers_process_sticky_position( $attrs ) {
	// Initialize return arrays
	$styles = '';
	$data_attrs = array();
	$classes = array();
	
	// Add unstick on mobile class if enabled (regardless of sticky position)
	if ( ! empty( $attrs['unstickOnMobile'] ) ) {
		$classes[] = 'ollie-unstick-mobile';
	}
	
	// Check if sticky position is enabled for other attributes
	if ( empty( $attrs['style']['position']['type'] ) || 'sticky' !== $attrs['style']['position']['type'] ) {
		// Return early if no sticky position, but still return classes if we have any
		if ( ! empty( $classes ) ) {
			return array(
				'styles' => '',
				'data_attrs' => array(),
				'classes' => $classes,
			);
		}
		return false;
	}
	
	// Process sticky offset value only if it's set and not zero
	if ( isset( $attrs['stickyScrollOffset'] ) && $attrs['stickyScrollOffset'] !== '0px' && $attrs['stickyScrollOffset'] !== '0' ) {
		$offset_value = sanitize_text_field( $attrs['stickyScrollOffset'] );

		// Validate CSS unit format
		if ( preg_match( '/^-?[0-9.]+(?:px|em|rem|%|vh|vw)$/', $offset_value ) ) {
			$styles = 'top: calc(' . $offset_value . ' + var(--wp-admin--admin-bar--position-offset, 0px)); ';
			$styles .= '--sticky-top-offset: ' . $offset_value . '; ';
			$styles .= '--sticky-full-offset: calc(' . $offset_value . ' + var(--wp-admin--admin-bar--position-offset, 0px));';
			$data_attrs['data-sticky-offset'] = $offset_value;
		}
	}
	
	// Add scroll-up-only behavior if enabled
	if ( ! empty( $attrs['stickyOnScrollUp'] ) ) {
		$data_attrs['data-sticky-on-scroll-up'] = 'true';
	}

	// Add z-index if set
	if ( isset( $attrs['stickyZIndex'] ) && $attrs['stickyZIndex'] !== null ) {
		$z_index = intval( $attrs['stickyZIndex'] );
		if ( $z_index !== 0 ) {
			$styles .= ' z-index: ' . $z_index . ';';
			$data_attrs['data-sticky-z-index'] = (string) $z_index;
		}
	}

	if ( empty( $styles ) && empty( $data_attrs ) && empty( $classes ) ) {
		return false;
	}
	
	return array(
		'styles' => $styles,
		'data_attrs' => $data_attrs,
		'classes' => $classes,
	);
}

/**
 * Filter block content to apply group controls (linking and stacking)
 *
 * @since 0.1.0
 * @param string   $block_content The block content.
 * @param array    $block         The block data.
 * @param WP_Block $instance      The block instance.
 * @return string Modified block content.
 */
function ollie_ui_helpers_group_render_block( $block_content, $block, $instance ) {
	$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
	$context = isset( $instance->context ) ? $instance->context : array();

	// Check if this block has group attributes
	if ( ! ollie_ui_helpers_has_group_attributes( $block_name, $attrs ) ) {
		return $block_content;
	}

	// Determine block support
	$supports_linking = in_array( $block_name, array( 'core/group', 'core/column', 'core/cover' ), true );
	$supports_stacking = 'core/group' === $block_name;

	// Process link attributes (both group and column)
	$link_data = $supports_linking ? ollie_ui_helpers_process_group_link( $attrs, $context ) : false;

	// Process stacking attributes (group only)
	$stacking_data = $supports_stacking ? ollie_ui_helpers_process_group_stacking( $attrs ) : array( 'classes' => array() );

	// Process sticky position attributes (group only)
	$sticky_data = $supports_stacking ? ollie_ui_helpers_process_sticky_position( $attrs ) : false;
	
	// Combine all classes
	$all_classes = array();
	if ( $link_data && isset( $link_data['classes'] ) ) {
		$all_classes = array_merge( $all_classes, $link_data['classes'] );
	}
	if ( isset( $stacking_data['classes'] ) ) {
		$all_classes = array_merge( $all_classes, $stacking_data['classes'] );
	}
	if ( $sticky_data && isset( $sticky_data['classes'] ) ) {
		$all_classes = array_merge( $all_classes, $sticky_data['classes'] );
	}
	
	// Apply classes, styles, and data attributes to the group block
	if ( ! empty( $all_classes ) || $sticky_data ) {
		$processor = new WP_HTML_Tag_Processor( $block_content );
		if ( $processor->next_tag() ) {
			// Add classes
			foreach ( $all_classes as $class ) {
				$processor->add_class( $class );
			}
			
			// Add inline styles for sticky position
			if ( $sticky_data && isset( $sticky_data['styles'] ) ) {
				$existing_style = $processor->get_attribute( 'style' );
				if ( $existing_style ) {
					// Ensure existing style ends with semicolon before appending
					$existing_style = rtrim( $existing_style, '; ' ) . '; ';
					$new_style = $existing_style . $sticky_data['styles'];
				} else {
					$new_style = $sticky_data['styles'];
				}
				$processor->set_attribute( 'style', $new_style );
			}
			
			// Add data attributes for sticky position
			if ( $sticky_data && isset( $sticky_data['data_attrs'] ) ) {
				foreach ( $sticky_data['data_attrs'] as $attr_name => $attr_value ) {
					$processor->set_attribute( $attr_name, $attr_value );
				}
			}
			
			$block_content = $processor->get_updated_html();
		}
	}
	
	// Insert the link markup if needed
	if ( $link_data && ! empty( $link_data['url'] ) ) {
		// Empty nested buttons inherit the group URL so they stay a real,
		// focusable link instead of a dead click that blocks the card.
		$block_content = ollie_ui_helpers_sync_empty_button_links(
			$block_content,
			$link_data['url'],
			$link_data['link_target'] ?? '_self',
			$link_data['link_rel'] ?? 'follow'
		);

		// The overlay becomes the card's focusable, labeled link when no
		// element inside links to the group URL — or whenever the author
		// explicitly set a screen reader label.
		$has_inner_link = ollie_ui_helpers_content_links_to(
			$block_content,
			$link_data['url']
		);
		$link_markup    = ollie_ui_helpers_build_group_link_markup(
			$link_data,
			! empty( $link_data['has_custom_label'] ) || ! $has_inner_link
		);

		// Insert the link before the closing tag of the wrapper element
		$closing_tag = '</div>'; // Group blocks typically use div
		$last_pos = strrpos( $block_content, $closing_tag );

		if ( $last_pos !== false ) {
			// Insert the link before the closing tag
			$block_content = substr( $block_content, 0, $last_pos ) . $link_markup . substr( $block_content, $last_pos );
		}
	}

	return $block_content;
}
add_filter( 'render_block', 'ollie_ui_helpers_group_render_block', 10, 3 );

/**
 * Register usesContext for group, column, and cover blocks to receive term context.
 * This allows these blocks to access termId and taxonomy from parent terms query blocks.
 *
 * @since 0.1.0
 * @param array  $args       Block registration arguments.
 * @param string $block_type Block type name.
 * @return array Modified block arguments.
 */
function ollie_ui_helpers_register_term_context( $args, $block_type ) {
	$linkable_blocks = array( 'core/group', 'core/column', 'core/cover' );

	if ( in_array( $block_type, $linkable_blocks, true ) ) {
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
	}

	return $args;
}
add_filter( 'register_block_type_args', 'ollie_ui_helpers_register_term_context', 10, 2 );
