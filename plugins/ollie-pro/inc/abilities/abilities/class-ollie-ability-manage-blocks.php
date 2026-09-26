<?php
/**
 * Ability: ollie/manage-blocks
 *
 * Consolidated block management ability with actions:
 * - list-sections: List top-level blocks (sections) of a post with metadata.
 * - update:        Update a specific block's attributes and/or inner HTML.
 * - batch-update:  Update multiple blocks' attributes and/or inner HTML in one call.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Abilities;

use olpo\Abilities\Validators\Ollie_Block_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manage_Blocks {

	/**
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
			'ollie/manage-blocks',
			array(
				'label'               => __( 'Manage Blocks', 'ollie-pro' ),
				'description'         => __( 'Inspect and surgically modify individual block attributes or inner HTML — without replacing the entire block markup. Actions: "list-sections" (returns all top-level blocks with zero-based index, blockName, label, and text summary), "list-text" (returns every text block inside a section with its pre-computed index_path, block_type, and inner_html — use this before batch-update to get paths without parsing markup), "update" (modify a single block\'s attributes and/or innerHTML by index_path), "batch-update" (modify multiple blocks\' attributes and/or innerHTML in one call — preferred for content replacement). Recommended content-replace workflow: 1) list-sections to find target section, 2) list-text with section_index to get all text blocks and their paths, 3) batch-update with those paths and new inner_html. index_path is an array of zero-based integers navigating the block tree — [0] targets the first top-level block, [0, 2] targets the third inner block inside the first top-level block. For full block replacement, insertion, deletion, or section-level restructuring, use ollie/manage-content instead.', 'ollie-pro' ),
				'category'            => 'ollie-design',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action', 'post_id' ),
					'properties'           => array(
						'action'     => array(
							'type'        => 'string',
							'enum'        => array( 'list-sections', 'list-text', 'update', 'batch-update' ),
							'description' => __( 'Action to perform: "list-sections", "list-text", "update", or "batch-update".', 'ollie-pro' ),
						),
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'Post/page ID to inspect or modify.', 'ollie-pro' ),
						),
						// List-text parameters.
						'section_index' => array(
							'type'        => 'integer',
							'description' => __( 'Zero-based index of the top-level section to scan (action: list-text). Omit to scan all sections.', 'ollie-pro' ),
						),
						// Update parameters.
						'index_path' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( 'Array of zero-based indices to navigate to the target block (action: update). [0] = first top-level block, [0, 2] = third inner block of first top-level block.', 'ollie-pro' ),
						),
						'attributes' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'description'          => __( 'Block attributes to merge (action: update). Common Ollie Pro attributes: "ollieHoverColor" (hex), "ollieHoverBgColor" (hex), "animationType" (string: fadeIn, fadeInUp, fadeInDown, fadeInLeft, fadeInRight, zoomIn, pulse, scaleOnHover), "animationDuration" (number), "animationDelay" (number), "animateOnScroll" (bool), "className" (add standard CSS classes), "ollieCustomClasses" (array of Ollie CSS Manager class names, e.g. ["my-class"]), "ollieButtonIcon" (icon name).', 'ollie-pro' ),
						),
						'inner_html' => array(
							'type'        => 'string',
							'description' => __( 'Replace the block\'s inner HTML content (action: update). For simple blocks like paragraphs or headings, this replaces the text content.', 'ollie-pro' ),
						),
						'updates'    => array(
							'type'        => 'array',
							'items'       => array(
								'type'                 => 'object',
								'required'             => array( 'index_path' ),
								'properties'           => array(
									'index_path' => array(
										'type'        => 'array',
										'items'       => array( 'type' => 'integer' ),
										'description' => __( 'Array of zero-based indices to navigate to the target block.', 'ollie-pro' ),
									),
									'attributes' => array(
										'type'                 => 'object',
										'additionalProperties' => true,
										'description'          => __( 'Block attributes to merge.', 'ollie-pro' ),
									),
									'inner_html' => array(
										'type'        => 'string',
										'description' => __( 'Replace the block\'s inner HTML content.', 'ollie-pro' ),
									),
								),
								'additionalProperties' => false,
							),
							'description' => __( 'Array of block updates for batch-update action. Each item needs index_path and at least one of attributes or inner_html. Use this for fast content replacement — swap text across multiple blocks in one call while preserving block structure.', 'ollie-pro' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						// List sections output.
						'post_id'    => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'sections'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'index'      => array( 'type' => 'integer' ),
									'block_name' => array( 'type' => 'string' ),
									'label'      => array( 'type' => 'string' ),
									'summary'    => array( 'type' => 'string' ),
								),
							),
						),
						// List-text output.
						'text_blocks' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'index_path' => array(
										'type'  => 'array',
										'items' => array( 'type' => 'integer' ),
									),
									'block_name' => array( 'type' => 'string' ),
									'block_type' => array( 'type' => 'string' ),
									'inner_html' => array( 'type' => 'string' ),
								),
							),
						),
						// Update output.
						'success'    => array( 'type' => 'boolean' ),
						'block_name' => array( 'type' => 'string' ),
						'updated'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						// Batch-update output.
						'updates_applied' => array( 'type' => 'integer' ),
						'details'         => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'index_path'  => array(
										'type'  => 'array',
										'items' => array( 'type' => 'integer' ),
									),
									'block_name'  => array( 'type' => 'string' ),
									'updated'     => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( self::class, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
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

		switch ( $action ) {
			case 'list-sections':
				return self::execute_list_sections( $input );

			case 'list-text':
				return self::execute_list_text( $input );

			case 'update':
				return self::execute_update( $input );

			case 'batch-update':
				return self::execute_batch_update( $input );

			default:
				return new \WP_Error(
					'invalid_action',
					__( 'Invalid action. Use "list-sections", "list-text", "update", or "batch-update".', 'ollie-pro' )
				);
		}
	}

	/**
	 * List top-level blocks (sections) of a post.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	private static function execute_list_sections( array $input ) {
		$post_id = (int) ( $input['post_id'] ?? 0 );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You cannot edit this post.', 'ollie-pro' ) );
		}

		$blocks   = parse_blocks( $post->post_content ?? '' );
		$sections = array();
		$idx      = 0;

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$label = $block['attrs']['metadata']['name'] ?? '';
			if ( '' === $label ) {
				$label = self::humanize_block_name( $block['blockName'] );
			}

			$summary = self::extract_summary( $block );

			$sections[] = array(
				'index'      => $idx,
				'block_name' => $block['blockName'],
				'label'      => $label,
				'summary'    => $summary,
			);

			$idx++;
		}

		return array(
			'post_id'  => $post_id,
			'title'    => get_the_title( $post_id ),
			'sections' => $sections,
		);
	}

	/**
	 * List all text-bearing blocks within a section (or all sections).
	 *
	 * Server-side equivalent of the client-side collectTextBlocks() used by
	 * the AI rewrite feature. Returns each text block with its pre-computed
	 * index_path so agents can feed paths directly into batch-update.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	private static function execute_list_text( array $input ) {
		$post_id       = (int) ( $input['post_id'] ?? 0 );
		$section_index = isset( $input['section_index'] ) ? (int) $input['section_index'] : null;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You cannot edit this post.', 'ollie-pro' ) );
		}

		$blocks = parse_blocks( $post->post_content ?? '' );
		$blocks = array_values( array_filter( $blocks, function ( $b ) {
			return ! empty( $b['blockName'] );
		} ) );

		if ( null !== $section_index ) {
			if ( ! isset( $blocks[ $section_index ] ) ) {
				return new \WP_Error(
					'section_not_found',
					sprintf( __( 'Section not found at index %d.', 'ollie-pro' ), $section_index )
				);
			}
			$text_blocks = self::collect_text_blocks( $blocks[ $section_index ], array( $section_index ) );
		} else {
			$text_blocks = array();
			foreach ( $blocks as $idx => $block ) {
				$text_blocks = array_merge( $text_blocks, self::collect_text_blocks( $block, array( $idx ) ) );
			}
		}

		return array(
			'post_id'     => $post_id,
			'title'       => get_the_title( $post_id ),
			'text_blocks' => $text_blocks,
		);
	}

	/**
	 * Block types that carry editable text content.
	 */
	private const TEXT_BLOCK_TYPES = array(
		'core/heading'      => 'heading',
		'core/paragraph'    => 'paragraph',
		'core/button'       => 'button',
		'core/list-item'    => 'list item',
		'core/verse'        => 'verse',
		'core/preformatted' => 'preformatted',
		'core/pullquote'    => 'pullquote',
		'core/quote'        => 'quote',
	);

	/**
	 * Recursively collect text blocks from a parsed block tree.
	 *
	 * @param array $block      Parsed block.
	 * @param array $path       Current index path to this block.
	 * @return array Array of text block entries.
	 */
	private static function collect_text_blocks( array $block, array $path ): array {
		$results = array();

		$block_name = $block['blockName'] ?? '';

		if ( isset( self::TEXT_BLOCK_TYPES[ $block_name ] ) ) {
			$inner_html = '';
			foreach ( $block['innerContent'] ?? array() as $chunk ) {
				if ( is_string( $chunk ) ) {
					$inner_html .= $chunk;
				}
			}

			// Only include if the block has visible text content.
			$text = trim( wp_strip_all_tags( $inner_html ) );
			if ( '' !== $text ) {
				$results[] = array(
					'index_path' => $path,
					'block_name' => $block_name,
					'block_type' => self::TEXT_BLOCK_TYPES[ $block_name ],
					'inner_html' => $inner_html,
				);
			}
		}

		// Recurse into inner blocks.
		if ( ! empty( $block['innerBlocks'] ) ) {
			$inner_blocks = array_values( array_filter(
				$block['innerBlocks'],
				function ( $b ) {
					return ! empty( $b['blockName'] );
				}
			) );

			foreach ( $inner_blocks as $child_idx => $child ) {
				$child_path = array_merge( $path, array( $child_idx ) );
				$results    = array_merge( $results, self::collect_text_blocks( $child, $child_path ) );
			}
		}

		return $results;
	}

	/**
	 * Update a specific block within a post.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	private static function execute_update( array $input ) {
		$post_id    = (int) ( $input['post_id'] ?? 0 );
		$index_path = $input['index_path'] ?? array();
		$attributes = $input['attributes'] ?? null;
		$inner_html = $input['inner_html'] ?? null;

		// Backward-compat: expand legacy ollieAnimation object into individual attributes.
		if ( is_array( $attributes ) ) {
			$attributes = self::prepare_attributes( $attributes );
		}

		if ( null === $attributes && null === $inner_html ) {
			return new \WP_Error( 'nothing_to_update', __( 'Provide at least "attributes" or "inner_html" to update.', 'ollie-pro' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You cannot edit this post.', 'ollie-pro' ) );
		}

		if ( ! is_array( $index_path ) || empty( $index_path ) ) {
			return new \WP_Error( 'invalid_index_path', __( 'index_path must be a non-empty array of integers.', 'ollie-pro' ) );
		}

		$blocks = parse_blocks( $post->post_content ?? '' );

		// Filter out empty/whitespace-only blocks for consistent indexing.
		$blocks = array_values( array_filter( $blocks, function ( $b ) {
			return ! empty( $b['blockName'] );
		} ) );

		// Navigate to the target block.
		$target  = &$blocks;
		$updated = array();

		for ( $i = 0; $i < count( $index_path ); $i++ ) {
			$idx = (int) $index_path[ $i ];

			if ( $i === count( $index_path ) - 1 ) {
				// Last index — this is our target block.
				if ( ! isset( $target[ $idx ] ) ) {
					return new \WP_Error( 'block_not_found', sprintf( __( 'Block not found at index %d.', 'ollie-pro' ), $idx ) );
				}

				$block_name = $target[ $idx ]['blockName'];

				// Merge attributes.
				if ( null !== $attributes && is_array( $attributes ) ) {
					$target[ $idx ]['attrs'] = array_merge( $target[ $idx ]['attrs'] ?? array(), $attributes );
					$updated[] = 'attributes';
				}

				// Replace inner HTML.
				if ( null !== $inner_html ) {
					$target[ $idx ]['innerHTML']    = $inner_html;
					$target[ $idx ]['innerContent'] = array( $inner_html );
					$updated[] = 'inner_html';
				}
			} else {
				// Navigate deeper into innerBlocks.
				if ( ! isset( $target[ $idx ] ) || ! isset( $target[ $idx ]['innerBlocks'] ) ) {
					return new \WP_Error( 'block_not_found', sprintf( __( 'Block not found at index path position %d.', 'ollie-pro' ), $i ) );
				}

				// Filter inner blocks too.
				$target[ $idx ]['innerBlocks'] = array_values( array_filter(
					$target[ $idx ]['innerBlocks'],
					function ( $b ) {
						return ! empty( $b['blockName'] );
					}
				) );

				$target = &$target[ $idx ]['innerBlocks'];
			}
		}

		// Serialize blocks back to post content and run through block validator.
		$new_content = serialize_blocks( $blocks );

		if ( null !== self::$block_validator ) {
			$sanitized = self::$block_validator->sanitize_block_markup( $new_content );
			if ( is_array( $sanitized ) && isset( $sanitized['markup'] ) ) {
				$new_content = $sanitized['markup'];
			}
		}

		// Temporarily remove kses filters — MCP requires admin caps and the block
		// validator already sanitises structure.
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

		return array(
			'success'    => true,
			'post_id'    => $post_id,
			'block_name' => $block_name ?? 'unknown',
			'updated'    => $updated,
		);
	}

	/**
	 * Batch update multiple blocks' attributes and/or inner HTML.
	 *
	 * Applies all changes to the in-memory block tree, then serializes,
	 * validates, and saves once. Atomic — if any single update fails
	 * validation, the entire batch is rejected with no partial writes.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	private static function execute_batch_update( array $input ) {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$updates = $input['updates'] ?? null;

		if ( ! is_array( $updates ) || empty( $updates ) ) {
			return new \WP_Error( 'missing_updates', __( 'updates array is required for batch-update. Each item needs index_path and at least one of attributes or inner_html.', 'ollie-pro' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'ollie-pro' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'unauthorized', __( 'You cannot edit this post.', 'ollie-pro' ) );
		}

		$blocks = parse_blocks( $post->post_content ?? '' );

		// Filter out empty/whitespace-only blocks for consistent indexing.
		$blocks = array_values( array_filter( $blocks, function ( $b ) {
			return ! empty( $b['blockName'] );
		} ) );

		$details = array();

		foreach ( $updates as $ui => $update ) {
			$index_path = $update['index_path'] ?? array();
			$attributes = $update['attributes'] ?? null;
			$inner_html = $update['inner_html'] ?? null;

			if ( ! is_array( $index_path ) || empty( $index_path ) ) {
				return new \WP_Error(
					'invalid_index_path',
					sprintf(
						__( 'updates[%d]: index_path must be a non-empty array of integers.', 'ollie-pro' ),
						$ui
					)
				);
			}

			if ( null === $attributes && null === $inner_html ) {
				return new \WP_Error(
					'nothing_to_update',
					sprintf(
						__( 'updates[%d]: provide at least "attributes" or "inner_html".', 'ollie-pro' ),
						$ui
					)
				);
			}

			if ( is_array( $attributes ) ) {
				$attributes = self::prepare_attributes( $attributes );
			}

			// Navigate to the target block.
			$target = &$blocks;
			$updated_fields = array();

			for ( $i = 0; $i < count( $index_path ); $i++ ) {
				$idx = (int) $index_path[ $i ];

				if ( $i === count( $index_path ) - 1 ) {
					// Last index — this is our target block.
					if ( ! isset( $target[ $idx ] ) ) {
						return new \WP_Error(
							'block_not_found',
							sprintf(
								__( 'updates[%1$d]: block not found at index %2$d.', 'ollie-pro' ),
								$ui,
								$idx
							)
						);
					}

					$block_name = $target[ $idx ]['blockName'];

					// Merge attributes.
					if ( null !== $attributes && is_array( $attributes ) ) {
						$target[ $idx ]['attrs'] = array_merge( $target[ $idx ]['attrs'] ?? array(), $attributes );
						$updated_fields[] = 'attributes';
					}

					// Replace inner HTML.
					if ( null !== $inner_html ) {
						$target[ $idx ]['innerHTML']    = $inner_html;
						$target[ $idx ]['innerContent'] = array( $inner_html );
						$updated_fields[] = 'inner_html';
					}
				} else {
					// Navigate deeper into innerBlocks.
					if ( ! isset( $target[ $idx ] ) || ! isset( $target[ $idx ]['innerBlocks'] ) ) {
						return new \WP_Error(
							'block_not_found',
							sprintf(
								__( 'updates[%1$d]: block not found at index path position %2$d.', 'ollie-pro' ),
								$ui,
								$i
							)
						);
					}

					// Filter inner blocks too.
					$target[ $idx ]['innerBlocks'] = array_values( array_filter(
						$target[ $idx ]['innerBlocks'],
						function ( $b ) {
							return ! empty( $b['blockName'] );
						}
					) );

					$target = &$target[ $idx ]['innerBlocks'];
				}
			}

			// Must unset reference before next iteration to avoid corruption.
			unset( $target );

			$details[] = array(
				'index_path' => $index_path,
				'block_name' => $block_name ?? 'unknown',
				'updated'    => $updated_fields,
			);
		}

		// Serialize blocks back to post content and run through block validator.
		$new_content = serialize_blocks( $blocks );

		if ( null !== self::$block_validator ) {
			$sanitized = self::$block_validator->sanitize_block_markup( $new_content );
			if ( is_array( $sanitized ) && isset( $sanitized['markup'] ) ) {
				$new_content = $sanitized['markup'];
			}
		}

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

		return array(
			'success'         => true,
			'post_id'         => $post_id,
			'updates_applied' => count( $details ),
			'details'         => $details,
		);
	}

	/**
	 * Prepare attributes for merge — expands legacy ollieAnimation object.
	 *
	 * @param array $attributes Raw attributes from input.
	 * @return array Prepared attributes.
	 */
	private static function prepare_attributes( array $attributes ): array {
		if ( isset( $attributes['ollieAnimation'] ) && is_array( $attributes['ollieAnimation'] ) ) {
			$anim = $attributes['ollieAnimation'];
			unset( $attributes['ollieAnimation'] );

			$anim_map = array(
				'type'     => 'animationType',
				'duration' => 'animationDuration',
				'delay'    => 'animationDelay',
				'distance' => 'animationDistance',
				'scale'    => 'animationScale',
				'easing'   => 'animationEasing',
			);

			foreach ( $anim_map as $old_key => $new_key ) {
				if ( isset( $anim[ $old_key ] ) && ! isset( $attributes[ $new_key ] ) ) {
					$attributes[ $new_key ] = $anim[ $old_key ];
				}
			}

			foreach ( $anim as $key => $value ) {
				if ( ! isset( $anim_map[ $key ] ) && ! isset( $attributes[ $key ] ) ) {
					$attributes[ $key ] = $value;
				}
			}
		}

		return $attributes;
	}

	/**
	 * Convert block name to a human-readable label.
	 *
	 * @param string $name Block name (e.g. "core/group").
	 * @return string Human-readable label.
	 */
	private static function humanize_block_name( string $name ): string {
		$parts = explode( '/', $name );
		$label = end( $parts );
		return ucwords( str_replace( '-', ' ', $label ) );
	}

	/**
	 * Extract a brief text summary from a block's inner content.
	 *
	 * @param array $block Parsed block.
	 * @return string Summary (truncated to ~120 characters).
	 */
	private static function extract_summary( array $block ): string {
		$html = '';
		foreach ( $block['innerContent'] ?? array() as $chunk ) {
			if ( is_string( $chunk ) ) {
				$html .= $chunk;
			}
		}

		// Also recurse one level into inner blocks.
		foreach ( $block['innerBlocks'] ?? array() as $inner ) {
			foreach ( $inner['innerContent'] ?? array() as $chunk ) {
				if ( is_string( $chunk ) ) {
					$html .= ' ' . $chunk;
				}
			}
		}

		$text = wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', trim( $text ) );

		if ( mb_strlen( $text ) > 120 ) {
			$text = mb_substr( $text, 0, 117 ) . '...';
		}

		return $text;
	}
}
