<?php
/**
 * Command Palette integration.
 *
 * @package ShopCommands
 */

declare(strict_types=1);

namespace ShopCommands;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the reusable Command Palette execution and result modal layer.
 */
final class Command_Palette {
	/**
	 * Enqueue the Command Palette assets for authorized users.
	 */
	public static function enqueue_assets(): void {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability for Shop Managers.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$plugin_file = dirname( __DIR__ ) . '/shop-commands.php';
		$script_path = dirname( __DIR__ ) . '/assets/command-palette.js';
		$style_path  = dirname( __DIR__ ) . '/assets/command-palette.css';

		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-commands' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_style( 'wp-commands' );
		wp_enqueue_style( 'wp-components' );

		wp_enqueue_script_module(
			'shop-commands-command-palette',
			plugins_url( 'assets/command-palette.js', $plugin_file ),
			[ '@wordpress/abilities', '@wordpress/core-abilities' ],
			(string) filemtime( $script_path )
		);

		if ( function_exists( 'wp_set_script_module_translations' ) ) {
			wp_set_script_module_translations( 'shop-commands-command-palette', 'shop-commands' );
		}

		wp_enqueue_style(
			'shop-commands-command-palette',
			plugins_url( 'assets/command-palette.css', $plugin_file ),
			[],
			(string) filemtime( $style_path )
		);
	}
}
