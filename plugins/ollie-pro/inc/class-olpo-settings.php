<?php

namespace olpo;

class Settings {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Settings.
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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_action( 'admin_footer', array( $this, 'render_modal' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_modal_scripts' ) );
		add_filter( 'plugin_action_links_ollie-pro/ollie-pro.php', array( $this, 'add_quick_links' ) );
	}

	/**
	 * Register quick links in plugins settings page.
	 *
	 * @param array $links given list of links.
	 *
	 * @return array
	 */
	public function add_quick_links( $links ) {
		$settings_url = esc_url( add_query_arg( 'page', 'ollie', get_admin_url() . 'themes.php' ) );
		$docs_url     = esc_url( 'https://olliewp.com/docs/' );

		$links[] = '<a href="' . $settings_url . '">' . esc_html__( 'Settings', 'ollie-pro' ) . '</a>';
		$links[] = '<a target="_blank" href="' . $docs_url . '">' . esc_html__( 'Docs', 'ollie-pro' ) . '</a>';

		return $links;
	}

	/**
	 * Add admin menu item.
	 *
	 * @return void
	 */
	public function add_menu() {
		$settings_suffix = add_theme_page(
			esc_html__( 'Ollie', 'ollie-pro' ),
			esc_html__( 'Ollie', 'ollie-pro' ),
			'manage_options',
			'ollie',
			array( $this, 'render_settings' )
		);

		add_action( "admin_print_scripts-{$settings_suffix}", array( $this, 'add_settings_scripts' ) );
		add_action( 'admin_print_scripts', array( $this, 'add_settings_scripts' ) );
	}

	/**
	 * Enqueue admin settings scripts.
	 *
	 * @return void
	 */
	public function add_settings_scripts() {
		$screen = get_current_screen();

		// Skip if not on Ollie settings page.
		if ( 'appearance_page_ollie' !== $screen->base ) {
			return;
		}

		wp_enqueue_media();

		$onboarding_asset_file = include OLPO_PATH . '/inc/onboarding/build/index.asset.php';
		$onboarding_version    = isset( $onboarding_asset_file['version'] ) ? $onboarding_asset_file['version'] : OLPO_VERSION;

		wp_enqueue_script( 'ollie-onboarding', OLPO_URL . '/inc/onboarding/build/index.js', array(
			'wp-api',
			'wp-components',
			'wp-plugins',
			'wp-edit-post',
			'wp-edit-site',
			'wp-element',
			'wp-api-fetch',
			'wp-data',
			'wp-i18n',
			'wp-block-editor',
		), $onboarding_version, true );

		$args = array(
			'screen'              => 'ollie-onboarding',
			'version'             => $onboarding_version,
			'dashboard_link'      => esc_url( admin_url() ),
			'home_link'           => esc_url( home_url() ),
			'permalink_structure' => true,
		);

		// Check if partner.txt file exists and add partner ID and admin email to args
		$partner_file = OLPO_PATH . '/partner.txt';

		if ( file_exists( $partner_file ) ) {
			$partner_key         = trim( file_get_contents( $partner_file ) );
			$args['partner_key'] = $partner_key;
			$args['admin_email'] = get_option( 'admin_email' );
			$args['site_url']    = preg_replace( '(^https?://)', '', site_url() );
		}

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

		// Pass them to global options data.
		$args['email']    = $email;
		$args['password'] = $password;

		// Check permalink structure.
		$permalinks = get_option( 'permalink_structure' );

		if ( empty( $permalinks ) ) {
			$args['permalink_structure'] = false;
		}

		// Get latest changelog entries
		$args['plugin_changelog'] = $this->get_latest_changelog_entry( OLPO_PATH . '/readme.txt' );
		$args['theme_changelog']  = $this->get_latest_changelog_entry( get_template_directory() . '/readme.txt' );

		// Abilities / MCP Adapter details for the settings page.
		$args['site_name']              = get_bloginfo( 'name' );
		$args['site_url']               = site_url();
		$args['abilities_adapter_active'] = is_plugin_active( 'mcp-adapter/mcp-adapter.php' );
		$args['ai_plugin_active']         = is_plugin_active( 'ai/ai.php' );
		$args['ai_provider_connected']    = $this->is_ai_provider_connected();
		$args['ai_connectors_url']        = admin_url( 'options-connectors.php' );
		$args['abilities_endpoint']       = rest_url( 'mcp/mcp-adapter-default-server' );
		$args['wp_path']                = ABSPATH;

		// Check for an existing Ollie Abilities application password.
		$current_user = wp_get_current_user();
		$args['current_user_login']             = $current_user ? $current_user->user_login : '';
		$args['abilities_app_password_exists'] = false;
		$args['abilities_app_password_user']   = '';
		$args['abilities_app_password_uuid']   = '';

		if ( $current_user && $current_user->ID ) {
			$app_passwords = \WP_Application_Passwords::get_user_application_passwords( $current_user->ID );
			foreach ( $app_passwords as $app_pass ) {
				if ( isset( $app_pass['name'] ) && false !== strpos( $app_pass['name'], 'Ollie Abilities' ) ) {
					$args['abilities_app_password_exists'] = true;
					$args['abilities_app_password_user']   = $current_user->user_login;
					$args['abilities_app_password_uuid']   = $app_pass['uuid'] ?? '';
					break;
				}
			}
		}

		wp_localize_script( 'ollie-onboarding', 'ollie_options', $args );

		// Make the blocks translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ollie-onboarding', 'ollie-pro', OLPO_PATH . '/languages' );
		}

		wp_enqueue_style( 'ollie-onboarding-style', OLPO_URL . '/inc/onboarding/build/index.css', array( 'wp-components' ), $onboarding_version );
	}

