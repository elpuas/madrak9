<?php
/**
 * Ability: ollie/manage-content
 *
 * Block-level content manipulation for WordPress posts, pages, and custom post types.
 * Works with individual blocks to avoid large payload timeouts.
 *
 * Use ollie/manage-posts "list" to find post IDs, then use this ability
 * to read and modify content at the block level.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Abilities;

use olpo\Abilities\Validators\Ollie_Block_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manage_Content {

	/**
	 * Block validator instance.
	 *
	 * @var Ollie_Block_Validator|null
	 */
	private static ?Ollie_Block_Validator $block_validator = null;

	/**
	 * Register the ability.
	 *
	 * @param Ollie_Block_Validator|null $block_validator Block validator.
	 */
	public static function register( ?Ollie_Block_Validator $block_validator = null ): void {
		self::$block_validator = $block_validator;
		wp_register_ability(
			'ollie/manage-content',
			array(
				'label'               => __( 'Manage Content', 'ollie-pro' ),
				'description'         => __( 'Block-level content operations for WordPress posts, pages, and custom post types — replace, insert, or delete entire blocks by markup. Use ollie/manage-posts "list" to find post IDs, then "list_blocks" to see all blocks, "get_block" to read a single block\'s full markup, "update_block" to replace a block with new markup, "insert_block" to add a block at a position, "delete_block" to remove a block, or "batch_update" to replace multiple blocks in one call. Best for swapping out sections, restructuring layouts, or bulk content changes. For surgical attribute or inner-HTML edits on a single block, use ollie/manage-blocks instead.', 'ollie-pro' ),
				'category'            => 'ollie-design',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action', 'post_id' ),
					'properties'           => array(
						'action'       => array(
							'type'        => 'string',
							'enum'        => array( 'list_blocks', 'get_block', 'update_block', 'insert_block', 'delete_block', 'batch_update' ),
							'description' => __( 'The operation to perform on post blocks.', 'ollie-pro' ),
						),
						'post_id'      => array(
							'type'        => 'integer',
							'description' => __( 'Post ID to operate on.', 'ollie-pro' ),
						),
						'block_index'  => array(
							'type'        => 'integer',
							'description' => __( 'Zero-based index of the block. Required for get_block, update_block, insert_block, and delete_block.', 'ollie-pro' ),
						),
						'content'      => array(
							'type'        => 'string',
							'description' => __( 'Block markup for update_block and insert_block. Should be valid block markup for a single top-level block.', 'ollie-pro' ),
						),
						'updates'      => array(
							'type'        => 'array',
							'description' => __( 'Array of block updates for batch_update. Each item must have "block_index" (integer) and "content" (string). Indices refer to the original block positions before any modifications.', 'ollie-pro' ),
							'items'       => array(
								'type'       => 'object',
								'required'   => array( 'block_index', 'content' ),
								'properties' => array(
									'block_index' => array(
										'type'        => 'integer',
										'description' => __( 'Zero-based block index to replace.', 'ollie-pro' ),
									),
									'content'     => array(
										'type'        => 'string',
										'description' => __( 'New block markup.', 'ollie-pro' ),
									),
								),
							),
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
			case 'list_blocks':
				return self::handle_list_blocks( $input );
			case 'get_block':
				return self::handle_get_block( $input );
			case 'update_block':
				return self::handle_update_block( $input );
			case 'insert_block':
				return self::handle_insert_block( $input );
			case 'delete_block':
				return self::handle_delete_block( $input );
			case 'batch_update':
				return self::handle_batch_update( $input );
			default:
				return new \WP_Error( 'invalid_action', __( 'Action must be one of: list_blocks, get_block, update_block, insert_block, delete_block, batch_update.', 'ollie-pro' ) );
		}
	}

	/**
	 * Validate post access and return the post object.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $capability Capability to check (default edit_post).
	 * @return \WP_Post|\WP_Error
	 */
	private static function validate_post( int $post_id, string $capability = 'edit_post' ) {
		if ( $post_id < 1 ) {
			return new \WP_Error( 'missing_post_id', __( 'post_id is required.', 'ollie-pro' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( $capability, $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You do not have permission to access this post.', 'ollie-pro' ) );
		}

		return $post;
	}

	/**
	 * Parse a post's content into top-level blocks.
	 *
	 * Filters out empty/whitespace-only filler blocks that WordPress's parser
	 * creates between actual blocks.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Array of parsed block arrays.
	 */
	private static function parse_blocks( \WP_Post $post ): array {
		$all_blocks = parse_blocks( $post->post_content );

		// Filter out empty filler blocks (null blockName, whitespace-only).
		return array_values(
			array_filter(
				$all_blocks,
				static function ( $block ) {
					return ! empty( $block['blockName'] );
				}
			)
		);
	}

	/**
	 * Serialize an array of blocks back to markup.
	 *
	 * @param array $blocks Parsed block arrays.
	 * @return string Serialized block markup.
	 */
	private static function serialize_blocks( array $blocks ): string {
		$output = '';
		foreach ( $blocks as $block ) {
			$output .= serialize_block( $block );
		}

		return $output;
	}

	/**
	 * Save updated blocks back to the post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $blocks  Array of parsed block arrays.
	 * @return true|\WP_Error
	 */
	private static function save_blocks( int $post_id, array $blocks ) {
		$content = self::serialize_blocks( $blocks );

		if ( null !== self::$block_validator ) {
			$sanitized = self::$block_validator->sanitize_block_markup( $content );
			if ( is_array( $sanitized ) && isset( $sanitized['markup'] ) ) {
				$content = $sanitized['markup'];
			}
		}

		// Temporarily remove kses filters — MCP requires admin caps and the block
		// validator already sanitises structure.
		kses_remove_filters();

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			),
			true
		);

		kses_init_filters();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Build a compact block summary for list responses.
	 *
	 * @param array $block Parsed block array.
	 * @param int   $index Block index.
	 * @return array
	 */
	private static function block_summary( array $block, int $index ): array {
		$inner_count = ! empty( $block['innerBlocks'] ) ? count( $block['innerBlocks'] ) : 0;

		$summary = array(
			'index'      => $index,
			'block_name' => $block['blockName'],
			'attrs'      => $block['attrs'] ?? array(),
		);

		if ( $inner_count > 0 ) {
			$summary['inner_block_count'] = $inner_count;
		}

		// Include a short content preview (first 120 chars of innerHTML).
		$inner_html = trim( $block['innerHTML'] ?? '' );
		if ( '' !== $inner_html ) {
			$preview = mb_substr( wp_strip_all_tags( $inner_html ), 0, 120 );
			if ( '' !== $preview ) {
				$summary['preview'] = $preview;
			}
		}

		return $summary;
	}

	/**
	 * List all top-level blocks in a post.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_list_blocks( array $input ) {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$post    = self::validate_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$blocks    = self::parse_blocks( $post );
		$summaries = array();

		foreach ( $blocks as $index => $block ) {
			$summaries[] = self::block_summary( $block, $index );
		}

		return array(
			'post_id'     => $post_id,
			'title'       => $post->post_title,
			'block_count' => count( $blocks ),
			'blocks'      => $summaries,
		);
	}

	/**
	 * Get a single block's full markup by index.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_get_block( array $input ) {
		$post_id     = (int) ( $input['post_id'] ?? 0 );
		$block_index = $input['block_index'] ?? null;

		$post = self::validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( null === $block_index ) {
			return new \WP_Error( 'missing_block_index', __( 'block_index is required for get_block.', 'ollie-pro' ) );
		}

		$blocks      = self::parse_blocks( $post );
		$block_index = (int) $block_index;

		if ( $block_index < 0 || $block_index >= count( $blocks ) ) {
			return new \WP_Error(
				'invalid_block_index',
				sprintf(
					/* translators: 1: index requested, 2: total blocks */
					__( 'Block index %1$d is out of range. Post has %2$d blocks (0–%3$d).', 'ollie-pro' ),
					$block_index,
					count( $blocks ),
					count( $blocks ) - 1
				)
			);
		}

		$block   = $blocks[ $block_index ];
		$markup  = serialize_block( $block );

		return array(
			'post_id'     => $post_id,
			'block_index' => $block_index,
			'block_name'  => $block['blockName'],
			'content'     => $markup,
		);
	}

	/**
	 * Update (replace) a single block by index.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_update_block( array $input ) {
		$post_id     = (int) ( $input['post_id'] ?? 0 );
		$block_index = $input['block_index'] ?? null;
		$content     = $input['content'] ?? null;

		$post = self::validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( null === $block_index ) {
			return new \WP_Error( 'missing_block_index', __( 'block_index is required for update_block.', 'ollie-pro' ) );
		}
		if ( null === $content ) {
			return new \WP_Error( 'missing_content', __( 'content is required for update_block.', 'ollie-pro' ) );
		}

		$blocks      = self::parse_blocks( $post );
		$block_index = (int) $block_index;

		if ( $block_index < 0 || $block_index >= count( $blocks ) ) {
			return new \WP_Error(
				'invalid_block_index',
				sprintf(
					__( 'Block index %1$d is out of range. Post has %2$d blocks (0–%3$d).', 'ollie-pro' ),
					$block_index,
					count( $blocks ),
					count( $blocks ) - 1
				)
			);
		}

		// Parse the new content and replace the block.
		$new_blocks = parse_blocks( $content );
		$new_blocks = array_values(
			array_filter( $new_blocks, static function ( $b ) {
				return ! empty( $b['blockName'] );
			} )
		);

		if ( empty( $new_blocks ) ) {
			return new \WP_Error( 'invalid_content', __( 'The provided content does not contain valid block markup.', 'ollie-pro' ) );
		}

		$old_name = $blocks[ $block_index ]['blockName'];

		// Replace the single block with the parsed block(s).
		array_splice( $blocks, $block_index, 1, $new_blocks );

		$save_result = self::save_blocks( $post_id, $blocks );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return array(
			'post_id'         => $post_id,
			'block_index'     => $block_index,
			'old_block_name'  => $old_name,
			'new_block_name'  => $new_blocks[0]['blockName'],
			'blocks_inserted' => count( $new_blocks ),
			'total_blocks'    => count( $blocks ),
		);
	}

	/**
	 * Insert a new block at a given index.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_insert_block( array $input ) {
		$post_id     = (int) ( $input['post_id'] ?? 0 );
		$block_index = $input['block_index'] ?? null;
		$content     = $input['content'] ?? null;

		$post = self::validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( null === $block_index ) {
			return new \WP_Error( 'missing_block_index', __( 'block_index is required for insert_block. Use the index where the new block should appear.', 'ollie-pro' ) );
		}
		if ( null === $content ) {
			return new \WP_Error( 'missing_content', __( 'content is required for insert_block.', 'ollie-pro' ) );
		}

		$blocks      = self::parse_blocks( $post );
		$block_index = (int) $block_index;

		// Allow inserting at the end (block_index == count).
		if ( $block_index < 0 || $block_index > count( $blocks ) ) {
			return new \WP_Error(
				'invalid_block_index',
				sprintf(
					__( 'Block index %1$d is out of range for insert. Valid range is 0–%2$d.', 'ollie-pro' ),
					$block_index,
					count( $blocks )
				)
			);
		}

		$new_blocks = parse_blocks( $content );
		$new_blocks = array_values(
			array_filter( $new_blocks, static function ( $b ) {
				return ! empty( $b['blockName'] );
			} )
		);

		if ( empty( $new_blocks ) ) {
			return new \WP_Error( 'invalid_content', __( 'The provided content does not contain valid block markup.', 'ollie-pro' ) );
		}

		array_splice( $blocks, $block_index, 0, $new_blocks );

		$save_result = self::save_blocks( $post_id, $blocks );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return array(
			'post_id'         => $post_id,
			'block_index'     => $block_index,
			'blocks_inserted' => count( $new_blocks ),
			'total_blocks'    => count( $blocks ),
		);
	}

	/**
	 * Delete a block by index.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_delete_block( array $input ) {
		$post_id     = (int) ( $input['post_id'] ?? 0 );
		$block_index = $input['block_index'] ?? null;

		$post = self::validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( null === $block_index ) {
			return new \WP_Error( 'missing_block_index', __( 'block_index is required for delete_block.', 'ollie-pro' ) );
		}

		$blocks      = self::parse_blocks( $post );
		$block_index = (int) $block_index;

		if ( $block_index < 0 || $block_index >= count( $blocks ) ) {
			return new \WP_Error(
				'invalid_block_index',
				sprintf(
					__( 'Block index %1$d is out of range. Post has %2$d blocks (0–%3$d).', 'ollie-pro' ),
					$block_index,
					count( $blocks ),
					count( $blocks ) - 1
				)
			);
		}

		$removed_name = $blocks[ $block_index ]['blockName'];
		array_splice( $blocks, $block_index, 1 );

		$save_result = self::save_blocks( $post_id, $blocks );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return array(
			'post_id'          => $post_id,
			'deleted_index'    => $block_index,
			'deleted_block'    => $removed_name,
			'remaining_blocks' => count( $blocks ),
		);
	}

	/**
	 * Batch update multiple blocks in one call.
	 *
	 * Indices refer to the original block positions. Updates are applied
	 * from highest index to lowest to preserve position accuracy.
	 *
	 * @return array|\WP_Error
	 */
	private static function handle_batch_update( array $input ) {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$updates = $input['updates'] ?? null;

		$post = self::validate_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! is_array( $updates ) || empty( $updates ) ) {
			return new \WP_Error( 'missing_updates', __( 'updates array is required for batch_update. Each item needs block_index and content.', 'ollie-pro' ) );
		}

		$blocks      = self::parse_blocks( $post );
		$total       = count( $blocks );
		$applied     = array();

		// Sort updates by block_index descending so splicing doesn't shift later indices.
		usort( $updates, static function ( $a, $b ) {
			return ( $b['block_index'] ?? 0 ) - ( $a['block_index'] ?? 0 );
		} );

		foreach ( $updates as $update ) {
			$idx     = (int) ( $update['block_index'] ?? -1 );
			$markup  = $update['content'] ?? '';

			if ( $idx < 0 || $idx >= $total ) {
				return new \WP_Error(
					'invalid_block_index',
					sprintf(
						__( 'Block index %1$d is out of range in batch_update. Post has %2$d blocks (0–%3$d).', 'ollie-pro' ),
						$idx,
						$total,
						$total - 1
					)
				);
			}

			if ( empty( $markup ) ) {
				return new \WP_Error(
					'missing_content',
					sprintf(
						__( 'Content is required for block index %d in batch_update.', 'ollie-pro' ),
						$idx
					)
				);
			}

			$new_blocks = parse_blocks( $markup );
			$new_blocks = array_values(
				array_filter( $new_blocks, static function ( $b ) {
					return ! empty( $b['blockName'] );
				} )
			);

			if ( empty( $new_blocks ) ) {
				return new \WP_Error(
					'invalid_content',
					sprintf(
						__( 'Content for block index %d does not contain valid block markup.', 'ollie-pro' ),
						$idx
					)
				);
			}

			$old_name = $blocks[ $idx ]['blockName'];
			array_splice( $blocks, $idx, 1, $new_blocks );

			$applied[] = array(
				'block_index'    => $idx,
				'old_block_name' => $old_name,
				'new_block_name' => $new_blocks[0]['blockName'],
			);
		}

		$save_result = self::save_blocks( $post_id, $blocks );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		// Reverse so applied list is in ascending index order for readability.
		$applied = array_reverse( $applied );

		return array(
			'post_id'       => $post_id,
			'updates_count' => count( $applied ),
			'updates'       => $applied,
			'total_blocks'  => count( $blocks ),
		);
	}
}
