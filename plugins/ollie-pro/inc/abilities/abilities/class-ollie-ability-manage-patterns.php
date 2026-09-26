<?php
/**
 * Ability: ollie/manage-patterns
 *
 * Consolidated pattern management ability with actions:
 * - search:  Semantic vector search against the Ollie cloud pattern library.
 * - apply:   Insert a pattern into a page (two-step preview/confirm).
 * - replace: Replace a top-level section with a new pattern (two-step preview/confirm).
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Abilities;

use olpo\Abilities\Ollie_Pattern_Index;
use olpo\Abilities\Validators\Ollie_Block_Validator;
use olpo\Abilities\Validators\Ollie_Design_Linter;
use olpo\Abilities\Preview\Ollie_Preview_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manage_Patterns {

	/**
	 * Register the ability.
	 *
	 * @param Ollie_Pattern_Index   $pattern_index   Pattern index.
	 * @param Ollie_Block_Validator $block_validator  Block validator.
	 * @param Ollie_Design_Linter   $design_linter   Linter.
	 * @param Ollie_Preview_Handler $preview_handler  Preview handler.
	 */
	public static function register(
		Ollie_Pattern_Index $pattern_index,
		Ollie_Block_Validator $block_validator,
		Ollie_Design_Linter $design_linter,
		Ollie_Preview_Handler $preview_handler
	): void {
		wp_register_ability(
			'ollie/manage-patterns',
			array(
				'label'               => __( 'Manage Patterns', 'ollie-pro' ),
				'description'         => __( 'Search, apply, and replace block patterns. IMPORTANT: Do NOT use this tool to compose a page from multiple section patterns when the user asks to "create a page" — use ollie/manage-posts "create_from_pattern" instead, which finds full-page designs in a single call. This tool is for: adding sections to an existing page, replacing sections on an existing page, or searching for patterns when you need to browse what is available. Actions: "search" (semantic cloud search — returns patterns with full block markup, auto-cached locally), "apply" (insert pattern into a page — requires post_id), "replace" (swap a top-level section — requires post_id and section_index). WORKFLOW: 1) Search for patterns (results are auto-cached locally). 2) Use the "pattern_slug" field from search results (preferred, fast & reliable) or pass raw "content" to apply/replace. PREVIEW/CONFIRM FLOW: apply and replace use a two-step process. Step 1: call with post_id + pattern_slug (confirm defaults to false) — returns a preview with preview_token and preview_url. Step 2: call again with post_id + pattern_slug + confirm=true + preview_token to finalize. REQUIRED PARAMS: apply needs post_id; replace needs post_id + section_index.', 'ollie-pro' ),
				'category'            => 'ollie-design',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action' ),
					'properties'           => array(
						'action'          => array(
							'type'        => 'string',
							'enum'        => array( 'search', 'apply', 'replace' ),
							'description' => __( 'Action to perform: "search", "apply", or "replace".', 'ollie-pro' ),
						),
						// Search parameters.
						'query'           => array(
							'type'        => 'string',
							'description' => __( 'Free-text search prompt for semantic pattern matching (action: search).', 'ollie-pro' ),
						),
						'limit'           => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of search results. Default 5 (action: search).', 'ollie-pro' ),
							'default'     => 5,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'include_content' => array(
							'type'        => 'boolean',
							'description' => __( 'Include full pattern block markup in search results. Default true (action: search).', 'ollie-pro' ),
							'default'     => true,
						),
						'threshold'       => array(
							'type'        => 'number',
							'description' => __( 'Minimum similarity score 0–1. Default 0.5 (action: search).', 'ollie-pro' ),
							'default'     => 0.5,
							'minimum'     => 0,
							'maximum'     => 1,
						),
						// Apply / Replace parameters.
						'pattern_slug'    => array(
							'type'        => 'string',
							'description' => __( 'Slug of a locally cached cloud pattern (e.g. "cloud/studio/hero-with-gallery"). Returned as "pattern_slug" in search results. Preferred over "content" — faster and more reliable since it reads from a local file.', 'ollie-pro' ),
						),
						'content'         => array(
							'type'        => 'string',
							'description' => __( 'Raw block markup. Fallback if "pattern_slug" is not available. You can pass the "content" field from a search result here.', 'ollie-pro' ),
						),
						'pattern_title'   => array(
							'type'        => 'string',
							'description' => __( 'Optional pattern title for labeling (action: apply, replace).', 'ollie-pro' ),
						),
						'post_id'         => array(
							'type'        => 'integer',
							'description' => __( 'Required for apply/replace. The post/page ID to insert into or replace within. Use ollie/manage-posts "list" to find page IDs.', 'ollie-pro' ),
						),
						'template'        => array(
							'type'        => 'string',
							'description' => __( 'Optional page template slug to set after applying (e.g. "page-no-title", "blank"). Only used on apply/replace.', 'ollie-pro' ),
						),
						'position'        => array(
							'type'        => 'string',
							'description' => __( 'Where to insert: "append" (default), "prepend", or "after:{index}" (action: apply).', 'ollie-pro' ),
							'default'     => 'append',
						),
						'section_index'   => array(
							'type'        => 'integer',
							'description' => __( 'Zero-based index of the top-level block to replace (action: replace).', 'ollie-pro' ),
						),
						'confirm'         => array(
							'type'        => 'boolean',
							'description' => __( 'Set to true to apply after previewing (action: apply, replace).', 'ollie-pro' ),
							'default'     => false,
						),
						'preview_token'   => array(
							'type'        => 'string',
							'description' => __( 'Token from the preview step. Required when confirm is true (action: apply, replace).', 'ollie-pro' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						// Search output.
						'success'        => array( 'type' => 'boolean' ),
						'query'          => array( 'type' => 'string' ),
						'total_results'  => array( 'type' => 'integer' ),
						'search_method'  => array( 'type' => 'string' ),
						'patterns'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'               => array( 'type' => 'integer' ),
									'slug'             => array( 'type' => 'string' ),
									'pattern_slug'     => array(
										'type'        => 'string',
										'description' => 'Use this value as the pattern_slug input for apply/replace.',
									),
									'title'            => array( 'type' => 'string' ),
									'content'          => array( 'type' => 'string' ),
									'meta'             => array( 'type' => 'object' ),
									'categories'       => array( 'type' => 'array' ),
									'collections'      => array( 'type' => 'array' ),
									'similarity_score' => array( 'type' => 'number' ),
								),
							),
						),
						// Apply / Replace output.
						'status'         => array( 'type' => 'string' ),
						'pattern_title'  => array( 'type' => 'string' ),
						'markup'         => array( 'type' => 'string' ),
						'validation'     => array( 'type' => 'object' ),
						'auto_fixes'     => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'preview_url'    => array( 'type' => 'string' ),
						'preview_token'  => array( 'type' => 'string' ),
						'post_id'        => array( 'type' => 'integer' ),
						'error'          => array( 'type' => 'string' ),
					),
					'additionalProperties' => true,
				),
				'execute_callback'    => static function ( $input = array() ) use ( $pattern_index, $block_validator, $design_linter, $preview_handler ) {
					$input  = is_array( $input ) ? $input : array();
					$action = $input['action'] ?? '';

					switch ( $action ) {
						case 'search':
							return self::execute_search( $input, $pattern_index );

						case 'apply':
							return self::execute_apply( $input, $pattern_index, $block_validator, $design_linter, $preview_handler );

						case 'replace':
							return self::execute_replace( $input, $pattern_index, $block_validator, $design_linter, $preview_handler );

						default:
							return new \WP_Error(
								'invalid_action',
								__( 'Invalid action. Use "search", "apply", or "replace".', 'ollie-pro' )
							);
					}
				},
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
	 * Execute search action.
	 *
	 * @param array              $input         Ability input.
	 * @param Ollie_Pattern_Index $pattern_index Pattern index.
	 * @return array
	 */
	private static function execute_search( array $input, Ollie_Pattern_Index $pattern_index ): array {
		$query           = $input['query'] ?? '';
		$limit           = $input['limit'] ?? 5;
		$include_content = $input['include_content'] ?? true;
		$threshold       = $input['threshold'] ?? 0.5;

		return $pattern_index->cloud_search(
			(string) $query,
			(int) $limit,
			(bool) $include_content,
			(float) $threshold
		);
	}

	/**
	 * Resolve pattern content from the content input.
	 *
	 * @param array $input Ability input.
	 * @return array{markup: string, title: string}|\WP_Error
	 */
	private static function resolve_pattern( array $input, Ollie_Pattern_Index $pattern_index ) {
		$title = $input['pattern_title'] ?? '';

		// Preferred: resolve from locally cached cloud pattern by slug.
		if ( ! empty( $input['pattern_slug'] ) ) {
			$cached = $pattern_index->get_cached_cloud_pattern( (string) $input['pattern_slug'] );
			if ( null !== $cached ) {
				return array(
					'markup' => $cached['content'],
					'title'  => $title ?: $cached['title'],
				);
			}

			return new \WP_Error(
				'pattern_not_cached',
				sprintf(
					__( 'Pattern "%s" not found in local cache. Run a "search" first to cache patterns, then use the "pattern_slug" from the results.', 'ollie-pro' ),
					$input['pattern_slug']
				)
			);
		}

		// Fallback: use raw content parameter.
		if ( ! empty( $input['content'] ) ) {
			return array( 'markup' => $input['content'], 'title' => $title );
		}

		return new \WP_Error(
			'missing_pattern',
			__( '"pattern_slug" or "content" parameter is required for apply/replace. First use action "search" to find and cache patterns, then use the "pattern_slug" from the results.', 'ollie-pro' )
		);
	}

	/**
	 * Validate that the post exists and the user can edit it.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private static function validate_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You cannot edit this post.', 'ollie-pro' ) );
		}
		return $post;
	}

	/**
	 * Execute apply action.
	 */
	private static function execute_apply(
		array $input,
		Ollie_Pattern_Index $pattern_index,
		Ollie_Block_Validator $block_validator,
		Ollie_Design_Linter $design_linter,
		Ollie_Preview_Handler $preview_handler
	) {
		// Validate required parameters up front with clear error messages.
		if ( empty( $input['post_id'] ) ) {
			return new \WP_Error(
				'missing_post_id',
				__( 'Missing required parameter "post_id" for apply action. Use ollie/manage-posts "list" to find page IDs.', 'ollie-pro' )
			);
		}

		$post_id  = (int) $input['post_id'];
		$position = $input['position'] ?? 'append';
		$confirm  = (bool) ( $input['confirm'] ?? false );
		$template = sanitize_text_field( $input['template'] ?? '' );

		$resolved = self::resolve_pattern( $input, $pattern_index );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$pattern_markup = $resolved['markup'];
		$pattern_title  = $resolved['title'];

		$post = self::validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Auto-fix common block markup issues before validation.
		$sanitized      = $block_validator->sanitize_block_markup( $pattern_markup );
		$pattern_markup = $sanitized['markup'];
		$auto_fixes     = $sanitized['fixes'];

		// Lint the sanitized pattern markup and summarize for compact output.
		$lint_result = $design_linter->lint( $pattern_markup );
		$validation  = $design_linter->summarize( $lint_result );

		// Build the new content.
		$existing_content = $post->post_content ?? '';
		$new_content      = self::merge_content( $existing_content, $pattern_markup, $position );

		// Preview step.
		if ( ! $confirm ) {
			$preview = $preview_handler->create_preview( $new_content );

			return array(
				'status'        => 'preview',
				'pattern_title' => $pattern_title,
				'markup'        => mb_substr( $pattern_markup, 0, 500 ) . ( mb_strlen( $pattern_markup ) > 500 ? '…' : '' ),
				'validation'    => $validation,
				'auto_fixes'    => $auto_fixes,
				'preview_url'   => $preview['preview_url'],
				'preview_token' => $preview['token'],
				'post_id'       => $post_id,
			);
		}

		// Apply step.
		$preview_token = $input['preview_token'] ?? '';
		if ( '' !== $preview_token ) {
			$preview_handler->delete_preview( $preview_token );
		}

		// Temporarily remove kses filters — MCP requires admin caps and kses
		// strips valid CSS transform values that blocks like outermost/icon-block need.
		kses_remove_filters();

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);

		kses_init_filters();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Set page template if provided.
		if ( '' !== $template ) {
			update_post_meta( $post_id, '_wp_page_template', $template );
		}

		return array(
			'status'        => 'applied',
			'pattern_title' => $pattern_title,
			'markup'        => mb_substr( $pattern_markup, 0, 500 ) . ( mb_strlen( $pattern_markup ) > 500 ? '…' : '' ),
			'validation'    => $validation,
			'auto_fixes'    => $auto_fixes,
			'post_id'       => $post_id,
		);
	}

	/**
	 * Execute replace action.
	 */
	private static function execute_replace(
		array $input,
		Ollie_Pattern_Index $pattern_index,
		Ollie_Block_Validator $block_validator,
		Ollie_Design_Linter $design_linter,
		Ollie_Preview_Handler $preview_handler
	) {
		// Validate required parameters up front with clear error messages.
		if ( empty( $input['post_id'] ) ) {
			return new \WP_Error(
				'missing_post_id',
				__( 'Missing required parameter "post_id" for replace action. Use ollie/manage-posts "list" to find page IDs.', 'ollie-pro' )
			);
		}
		if ( ! isset( $input['section_index'] ) ) {
			return new \WP_Error(
				'missing_section_index',
				__( 'Missing required parameter "section_index" for replace action. Use ollie/manage-content "list_blocks" to see top-level block indices.', 'ollie-pro' )
			);
		}

		$post_id       = (int) $input['post_id'];
		$section_index = (int) $input['section_index'];
		$confirm       = (bool) ( $input['confirm'] ?? false );
		$template      = sanitize_text_field( $input['template'] ?? '' );

		$resolved = self::resolve_pattern( $input, $pattern_index );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$pattern_markup = $resolved['markup'];
		$pattern_title  = $resolved['title'];

		$post = self::validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Auto-fix common block markup issues before validation.
		$sanitized      = $block_validator->sanitize_block_markup( $pattern_markup );
		$pattern_markup = $sanitized['markup'];
		$auto_fixes     = $sanitized['fixes'];

		$blocks = parse_blocks( $post->post_content ?? '' );

		// Filter out null/whitespace-only blocks for accurate indexing.
		$top_level = array();
		foreach ( $blocks as $idx => $block ) {
			if ( ! empty( $block['blockName'] ) ) {
				$top_level[] = $idx;
			}
		}

		if ( $section_index < 0 || $section_index >= count( $top_level ) ) {
			return new \WP_Error(
				'invalid_section',
				sprintf( __( 'Section index %d is out of range (0–%d).', 'ollie-pro' ), $section_index, max( 0, count( $top_level ) - 1 ) )
			);
		}

		// Lint the sanitized pattern markup and summarize for compact output.
		$lint_result = $design_linter->lint( $pattern_markup );
		$validation  = $design_linter->summarize( $lint_result );

		// Replace the block at the real index.
		$real_index         = $top_level[ $section_index ];
		$replacement_blocks = parse_blocks( $pattern_markup );
		array_splice( $blocks, $real_index, 1, $replacement_blocks );

		$new_content = '';
		foreach ( $blocks as $block ) {
			$new_content .= serialize_block( $block );
		}

		// Preview step.
		if ( ! $confirm ) {
			$preview = $preview_handler->create_preview( $new_content );

			return array(
				'status'        => 'preview',
				'pattern_title' => $pattern_title,
				'markup'        => mb_substr( $pattern_markup, 0, 500 ) . ( mb_strlen( $pattern_markup ) > 500 ? '…' : '' ),
				'validation'    => $validation,
				'auto_fixes'    => $auto_fixes,
				'preview_url'   => $preview['preview_url'],
				'preview_token' => $preview['token'],
				'post_id'       => $post_id,
			);
		}

		// Apply step.
		$preview_token = $input['preview_token'] ?? '';
		if ( '' !== $preview_token ) {
			$preview_handler->delete_preview( $preview_token );
		}

		// Temporarily remove kses filters — see execute_apply comment.
		kses_remove_filters();

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);

		kses_init_filters();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Set page template if provided.
		if ( '' !== $template ) {
			update_post_meta( $post_id, '_wp_page_template', $template );
		}

		return array(
			'status'        => 'applied',
			'pattern_title' => $pattern_title,
			'markup'        => mb_substr( $pattern_markup, 0, 500 ) . ( mb_strlen( $pattern_markup ) > 500 ? '…' : '' ),
			'validation'    => $validation,
			'auto_fixes'    => $auto_fixes,
			'post_id'       => $post_id,
		);
	}

	/**
	 * Merge pattern markup into existing post content at position.
	 *
	 * @param string $existing Existing post content.
	 * @param string $pattern  Pattern markup.
	 * @param string $position Position: "append", "prepend", or "after:{index}".
	 * @return string Merged content.
	 */
	private static function merge_content( string $existing, string $pattern, string $position ): string {
		if ( 'prepend' === $position ) {
			return $pattern . "\n\n" . $existing;
		}

		if ( 0 === strpos( $position, 'after:' ) ) {
			$index  = (int) substr( $position, 6 );
			$blocks = parse_blocks( $existing );

			$before = array_slice( $blocks, 0, $index + 1 );
			$after  = array_slice( $blocks, $index + 1 );

			$before_markup = '';
			foreach ( $before as $block ) {
				$before_markup .= serialize_block( $block );
			}

			$after_markup = '';
			foreach ( $after as $block ) {
				$after_markup .= serialize_block( $block );
			}

			return $before_markup . "\n\n" . $pattern . "\n\n" . $after_markup;
		}

		// Default: append.
		return $existing . "\n\n" . $pattern;
	}
}
