<?php
/**
 * Find order ability.
 *
 * @package ShopCommands
 */

declare(strict_types=1);

namespace ShopCommands;

defined( 'ABSPATH' ) || exit;

/**
 * Looks up a Core-compatible order number without exposing private order data.
 */
final class Find_Order {
	/**
	 * Register the Find order ability.
	 */
	public static function register_ability(): void {
		wp_register_ability(
			'shop-commands/find-order',
			[
				'label'               => __( 'Find order', 'shop-commands' ),
				'description'         => __( 'Find an order by its WooCommerce Core order number.', 'shop-commands' ),
				'category'            => 'shop-commands',
				'input_schema'        => self::get_input_schema(),
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
	 * Find a regular order by its Core-compatible displayed number.
	 *
	 * @param mixed $input Validated Ability input.
	 * @return array<string, mixed>|\WP_Error Order summary, not-found result, or controlled error.
	 */
	public static function execute( $input ) {
		if ( ! is_array( $input ) || ! isset( $input['order_number'] ) ) {
			return new \WP_Error(
				'shop_commands_missing_order_number',
				__( 'Enter an order number.', 'shop-commands' ),
				[ 'status' => 400 ]
			);
		}

		$number = $input['order_number'];
		if ( ! is_string( $number ) || ! preg_match( '/^[1-9][0-9]*$/D', $number ) ) {
			return new \WP_Error(
				'shop_commands_invalid_order_number',
				__( 'Enter a valid numeric order number.', 'shop-commands' ),
				[ 'status' => 400 ]
			);
		}

		$order_id = filter_var( $number, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
		if ( false === $order_id ) {
			return new \WP_Error(
				'shop_commands_invalid_order_number',
				__( 'Enter a valid numeric order number.', 'shop-commands' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error(
				'shop_commands_not_initialized',
				__( 'WooCommerce is required to retrieve order information.', 'shop-commands' ),
				[ 'status' => 503 ]
			);
		}

		try {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof \WC_Order || 'shop_order' !== $order->get_type() ) {
				return self::not_found();
			}
			$display_number = (string) $order->get_order_number();
			if ( $number !== $display_number ) {
				return self::not_found();
			}

			if ( in_array( $order->get_status(), [ 'checkout-draft', 'auto-draft', 'trash' ], true ) ) {
				return self::not_found();
			}

			$created_date = $order->get_date_created();
			$customer     = trim( $order->get_formatted_billing_full_name() );

			return [
				'found' => true,
				'order' => [
					'order_number' => $display_number,
					'customer'     => '' !== $customer ? $customer : __( 'Not provided', 'shop-commands' ),
					'status'       => wc_get_order_status_name( $order->get_status() ),
					'total'        => (float) $order->get_total(),
					'date'         => $created_date
						? wp_date(
							get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T',
							$created_date->getTimestamp(),
							wp_timezone()
						)
						: null,
				],
			];
		} catch ( \Throwable $error ) {
			return new \WP_Error(
				'shop_commands_order_data_unavailable',
				__( 'The order could not be retrieved. Please try again.', 'shop-commands' ),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Return an expected not-found result.
	 *
	 * @return array<string, bool|string> Not-found response.
	 */
	private static function not_found(): array {
		return [
			'found'   => false,
			'message' => __( 'No matching order was found.', 'shop-commands' ),
		];
	}

	/**
	 * Get the single-field input schema.
	 *
	 * @return array<string, mixed> JSON Schema definition.
	 */
	private static function get_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'order_number' => [
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 19,
					'pattern'     => '^[1-9][0-9]*$',
					'title'       => __( 'Order number', 'shop-commands' ),
					'description' => __(
						'Numeric Core order number. Custom numbers that differ from order IDs cannot be searched.',
						'shop-commands'
					),
				],
			],
			'required'             => [ 'order_number' ],
			'additionalProperties' => false,
		];
	}

	/**
	 * Get the strict result schema.
	 *
	 * @return array<string, mixed> JSON Schema definition.
	 */
	private static function get_output_schema(): array {
		return [
			'type'                 => 'object',
			'title'                => __( 'Find order', 'shop-commands' ),
			'properties'           => [
				'found'   => [
					'type'  => 'boolean',
					'title' => __( 'Found', 'shop-commands' ),
				],
				'message' => [
					'type'  => 'string',
					'title' => __( 'Message', 'shop-commands' ),
				],
				'order'   => [
					'type'                 => 'object',
					'title'                => __( 'Order', 'shop-commands' ),
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
							'type'  => 'number',
							'title' => __( 'Total', 'shop-commands' ),
						],
						'date'         => [
							'type'  => [ 'string', 'null' ],
							'title' => __( 'Date', 'shop-commands' ),
						],
					],
					'required'             => [ 'order_number', 'customer', 'status', 'total', 'date' ],
					'additionalProperties' => false,
				],
			],
			'required'             => [ 'found' ],
			'additionalProperties' => false,
		];
	}
}
