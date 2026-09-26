<?php
/**
 * Animation Presets REST API
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include constants
require_once __DIR__ . '/animation-constants.php';

/**
 * Register REST API routes for animation presets
 */
function ollie_ui_helpers_register_preset_routes() {
	register_rest_route( 'ollie-extensions/v1', '/animation-presets', array(
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'ollie_ui_helpers_get_presets',
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		),
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'ollie_ui_helpers_save_preset',
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		),
	) );

	register_rest_route( 'ollie-extensions/v1', '/animation-presets/(?P<id>[a-zA-Z0-9_-]+)', array(
		array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => 'ollie_ui_helpers_update_preset',
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		),
		array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => 'ollie_ui_helpers_delete_preset',
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		),
	) );
}
add_action( 'rest_api_init', 'ollie_ui_helpers_register_preset_routes' );

/**
 * Migrate old preset IDs to slug-based IDs
 * 
 * @param array $presets Array of presets
 * @return array Updated presets
 */
function ollie_ui_helpers_migrate_preset_ids( $presets ) {
	$needs_update = false;
	$used_ids = array();
	
	foreach ( $presets as &$preset ) {
		// Check if this is an old-style ID (preset_timestamp_random)
		if ( preg_match( '/^preset_\d+_\d+$/', $preset['id'] ) ) {
			// Generate new slug-based ID from name
			$base_id = sanitize_title( $preset['name'] );
			$new_id = $base_id;
			
			// Ensure uniqueness
			$counter = 2;
			while ( in_array( $new_id, $used_ids, true ) ) {
				$new_id = $base_id . '-' . $counter;
				$counter++;
			}
			
			$preset['id'] = $new_id;
			$used_ids[] = $new_id;
			$needs_update = true;
		} else {
			$used_ids[] = $preset['id'];
		}
	}
	
	// Save if we migrated any IDs
	if ( $needs_update ) {
		update_option( 'ollie_animation_presets', $presets );
		// Clear CSS cache since IDs changed
		ollie_ui_helpers_clear_preset_css_cache();
	}
	
	return $presets;
}

/**
 * Get all animation presets
 * 
 * @return WP_REST_Response Array of presets
 */
function ollie_ui_helpers_get_presets() {
	$presets = get_option( 'ollie_animation_presets', array() );
	
	// Ensure presets is an array
	if ( ! is_array( $presets ) ) {
		$presets = array();
	}
	
	// Migrate old IDs if needed
	$presets = ollie_ui_helpers_migrate_preset_ids( $presets );
	
	return rest_ensure_response( $presets );
}

/**
 * Build preset settings array from parameters
 * 
 * @param array $params Request parameters
 * @param array $existing Optional existing values to use for missing values
 * @return array Sanitized settings array
 */
function ollie_ui_helpers_build_preset_settings( $params, $existing = array() ) {
	$defaults = ollie_ui_helpers_get_animation_defaults();

	return array(
		'animationType'       => isset( $params['animationType'] ) ? sanitize_text_field( $params['animationType'] ) : ( $existing['animationType'] ?? '' ),
		'animationDuration'   => isset( $params['animationDuration'] ) ? floatval( $params['animationDuration'] ) : ( $existing['animationDuration'] ?? $defaults['duration'] ),
		'animationDelay'      => isset( $params['animationDelay'] ) ? floatval( $params['animationDelay'] ) : ( $existing['animationDelay'] ?? $defaults['delay'] ),
		'animationDistance'   => isset( $params['animationDistance'] ) ? intval( $params['animationDistance'] ) : ( $existing['animationDistance'] ?? $defaults['distance'] ),
		'animationScale'      => isset( $params['animationScale'] ) ? floatval( $params['animationScale'] ) : ( $existing['animationScale'] ?? $defaults['scale'] ),
		'animateOnScroll'     => isset( $params['animateOnScroll'] ) ? (bool) $params['animateOnScroll'] : ( $existing['animateOnScroll'] ?? $defaults['animateOnScroll'] ),
		'animateOnce'         => isset( $params['animateOnce'] ) ? (bool) $params['animateOnce'] : ( $existing['animateOnce'] ?? $defaults['animateOnce'] ),
		'scrollOffset'        => isset( $params['scrollOffset'] ) ? intval( $params['scrollOffset'] ) : ( $existing['scrollOffset'] ?? $defaults['scrollOffset'] ),
		'animateSequentially' => isset( $params['animateSequentially'] ) ? (bool) $params['animateSequentially'] : ( $existing['animateSequentially'] ?? $defaults['animateSequentially'] ),
		'sequentialDelay'     => isset( $params['sequentialDelay'] ) ? floatval( $params['sequentialDelay'] ) : ( $existing['sequentialDelay'] ?? $defaults['sequentialDelay'] ),
	);
}

