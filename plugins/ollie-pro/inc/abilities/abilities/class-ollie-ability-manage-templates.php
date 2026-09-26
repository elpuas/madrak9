<?php
/**
 * Ability: ollie/manage-templates
 *
 * Lists and manages WordPress block templates and template parts.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manage_Templates {

	/**
	 * Register the ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'ollie/manage-templates',
			array(
				'label'               => __( 'Manage Templates', 'ollie-pro' ),
				'description'         => __( 'Manage WordPress block templates and template parts (headers, footers, sidebars). Actions: "list" (returns all templates/parts with id, slug, title, area — use first to discover available templates), "get" (read full block markup of a template by template_id or slug), "update" (replace a template\'s block markup by template_id). Set type to "wp_template" for page templates or "wp_template_part" (default) for headers/footers/sidebars. Template parts have an "area" field: header, footer, or uncategorized.', 'ollie-pro' ),
				'category'            => 'ollie-design',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action' ),
					'properties'           => array(
						'action'        => array(
							'type'        => 'string',
							'enum'        => array( 'list', 'get', 'update' ),
							'description' => __( 'Action to perform.', 'ollie-pro' ),
						),
						'type'          => array(
							'type'        => 'string',
							'enum'        => array( 'wp_template', 'wp_template_part' ),
							'description' => __( 'Template type. Defaults to "wp_template_part".', 'ollie-pro' ),
							'default'     => 'wp_template_part',
						),
						'template_id'   => array(
							'type'        => 'integer',
							'description' => __( 'Template post ID. Required for "get" and "update".', 'ollie-pro' ),
						),
						'slug'          => array(
							'type'        => 'string',
							'description' => __( 'Template slug. Alternative to template_id for "get" action.', 'ollie-pro' ),
						),
						'content'       => array(
							'type'        => 'string',
							'description' => __( 'New block markup content for the template. Required for "update".', 'ollie-pro' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'templates' => array( 'type' => 'array' ),
						'template'  => array( 'type' => 'object' ),
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
		$type   = $input['type'] ?? 'wp_template_part';

		if ( ! in_array( $type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			$type = 'wp_template_part';
		}

		switch ( $action ) {
			case 'list':
				return self::list_templates( $type );
			case 'get':
				return self::get_template( $input, $type );
			case 'update':
				return self::update_template( $input, $type );
			default:
				return new \WP_Error( 'invalid_action', __( 'Invalid action. Use "list", "get", or "update".', 'ollie-pro' ) );
		}
	}

	/**
	 * List templates of a given type.
	 */
	private static function list_templates( string $type ): array {
		$posts = get_posts( array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$templates = array();
		foreach ( $posts as $post ) {
			$templates[] = array(
				'id'     => $post->ID,
				'slug'   => $post->post_name,
				'title'  => $post->post_title ?: $post->post_name,
				'status' => $post->post_status,
				'area'   => get_post_meta( $post->ID, 'wp_template_part_area', true ) ?: '',
			);
		}

		return array(
			'success'   => true,
			'type'      => $type,
			'total'     => count( $templates ),
			'templates' => $templates,
		);
	}

	/**
	 * Get a specific template by ID or slug.
	 */
	private static function get_template( array $input, string $type ) {
		$template_id = (int) ( $input['template_id'] ?? 0 );
		$slug        = sanitize_title( $input['slug'] ?? '' );

		$post = null;
		if ( $template_id ) {
			$post = get_post( $template_id );
		} elseif ( $slug ) {
			$posts = get_posts( array(
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'draft' ),
				'name'           => $slug,
				'posts_per_page' => 1,
			) );
			$post = $posts[0] ?? null;
		}

		if ( ! $post || $post->post_type !== $type ) {
			return new \WP_Error( 'template_not_found', __( 'Template not found. Provide template_id or slug.', 'ollie-pro' ) );
		}

		return array(
			'success'  => true,
			'template' => array(
				'id'      => $post->ID,
				'slug'    => $post->post_name,
				'title'   => $post->post_title ?: $post->post_name,
				'content' => $post->post_content,
				'area'    => get_post_meta( $post->ID, 'wp_template_part_area', true ) ?: '',
			),
		);
	}

	/**
	 * Update a template's content.
	 */
	private static function update_template( array $input, string $type ) {
		$template_id = (int) ( $input['template_id'] ?? 0 );
		if ( ! $template_id ) {
			return new \WP_Error( 'missing_template_id', __( 'template_id is required for "update" action.', 'ollie-pro' ) );
		}

		$post = get_post( $template_id );
		if ( ! $post || $post->post_type !== $type ) {
			return new \WP_Error( 'template_not_found', __( 'Template not found.', 'ollie-pro' ) );
		}

		if ( ! isset( $input['content'] ) ) {
			return new \WP_Error( 'missing_content', __( 'content is required for "update" action.', 'ollie-pro' ) );
		}

		// Temporarily remove kses filters — MCP requires admin caps and kses
		// strips valid CSS transform values that blocks like outermost/icon-block need.
		kses_remove_filters();

		$result = wp_update_post(
			array(
				'ID'           => $template_id,
				'post_content' => $input['content'],
			),
			true
		);

		kses_init_filters();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'  => true,
			'template' => array(
				'id'   => $template_id,
				'slug' => $post->post_name,
				'title' => $post->post_title ?: $post->post_name,
			),
		);
	}
}
