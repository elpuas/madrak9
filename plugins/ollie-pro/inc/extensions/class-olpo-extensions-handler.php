<?php

namespace olpo;

class Extensions_Handler {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Extensions_Handler.
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
	 * Setting up scripts
	 *
	 * @return void
	 */
	public function __construct() {
		$this->load_extensions();

		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 99 );
		add_action( 'init', array( $this, 'install_extension_plugins' ) );
	}

	// Install extension plugin if the extension is enabled; ensure this only runs once via a flag in the 'ollie' option.
	public function install_extension_plugins() {
		// Capability check.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Fetch plugin options and check run-once flag.
		$options = (array) get_option( 'ollie', array() );
		$already_ran = ! empty( $options['extension_plugins_installed'] );
		if ( $already_ran ) {
			return; // We already ran this once; nothing to do.
		}

		$plugin_file = 'ollie-menu-designer/ollie-menu-designer.php';
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

		$extension_enabled = $this->is_extension_enabled( 'ollie-menu-designer' );

		// If extension is disabled, do nothing (and do not set the flag yet).
		if ( ! $extension_enabled ) {
			return;
		}

		// If the plugin is not installed, install it and then activate it.
		if ( ! file_exists( $plugin_path ) ) {
			require_once OLPO_PATH . '/inc/extensions/class-olpo-plugin-installer.php';
			$installer = \olpo\Plugin_Installer::get_instance();
			$result    = $installer->install_package_from_wp_org( 'ollie-menu-designer' );
			// If installation failed, bail quietly without setting the flag so we can retry later.
			if ( ! is_array( $result ) || ( isset( $result['status'] ) && 'success' !== $result['status'] ) ) {
				return;
			}
			// After successful install, attempt to activate the plugin.
			if ( file_exists( $plugin_path ) ) {
				if ( ! function_exists( 'activate_plugin' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				activate_plugin( $plugin_file );
			}
		}

		// Mark as completed so this routine runs only once.
		$options['extension_plugins_installed'] = true;
		update_option( 'ollie', $options );
	}


	/**
	 * Return normalized enabled extensions using DB value merged with defaults.
	 *
	 * @return array slug => bool
	 */
	private function get_enabled_extensions() {
		$options = (array) get_option( 'ollie', array() );

		$defaults = array(
			'ollie-menu-designer'       => true,
			'animation-designer'        => true,
			'hover-colors'              => true,
			'keyboard-shortcuts'        => true,
			'advanced-group'            => true,
			'advanced-columns'          => true,
			'advanced-paragraph'        => true,
			'button-icons'              => true,
			'advanced-grid'             => true,
			'class-manager'             => false,
			'cover-expand'              => true,
			'cover-designer'            => true,
			'smart-sync'                => true,
			'product-template-patterns' => true,
			'pattern-library'           => true,
			'text-wrap'                 => true,
			'video-modal'               => true,
			'icon-block-ollie'          => true,
			'responsive-controls'             => true,
			'abilities'                 => false,
			'ai-content'                => true,
			'carousel'                  => true,
		);

		$enabled = $defaults;
		if ( isset( $options['extensions'] ) && is_array( $options['extensions'] ) ) {
			foreach ( $options['extensions'] as $k => $v ) {
				$enabled[ sanitize_key( $k ) ] = (bool) $v;
			}
		}

		return $enabled;
	}

	/**
	 * Helper to check if a specific extension is enabled.
	 */
	private function is_extension_enabled( $slug ) {
		$enabled = $this->get_enabled_extensions();

		return isset( $enabled[ $slug ] ) ? (bool) $enabled[ $slug ] : false;
	}

	/**
	 * Static helper to check if a specific extension is enabled.
	 * This can be called from outside the class.
	 *
	 * @param string $slug The extension slug to check.
	 * @return bool Whether the extension is enabled.
	 */
	public static function is_extension_enabled_static( $slug ) {
		$options = (array) get_option( 'ollie', array() );

		$defaults = array(
			'ollie-menu-designer'       => true,
			'animation-designer'        => true,
			'hover-colors'              => true,
			'keyboard-shortcuts'        => true,
			'advanced-group'            => true,
			'advanced-columns'          => true,
			'advanced-paragraph'        => true,
			'button-icons'              => true,
			'advanced-grid'             => true,
			'class-manager'             => false,
			'cover-expand'              => true,
			'cover-designer'            => true,
			'smart-sync'                => true,
			'product-template-patterns' => true,
			'pattern-library'           => true,
			'text-wrap'                 => true,
			'video-modal'               => true,
			'icon-block-ollie'          => true,
			'abilities'                 => false,
			'ai-content'                => true,
			'carousel'                  => true,
		);

		$enabled = $defaults;
		if ( isset( $options['extensions'] ) && is_array( $options['extensions'] ) ) {
			foreach ( $options['extensions'] as $k => $v ) {
				$enabled[ sanitize_key( $k ) ] = (bool) $v;
			}
		}

		return isset( $enabled[ $slug ] ) ? (bool) $enabled[ $slug ] : false;
	}

	/**
	 * Load PHP for enabled extensions only.
	 */
	public function load_extensions() {
		$enabled = $this->get_enabled_extensions();

		// Map settings slugs to their PHP files.
		$map = array(
			'hover-colors'         => OLPO_PATH . '/inc/extensions/loader/hover-color/hover-color.php',
			'advanced-columns'     => OLPO_PATH . '/inc/extensions/loader/columns/columns.php',
			'advanced-group'       => OLPO_PATH . '/inc/extensions/loader/advanced-group/advanced-group.php',
			'button-icons'         => OLPO_PATH . '/inc/extensions/loader/button-icons/button-icons.php',
			'advanced-paragraph'   => OLPO_PATH . '/inc/extensions/loader/paragraph-hover-decoration/paragraph-hover-decoration.php',
			'animation-designer'   => OLPO_PATH . '/inc/extensions/loader/animation/animation.php',
			'advanced-grid'        => OLPO_PATH . '/inc/extensions/loader/advanced-grid/advanced-grid.php',
			'class-manager'        => OLPO_PATH . '/inc/extensions/loader/class-manager/class-manager.php',
			'cover-expand'         => OLPO_PATH . '/inc/extensions/loader/cover-expand/cover-expand.php',
			'cover-designer'       => OLPO_PATH . '/inc/extensions/loader/cover-designer/cover-designer.php',
			'video-modal'          => OLPO_PATH . '/inc/extensions/loader/video-modal/video-modal.php',
			'text-wrap'            => OLPO_PATH . '/inc/extensions/loader/text-wrap/text-wrap.php',
			'responsive-controls'        => OLPO_PATH . '/inc/extensions/loader/responsive-controls/responsive-controls.php',
			'ai-content'           => OLPO_PATH . '/inc/extensions/loader/ai-content/ai-content.php',
			'carousel'             => OLPO_PATH . '/inc/carousel/carousel.php',
			// Same toggle as the Icon Block editor integration: one
			// setting governs the Ollie icon set everywhere.
			'icon-block-ollie'     => OLPO_PATH . '/inc/extensions/loader/core-icons/core-icons.php',
			// 'keyboard-shortcuts' and 'menu-designer' currently do not have PHP loaders here.
		);

		foreach ( $map as $slug => $file ) {
			if ( ! empty( $enabled[ $slug ] ) && file_exists( $file ) ) {
				require_once $file;
			}
		}

		// Always load cover-term-image (not toggleable, enhances Terms Query blocks automatically)
		require_once OLPO_PATH . '/inc/extensions/loader/cover-term-image/cover-term-image.php';

		// Always load scroll-resize (not toggleable, utility class-based trigger)
		require_once OLPO_PATH . '/inc/extensions/loader/scroll-resize/scroll-resize.php';

		// Always load pattern-editing (not toggleable, sets the WP 7.0 content-only default; user can toggle per-user in editor preferences)
		require_once OLPO_PATH . '/inc/extensions/loader/pattern-editing/pattern-editing.php';

		// Always load accordion-item padding support (Core omits it; matches accordion-panel).
		require_once OLPO_PATH . '/inc/extensions/loader/accordion-item-padding/accordion-item-padding.php';

		// Always load Writing Prompt ability — block lives at plugin root (not extension-toggled).
		require_once OLPO_PATH . '/inc/extensions/loader/writing-prompt/writing-prompt.php';
	}


	/**
	 * Get plugin asset file data.
	 *
	 * @return array|false Asset file data or false on failure.
	 * @since 0.1.0
	 */
	public function get_asset_file() {
		static $asset_file = null;

		if ( null === $asset_file ) {
			$asset_file_path = OLPO_PATH . '/inc/extensions/build/index.asset.php';

			if ( file_exists( $asset_file_path ) ) {
				$asset_file = include $asset_file_path;
			} else {
				$asset_file = false;
			}
		}

		return $asset_file;
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_block_editor_assets() {
		// Only load in admin area (editor), not on frontend
		if ( ! is_admin() ) {
			return;
		}

		// Skip if no extensions are enabled.
		$enabled = $this->get_enabled_extensions();
		if ( ! in_array( true, $enabled, true ) ) {
			return;
		}

		$asset_file = $this->get_asset_file();

		if ( ! $asset_file ) {
			return;
		}

		// Enqueue CodeMirror for CSS editing in class manager
		wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

		wp_enqueue_script(
			'ollie-extensions-editor',
			OLPO_URL . '/inc/extensions/build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		// Pass enabled extensions and assets to JavaScript
		wp_localize_script(
			'ollie-extensions-editor',
			'ollieProExtensions',
			array(
				'enabled'         => $enabled,
				'buildUrl'        => OLPO_URL . '/inc/extensions/build/',
				'termImagePreview' => OLPO_URL . '/inc/extensions/build/images/preview.webp',
				'wp7'             => version_compare( get_bloginfo( 'version' ), '7.0-alpha', '>=' ),
				// WP 7.1 relocated the inspector's text/background color
				// controls into the Typography and Background panels
				// (Gutenberg #77279); the hover color controls follow.
				'wp71'            => version_compare( get_bloginfo( 'version' ), '7.1-alpha', '>=' ),
				'coreResponsiveStyles' => method_exists( 'WP_Theme_JSON', 'get_viewport_media_queries' ),
			)
		);


		// Enqueue frontend styles (includes animation styles needed for preview)
		if ( file_exists( OLPO_PATH . '/inc/extensions/build/style-index.css' ) ) {
			wp_enqueue_style(
				'ollie-extensions-style',
				OLPO_URL . '/inc/extensions/build/style-index.css',
				array(),
				$asset_file['version']
			);
		}

		// Enqueue editor-only styles
		if ( file_exists( OLPO_PATH . '/inc/extensions/build/editor.css' ) ) {
			wp_enqueue_style(
				'ollie-extensions-editor',
				OLPO_URL . '/inc/extensions/build/editor.css',
				array(),
				$asset_file['version']
			);
		}

		wp_set_script_translations(
			'ollie-extensions-editor',
			'ollie-extensions',
			OLPO_PATH . '/inc/extensions/languages'
		);
	}

	/**
	 * Enqueue frontend styles.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_frontend_assets() {
		// Only load on frontend
		if ( is_admin() ) {
			return;
		}

		// Skip if no extensions are enabled.
		$enabled = $this->get_enabled_extensions();
		if ( ! in_array( true, $enabled, true ) ) {
			return;
		}

		$asset_file = $this->get_asset_file();

		if ( ! $asset_file ) {
			return;
		}

		// Load frontend styles
		if ( file_exists( OLPO_PATH . '/inc/extensions/build/style-index.css' ) ) {
			wp_enqueue_style(
				'ollie-extensions-frontend',
				OLPO_URL . '/inc/extensions/build/style-index.css',
				array(),
				$asset_file['version']
			);
		}
	}


	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_frontend_scripts() {
		// Only enqueue on the frontend, not in the editor.
		if ( is_admin() ) {
			return;
		}

		// Only when advanced-group extension is enabled.
		if ( ! $this->is_extension_enabled( 'advanced-group' ) ) {
			return;
		}

		// Always load the script when extension is enabled. The script is small (~5KB)
		// and will simply do nothing if there are no linked/sticky elements on the page.
		// This avoids detection issues with has_block() which doesn't check templates.
		$frontend_script = file_exists( OLPO_PATH . '/inc/extensions/build/advanced-group-frontend.js' )
			? 'build/advanced-group-frontend.js'
			: 'src/controls/advanced-group/frontend.js';

		wp_enqueue_script(
			'ollie-extensions-advanced-group',
			OLPO_URL . '/inc/extensions/' . $frontend_script,
			array(),
			OLPO_VERSION,
			true
		);
	}
}

