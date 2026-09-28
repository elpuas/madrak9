<?php
/**
 * Plugin Name:       Shop Commands
 * Description:       Provides operational WordPress Abilities for WooCommerce Shop Managers.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            MadraK9
 * Text Domain:       shop-commands
 *
 * @package ShopCommands
 */

declare(strict_types=1);

namespace ShopCommands;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/class-orders-needing-attention.php';
require_once __DIR__ . '/src/class-command-palette.php';

add_action( 'wp_abilities_api_categories_init', [ Orders_Needing_Attention::class, 'register_category' ] );
add_action( 'wp_abilities_api_init', [ Orders_Needing_Attention::class, 'register_ability' ] );
add_action( 'admin_enqueue_scripts', [ Command_Palette::class, 'enqueue_assets' ] );
