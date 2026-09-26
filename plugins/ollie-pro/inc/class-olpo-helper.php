<?php

namespace olpo;

class Helper {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Helper.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setting up admin fields
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'wp_theme_json_data_theme', [ __CLASS__, 'filter_theme_json_data' ] );
	}

	/**
	 * Create child theme for Ollie.
	 *
	 * @return bool
	 */
	public static function create_child_theme( $data ) {
		// Prepare directories
		$child_theme_file_dir = OLPO_PATH . '/inc/child-theme';
		$ollie_dir            = get_template_directory();
		$themes_dir           = dirname( $ollie_dir );

		// Create a sanitized slug from the theme name for the directory
		$theme_slug      = ! empty( $data['textDomain'] ) ? sanitize_title( $data['textDomain'] ) : sanitize_title( $data['themeName'] );
		$ollie_child_dir = $themes_dir . '/' . $theme_slug;

		// Create directory
		if ( ! file_exists( $ollie_child_dir ) ) {
			wp_mkdir_p( $ollie_child_dir );
		}

		// Get the template style.css content
		$template_style = file_get_contents( $child_theme_file_dir . '/style.css' );
		if ( $template_style === false ) {
			return false;
		}

		// Replace the header information
		$header_replacements = array(
			'/Theme Name:.*/'  => 'Theme Name: ' . $data['themeName'],
			'/Theme URI:.*/'   => 'Theme URI: ' . ( ! empty( $data['themeUrl'] ) ? $data['themeUrl'] : 'https://olliewp.com' ),
			'/Description:.*/' => 'Description: ' . ( ! empty( $data['description'] ) ? $data['description'] : 'A child theme for Ollie.' ),
			'/Author:.*/'      => 'Author: ' . ( ! empty( $data['author'] ) ? $data['author'] : 'Mike McAlister' ),
			'/Author URI:.*/'  => 'Author URI: ' . ( ! empty( $data['authorUrl'] ) ? $data['authorUrl'] : 'https://olliewp.com' ),
			'/Version:.*/'     => 'Version: ' . ( ! empty( $data['version'] ) ? $data['version'] : '1.0.0' ),
			'/Text Domain:.*/' => 'Text Domain: ' . ( ! empty( $data['textDomain'] ) ? $data['textDomain'] : 'ollie-child' )
		);

		foreach ( $header_replacements as $pattern => $replacement ) {
			$template_style = preg_replace( $pattern, $replacement, $template_style );
		}

		// Write the modified style.css
		if ( file_put_contents( $ollie_child_dir . '/style.css', $template_style ) === false ) {
			return false;
		}

		// Copy other required files
		if ( ! copy( $child_theme_file_dir . '/screenshot.png', $ollie_child_dir . '/screenshot.png' ) ) {
			return false;
		}

		if ( ! copy( $child_theme_file_dir . '/functions.php', $ollie_child_dir . '/functions.php' ) ) {
			return false;
		}

		// Handle disabled styles - create blank style JSON files to override parent theme styles
		if ( ! empty( $data['disabledStyles'] ) && is_array( $data['disabledStyles'] ) ) {
			$styles_dir = $ollie_child_dir . '/styles';

			// Create styles directory if needed
			if ( ! file_exists( $styles_dir ) ) {
				wp_mkdir_p( $styles_dir );
			}

			// Style variation titles (must match parent theme exactly)
			$style_titles = array(
				'agency'  => 'Agency',
				'creator' => 'Creator',
				'studio'  => 'Studio',
				'startup' => 'Startup',
			);

			foreach ( $data['disabledStyles'] as $style_key => $is_disabled ) {
				// Handle colors separately
				if ( $style_key === 'colors' && $is_disabled ) {
					$colors_dir = $styles_dir . '/colors';

					// Create colors directory if needed
					if ( ! file_exists( $colors_dir ) ) {
						wp_mkdir_p( $colors_dir );
					}

					// Color variation titles
					$color_titles = array(
						'blue'   => 'Blue',
						'green'  => 'Green',
						'neon'   => 'Neon',
						'orange' => 'Orange',
						'pink'   => 'Pink',
						'red'    => 'Red',
						'teal'   => 'Teal',
					);

					foreach ( $color_titles as $color_key => $color_title ) {
						$color_content = array(
							'$schema'  => 'https://schemas.wp.org/trunk/theme.json',
							'version'  => 3,
							'title'    => $color_title,
							'settings' => new \stdClass(),
						);

						$json_content = wp_json_encode( $color_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
						$color_file   = $colors_dir . '/' . $color_key . '.json';

						file_put_contents( $color_file, $json_content );
					}
				} elseif ( $style_key === 'typography' && $is_disabled ) {
					// Handle typography separately
					$typography_dir = $styles_dir . '/typography';

					// Create typography directory if needed
					if ( ! file_exists( $typography_dir ) ) {
						wp_mkdir_p( $typography_dir );
					}

					// Typography preset titles (1-9)
					for ( $i = 1; $i <= 9; $i++ ) {
						$typography_content = array(
							'$schema'  => 'https://schemas.wp.org/trunk/theme.json',
							'version'  => 3,
							'title'    => 'Typography Preset ' . $i,
							'settings' => new \stdClass(),
						);

						$json_content    = wp_json_encode( $typography_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
						$typography_file = $typography_dir . '/typography-preset-' . $i . '.json';

						file_put_contents( $typography_file, $json_content );
					}
				} elseif ( $is_disabled && isset( $style_titles[ $style_key ] ) ) {
					// Create blank style JSON file to override parent theme style
					$style_content = array(
						'$schema'  => 'https://schemas.wp.org/trunk/theme.json',
						'version'  => 3,
						'title'    => $style_titles[ $style_key ],
						'settings' => new \stdClass(),
					);

					$json_content = wp_json_encode( $style_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
					$style_file   = $styles_dir . '/' . $style_key . '.json';

					file_put_contents( $style_file, $json_content );
				}
			}
		}

		// Handle disable patterns - append function to functions.php
		if ( ! empty( $data['disablePatterns'] ) && $data['disablePatterns'] === true ) {
			$functions_file = $ollie_child_dir . '/functions.php';

			if ( file_exists( $functions_file ) ) {
				$pattern_code = <<<'PHP'


/**
 * Unregister all Ollie block patterns.
 *
 * @return void
 */
add_action( 'init', 'ollie_child_unregister_patterns', 999 );

function ollie_child_unregister_patterns() {
	$patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();

	foreach ( $patterns as $pattern ) {
		if ( isset( $pattern['name'] ) && str_starts_with( $pattern['name'], 'ollie/' ) ) {
			unregister_block_pattern( $pattern['name'] );
		}
	}
}
PHP;

				file_put_contents( $functions_file, $pattern_code, FILE_APPEND );
			}
		}

		// Activate child theme
		switch_theme( $theme_slug );

		return true;
	}

	/**
	 * Create pages in WordPress.
	 *
	 * @param array $pages given list of pages.
	 *
	 * @return array
	 */
	public static function create_pages( $pages ) {
		$create_page_ids = [];

		foreach ( $pages as $page_slug ) {
			// Check if page exists.
			if ( ! get_page_by_path( $page_slug, OBJECT, [ 'page' ] ) ) {
				$proPrefix = [ 'agency', 'creator', 'startup', 'studio', 'ecommerce' ];
				$content   = '<!-- wp:pattern {"slug":"ollie/page-' . sanitize_title( $page_slug ) . '"} /-->';

				// Check if page_slug contains any of the pro prefixes.
				foreach ( $proPrefix as $prefix ) {
					if ( strpos( $page_slug, $prefix ) !== false ) {
						// Prepare the page slug.
						$page_slug = str_replace( '/', '-', $page_slug );

						// Get content from the pattern file if it exists
						$pattern_path = get_theme_file_path( 'patterns/' . $page_slug . '.php' );

						if ( file_exists( $pattern_path ) ) {
							$content = file_get_contents( $pattern_path );
							$content = preg_replace( '/<\?php.*?\?>/s', '', $content );
						}

						break;
					}
				}

				// Rework the title.
				// Check if slug has a number prefix pattern (e.g., "ecommerce-01-homepage")
				$number_suffix = '';
				if ( preg_match( '/^[a-z]+-(\d+)-/', $page_slug, $matches ) ) {
					$number_suffix = ' ' . ltrim( $matches[1], '0' ); // Remove leading zeros (01 -> 1)
					if ( $number_suffix === ' ' ) {
						$number_suffix = ' 0'; // Handle case where number is just "00"
					}
				}
				$title = preg_replace( '/^[a-z]+-\d+-/', '', $page_slug ); // Remove prefix and number
				$title = str_replace( '-', ' ', $title ); // Replace remaining dashes with spaces
				$title = ucwords( $title ); // Capitalize words
				$title = $title . $number_suffix; // Append number if present (e.g., "Homepage 1")

				// Create page.
				$page_id = wp_insert_post(
					array(
						'post_author'  => 1,
						'post_title'   => $title,
						'post_name'    => sanitize_title( $page_slug ),
						'post_status'  => 'publish',
						'post_content' => $content,
						'post_type'    => 'page',
					)
				);

				$create_page_ids[ $page_slug ] = $page_id;

				// Update the page template.
				update_post_meta( $page_id, '_wp_page_template', 'page-no-title' );

				// Note: We no longer delete pattern files here because they may be
				// reused by templates (e.g., a pattern used for both a page and a template)
			}
		}

		return $create_page_ids;
	}

	/**
	 * Install pattern from cloud into Ollie theme.
	 *
	 * @param $pattern
	 *
	 * @return bool
	 */
	public static function download_pattern( $pattern, $isDynamic = false ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return false;
		}

		// Verify pattern data
		if ( ! is_array( $pattern ) || empty( $pattern['slug'] ) ) {
			return false;
		}

		// Get pattern dir using WP Filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		$pattern_dir = wp_normalize_path( get_stylesheet_directory() . '/patterns/' );

		if ( ! $wp_filesystem->exists( $pattern_dir ) ) {
			wp_mkdir_p( $pattern_dir );
		}

		$sample_file  = wp_normalize_path( OLPO_PATH . '/inc/templates/pattern.php' );
		$pattern_slug = sanitize_file_name( str_replace( '/', '-', $pattern['slug'] ) );
		$pattern_file = $pattern_dir . $pattern_slug . '.php';

		// Create pattern in theme
		if ( ! $wp_filesystem->exists( $pattern_file ) ) {
			// Copy and rename sample file
			if ( ! $wp_filesystem->copy( $sample_file, $pattern_file, true ) ) {
				self::debug_log( 'Failed to download pattern: ' . $pattern_slug );

				return false;
			}

			// Prepare categories with sanitization
			$categories        = array_map( 'sanitize_text_field', array_unique( $pattern['categories'] ?? [] ) );
			$categories_string = '';
			foreach ( $categories as $category ) {
				$categories_string .= 'ollie/' . $category . ', ';
			}
			$categories_string = rtrim( $categories_string, ', ' );

			// Prepare placeholders with proper sanitization
			$placeholders = array(
				'[PATTERN_TITLE]'         => wp_kses_post( $pattern['title'] ?? '' ),
				'[PATTERN_SLUG]'          => $pattern_slug,
				'[PATTERN_CATEGORIES]'    => $categories_string,
				'[PATTERN_DESCRIPTION]'   => wp_kses_post( $pattern['description'] ?? '' ),
				'[PATTERN_KEYWORDS]'      => sanitize_text_field( $pattern['keywords'] ?? '' ),
				'[PATTERN_WIDTH]'         => sanitize_text_field( $pattern['width'] ?? '' ),
				'[PATTERN_CONTENT]'       => wp_kses( $pattern['content'], self::get_kses_extended_ruleset() ) ?? '',
				'[PATTERN_TEMPLATE_PART]' => ! empty( $pattern['template_part'] ) ?
					'core/template-part/' . sanitize_text_field( $pattern['template_part'] ) : ''
			);

			// Overwrite pattern content with dynamic_content (with fallback to content).
			if ( $isDynamic ) {
				$dynamic_content = ! empty( $pattern['dynamic_content'] ) ? $pattern['dynamic_content'] : $pattern['content'];
				$placeholders['[PATTERN_CONTENT]'] = wp_kses( $dynamic_content, self::get_kses_extended_ruleset() ) ?? '';
			}

			// Get and validate pattern content
			$pattern_content = $wp_filesystem->get_contents( $pattern_file );
			if ( $pattern_content === false ) {
				return false;
			}

			// Replace placeholders
			foreach ( $placeholders as $placeholder => $string ) {
				$pattern_content = str_replace( $placeholder, $string, $pattern_content );
			}

			// Write updated content
			if ( ! $wp_filesystem->put_contents( $pattern_file, $pattern_content, FS_CHMOD_FILE ) ) {
				return false;
			}

			// Register the pattern immediately so it's available for use
			self::register_pattern_from_file( $pattern_file );

			return true;
		}

		// File already exists - register it and return success
		if ( file_exists( $pattern_file ) && filesize( $pattern_file ) > 100 ) {
			self::register_pattern_from_file( $pattern_file );
			return true;
		}

		return false;
	}

	/**
	 * Register a pattern from a file path
	 *
	 * @param string $pattern_file Full path to the pattern file
	 *
	 * @return bool
	 */
	public static function register_pattern_from_file( $pattern_file ) {
		// Capability check to ensure only authorized users can register patterns from files.
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return false;
		}

		// Basic input validation.
		if ( empty( $pattern_file ) || ! is_string( $pattern_file ) ) {
			return false;
		}

		// Normalize the provided path and the allowed base directory (the theme's patterns directory).
		$normalized_file      = wp_normalize_path( $pattern_file );
		$allowed_base_dir_raw = get_stylesheet_directory() . '/patterns/';
		$allowed_base_dir     = wp_normalize_path( $allowed_base_dir_raw );

		// Resolve real paths to prevent directory traversal and symlink escape attempts.
		$real_file_path   = realpath( $normalized_file );
		$real_allowed_dir = realpath( $allowed_base_dir );

		if ( false === $real_file_path || false === $real_allowed_dir ) {
			return false;
		}

		// Ensure the target file resides within the allowed patterns directory.
		$real_file_path   = wp_normalize_path( $real_file_path );
		$real_allowed_dir = trailingslashit( wp_normalize_path( $real_allowed_dir ) );
		if ( 0 !== strpos( $real_file_path, $real_allowed_dir ) ) {
			return false;
		}

		// Only allow PHP files and ensure the file exists and is readable.
		if ( strtolower( pathinfo( $real_file_path, PATHINFO_EXTENSION ) ) !== 'php' ) {
			return false;
		}
		if ( ! is_file( $real_file_path ) || ! is_readable( $real_file_path ) ) {
			return false;
		}

		// All checks passed; use the sanitized, validated real path from here on.
		$pattern_file = $real_file_path;

		if ( ! file_exists( $pattern_file ) ) {
			return false;
		}

		// Get pattern slug from filename
		$pattern_slug = basename( $pattern_file, '.php' );

		// Load the pattern file to get header data
		$pattern_data = get_file_data(
			$pattern_file,
			array(
				'title'       => 'Title',
				'slug'        => 'Slug',
				'description' => 'Description',
				'categories'  => 'Categories',
				'keywords'    => 'Keywords',
			)
		);

		// Get the pattern content
		ob_start();
		include $pattern_file;
		$pattern_content = ob_get_clean();

		// Register the pattern if we have valid data
		if ( ! empty( $pattern_data['slug'] ) && ! empty( $pattern_content ) ) {
			$pattern_properties = array(
				'title'   => $pattern_data['title'],
				'content' => $pattern_content,
			);

			// Add optional properties
			if ( ! empty( $pattern_data['description'] ) ) {
				$pattern_properties['description'] = $pattern_data['description'];
			}

			if ( ! empty( $pattern_data['categories'] ) ) {
				$pattern_properties['categories'] = array_filter(
					array_map( 'trim', explode( ',', $pattern_data['categories'] ) )
				);
			}

			if ( ! empty( $pattern_data['keywords'] ) ) {
				$pattern_properties['keywords'] = array_filter(
					array_map( 'trim', explode( ',', $pattern_data['keywords'] ) )
				);
			}

			register_block_pattern( $pattern_data['slug'], $pattern_properties );
			return true;
		}

		return false;
	}

	/**
	 * Uninstall pattern from filesystem.
	 *
	 * @param $pattern
	 *
	 * @return false|int|\WP_Error
	 */
	public static function delete_pattern( $pattern ) {
		// Get Ollie theme dir.
		$pattern_dir  = get_stylesheet_directory() . '/patterns/';
		$pattern_slug = str_replace( '/', '-', $pattern['slug'] );
		$pattern_file = sanitize_file_name( $pattern_slug ) . '.php';

		if ( ! $pattern ) {
			return false;
		}

		// Delete the pattern file.
		if ( file_exists( $pattern_dir . $pattern_file ) ) {
			return wp_delete_file( $pattern_dir . $pattern_file );
		}

		return false;
	}

	/**
	 * Get installed pattern slugs.
	 *
	 * @return array
	 */
	public static function get_downloaded_patterns() {
		// Get Ollie theme dir.
		$pattern_dir         = get_stylesheet_directory() . '/patterns/';
		$downloaded_patterns = [];

		if ( ! file_exists( $pattern_dir ) ) {
			wp_mkdir_p( $pattern_dir );
		}

		$pattern_files = scandir( $pattern_dir );

		if ( is_array( $pattern_files ) ) {
			foreach ( $pattern_files as $pattern ) {
				if ( $pattern !== '.' && $pattern !== '..' ) {
					$downloaded_patterns[] = str_replace( '.php', '', $pattern );
				}
			}
		}

		return $downloaded_patterns;
	}

	/**
	 * Get favorite patterns.
	 *
	 * @return array
	 */
	public static function get_favorite_patterns() {
		return get_option( 'ollie_favorite_patterns', [] );
	}

	/**
	 * Log debug messages using WordPress's built-in logging
	 *
	 * @param mixed $message The message to log
	 *
	 * @return void
	 */
	private static function debug_log( $message ) {
		if ( ! current_user_can( 'manage_options' ) || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = wp_json_encode( $message );
		}
	}

	/**
	 * Updates the global styles post with new typography settings
	 *
	 * @param string $style_name The name of the typography style to apply
	 *
	 * @return array Response array with success status and message
	 */
	public static function update_global_typography( $style_name ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return [
				'success' => false,
				'message' => 'Insufficient permissions'
			];
		}

		try {
			$style_name = sanitize_text_field( $style_name );

			// Special handling for typography-preset-0
			if ( $style_name === 'typography-preset-0' ) {
				$global_styles_post = get_posts( [
					'name'           => 'wp-global-styles-' . get_stylesheet(),
					'post_type'      => 'wp_global_styles',
					'posts_per_page' => 1,
					'post_status'    => [ 'publish', 'draft' ],
				] );

				if ( ! empty( $global_styles_post ) ) {
					$post           = $global_styles_post[0];
					$current_styles = json_decode( $post->post_content, true ) ?: [];

					// Remove typography settings
					if ( isset( $current_styles['settings']['typography'] ) ) {
						unset( $current_styles['settings']['typography'] );
					}
					if ( isset( $current_styles['styles']['typography'] ) ) {
						unset( $current_styles['styles']['typography'] );
					}
					if ( isset( $current_styles['styles']['elements'] ) ) {
						foreach ( $current_styles['styles']['elements'] as &$element ) {
							if ( isset( $element['typography'] ) ) {
								unset( $element['typography'] );
							}
						}
					}

					// Update the post content
					wp_update_post( [
						'ID'           => $post->ID,
						'post_content' => wp_json_encode( $current_styles )
					] );

					// Clear caches
					wp_cache_delete( 'global_styles_' . get_stylesheet(), 'global_styles' );
					delete_transient( 'global_styles' );
					delete_transient( 'global_styles_' . get_stylesheet() );
				}

				return [
					'success' => true,
					'message' => 'Typography reset to theme defaults'
				];
			}

			// Regular handling for other typography presets
			$variation_file = wp_normalize_path( get_template_directory() . '/styles/typography/' . $style_name . '.json' );

			// Validate file path
			if ( strpos( $variation_file, wp_normalize_path( get_template_directory() ) ) !== 0 ) {
				return [
					'success' => false,
					'message' => 'Invalid file path'
				];
			}

			if ( ! file_exists( $variation_file ) ) {
				return [
					'success' => false,
					'message' => 'Typography style file not found'
				];
			}

			// Use WP Filesystem for file operations
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			global $wp_filesystem;

			// Read and validate JSON
			$variation_content = $wp_filesystem->get_contents( $variation_file );
			if ( $variation_content === false ) {
				return [
					'success' => false,
					'message' => 'Failed to read typography file'
				];
			}

			$variation_data = json_decode( $variation_content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return [
					'success' => false,
					'message' => 'Invalid JSON in typography file'
				];
			}

			// Validate JSON structure
			if ( ! isset( $variation_data['settings'] ) || ! isset( $variation_data['styles'] ) ) {
				return [
					'success' => false,
					'message' => 'Invalid typography data structure'
				];
			}

			// Get or create global styles post
			$result = self::get_or_create_global_styles_post();
			if ( ! $result ) {
				return [
					'success' => false,
					'message' => 'Failed to get or create global styles post'
				];
			}

			$post           = $result['post'];
			$current_styles = $result['styles'];

			// Update typography settings
			if ( isset( $variation_data['settings']['typography'] ) ) {
				$current_styles['settings']['typography'] = $variation_data['settings']['typography'];
			}

			// Update typography styles
			if ( isset( $variation_data['styles'] ) ) {
				// Global typography styles
				if ( isset( $variation_data['styles']['typography'] ) ) {
					$current_styles['styles']['typography'] = $variation_data['styles']['typography'];
				}

				// Element-specific typography styles
				if ( isset( $variation_data['styles']['elements'] ) ) {
					if ( ! isset( $current_styles['styles']['elements'] ) ) {
						$current_styles['styles']['elements'] = [];
					}
					foreach ( $variation_data['styles']['elements'] as $element => $styles ) {
						$current_styles['styles']['elements'][ $element ] = $styles;
					}
				}
			}

			// Update the post content
			$result = wp_update_post( [
				'ID'           => $post->ID,
				'post_content' => wp_json_encode( $current_styles )
			] );

			if ( is_wp_error( $result ) ) {
				return [
					'success' => false,
					'message' => 'Failed to update global styles post'
				];
			}

			// Clear caches
			wp_cache_delete( 'global_styles_' . get_stylesheet(), 'global_styles' );
			delete_transient( 'global_styles' );
			delete_transient( 'global_styles_' . get_stylesheet() );

			return [
				'success' => true,
				'message' => 'Typography updated successfully'
			];

		} catch ( Exception $e ) {
			self::debug_log( 'Typography update error: ' . $e->getMessage() );

			return [
				'success' => false,
				'message' => 'Internal server error'
			];
		}
	}

	/**
	 * Modifies the theme JSON data by updating theme styles and settings.
	 * Now only handles color settings as typography is handled via global styles post.
	 */
	public static function filter_theme_json_data( $theme_json ) {
		$settings = get_option( 'ollie', [] );
		$data     = $theme_json->get_data();

		// Only filter if brand_color or color_palette settings exist
		if ( isset( $settings['brand_color'] ) && ! empty( $settings['brand_color'] ) ||
		     isset( $settings['style'] ) && ! empty( $settings['style'] ) ) {
			$data['settings']['color']['palette'] = self::handle_color_settings( $data, $settings );

			return $theme_json->update_with( $data );
		}

		return $theme_json;
	}

	/**
	 * Removes color values from global styles
	 *
	 * @return array Response array with success status and message
	 */
	public static function remove_color_values( $force = false ) {
		// Only remove color values during first-time setup unless forced.
		// When a user explicitly switches to a preset palette, force is true
		// so custom colors are cleared in favor of the preset theme.json colors.
		if ( ! $force ) {
			$ollie_settings = (array) get_option( 'ollie', [] );
			if ( ! empty( $ollie_settings['color_palette'] ) || ! empty( $ollie_settings['brand_color'] ) ) {
				return [
					'success' => true,
					'message' => 'Skipped — color settings already configured'
				];
			}
		}

		// Get the global styles post
		$result = self::get_or_create_global_styles_post();
		if ( ! $result ) {
			return [
				'success' => false,
				'message' => 'Failed to get or create global styles post'
			];
		}

		$post           = $result['post'];
		$current_styles = $result['styles'];

		// Remove color settings from the global styles
		if ( isset( $current_styles['settings']['color'] ) ) {
			unset( $current_styles['settings']['color'] );
		}

		// Update the post content
		wp_update_post( [
			'ID'           => $post->ID,
			'post_content' => wp_json_encode( $current_styles )
		] );

		// Clean all theme.json related caches
		wp_clean_theme_json_cache();

		return [
			'success' => true,
			'message' => 'Color values removed successfully'
		];
	}

	/**
	 * Handles color palette settings for theme.json
	 *
	 * @param array $data Current theme.json data
	 * @param array $settings Ollie settings
	 *
	 * @return array Updated color palette
	 */
	private static function handle_color_settings( $data, $settings ) {
		// If brand color and custom palette are set, use the custom palette
		if ( isset( $settings['brand_color'] ) && ! empty( $settings['brand_color'] ) &&
		     isset( $settings['color_palette'] ) && ! empty( $settings['color_palette'] ) ) {
			return $settings['color_palette'];
		}

		// Otherwise use the selected style's palette
		$style      = isset( $settings['style'] ) ? $settings['style'] : 'purple';
		$style_file = get_template_directory() . '/styles/colors/' . $style . '.json';

		// If style is 'purple', use the main theme.json
		if ( $style === 'purple' ) {
			$style_file = get_template_directory() . '/theme.json';
		}

		if ( file_exists( $style_file ) ) {
			$style_json = json_decode( file_get_contents( $style_file ), true );

			return $style_json['settings']['color']['palette'];
		}

		return [];
	}

	/**
	 * Gets or creates the global styles post
	 *
	 * @return array|false Array with post and styles data, or false on failure
	 */
	private static function get_or_create_global_styles_post() {
		// Get the latest global styles post
		$global_styles_post = get_posts( [
			'post_type'      => 'wp_global_styles',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'post_status'    => [ 'publish', 'draft' ],
		] );

		// If no global styles post exists, create one
		if ( empty( $global_styles_post ) ) {
			$post_content = array(
				'version'                     => 2,
				'isGlobalStylesUserThemeJSON' => true
			);

			$post_id = wp_insert_post( array(
				'post_content' => wp_json_encode( $post_content ),
				'post_status'  => 'publish',
				'post_title'   => 'Custom Styles',
				'post_type'    => 'wp_global_styles',
				'post_name'    => sprintf( 'wp-global-styles-%s', urlencode( get_stylesheet() ) ),
				'tax_input'    => array(
					'wp_theme' => array( get_stylesheet() )
				)
			), true );

			if ( is_wp_error( $post_id ) ) {
				return false;
			}

			$post = get_post( $post_id );
		} else {
			$post = $global_styles_post[0];
		}

		// Get current styles content
		$current_styles = json_decode( $post->post_content, true ) ?: [];

		return [
			'post'   => $post,
			'styles' => $current_styles
		];
	}

	public static function get_kses_extended_ruleset() {
		$kses_defaults = wp_kses_allowed_html( 'post' );

		$svg_args = array(
			'svg'   => array(
				'class'           => true,
				'aria-hidden'     => true,
				'aria-labelledby' => true,
				'role'            => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'focusable'       => true,
				'fill'            => true,
				'viewbox'         => true,
			),
			'g'     => array( 'fill' => true ),
			'title' => array( 'title' => true ),
			'path'  => array(
				'd'         => true,
				'fill'      => true,
				'fill-rule' => true,
				'clip-rule' => true,
			),
		);

		return array_merge( $kses_defaults, $svg_args );
	}

	/**
	 * Resets all template and template part customizations.
	 *
	 * @return array Response array with success status and message
	 */
	public static function reset_templates() {
		// Check if user has permission
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to reset templates.', 'ollie-pro' ) );
		}

		global $wpdb;

		try {
			// Delete only template and template part posts
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->posts} 
					WHERE post_type IN (%s, %s)",
					'wp_template',
					'wp_template_part'
				)
			);

			return array(
				'success' => true,
				'message' => __( 'Templates reset successfully.', 'ollie-pro' )
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'reset_failed', __( 'Failed to reset templates.', 'ollie-pro' ) );
		}
	}

	public static function reset_global_styles() {
		// Check if user has permission
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to reset global styles.', 'ollie-pro' ) );
		}

		global $wpdb;

		try {
			// Delete only global styles posts
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->posts} 
					WHERE post_type = %s",
					'wp_global_styles'
				)
			);

			return array(
				'success' => true,
				'message' => __( 'Global styles reset successfully.', 'ollie-pro' )
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'reset_failed', __( 'Failed to reset global styles.', 'ollie-pro' ) );
		}
	}


	/**
	 * Maybe get credentials for auto-login.
	 * @return array
	 */
	public static function get_credentials() {
		// Check if credentials are defined via config.
		$email    = defined( 'OLLIE_EMAIL' ) ? OLLIE_EMAIL : '';
		$password = defined( 'OLLIE_PASSWORD' ) ? OLLIE_PASSWORD : '';

		// Check if credentials are stored in DB.
		$options = get_option( 'ollie' );

		if ( isset( $options['login'] ) && $options['login'] ) {
			$credentials = explode( ':', base64_decode( $options['login'] ) );
			$email       = $credentials[0];
			$password    = $credentials[1];
		}

		return [
			'email'    => $email,
			'password' => $password,
		];
	}
}

