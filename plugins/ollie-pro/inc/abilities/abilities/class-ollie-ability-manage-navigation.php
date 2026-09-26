<?php
/**
 * Ability: ollie/manage-navigation
 *
 * Manages WordPress navigation menus and mega menu blocks.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manage_Navigation {

	/**
	 * Register the ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'ollie/manage-navigation',
			array(
				'label'               => __( 'Manage Navigation', 'ollie-pro' ),
				'description'         => __( 'Manage WordPress navigation menus (wp_navigation post type). Actions: "list" (returns all navigation menus with id/title — use first to discover menu IDs), "create" (create a new navigation menu with title and optional content/items), "get" (read full block markup of a menu by menu_id), "update" (replace menu content by menu_id — provide block markup via "content" or structured links via "items"). Supports core/navigation-link, core/navigation-submenu, and ollie-menu-designer/mega-menu blocks when the Mega Menu extension is active.', 'ollie-pro' ),
				'category'            => 'ollie-design',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action' ),
					'properties'           => array(
						'action'  => array(
							'type'        => 'string',
							'enum'        => array( 'list', 'get', 'update', 'create' ),
							'description' => __( 'Action to perform on navigation menus.', 'ollie-pro' ),
						),
						'nav_id'  => array(
							'type'        => 'integer',
							'description' => __( 'Navigation post ID. Required for "get" and "update" actions.', 'ollie-pro' ),
						),
						'title'   => array(
							'type'        => 'string',
							'description' => __( 'Navigation menu title. Required for "create" action.', 'ollie-pro' ),
						),
 					'content' => array(
							'type'        => 'string',
							'description' => __( 'Block markup content for the navigation. Use WordPress navigation block inner blocks (core/navigation-link, core/navigation-submenu, ollie-menu-designer/mega-menu). Required for "update" and optional for "create". If "items" is also provided, "items" takes precedence.', 'ollie-pro' ),
						),
						'items'   => array(
							'type'        => 'array',
							'description' => __( 'Structured menu items array for "create" or "update". Each item: { "label": string, "url": string, "children": [ { "label": string, "url": string } ] }. Items with "children" become dropdown submenus automatically. Takes precedence over raw "content".', 'ollie-pro' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'label'    => array( 'type' => 'string' ),
									'url'      => array( 'type' => 'string' ),
									'children' => array(
										'type'  => 'array',
										'items' => array(
											'type'       => 'object',
											'properties' => array(
												'label' => array( 'type' => 'string' ),
												'url'   => array( 'type' => 'string' ),
											),
										),
									),
								),
							),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'             => array( 'type' => 'boolean' ),
						'menus'               => array( 'type' => 'array' ),
						'nav_id'              => array( 'type' => 'integer' ),
						'title'               => array( 'type' => 'string' ),
						'content'             => array( 'type' => 'string' ),
						'mega_menu_available' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( self::class, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);
	}

	/**
	 * Execute callback.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$action = $input['action'] ?? '';
		$mega_menu_active = is_plugin_active( 'ollie-menu-designer/ollie-menu-designer.php' );

		switch ( $action ) {
			case 'list':
				return self::list_menus( $mega_menu_active );
			case 'get':
				return self::get_menu( $input, $mega_menu_active );
			case 'create':
				return self::create_menu( $input, $mega_menu_active );
			case 'update':
				return self::update_menu( $input, $mega_menu_active );
			default:
				return new \WP_Error( 'invalid_action', __( 'Invalid action. Use "list", "get", "create", or "update".', 'ollie-pro' ) );
		}
	}

	/**
	 * List all navigation menus.
	 */
	private static function list_menus( bool $mega_menu_active ): array {
		$navs = get_posts( array(
			'post_type'      => 'wp_navigation',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$menus = array();
		foreach ( $navs as $nav ) {
			$menus[] = array(
				'nav_id' => $nav->ID,
				'title'  => $nav->post_title,
				'status' => $nav->post_status,
			);
		}

		return array(
			'success'             => true,
			'total'               => count( $menus ),
			'menus'               => $menus,
			'mega_menu_available' => $mega_menu_active,
		);
	}

	/**
	 * Get a specific navigation menu.
	 */
	private static function get_menu( array $input, bool $mega_menu_active ) {
		$nav_id = (int) ( $input['nav_id'] ?? 0 );
		if ( ! $nav_id ) {
			return new \WP_Error( 'missing_nav_id', __( 'nav_id is required for "get" action.', 'ollie-pro' ) );
		}

		$nav = get_post( $nav_id );
		if ( ! $nav || 'wp_navigation' !== $nav->post_type ) {
			return new \WP_Error( 'nav_not_found', __( 'Navigation menu not found.', 'ollie-pro' ) );
		}

		return array(
			'success'             => true,
			'nav_id'              => $nav->ID,
			'title'               => $nav->post_title,
			'content'             => $nav->post_content,
			'mega_menu_available' => $mega_menu_active,
		);
	}

	/**
	 * Create a new navigation menu.
	 */
	private static function create_menu( array $input, bool $mega_menu_active ) {
		$title = sanitize_text_field( $input['title'] ?? '' );
		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_title', __( 'title is required for "create" action.', 'ollie-pro' ) );
		}

		// Build content from structured items if provided, otherwise use raw content.
		$content = self::resolve_menu_content( $input );

		// Temporarily remove kses filters — MCP requires admin caps and kses
		// strips valid CSS transform values that blocks like outermost/icon-block need.
		kses_remove_filters();

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
			),
			true
		);

		kses_init_filters();

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'success'             => true,
			'nav_id'              => $post_id,
			'title'               => $title,
			'content'             => get_post( $post_id )->post_content,
			'mega_menu_available' => $mega_menu_active,
		);
	}

	/**
	 * Update an existing navigation menu.
	 */
	private static function update_menu( array $input, bool $mega_menu_active ) {
		$nav_id = (int) ( $input['nav_id'] ?? 0 );
		if ( ! $nav_id ) {
			return new \WP_Error( 'missing_nav_id', __( 'nav_id is required for "update" action.', 'ollie-pro' ) );
		}

		$nav = get_post( $nav_id );
		if ( ! $nav || 'wp_navigation' !== $nav->post_type ) {
			return new \WP_Error( 'nav_not_found', __( 'Navigation menu not found.', 'ollie-pro' ) );
		}

		$update_args = array( 'ID' => $nav_id );

		// Build content from structured items if provided, otherwise use raw content.
		if ( isset( $input['items'] ) || isset( $input['content'] ) ) {
			$update_args['post_content'] = self::resolve_menu_content( $input );
		}
		if ( isset( $input['title'] ) ) {
			$update_args['post_title'] = sanitize_text_field( $input['title'] );
		}

		// Temporarily remove kses filters — see create_menu comment.
		kses_remove_filters();

		$result = wp_update_post( $update_args, true );

		kses_init_filters();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$refreshed = get_post( $nav_id );

		return array(
			'success'             => true,
			'nav_id'              => $nav_id,
			'title'               => $refreshed->post_title,
			'content'             => $refreshed->post_content,
			'mega_menu_available' => $mega_menu_active,
		);
	}

	/**
	 * Resolve menu content from structured items or raw content.
	 *
	 * If 'items' is provided, build block markup from the structured array.
	 * Otherwise fall back to raw 'content'.
	 *
	 * @param array $input Ability input.
	 * @return string Block markup for the navigation.
	 */
	private static function resolve_menu_content( array $input ): string {
		if ( ! empty( $input['items'] ) && is_array( $input['items'] ) ) {
			return self::build_nav_blocks( $input['items'] );
		}

		return $input['content'] ?? '';
	}

	/**
	 * Build navigation block markup from a structured items array.
	 *
	 * Each item may have 'label', 'url', and optional 'children' (array of child items).
	 * Items with children become core/navigation-submenu blocks; others become core/navigation-link.
	 *
	 * @param array $items Menu items.
	 * @return string Serialized block markup.
	 */
	private static function build_nav_blocks( array $items ): string {
		$blocks = array();

		foreach ( $items as $item ) {
			$label = sanitize_text_field( $item['label'] ?? '' );
			$url   = esc_url_raw( $item['url'] ?? '#' );

			if ( empty( $label ) ) {
				continue;
			}

			$children = $item['children'] ?? array();

			if ( ! empty( $children ) && is_array( $children ) ) {
				// Build child navigation-link blocks.
				$inner_blocks  = array();
				$inner_content = array();

				foreach ( $children as $child ) {
					$child_label = sanitize_text_field( $child['label'] ?? '' );
					$child_url   = esc_url_raw( $child['url'] ?? '#' );

					if ( empty( $child_label ) ) {
						continue;
					}

					$inner_blocks[] = array(
						'blockName'    => 'core/navigation-link',
						'attrs'        => array(
							'label' => $child_label,
							'url'   => $child_url,
						),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					);
					$inner_content[] = null; // Placeholder for inner block.
				}

				$blocks[] = array(
					'blockName'    => 'core/navigation-submenu',
					'attrs'        => array(
						'label'          => $label,
						'url'            => $url,
						'isTopLevelLink' => true,
					),
					'innerBlocks'  => $inner_blocks,
					'innerHTML'    => '',
					'innerContent' => $inner_content,
				);
			} else {
				$blocks[] = array(
					'blockName'    => 'core/navigation-link',
					'attrs'        => array(
						'label' => $label,
						'url'   => $url,
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);
			}
		}

		return serialize_blocks( $blocks );
	}
}
