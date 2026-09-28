<?php
/**
 * Today's orders ability.
 *
 * @package ShopCommands
 */

declare(strict_types=1);

namespace ShopCommands;

defined( 'ABSPATH' ) || exit;

/**
 * Summarizes orders created today in WooCommerce's paid statuses.
 */
final class Todays_Orders {
	/**
	 * Register the Today's orders ability.
	 */
	public static function register_ability(): void {
		wp_register_ability(
			'shop-commands/todays-orders',
			[
				'label'               => __( "Today's orders", 'shop-commands' ),
				'description'         => __(
					'Summarize orders created today in statuses WooCommerce considers paid.',
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
	 * Get today's paid-status order count and recorded order totals.
	 *
	 * @return array<string, int|float>|\WP_Error Order summary or dependency error.
	 */
	public static function execute() {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_is_paid_statuses' ) ) {
			return new \WP_Error(
				'shop_commands_not_initialized',
				__( 'WooCommerce is required to retrieve order information.', 'shop-commands' )
			);
		}

		$statuses = wc_get_is_paid_statuses();
		if ( [] === $statuses ) {
			return self::empty_result();
		}

		$start = current_datetime()->setTime( 0, 0 );
		$end   = $start->modify( '+1 day' );
		$date  = $start->getTimestamp() . '...' . ( $end->getTimestamp() - 1 );

		$orders  = 0;
		$revenue = 0.0;
		$page    = 1;

		do {
			$result = wc_get_orders(
				[
					'type'         => 'shop_order',
					'status'       => $statuses,
					'date_created' => $date,
					'limit'        => 100,
					'page'         => $page,
					'paginate'     => true,
					'orderby'      => 'ID',
					'order'        => 'ASC',
				]
			);

			foreach ( $result->orders as $order ) {
				++$orders;
				$revenue += (float) $order->get_total();
			}

			++$page;
		} while ( $page <= (int) $result->max_num_pages );

		$revenue = round( $revenue, wc_get_price_decimals() );

		return [
			'orders'              => $orders,
			'revenue'             => $revenue,
			'average_order_value' => $orders > 0 ? round( $revenue / $orders, wc_get_price_decimals() ) : 0,
		];
	}

	/**
	 * Return a schema-valid summary when no paid statuses are configured.
	 *
	 * @return array<string, int> Zero summary.
	 */
	private static function empty_result(): array {
		return [
			'orders'              => 0,
			'revenue'             => 0,
			'average_order_value' => 0,
		];
	}

	/**
	 * Get the strict summary output schema.
	 *
	 * @return array<string, mixed> JSON Schema definition.
	 */
	private static function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'title'                => __( "Today's orders", 'shop-commands' ),
			'description'          => __(
				'Summary of orders created on the current site-local date in statuses WooCommerce considers paid.',
				'shop-commands'
			),
			'properties'           => [
				'orders'              => [
					'type'        => 'integer',
					'minimum'     => 0,
					'title'       => __( "Today's orders", 'shop-commands' ),
					'description' => __(
						'Number of orders created today that are currently in a paid status.',
						'shop-commands'
					),
				],
				'revenue'             => [
					'type'        => 'number',
					'minimum'     => 0,
					'title'       => __( 'Revenue', 'shop-commands' ),
					'description' => __(
						'Sum of included order totals, without refund or currency adjustments.',
						'shop-commands'
					),
				],
				'average_order_value' => [
					'type'        => 'number',
					'minimum'     => 0,
					'title'       => __( 'Average order value', 'shop-commands' ),
					'description' => __(
						'Revenue divided by the number of included orders, or zero when there are none.',
						'shop-commands'
					),
				],
			],
			'required'             => [ 'orders', 'revenue', 'average_order_value' ],
			'additionalProperties' => false,
		];
	}
}
