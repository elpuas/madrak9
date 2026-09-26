<?php
/**
 * Ability: ollie/manage-global-styles
 *
 * Unified ability for reading and writing all global style settings:
 * color palette, typography, spacing scale, layout, raw theme.json data,
 * and full font management (list, install, remove, collections).
 *
 * Actions: get, update, read-raw, update-raw, list-fonts, install-font, remove-font, list-font-collections.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manage_Global_Styles {

	/**
	 * Register the ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'ollie/manage-global-styles',
			array(
				'label'               => __( 'Manage Global Styles', 'ollie-pro' ),
				'description'         => __( 'Read and update the site\'s global design tokens and fonts. Actions: "get" (returns current colors, typography, spacing, and layout settings — use "sections" param to limit response), "update" (modify color palette hex values, heading/body font families, font sizes, spacing scale, and layout widths), "read-raw" (returns the raw theme.json overrides object), "update-raw" (deep-merge arbitrary theme.json overrides), "list-fonts" (returns all installed font families with faces), "install-font" (install from URL, file upload, or Google Fonts by slug), "remove-font" (delete a font family by slug), "list-font-collections" (browse available font collections like Google Fonts). Use "get" first to see current values before updating.', 'ollie-pro' ),
				'category'            => 'ollie-design',
				'input_schema'        => self::get_input_schema(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'status'      => array( 'type' => 'string' ),
						'colors'      => array( 'type' => 'object' ),
						'typography'  => array( 'type' => 'object' ),
						'spacing'     => array( 'type' => 'object' ),
						'layout'      => array( 'type' => 'object' ),
						'section'     => array( 'type' => 'string' ),
						'data'        => array( 'type' => 'object' ),
						'merged'      => array( 'type' => 'array' ),
						'palette'     => array( 'type' => 'array' ),
						'fonts'       => array( 'type' => 'array' ),
						'collections' => array( 'type' => 'array' ),
						'font_family' => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( self::class, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);
	}

	/**
	 * Build the input schema.
	 */
	private static function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'action' ),
			'properties'           => array(
				'action'   => array(
					'type'        => 'string',
					'enum'        => array( 'get', 'update', 'read-raw', 'update-raw', 'list-fonts', 'install-font', 'remove-font', 'list-font-collections' ),
					'description' => __( 'The action to perform.', 'ollie-pro' ),
				),
				'sections' => array(
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => array( 'colors', 'typography', 'spacing', 'layout' ),
					),
					'description' => __( '(get) Limit response to specific sections.', 'ollie-pro' ),
				),
				'colors'   => array(
					'type'                 => 'object',
					'description'          => __( '(update) Map of color slug to hex value.', 'ollie-pro' ),
					'additionalProperties' => array(
						'type'    => 'string',
						'pattern' => '^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$',
					),
				),
				'heading_font_family' => array(
					'type'        => 'string',
					'description' => __( '(update) Font family slug for headings.', 'ollie-pro' ),
				),
				'body_font_family' => array(
					'type'        => 'string',
					'description' => __( '(update) Font family slug for body text.', 'ollie-pro' ),
				),
				'font_sizes' => array(
					'type'        => 'array',
					'description' => __( '(update) Complete font size scale.', 'ollie-pro' ),
					'items'       => array(
						'type'       => 'object',
						'required'   => array( 'slug', 'name', 'size' ),
						'properties' => array(
							'slug' => array( 'type' => 'string' ),
							'name' => array( 'type' => 'string' ),
							'size' => array( 'type' => 'string' ),
						),
					),
				),
				'spacing_sizes' => array(
					'type'        => 'array',
					'description' => __( '(update) Complete spacing scale.', 'ollie-pro' ),
					'items'       => array(
						'type'       => 'object',
						'required'   => array( 'slug', 'name', 'size' ),
						'properties' => array(
							'slug' => array( 'type' => 'string' ),
							'name' => array( 'type' => 'string' ),
							'size' => array( 'type' => 'string' ),
						),
					),
				),
				'section'  => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'settings', 'styles', 'settings.color', 'settings.typography', 'settings.spacing', 'settings.layout', 'styles.color', 'styles.typography', 'styles.spacing', 'styles.blocks' ),
					'description' => __( '(read-raw) Which section to return.', 'ollie-pro' ),
					'default'     => 'all',
				),
				'data'     => array(
					'type'        => 'object',
					'description' => __( '(update-raw) Partial theme.json to deep-merge.', 'ollie-pro' ),
				),
				'font_slug' => array(
					'type'        => 'string',
					'description' => __( '(install-font, remove-font) The font family slug.', 'ollie-pro' ),
				),
				'font_name' => array(
					'type'        => 'string',
					'description' => __( '(install-font) Human-readable font name, e.g. "Inter".', 'ollie-pro' ),
				),
				'font_family_css' => array(
					'type'        => 'string',
					'description' => __( '(install-font) CSS font-family value, e.g. "Inter, sans-serif".', 'ollie-pro' ),
				),
				'font_faces' => array(
					'type'        => 'array',
					'description' => __( '(install-font) Array of font face definitions with fontWeight, fontStyle, and src (URL to .woff2/.ttf/.otf file).', 'ollie-pro' ),
					'items'       => array(
						'type'       => 'object',
						'required'   => array( 'fontWeight', 'src' ),
						'properties' => array(
							'fontWeight' => array( 'type' => 'string', 'description' => __( 'e.g. "400", "700", "100 900".', 'ollie-pro' ) ),
							'fontStyle'  => array( 'type' => 'string', 'description' => __( 'e.g. "normal", "italic". Defaults to "normal".', 'ollie-pro' ) ),
							'src'        => array( 'type' => 'string', 'description' => __( 'URL to the font file (.woff2, .ttf, .otf).', 'ollie-pro' ) ),
						),
					),
				),
				'google_font' => array(
					'type'        => 'string',
					'description' => __( '(install-font) Google Font family name. If provided, font faces are auto-fetched from the Google Fonts collection.', 'ollie-pro' ),
				),
				'font_variations' => array(
					'type'        => 'array',
					'description' => __( '(install-font) When using google_font, specify which variations to install. Each item: {"weight":"400","style":"normal"}. If omitted, all available variations are installed.', 'ollie-pro' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'weight' => array( 'type' => 'string' ),
							'style'  => array( 'type' => 'string' ),
						),
					),
				),
				'collection_slug' => array(
					'type'        => 'string',
					'description' => __( '(list-font-collections) Slug of a specific collection to retrieve. If omitted, all collections are listed.', 'ollie-pro' ),
				),
				'collection_search' => array(
					'type'        => 'string',
					'description' => __( '(list-font-collections) Search term to filter fonts within a collection.', 'ollie-pro' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Execute callback.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$action = $input['action'] ?? '';

		switch ( $action ) {
			case 'get':
				return self::handle_get( $input );
			case 'update':
				return self::handle_update( $input );
			case 'read-raw':
				return self::handle_read_raw( $input );
			case 'update-raw':
				return self::handle_update_raw( $input );
			case 'list-fonts':
				return self::handle_list_fonts( $input );
			case 'install-font':
				return self::handle_install_font( $input );
			case 'remove-font':
				return self::handle_remove_font( $input );
			case 'list-font-collections':
				return self::handle_list_font_collections( $input );
			default:
				return new \WP_Error( 'invalid_action', __( 'Action must be one of: get, update, read-raw, update-raw, list-fonts, install-font, remove-font, list-font-collections.', 'ollie-pro' ) );
		}
	}

	/* ─── GET ─────────────────────────────────────────────────────── */

	private static function handle_get( array $input ): array {
		$sections = $input['sections'] ?? array( 'colors', 'typography', 'spacing', 'layout' );
		$result   = array( 'status' => 'ok' );

		if ( in_array( 'colors', $sections, true ) ) {
			$palette   = wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) ?: array();
			$gradients = wp_get_global_settings( array( 'color', 'gradients', 'theme' ) ) ?: array();

			$result['colors'] = array(
				'palette'   => $palette,
				'gradients' => $gradients,
			);
		}

		if ( in_array( 'typography', $sections, true ) ) {
			$font_families = wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ) ?: array();
			$font_sizes    = wp_get_global_settings( array( 'typography', 'fontSizes', 'theme' ) ) ?: array();
			$styles        = wp_get_global_styles( array() ) ?: array();

			$result['typography'] = array(
				'font_families'       => array_map(
					function ( $f ) {
						return array(
							'slug'       => $f['slug'] ?? '',
							'name'       => $f['name'] ?? '',
							'fontFamily' => $f['fontFamily'] ?? '',
						);
					},
					$font_families
				),
				'font_sizes'          => $font_sizes,
				'heading_font_family' => $styles['elements']['heading']['typography']['fontFamily'] ?? null,
				'body_font_family'    => $styles['typography']['fontFamily'] ?? null,
			);
		}

		if ( in_array( 'spacing', $sections, true ) ) {
			$result['spacing'] = array(
				'spacing_sizes' => wp_get_global_settings( array( 'spacing', 'spacingSizes', 'theme' ) ) ?: array(),
				'units'         => wp_get_global_settings( array( 'spacing', 'units' ) ) ?: array(),
			);
		}

		if ( in_array( 'layout', $sections, true ) ) {
			$result['layout'] = array(
				'content_size' => wp_get_global_settings( array( 'layout', 'contentSize' ) ) ?: '',
				'wide_size'    => wp_get_global_settings( array( 'layout', 'wideSize' ) ) ?: '',
			);
		}

		return $result;
	}

	/* ─── UPDATE ──────────────────────────────────────────────────── */

	/**
	 * @return array|\WP_Error
	 */
	private static function handle_update( array $input ) {
		$global_styles = Global_Styles_Helper::get_post();
		if ( is_wp_error( $global_styles ) ) {
			return $global_styles;
		}

		$settings = json_decode( $global_styles->post_content, true ) ?: array();
		$updated  = array();

		// Update colors.
		if ( ! empty( $input['colors'] ) && is_array( $input['colors'] ) ) {
			$colors  = $input['colors'];
			$palette = $settings['settings']['color']['palette']['theme'] ?? array();

			if ( empty( $palette ) ) {
				$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) ?: array();
			}

			$updated_slugs = array();
			foreach ( $palette as &$entry ) {
				$slug = $entry['slug'] ?? '';
				if ( isset( $colors[ $slug ] ) ) {
					$entry['color']  = $colors[ $slug ];
					$updated_slugs[] = $slug;
				}
			}
			unset( $entry );

			$unknown = array_diff( array_keys( $colors ), $updated_slugs );
			if ( ! empty( $unknown ) ) {
				return new \WP_Error(
					'unknown_slugs',
					sprintf( __( 'Unknown color slugs: %s', 'ollie-pro' ), implode( ', ', $unknown ) )
				);
			}

			$settings['settings']['color']['palette']['theme'] = $palette;
			$updated[] = 'colors';
		}

		// Update typography.
		if ( isset( $input['heading_font_family'] ) || isset( $input['body_font_family'] ) || isset( $input['font_sizes'] ) ) {
			$registered_families = self::get_all_font_family_slugs();

			if ( isset( $input['heading_font_family'] ) ) {
				$slug = $input['heading_font_family'];
				if ( ! in_array( $slug, $registered_families, true ) ) {
					return new \WP_Error( 'invalid_font', sprintf( __( 'Font family "%s" is not registered. Use list-fonts to see available fonts or install-font to add it first.', 'ollie-pro' ), $slug ) );
				}
				$settings['styles']['elements']['heading']['typography']['fontFamily'] = 'var:preset|font-family|' . $slug;
				$updated[] = 'heading_font_family';
			}

			if ( isset( $input['body_font_family'] ) ) {
				$slug = $input['body_font_family'];
				if ( ! in_array( $slug, $registered_families, true ) ) {
					return new \WP_Error( 'invalid_font', sprintf( __( 'Font family "%s" is not registered. Use list-fonts to see available fonts or install-font to add it first.', 'ollie-pro' ), $slug ) );
				}
				$settings['styles']['typography']['fontFamily'] = 'var:preset|font-family|' . $slug;
				$updated[] = 'body_font_family';
			}

			if ( isset( $input['font_sizes'] ) && is_array( $input['font_sizes'] ) ) {
				$settings['settings']['typography']['fontSizes']['theme'] = $input['font_sizes'];
				$updated[] = 'font_sizes';
			}
		}

		// Update spacing.
		if ( isset( $input['spacing_sizes'] ) && is_array( $input['spacing_sizes'] ) ) {
			if ( empty( $input['spacing_sizes'] ) ) {
				return new \WP_Error( 'invalid_input', __( 'Provide a non-empty spacing_sizes array.', 'ollie-pro' ) );
			}
			$settings['settings']['spacing']['spacingSizes']['theme'] = $input['spacing_sizes'];
			$updated[] = 'spacing_sizes';
		}

		if ( empty( $updated ) ) {
			return new \WP_Error( 'nothing_to_update', __( 'Provide at least one style property to update.', 'ollie-pro' ) );
		}

		$result = wp_update_post(
			array(
				'ID'           => $global_styles->ID,
				'post_content' => wp_json_encode( $settings ),
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::bust_cache();

		return array(
			'status'  => 'updated',
			'updated' => $updated,
		);
	}

	/* ─── READ-RAW ────────────────────────────────────────────────── */

	/**
	 * @return array|\WP_Error
	 */
	private static function handle_read_raw( array $input ) {
		$section    = $input['section'] ?? 'all';
		$theme_json = \WP_Theme_JSON_Resolver::get_merged_data();
		$raw_data   = $theme_json->get_raw_data();

		if ( 'all' === $section ) {
			return array(
				'status'  => 'ok',
				'section' => 'all',
				'data'    => $raw_data,
			);
		}

		$parts   = explode( '.', $section );
		$current = $raw_data;

		foreach ( $parts as $key ) {
			if ( is_array( $current ) && isset( $current[ $key ] ) ) {
				$current = $current[ $key ];
			} else {
				return new \WP_Error(
					'section_not_found',
					sprintf( __( 'Section "%s" not found in theme.json data.', 'ollie-pro' ), $section )
				);
			}
		}

		return array(
			'status'  => 'ok',
			'section' => $section,
			'data'    => $current,
		);
	}

	/* ─── UPDATE-RAW ──────────────────────────────────────────────── */

	/**
	 * @return array|\WP_Error
	 */
	private static function handle_update_raw( array $input ) {
		$data = $input['data'] ?? array();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_data', __( 'Data must be a non-empty object with settings and/or styles.', 'ollie-pro' ) );
		}

		$allowed_keys = array( 'settings', 'styles' );
		$merged_keys  = array();

		foreach ( array_keys( $data ) as $key ) {
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				return new \WP_Error(
					'invalid_key',
					sprintf( __( 'Key "%s" is not allowed. Only "settings" and "styles" are accepted.', 'ollie-pro' ), $key )
				);
			}
			$merged_keys[] = $key;
		}

		$gs_post = Global_Styles_Helper::get_post();
		if ( is_wp_error( $gs_post ) ) {
			return $gs_post;
		}

		$existing = json_decode( $gs_post->post_content, true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		foreach ( $merged_keys as $key ) {
			$existing[ $key ] = self::deep_merge(
				$existing[ $key ] ?? array(),
				$data[ $key ]
			);
		}

		if ( ! isset( $existing['version'] ) ) {
			$existing['version'] = 3;
		}

		$result = wp_update_post(
			array(
				'ID'           => $gs_post->ID,
				'post_content' => wp_json_encode( $existing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::bust_cache();

		return array(
			'status' => 'updated',
			'merged' => $merged_keys,
		);
	}

	/* ─── LIST-FONTS ──────────────────────────────────────────────── */

	private static function handle_list_fonts( array $input ): array {
		$fonts = array();

		// Theme-defined fonts from theme.json.
		$theme_fonts = wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ) ?: array();
		foreach ( $theme_fonts as $font ) {
			$fonts[] = array(
				'slug'       => $font['slug'] ?? '',
				'name'       => $font['name'] ?? '',
				'fontFamily' => $font['fontFamily'] ?? '',
				'source'     => 'theme',
			);
		}

		// User-installed fonts from Font Library (wp_font_family CPT).
		$font_families = get_posts( array(
			'post_type'      => 'wp_font_family',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
		) );

		foreach ( $font_families as $ff_post ) {
			$ff_settings = json_decode( $ff_post->post_content, true );
			if ( ! is_array( $ff_settings ) ) {
				continue;
			}

			$slug = $ff_settings['slug'] ?? $ff_post->post_name;

			// Get font faces for this family.
			$face_posts = get_children( array(
				'post_parent' => $ff_post->ID,
				'post_type'   => 'wp_font_face',
				'post_status' => 'publish',
			) );

			$faces = array();
			foreach ( $face_posts as $face_post ) {
				$face_settings = json_decode( $face_post->post_content, true );
				if ( is_array( $face_settings ) ) {
					$faces[] = array(
						'id'         => $face_post->ID,
						'fontWeight' => $face_settings['fontWeight'] ?? '400',
						'fontStyle'  => $face_settings['fontStyle'] ?? 'normal',
						'src'        => $face_settings['src'] ?? '',
					);
				}
			}

			$fonts[] = array(
				'id'         => $ff_post->ID,
				'slug'       => $slug,
				'name'       => $ff_settings['name'] ?? $slug,
				'fontFamily' => $ff_settings['fontFamily'] ?? '',
				'source'     => 'user',
				'faces'      => $faces,
			);
		}

		return array(
			'status'      => 'ok',
			'total_fonts' => count( $fonts ),
			'fonts'       => $fonts,
		);
	}

	/* ─── INSTALL-FONT ────────────────────────────────────────────── */

	/**
	 * Install a font either from explicit face URLs or from Google Fonts.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_install_font( array $input ) {
		// Google Fonts shortcut.
		if ( ! empty( $input['google_font'] ) ) {
			return self::install_google_font( $input );
		}

		// Manual install with explicit font_faces.
		$font_slug       = $input['font_slug'] ?? '';
		$font_name       = $input['font_name'] ?? '';
		$font_family_css = $input['font_family_css'] ?? '';
		$font_faces_data = $input['font_faces'] ?? array();

		if ( empty( $font_slug ) || empty( $font_name ) ) {
			return new \WP_Error( 'missing_params', __( 'Provide font_slug and font_name, or use google_font for automatic installation.', 'ollie-pro' ) );
		}

		if ( empty( $font_family_css ) ) {
			$font_family_css = $font_name . ', sans-serif';
		}

		// Check if font family already exists.
		$existing = self::get_font_family_post_by_slug( $font_slug );
		if ( $existing ) {
			return new \WP_Error( 'font_exists', sprintf( __( 'Font family "%s" is already installed (ID: %d). Use remove-font first to reinstall.', 'ollie-pro' ), $font_slug, $existing->ID ) );
		}

		// Create the font family post.
		$ff_settings = array(
			'name'       => $font_name,
			'slug'       => $font_slug,
			'fontFamily' => $font_family_css,
		);

		$ff_post_id = wp_insert_post( array(
			'post_type'    => 'wp_font_family',
			'post_title'   => $font_name,
			'post_name'    => $font_slug,
			'post_status'  => 'publish',
			'post_content' => wp_json_encode( $ff_settings ),
		), true );

		if ( is_wp_error( $ff_post_id ) ) {
			return $ff_post_id;
		}

		// Install font faces.
		$installed_faces = array();
		if ( ! empty( $font_faces_data ) ) {
			$face_result = self::install_font_faces( $ff_post_id, $font_faces_data );
			if ( is_wp_error( $face_result ) ) {
				wp_delete_post( $ff_post_id, true );
				return $face_result;
			}
			$installed_faces = $face_result;
		}

		// Register the font in theme.json so it's immediately available.
		self::register_font_in_global_styles( $font_slug, $font_name, $font_family_css );

		return array(
			'status'      => 'installed',
			'font_family' => array(
				'id'         => $ff_post_id,
				'slug'       => $font_slug,
				'name'       => $font_name,
				'fontFamily' => $font_family_css,
				'faces'      => $installed_faces,
			),
		);
	}

	/**
	 * Install a Google Font by name.
	 *
	 * @return array|\WP_Error
	 */
	private static function install_google_font( array $input ) {
		$google_font_name = trim( $input['google_font'] );
		$variations       = $input['font_variations'] ?? array();
		$font_slug        = sanitize_title( $google_font_name );

		// Check if already installed.
		$existing = self::get_font_family_post_by_slug( $font_slug );
		if ( $existing ) {
			return new \WP_Error( 'font_exists', sprintf( __( 'Font "%s" is already installed (ID: %d). Use remove-font first to reinstall.', 'ollie-pro' ), $google_font_name, $existing->ID ) );
		}

		// Find the font in the Google Fonts collection.
		$google_font_data = self::find_google_font( $google_font_name );
		if ( is_wp_error( $google_font_data ) ) {
			return $google_font_data;
		}

		$font_family_css = $google_font_data['fontFamily'] ?? ( $google_font_name . ', sans-serif' );

		// Create the font family post.
		$ff_settings = array(
			'name'       => $google_font_name,
			'slug'       => $font_slug,
			'fontFamily' => $font_family_css,
		);

		$ff_post_id = wp_insert_post( array(
			'post_type'    => 'wp_font_family',
			'post_title'   => $google_font_name,
			'post_name'    => $font_slug,
			'post_status'  => 'publish',
			'post_content' => wp_json_encode( $ff_settings ),
		), true );

		if ( is_wp_error( $ff_post_id ) ) {
			return $ff_post_id;
		}

		// Build font faces from Google Fonts data.
		$google_faces = $google_font_data['fontFace'] ?? array();
		$faces_to_install = array();

		foreach ( $google_faces as $face ) {
			$weight = $face['fontWeight'] ?? '400';
			$style  = $face['fontStyle'] ?? 'normal';
			$src    = $face['src'] ?? '';

			if ( empty( $src ) ) {
				continue;
			}

			// Filter by requested variations if specified.
			if ( ! empty( $variations ) ) {
				$matched = false;
				foreach ( $variations as $var ) {
					$var_weight = $var['weight'] ?? '';
					$var_style  = $var['style'] ?? 'normal';
					if ( $var_weight === $weight && $var_style === $style ) {
						$matched = true;
						break;
					}
				}
				if ( ! $matched ) {
					continue;
				}
			}

			$faces_to_install[] = array(
				'fontWeight' => $weight,
				'fontStyle'  => $style,
				'src'        => $src,
			);
		}

		if ( empty( $faces_to_install ) ) {
			wp_delete_post( $ff_post_id, true );
			return new \WP_Error( 'no_faces', __( 'No matching font variations found. Check font_variations or try without it to install all available weights.', 'ollie-pro' ) );
		}

		$installed_faces = self::install_font_faces( $ff_post_id, $faces_to_install );
		if ( is_wp_error( $installed_faces ) ) {
			wp_delete_post( $ff_post_id, true );
			return $installed_faces;
		}

		// Register in global styles.
		self::register_font_in_global_styles( $font_slug, $google_font_name, $font_family_css );

		return array(
			'status'      => 'installed',
			'font_family' => array(
				'id'         => $ff_post_id,
				'slug'       => $font_slug,
				'name'       => $google_font_name,
				'fontFamily' => $font_family_css,
				'faces'      => $installed_faces,
			),
		);
	}

	/**
	 * Find a font in the Google Fonts collection.
	 *
	 * @return array|\WP_Error Font data array or error.
	 */
	private static function find_google_font( string $name ) {
		$library     = \WP_Font_Library::get_instance();
		$collections = $library->get_font_collections();

		// Look for the default Google Fonts collection.
		$google_collection = null;
		foreach ( $collections as $collection ) {
			$slug = $collection->slug ?? '';
			if ( 'google-fonts' === $slug || str_contains( $slug, 'google' ) ) {
				$google_collection = $collection;
				break;
			}
		}

		if ( ! $google_collection ) {
			// If no Google collection, try all collections.
			foreach ( $collections as $collection ) {
				$data = $collection->get_data();
				if ( is_wp_error( $data ) ) {
					continue;
				}
				$font_families = $data['font_families'] ?? array();
				foreach ( $font_families as $ff ) {
					$ff_name = $ff['font_family_settings']['name'] ?? '';
					if ( strcasecmp( $ff_name, $name ) === 0 ) {
						return $ff['font_family_settings'];
					}
				}
			}
			return new \WP_Error( 'font_not_found', sprintf( __( 'Font "%s" not found in any font collection.', 'ollie-pro' ), $name ) );
		}

		$data = $google_collection->get_data();
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$font_families = $data['font_families'] ?? array();
		$name_lower    = strtolower( $name );

		foreach ( $font_families as $ff ) {
			$ff_name = $ff['font_family_settings']['name'] ?? '';
			if ( strtolower( $ff_name ) === $name_lower ) {
				return $ff['font_family_settings'];
			}
		}

		return new \WP_Error( 'font_not_found', sprintf( __( 'Font "%s" not found in Google Fonts collection.', 'ollie-pro' ), $name ) );
	}

	/**
	 * Install font face posts and download their files.
	 *
	 * @param int   $ff_post_id  Font family post ID.
	 * @param array $faces_data  Array of face definitions.
	 * @return array|\WP_Error   Array of installed face info or error.
	 */
	private static function install_font_faces( int $ff_post_id, array $faces_data ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$font_dir  = wp_get_font_dir();
		$installed = array();

		foreach ( $faces_data as $face ) {
			$weight = $face['fontWeight'] ?? '400';
			$style  = $face['fontStyle'] ?? 'normal';
			$src    = $face['src'] ?? '';

			if ( empty( $src ) ) {
				continue;
			}

			// Download the font file to the fonts directory.
			$local_src = $src;
			if ( filter_var( $src, FILTER_VALIDATE_URL ) ) {
				$downloaded = self::download_font_file( $src, $font_dir );
				if ( is_wp_error( $downloaded ) ) {
					$installed[] = array(
						'fontWeight' => $weight,
						'fontStyle'  => $style,
						'src'        => $src,
						'status'     => 'failed',
						'error'      => $downloaded->get_error_message(),
					);
					continue;
				}
				$local_src = $downloaded['url'];
				$local_file = $downloaded['file'];
			}

			$face_settings = array(
				'fontWeight' => $weight,
				'fontStyle'  => $style,
				'src'        => $local_src,
			);

			// Generate a unique slug for the face.
			$face_slug = sanitize_title( $weight . '-' . $style );

			$face_post_id = wp_insert_post( array(
				'post_type'    => 'wp_font_face',
				'post_parent'  => $ff_post_id,
				'post_title'   => $face_slug,
				'post_name'    => $face_slug,
				'post_status'  => 'publish',
				'post_content' => wp_json_encode( $face_settings ),
			), true );

			if ( is_wp_error( $face_post_id ) ) {
				$installed[] = array(
					'fontWeight' => $weight,
					'fontStyle'  => $style,
					'status'     => 'failed',
					'error'      => $face_post_id->get_error_message(),
				);
				continue;
			}

			// Store relative font file path in meta if we downloaded it.
			if ( isset( $local_file ) ) {
				$relative = str_replace( $font_dir['basedir'] . '/', '', $local_file );
				add_post_meta( $face_post_id, '_wp_font_face_file', $relative );
			}

			$installed[] = array(
				'id'         => $face_post_id,
				'fontWeight' => $weight,
				'fontStyle'  => $style,
				'src'        => $local_src,
				'status'     => 'installed',
			);
		}

		return $installed;
	}

	/**
	 * Download a remote font file to the WP fonts directory.
	 *
	 * @return array|\WP_Error Array with 'file' (path) and 'url' keys, or error.
	 */
	private static function download_font_file( string $url, array $font_dir ) {
		// Ensure fonts directory exists.
		if ( ! file_exists( $font_dir['path'] ) ) {
			wp_mkdir_p( $font_dir['path'] );
		}

		// Determine file extension.
		$path_info = pathinfo( wp_parse_url( $url, PHP_URL_PATH ) );
		$ext       = $path_info['extension'] ?? 'woff2';
		$allowed   = array( 'woff2', 'woff', 'ttf', 'otf', 'eot' );
		if ( ! in_array( strtolower( $ext ), $allowed, true ) ) {
			$ext = 'woff2';
		}

		$filename  = wp_unique_filename( $font_dir['path'], sanitize_file_name( ( $path_info['filename'] ?? 'font' ) . '.' . $ext ) );
		$dest_path = trailingslashit( $font_dir['path'] ) . $filename;

		$tmp_file = download_url( $url, 60 );
		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Move to fonts directory.
		$moved = rename( $tmp_file, $dest_path );
		if ( ! $moved ) {
			// Fallback: copy then delete.
			if ( copy( $tmp_file, $dest_path ) ) {
				unlink( $tmp_file );
			} else {
				unlink( $tmp_file );
				return new \WP_Error( 'move_failed', __( 'Could not move font file to fonts directory.', 'ollie-pro' ) );
			}
		}

		return array(
			'file' => $dest_path,
			'url'  => trailingslashit( $font_dir['url'] ) . $filename,
		);
	}

	/**
	 * Register a newly installed font in the global styles so it appears in the font picker.
	 */
	private static function register_font_in_global_styles( string $slug, string $name, string $font_family_css ): void {
		$gs_post = Global_Styles_Helper::get_post();
		if ( is_wp_error( $gs_post ) ) {
			return;
		}

		$settings = json_decode( $gs_post->post_content, true ) ?: array();

		$font_families = $settings['settings']['typography']['fontFamilies']['custom'] ?? array();

		// Avoid duplicates.
		foreach ( $font_families as $ff ) {
			if ( ( $ff['slug'] ?? '' ) === $slug ) {
				return;
			}
		}

		$font_families[] = array(
			'fontFamily' => $font_family_css,
			'name'       => $name,
			'slug'       => $slug,
		);

		$settings['settings']['typography']['fontFamilies']['custom'] = $font_families;

		wp_update_post( array(
			'ID'           => $gs_post->ID,
			'post_content' => wp_json_encode( $settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		) );

		self::bust_cache();
	}

	/* ─── REMOVE-FONT ─────────────────────────────────────────────── */

	/**
	 * Remove a user-installed font family and all its faces.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_remove_font( array $input ) {
		$font_slug = $input['font_slug'] ?? '';

		if ( empty( $font_slug ) ) {
			return new \WP_Error( 'missing_slug', __( 'Provide font_slug to identify the font to remove.', 'ollie-pro' ) );
		}

		$ff_post = self::get_font_family_post_by_slug( $font_slug );
		if ( ! $ff_post ) {
			return new \WP_Error( 'font_not_found', sprintf( __( 'Font family "%s" not found in the Font Library. Note: theme-bundled fonts cannot be removed this way.', 'ollie-pro' ), $font_slug ) );
		}

		// Delete all child font faces (and their files via WP's _wp_before_delete_font_face hook).
		$face_ids = get_children( array(
			'post_parent' => $ff_post->ID,
			'post_type'   => 'wp_font_face',
			'fields'      => 'ids',
		) );

		$deleted_faces = 0;
		foreach ( $face_ids as $face_id ) {
			if ( wp_delete_post( $face_id, true ) ) {
				$deleted_faces++;
			}
		}

		// Delete the font family post.
		wp_delete_post( $ff_post->ID, true );

		// Remove from global styles custom fonts.
		self::unregister_font_from_global_styles( $font_slug );

		return array(
			'status'        => 'removed',
			'font_slug'     => $font_slug,
			'deleted_faces' => $deleted_faces,
		);
	}

	/**
	 * Remove a font from the global styles custom font families list.
	 */
	private static function unregister_font_from_global_styles( string $slug ): void {
		$gs_post = Global_Styles_Helper::get_post();
		if ( is_wp_error( $gs_post ) ) {
			return;
		}

		$settings = json_decode( $gs_post->post_content, true ) ?: array();

		$font_families = $settings['settings']['typography']['fontFamilies']['custom'] ?? array();
		$filtered      = array_values( array_filter( $font_families, function ( $ff ) use ( $slug ) {
			return ( $ff['slug'] ?? '' ) !== $slug;
		} ) );

		if ( count( $filtered ) === count( $font_families ) ) {
			return;
		}

		if ( empty( $filtered ) ) {
			unset( $settings['settings']['typography']['fontFamilies']['custom'] );
		} else {
			$settings['settings']['typography']['fontFamilies']['custom'] = $filtered;
		}

		wp_update_post( array(
			'ID'           => $gs_post->ID,
			'post_content' => wp_json_encode( $settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		) );

		self::bust_cache();
	}

	/* ─── LIST-FONT-COLLECTIONS ───────────────────────────────────── */

	/**
	 * List available font collections (e.g. Google Fonts) and optionally search within one.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_list_font_collections( array $input ) {
		$library     = \WP_Font_Library::get_instance();
		$collections = $library->get_font_collections();

		$slug   = $input['collection_slug'] ?? '';
		$search = $input['collection_search'] ?? '';

		// If a specific collection slug is given, return its data.
		if ( ! empty( $slug ) ) {
			$collection = $library->get_font_collection( $slug );
			if ( ! $collection ) {
				$available = array_map( function ( $c ) { return $c->slug; }, $collections );
				return new \WP_Error(
					'collection_not_found',
					sprintf( __( 'Collection "%s" not found. Available: %s', 'ollie-pro' ), $slug, implode( ', ', $available ) )
				);
			}

			$data = $collection->get_data();
			if ( is_wp_error( $data ) ) {
				return $data;
			}

			$font_families = $data['font_families'] ?? array();

			// Optionally filter by search term.
			if ( ! empty( $search ) ) {
				$search_lower  = strtolower( $search );
				$font_families = array_values( array_filter( $font_families, function ( $ff ) use ( $search_lower ) {
					$name = strtolower( $ff['font_family_settings']['name'] ?? '' );
					return str_contains( $name, $search_lower );
				} ) );
			}

			// Summarize fonts (don't return full face data unless searching).
			$summary = array_map( function ( $ff ) {
				$settings = $ff['font_family_settings'] ?? array();
				$faces    = $settings['fontFace'] ?? array();
				$weights  = array_unique( array_map( function ( $f ) { return $f['fontWeight'] ?? '400'; }, $faces ) );
				sort( $weights );
				return array(
					'name'       => $settings['name'] ?? '',
					'slug'       => $settings['slug'] ?? '',
					'fontFamily' => $settings['fontFamily'] ?? '',
					'weights'    => $weights,
					'styles'     => array_unique( array_map( function ( $f ) { return $f['fontStyle'] ?? 'normal'; }, $faces ) ),
					'face_count' => count( $faces ),
				);
			}, array_slice( $font_families, 0, 50 ) );

			return array(
				'status'       => 'ok',
				'collection'   => $slug,
				'total_fonts'  => count( $font_families ),
				'showing'      => count( $summary ),
				'fonts'        => $summary,
			);
		}

		// List all collections.
		$result = array();
		foreach ( $collections as $collection ) {
			$result[] = array(
				'slug'        => $collection->slug,
				'name'        => $collection->name ?? $collection->slug,
				'description' => $collection->description ?? '',
			);
		}

		return array(
			'status'      => 'ok',
			'collections' => $result,
		);
	}

	/* ─── HELPERS ─────────────────────────────────────────────────── */

	/**
	 * Get all known font family slugs (theme + user-installed).
	 */
	private static function get_all_font_family_slugs(): array {
		$slugs = array();

		// Theme fonts.
		$theme_fonts = wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ) ?: array();
		foreach ( $theme_fonts as $f ) {
			if ( ! empty( $f['slug'] ) ) {
				$slugs[] = $f['slug'];
			}
		}

		// Custom (user) fonts from global styles.
		$gs_post = Global_Styles_Helper::get_post();
		if ( ! is_wp_error( $gs_post ) ) {
			$settings     = json_decode( $gs_post->post_content, true ) ?: array();
			$custom_fonts = $settings['settings']['typography']['fontFamilies']['custom'] ?? array();
			foreach ( $custom_fonts as $f ) {
				if ( ! empty( $f['slug'] ) ) {
					$slugs[] = $f['slug'];
				}
			}
		}

		return array_unique( $slugs );
	}

	/**
	 * Find a wp_font_family post by its slug.
	 */
	private static function get_font_family_post_by_slug( string $slug ): ?\WP_Post {
		$query = new \WP_Query( array(
			'post_type'      => 'wp_font_family',
			'name'           => $slug,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		) );

		return ! empty( $query->posts ) ? $query->posts[0] : null;
	}

	/**
	 * Bust theme.json caches after updates.
	 */
	private static function bust_cache(): void {
		Global_Styles_Helper::bust_cache();
	}

	/**
	 * Recursively deep-merge two arrays.
	 */
	private static function deep_merge( array $base, array $overlay ): array {
		foreach ( $overlay as $key => $value ) {
			if ( is_int( $key ) ) {
				return $overlay;
			}
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = self::deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}
}
