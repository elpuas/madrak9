<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Plugin Name:       Ollie Pro
 * Plugin URI:        https://olliewp.com
 * Description:       Adds the Ollie Pro pattern library and Ollie Pro Dashboard to the Ollie block theme.
 * Version:           2.8.3
 * Author:            OllieWP Team
 * Author URI:        https://olliewp.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ollie-pro
 * Domain Path:       /languages
 *
 */

define( 'OLPO_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'OLPO_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'OLPO_VERSION', '2.8.3' );

// Plugin updater.
require OLPO_PATH . '/inc/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
	'https://vttiicmlzxzxrcyyewfn.supabase.co/storage/v1/object/public/ollie/releases/plugin.json',
	__FILE__,
	'ollie-pro'
);

// Load network admin for multisite.
if ( ! function_exists( 'olpo_load_network_admin' ) ) {
	add_action( 'plugins_loaded', 'olpo_load_network_admin' );

	/**
	 * Load network admin page on multisite.
	 *
	 * @return void
	 */
	function olpo_load_network_admin() {
		if ( is_multisite() ) {
			require_once( OLPO_PATH . '/inc/class-olpo-network-admin.php' );
			olpo\Network_Admin::get_instance();
		}
	}
}

if ( ! function_exists( 'olpo_is_theme_compatible' ) ) {
	/**
	 * Determine whether a theme is compatible with Ollie Pro.
	 *
	 * The official Ollie theme and custom Ollie-based parent themes whose
	 * template slug starts with "ollie-" are compatible by default.
	 *
	 * @param WP_Theme|null $theme Theme instance. Defaults to the active theme.
	 * @return bool Whether the theme is compatible with Ollie Pro.
	 */
	function olpo_is_theme_compatible( $theme = null ) {
		$theme = $theme ?: wp_get_theme();

		$template      = '';
		$is_compatible = false;

		if ( $theme && $theme->exists() ) {
			$template      = $theme->get_template();
			$is_compatible = 'ollie' === $template || 0 === strpos( $template, 'ollie-' );
		}

		/**
		 * Filters whether the active theme is compatible with Ollie Pro.
		 *
		 * Custom themes must provide the Ollie design system, including the
		 * theme.json settings and style slugs used by Ollie Pro features.
		 *
		 * @param bool          $is_compatible Whether the theme is compatible.
		 * @param WP_Theme|null $theme         Theme instance.
		 * @param string        $template      Parent theme template slug.
		 */
		return (bool) apply_filters( 'ollie_pro_is_theme_compatible', $is_compatible, $theme, $template );
	}
}

