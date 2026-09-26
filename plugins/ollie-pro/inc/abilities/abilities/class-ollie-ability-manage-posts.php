<?php
/**
 * Ability: ollie/manage-posts
 *
 * Unified CRUD + pattern-based creation for any WordPress post type.
 * Replaces the former ollie/manage-pages and ollie/create-page abilities.
 *
 * @package OlliePro
 * @since   3.1.0
 */

namespace olpo\Abilities\Abilities;

use olpo\Abilities\Ollie_Pattern_Index;
use olpo\Abilities\Validators\Ollie_Block_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manage_Posts {

	/**
	 * Block validator instance.
	 *
	 * @var Ollie_Block_Validator|null
	 */
	private static ?Ollie_Block_Validator $block_validator = null;

	/**
	 * Pattern index instance.
	 *
	 * @var Ollie_Pattern_Index|null
	 */
	private static ?Ollie_Pattern_Index $pattern_index = null;

	/**
	 * Maximum content length returned by get action (characters).
	 * Keeps MCP responses manageable for AI clients.
	 */
	private const MAX_CONTENT_LENGTH = 15000;

	/**
	 * Register the ability.
	 *
	 * @param Ollie_Pattern_Index        $pattern_index  Pattern index.
	 * @param Ollie_Block_Validator|null $block_validator Block validator.
	 */
	public static function register( Ollie_Pattern_Index $pattern_index, ?Ollie_Block_Validator $block_validator = null ): void {
		self::$block_validator = $block_validator;
		self::$pattern_index   = $pattern_index;
		wp_register_ability(
			'ollie/manage-posts',
			array(
				'label'               => __( 'Manage Posts', 'ollie-pro' ),
				'description'         => __( 'Create, list, get, update, and delete WordPress posts, pages, and custom post types. When creating new content, always try "create_from_pattern" first — it finds a matching design pattern and creates the post in one step. Only fall back to "create" (raw markup) if no pattern matches or the user provides their own content. Use "list" to find post IDs, then "get" to read content, "update" to modify, or "delete" to remove. The "post_type" parameter defaults to "post". Set it to "page" for pages, or any registered custom post type slug (e.g. "product", "portfolio"). All actions except "create" and "create_from_pattern" require a post_id.', 'ollie-pro' ),
				'category'            => 'ollie-design',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action' ),
					'properties'           => array(
						'action'         => array(
							'type'        => 'string',
							'enum'        => array( 'create', 'create_from_pattern', 'list', 'get', 'update', 'delete' ),
							'description' => __( 'The operation: create, create_from_pattern, list, get, update, or delete.', 'ollie-pro' ),
						),
						'post_type'      => array(
							'type'        => 'string',
							'description' => __( 'WordPress post type slug. Defaults to "post". Use "page" for pages, or any registered custom post type slug.', 'ollie-pro' ),
							'default'     => 'post',
						),
						'post_id'        => array(
							'type'        => 'integer',
							'description' => __( 'Post ID. Required for get, update, and delete.', 'ollie-pro' ),
						),
						'title'          => array(
							'type'        => 'string',
							'description' => __( 'Post title. Required for create and create_from_pattern, optional for update.', 'ollie-pro' ),
						),
						'content'        => array(
							'type'        => 'string',
							'description' => __( 'Block markup for the post body. Used by create and update.', 'ollie-pro' ),
						),
						'status'         => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
							'description' => __( 'Post status: publish, draft, pending, or private. Defaults to draft for create and create_from_pattern.', 'ollie-pro' ),
						),
						'template'       => array(
							'type'        => 'string',
							'description' => __( 'Template slug, e.g. "page-no-title", "blank", or "default" to reset. Used by create and update. For pages, defaults to "page-no-title" when using create_from_pattern. Other post types use site defaults.', 'ollie-pro' ),
						),
						'search'         => array(
							'type'        => 'string',
							'description' => __( 'Search term to filter posts by title. Used by list only.', 'ollie-pro' ),
						),
						'force'          => array(
							'type'        => 'boolean',
							'description' => __( 'For delete: true to permanently delete instead of trashing.', 'ollie-pro' ),
							'default'     => false,
						),
						'query'          => array(
							'type'        => 'string',
							'description' => __( 'Free-text description of the content to create (action: create_from_pattern). Use broad, page-level queries (e.g. "pricing page", "about page", "blog post hero"). The tool finds the best matching pattern.', 'ollie-pro' ),
						),
						'pattern_slug'   => array(
							'type'        => 'string',
							'description' => __( 'Use a specific cached pattern by slug instead of searching (action: create_from_pattern). Skips the search step.', 'ollie-pro' ),
						),
						'pattern_slugs'  => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Array of cached pattern slugs to compose into a single post (action: create_from_pattern). All patterns are concatenated in order.', 'ollie-pro' ),
						),
						'custom_content' => array(
							'type'        => 'string',
							'description' => __( 'Specific content to incorporate into the pattern (action: create_from_pattern). When set, the tool returns the pattern markup for you to merge, then use the "create" action to save.', 'ollie-pro' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
				'execute_callback'    => array( self::class, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
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
	 * Execute callback — routes to the appropriate handler.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$action = $input['action'] ?? '';

		switch ( $action ) {
			case 'create':
				return self::handle_create( $input );
			case 'create_from_pattern':
				return self::handle_create_from_pattern( $input );
			case 'list':
				return self::handle_list( $input );
			case 'get':
				return self::handle_get( $input );
			case 'update':
				return self::handle_update( $input );
			case 'delete':
				return self::handle_delete( $input );
			default:
				return new \WP_Error( 'invalid_action', __( 'Action must be one of: create, create_from_pattern, list, get, update, delete.', 'ollie-pro' ) );
		}
	}

	/**
	 * Resolve and validate a post type slug.
	 *
	 * @param string $post_type Post type slug.
	 * @return \WP_Post_Type|\WP_Error
	 */
	private static function resolve_post_type( string $post_type ) {
		$type_object = get_post_type_object( $post_type );

		if ( ! $type_object ) {
			return new \WP_Error(
				'invalid_post_type',
				sprintf(
					__( 'Post type "%s" does not exist.', 'ollie-pro' ),
					$post_type
				)
			);
		}

		if ( ! $type_object->public ) {
			return new \WP_Error(
				'invalid_post_type',
				sprintf(
					__( 'Post type "%s" is not public.', 'ollie-pro' ),
					$post_type
				)
			);
		}

		return $type_object;
	}

	/**
	 * Build a safe edit URL for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function edit_url( int $post_id ): string {
		$url = get_edit_post_link( $post_id, 'raw' );

		return ! empty( $url ) ? $url : admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	}

	/**
	 * Sanitize content through the block validator if available.
	 *
	 * @param string $content Raw block markup.
	 * @return string Sanitized markup.
	 */
	private static function sanitize_content( string $content ): string {
		if ( '' !== $content && null !== self::$block_validator ) {
			$sanitized = self::$block_validator->sanitize_block_markup( $content );
			if ( is_array( $sanitized ) && isset( $sanitized['markup'] ) ) {
				$content = $sanitized['markup'];
			}
		}

		return $content;
	}

	/**
	 * Insert a WordPress post.
	 *
	 * @param string $title     Post title.
	 * @param string $content   Post content (block markup).
	 * @param string $status    Post status.
	 * @param string $post_type Post type slug.
	 * @param string $template  Template slug (pages only).
	 * @return int|\WP_Error Post ID or error.
	 */
	private static function insert_post( string $title, string $content, string $status, string $post_type, string $template = '' ) {
		$type_object = self::resolve_post_type( $post_type );
		if ( is_wp_error( $type_object ) ) {
			return $type_object;
		}

		$valid_statuses = array( 'publish', 'draft', 'pending', 'private' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = 'draft';
		}

		if ( 'publish' === $status && ! current_user_can( $type_object->cap->publish_posts ) ) {
			$status = 'draft';
		}

		kses_remove_filters();

		$post_id = wp_insert_post(
			array(
				'post_author'  => get_current_user_id(),
				'post_title'   => $title,
				'post_name'    => sanitize_title( $title ),
				'post_status'  => $status,
				'post_content' => $content,
				'post_type'    => $post_type,
			),
			true
		);

		kses_init_filters();

		if ( ! is_wp_error( $post_id ) && ! empty( $template ) && 'page' === $post_type ) {
			update_post_meta( $post_id, '_wp_page_template', $template );
		}

		return $post_id;
	}

	/**
	 * Create a new post from raw markup.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_create( array $input ) {
		$title     = sanitize_text_field( $input['title'] ?? '' );
		$content   = self::sanitize_content( $input['content'] ?? '' );
		$status    = $input['status'] ?? 'draft';
		$post_type = sanitize_text_field( $input['post_type'] ?? 'post' );
		$template  = sanitize_text_field( $input['template'] ?? '' );

		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_title', __( 'Post title is required for the create action.', 'ollie-pro' ) );
		}

		$type_object = self::resolve_post_type( $post_type );
		if ( is_wp_error( $type_object ) ) {
			return $type_object;
		}

		if ( ! current_user_can( $type_object->cap->edit_posts ) ) {
			return new \WP_Error( 'unauthorized', __( 'You do not have permission to create this content.', 'ollie-pro' ) );
		}

		$post_id = self::insert_post( $title, $content, $status, $post_type, $template );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = array(
			'post_id'   => $post_id,
			'title'     => $title,
			'status'    => $status,
			'post_type' => $post_type,
			'edit_url'  => self::edit_url( $post_id ),
		);

		if ( 'page' === $post_type ) {
			$result['template'] = ! empty( $template ) ? $template : 'default';
		}

		return $result;
	}

	/**
	 * Create a post from a pattern search — one-shot creation.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_create_from_pattern( array $input ) {
		$title          = sanitize_text_field( $input['title'] ?? '' );
		$query          = $input['query'] ?? '';
		$pattern_slug   = $input['pattern_slug'] ?? '';
		$pattern_slugs  = $input['pattern_slugs'] ?? array();
		$custom_content = $input['custom_content'] ?? '';
		$status         = $input['status'] ?? 'draft';
		$post_type      = sanitize_text_field( $input['post_type'] ?? 'post' );
		$template       = sanitize_text_field( $input['template'] ?? '' );

		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_title', __( 'Post title is required for create_from_pattern.', 'ollie-pro' ) );
		}

		$type_object = self::resolve_post_type( $post_type );
		if ( is_wp_error( $type_object ) ) {
			return $type_object;
		}

		if ( ! current_user_can( $type_object->cap->edit_posts ) ) {
			return new \WP_Error( 'unauthorized', __( 'You do not have permission to create this content.', 'ollie-pro' ) );
		}

		// Default template to page-no-title for pages.
		if ( 'page' === $post_type && empty( $template ) ) {
			$template = 'page-no-title';
		}

		// Path 1: Compose from multiple known pattern slugs.
		if ( ! empty( $pattern_slugs ) && is_array( $pattern_slugs ) ) {
			return self::compose_from_slugs( $pattern_slugs, $title, $status, $post_type, $template, $custom_content );
		}

		// Path 2: Use a single known pattern slug.
		if ( ! empty( $pattern_slug ) ) {
			$cached = self::$pattern_index->get_cached_cloud_pattern( $pattern_slug );
			if ( null === $cached ) {
				return new \WP_Error(
					'pattern_not_cached',
					sprintf(
						__( 'Pattern "%s" not found in local cache. Run a pattern search first.', 'ollie-pro' ),
						$pattern_slug
					)
				);
			}

			return self::create_from_markup(
				$cached['content'],
				$cached['title'],
				$pattern_slug,
				$title,
				$status,
				$post_type,
				$template,
				$custom_content
			);
		}

		// Path 3: Search — use the single best match.
		if ( empty( $query ) ) {
			return new \WP_Error( 'missing_query', __( 'Either "query", "pattern_slug", or "pattern_slugs" is required for create_from_pattern.', 'ollie-pro' ) );
		}

		$search_results = self::$pattern_index->cloud_search( $query, 1, true, 0.4 );

		if ( ! empty( $search_results['error'] ) ) {
			return new \WP_Error( 'search_failed', $search_results['error'] );
		}

		$patterns = $search_results['patterns'] ?? array();
		if ( empty( $patterns ) ) {
			return new \WP_Error(
				'no_patterns_found',
				sprintf(
					__( 'No patterns found matching "%s". Try a different description.', 'ollie-pro' ),
					$query
				)
			);
		}

		$best           = $patterns[0];
		$pattern_markup = $best['content'] ?? '';
		$pattern_title  = $best['title'] ?? '';
		$best_slug      = $best['pattern_slug'] ?? ( $best['slug'] ?? '' );

		if ( empty( $pattern_markup ) ) {
			return new \WP_Error( 'empty_pattern', __( 'The matched pattern has no content.', 'ollie-pro' ) );
		}

		return self::create_from_markup(
			$pattern_markup,
			$pattern_title,
			$best_slug,
			$title,
			$status,
			$post_type,
			$template,
			$custom_content
		);
	}

	/**
	 * Compose a post from multiple cached pattern slugs.
	 *
	 * @param array  $slugs          Pattern slugs.
	 * @param string $title          Post title.
	 * @param string $status         Post status.
	 * @param string $post_type      Post type slug.
	 * @param string $template       Template slug.
	 * @param string $custom_content Custom content for merge.
	 * @return array|\WP_Error
	 */
	private static function compose_from_slugs(
		array $slugs,
		string $title,
		string $status,
		string $post_type,
		string $template,
		string $custom_content
	) {
		$markup_parts = array();
		$titles       = array();
		$valid_slugs  = array();

		foreach ( $slugs as $slug ) {
			$slug   = sanitize_text_field( $slug );
			$cached = self::$pattern_index->get_cached_cloud_pattern( $slug );
			if ( null === $cached ) {
				return new \WP_Error(
					'pattern_not_cached',
					sprintf(
						__( 'Pattern "%s" not found in local cache. Run a pattern search first via ollie/manage-patterns.', 'ollie-pro' ),
						$slug
					)
				);
			}
			$markup_parts[] = $cached['content'];
			$titles[]       = $cached['title'];
			$valid_slugs[]  = $slug;
		}

		$composed_markup = implode( "\n\n", $markup_parts );

		// If custom content was provided, return for merging.
		if ( ! empty( $custom_content ) ) {
			return array(
				'mode'            => 'merge_required',
				'composition'     => 'multi_section',
				'pattern_titles'  => $titles,
				'pattern_slugs'   => $valid_slugs,
				'content'         => $composed_markup,
				'custom_content'  => $custom_content,
				'title'           => $title,
				'status'          => $status,
				'post_type'       => $post_type,
				'template'        => $template,
				'hint'            => __( 'Multiple section patterns were composed. Merge the custom_content into the combined markup, then use ollie/manage-posts "create" action to save.', 'ollie-pro' ),
			);
		}

		// Create the post directly.
		$composed_markup = self::sanitize_content( $composed_markup );

		$post_id = self::insert_post( $title, $composed_markup, $status, $post_type, $template );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = array(
			'post_id'        => $post_id,
			'title'          => $title,
			'status'         => $status,
			'post_type'      => $post_type,
			'composition'    => 'multi_section',
			'pattern_titles' => $titles,
			'pattern_slugs'  => $valid_slugs,
			'edit_url'       => self::edit_url( $post_id ),
		);

		if ( 'page' === $post_type ) {
			$result['template'] = ! empty( $template ) ? $template : 'default';
		}

		return $result;
	}

	/**
	 * Create a post from a single pattern's markup, or return for merging.
	 *
	 * @param string $markup          Pattern markup.
	 * @param string $pattern_title   Pattern title.
	 * @param string $pattern_slug    Pattern slug.
	 * @param string $title           Post title.
	 * @param string $status          Post status.
	 * @param string $post_type       Post type slug.
	 * @param string $template        Template slug.
	 * @param string $custom_content  Custom content for merge.
	 * @return array|\WP_Error
	 */
	private static function create_from_markup(
		string $markup,
		string $pattern_title,
		string $pattern_slug,
		string $title,
		string $status,
		string $post_type,
		string $template,
		string $custom_content
	) {
		// If custom content was provided, return the pattern for merging.
		if ( ! empty( $custom_content ) ) {
			return array(
				'mode'           => 'merge_required',
				'pattern_title'  => $pattern_title,
				'pattern_slug'   => $pattern_slug,
				'content'        => $markup,
				'custom_content' => $custom_content,
				'title'          => $title,
				'status'         => $status,
				'post_type'      => $post_type,
				'template'       => $template,
				'hint'           => __( 'Merge the custom_content into the pattern markup, then use ollie/manage-posts "create" action to save.', 'ollie-pro' ),
			);
		}

		// Create the post directly.
		$markup = self::sanitize_content( $markup );

		$post_id = self::insert_post( $title, $markup, $status, $post_type, $template );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = array(
			'post_id'       => $post_id,
			'title'         => $title,
			'status'        => $status,
			'post_type'     => $post_type,
			'pattern_title' => $pattern_title,
			'pattern_slug'  => $pattern_slug,
			'edit_url'      => self::edit_url( $post_id ),
		);

		if ( 'page' === $post_type ) {
			$result['template'] = ! empty( $template ) ? $template : 'default';
		}

		return $result;
	}

	/**
	 * List posts — returns compact summaries (no content, no URLs).
	 */
	private static function handle_list( array $input ): array {
		$post_type = sanitize_text_field( $input['post_type'] ?? 'post' );
		$status    = $input['status'] ?? 'any';
		$search    = sanitize_text_field( $input['search'] ?? '' );

		$type_object = self::resolve_post_type( $post_type );
		if ( is_wp_error( $type_object ) ) {
			return array(
				'total' => 0,
				'count' => 0,
				'posts' => array(),
				'error' => $type_object->get_error_message(),
			);
		}

		$valid_list_statuses = array( 'any', 'publish', 'draft', 'pending', 'private', 'trash' );
		if ( ! in_array( $status, $valid_list_statuses, true ) ) {
			$status = 'any';
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$posts = array();

		foreach ( $query->posts as $post ) {
			$item = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'status'    => $post->post_status,
				'post_type' => $post->post_type,
			);

			if ( 'page' === $post_type ) {
				$item['template'] = get_post_meta( $post->ID, '_wp_page_template', true ) ?: 'default';
			}

			$posts[] = $item;
		}

		return array(
			'total' => $query->found_posts,
			'count' => count( $posts ),
			'posts' => $posts,
		);
	}

	/**
	 * Get post content. Truncates very large content to keep responses manageable.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_get( array $input ) {
		$post_id = (int) ( $input['post_id'] ?? 0 );

		if ( $post_id < 1 ) {
			return new \WP_Error( 'missing_post_id', __( 'post_id is required for the get action.', 'ollie-pro' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You cannot view this post.', 'ollie-pro' ) );
		}

		$content        = $post->post_content;
		$content_length = mb_strlen( $content );
		$truncated      = false;

		if ( $content_length > self::MAX_CONTENT_LENGTH ) {
			$content   = mb_substr( $content, 0, self::MAX_CONTENT_LENGTH );
			$truncated = true;
		}

		$result = array(
			'post_id'   => $post_id,
			'title'     => $post->post_title,
			'status'    => $post->post_status,
			'post_type' => $post->post_type,
			'content'   => $content,
			'edit_url'  => self::edit_url( $post_id ),
		);

		if ( 'page' === $post->post_type ) {
			$result['template'] = get_post_meta( $post_id, '_wp_page_template', true ) ?: 'default';
		}

		if ( $truncated ) {
			$result['truncated']      = true;
			$result['content_length'] = $content_length;
		}

		return $result;
	}

	/**
	 * Update an existing post.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_update( array $input ) {
		$post_id = (int) ( $input['post_id'] ?? 0 );

		if ( $post_id < 1 ) {
			return new \WP_Error( 'missing_post_id', __( 'post_id is required for the update action.', 'ollie-pro' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You cannot edit this post.', 'ollie-pro' ) );
		}

		$type_object = get_post_type_object( $post->post_type );
		$update_args = array( 'ID' => $post_id );
		$updated     = array();

		if ( isset( $input['content'] ) ) {
			$update_args['post_content'] = self::sanitize_content( $input['content'] );
			$updated[]                   = 'content';
		}

		if ( isset( $input['title'] ) ) {
			$update_args['post_title'] = sanitize_text_field( $input['title'] );
			$updated[]                 = 'title';
		}

		if ( isset( $input['status'] ) ) {
			$valid_statuses = array( 'publish', 'draft', 'pending', 'private' );
			if ( in_array( $input['status'], $valid_statuses, true ) ) {
				if ( 'publish' === $input['status'] && $type_object && ! current_user_can( $type_object->cap->publish_posts ) ) {
					return new \WP_Error( 'unauthorized', __( 'You do not have permission to publish this content.', 'ollie-pro' ) );
				}
				$update_args['post_status'] = $input['status'];
				$updated[]                  = 'status';
			}
		}

		if ( isset( $input['template'] ) && 'page' === $post->post_type ) {
			$template = sanitize_text_field( $input['template'] );
			if ( 'default' === $template ) {
				delete_post_meta( $post_id, '_wp_page_template' );
			} else {
				update_post_meta( $post_id, '_wp_page_template', $template );
			}
			$updated[] = 'template';
		}

		if ( empty( $updated ) ) {
			return new \WP_Error( 'nothing_to_update', __( 'Provide at least one of: content, title, status, template.', 'ollie-pro' ) );
		}

		// Temporarily remove kses filters — see insert_post comment.
		kses_remove_filters();

		$result = wp_update_post( $update_args, true );

		kses_init_filters();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$refreshed = get_post( $post_id );

		$response = array(
			'post_id'   => $post_id,
			'title'     => $refreshed->post_title,
			'status'    => $refreshed->post_status,
			'post_type' => $refreshed->post_type,
			'updated'   => $updated,
			'edit_url'  => self::edit_url( $post_id ),
		);

		if ( 'page' === $refreshed->post_type ) {
			$response['template'] = get_post_meta( $post_id, '_wp_page_template', true ) ?: 'default';
		}

		return $response;
	}

	/**
	 * Delete (trash or permanently remove) a post.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_delete( array $input ) {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$force   = (bool) ( $input['force'] ?? false );

		if ( $post_id < 1 ) {
			return new \WP_Error( 'missing_post_id', __( 'post_id is required for the delete action.', 'ollie-pro' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You do not have permission to delete this post.', 'ollie-pro' ) );
		}

		$title = $post->post_title;

		if ( $force ) {
			$deleted = wp_delete_post( $post_id, true );
			if ( ! $deleted ) {
				return new \WP_Error( 'delete_failed', __( 'Failed to permanently delete the post.', 'ollie-pro' ) );
			}

			return array(
				'post_id'   => $post_id,
				'title'     => $title,
				'post_type' => $post->post_type,
				'deleted'   => true,
			);
		}

		$trashed = wp_trash_post( $post_id );
		if ( ! $trashed ) {
			return new \WP_Error( 'trash_failed', __( 'Failed to trash the post.', 'ollie-pro' ) );
		}

		return array(
			'post_id'   => $post_id,
			'title'     => $title,
			'post_type' => $post->post_type,
			'status'    => 'trash',
		);
	}
}
