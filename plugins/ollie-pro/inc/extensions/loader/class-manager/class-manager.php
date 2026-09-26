<?php
/**
 * Class Manager Loader
 *
 * Handles REST API endpoints and CSS output for the class manager.
 *
 * @package OlliePro\Extensions
 */

namespace olpo\Extensions\ClassManager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register REST API endpoints for class management.
 */
function register_rest_routes() {
	// Get all CSS classes
	register_rest_route(
		'ollie-pro/v1',
		'/css-classes',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_css_classes',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
		)
	);

	// Save/update a CSS class
	register_rest_route(
		'ollie-pro/v1',
		'/css-classes',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\save_css_class',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
			'args'                => array(
				'className'  => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_html_class',
					'validate_callback' => function ( $value ) {
						// Validate CSS class name format
						// Supports: letters, numbers, hyphens, underscores
						return ! empty( $value ) && preg_match( '/^[a-zA-Z_-][a-zA-Z0-9_-]*$/', $value );
					},
				),
				'css'        => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => __NAMESPACE__ . '\sanitize_css',
				),
				'hoverCss'   => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => __NAMESPACE__ . '\sanitize_css',
				),
				'focusCss'   => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => __NAMESPACE__ . '\sanitize_css',
				),
				'activeCss'  => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => __NAMESPACE__ . '\sanitize_css',
				),
				'disabledCss' => array(
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => __NAMESPACE__ . '\sanitize_css',
				),
				'loadGlobal' => array(
					'required'          => false,
					'type'              => 'boolean',
				),
			),
		)
	);

	// Delete a CSS class
	register_rest_route(
		'ollie-pro/v1',
		'/css-classes',
		array(
			'methods'             => 'DELETE',
			'callback'            => __NAMESPACE__ . '\delete_css_class',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
			'args'                => array(
				'className' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_html_class',
				),
			),
		)
	);

	// Get Global Styles Additional CSS
	register_rest_route(
		'ollie-pro/v1',
		'/global-styles-css',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_global_styles_css',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
		)
	);

	// Save Global Styles Additional CSS
	register_rest_route(
		'ollie-pro/v1',
		'/global-styles-css',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\save_global_styles_css',
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
			'args'                => array(
				'css' => array(
					'required' => true,
					'type'     => 'string',
					// Don't use sanitize_css - it strips newlines with wp_strip_all_tags
					// Global Styles handles its own sanitization
				),
			),
		)
	);

	// Get usage details for a specific class
	register_rest_route(
		'ollie-pro/v1',
		'/class-usage/(?P<className>[a-zA-Z0-9_-]+)',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\get_class_usage',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'className' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_html_class',
				),
			),
		)
	);

	// Update usage for a specific class and post immediately
	register_rest_route(
		'ollie-pro/v1',
		'/toggle-class-usage',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\toggle_class_usage',
			'permission_callback' => function ( $request ) {
				$post_id = $request->get_param( 'postId' );
				return current_user_can( 'edit_post', $post_id );
			},
			'args'                => array(
				'postId'    => array(
					'required'          => true,
					'type'              => 'integer',
					'validate_callback' => function ( $value ) {
						return is_numeric( $value ) && $value > 0;
					},
				),
				'className' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_html_class',
				),
				'action'    => array(
					'required'          => true,
					'type'              => 'string',
					'enum'              => array( 'add', 'remove' ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );

/**
 * Sanitize CSS input.
 *
 * @param string $css Raw CSS input.
 * @return string Sanitized CSS.
 */
function sanitize_css( $css ) {
	// Remove any potential HTML/script tags (but preserve newlines and formatting)
	$css = strip_tags( $css );

	// Remove any @import statements for security
	$css = preg_replace( '/@import\s+[^;]+;/', '', $css );

	// Remove any javascript:, data:, vbscript:, or other dangerous protocol URLs
	$css = preg_replace( '/url\s*\(\s*["\']?(javascript|data|vbscript|file):[^)]*\)/i', '', $css );

	// Validate and sanitize remaining url() values to only allow http(s), relative paths, or no protocol
	$css = preg_replace_callback(
		'/url\s*\(\s*["\']?([^)"\'\s]+)["\']?\s*\)/i',
		function( $matches ) {
			$url = trim( $matches[1] );

			// Allow relative paths (starting with / or ./ or ../)
			if ( preg_match( '/^(\/|\.\/|\.\.\/)/i', $url ) ) {
				return $matches[0];
			}

			// Allow http:// and https:// only
			if ( preg_match( '/^https?:\/\//i', $url ) ) {
				return $matches[0];
			}

			// Allow protocol-relative URLs (//example.com)
			if ( preg_match( '/^\/\//i', $url ) ) {
				return $matches[0];
			}

			// Remove any other URL schemes
			return '';
		},
		$css
	);

	// Remove potentially dangerous CSS constructs
	$css = preg_replace( '/expression\s*\([^)]*\)/i', '', $css ); // Remove IE expression()
	$css = preg_replace( '/-moz-binding\s*:[^;]+;/i', '', $css ); // Remove -moz-binding
	$css = preg_replace( '/behavior\s*:[^;]+;/i', '', $css ); // Remove IE behavior

	return $css;
}

/**
 * Get all CSS classes.
 *
 * @return array List of CSS classes with their CSS.
 */
function get_css_classes() {
	$classes = get_option( 'ollie_pro_css_classes', array() );

	// Ensure it's an array
	if ( ! is_array( $classes ) ) {
		$classes = array();
	}

	// Convert to array format expected by frontend
	$formatted = array();
	foreach ( $classes as $name => $data ) {
		// Handle both old format (string) and new format (array)
		if ( is_string( $data ) ) {
			$formatted[] = array(
				'name'        => sanitize_html_class( $name ),
				'css'         => $data,
				'hoverCss'    => '',
				'focusCss'    => '',
				'activeCss'   => '',
				'disabledCss' => '',
				'loadGlobal'  => false,
				'usage'       => array(),
			);
		} else {
			$formatted[] = array(
				'name'        => sanitize_html_class( $name ),
				'css'         => isset( $data['css'] ) ? $data['css'] : '',
				'hoverCss'    => isset( $data['hoverCss'] ) ? $data['hoverCss'] : '',
				'focusCss'    => isset( $data['focusCss'] ) ? $data['focusCss'] : '',
				'activeCss'   => isset( $data['activeCss'] ) ? $data['activeCss'] : '',
				'disabledCss' => isset( $data['disabledCss'] ) ? $data['disabledCss'] : '',
				'loadGlobal'  => isset( $data['loadGlobal'] ) ? (bool) $data['loadGlobal'] : false,
				'usage'       => isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array(),
			);
		}
	}

	return $formatted;
}

/**
 * Save or update a CSS class.
 *
 * @param \WP_REST_Request $request Request object.
 * @return array Response data.
 */
function save_css_class( $request ) {
	$class_name   = $request->get_param( 'className' );
	$css          = $request->get_param( 'css' ); // Already sanitized by REST API sanitize_callback
	$hover_css    = $request->get_param( 'hoverCss' ); // Already sanitized by REST API sanitize_callback
	$focus_css    = $request->get_param( 'focusCss' ); // Already sanitized by REST API sanitize_callback
	$active_css   = $request->get_param( 'activeCss' ); // Already sanitized by REST API sanitize_callback
	$disabled_css = $request->get_param( 'disabledCss' ); // Already sanitized by REST API sanitize_callback
	$load_global  = $request->get_param( 'loadGlobal' );

	if ( empty( $class_name ) ) {
		return new \WP_Error(
			'invalid_class_name',
			__( 'Invalid class name.', 'ollie-pro' ),
			array( 'status' => 400 )
		);
	}

	$classes = get_option( 'ollie_pro_css_classes', array() );

	// Ensure it's an array
	if ( ! is_array( $classes ) ) {
		$classes = array();
	}

	// Save class with CSS and all state CSS (preserve usage if it exists)
	if ( is_array( $classes[ $class_name ] ) ) {
		// Update CSS values and loadGlobal
		$classes[ $class_name ]['css']         = $css;
		$classes[ $class_name ]['hoverCss']    = $hover_css ? $hover_css : '';
		$classes[ $class_name ]['focusCss']    = $focus_css ? $focus_css : '';
		$classes[ $class_name ]['activeCss']   = $active_css ? $active_css : '';
		$classes[ $class_name ]['disabledCss'] = $disabled_css ? $disabled_css : '';
		if ( null !== $load_global ) {
			$classes[ $class_name ]['loadGlobal'] = (bool) $load_global;
		}
		// Ensure usage array exists
		if ( ! isset( $classes[ $class_name ]['usage'] ) ) {
			$classes[ $class_name ]['usage'] = array();
		}
	} else {
		// New class or old format - save as array with CSS, state CSS and empty usage
		$classes[ $class_name ] = array(
			'css'         => $css,
			'hoverCss'    => $hover_css ? $hover_css : '',
			'focusCss'    => $focus_css ? $focus_css : '',
			'activeCss'   => $active_css ? $active_css : '',
			'disabledCss' => $disabled_css ? $disabled_css : '',
			'loadGlobal'  => $load_global ? (bool) $load_global : false,
			'usage'       => array(),
		);
	}

	update_option( 'ollie_pro_css_classes', $classes );

	return array(
		'success' => true,
		'message' => __( 'Class saved successfully.', 'ollie-pro' ),
	);
}

/**
 * Delete a CSS class.
 *
 * @param \WP_REST_Request $request Request object.
 * @return array Response data.
 */
function delete_css_class( $request ) {
	$class_name = $request->get_param( 'className' );

	if ( empty( $class_name ) ) {
		return new \WP_Error(
			'invalid_class_name',
			__( 'Invalid class name.', 'ollie-pro' ),
			array( 'status' => 400 )
		);
	}

	$classes = get_option( 'ollie_pro_css_classes', array() );

	// Ensure it's an array
	if ( ! is_array( $classes ) ) {
		$classes = array();
	}

	if ( isset( $classes[ $class_name ] ) ) {
		unset( $classes[ $class_name ] );
		update_option( 'ollie_pro_css_classes', $classes );
	}

	return array(
		'success' => true,
		'message' => __( 'Class deleted successfully.', 'ollie-pro' ),
	);
}

/**
 * Enqueue custom CSS in the block editor.
 */
function enqueue_editor_css() {
	// Prevent duplicate enqueues
	static $enqueued = false;
	if ( $enqueued ) {
		return;
	}
	$enqueued = true;

	// Get all saved CSS classes
	$css_classes = get_option( 'ollie_pro_css_classes', array() );

	if ( empty( $css_classes ) || ! is_array( $css_classes ) ) {
		return;
	}

	// Build CSS output for all classes in editor (so users can preview)
	// Scope with body class so CSS only applies when Class Manager is enabled
	$css_output = '';
	foreach ( $css_classes as $class_name => $data ) {
		// Handle both old format (string) and new format (array)
		$css          = is_string( $data ) ? $data : ( isset( $data['css'] ) ? $data['css'] : '' );
		$hover_css    = is_array( $data ) && isset( $data['hoverCss'] ) ? $data['hoverCss'] : '';
		$focus_css    = is_array( $data ) && isset( $data['focusCss'] ) ? $data['focusCss'] : '';
		$active_css   = is_array( $data ) && isset( $data['activeCss'] ) ? $data['activeCss'] : '';
		$disabled_css = is_array( $data ) && isset( $data['disabledCss'] ) ? $data['disabledCss'] : '';

		// Add default CSS with body class scope
		if ( ! empty( $css ) ) {
			$css_output .= sprintf(
				"body.ollie-class-manager-enabled .wp-block.%s { %s }\n",
				esc_attr( $class_name ),
				$css // Already sanitized on save, don't escape for CSS output
			);
		}

		// Add hover CSS with body class scope
		if ( ! empty( $hover_css ) ) {
			$css_output .= sprintf(
				"body.ollie-class-manager-enabled .wp-block.%s:hover { %s }\n",
				esc_attr( $class_name ),
				$hover_css // Already sanitized on save, don't escape for CSS output
			);
		}

		// Add focus CSS with body class scope
		if ( ! empty( $focus_css ) ) {
			$css_output .= sprintf(
				"body.ollie-class-manager-enabled .wp-block.%s:focus { %s }\n",
				esc_attr( $class_name ),
				$focus_css // Already sanitized on save, don't escape for CSS output
			);
		}

		// Add active CSS with body class scope
		if ( ! empty( $active_css ) ) {
			$css_output .= sprintf(
				"body.ollie-class-manager-enabled .wp-block.%s:active { %s }\n",
				esc_attr( $class_name ),
				$active_css // Already sanitized on save, don't escape for CSS output
			);
		}

		// Add disabled CSS with body class scope
		if ( ! empty( $disabled_css ) ) {
			$css_output .= sprintf(
				"body.ollie-class-manager-enabled .wp-block.%s:disabled { %s }\n",
				esc_attr( $class_name ),
				$disabled_css // Already sanitized on save, don't escape for CSS output
			);
		}
	}

	// Output CSS if we have any
	if ( ! empty( $css_output ) ) {
		// Enqueue directly as a separate stylesheet for the editor
		wp_register_style( 'ollie-custom-classes-editor', false );
		wp_enqueue_style( 'ollie-custom-classes-editor' );
		wp_add_inline_style( 'ollie-custom-classes-editor', $css_output );
	}
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_css', 20 );

/**
 * Get content from synced patterns (reusable blocks) referenced in content.
 *
 * Extracts content from wp_block posts to ensure classes used in synced
 * patterns are detected for style output. Uses batched queries for performance.
 *
 * @param string $content The content to search for synced pattern references.
 * @return string The combined content from all referenced synced patterns.
 */
function get_synced_pattern_content( $content ) {
	$synced_content = '';
	$processed      = array();
	$to_process     = array( $content );

	// Process iteratively to handle nested patterns while batching queries
	while ( ! empty( $to_process ) ) {
		$current_content = array_shift( $to_process );

		// Match wp:block references with ref attribute (synced patterns)
		if ( ! preg_match_all( '/<!--\s*wp:block\s*\{[^}]*"ref"\s*:\s*(\d+)[^}]*\}[^>]*-->/i', $current_content, $matches ) ) {
			continue;
		}

		// Filter out already processed IDs
		$block_ids = array_map( 'intval', $matches[1] );
		$block_ids = array_diff( $block_ids, $processed );

		if ( empty( $block_ids ) ) {
			continue;
		}

		// Mark as processed
		$processed = array_merge( $processed, $block_ids );

		// Batch query for all synced patterns at once
		$blocks = get_posts(
			array(
				'post_type'      => 'wp_block',
				'post__in'       => $block_ids,
				'posts_per_page' => count( $block_ids ),
				'no_found_rows'  => true,
			)
		);

		foreach ( $blocks as $block ) {
			if ( ! empty( $block->post_content ) ) {
				$synced_content .= $block->post_content;
				// Queue for checking nested patterns
				$to_process[] = $block->post_content;
			}
		}
	}

	return $synced_content;
}

/**
 * Output custom CSS on the frontend for classes that are in use.
 */
/**
 * Get the current template slug using WordPress template hierarchy.
 *
 * This handles cases where get_page_template_slug() returns empty,
 * such as WooCommerce product pages that use the template hierarchy.
 *
 * @return string The template slug (e.g., 'single-product', 'archive', 'index').
 */
function get_current_template_slug() {
	// First check for manually assigned page template
	$template_slug = get_page_template_slug();
	if ( ! empty( $template_slug ) ) {
		// Remove .php or .html extension if present
		return preg_replace( '/\.(php|html)$/', '', $template_slug );
	}

	// Build template hierarchy candidates based on current context
	$templates = array();

	if ( is_singular() ) {
		$post_type = get_post_type();

		// Add post type specific templates
		if ( 'post' !== $post_type && 'page' !== $post_type ) {
			$templates[] = "single-{$post_type}";
		}

		if ( is_single() ) {
			$templates[] = 'single';
		} elseif ( is_page() ) {
			$templates[] = 'page';
		}

		$templates[] = 'singular';
	} elseif ( is_archive() ) {
		if ( is_post_type_archive() ) {
			$post_type   = get_query_var( 'post_type' );
			$templates[] = "archive-{$post_type}";
		}

		if ( is_tax() ) {
			$term        = get_queried_object();
			$templates[] = "taxonomy-{$term->taxonomy}-{$term->slug}";
			$templates[] = "taxonomy-{$term->taxonomy}";
			$templates[] = 'taxonomy';
		}

		if ( is_category() ) {
			$templates[] = 'category';
		} elseif ( is_tag() ) {
			$templates[] = 'tag';
		}

		$templates[] = 'archive';
	} elseif ( is_search() ) {
		$templates[] = 'search';
	} elseif ( is_404() ) {
		$templates[] = '404';
	} elseif ( is_home() ) {
		$templates[] = 'home';
	} elseif ( is_front_page() ) {
		$templates[] = 'front-page';
	}

	// Always add index as fallback
	$templates[] = 'index';

	// Find the first template that exists as a block template
	$stylesheet = get_stylesheet();
	foreach ( $templates as $template_slug ) {
		$template = get_block_template( "{$stylesheet}//{$template_slug}", 'wp_template' );
		if ( $template ) {
			return $template_slug;
		}
	}

	return 'index';
}

/**
 * Output custom CSS for class manager classes.
 */
function output_custom_css() {
	// Only run on frontend
	if ( is_admin() ) {
		return;
	}

	global $post;

	// Get all saved CSS classes
	$css_classes = get_option( 'ollie_pro_css_classes', array() );

	if ( empty( $css_classes ) || ! is_array( $css_classes ) ) {
		return;
	}

	// Get the current post content
	$content = '';
	if ( $post && isset( $post->post_content ) ) {
		$content = $post->post_content;
	}

	// Check for block templates and template parts
	$template_content = '';
	if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
		// Get current template using proper template hierarchy resolution
		$template_slug = get_current_template_slug();
		$template      = get_block_template( get_stylesheet() . '//' . $template_slug, 'wp_template' );
		if ( $template && isset( $template->content ) ) {
			$template_content .= $template->content;
		}

		// Get header and footer template parts
		$header = get_block_template( get_stylesheet() . '//header', 'wp_template_part' );
		if ( $header && isset( $header->content ) ) {
			$template_content .= $header->content;
		}

		$footer = get_block_template( get_stylesheet() . '//footer', 'wp_template_part' );
		if ( $footer && isset( $footer->content ) ) {
			$template_content .= $footer->content;
		}
	}

	$content .= $template_content;

	// Get content from synced patterns (reusable blocks) referenced in the content
	$content .= get_synced_pattern_content( $content );

	// Build CSS output only for classes that are used OR set to load globally
	$css_output = '';
	foreach ( $css_classes as $class_name => $data ) {
		// Handle both old format (string) and new format (array)
		$css          = is_string( $data ) ? $data : ( isset( $data['css'] ) ? $data['css'] : '' );
		$hover_css    = is_array( $data ) && isset( $data['hoverCss'] ) ? $data['hoverCss'] : '';
		$focus_css    = is_array( $data ) && isset( $data['focusCss'] ) ? $data['focusCss'] : '';
		$active_css   = is_array( $data ) && isset( $data['activeCss'] ) ? $data['activeCss'] : '';
		$disabled_css = is_array( $data ) && isset( $data['disabledCss'] ) ? $data['disabledCss'] : '';
		$load_global  = is_array( $data ) && isset( $data['loadGlobal'] ) ? (bool) $data['loadGlobal'] : false;

		if ( empty( $css ) && empty( $hover_css ) && empty( $focus_css ) && empty( $active_css ) && empty( $disabled_css ) ) {
			continue;
		}

		// Output if class is set to load globally, OR if it's used in content
		$should_output = $load_global;

		if ( ! $should_output ) {
			// Check if class exists in block markup
			$pattern       = '/"ollieCustomClasses":\s*\[[^\]]*"' . preg_quote( $class_name, '/' ) . '"[^\]]*\]/';
			$should_output = preg_match( $pattern, $content );
		}

		if ( $should_output ) {
			// Add default CSS
			if ( ! empty( $css ) ) {
				$css_output .= sprintf(
					".%s { %s }\n",
					esc_attr( $class_name ),
					$css // Already sanitized on save, don't escape for CSS output
				);
			}

			// Add hover CSS
			if ( ! empty( $hover_css ) ) {
				$css_output .= sprintf(
					".%s:hover { %s }\n",
					esc_attr( $class_name ),
					$hover_css // Already sanitized on save, don't escape for CSS output
				);
			}

			// Add focus CSS
			if ( ! empty( $focus_css ) ) {
				$css_output .= sprintf(
					".%s:focus { %s }\n",
					esc_attr( $class_name ),
					$focus_css // Already sanitized on save, don't escape for CSS output
				);
			}

			// Add active CSS
			if ( ! empty( $active_css ) ) {
				$css_output .= sprintf(
					".%s:active { %s }\n",
					esc_attr( $class_name ),
					$active_css // Already sanitized on save, don't escape for CSS output
				);
			}

			// Add disabled CSS
			if ( ! empty( $disabled_css ) ) {
				$css_output .= sprintf(
					".%s:disabled { %s }\n",
					esc_attr( $class_name ),
					$disabled_css // Already sanitized on save, don't escape for CSS output
				);
			}
		}
	}

	// Output CSS if we have any
	if ( ! empty( $css_output ) ) {
		printf(
			'<style id="ollie-pro-custom-classes">%s</style>',
			$css_output // Sanitized on save and escaped on output
		);
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\output_custom_css', 100 );

/**
 * Get Global Styles Additional CSS.
 *
 * @return array CSS content.
 */
function get_global_styles_css() {
	$css = '';

	// Get from the global styles post directly
	$global_styles_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

	if ( $global_styles_id ) {
		$post = get_post( $global_styles_id );

		if ( $post && $post->post_content ) {
			$config = json_decode( $post->post_content, true );

			// Check for styles.css path in the config (Additional CSS location)
			if ( isset( $config['styles']['css'] ) ) {
				$css = $config['styles']['css'];
			}
		}
	}

	return array(
		'css' => $css,
	);
}

/**
 * Save Global Styles Additional CSS.
 *
 * @param \WP_REST_Request $request Request object.
 * @return array Response data.
 */
function save_global_styles_css( $request ) {
	$css = $request->get_param( 'css' );

	// Validate that CSS is a string
	if ( ! is_string( $css ) ) {
		return new \WP_Error(
			'invalid_css',
			__( 'CSS must be a string.', 'ollie-pro' ),
			array( 'status' => 400 )
		);
	}

	// Normalize newlines - ensure we have proper line breaks
	$css = str_replace( array( "\r\n", "\r" ), "\n", $css );

	// Try using wp_update_global_styles_custom_css if available (WordPress 6.7+)
	if ( function_exists( 'wp_update_global_styles_custom_css' ) ) {
		wp_update_global_styles_custom_css( $css );
	} else {
		// Update Global Styles custom CSS manually
		// Get the current global styles post
		$global_styles_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

		if ( ! $global_styles_id ) {
			return new \WP_Error(
				'no_global_styles',
				__( 'Global Styles post not found.', 'ollie-pro' ),
				array( 'status' => 404 )
			);
		}

		// Get current post and verify it exists and is the correct post type
		$post = get_post( $global_styles_id );

		if ( ! $post || 'wp_global_styles' !== $post->post_type ) {
			return new \WP_Error(
				'invalid_post',
				__( 'Invalid Global Styles post.', 'ollie-pro' ),
				array( 'status' => 400 )
			);
		}

		// Parse existing config
		$config = array();
		if ( $post->post_content ) {
			$config = json_decode( $post->post_content, true );
		}

		if ( ! is_array( $config ) ) {
			$config = array();
		}

		// Update the custom CSS in styles.css
		if ( ! isset( $config['styles'] ) ) {
			$config['styles'] = array();
		}

		$config['styles']['css'] = $css;

		// Encode to JSON with proper escaping
		$encoded = wp_json_encode( $config, JSON_UNESCAPED_SLASHES );

		if ( false === $encoded ) {
			return new \WP_Error(
				'encoding_failed',
				__( 'Failed to encode Global Styles.', 'ollie-pro' ),
				array( 'status' => 500 )
			);
		}

		// Use wpdb to update directly to preserve JSON encoding
		// wp_update_post() filters content and corrupts the \n escape sequences
		global $wpdb;
		$result = $wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $encoded ),
			array( 'ID' => $global_styles_id ),
			array( '%s' ), // post_content is string
			array( '%d' )  // ID is integer
		);

		if ( false === $result ) {
			return new \WP_Error(
				'update_failed',
				__( 'Failed to update Global Styles.', 'ollie-pro' ),
				array( 'status' => 500 )
			);
		}

		// Clear caches
		clean_post_cache( $global_styles_id );
		wp_cache_delete( 'theme_json', 'themes' );
	}

	return array(
		'success' => true,
		'message' => __( 'Global Styles CSS saved successfully.', 'ollie-pro' ),
	);
}

/**
 * Toggle usage for a specific class and post immediately.
 *
 * @param \WP_REST_Request $request Request object.
 * @return array Success response.
 */
function toggle_class_usage( $request ) {
	$post_id    = $request->get_param( 'postId' );
	$class_name = $request->get_param( 'className' );
	$action     = $request->get_param( 'action' );

	// Get the post
	$post = get_post( $post_id );

	if ( ! $post ) {
		return new \WP_Error(
			'post_not_found',
			__( 'Post not found.', 'ollie-pro' ),
			array( 'status' => 404 )
		);
	}

	// Only update for post types that use the block editor and are published
	if ( ! use_block_editor_for_post_type( $post->post_type ) || 'publish' !== $post->post_status ) {
		return array(
			'success' => false,
			'message' => __( 'Only published content that uses the block editor is tracked.', 'ollie-pro' ),
		);
	}

	// Get all classes
	$all_classes = get_option( 'ollie_pro_css_classes', array() );

	if ( ! is_array( $all_classes ) || ! isset( $all_classes[ $class_name ] ) ) {
		return array(
			'success' => false,
			'message' => __( 'Class not found.', 'ollie-pro' ),
		);
	}

	$class_data = $all_classes[ $class_name ];

	// Ensure class data is an array
	if ( ! is_array( $class_data ) ) {
		$class_data = array(
			'css'   => $class_data,
			'usage' => array(),
		);
	}

	// Ensure usage array exists
	if ( ! isset( $class_data['usage'] ) || ! is_array( $class_data['usage'] ) ) {
		$class_data['usage'] = array();
	}

	// Add or remove based on action
	if ( 'add' === $action ) {
		// Add post ID if not already there
		if ( ! in_array( $post_id, $class_data['usage'], true ) ) {
			$class_data['usage'][] = $post_id;
		}
	} elseif ( 'remove' === $action ) {
		// Remove post ID if it's there
		$class_data['usage'] = array_diff( $class_data['usage'], array( $post_id ) );
		$class_data['usage'] = array_values( $class_data['usage'] ); // Re-index
	}

	// Update the class data
	$all_classes[ $class_name ] = $class_data;

	// Save updated classes
	update_option( 'ollie_pro_css_classes', $all_classes );

	return array(
		'success' => true,
		'message' => __( 'Usage updated successfully.', 'ollie-pro' ),
	);
}

/**
 * Get usage details for a specific class.
 *
 * @param \WP_REST_Request $request Request object.
 * @return array Usage details with post titles and URLs.
 */
function get_class_usage( $request ) {
	$class_name = $request->get_param( 'className' );

	if ( empty( $class_name ) ) {
		return new \WP_Error(
			'invalid_class_name',
			__( 'Invalid class name.', 'ollie-pro' ),
			array( 'status' => 400 )
		);
	}

	// Get all classes
	$all_classes = get_option( 'ollie_pro_css_classes', array() );

	// Check if class exists
	if ( ! isset( $all_classes[ $class_name ] ) ) {
		return array(
			'count' => 0,
			'posts' => array(),
		);
	}

	$class_data = $all_classes[ $class_name ];

	// Get usage array
	$usage = array();
	if ( is_array( $class_data ) && isset( $class_data['usage'] ) && is_array( $class_data['usage'] ) ) {
		$usage = $class_data['usage'];
	}

	// If no usage, return empty
	if ( empty( $usage ) ) {
		return array(
			'count' => 0,
			'posts' => array(),
		);
	}

	// Get post details for each post ID
	$posts = array();
	foreach ( $usage as $post_id ) {
		$post = get_post( $post_id );

		// Skip if post doesn't exist or isn't published
		if ( ! $post || 'publish' !== $post->post_status ) {
			continue;
		}

		$posts[] = array(
			'id'      => $post->ID,
			'title'   => $post->post_title ? $post->post_title : __( '(no title)', 'ollie-pro' ),
			'type'    => $post->post_type,
			'slug'    => $post->post_name,
			'editUrl' => get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	return array(
		'count' => count( $posts ),
		'posts' => $posts,
	);
}

/**
 * Update class usage tracking when a post is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function update_class_usage_on_save( $post_id, $post ) {
	// Skip for autosaves, revisions, and non-post/page types
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Only track post types that use the block editor and are published
	if ( ! use_block_editor_for_post_type( $post->post_type ) || 'publish' !== $post->post_status ) {
		return;
	}

	// Parse the post content to find classes used
	$classes_in_post = extract_classes_from_content( $post->post_content );

	// Get all saved classes
	$all_classes = get_option( 'ollie_pro_css_classes', array() );

	if ( ! is_array( $all_classes ) ) {
		return;
	}

	// Update usage for each class
	foreach ( $all_classes as $class_name => $class_data ) {
		// Ensure class data is an array
		if ( ! is_array( $class_data ) ) {
			$class_data = array(
				'css'   => $class_data,
				'usage' => array(),
			);
		}

		// Ensure usage array exists
		if ( ! isset( $class_data['usage'] ) || ! is_array( $class_data['usage'] ) ) {
			$class_data['usage'] = array();
		}

		// Add or remove post ID based on whether class is used
		if ( in_array( $class_name, $classes_in_post, true ) ) {
			// Add post ID if not already there
			if ( ! in_array( $post_id, $class_data['usage'], true ) ) {
				$class_data['usage'][] = $post_id;
			}
		} else {
			// Remove post ID if it's there
			$class_data['usage'] = array_diff( $class_data['usage'], array( $post_id ) );
			$class_data['usage'] = array_values( $class_data['usage'] ); // Re-index
		}

		// Update the class data
		$all_classes[ $class_name ] = $class_data;
	}

	// Save updated classes
	update_option( 'ollie_pro_css_classes', $all_classes );
}
add_action( 'save_post', __NAMESPACE__ . '\update_class_usage_on_save', 10, 2 );

/**
 * Extract class names from post content.
 *
 * @param string $content Post content.
 * @return array Array of class names found.
 */
function extract_classes_from_content( $content ) {
	$classes = array();

	// Look for ollieCustomClasses attribute in block markup
	// Pattern: "ollieCustomClasses":["class1","class2"]
	if ( preg_match_all( '/"ollieCustomClasses":\s*\[(.*?)\]/', $content, $matches ) ) {
		foreach ( $matches[1] as $match ) {
			// Extract individual class names from the JSON array
			if ( preg_match_all( '/"([^"]+)"/', $match, $class_matches ) ) {
				$classes = array_merge( $classes, $class_matches[1] );
			}
		}
	}

	// Return unique class names
	return array_unique( $classes );
}

/**
 * Clean up class usage when a post is deleted.
 *
 * @param int $post_id Post ID.
 */
function cleanup_class_usage_on_delete( $post_id ) {
	// Get all saved classes
	$all_classes = get_option( 'ollie_pro_css_classes', array() );

	if ( ! is_array( $all_classes ) ) {
		return;
	}

	$updated = false;

	// Remove post ID from all classes
	foreach ( $all_classes as $class_name => $class_data ) {
		// Ensure class data is an array
		if ( ! is_array( $class_data ) ) {
			continue;
		}

		// Ensure usage array exists
		if ( ! isset( $class_data['usage'] ) || ! is_array( $class_data['usage'] ) ) {
			continue;
		}

		// Remove post ID if it exists
		if ( in_array( $post_id, $class_data['usage'], true ) ) {
			$class_data['usage'] = array_diff( $class_data['usage'], array( $post_id ) );
			$class_data['usage'] = array_values( $class_data['usage'] ); // Re-index
			$all_classes[ $class_name ] = $class_data;
			$updated = true;
		}
	}

	// Save if updated
	if ( $updated ) {
		update_option( 'ollie_pro_css_classes', $all_classes );
	}
}
add_action( 'delete_post', __NAMESPACE__ . '\cleanup_class_usage_on_delete' );