// run plugin.
if ( ! function_exists( 'olpo_run_plugin' ) ) {
	add_action( 'after_setup_theme', 'olpo_run_plugin' );

	/**
	 * Run plugin
	 *
	 * @return void
	 */
	function olpo_run_plugin() {
		// Get the current theme.
		$theme = wp_get_theme();

		// Check if the active theme supports the Ollie design system.
		if ( olpo_is_theme_compatible( $theme ) ) {
			// The minimum version applies only to the official Ollie parent theme.
			if ( 'ollie' === $theme->get_template() ) {
				$version = $theme->get( 'Version' );

				if ( $theme->parent() ) {
					$version = $theme->parent()->get( 'Version' );
				}

				// Check theme version.
				if ( version_compare( $version, '1.4.8', '<' ) ) {
					// Add admin notice for outdated theme.
					add_action( 'admin_notices', function () use ( $theme ) {
						/* translators: %s: Link to update Ollie theme */
						$message = sprintf(
							__( 'Ollie Pro 2.0 requires Ollie theme version 1.4.8 or higher. Please %s before continuing.', 'ollie-pro' ),
							'<a href="' . esc_url( admin_url( 'themes.php' ) ) . '">' . __( 'update your theme', 'ollie-pro' ) . '</a>'
						);
						echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
					} );

					return;
				}
			}

			require_once( OLPO_PATH . '/inc/helpers/ai-availability.php' );
			require_once( OLPO_PATH . '/inc/class-olpo-settings.php' );
			require_once( OLPO_PATH . '/inc/class-olpo-helper.php' );
			require_once( OLPO_PATH . '/inc/extensions/class-olpo-extensions-handler.php' );

			olpo\Settings::get_instance();
			olpo\Helper::get_instance();
			olpo\Extensions_Handler::get_instance();

 		// Abilities integration — requires the Abilities extension enabled + WordPress Abilities API.
			if ( olpo\Extensions_Handler::is_extension_enabled_static( 'abilities' ) ) {
				if ( function_exists( 'wp_register_ability' ) || has_action( 'wp_abilities_api_init' ) !== false ) {
 				require_once( OLPO_PATH . '/inc/abilities/class-ollie-abilities.php' );
 				olpo\Abilities\Ollie_Abilities::get_instance();
				}
			}
		} else {
			// If multisite, only show the notice to network admin.
			if ( is_multisite() && function_exists( 'get_sites' ) ) {
				if ( ! is_network_admin() ) {
					// Add admin notice.
					add_action( 'network_admin_notices', function () {
						/* translators: %s: Link to Ollie theme */
						$message = sprintf( __( 'The Ollie Pro plugin needs the free Ollie theme to work. View the theme and install it %s.', 'ollie-pro' ), '<a href=' . esc_url( network_admin_url( 'theme-install.php?search=ollie' ) ) . '>by clicking here</a>' );
						echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
					} );
				}
			} else {
				// Add admin notice.
				add_action( 'admin_notices', function () {
					/* translators: %s: Link to Ollie theme */
					$message = sprintf( __( 'The Ollie Pro plugin needs the free Ollie theme to work. View the theme and install it %s.', 'ollie-pro' ), '<a href=' . esc_url( admin_url( 'theme-install.php?search=ollie' ) ) . '>by clicking here</a>' );
					echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
				} );
			}
		}
	}

	add_action( 'init', 'olpo_register_pattern_block' );

	/**
	 * Register the pattern block.
	 *
	 * @return void
	 */
	function olpo_register_pattern_block() {
		// Only load in the admin.
		if ( ! is_admin() || is_customize_preview() ) {
			return;
		}

		// Check if pattern library extension is enabled.
		require_once( OLPO_PATH . '/inc/extensions/class-olpo-extensions-handler.php' );
		if ( ! olpo\Extensions_Handler::is_extension_enabled_static( 'pattern-library' ) ) {
			return;
		}

		// Register scripts.
		$olpo_asset_file = include( plugin_dir_path( __FILE__ ) . 'build/pattern-block/index.asset.php' );
		wp_register_script( 'ollie-pattern-block', plugins_url( 'build/pattern-block/index.js', __FILE__ ), $olpo_asset_file['dependencies'], $olpo_asset_file['version'], true );

		// Register block.
		register_block_type( __DIR__ . '/build/pattern-block' );
		wp_enqueue_script( 'ollie-pattern-block', plugins_url( 'build/pattern-block/index.js', __FILE__ ), $olpo_asset_file['dependencies'], $olpo_asset_file['version'], true );
		wp_enqueue_style( 'ollie-pattern-block-style', plugins_url( 'build/pattern-block/index.css', __FILE__ ), array(), $olpo_asset_file['version'] );

		// Register pattern prompt block.
		register_block_type( __DIR__ . '/build/pattern-prompt' );

		require_once( OLPO_PATH . '/inc/class-olpo-helper.php' );

		$args = array(
			'version'             => OLPO_VERSION,
			'downloaded_patterns' => olpo\Helper::get_downloaded_patterns(),
			'favorite_patterns'   => olpo\Helper::get_favorite_patterns(),
		);

		// Maybe auto-login.
		$login = olpo\Helper::get_credentials();

		if ( ! empty( $login ) ) {
			$args['email']    = $login['email'];
			$args['password'] = $login['password'];
		}

		wp_localize_script( 'ollie-pattern-block', 'ollie_pattern_options', $args );

		// Make the blocks translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ollie-pattern-block', 'ollie-pro', OLPO_PATH . '/languages' );
		}
	}

	/**
	 * Register the Writing Prompt block on both admin and frontend.
	 */
	function olpo_register_writing_prompt_block() {
		register_block_type( __DIR__ . '/build/writing-prompt' );
	}
	add_action( 'init', 'olpo_register_writing_prompt_block' );
}