/**
 * Save a new animation preset
 * 
 * @param WP_REST_Request $request The REST request object
 * @return WP_REST_Response|WP_Error Response object on success, error on failure
 */
function ollie_ui_helpers_save_preset( $request ) {
	try {
		$presets = get_option( 'ollie_animation_presets', array() );
		
		// Ensure presets is an array
		if ( ! is_array( $presets ) ) {
			$presets = array();
		}
		
		// Limit number of presets to prevent database bloat
		$max_presets = apply_filters( 'ollie_ui_helpers_max_animation_presets', 50 );
		if ( count( $presets ) >= $max_presets ) {
			return new WP_Error( 'preset_limit_reached', sprintf( __( 'Maximum number of presets (%d) reached', 'ollie-pro' ), $max_presets ), array( 'status' => 400 ) );
		}
		
		// Get JSON parameters
		$params = $request->get_json_params();
		
		// Validate and sanitize all inputs
		if ( empty( $params['name'] ) || strlen( $params['name'] ) < 1 ) {
			return new WP_Error( 'invalid_preset_name', __( 'Preset name is required', 'ollie-pro' ), array( 'status' => 400 ) );
		}
		
		$preset_name = sanitize_text_field( $params['name'] );
		if ( strlen( $preset_name ) > 50 ) {
			return new WP_Error( 'preset_name_too_long', __( 'Preset name must be less than 50 characters', 'ollie-pro' ), array( 'status' => 400 ) );
		}
		
		// Validate animation type against allowed values
		$allowed_animations = ollie_ui_helpers_get_allowed_animations();
		
		if ( ! empty( $params['animationType'] ) && ! in_array( $params['animationType'], $allowed_animations, true ) ) {
			return new WP_Error( 'invalid_animation_type', __( 'Invalid animation type', 'ollie-pro' ), array( 'status' => 400 ) );
		}
		
		// Validate numeric ranges
		if ( isset( $params['animationDuration'] ) && ( $params['animationDuration'] < 0.1 || $params['animationDuration'] > 10 ) ) {
			return new WP_Error( 'invalid_duration', __( 'Duration must be between 0.1 and 10 seconds', 'ollie-pro' ), array( 'status' => 400 ) );
		}
		
		if ( isset( $params['animationDelay'] ) && ( $params['animationDelay'] < 0 || $params['animationDelay'] > 10 ) ) {
			return new WP_Error( 'invalid_delay', __( 'Delay must be between 0 and 10 seconds', 'ollie-pro' ), array( 'status' => 400 ) );
		}
		
		if ( isset( $params['scrollOffset'] ) && ( $params['scrollOffset'] < -500 || $params['scrollOffset'] > 500 ) ) {
			return new WP_Error( 'invalid_offset', __( 'Scroll offset must be between -500 and 500', 'ollie-pro' ), array( 'status' => 400 ) );
		}
		
		// Generate slug-based ID from preset name
		$base_id = sanitize_title( $preset_name );
		$preset_id = $base_id;
		
		// Check for existing presets with the same ID and increment if needed
		$counter = 2;
		$existing_ids = array_column( $presets, 'id' );
		while ( in_array( $preset_id, $existing_ids, true ) ) {
			$preset_id = $base_id . '-' . $counter;
			$counter++;
		}
		
		// Build settings using helper function
		// The JavaScript sends settings nested in a 'settings' object
		$settings_params = isset( $params['settings'] ) ? $params['settings'] : $params;
		$settings = ollie_ui_helpers_build_preset_settings( $settings_params );
		
		// Create new preset with both nested and flat structure for backward compatibility
		$new_preset = array_merge(
			array(
				'id'       => $preset_id,
				'name'     => $preset_name,
				'settings' => $settings, // Nested structure for CSS generation
			),
			$settings // Flat structure for backward compatibility
		);
		
		// Add to presets array
		$presets[] = $new_preset;
		
		// Save to database
		update_option( 'ollie_animation_presets', $presets );
		
		// Clear CSS cache to regenerate with new preset
		ollie_ui_helpers_clear_preset_css_cache();
		
		// Return just the new preset for consistency with frontend expectations
		return rest_ensure_response( $new_preset );
	} catch ( Exception $e ) {
		return new WP_Error( 'preset_save_failed', $e->getMessage(), array( 'status' => 500 ) );
	}
}

