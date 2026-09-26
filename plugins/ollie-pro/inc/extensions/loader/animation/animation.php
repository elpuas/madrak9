<?php
/**
 * Animation Controls
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include REST API for animation presets
require_once __DIR__ . '/animation-presets-api.php';

/**
 * Check if block has animation attributes
 * Internal function used by animation render_block filter
 *
 * @since 0.1.0
 * @access private
 * @param array $attrs Block attributes.
 * @return bool Whether block has animation attributes.
 */
function ollie_ui_helpers_has_animation_attributes( $attrs ) {
	return isset( $attrs['animationType'] ) ||
	       isset( $attrs['animationDuration'] ) ||
	       isset( $attrs['animationDelay'] ) ||
	       isset( $attrs['animateOnScroll'] ) ||
	       isset( $attrs['animationPresetId'] );
}

/**
 * Get animation classes to remove based on attributes
 * Internal function used by animation render_block filter
 *
 * @since 0.1.0
 * @access private
 * @param array $attrs Block attributes.
 * @return array Classes that should be removed.
 */
function ollie_ui_helpers_get_animation_classes_to_remove( $attrs ) {
	$classes_to_remove = array();
	
	// Remove sequential class if animation is not sequential or no animation type
	if ( empty( $attrs['animateSequentially'] ) || empty( $attrs['animationType'] ) ) {
		$classes_to_remove[] = 'ollie-animate-sequentially';
	}
	
	return $classes_to_remove;
}

/**
 * Remove old animation classes from block
 * Internal function used by animation render_block filter
 *
 * @since 0.1.0
 * @access private
 * @param object $processor WP_HTML_Tag_Processor instance.
 * @param array  $attrs Block attributes.
 */
function ollie_ui_helpers_remove_old_animation_classes( $processor, $attrs ) {
	// Remove old animation classes when no animation is set
	if ( empty( $attrs['animationType'] ) && empty( $attrs['animationPresetId'] ) ) {
		// Get current class attribute to find and remove all ollie-animate* classes
		$current_classes = $processor->get_attribute( 'class' );
		if ( $current_classes ) {
			$class_array = explode( ' ', $current_classes );
			foreach ( $class_array as $existing_class ) {
				// Remove ALL classes that start with 'ollie-animate' (includes base class and all variations)
				if ( strpos( $existing_class, 'ollie-animate' ) === 0 ) {
					$processor->remove_class( $existing_class );
				}
				// Remove animation preset classes
				if ( strpos( $existing_class, 'ollie-animation-preset-' ) === 0 ) {
					$processor->remove_class( $existing_class );
				}
			}
		}
	}
}

/**
 * Process animation block attributes.
 *
 * @since 0.1.0
 * @param array $attrs Block attributes.
 * @return array Array containing 'styles' and 'classes' arrays.
 */
