<?php
/**
 * Orders needing attention ability.
 *
 * @package ShopCommands
 */

declare(strict_types=1);

namespace ShopCommands;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and executes the orders needing attention ability.
 */
final class Orders_Needing_Attention {
	/**
	 * Register the Shop Commands ability category.
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			'shop-commands',
			[
				'label'       => __( 'Shop Commands', 'shop-commands' ),
				'description' => __( 'Operational abilities for WooCommerce Shop Managers.', 'shop-commands' ),
			]
		);
	}

	/**
	 * Register the orders needing attention ability.
	 */
	public static function register_ability(): void {
		wp_register_ability(
			'shop-commands/orders-needing-attention',
			[
				'label'               => __( 'Orders needing attention', 'shop-commands' ),
				'description'         => __( 'Return a summary of WooCommerce orders that may need attention.', 'shop-commands' ),
				'category'            => 'shop-commands',
				'output_schema'       => self::get_output_schema(),
				'execute_callback'    => [ self::class, 'execute' ],
				'permission_callback' => [ self::class, 'check_permission' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * Check whether the current user may view operational order information.
	 */
	public static function check_permission(): bool {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability for Shop Managers.
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Return counts for order statuses that may need attention.
	 *
	 * @return array<string, int>|\WP_Error Order counts or a dependency error.
	 */
	public static function execute() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return new \WP_Error(
				'shop_commands_not_initialized',
				__( 'WooCommerce is required to retrieve order information.', 'shop-commands' )
			);
		}

		return [
			'processing' => self::get_order_count( 'processing' ),
			'on_hold'    => self::get_order_count( 'on-hold' ),
			'failed'     => self::get_order_count( 'failed' ),
		];
	}

	/**
	 * Count orders with a specific status without loading full order objects.
	 *
	 * @param string $status WooCommerce order status without the wc- prefix.
	 * @return int Order count.
	 */
	private static function get_order_count( string $status ): int {
		$result = wc_get_orders(
			[
				'status'   => $status,
				'limit'    => 1,
				'paginate' => true,
				'return'   => 'ids',
			]
		);

		return (int) $result->total;
	}

	/**
	 * Get the ability output schema.
	 *
	 * @return array<string, mixed> JSON Schema definition.
	 */
	private static function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'title'                => __( 'Orders needing attention', 'shop-commands' ),
			'description'          => __( 'Counts of WooCommerce orders in statuses that may require a Shop Manager to take action.', 'shop-commands' ),
			'properties'           => [
				'processing' => [
					'type'        => 'integer',
					'minimum'     => 0,
					'title'       => __( 'Processing', 'shop-commands' ),
					'description' => __( 'Number of orders currently being processed.', 'shop-commands' ),
				],
				'on_hold'    => [
					'type'        => 'integer',
					'minimum'     => 0,
					'title'       => __( 'On hold', 'shop-commands' ),
					'description' => __( 'Number of orders currently on hold.', 'shop-commands' ),
				],
				'failed'     => [
					'type'        => 'integer',
					'minimum'     => 0,
					'title'       => __( 'Failed', 'shop-commands' ),
					'description' => __( 'Number of orders with a failed payment or checkout.', 'shop-commands' ),
				],
			],
			'required'             => [ 'processing', 'on_hold', 'failed' ],
			'additionalProperties' => false,
		];
	}
}
