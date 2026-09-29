<?php
/**
 * Recent orders ability.
 *
 * @package ShopCommands
 */

declare(strict_types=1);

namespace ShopCommands;

defined( 'ABSPATH' ) || exit;

/**
 * Returns a concise summary of the most recently created orders.
 */
final class Recent_Orders {
	/**
	 * Register the Recent orders ability.
	 */
	public static function register_ability(): void {
		wp_register_ability(
			'shop-commands/recent-orders',
			[
				'label'               => __( 'Recent orders', 'shop-commands' ),
				'description'         => __(
					'Return the five most recently created orders, excluding drafts and trash.',
					'shop-commands'
				),
				'category'            => 'shop-commands',
				'output_schema'       => self::get_output_schema(),
				'execute_callback'    => [ self::class, 'execute' ],
				'permission_callback' => [ Orders_Needing_Attention::class, 'check_permission' ],
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
	 * Get the five most recent non-draft orders.
	 *
	 * @return array<string, array<int, array<string, string|float|null>>>|\WP_Error
	 *     Order summaries or dependency error.
	 */
	public static function execute() {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_order_statuses' ) ) {
			return new \WP_Error(
				'shop_commands_not_initialized',
				__( 'WooCommerce is required to retrieve order information.', 'shop-commands' )
			);
		}

		$statuses = array_values( array_diff( array_keys( wc_get_order_statuses() ), [ 'wc-checkout-draft' ] ) );
		if ( [] === $statuses ) {
			return [ 'orders' => [] ];
		}

		$orders = wc_get_orders(
			[
				'type'    => 'shop_order',
				'status'  => $statuses,
				'limit'   => 5,
				'orderby' => 'date',
				'order'   => 'DESC',
			]
		);

		$summaries = [];
		foreach ( $orders as $order ) {
			$created_date = $order->get_date_created();
			$customer     = trim( $order->get_formatted_billing_full_name() );

			$summaries[] = [
				'order_number' => (string) $order->get_order_number(),
				'customer'     => '' !== $customer ? $customer : __( 'Not provided', 'shop-commands' ),
				'status'       => wc_get_order_status_name( $order->get_status() ),
				'total'        => (float) $order->get_total(),
				'created_date' => $created_date
					? wp_date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T',
						$created_date->getTimestamp(),
						wp_timezone()
					)
					: null,
			];
		}

		return [ 'orders' => $summaries ];
	}

	/**
	 * Get the strict order summary output schema.
	 *
	 * @return array<string, mixed> JSON Schema definition.
	 */
	private static function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'title'                => __( 'Recent orders', 'shop-commands' ),
			'description'          => __( 'The five most recently created non-draft orders.', 'shop-commands' ),
			'properties'           => [
				'orders' => [
					'type'        => 'array',
					'maxItems'    => 5,
					'title'       => __( 'Recent orders', 'shop-commands' ),
					'description' => __(
						'Newest orders first; includes cancelled, refunded, and failed orders.',
						'shop-commands'
					),
					'items'       => [
						'type'                 => 'object',
						'properties'           => [
							'order_number' => [
								'type'  => 'string',
								'title' => __( 'Order number', 'shop-commands' ),
							],
							'customer'     => [
								'type'  => 'string',
								'title' => __( 'Customer', 'shop-commands' ),
							],
							'status'       => [
								'type'  => 'string',
								'title' => __( 'Status', 'shop-commands' ),
							],
							'total'        => [
								'type'        => 'number',
								'title'       => __( 'Total', 'shop-commands' ),
								'description' => __(
									'Recorded order total; currency formatting is not applied.',
									'shop-commands'
								),
							],
							'created_date' => [
								'type'        => [ 'string', 'null' ],
								'title'       => __( 'Created', 'shop-commands' ),
								'description' => __( 'Created date in the WordPress site timezone.', 'shop-commands' ),
							],
						],
						'required'             => [ 'order_number', 'customer', 'status', 'total', 'created_date' ],
						'additionalProperties' => false,
					],
				],
			],
			'required'             => [ 'orders' ],
			'additionalProperties' => false,
		];
	}
}