function ollie_ui_helpers_process_animation_attributes( $attrs ) {
	$styles  = array();
	$classes = array();
	
	// Check if using a preset
	if ( ! empty( $attrs['animationPresetId'] ) ) {
		// Find the preset
		$presets = get_option( 'ollie_animation_presets', array() );
		$preset = null;
		foreach ( $presets as $p ) {
			if ( $p['id'] === $attrs['animationPresetId'] ) {
				$preset = $p;
				break;
			}
		}
		
		// If preset exists, apply it
		if ( $preset ) {
			$preset_id = sanitize_html_class( $attrs['animationPresetId'] );
			$classes[] = 'ollie-animation-preset-' . $preset_id;
			$classes[] = 'ollie-animate';

			// Get preset settings (with backward compatibility)
			$settings = isset( $preset['settings'] ) ? $preset['settings'] : $preset;

			// Get animation type from preset settings
			$animation_type = $settings['animationType'] ?? '';

			// Add the animation type class for animations that require specific CSS selectors
			// (e.g., zoomBackgroundOnHover targets .wp-block-cover.ollie-animate-zoomBackgroundOnHover)
			if ( ! empty( $animation_type ) ) {
				$classes[] = 'ollie-animate-' . sanitize_html_class( $animation_type );
			}

			// Handle sequential animation
			if ( ! empty( $settings['animateSequentially'] ) ) {
				$classes[] = 'ollie-animate-sequentially';
				$styles[] = sprintf( '--sequential-delay: %ss;', floatval( $settings['sequentialDelay'] ?? 0.2 ) );
			}

			// Handle scroll-based animations (needed for JS functionality)
			$is_hover = ( $animation_type === 'scaleOnHover' || $animation_type === 'zoomBackgroundOnHover' );
			// Get animate on scroll from preset settings
			$animate_on_scroll = $settings['animateOnScroll'] ?? false;
			
			if ( ! $is_hover && $animate_on_scroll ) {
				$classes[] = 'ollie-animate-on-scroll';
				// Get animate once from preset settings
				if ( $settings['animateOnce'] ?? true ) {
					$classes[] = 'ollie-animate-once';
				}
			}
		}
		
		return array(
			'styles'  => $styles,
			'classes' => $classes,
		);
	}
	
	// Process animation type (non-preset)
	if ( ! empty( $attrs['animationType'] ) ) {
		$animation_type = sanitize_html_class( $attrs['animationType'] );
		$classes[] = 'ollie-animate';
		$classes[] = 'ollie-animate-' . $animation_type;
		
		// Set animation duration (default: 1s)
		$duration = floatval( $attrs['animationDuration'] ?? 1 );
		if ( $duration !== 1 ) {
			$styles[] = sprintf( '--animation-duration: %ss;', $duration );
		}
		
		// Set animation delay (default: 0s)
		$delay = floatval( $attrs['animationDelay'] ?? 0 );
		if ( $delay > 0 ) {
			$styles[] = sprintf( '--animation-delay: %ss;', $delay );
		}
		
		// Process animation distance/scale
		$scale_animations = array( 'zoomIn', 'pulse', 'scaleOnHover', 'zoomBackgroundOnHover' );

		if ( in_array( $animation_type, $scale_animations, true ) ) {
			// Use the dedicated scale attribute for scale animations
			$scale = floatval( $attrs['animationScale'] ?? 1.05 );
			// Always output scale for scale animations
			$styles[] = sprintf( '--animation-scale: %s;', $scale );
		} elseif ( $animation_type === 'fadeInWords' ) {
			// Per-word stagger delay
			$word_delay = floatval( $attrs['wordDelay'] ?? 0.2 );
			$styles[] = sprintf( '--word-delay: %ss;', $word_delay );
		} else {
			// Use distance in pixels for movement animations
			$distance = intval( $attrs['animationDistance'] ?? 30 );
			if ( $distance !== 30 ) {
				$styles[] = sprintf( '--animation-distance: %spx;', $distance );
			}
		}

		// Configure scroll-based animations (exclude hover animations)
		$is_hover_animation = ( $animation_type === 'scaleOnHover' || $animation_type === 'zoomBackgroundOnHover' );
		// Default to false for animateOnScroll
		$animate_on_scroll = isset( $attrs['animateOnScroll'] ) && $attrs['animateOnScroll'];
		
		if ( ! $is_hover_animation && $animate_on_scroll ) {
			$classes[] = 'ollie-animate-on-scroll';
			
			// Default to true for animateOnce if not explicitly set to false
			$animate_once = ! isset( $attrs['animateOnce'] ) || $attrs['animateOnce'];
			if ( $animate_once ) {
				$classes[] = 'ollie-animate-once';
			}
			
			// Add scroll offset (always output for JavaScript to use)
			$scroll_offset = isset( $attrs['scrollOffset'] ) ? intval( $attrs['scrollOffset'] ) : -50;
			$styles[] = sprintf( '--scroll-offset: %spx;', $scroll_offset );
		}
		
		// Add sequential animation support
		if ( ! empty( $attrs['animateSequentially'] ) && $attrs['animateSequentially'] ) {
			$classes[] = 'ollie-animate-sequentially';
			$sequential_delay = isset( $attrs['sequentialDelay'] ) ? floatval( $attrs['sequentialDelay'] ) : 0.2;
			// Always output the sequential delay for JavaScript to use
			$styles[] = sprintf( '--sequential-delay: %ss;', $sequential_delay );
		}
	}
	
	return array(
		'styles'  => $styles,
		'classes' => $classes,
	);
}

