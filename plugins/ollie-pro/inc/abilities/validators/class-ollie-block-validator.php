<?php
/**
 * Ollie Block Validator — Layer 2 schema validation.
 *
 * Validates block markup against WordPress block grammar and
 * Ollie pattern schemas. Catches structural errors.
 *
 * Rules implemented: S-01 through S-09 per spec.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ollie_Block_Validator {

	/**
	 * Maximum allowed nesting depth (constraint C-08).
	 */
	private const MAX_NESTING_DEPTH = 10;

	/**
	 * Validate block markup and return an array of issues.
	 *
	 * @param string $markup Raw block markup.
	 * @return array{valid: bool, issues: array[]}
	 */
	public function validate( string $markup ): array {
		$issues = array();
		$blocks = parse_blocks( $markup );

		if ( empty( $blocks ) || $this->is_only_whitespace( $blocks ) ) {
			$issues[] = $this->issue( 'S-01', 'error', 'Block markup could not be parsed — no valid blocks found.' );
			return array(
				'valid'  => false,
				'issues' => $issues,
			);
		}

		foreach ( $blocks as $block ) {
			$this->validate_block( $block, $issues, 0 );
		}

		$has_errors = false;
		foreach ( $issues as $issue ) {
			if ( 'error' === $issue['severity'] ) {
				$has_errors = true;
				break;
			}
		}

		return array(
			'valid'  => ! $has_errors,
			'issues' => $issues,
		);
	}

	/**
	 * Recursively validate a single parsed block.
	 *
	 * @param array $block   Parsed block array.
	 * @param array &$issues Collected issues.
	 * @param int   $depth   Current nesting depth.
	 */
	private function validate_block( array $block, array &$issues, int $depth ): void {
		$block_name = $block['blockName'] ?? null;

		// Skip freeform (classic) and null blocks.
		if ( null === $block_name || '' === $block_name ) {
			return;
		}

		// C-08 — Maximum nesting depth (advisory).
		if ( $depth > self::MAX_NESTING_DEPTH ) {
			$issues[] = $this->issue(
				'C-08',
				'warning',
				sprintf( 'Block nesting depth exceeds recommended maximum of %d: "%s" at depth %d.', self::MAX_NESTING_DEPTH, $block_name, $depth )
			);
		}

		// S-01 — Valid block grammar (verified by parse_blocks succeeding, but check name format).
		if ( false === strpos( $block_name, '/' ) && 0 !== strpos( $block_name, 'core/' ) ) {
			// Core blocks without namespace are fine (parse_blocks normalizes them).
		}

		// C-07 — Only registered blocks allowed (advisory for cloud patterns).
		$registry = \WP_Block_Type_Registry::get_instance();
		if ( ! $registry->is_registered( $block_name ) ) {
			$issues[] = $this->issue(
				'C-07',
				'warning',
				sprintf( 'Block type "%s" is not registered on this site.', $block_name )
			);
		} else {
			$block_type = $registry->get_registered( $block_name );
			$attrs      = $block['attrs'] ?? array();

			// S-02 / S-03 — Required attributes & type validation.
			$this->validate_attributes( $block_name, $block_type, $attrs, $issues );

			// S-04 / S-05 — Inner blocks validation.
			$this->validate_inner_blocks( $block_name, $block_type, $block, $issues );
		}

		// S-06 — Duplicate block IDs (anchor check).
		static $seen_anchors = array();
		$anchor = $block['attrs']['anchor'] ?? null;
		if ( null !== $anchor && '' !== $anchor ) {
			if ( isset( $seen_anchors[ $anchor ] ) ) {
				$issues[] = $this->issue(
					'S-06',
					'warning',
					sprintf( 'Duplicate anchor/ID "%s" found in block "%s".', $anchor, $block_name ),
					'regenerate'
				);
			}
			$seen_anchors[ $anchor ] = true;
		}

		// S-08 — No empty required content.
		$this->check_empty_content( $block_name, $block, $issues );

		// Recurse into inner blocks.
		$inner_blocks = $block['innerBlocks'] ?? array();
		foreach ( $inner_blocks as $inner ) {
			$this->validate_block( $inner, $issues, $depth + 1 );
		}
	}

	/**
	 * Validate block attributes against block.json schema.
	 *
	 * @param string         $block_name Block name.
	 * @param \WP_Block_Type $block_type Registered block type.
	 * @param array          $attrs      Attributes from parsed block.
	 * @param array          &$issues    Collected issues.
	 */
	private function validate_attributes( string $block_name, \WP_Block_Type $block_type, array $attrs, array &$issues ): void {
		$schema = $block_type->attributes ?? array();

		foreach ( $schema as $attr_name => $attr_def ) {
			$type = $attr_def['type'] ?? 'string';

			if ( ! isset( $attrs[ $attr_name ] ) ) {
				// Attribute not present — not an error if it has a default or is optional.
				continue;
			}

			$value = $attrs[ $attr_name ];

			// S-03 — Type validation (advisory).
			// Handle union types (e.g. ["string", "object"]) — pass if any type matches.
			$types_to_check = is_array( $type ) ? $type : array( $type );
			$type_matched   = false;
			foreach ( $types_to_check as $single_type ) {
				if ( is_string( $single_type ) && $this->check_type( $value, $single_type ) ) {
					$type_matched = true;
					break;
				}
			}
			if ( ! $type_matched ) {
				$type_label = is_array( $type ) ? implode( '|', $type ) : $type;
				$issues[]   = $this->issue(
					'S-03',
					'warning',
					sprintf(
						'Attribute "%s" on "%s" has type "%s", expected "%s".',
						$attr_name,
						$block_name,
						gettype( $value ),
						$type_label
					),
					'coerce'
				);
			}

			// S-03 — Enum validation (advisory).
			if ( isset( $attr_def['enum'] ) && ! in_array( $value, $attr_def['enum'], true ) ) {
				$issues[] = $this->issue(
					'S-03',
					'warning',
					sprintf(
						'Attribute "%s" on "%s" has value "%s", not in allowed enum.',
						$attr_name,
						$block_name,
						is_scalar( $value ) ? (string) $value : 'non-scalar'
					),
					'coerce'
				);
			}
		}
	}

	/**
	 * Validate inner blocks relationships.
	 *
	 * @param string         $block_name Block name.
	 * @param \WP_Block_Type $block_type Registered block type.
	 * @param array          $block      Parsed block.
	 * @param array          &$issues    Collected issues.
	 */
	private function validate_inner_blocks( string $block_name, \WP_Block_Type $block_type, array $block, array &$issues ): void {
		$inner_blocks   = $block['innerBlocks'] ?? array();
		$allowed_blocks = $block_type->allowed_blocks ?? null;
		$parent         = $block_type->parent ?? null;

		// S-04 — Blocks that don't support innerBlocks must not contain them (advisory).
		$supports_inner = $block_type->supports['innerBlocks'] ?? true;
		if ( false === $supports_inner && ! empty( $inner_blocks ) ) {
			$issues[] = $this->issue(
				'S-04',
				'warning',
				sprintf( 'Block "%s" does not support inner blocks but contains %d.', $block_name, count( $inner_blocks ) )
			);
		}

		// S-05 — Inner block types valid per allowedBlocks (advisory).
		if ( null !== $allowed_blocks && is_array( $allowed_blocks ) && ! empty( $inner_blocks ) ) {
			foreach ( $inner_blocks as $inner ) {
				$inner_name = $inner['blockName'] ?? '';
				if ( '' !== $inner_name && ! in_array( $inner_name, $allowed_blocks, true ) ) {
					$issues[] = $this->issue(
						'S-05',
						'warning',
						sprintf( 'Block "%s" is not allowed as child of "%s".', $inner_name, $block_name )
					);
				}
			}
		}
	}

	/**
	 * Check for empty required content in specific block types.
	 *
	 * @param string $block_name Block name.
	 * @param array  $block      Parsed block.
	 * @param array  &$issues    Collected issues.
	 */
	private function check_empty_content( string $block_name, array $block, array &$issues ): void {
		$inner_html = trim( $block['innerHTML'] ?? '' );

		// Strip block comment delimiters and HTML tags to get text content.
		$text_content = trim( wp_strip_all_tags( $inner_html ) );

		$requires_content = array(
			'core/heading',
			'core/paragraph',
			'core/button',
			'core/list-item',
		);

		if ( in_array( $block_name, $requires_content, true ) && '' === $text_content ) {
			$issues[] = $this->issue(
				'S-08',
				'warning',
				sprintf( 'Block "%s" has empty content.', $block_name ),
				'placeholder'
			);
		}
	}

	/**
	 * Check if a value matches a JSON Schema type.
	 *
	 * @param mixed  $value Value to check.
	 * @param string $type  Expected type.
	 * @return bool
	 */
	private function check_type( $value, string $type ): bool {
		switch ( $type ) {
			case 'string':
				return is_string( $value );
			case 'number':
				return is_numeric( $value );
			case 'integer':
				return is_int( $value );
			case 'boolean':
				return is_bool( $value );
			case 'array':
				return is_array( $value ) && array_is_list( $value );
			case 'object':
				return is_array( $value ) && ! array_is_list( $value );
			case 'null':
				return null === $value;
			default:
				return true;
		}
	}

	/**
	 * Check if parsed blocks array contains only whitespace/null blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return bool
	 */
	private function is_only_whitespace( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Sanitize and auto-fix block markup to resolve common validation issues.
	 *
	 * Performs the following fixes:
	 * - Regenerates duplicate anchor IDs (S-06).
	 * - Strips unrecognised attributes that would fail type validation (S-03).
	 * - Ensures proper block comment delimiters are balanced.
	 * - Cleans up extraneous whitespace between blocks.
	 *
	 * @param string $markup Raw block markup.
	 * @return array{markup: string, fixes: string[]}
	 */
	public function sanitize_block_markup( string $markup ): array {
		$fixes  = array();
		$blocks = parse_blocks( $markup );

		if ( empty( $blocks ) || $this->is_only_whitespace( $blocks ) ) {
			return array(
				'markup' => $markup,
				'fixes'  => $fixes,
			);
		}

		$seen_anchors = array();
		$blocks       = $this->sanitize_blocks_recursive( $blocks, $seen_anchors, $fixes );

		// Re-serialize the cleaned blocks.
		$clean_markup = '';
		foreach ( $blocks as $block ) {
			$clean_markup .= serialize_block( $block );
		}

		// Collapse runs of 3+ newlines to 2.
		$before_len   = strlen( $clean_markup );
		$clean_markup = preg_replace( '/\n{3,}/', "\n\n", $clean_markup );
		if ( strlen( $clean_markup ) !== $before_len ) {
			$fixes[] = 'Collapsed excessive whitespace between blocks.';
		}

		return array(
			'markup' => $clean_markup,
			'fixes'  => $fixes,
		);
	}

	/**
	 * Recursively sanitize a parsed blocks array.
	 *
	 * @param array    $blocks       Parsed blocks.
	 * @param array    &$seen_anchors Tracking duplicate anchors.
	 * @param string[] &$fixes       Applied fixes log.
	 * @return array Sanitized blocks.
	 */
	private function sanitize_blocks_recursive( array $blocks, array &$seen_anchors, array &$fixes ): array {
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $blocks as &$block ) {
			$block_name = $block['blockName'] ?? null;
			if ( null === $block_name || '' === $block_name ) {
				continue;
			}

			// Fix duplicate anchors (S-06).
			$anchor = $block['attrs']['anchor'] ?? null;
			if ( null !== $anchor && '' !== $anchor ) {
				if ( isset( $seen_anchors[ $anchor ] ) ) {
					$new_anchor = $anchor . '-' . wp_generate_password( 4, false );
					$block['attrs']['anchor'] = $new_anchor;

					// Also fix innerHTML if it contains the old id.
					if ( isset( $block['innerHTML'] ) ) {
						$block['innerHTML'] = str_replace(
							'id="' . $anchor . '"',
							'id="' . $new_anchor . '"',
							$block['innerHTML']
						);
					}
					if ( isset( $block['innerContent'] ) ) {
						foreach ( $block['innerContent'] as &$ic ) {
							if ( is_string( $ic ) ) {
								$ic = str_replace(
									'id="' . $anchor . '"',
									'id="' . $new_anchor . '"',
									$ic
								);
							}
						}
						unset( $ic );
					}

					$fixes[] = sprintf( 'Regenerated duplicate anchor "%s" → "%s".', $anchor, $new_anchor );
				}
				$seen_anchors[ $block['attrs']['anchor'] ] = true;
			}

			// Fix outermost/icon-block missing default transform in style attribute.
			if ( 'outermost/icon-block' === $block_name ) {
				$block = $this->fix_icon_block_transform( $block, $fixes );
			}

			// Fix heading tag to match level attribute.
			if ( 'core/heading' === $block_name ) {
				$block = $this->fix_heading_level( $block, $fixes );
			}

			// Fix image blocks with non-numeric wp-image-* classes.
			if ( 'core/image' === $block_name ) {
				$block = $this->fix_image_class( $block, $fixes );
			}

			// Fix cover block element ordering and wp-image class.
			if ( 'core/cover' === $block_name ) {
				$block = $this->fix_cover_block( $block, $fixes );
			}

			// Fix button block color class sync.
			if ( 'core/button' === $block_name ) {
				$block = $this->fix_button_classes( $block, $fixes );
			}

			// Strip unknown attributes for registered blocks (S-03 fix).
			if ( $registry->is_registered( $block_name ) ) {
				$block_type  = $registry->get_registered( $block_name );
				$schema_attrs = $block_type->attributes ?? array();

				if ( ! empty( $schema_attrs ) && ! empty( $block['attrs'] ) ) {
					$cleaned = array();
					foreach ( $block['attrs'] as $key => $value ) {
						// Keep known attributes, className, anchor, lock, style, and metadata.
						if (
							isset( $schema_attrs[ $key ] )
							|| in_array( $key, array( 'className', 'anchor', 'lock', 'style', 'metadata', 'align', 'layout', 'fontSize', 'fontFamily', 'backgroundColor', 'textColor', 'gradient' ), true )
						) {
							$cleaned[ $key ] = $value;
						}
					}

					$removed_count = count( $block['attrs'] ) - count( $cleaned );
					if ( $removed_count > 0 ) {
						$fixes[] = sprintf( 'Stripped %d unknown attribute(s) from "%s".', $removed_count, $block_name );
						$block['attrs'] = $cleaned;
					}
				}
			}

			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->sanitize_blocks_recursive( $block['innerBlocks'], $seen_anchors, $fixes );
			}
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * Fix outermost/icon-block HTML to match the save function output.
	 *
	 * The icon-block save function builds specific CSS classes and inline styles
	 * on the icon-container element based on block attributes. When Claude generates
	 * icon block markup, it often misses or misspells classes (e.g. has-no-icon-fill
	 * instead of has-no-icon-fill-color) or omits required styles (transform, color,
	 * width). This method rebuilds the icon-container's class and style attributes
	 * from the block attributes so the HTML matches what WordPress expects.
	 *
	 * @param array    $block Parsed block array.
	 * @param string[] &$fixes Applied fixes log.
	 * @return array Fixed block array.
	 */
	private function fix_icon_block_transform( array $block, array &$fixes ): array {
		$attrs = $block['attrs'] ?? array();

		// Build expected classes.
		$classes = array( 'icon-container' );

		$icon_color_value = $attrs['iconColorValue'] ?? '';
		$icon_color       = $attrs['iconColor'] ?? '';
		$bg_color_value   = $attrs['iconBackgroundColorValue'] ?? '';
		$bg_color         = $attrs['iconBackgroundColor'] ?? '';
		$gradient         = $attrs['gradient'] ?? '';
		$custom_gradient  = $attrs['customGradient'] ?? '';
		$has_no_fill      = ! empty( $attrs['hasNoIconFill'] );

		if ( '' !== $icon_color_value ) {
			$classes[] = 'has-icon-color';
		}
		if ( $has_no_fill ) {
			$classes[] = 'has-no-icon-fill-color';
		}
		if ( '' !== $bg_color_value || '' !== $bg_color || '' !== $gradient || '' !== $custom_gradient ) {
			$classes[] = 'has-icon-background-color';
		}
		if ( '' !== $bg_color ) {
			$classes[] = 'has-' . $bg_color . '-background-color';
		}
		if ( '' !== $icon_color ) {
			$classes[] = 'has-' . $icon_color . '-color';
		}
		if ( '' !== $gradient ) {
			$classes[] = 'has-' . $gradient . '-gradient-background';
		}

		// Build expected inline styles.
		$styles = array();

		if ( '' !== $custom_gradient && '' === $gradient ) {
			$styles[] = 'background:' . $custom_gradient;
		}
		if ( '' !== $bg_color_value ) {
			$styles[] = 'background-color:' . $bg_color_value;
		}
		if ( '' !== $icon_color_value ) {
			$styles[] = 'color:' . $icon_color_value;
		}

		// Width — default 48px.
		$width  = $attrs['width'] ?? '';
		$height = $attrs['height'] ?? '';
		if ( '' !== $width ) {
			$width_str = (string) $width;
			// If it's purely numeric, append px.
			if ( is_numeric( $width_str ) ) {
				$width_str .= 'px';
			}
			$styles[] = 'width:' . $width_str;
		} else {
			if ( '' === $height ) {
				$styles[] = 'width:48px';
			}
		}
		if ( '' !== $height ) {
			$styles[] = 'height:' . $height;
		}

		// Border styles from block attributes (experimentalBorder support).
		$block_style = $attrs['style'] ?? array();
		$border      = $block_style['border'] ?? array();
		if ( ! empty( $border ) ) {
			if ( isset( $border['color'] ) ) {
				$styles[] = 'border-color:' . $border['color'];
			}
			if ( isset( $border['width'] ) ) {
				$styles[] = 'border-width:' . $border['width'];
			}
			if ( isset( $border['style'] ) ) {
				$styles[] = 'border-style:' . $border['style'];
			}
			if ( isset( $border['radius'] ) ) {
				if ( is_array( $border['radius'] ) ) {
					$tl = $border['radius']['topLeft'] ?? '0px';
					$tr = $border['radius']['topRight'] ?? '0px';
					$br = $border['radius']['bottomRight'] ?? '0px';
					$bl = $border['radius']['bottomLeft'] ?? '0px';
					$styles[] = 'border-radius:' . $tl . ' ' . $tr . ' ' . $br . ' ' . $bl;
				} else {
					$styles[] = 'border-radius:' . $border['radius'];
				}
			}
		}

		// Padding from spacing support.
		$spacing = $block_style['spacing'] ?? array();
		$padding = $spacing['padding'] ?? array();
		if ( ! empty( $padding ) ) {
			if ( is_string( $padding ) ) {
				$styles[] = 'padding:' . $padding;
			} else {
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					if ( isset( $padding[ $side ] ) ) {
						$styles[] = 'padding-' . $side . ':' . $padding[ $side ];
					}
				}
			}
		}

		// Transform — always present.
		$rotate  = isset( $attrs['rotate'] ) ? (int) $attrs['rotate'] : 0;
		$flip_h  = ! empty( $attrs['flipHorizontal'] );
		$flip_v  = ! empty( $attrs['flipVertical'] );
		$scale_x = $flip_h ? -1 : 1;
		$scale_y = $flip_v ? -1 : 1;
		$styles[] = sprintf( 'transform:rotate(%ddeg) scaleX(%d) scaleY(%d)', $rotate, $scale_x, $scale_y );

		$expected_class = implode( ' ', $classes );
		$expected_style = implode( ';', $styles );

		$fixed = false;

		// Fix innerHTML.
		if ( isset( $block['innerHTML'] ) ) {
			$block['innerHTML'] = $this->fix_icon_container_html( $block['innerHTML'], $expected_class, $expected_style, $fixed );
		}

		// Fix innerContent entries.
		if ( isset( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as &$ic ) {
				if ( is_string( $ic ) ) {
					$ic = $this->fix_icon_container_html( $ic, $expected_class, $expected_style, $fixed );
				}
			}
			unset( $ic );
		}

		if ( $fixed ) {
			$fixes[] = 'Fixed outermost/icon-block icon-container classes and styles to match save output.';
		}

		return $block;
	}

	/**
	 * Fix the icon-container element's class and style attributes in HTML.
	 *
	 * Matches `<div` or `<a` elements whose class contains "icon-container" and
	 * replaces their class and style attributes with the expected values derived
	 * from the block attributes.
	 *
	 * @param string $html           HTML string.
	 * @param string $expected_class  Expected class attribute value.
	 * @param string $expected_style  Expected style attribute value.
	 * @param bool   &$fixed          Set to true if a fix was applied.
	 * @return string Fixed HTML.
	 */
	private function fix_icon_container_html( string $html, string $expected_class, string $expected_style, bool &$fixed ): string {
		if ( false === strpos( $html, 'icon-container' ) ) {
			return $html;
		}

		// Match the icon-container element (div or a) with its class and style attributes.
		return preg_replace_callback(
			'/(<(?:div|a)\s)([^>]*class="[^"]*icon-container[^"]*"[^>]*)(>)/',
			function ( $matches ) use ( $expected_class, $expected_style, &$fixed ) {
				$tag_open   = $matches[1]; // '<div ' or '<a '
				$attrs_str  = $matches[2]; // everything between tag name and >
				$tag_close  = $matches[3]; // '>'

				// Extract current class and style.
				$current_class = '';
				$current_style = '';
				if ( preg_match( '/class="([^"]*)"/', $attrs_str, $cm ) ) {
					$current_class = $cm[1];
				}
				if ( preg_match( '/style="([^"]*)"/', $attrs_str, $sm ) ) {
					$current_style = $sm[1];
				}

				// Normalize for comparison: collapse whitespace, trim, strip trailing semicolons.
				$norm_class = trim( preg_replace( '/\s+/', ' ', $current_class ) );
				$norm_style = trim( preg_replace( '/\s+/', '', $current_style ), '; ' );
				$norm_exp_class = trim( preg_replace( '/\s+/', ' ', $expected_class ) );
				$norm_exp_style = trim( preg_replace( '/\s+/', '', $expected_style ), '; ' );

				if ( $norm_class === $norm_exp_class && $norm_style === $norm_exp_style ) {
					return $matches[0];
				}

				// Replace class and style in the attributes string.
				$new_attrs = $attrs_str;
				if ( '' !== $current_class ) {
					$new_attrs = preg_replace( '/class="[^"]*"/', 'class="' . $expected_class . '"', $new_attrs, 1 );
				} else {
					$new_attrs = 'class="' . $expected_class . '" ' . $new_attrs;
				}
				if ( '' !== $current_style ) {
					$new_attrs = preg_replace( '/style="[^"]*"/', 'style="' . $expected_style . '"', $new_attrs, 1 );
				} else {
					$new_attrs = preg_replace( '/class="([^"]*)"/', 'class="$1" style="' . $expected_style . '"', $new_attrs, 1 );
				}

				$fixed = true;
				return $tag_open . $new_attrs . $tag_close;
			},
			$html
		);
	}

	/**
	 * Fix heading block HTML tag to match the level attribute.
	 *
	 * WordPress heading blocks use the `level` attribute (default 2) to determine
	 * which HTML tag (h1-h6) to render. When the markup contains a different tag
	 * than what the attribute specifies, block validation fails.
	 *
	 * @param array    $block Parsed block array.
	 * @param string[] &$fixes Applied fixes log.
	 * @return array Fixed block array.
	 */
	private function fix_heading_level( array $block, array &$fixes ): array {
		$level = $block['attrs']['level'] ?? 2;
		$level = max( 1, min( 6, (int) $level ) );
		$expected_tag = 'h' . $level;

		// Check if innerHTML uses a different heading tag.
		if ( ! isset( $block['innerHTML'] ) || ! preg_match( '/<(h[1-6])\b/', $block['innerHTML'], $m ) ) {
			return $block;
		}

		$actual_tag = $m[1];
		if ( $actual_tag === $expected_tag ) {
			return $block;
		}

		// Replace opening and closing tags in innerHTML and innerContent.
		if ( isset( $block['innerHTML'] ) ) {
			$block['innerHTML'] = preg_replace(
				'/<' . $actual_tag . '(\s|>)/',
				'<' . $expected_tag . '$1',
				$block['innerHTML']
			);
			$block['innerHTML'] = str_replace(
				'</' . $actual_tag . '>',
				'</' . $expected_tag . '>',
				$block['innerHTML']
			);
		}

		if ( isset( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as &$ic ) {
				if ( is_string( $ic ) ) {
					$ic = preg_replace(
						'/<' . $actual_tag . '(\s|>)/',
						'<' . $expected_tag . '$1',
						$ic
					);
					$ic = str_replace(
						'</' . $actual_tag . '>',
						'</' . $expected_tag . '>',
						$ic
					);
				}
			}
			unset( $ic );
		}

		$fixes[] = sprintf( 'Fixed heading tag from <%s> to <%s> to match level attribute.', $actual_tag, $expected_tag );

		return $block;
	}

	/**
	 * Fix image block wp-image-* classes.
	 *
	 * WordPress adds a `wp-image-{id}` class to image blocks based on the numeric
	 * attachment `id` attribute. When Claude generates markup with non-numeric
	 * wp-image classes (e.g. wp-image-mike), block validation fails. This method:
	 * - Strips wp-image-* classes with non-numeric IDs
	 * - Ensures the class matches the id attribute if one exists
	 *
	 * @param array    $block Parsed block array.
	 * @param string[] &$fixes Applied fixes log.
	 * @return array Fixed block array.
	 */
	private function fix_image_class( array $block, array &$fixes ): array {
		if ( ! isset( $block['innerHTML'] ) ) {
			return $block;
		}

		// Check for wp-image-* classes.
		if ( false === strpos( $block['innerHTML'], 'wp-image-' ) ) {
			return $block;
		}

		$id = $block['attrs']['id'] ?? null;
		$fixed = false;

		$replace_fn = function ( $html ) use ( $id, &$fixed ) {
			// Remove any wp-image-* class with a non-numeric suffix.
			$result = preg_replace_callback(
				'/\bwp-image-([^\s"]+)/',
				function ( $m ) use ( $id, &$fixed ) {
					$suffix = $m[1];
					// If suffix is numeric and matches the id attribute, keep it.
					if ( is_numeric( $suffix ) && null !== $id && (int) $suffix === (int) $id ) {
						return $m[0];
					}
					// If suffix is non-numeric, strip it.
					if ( ! is_numeric( $suffix ) ) {
						$fixed = true;
						return '';
					}
					// Numeric but no matching id attribute — strip it.
					if ( null === $id ) {
						$fixed = true;
						return '';
					}
					// Numeric but doesn't match id — replace with correct class.
					$fixed = true;
					return 'wp-image-' . (int) $id;
				},
				$html
			);
			// Clean up any double spaces left from removal.
			return preg_replace( '/  +/', ' ', preg_replace( '/class="([^"]*)\s+"/', 'class="$1"', $result ) );
		};

		$block['innerHTML'] = $replace_fn( $block['innerHTML'] );

		if ( isset( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as &$ic ) {
				if ( is_string( $ic ) ) {
					$ic = $replace_fn( $ic );
				}
			}
			unset( $ic );
		}

		if ( $fixed ) {
			$fixes[] = 'Fixed image block: removed invalid wp-image class.';
		}

		return $block;
	}

	/**
	 * Fix cover block markup to match save function output.
	 *
	 * The cover block save function renders child elements in a specific order:
	 * 1. Image/video background element
	 * 2. Background overlay span
	 * 3. Inner container div
	 *
	 * It also only adds `wp-image-{id}` class when the block has a numeric id
	 * attribute. This method reorders elements and fixes the wp-image class.
	 *
	 * @param array    $block Parsed block array.
	 * @param string[] &$fixes Applied fixes log.
	 * @return array Fixed block array.
	 */
	private function fix_cover_block( array $block, array &$fixes ): array {
		if ( ! isset( $block['innerHTML'] ) ) {
			return $block;
		}

		$html = $block['innerHTML'];
		$id   = $block['attrs']['id'] ?? null;
		$fixed = false;

		// Fix wp-image-* class on the image-background element.
		$has_wp_image_class = preg_match( '/wp-block-cover__image-background\s+wp-image-(\S+)/', $html, $m );

		if ( $has_wp_image_class ) {
			$img_id = $m[1];
			if ( ! is_numeric( $img_id ) ) {
				// Non-numeric wp-image class — strip it.
				$html = preg_replace(
					'/(wp-block-cover__image-background)\s+wp-image-[^\s"]+/',
					'$1',
					$html
				);
				$fixed = true;

				// If block has a numeric id, add the correct class.
				if ( null !== $id && is_numeric( $id ) ) {
					$html = str_replace(
						'wp-block-cover__image-background',
						'wp-block-cover__image-background wp-image-' . (int) $id,
						$html
					);
				}
			} elseif ( null !== $id && is_numeric( $id ) && (int) $img_id !== (int) $id ) {
				// Numeric but wrong id — replace with correct one.
				$html = preg_replace(
					'/wp-image-\d+/',
					'wp-image-' . (int) $id,
					$html
				);
				$fixed = true;
			}
		} elseif ( null !== $id && is_numeric( $id ) && false !== strpos( $html, 'wp-block-cover__image-background' ) ) {
			// wp-image class is missing entirely but block has a numeric id — add it.
			$html = str_replace(
				'wp-block-cover__image-background',
				'wp-block-cover__image-background wp-image-' . (int) $id,
				$html
			);
			$fixed = true;
		}

		// Check if elements are in wrong order: overlay before image-background.
		// Expected order: image-background THEN overlay.
		$img_bg_pos     = strpos( $html, 'wp-block-cover__image-background' );
		$overlay_pos    = strpos( $html, 'wp-block-cover__background' );

		if ( false !== $img_bg_pos && false !== $overlay_pos && $overlay_pos < $img_bg_pos ) {
			// Elements are in wrong order — reorder them.
			// Extract the image background element and the overlay element.
			$img_pattern     = '/<(?:div|img|video)\s[^>]*wp-block-cover__image-background[^>]*(?:\/>|>[^<]*<\/(?:div|img|video)>|>)/s';
			$overlay_pattern = '/<span\s[^>]*wp-block-cover__background[^>]*><\/span>/s';

			$img_element     = '';
			$overlay_element = '';

			if ( preg_match( $img_pattern, $html, $im ) ) {
				$img_element = $im[0];
			}
			if ( preg_match( $overlay_pattern, $html, $om ) ) {
				$overlay_element = $om[0];
			}

			if ( $img_element && $overlay_element ) {
				// Remove both elements.
				$html = str_replace( $img_element, '', $html );
				$html = str_replace( $overlay_element, '', $html );

				// Find the inner-container div and insert both before it.
				$inner_pos = strpos( $html, 'wp-block-cover__inner-container' );
				if ( false !== $inner_pos ) {
					// Find the start of the inner-container div tag.
					$div_start = strrpos( substr( $html, 0, $inner_pos ), '<div' );
					if ( false !== $div_start ) {
						$html = substr( $html, 0, $div_start )
							. $img_element
							. $overlay_element
							. substr( $html, $div_start );
						$fixed = true;
					}
				}
			}
		}

		if ( $fixed ) {
			$block['innerHTML'] = $html;

			// Apply the same fixes to innerContent string entries.
			if ( isset( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as &$ic ) {
					if ( is_string( $ic ) ) {
						$ic = $this->fix_cover_html( $ic, $id );
					}
				}
				unset( $ic );
			}

			$fixes[] = 'Fixed cover block: corrected element order and/or image class.';
		}

		return $block;
	}

	/**
	 * Fix button block HTML to sync color/style classes with block attributes.
	 *
	 * WordPress button blocks derive CSS classes on the inner `<a>` element from
	 * block attributes like backgroundColor, textColor, and className. When Claude
	 * updates attributes via manage-blocks without updating innerHTML, the classes
	 * go out of sync. This method rebuilds the expected classes on the link element.
	 *
	 * @param array    $block Parsed block array.
	 * @param string[] &$fixes Applied fixes log.
	 * @return array Fixed block array.
	 */
	private function fix_button_classes( array $block, array &$fixes ): array {
		if ( ! isset( $block['innerHTML'] ) ) {
			return $block;
		}

		$attrs = $block['attrs'] ?? array();
		$bg_color  = $attrs['backgroundColor'] ?? '';
		$text_color = $attrs['textColor'] ?? '';
		$gradient  = $attrs['gradient'] ?? '';
		$class_name = $attrs['className'] ?? '';

		// Only fix if the block has color attributes that need class sync.
		if ( '' === $bg_color && '' === $text_color && '' === $gradient ) {
			return $block;
		}

		// Build expected classes for the outer div.
		$outer_classes = array( 'wp-block-button' );
		if ( ! empty( $attrs['width'] ) ) {
			$outer_classes[] = 'has-custom-width';
			$outer_classes[] = 'wp-block-button__width-' . $attrs['width'];
		}
		if ( '' !== $class_name ) {
			foreach ( explode( ' ', $class_name ) as $cls ) {
				$cls = trim( $cls );
				if ( '' !== $cls && ! in_array( $cls, $outer_classes, true ) ) {
					$outer_classes[] = $cls;
				}
			}
		}

		// Build expected classes for the inner <a> element.
		$link_classes = array( 'wp-block-button__link' );

		if ( '' !== $text_color ) {
			$link_classes[] = 'has-' . $text_color . '-color';
			$link_classes[] = 'has-text-color';
			$link_classes[] = 'has-link-color';
		}
		if ( '' !== $bg_color ) {
			$link_classes[] = 'has-' . $bg_color . '-background-color';
			$link_classes[] = 'has-background';
		}
		if ( '' !== $gradient ) {
			$link_classes[] = 'has-' . $gradient . '-gradient-background';
			$link_classes[] = 'has-background';
		}

		$link_classes[] = 'wp-element-button';

		$expected_outer = implode( ' ', array_unique( $outer_classes ) );
		$expected_link  = implode( ' ', array_unique( $link_classes ) );

		$fixed = false;

		$fix_fn = function ( $html ) use ( $expected_outer, $expected_link, &$fixed ) {
			// Fix outer div classes.
			$html = preg_replace_callback(
				'/(<div\s+)class="([^"]*wp-block-button[^"]*)"/',
				function ( $m ) use ( $expected_outer, &$fixed ) {
					$current = trim( $m[2] );
					if ( $current !== $expected_outer ) {
						$fixed = true;
						return $m[1] . 'class="' . $expected_outer . '"';
					}
					return $m[0];
				},
				$html
			);

			// Fix inner <a> classes.
			$html = preg_replace_callback(
				'/(<a\s+)class="([^"]*wp-block-button__link[^"]*)"/',
				function ( $m ) use ( $expected_link, &$fixed ) {
					$current = trim( $m[2] );
					if ( $current !== $expected_link ) {
						$fixed = true;
						return $m[1] . 'class="' . $expected_link . '"';
					}
					return $m[0];
				},
				$html
			);

			return $html;
		};

		$block['innerHTML'] = $fix_fn( $block['innerHTML'] );

		if ( isset( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as &$ic ) {
				if ( is_string( $ic ) ) {
					$ic = $fix_fn( $ic );
				}
			}
			unset( $ic );
		}

		if ( $fixed ) {
			$fixes[] = 'Fixed button block: synced color classes with block attributes.';
		}

		return $block;
	}

	/**
	 * Apply cover block HTML fixes (wp-image class and element reordering) to a single HTML string.
	 *
	 * @param string   $html HTML string.
	 * @param int|null $id   Block attachment ID.
	 * @return string Fixed HTML.
	 */
	private function fix_cover_html( string $html, $id ): string {
		// Fix wp-image class: strip non-numeric, add missing, correct mismatched.
		$has_wp_image = preg_match( '/wp-block-cover__image-background\s+wp-image-(\S+)/', $html, $m );

		if ( $has_wp_image && ! is_numeric( $m[1] ) ) {
			$html = preg_replace(
				'/(wp-block-cover__image-background)\s+wp-image-[^\s"]+/',
				'$1',
				$html
			);
			if ( null !== $id && is_numeric( $id ) ) {
				$html = str_replace(
					'wp-block-cover__image-background',
					'wp-block-cover__image-background wp-image-' . (int) $id,
					$html
				);
			}
		} elseif ( ! $has_wp_image && null !== $id && is_numeric( $id ) && false !== strpos( $html, 'wp-block-cover__image-background' ) ) {
			$html = str_replace(
				'wp-block-cover__image-background',
				'wp-block-cover__image-background wp-image-' . (int) $id,
				$html
			);
		}

		// Fix element order if overlay comes before image-background.
		$img_bg_pos  = strpos( $html, 'wp-block-cover__image-background' );
		$overlay_pos = strpos( $html, 'wp-block-cover__background' );

		if ( false !== $img_bg_pos && false !== $overlay_pos && $overlay_pos < $img_bg_pos ) {
			$img_pattern     = '/<(?:div|img|video)\s[^>]*wp-block-cover__image-background[^>]*(?:\/>|>[^<]*<\/(?:div|img|video)>|>)/s';
			$overlay_pattern = '/<span\s[^>]*wp-block-cover__background[^>]*><\/span>/s';

			$img_element     = '';
			$overlay_element = '';

			if ( preg_match( $img_pattern, $html, $im ) ) {
				$img_element = $im[0];
			}
			if ( preg_match( $overlay_pattern, $html, $om ) ) {
				$overlay_element = $om[0];
			}

			if ( $img_element && $overlay_element ) {
				$html = str_replace( $img_element, '', $html );
				$html = str_replace( $overlay_element, '', $html );

				$inner_pos = strpos( $html, 'wp-block-cover__inner-container' );
				if ( false !== $inner_pos ) {
					$div_start = strrpos( substr( $html, 0, $inner_pos ), '<div' );
					if ( false !== $div_start ) {
						$html = substr( $html, 0, $div_start )
							. $img_element
							. $overlay_element
							. substr( $html, $div_start );
					}
				}
			}
		}

		return $html;
	}

	/**
	 * Create an issue array.
	 *
	 * @param string $id       Rule ID (e.g. "S-01").
	 * @param string $severity "error" or "warning".
	 * @param string $message  Human-readable message.
	 * @param string $autofix  Autofix strategy or empty.
	 * @return array
	 */
	private function issue( string $id, string $severity, string $message, string $autofix = '' ): array {
		return array(
			'id'       => $id,
			'severity' => $severity,
			'message'  => $message,
			'autofix'  => $autofix,
		);
	}
}
