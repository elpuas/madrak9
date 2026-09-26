<?php

namespace olpo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Network_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Network_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Network_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'network_admin_menu', array( $this, 'add_network_menu' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'activated_plugin', array( $this, 'redirect_on_activation' ), 10, 2 );
	}

	/**
	 * Add network admin menu page.
	 *
	 * @return void
	 */
	public function add_network_menu() {
		$page_hook = add_submenu_page(
			'settings.php',
			esc_html__( 'Ollie Network Settings', 'ollie-pro' ),
			esc_html__( 'Ollie', 'ollie-pro' ),
			'manage_network_options',
			'ollie-network',
			array( $this, 'render_page' )
		);

		add_action( "admin_print_scripts-{$page_hook}", array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Render the network admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		?>
		<div id="ollie-network-admin"></div>
		<?php
	}

	/**
	 * Redirect to Ollie Network page on plugin activation.
	 *
	 * @param string $plugin Plugin basename.
	 * @param bool   $network_wide Whether the plugin was activated network-wide.
	 *
	 * @return void
	 */
	public function redirect_on_activation( $plugin, $network_wide ) {
		if ( ! $network_wide ) {
			return;
		}

		if ( 'ollie-pro/ollie-pro.php' !== $plugin ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		wp_safe_redirect( network_admin_url( 'settings.php?page=ollie-network' ) );
		exit;
	}

	/**
	 * Enqueue scripts and styles for the network admin page.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$asset_file = OLPO_PATH . '/inc/network-admin/build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'ollie-network-admin',
			OLPO_URL . '/inc/network-admin/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'ollie-network-admin-style',
			OLPO_URL . '/inc/network-admin/build/style-index.css',
			array( 'wp-components' ),
			$asset['version']
		);

		// Get stored network credentials.
		$credentials = self::get_network_credentials();

		wp_localize_script( 'ollie-network-admin', 'ollie_network', array(
			'rest_url'   => esc_url_raw( rest_url( 'ollie/v1/network/' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'email'      => $credentials['email'],
			'password'   => $credentials['password'],
			'version'    => OLPO_VERSION,
			'network_admin_url' => esc_url( network_admin_url() ),
		) );
	}

	/**
	 * Register REST API routes for network admin.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route( 'ollie/v1', '/network/sites', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_sites' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_network_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/network/save-credentials', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_credentials' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_network_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/network/clear-credentials', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'clear_credentials' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_network_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/network/activate-sites', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'activate_sites' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_network_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/network/deactivate-sites', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'deactivate_sites' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_network_options' );
			},
		) );

		register_rest_route( 'ollie/v1', '/network/install-theme', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'install_theme' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_network_options' );
			},
		) );
	}

	/**
	 * Get all sites in the network with their Ollie activation status.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_sites() {
		$sites  = get_sites( array( 'number' => 0 ) );
		$result = array();

		$credentials = self::get_network_credentials();
		$is_network_activated = is_plugin_active_for_network( 'ollie-pro/ollie-pro.php' );

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );

			$site_options  = get_option( 'ollie', array() );
			$has_login     = $is_network_activated || ! empty( $site_options['login'] );
			$theme         = wp_get_theme();
			$is_ollie      = olpo_is_theme_compatible( $theme );

			$result[] = array(
				'blog_id'    => (int) $site->blog_id,
				'name'       => get_bloginfo( 'name' ),
				'url'        => get_site_url(),
				'domain'     => $site->domain,
				'path'       => $site->path,
				'registered' => $site->registered,
				'has_login'  => $has_login,
				'is_ollie'   => $is_ollie,
				'admin_url'  => get_admin_url( $site->blog_id ),
			);

			restore_current_blog();
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Save network credentials and optionally propagate to subsites.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function save_credentials( $request ) {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$password = $request->get_param( 'password' );

		if ( empty( $email ) || empty( $password ) ) {
			return new \WP_REST_Response( array(
				'status'  => 400,
				'message' => __( 'Email and password are required.', 'ollie-pro' ),
			), 400 );
		}

		// Store credentials at network level.
		$encoded = base64_encode( $email . ':' . $password );
		update_site_option( 'ollie_network_login', $encoded );

		// Store keep_logged_in preference.
		$keep_logged_in = (bool) $request->get_param( 'keep_logged_in' );
		update_site_option( 'ollie_network_keep_logged_in', $keep_logged_in ? '1' : '0' );

		return new \WP_REST_Response( array(
			'status'  => 200,
			'message' => __( 'Credentials saved.', 'ollie-pro' ),
		), 200 );
	}

	/**
	 * Clear network-level credentials.
	 *
	 * @return \WP_REST_Response
	 */
	public function clear_credentials() {
		delete_site_option( 'ollie_network_login' );
		delete_site_option( 'ollie_network_keep_logged_in' );

		return new \WP_REST_Response( array(
			'status'  => 200,
			'message' => __( 'Credentials cleared.', 'ollie-pro' ),
		), 200 );
	}

	/**
	 * Activate selected sites by storing credentials in each subsite.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function activate_sites( $request ) {
		$blog_ids = $request->get_param( 'blog_ids' );

		if ( empty( $blog_ids ) || ! is_array( $blog_ids ) ) {
			return new \WP_REST_Response( array(
				'status'  => 400,
				'message' => __( 'No sites selected.', 'ollie-pro' ),
			), 400 );
		}

		$credentials = self::get_network_credentials();

		if ( empty( $credentials['email'] ) || empty( $credentials['password'] ) ) {
			return new \WP_REST_Response( array(
				'status'  => 400,
				'message' => __( 'Please log in first.', 'ollie-pro' ),
			), 400 );
		}

		$encoded   = base64_encode( $credentials['email'] . ':' . $credentials['password'] );
		$activated = array();

		foreach ( $blog_ids as $blog_id ) {
			$blog_id = absint( $blog_id );

			switch_to_blog( $blog_id );

			$options          = (array) get_option( 'ollie', array() );
			$options['login'] = $encoded;
			update_option( 'ollie', $options );

			$activated[] = $blog_id;

			restore_current_blog();
		}

		return new \WP_REST_Response( array(
			'status'    => 200,
			'message'   => sprintf(
				/* translators: %d: Number of activated sites */
				__( '%d site(s) activated.', 'ollie-pro' ),
				count( $activated )
			),
			'activated' => $activated,
		), 200 );
	}

	/**
	 * Deactivate selected sites by removing credentials from each subsite.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function deactivate_sites( $request ) {
		$blog_ids = $request->get_param( 'blog_ids' );

		if ( empty( $blog_ids ) || ! is_array( $blog_ids ) ) {
			return new \WP_REST_Response( array(
				'status'  => 400,
				'message' => __( 'No sites selected.', 'ollie-pro' ),
			), 400 );
		}

		$deactivated = array();

		foreach ( $blog_ids as $blog_id ) {
			$blog_id = absint( $blog_id );

			switch_to_blog( $blog_id );

			$options = (array) get_option( 'ollie', array() );

			if ( isset( $options['login'] ) ) {
				unset( $options['login'] );
				update_option( 'ollie', $options );
			}

			$deactivated[] = $blog_id;

			restore_current_blog();
		}

		return new \WP_REST_Response( array(
			'status'      => 200,
			'message'     => sprintf(
				/* translators: %d: Number of deactivated sites */
				__( '%d site(s) deactivated.', 'ollie-pro' ),
				count( $deactivated )
			),
			'deactivated' => $deactivated,
		), 200 );
	}

	/**
	 * Install and activate the Ollie theme on selected subsites.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function install_theme( $request ) {
		$blog_ids = $request->get_param( 'blog_ids' );

		if ( empty( $blog_ids ) || ! is_array( $blog_ids ) ) {
			return new \WP_REST_Response( array(
				'status'  => 400,
				'message' => __( 'No sites selected.', 'ollie-pro' ),
			), 400 );
		}

		// Ensure required files are loaded for theme operations in REST context.
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		// Check if the Ollie theme is installed network-wide.
		$theme = wp_get_theme( 'ollie' );

		if ( ! $theme->exists() ) {
			// Install the Ollie theme from WordPress.org.
			$skin     = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new \Theme_Upgrader( $skin );
			$result   = $upgrader->install( 'https://downloads.wordpress.org/theme/ollie.zip' );

			if ( is_wp_error( $result ) ) {
				return new \WP_REST_Response( array(
					'status'  => 500,
					'message' => $result->get_error_message(),
				), 500 );
			}

			if ( ! $result ) {
				$errors = $skin->get_errors();
				$msg    = is_wp_error( $errors ) ? $errors->get_error_message() : __( 'Failed to install the Ollie theme.', 'ollie-pro' );

				return new \WP_REST_Response( array(
					'status'  => 500,
					'message' => $msg,
				), 500 );
			}
		}

		// Make sure the theme is network-enabled so subsites can use it.
		$allowed_themes = get_site_option( 'allowedthemes', array() );
		if ( ! isset( $allowed_themes['ollie'] ) ) {
			$allowed_themes['ollie'] = true;
			update_site_option( 'allowedthemes', $allowed_themes );
		}

		$activated = array();
		$errors    = array();

		foreach ( $blog_ids as $blog_id ) {
			$blog_id = absint( $blog_id );

			try {
				switch_to_blog( $blog_id );
				switch_theme( 'ollie' );
				$activated[] = $blog_id;
			} catch ( \Exception $e ) {
				$errors[] = sprintf( 'Site %d: %s', $blog_id, $e->getMessage() );
			} finally {
				restore_current_blog();
			}
		}

		return new \WP_REST_Response( array(
			'status'    => 200,
			'message'   => sprintf(
				/* translators: %d: Number of sites */
				__( 'Ollie theme activated on %d site(s).', 'ollie-pro' ),
				count( $activated )
			),
			'activated' => $activated,
			'errors'    => $errors,
		), 200 );
	}

	/**
	 * Get stored network-level credentials.
	 *
	 * @return array
	 */
	public static function get_network_credentials() {
		$email    = defined( 'OLLIE_EMAIL' ) ? OLLIE_EMAIL : '';
		$password = defined( 'OLLIE_PASSWORD' ) ? OLLIE_PASSWORD : '';

		$encoded = get_site_option( 'ollie_network_login', '' );

		if ( ! empty( $encoded ) ) {
			$decoded  = base64_decode( $encoded );
			$parts    = explode( ':', $decoded, 2 );

			if ( count( $parts ) === 2 ) {
				$email    = $parts[0];
				$password = $parts[1];
			}
		}

		return array(
			'email'    => $email,
			'password' => $password,
		);
	}
}