/**
 * Generate CSS for animation presets
 * 
 * @since 0.1.0
 * @return string Generated CSS
 */
function ollie_ui_helpers_generate_preset_css() {
	$presets = get_option( 'ollie_animation_presets', array() );
	
	if ( empty( $presets ) ) {
		return '';
	}
	
	$css = '';
	
	foreach ( $presets as $preset ) {
		if ( empty( $preset['id'] ) || empty( $preset['settings'] ) ) {
			continue;
		}
		
		$settings = $preset['settings'];
		$selector = '.ollie-animation-preset-' . sanitize_html_class( $preset['id'] );
		
		// Build CSS rules for this preset
		$rules = array();
		
		// We'll handle animation type later for the full CSS generation
		
		// Always set animation duration (even if default) so var() works
		$duration = isset( $settings['animationDuration'] ) ? floatval( $settings['animationDuration'] ) : 1;
		$rules[] = sprintf( '--animation-duration: %ss', $duration );
		
		// Always set animation delay (even if default) so var() works
		$delay = isset( $settings['animationDelay'] ) ? floatval( $settings['animationDelay'] ) : 0;
		$rules[] = sprintf( '--animation-delay: %ss', $delay );
		
		// Distance or Scale - always set for consistency
		$scale_animations = array( 'zoomIn', 'pulse', 'scaleOnHover', 'zoomBackgroundOnHover' );
		
		// Check both nested and flat structure for animation type
		$check_animation_type = ! empty( $settings['animationType'] ) ? $settings['animationType'] : ( ! empty( $preset['animationType'] ) ? $preset['animationType'] : '' );
		
		if ( ! empty( $check_animation_type ) && in_array( $check_animation_type, $scale_animations, true ) ) {
			// Use the dedicated scale attribute for scale animations
			$scale = isset( $settings['animationScale'] ) ? floatval( $settings['animationScale'] ) : 1.05;
			$rules[] = sprintf( '--animation-scale: %s', $scale );
		} elseif ( $check_animation_type === 'fadeInWords' ) {
			// Per-word stagger delay
			$word_delay = isset( $settings['wordDelay'] ) ? floatval( $settings['wordDelay'] ) : 0.2;
			$rules[] = sprintf( '--word-delay: %ss', $word_delay );
		} else {
			// Always set distance for movement animations
			$distance = isset( $settings['animationDistance'] ) ? intval( $settings['animationDistance'] ) : 30;
			$rules[] = sprintf( '--animation-distance: %spx', $distance );
		}
		
		// Scroll offset for scroll-triggered animations (always output for consistency)
		$scroll_offset_preset = isset( $settings['scrollOffset'] ) ? intval( $settings['scrollOffset'] ) : -50;
		$rules[] = sprintf( '--scroll-offset: %spx', $scroll_offset_preset );
		
		// Sequential delay for sequential animations
		if ( ! empty( $settings['animateSequentially'] ) ) {
			$sequential_delay_preset = isset( $settings['sequentialDelay'] ) ? floatval( $settings['sequentialDelay'] ) : 0.2;
			$rules[] = sprintf( '--sequential-delay: %ss', $sequential_delay_preset );
		}
		
		// Generate the CSS rule if we have properties
		if ( ! empty( $rules ) ) {
			$css .= sprintf( "%s { %s } ", $selector, implode( '; ', $rules ) );
		}
		
		// Generate animation CSS for the preset
		// Check both nested and flat structure for backward compatibility
		$animation_type = ! empty( $settings['animationType'] ) ? $settings['animationType'] : ( ! empty( $preset['animationType'] ) ? $preset['animationType'] : '' );
		
		if ( ! empty( $animation_type ) ) {

			// Convert animation type to keyframe name (e.g. fadeInLeft -> ollieAnimateFadeInLeft)
			$keyframe_name = 'ollieAnimate' . ucfirst( $animation_type );

			// fadeInWords animates individual word spans, not the parent element,
			// so skip the parent-level animation/initial-state rules. The base
			// .ollie-animate-fadeInWords selectors in index.scss handle the words.
			if ( $animation_type !== 'fadeInWords' ) {
				// For scroll-based animations when animated
				$css .= sprintf(
					"%s.ollie-animate-on-scroll.ollie-animated { animation: %s var(--animation-duration) ease-out var(--animation-delay) both; } ",
					$selector,
					$keyframe_name
				);

				// For non-scroll animations (immediate) - exclude hover animations that don't use keyframes
				$css .= sprintf(
					"%s:not(.ollie-animate-on-scroll):not(.ollie-animate-scaleOnHover):not(.ollie-animate-zoomBackgroundOnHover) { animation: %s var(--animation-duration) ease-out var(--animation-delay) both; } ",
					$selector,
					$keyframe_name
				);

				// Special handling for scaleOnHover
				if ( $animation_type === 'scaleOnHover' ) {
					$css .= sprintf(
						"%s { transition: transform var(--animation-duration, 1s) ease; transform-origin: center center; } ",
						$selector
					);
					$css .= sprintf(
						"%s:hover { transform: scale(var(--animation-scale, 1.05)); } ",
						$selector
					);
				}

				// Set initial state for scroll animations
				$scale_animations = array( 'zoomIn', 'pulse' );
				if ( in_array( $animation_type, $scale_animations, true ) ) {
					// Zoom/pulse animations start from opacity 0
					$css .= sprintf( "%s.ollie-animate-on-scroll:not(.ollie-animated) { opacity: 0; } ", $selector );
				} elseif ( strpos( $animation_type, 'fade' ) !== false ) {
					// Fade animations start from opacity 0
					$css .= sprintf( "%s.ollie-animate-on-scroll:not(.ollie-animated) { opacity: 0; } ", $selector );
				}
			}
		}
	}
	
	return $css;
}