/**
 * Update an existing animation preset
 * 
 * @param WP_REST_Request $request The REST request object
 * @return WP_REST_Response|WP_Error Response object on success, error on failure
 */
function ollie_ui_helpers_update_preset( $request ) {
	try {
		$preset_id = $request->get_param( 'id' );
		$presets = get_option( 'ollie_animation_presets', array() );
		
		// Get JSON parameters
		$params = $request->get_json_params();
		
		// Find and update the preset
		$updated = false;
		$updated_preset = null;
		foreach ( $presets as &$preset ) {
			if ( $preset['id'] === $preset_id ) {
				// Get existing settings as defaults
				$defaults = isset( $preset['settings'] ) ? $preset['settings'] : $preset;
				
				// Build updated settings using helper function
				// The JavaScript sends settings nested in a 'settings' object
				$settings_params = isset( $params['settings'] ) ? $params['settings'] : $params;
				$settings = ollie_ui_helpers_build_preset_settings( $settings_params, $defaults );
				
				// Update both nested and flat structure for backward compatibility
				$preset['settings'] = $settings;
				foreach ( $settings as $key => $value ) {
					$preset[ $key ] = $value;
				}
				
				$updated_preset = $preset;
				$updated = true;
				break;
			}
		}
		
		if ( ! $updated ) {
			return new WP_Error( 'preset_not_found', __( 'Preset not found', 'ollie-pro' ), array( 'status' => 404 ) );
		}
		
		// Save to database
		update_option( 'ollie_animation_presets', $presets );
		
		// Clear CSS cache to regenerate with updated preset
		ollie_ui_helpers_clear_preset_css_cache();
		
		// Return just the updated preset for consistency with frontend expectations
		return rest_ensure_response( $updated_preset );
	} catch ( Exception $e ) {
		return new WP_Error( 'preset_update_failed', $e->getMessage(), array( 'status' => 500 ) );
	}
}

/**
 * Delete an animation preset
 * 
 * @param WP_REST_Request $request The REST request object
 * @return WP_REST_Response Response object with updated presets list
 */
function ollie_ui_helpers_delete_preset( $request ) {
	$preset_id = $request->get_param( 'id' );
	$presets = get_option( 'ollie_animation_presets', array() );
	
	// Filter out the preset to delete and re-index in one operation
	$presets = array_values( array_filter( $presets, function( $preset ) use ( $preset_id ) {
		return $preset['id'] !== $preset_id;
	} ) );
	
	// Save to database
	update_option( 'ollie_animation_presets', $presets );
	
	// Clear CSS cache since presets have changed
	ollie_ui_helpers_clear_preset_css_cache();
	
	return rest_ensure_response( array(
		'success' => true,
		'presets' => $presets,
	) );
}

/**
 * Localize preset data for the editor
 */
function ollie_ui_helpers_localize_preset_data() {
	$presets = get_option( 'ollie_animation_presets', array() );
	
	// Migrate old IDs if needed
	$presets = ollie_ui_helpers_migrate_preset_ids( $presets );
	
	wp_localize_script(
		'ollie-extensions-editor',
		'ollieAnimationPresets',
		array(
			'apiUrl'   => rest_url( 'ollie-extensions/v1' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'presets'  => $presets,
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'ollie_ui_helpers_localize_preset_data', 11 ); // Priority 11 to run after script is enqueued
