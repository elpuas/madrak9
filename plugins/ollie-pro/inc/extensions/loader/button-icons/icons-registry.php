<?php
/**
 * Button Icons Registry
 *
 * Single source of truth for all button icons and their variants.
 *
 * @package OllieUIHelpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon Registry class
 *
 * @since 0.1.0
 */
class Ollie_Button_Icons_Registry {

	/**
	 * Cached icons data
	 *
	 * @since 0.1.0
	 * @var array|null
	 */
	private static $icons_cache = null;

	/**
	 * Get all available icons with their variants
	 *
	 * @since 0.1.0
	 * @return array Icons configuration
	 */
	public static function get_icons() {
		// Return cached data if available
		if ( null !== self::$icons_cache ) {
			return self::$icons_cache;
		}

		// Read icons from JSON file
		$json_file = __DIR__ . '/icons.json';

		if ( ! file_exists( $json_file ) ) {
			return array();
		}

		$json_content = file_get_contents( $json_file );
		$icons_data = json_decode( $json_content, true );

		if ( ! is_array( $icons_data ) ) {
			return array();
		}

		// Cache the icons data
		self::$icons_cache = $icons_data;

		return $icons_data;
	}
	
	/**
	 * Get SVG for a specific icon
	 *
	 * @since 0.1.0
	 * @param string $icon Icon name
	 * @param string $weight Icon weight (regular, bold, fill)
	 * @return string SVG HTML
	 */
	public static function get_svg( $icon, $weight = 'regular' ) {
		$icons = self::get_icons();
		
		if ( ! isset( $icons[ $icon ] ) ) {
			return '';
		}
		
		$icon_data = $icons[ $icon ];
		
		// Get the path for the specified weight, fallback to regular
		$path = '';
		if ( isset( $icon_data['paths'][ $weight ] ) ) {
			$path = $icon_data['paths'][ $weight ];
		} elseif ( isset( $icon_data['paths']['regular'] ) ) {
			$path = $icon_data['paths']['regular'];
		} elseif ( isset( $icon_data['paths'] ) && is_array( $icon_data['paths'] ) ) {
			// Get first available path
			$path = reset( $icon_data['paths'] );
		}
		
		if ( empty( $path ) ) {
			return '';
		}
		
		return sprintf(
			'<svg class="wp-block-button__link-icon" viewBox="%s" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="%s"/></svg>',
			esc_attr( $icon_data['viewBox'] ),
			esc_attr( $path )
		);
	}
	
	/**
	 * Export icons data as JSON for JavaScript
	 *
	 * @since 0.1.0
	 * @return string JSON encoded icons data
	 */
	public static function get_icons_json() {
		return wp_json_encode( self::get_icons() );
	}
}