	/**
	 * Add modal related scripts.
	 *
	 * @return void
	 */
	public function add_modal_scripts() {
		// Never show the activation modal on network admin pages.
		if ( is_network_admin() ) {
			return;
		}

		$ollie_settings = get_option( 'ollie' );
		$theme          = wp_get_theme();

		// Skip if onboarding is already complete.
		if ( true === isset( $ollie_settings['skip_onboarding'] ) ) {
			return;
		}

		wp_enqueue_script( 'ollie-onboarding', OLPO_URL . '/inc/onboarding/build/index.js', array(
			'wp-api',
			'wp-components',
			'wp-plugins',
			'wp-edit-post',
			'wp-edit-site',
			'wp-element',
			'wp-api-fetch',
			'wp-data',
			'wp-i18n',
			'wp-block-editor'
		), OLPO_VERSION, true );

		$args = array(
			'screen'          => 'ollie-modal',
			'onboarding_link' => admin_url() . 'themes.php?page=ollie',
			'skip_onboarding' => false,
		);

		if ( isset( $ollie_settings['skip_onboarding'] ) ) {
			$args['skip_onboarding'] = $ollie_settings['skip_onboarding'];
		}

		wp_localize_script( 'ollie-onboarding', 'ollie_options', $args );

		// Make the blocks translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ollie-onboarding', 'ollie-pro', OLPO_PATH . '/languages' );
		}
	}

	/**
	 * Whether any registered AI provider connector has authentication configured.
	 *
	 * Mirrors the WP AI plugin's hasCredentials check (which drives its own
	 * "Verify you have one or more AI Connectors configured" notice): iterate
	 * the WP Connector Registry for ai_provider connectors and consider any
	 * env var, PHP constant, or stored option to count as "connected".
	 *
	 * @return bool True if at least one AI provider has credentials present.
	 */
	private function is_ai_provider_connected(): bool {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return false;
		}

		foreach ( (array) wp_get_connectors() as $cdata ) {
			if ( ! is_array( $cdata ) || 'ai_provider' !== ( $cdata['type'] ?? '' ) ) {
				continue;
			}

			$auth = $cdata['authentication'] ?? null;
			if ( ! is_array( $auth ) || 'api_key' !== ( $auth['method'] ?? '' ) ) {
				continue;
			}

			$env_var = (string) ( $auth['env_var_name'] ?? '' );
			if ( '' !== $env_var ) {
				$env_value = getenv( $env_var );
				if ( false !== $env_value && '' !== $env_value ) {
					return true;
				}
			}

			$const_name = (string) ( $auth['constant_name'] ?? '' );
			if ( '' !== $const_name && defined( $const_name ) ) {
				$const_value = constant( $const_name );
				if ( is_string( $const_value ) && '' !== $const_value ) {
					return true;
				}
			}

			$setting_name = (string) ( $auth['setting_name'] ?? '' );
			if ( '' !== $setting_name ) {
				$db_value = get_option( $setting_name, '' );
				if ( '' !== $db_value ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Render Ollie settings.
	 *
	 * @return void
	 */
	public function render_settings() {
		?>
        <div id="ollie-onboarding"></div>
		<?php
	}

	/**
	 * Set up Rest API routes.
	 *
	 * @return void
	 */
	public function rest_api_init() {
		register_rest_route( 'ollie/v1', '/settings', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_settings' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/settings', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'save_settings' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/create-child-theme', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_child_theme' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/skip-onboarding', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'skip_onboarding' ],
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/create-pages', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_pages' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', 'patterns/download', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'download_pattern' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );


		register_rest_route( 'ollie/v1', 'template-parts/download', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'download_template_part' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', 'patterns/delete', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_pattern' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', 'favorites/add', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'add_favorite' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', 'favorites/delete', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_favorite' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', '/create-blog-page', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_blog_page' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' ) && current_user_can( 'edit_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', '/update-typography-style', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_typography_style' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);

		register_rest_route( 'ollie/v1', '/update-template-part', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_template_part' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			}
		) );

		register_rest_route( 'ollie/v1', '/update-template', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_template' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			}
		) );

		register_rest_route( 'ollie/v1', '/delete-preview-page', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'delete_preview_page' ),
			'permission_callback' => function () {
				return current_user_can( 'delete_pages' );
			}
		) );

		register_rest_route( 'ollie/v1', '/cleanup-pattern-files', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'cleanup_pattern_files' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_theme_options' );
			}
		) );

		register_rest_route( 'ollie/v1', '/preview-page', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'manage_preview_page' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_pages' );
			}
		) );

		register_rest_route( 'ollie/v1', '/check-plugins', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'check_plugins_status' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
			}
		) );

		register_rest_route( 'ollie/v1', '/install-plugins', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'install_plugins' ),
			'permission_callback' => function () {
				return current_user_can( 'install_plugins' );
			}
		) );

		register_rest_route( 'ollie/v1', '/reset-templates', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_templates' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				}
			)
		);

		register_rest_route( 'ollie/v1', '/reset-global-styles', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_global_styles' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);

		register_rest_route( 'ollie/v1', '/remove-color-values', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'remove_color_values' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);

		register_rest_route( 'ollie/v1', '/clear-credentials', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'clear_credentials' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
			},
		) );

		register_rest_route( 'ollie/v1', 'partner/delete-file', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_partner_file' ],
			'permission_callback' => function () {
				return current_user_can( 'publish_pages' );
			},
		) );

		register_rest_route( 'ollie/v1', '/download-skill', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'download_skill' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	/**
	 * Get Ollie settings via Rest API.
	 *
	 * @return false|mixed|null
	 */
	public function get_settings() {
		$options = (array) get_option( 'ollie', [] );

		// Ensure we always return an 'extensions' key with sensible defaults (all enabled)
		$default_extensions = [
			'menu-designer'       => true,
			'animation-designer'  => true,
			'hover-colors'        => true,
			'keyboard-shortcuts'  => true,
			'advanced-group'      => true,
			'advanced-columns'    => true,
			'advanced-paragraph'  => true,
			'button-icons'        => true,
			'advanced-grid'       => true,
			'smart-sync'          => true,
			'responsive-controls'       => true,
			'carousel'            => true,
		];

		$should_persist = false;

		if ( ! isset( $options['extensions'] ) || ! is_array( $options['extensions'] ) ) {
			// No extensions saved yet — set defaults and mark for persistence
			$options['extensions'] = $default_extensions;
			$should_persist        = true;
		} else {
			// Merge saved values (cast to bool) over defaults to accommodate new keys
			$saved = array();
			foreach ( $options['extensions'] as $k => $v ) {
				$saved[ sanitize_key( $k ) ] = (bool) $v;
			}
			$merged = array_merge( $default_extensions, $saved );

			// If merged differs from what's stored (e.g., new keys added), persist the merged set
			if ( $merged !== $options['extensions'] ) {
				$options['extensions'] = $merged;
				$should_persist        = true;
			} else {
				$options['extensions'] = $merged;
			}
		}

		// Force Menu Designer extension state to mirror the Ollie Menu Designer plugin activation.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$menu_designer_plugin_file = 'ollie-menu-designer/ollie-menu-designer.php';
		$plugin_active = function_exists( 'is_plugin_active' ) && is_plugin_active( $menu_designer_plugin_file );
		if ( ! isset( $options['extensions']['menu-designer'] ) || $options['extensions']['menu-designer'] !== (bool) $plugin_active ) {
			$options['extensions']['menu-designer'] = (bool) $plugin_active;
			$should_persist = true;
		}

		if ( $should_persist ) {
			update_option( 'ollie', $options );
		}

		return $options;
	}

	/**
	 * Save settings via Rest API.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function save_settings( $request ) {
		if ( $request->get_params() ) {
			$params  = $request->get_params();
			$options = $this->sanitize_options_array( $params );

			// Merge with existing options to avoid overwriting unrelated settings
			$existing = (array) get_option( 'ollie', [] );
			$options  = array_merge( $existing, $options );

			// Enforce: Menu Designer extension is controlled by the plugin activation, not user input
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$menu_designer_plugin_file = 'ollie-menu-designer/ollie-menu-designer.php';
			$plugin_active            = function_exists( 'is_plugin_active' ) && is_plugin_active( $menu_designer_plugin_file );
			if ( isset( $options['extensions'] ) && is_array( $options['extensions'] ) ) {
				$options['extensions']['menu-designer'] = (bool) $plugin_active;
			}

			// Save to Ollie options
			update_option( 'ollie', $options );

			// Handle WordPress core settings for homepage display
			// Only update if the request explicitly includes these params (not from merged existing options)
			if ( array_key_exists( 'show_on_front', $params ) ) {
				update_option( 'show_on_front', $options['show_on_front'] );

				if ( $options['show_on_front'] === 'page' ) {
					if ( array_key_exists( 'page_on_front', $params ) ) {
						update_option( 'page_on_front', absint( $options['page_on_front'] ) );
					}
					if ( array_key_exists( 'page_for_posts', $params ) ) {
						update_option( 'page_for_posts', absint( $options['page_for_posts'] ) );
					}
				} else {
					// Reset page settings when showing posts
					update_option( 'page_on_front', 0 );
					update_option( 'page_for_posts', 0 );
				}
			}

			return json_encode( [ "status" => 200, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "No data received." ] );
	}

	/**
	 * Create child theme via helper method.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function create_child_theme( $request ) {
		if ( $request->get_params() ) {
			$params = $request->get_params();
			Helper::create_child_theme( $params );

			return json_encode( [ "status" => 200, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "Could not create child theme." ] );
	}

	/**
	 * Skip onboarding.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function skip_onboarding( $request ) {
		if ( $request->get_params() ) {
			$options = (array) get_option( 'ollie', [] );

			// Set skip onboarding to true and update.
			$options['skip_onboarding'] = true;
			update_option( 'ollie', $options );

			return json_encode( [ "status" => 200, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "There was a problem skipping the onboarding." ] );
	}

	/**
	 * Create pages.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function create_pages( $request ) {
		if ( $request->get_params() ) {
			$pages = $request->get_params();
			unset( $pages['_locale'] );
			unset( $pages['rest_route'] );

			$created_pages = Helper::create_pages( $pages );

			return json_encode( [ "status" => 200, "pages" => $created_pages, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "Could not create pages." ] );
	}

	/**
	 * Install a pattern to the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function download_pattern( $request ) {
		if ( $request->get_params() ) {
			$pattern = $request->get_params();

			unset( $pattern['_locale'] );
			unset( $pattern['rest_route'] );

			$created_pattern = Helper::download_pattern( $pattern );

			if ( $created_pattern ) {
				return json_encode( [ "status" => 200, "pattern" => $created_pattern, "message" => "Ok" ] );
			}
		}

		return json_encode( [ "status" => 400, "message" => "Could not install pattern." ] );
	}

	/**
	 * Install a template part to the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function download_template_part( $request ) {
		if ( $request->get_params() ) {
			$pattern = $request->get_params();

			unset( $pattern['_locale'] );
			unset( $pattern['rest_route'] );

			$created_template_part = Helper::download_pattern( $pattern, true );

			if ( $created_template_part ) {
				return json_encode( [ "status" => 200, "template_part" => $created_template_part, "message" => "Ok" ] );
			}
		}

		return json_encode( [ "status" => 400, "message" => "Could not install pattern." ] );
	}

	/**
	 * Clean up pro pattern files from the theme's patterns directory.
	 * Called after starter site import is complete.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public function cleanup_pattern_files() {
		$patterns_dir = get_stylesheet_directory() . '/patterns/';
		$pro_prefixes = [ 'agency', 'creator', 'startup', 'studio', 'ecommerce' ];
		$deleted      = [];

		if ( is_dir( $patterns_dir ) ) {
			$files = scandir( $patterns_dir );

			foreach ( $files as $file ) {
				// Skip directories and non-PHP files
				if ( $file === '.' || $file === '..' || pathinfo( $file, PATHINFO_EXTENSION ) !== 'php' ) {
					continue;
				}

				// Check if file starts with a pro prefix
				foreach ( $pro_prefixes as $prefix ) {
					if ( strpos( $file, $prefix . '-' ) === 0 ) {
						$file_path = $patterns_dir . $file;
						if ( file_exists( $file_path ) ) {
							wp_delete_file( $file_path );
							$deleted[] = $file;
						}
						break;
					}
				}
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'deleted' => $deleted,
			'message' => sprintf( __( 'Cleaned up %d pattern files', 'ollie-pro' ), count( $deleted ) )
		) );
	}

	/**
	 * Install a pattern to the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function add_favorite( $request ) {
		if ( $request->get_params() ) {
			$data              = $request->get_params();
			$favorite_patterns = get_option( 'ollie_favorite_patterns', [] );

			// Check before use.
			if ( isset( $data[0] ) ) {
				$favorite_to_add = $data[0];

				// Add the pattern to the list.
				$favorite_patterns[] = $favorite_to_add;

				// Update the favorites list.
				update_option( 'ollie_favorite_patterns', $favorite_patterns );

				return json_encode( [ "status" => 200, "message" => "Ok" ] );
			}
		}

		return json_encode( [ "status" => 400, "message" => "Could not install pattern." ] );
	}

	/**
	 * Uninstall a pattern from the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function delete_favorite( $request ) {
		if ( $request->get_params() ) {
			$data              = $request->get_params();
			$favorite_patterns = get_option( 'ollie_favorite_patterns', [] );

			// Check before use.
			if ( isset( $data[0] ) && ! empty( $favorite_patterns ) ) {
				$favorite_to_delete = $data[0];

				if ( ( $key = array_search( $favorite_to_delete, $favorite_patterns ) ) !== false ) {

					unset( $favorite_patterns[ $key ] );

					// Update the list.
					update_option( 'ollie_favorite_patterns', $favorite_patterns );
				}
			}

			return json_encode( [
				"status"  => 200,
				"message" => "Ok"
			] );
		}

		return json_encode( [ "status" => 400, "message" => "Could not remove favorite." ] );
	}

	/**
	 * Uninstall a pattern from the local filesystem.
	 *
	 * @param object $request given request.
	 *
	 * @return string
	 */
	public function delete_pattern( $request ) {
		if ( $request->get_params() ) {
			$pattern = $request->get_params();

			unset( $pattern['_locale'] );
			unset( $pattern['rest_route'] );

			$uninstalled = Helper::delete_pattern( $pattern );

			return json_encode( [
				"status"      => 200,
				"uninstalled" => $uninstalled,
				"message"     => "Ok"
			] );
		}

		return json_encode( [ "status" => 400, "message" => "Could not uninstall pattern." ] );
	}

	/**
	 * Sanitize options array before saving to database.
	 *
	 * @param array $options User-submitted options.
	 *
	 * @return array Sanitized array of options.
	 */
	private function sanitize_options_array( array $options = [] ) {
		$sanitized_options = [];

		foreach ( $options as $key => $value ) {
			switch ( $key ) {
				case 'skip_onboarding':
				case 'onboarding_complete':
					$sanitized_options[ $key ] = (bool) $value;
					break;
				case 'color_palette':
					$sanitized_options[ $key ] = $value;
					break;
				case 'extensions':
					// Expect an associative array of extension_slug => bool
					$sanitized_options['extensions'] = array();
					if ( is_array( $value ) ) {
						foreach ( $value as $ext_key => $ext_val ) {
							$sanitized_options['extensions'][ sanitize_key( $ext_key ) ] = (bool) $ext_val;
						}
					}
					break;
				default:
					$sanitized_options[ $key ] = is_scalar( $value ) ? sanitize_text_field( $value ) : $value;
					break;
			}
		}

		return $sanitized_options;
	}

	/**
	 * Render Ollie onboarding modal.
	 *
	 * @return void
	 */
	public function render_modal() {
		// Never show the activation modal on network admin pages.
		if ( is_network_admin() ) {
			return;
		}
		?>
        <div id="ollie-modal"></div>
        <style>
            @keyframes OllieFadeIn {
                0% {
                    opacity: 0;
                }
                100% {
                    opacity: 1;
                }
            }

            .ollie-modal-background {
                background: rgba(93, 93, 111, 0.7);
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9991;
                animation: OllieFadeIn .5s;
            }

            .ollie-modal-content {
                background: white;
                padding: 50px;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 800px;
                width: 90%;
                box-shadow: 0 3px 10px rgb(0, 0, 0, 0.2);
                z-index: 99;
                border-radius: 3px;
            }

            .ollie-modal-close {
                background: none;
                border: none;
                padding: 0;
                position: absolute;
                right: 20px;
                top: 20px;
            }

            .ollie-modal-close:hover {
                cursor: pointer;
                opacity: .6;
            }

            .ollie-modal-columns {
                display: flex;
                align-items: center;
                gap: 40px;
            }

            .ollie-modal-text {
                flex: 1;
				min-width: 400px;
            }

            .ollie-modal-image {
                flex: 1;
				padding: 40px 40px 0 0;
                border-radius: 8px;
            }

			.ollie-modal-content {
				border-radius: 8px;
				overflow: hidden;
			}

            .ollie-modal-content img {
                max-width: 520px;
                display: block;
            }

            .ollie-modal-title {
                display: inline-block;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
                color: #3858e9;
                margin-bottom: 20px;
            }

            .ollie-modal-content h2 {
                font-size: 2.2em;
                font-weight: 400;
                margin: 0 0 25px;
            }

            .ollie-modal-content p {
                margin: 0 0 25px;
                font-size: 16px;
            }

            .ollie-modal-content .ollie-modal-inner button {
                padding: 0 20px;
                height: 42px;
                transition: 0.3s ease;
                background: #3858e9;
                color: white;
                border: none;
                cursor: pointer;
                border-radius: 2px;
                font-size: 14px;
            }

            .ollie-modal-content .ollie-modal-inner button:hover {
                background: #2145e6;
            }

            .ollie-modal-content button.ollie-modal-skip {
                background: none;
                color: #3c434a;
            }

            .ollie-modal-content button.ollie-modal-skip:hover {
                text-decoration: underline;
                background: none;
            }

        </style>
		<?php
	}

	public function create_blog_page() {
		// Check if a page with slug 'blog' exists
		$existing_page = get_page_by_path( 'blog' );

		// Set up the page details
		$page_title = $existing_page ? 'My Blog' : 'Blog';
		$page_slug  = $existing_page ? 'my-blog' : 'blog';

		// Create the page
		$page_id = wp_insert_post( array(
			'post_title'  => $page_title,
			'post_name'   => $page_slug,
			'post_status' => 'publish',
			'post_type'   => 'page'
		) );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			wp_send_json( [
				"status"     => 200,
				"page_id"    => $page_id,
				"page_title" => $page_title,
				"message"    => "Blog page created successfully"
			] );
		}

		wp_send_json( [
			"status"  => 400,
			"message" => "Could not create blog page"
		] );
	}

	/**
	 * Updates the typography style setting.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function update_typography_style( $request ) {
		$style = $request->get_param( 'style' );

		if ( ! $style ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Style parameter is required'
				),
				400
			);
		}

		// Update the global styles
		$result = Helper::update_global_typography( $style );

		if ( $result['success'] ) {
			// Update the Ollie settings
			$settings               = get_option( 'ollie', array() );
			$settings['typography'] = $style;
			update_option( 'ollie', $settings );

			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Typography style updated successfully'
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => $result['message']
			),
			500
		);
	}

	/**
	 * Updates a template part with the selected pattern.
	 *
	 * @param WP_REST_Request $request Request object containing 'style' and 'type' parameters.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function update_template_part( $request ) {
		// Get and validate required parameters
		$style  = $request->get_param( 'style' );
		$type   = $request->get_param( 'type' );
		$is_pro = false;

		if ( ! $style || ! $type ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'Style and type parameters are required', 'ollie-pro' )
			) );
		}

		// Determine pattern name based on style format:
		// 1. "ollie/pattern-name" - theme pattern, use as-is
		// 2. "collection/pattern-name" - pro pattern, convert / to -
		// 3. "pattern-name" - free pattern, add ollie/ prefix
		if ( strpos( $style, 'ollie/' ) === 0 ) {
			// Theme pattern - already has ollie/ prefix, use as-is
			$pattern_name = $style;
		} elseif ( strpos( $style, '/' ) !== false ) {
			// Pro pattern - convert slash to dash
			$is_pro       = true;
			$pattern_name = str_replace( '/', '-', $style );
		} else {
			// Free pattern - add ollie/ prefix
			$pattern_name = "ollie/$style";
		}

		// Get current theme
		$theme      = wp_get_theme();
		$theme_slug = $theme->get_stylesheet(); // This gets child theme if active, parent theme if no child

		// Get the pattern content
		$pattern_registry = \WP_Block_Patterns_Registry::get_instance();

		// If pattern is not registered, try to register it from the file system
		if ( ! $pattern_registry->is_registered( $pattern_name ) ) {
			// Determine the file name - for theme patterns (ollie/), strip the prefix
			// For pro patterns, use the converted slug with dashes
			if ( strpos( $pattern_name, 'ollie/' ) === 0 ) {
				$file_slug = str_replace( 'ollie/', '', $pattern_name );
			} else {
				$file_slug = $pattern_name;
			}
			$pattern_file = get_stylesheet_directory() . '/patterns/' . $file_slug . '.php';

			if ( file_exists( $pattern_file ) ) {
				// Register the pattern from the file
				$registered = Helper::register_pattern_from_file( $pattern_file );

				if ( ! $registered ) {
					return rest_ensure_response( array(
						'success' => false,
						'message' => sprintf( __( 'Pattern %s could not be registered', 'ollie-pro' ), $pattern_name )
					) );
				}
			} else {
				return rest_ensure_response( array(
					'success' => false,
					/* translators: %s: Name of the pattern */
					'message' => sprintf( __( 'Pattern %s not found', 'ollie-pro' ), $pattern_name )
				) );
			}
		}

		$pattern = $pattern_registry->get_registered( $pattern_name );

		// Get or create the template part
		$template_part = \get_block_template( "$theme_slug//$type", 'wp_template_part' );

		if ( ! $template_part || ! isset( $template_part->wp_id ) ) {
			// Create new template part
			$post_id = wp_insert_post( array(
				'post_title'   => ucfirst( $type ),
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_content' => $pattern['content'],
				'post_name'    => $type,
				'tax_input'    => array(
					'wp_theme' => array( $theme_slug )
				)
			) );

			if ( is_wp_error( $post_id ) ) {
				return rest_ensure_response( array(
					'success' => false,
					'message' => $post_id->get_error_message()
				) );
			}

			$result = $post_id;
		} else {
			// Update existing template part
			$result = wp_update_post( array(
				'ID'           => $template_part->wp_id,
				'post_content' => $pattern['content']
			) );
		}

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			) );
		}

		return rest_ensure_response( array(
			'success' => true,
			/* translators: %s: Type to update */
			'message' => sprintf( __( '%s updated successfully', 'ollie-pro' ), ucfirst( $type ) )
		) );
	}

	/**
	 * Updates a template with the selected pattern.
	 *
	 * @param WP_REST_Request $request Request object containing 'style' and 'type' parameters.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function update_template( $request ) {
		// Get and validate required parameters
		$style  = $request->get_param( 'style' );
		$type   = $request->get_param( 'type' );
		$is_pro = false;

		// If style doesn't include ollie it's a pro pattern.
		if ( strpos( $style, 'ollie/' ) === false ) {
			$is_pro = true;

			// We need to replace / with -.
			$style = str_replace( '/', '-', $style );
		}

		// Get current theme
		$theme      = wp_get_theme();
		$theme_slug = $theme->get_stylesheet(); // This gets child theme if active, parent theme if no child

		if ( ! $style || ! $type ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'Style and type parameters are required', 'ollie-pro' )
			) );
		}

		// Get the pattern content
		$pattern_registry = \WP_Block_Patterns_Registry::get_instance();
		$pattern_name     = $style;

		if ( $is_pro ) {
			// We need to get rid of ollie/ from the pattern name.
			$pattern_name = str_replace( 'ollie/', '', $pattern_name );
		}

		// If pattern is not registered, try to register it from the file system
		if ( ! $pattern_registry->is_registered( $pattern_name ) ) {
			// Try to load the pattern file if it exists
			$pattern_file = get_stylesheet_directory() . '/patterns/' . $pattern_name . '.php';

			if ( file_exists( $pattern_file ) ) {
				// Register the pattern from the file
				$registered = Helper::register_pattern_from_file( $pattern_file );

				if ( ! $registered ) {
					return rest_ensure_response( array(
						'success' => false,
						'message' => sprintf( __( 'Pattern %s could not be registered', 'ollie-pro' ), $pattern_name )
					) );
				}
			} else {
				return rest_ensure_response( array(
					'success' => false,
					/* translators: %s: Name of a pattern */
					'message' => sprintf( __( 'Pattern %s not found', 'ollie-pro' ), $pattern_name )
				) );
			}
		}

		$pattern = $pattern_registry->get_registered( $pattern_name );

		// Check if pattern already contains template parts (to avoid duplicates)
		$pattern_content = $pattern['content'];
		$has_template_parts = strpos( $pattern_content, 'wp:template-part' ) !== false;

		// Wrap pattern content with header and footer template parts (only if not already present)
		if ( ! $has_template_parts ) {
			$header_markup = '<!-- wp:template-part {"slug":"header","tagName":"header","className":"site-header"} /-->' . "\n\n";
			$footer_markup = "\n\n" . '<!-- wp:template-part {"slug":"footer","tagName":"footer","className":"site-footer"} /-->';
			$template_content = $header_markup . $pattern_content . $footer_markup;
		} else {
			$template_content = $pattern_content;
		}

		// Get or create the template
		$template = \get_block_template( "$theme_slug//$type" );

		if ( ! $template || ! isset( $template->wp_id ) ) {
			// Create new template
			$post_id = wp_insert_post( array(
				'post_title'   => ucfirst( $type ),
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_content' => $template_content,
				'post_name'    => $type,
				'tax_input'    => array(
					'wp_theme' => array( $theme_slug )
				)
			) );

			if ( is_wp_error( $post_id ) ) {
				return rest_ensure_response( array(
					'success' => false,
					'message' => $post_id->get_error_message()
				) );
			}

			$result = $post_id;
		} else {
			// Update existing template
			$result = wp_update_post( array(
				'ID'           => $template->wp_id,
				'post_content' => $template_content
			) );
		}

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			) );
		}

		// Note: We no longer delete template pattern files here because they may be
		// reused by multiple templates (e.g., index and archive using the same pattern)

		return rest_ensure_response( array(
			'success' => true,
			/* translators: %s: the type of the template */
			'message' => sprintf( __( '%s template updated successfully', 'ollie-pro' ), ucfirst( $type ) )
		) );
	}

	/**
	 * Deletes the temporary preview page
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function delete_preview_page() {
		// Delete preview page
		$page_slug = 'ollie-preview-page';
		$page      = get_page_by_path( $page_slug, OBJECT, 'page' );

		if ( $page ) {
			wp_delete_post( $page->ID, true );
		}

		// Delete preview post
		$post_slug = 'ollie-preview-post';
		$post      = get_page_by_path( $post_slug, OBJECT, 'post' );

		if ( $post ) {
			wp_delete_post( $post->ID, true );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Preview content deleted successfully', 'ollie-pro' )
		) );
	}

	/**
	 * Manages preview content - creates if it doesn't exist, returns URL if it does, or deletes if requested
	 *
	 * @param WP_REST_Request $request Request object containing optional 'action' parameter ('get' or 'delete') and 'type' ('page' or 'post')
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function manage_preview_page( $request ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'ollie-pro' ), [
				'status' => 403
			] );
		}

		$action = $request->get_param( 'action' ) ?? 'get';
		$type   = $request->get_param( 'type' ) ?? 'page';
		$slug   = 'ollie-preview-' . $type;

		$content = get_page_by_path( $slug, OBJECT, $type );

		if ( $action === 'delete' ) {
			if ( ! $content ) {
				return rest_ensure_response( array(
					'success' => true,
					/* translators: %s: Type to delete */
					'message' => sprintf( __( 'Preview %s already deleted', 'ollie-pro' ), $type )
				) );
			}

			$result = wp_delete_post( $content->ID, true );

			return rest_ensure_response( array(
				'success' => (bool) $result,
				'message' => $result
					/* translators: %s: Type to delete */
					? sprintf( __( 'Preview %s deleted successfully', 'ollie-pro' ), $type )
					/* translators: %s: Type to delete */
					: sprintf( __( 'Failed to delete preview %s', 'ollie-pro' ), $type )
			) );
		}

		// Return existing content URL if it exists
		if ( $content ) {
			return rest_ensure_response( array(
				'success' => true,
				'url'     => get_permalink( $content->ID )
			) );
		}

		// Sample post content
		$sample_content = '<!-- wp:paragraph --><p>WordPress has evolved dramatically in recent years, and Ollie is leading the charge toward a more intuitive, powerful site-building experience. Gone are the days of needing expensive or bloated page builders to create beautiful WordPress websites. With Ollie, you can design stunning, responsive sites using WordPress\'s native tools.</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">WordPress Like You\'ve Never Seen It Before</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Ollie is a WordPress block theme that integrates seamlessly with the WordPress site editor, unlocking a level of design and customization you never thought possible. It combines the power of a design system, pattern library, and block theme into one cohesive package that makes website building faster and more intuitive.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>The real magic of Ollie is how it leverages WordPress\'s powerful new features:</p><!-- /wp:paragraph --><!-- wp:list {"className":"is-style-list-boxed"} --><ul class="wp-block-list is-style-list-boxed"><!-- wp:list-item --><li><strong>Full Site Editing</strong> - Design your entire site with drag-and-drop simplicity directly in WordPress</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Patterns Library</strong> - Choose from 50+ pre-designed components to quickly build beautiful pages</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Global Styles</strong> - Make site-wide style changes with just a few clicks</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Responsive Design</strong> - Everything scales gracefully across all devices with zero extra work</li><!-- /wp:list-item --></ul><!-- /wp:list --><!-- wp:heading --><h2 class="wp-block-heading">Build Blazing-Fast Websites Without the Bloat</h2><!-- /wp:heading --><!-- wp:paragraph --><p>What makes Ollie truly special isn\'t just how good it looks—it\'s built to perform. While other solutions add layers of code that slow down your site, Ollie is lightweight and optimized for speed right out of the box.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Ollie only loads the critical styles and assets needed for each page, ensuring your site scores top marks in performance tests. This means better SEO, improved user experience, and no need for performance hacks to build a turbocharged WordPress website.</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Take It to the Next Level with Ollie Pro</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Love what Ollie offers but want even more design flexibility? Ollie Pro takes the experience to new heights with:</p><!-- /wp:paragraph --><!-- wp:list {"className":"is-style-list-boxed"} --><ul class="wp-block-list is-style-list-boxed"><!-- wp:list-item --><li><strong>Cloud Pattern Library</strong> - Access 200+ additional patterns and 30+ full page designs</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Pattern Browser</strong> - Browse, preview, and insert patterns with our intuitive interface</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Setup Wizard</strong> - Skip tedious setup steps and create new pages in minutes</li><!-- /wp:list-item --><!-- wp:list-item --><li><strong>Mix and Match Styles</strong> - Combine patterns from different collections for unlimited design possibilities</li><!-- /wp:list-item --></ul><!-- /wp:list --><!-- wp:paragraph --><p>The Ollie Pro pattern browser gives you a live preview of each pattern with responsive toggles to view designs on desktop, tablet, and mobile before you add them to your page.</p><!-- /wp:paragraph -->';

		$post_id = wp_insert_post( array(
			'post_title'   => 'Ollie Preview ' . ucfirst( $type ),
			'post_content' => $sample_content,
			'post_status'  => 'draft',
			'post_type'    => $type,
			'post_name'    => $slug
		) );

		if ( is_wp_error( $post_id ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $post_id->get_error_message()
			) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'url'     => get_permalink( $post_id )
		) );
	}

	/**
	 * Check the installation status of specified plugins
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function check_plugins_status() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = array(
			'icon-block'          => array(
				'file' => 'icon-block/icon-block.php',
				'slug' => 'icon-block'
			),
			'menu-designer'       => array(
				'file' => 'ollie-menu-designer/ollie-menu-designer.php',
				'slug' => 'ollie-menu-designer'
			),
			'block-visibility'    => array(
				'file' => 'block-visibility/block-visibility.php',
				'slug' => 'block-visibility'
			),
			'woocommerce'         => array(
				'file' => 'woocommerce/woocommerce.php',
				'slug' => 'woocommerce'
			),
			'advanced-query-loop' => array(
				'file' => 'advanced-query-loop/index.php',
				'slug' => 'advanced-query-loop'
			),
			'performance-lab'     => array(
				'file' => 'performance-lab/load.php',
				'slug' => 'performance-lab'
			),
			'custom-post-type-ui' => array(
				'file' => 'custom-post-type-ui/custom-post-type-ui.php',
				'slug' => 'custom-post-type-ui'
			),
			'create-block-theme'  => array(
				'file' => 'create-block-theme/create-block-theme.php',
				'slug' => 'create-block-theme'
			)
		);

		$status = array();
		foreach ( $plugins as $key => $plugin ) {
			$file_path      = WP_PLUGIN_DIR . '/' . $plugin['file'];
			$status[ $key ] = array(
				'installed' => file_exists( $file_path ),
				'activated' => is_plugin_active( $plugin['file'] )
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'plugins' => $status
		) );
	}

	/**
	 * Install and activate selected plugins
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function install_plugins( $request ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

		$plugins = $request->get_param( 'plugins' );

		if ( empty( $plugins ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'No plugins specified', 'ollie-pro' )
			) );
		}

		$plugin_data = array(
			'woocommerce'         => array(
				'file' => 'woocommerce/woocommerce.php',
				'slug' => 'woocommerce'
			),
			'icon-block'          => array(
				'file' => 'icon-block/icon-block.php',
				'slug' => 'icon-block'
			),
			'menu-designer'       => array(
				'file' => 'ollie-menu-designer/ollie-menu-designer.php',
				'slug' => 'ollie-menu-designer'
			),
			'block-visibility'    => array(
				'file' => 'block-visibility/block-visibility.php',
				'slug' => 'block-visibility'
			),
			'advanced-query-loop' => array(
				'file' => 'advanced-query-loop/index.php',
				'slug' => 'advanced-query-loop'
			),
			'performance-lab'     => array(
				'file' => 'performance-lab/load.php',
				'slug' => 'performance-lab'
			),
			'custom-post-type-ui' => array(
				'file' => 'custom-post-type-ui/custom-post-type-ui.php',
				'slug' => 'custom-post-type-ui'
			),
			'create-block-theme'  => array(
				'file' => 'create-block-theme/create-block-theme.php',
				'slug' => 'create-block-theme'
			),
			'mcp-adapter'         => array(
				'file'         => 'mcp-adapter/mcp-adapter.php',
				'slug'         => 'mcp-adapter',
				'download_url' => 'https://github.com/WordPress/mcp-adapter/releases/download/v0.5.0/mcp-adapter.zip'
			),
			'ai'                  => array(
				'file'         => 'ai/ai.php',
				'slug'         => 'ai',
				'download_url' => 'https://github.com/WordPress/ai/releases/download/0.7.0/ai.zip'
			)
		);

		$installed = array();
		$errors    = array();

		foreach ( $plugins as $plugin ) {
			if ( ! isset( $plugin_data[ $plugin ] ) ) {
				continue;
			}

			$slug = $plugin_data[ $plugin ]['slug'];
			$file = $plugin_data[ $plugin ]['file'];

			// Skip if already active
			if ( is_plugin_active( $file ) ) {
				continue;
			}

 		// Install if not installed
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
				// Use direct download URL if provided (e.g. GitHub-hosted plugins).
				if ( ! empty( $plugin_data[ $plugin ]['download_url'] ) ) {
					$download_link = $plugin_data[ $plugin ]['download_url'];
				} else {
					$api = plugins_api( 'plugin_information', array(
						'slug'   => $slug,
						'fields' => array(
							'short_description' => false,
							'sections'          => false,
							'requires'          => false,
							'rating'            => false,
							'ratings'           => false,
							'downloaded'        => false,
							'last_updated'      => false,
							'added'             => false,
							'tags'              => false,
							'compatibility'     => false,
							'homepage'          => false,
							'donate_link'       => false,
						),
					) );

					if ( is_wp_error( $api ) ) {
						$errors[] = $plugin;
						continue;
					}

					$download_link = $api->download_link;
				}

				$skin     = new \WP_Ajax_Upgrader_Skin();
				$upgrader = new \Plugin_Upgrader( $skin );
				$result   = $upgrader->install( $download_link );

				if ( is_wp_error( $result ) ) {
					$errors[] = $plugin;
					continue;
				}
			}

			// Activate the plugin
			$activation_result = activate_plugin( $file );
			if ( is_wp_error( $activation_result ) ) {
				$errors[] = $plugin;
				continue;
			}

			$installed[] = $plugin;
		}

		return rest_ensure_response( array(
			'success'   => true,
			'installed' => $installed,
			'errors'    => $errors
		) );
	}

	/**
	 * Reset all template and template part customizations.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public function reset_templates() {
		$result = Helper::reset_templates();

		return new \WP_REST_Response( $result, $result['success'] ? 200 : 500 );
	}

	/**
	 * Reset global styles.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response Response object.
	 */
	public function reset_global_styles() {
		$result = Helper::reset_global_styles();

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => $result->get_error_message()
			) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Removes color values from global styles
	 *
	 * @return \WP_REST_Response
	 */
	public function remove_color_values( $request ) {
		$force  = ! empty( $request->get_param( 'force' ) );
		$result = Helper::remove_color_values( $force );

		return rest_ensure_response( $result );
	}

	/**
	 * Clear stored credentials.
	 *
	 *
	 * @return string
	 */
	public function clear_credentials() {
		$options = get_option( 'ollie' );

		// Unset credentials.
		if ( isset( $options['login'] ) && $options['login'] ) {
			// Remove login.
			unset( $options['login'] );

			// Resave options
			update_option( 'ollie', $options );

			return json_encode( [ "status" => 200, "message" => "Ok" ] );
		}

		return json_encode( [ "status" => 400, "message" => "No data received." ] );
	}

	/**
	 * Serve the Ollie skill files as a zip download.
	 *
	 * Generates a zip on demand containing all modular skill files
	 * with their directory structure preserved.
	 *
	 * @return void|\WP_REST_Response Sends zip file directly, then exits.
	 */
	public function download_skill() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'ZipArchive is not available on this server.', 'ollie-pro' ) ),
				500
			);
		}

		$skills_dir = OLPO_PATH . '/inc/abilities/skills';
		$files      = array(
			'SKILL.md',
			'ollie-design-system.md',
			'reference/ABILITIES.md',
			'reference/TOKENS.md',
			'reference/MARKUP.md',
			'design/DESIGN.md',
			'design/ARCHETYPES.md',
			'design/PRESETS.md',
			'design/RUBRIC.md',
		);

		// Verify all files exist before creating the zip.
		foreach ( $files as $relative_path ) {
			if ( ! file_exists( $skills_dir . '/' . $relative_path ) ) {
				return new \WP_REST_Response(
					array(
						/* translators: %s: missing file path */
						'message' => sprintf( __( 'Skill file not found: %s', 'ollie-pro' ), $relative_path ),
					),
					404
				);
			}
		}

		$tmp_file = tempnam( sys_get_temp_dir(), 'ollie-skill' );
		$zip      = new \ZipArchive();

		if ( true !== $zip->open( $tmp_file, \ZipArchive::OVERWRITE ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Failed to create zip file.', 'ollie-pro' ) ),
				500
			);
		}

		foreach ( $files as $relative_path ) {
			$zip->addFile( $skills_dir . '/' . $relative_path, 'ollie-skill/' . $relative_path );
		}

		$zip->close();

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="ollie-skill.zip"' );
		header( 'Content-Length: ' . filesize( $tmp_file ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		readfile( $tmp_file );
		unlink( $tmp_file );
		exit;
	}

	/**
	 * Delete partner txt file.
	 *
	 * @return string
	 */
	public function delete_partner_file() {
		$partner_file = OLPO_PATH . '/partner.txt';

		if ( file_exists( $partner_file ) ) {
			if ( unlink( $partner_file ) ) {
				return json_encode( [ "status" => 200, "message" => "Partner file deleted successfully." ] );
			}
		}

		return json_encode( [ "status" => 200, "message" => "Partner file does not exist or was already deleted." ] );
	}

	/**
	 * Get the latest changelog entry from readme.txt
	 *
	 * @param string $readme_path Path to readme.txt file
	 *
	 * @return array Array containing version and changelog items
	 */
	private function get_latest_changelog_entry( $readme_path ) {
		if ( ! file_exists( $readme_path ) ) {
			return array(
				'version' => '',
				'items'   => array()
			);
		}

		$readme_content = file_get_contents( $readme_path );

		// Find the changelog section
		preg_match( '/== Changelog ==(.*?)(?:==|$)/s', $readme_content, $changelog_matches );

		if ( empty( $changelog_matches[1] ) ) {
			return array(
				'version' => '',
				'items'   => array()
			);
		}

		$changelog = trim( $changelog_matches[1] );

		// Extract the first version entry
		preg_match( '/= ([\d.]+)[^=]*?=(.*?)(?:=|$)/s', $changelog, $version_matches );

		if ( empty( $version_matches[1] ) || empty( $version_matches[2] ) ) {
			return array(
				'version' => '',
				'items'   => array()
			);
		}

		$version = trim( $version_matches[1] );
		$items_text = trim( $version_matches[2] );

		// Extract bullet points
		preg_match_all( '/^\* (.+)$/m', $items_text, $items_matches );

		$items = array();
		if ( ! empty( $items_matches[1] ) ) {
			$items = array_map( 'trim', $items_matches[1] );
		}

		return array(
			'version' => $version,
			'items'   => $items
		);
	}

}