/**
 * Get or generate cached preset CSS
 * 
 * @since 0.1.0
 * @return string CSS content
 */
function ollie_ui_helpers_get_preset_css() {
	// Use transient for caching with a unique key based on preset data
	$presets = get_option( 'ollie_animation_presets', array() );
	$cache_key = 'ollie_preset_css_' . md5( wp_json_encode( $presets ) );
	
	$css = get_transient( $cache_key );
	
	if ( false === $css ) {
		$css = ollie_ui_helpers_generate_preset_css();
		// Cache for 24 hours
		set_transient( $cache_key, $css, DAY_IN_SECONDS );
	}
	
	return $css;
}

/**
 * Clear preset CSS cache
 * Called when presets are updated
 * 
 * @since 0.1.0
 */
function ollie_ui_helpers_clear_preset_css_cache() {
	// Delete all transients that match our pattern
	global $wpdb;
	$wpdb->query( 
		"DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE '_transient_ollie_preset_css_%' 
		OR option_name LIKE '_transient_timeout_ollie_preset_css_%'"
	);
}

/**
 * Filter block content to apply animation classes and styles
 *
 * @since 0.1.0
 * @param string $block_content The block content.
 * @param array  $block The block data.
 * @return string Modified block content.
 */
function ollie_ui_helpers_animation_render_block( $block_content, $block ) {
	$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
	
	// Check if this block has animation attributes
	if ( ! ollie_ui_helpers_has_animation_attributes( $attrs ) ) {
		return $block_content;
	}
	
	// Process animation attributes to get styles and classes
	$animation_data = ollie_ui_helpers_process_animation_attributes( $attrs );
	
	// Get classes that need to be removed
	$classes_to_remove = ollie_ui_helpers_get_animation_classes_to_remove( $attrs );
	
	// If we have something to process
	if ( ! empty( $animation_data['styles'] ) || ! empty( $animation_data['classes'] ) || ! empty( $classes_to_remove ) ) {
		// Create HTML processor
		$processor = new WP_HTML_Tag_Processor( $block_content );
		
		// Process the first HTML tag
		if ( $processor->next_tag() ) {
			// Remove classes that shouldn't be present
			foreach ( $classes_to_remove as $class ) {
				$processor->remove_class( $class );
			}
			
			// Handle removing old animation classes
			ollie_ui_helpers_remove_old_animation_classes( $processor, $attrs );
			
			// Add animation classes
			foreach ( $animation_data['classes'] as $class ) {
				$processor->add_class( $class );
			}
			
			// Add or merge animation styles
			if ( ! empty( $animation_data['styles'] ) ) {
				$style = implode( ' ', $animation_data['styles'] );
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
add_filter( 'render_block', 'ollie_ui_helpers_animation_render_block', 10, 2 );

/**
 * Strip leaked animation indicator buttons from rendered content.
 *
 * A previous version injected <button class="ollie-animate-indicator"> via DOM
 * manipulation in the editor. If serialization raced the cleanup, the button
 * persisted into post_content. This filter removes any that leaked.
 *
 * @since 2.5.0
 * @param string $block_content The block content.
 * @return string Cleaned block content.
 */
function ollie_ui_helpers_strip_leaked_indicator( $block_content ) {
	if ( strpos( $block_content, 'ollie-animate-indicator' ) !== false ) {
		$block_content = preg_replace(
			'/<button\s+class="ollie-animate-indicator"[^>]*><\/button>/',
			'',
			$block_content
		);
	}
	return $block_content;
}
add_filter( 'render_block', 'ollie_ui_helpers_strip_leaked_indicator', 5 );

/**
 * Enqueue animation scripts and styles for frontend
 */
function ollie_ui_helpers_enqueue_animation_scripts() {
	// Only enqueue on the frontend, not in the editor
	if ( is_admin() ) {
		return;
	}
	
	// Always enqueue the animation script on frontend pages
	// The script will only activate if it finds elements with the animation classes
	wp_enqueue_script(
		'ollie-extensions-animation',
		OLPO_URL . '/inc/extensions/build/animation-frontend.js',
		array(),
		OLPO_VERSION,
		true
	);
	
	// Add preset CSS inline
	$preset_css = ollie_ui_helpers_get_preset_css();
	if ( ! empty( $preset_css ) ) {
		// Add to the main plugin stylesheet
		wp_add_inline_style( 'ollie-extensions-animation-style', $preset_css );
	}
}
add_action( 'wp_enqueue_scripts', 'ollie_ui_helpers_enqueue_animation_scripts' );

/**
 * Add preset CSS to block editor and frontend
 */
function ollie_ui_helpers_add_preset_css() {
	$preset_css = ollie_ui_helpers_get_preset_css();
	if ( ! empty( $preset_css ) ) {
		// Output CSS directly in head for both editor and frontend
		// Output CSS safely - it's already sanitized during generation
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<style id="ollie-animation-preset-css">' . $preset_css . '</style>'; 
	}
}
// Add to both frontend and editor
add_action( 'wp_head', 'ollie_ui_helpers_add_preset_css' );
add_action( 'admin_head', 'ollie_ui_helpers_add_preset_css' );
