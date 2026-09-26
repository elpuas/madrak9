<?php
/**
 * Ollie Design Linter — orchestrates validation layers.
 *
 * Phase 1 implements:
 *  - Layer 1: Mutation constraints (color tokens, type scale, spacing scale).
 *  - Layer 2: Schema validation (delegated to Ollie_Block_Validator).
 *
 * Also serves as the callback for the /wp-json/ollie/v1/lint REST endpoint.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ollie_Design_Linter {

	/**
	 * Block validator instance (Layer 2).
	 *
	 * @var Ollie_Block_Validator
	 */
	private Ollie_Block_Validator $block_validator;

	/**
	 * Allowed color token slugs from Ollie theme.json palette.
	 *
	 * @var string[]|null Null until lazy-loaded.
	 */
	private ?array $color_tokens = null;

	/**
	 * Allowed font-size slugs from Ollie theme.json.
	 *
	 * @var string[]|null Null until lazy-loaded.
	 */
	private ?array $font_size_tokens = null;

	/**
	 * Allowed spacing-size slugs from Ollie theme.json.
	 *
	 * @var string[]|null Null until lazy-loaded.
	 */
	private ?array $spacing_tokens = null;

	/**
	 * Constructor.
	 *
	 * @param Ollie_Block_Validator $block_validator Block validator instance.
	 */
	public function __construct( Ollie_Block_Validator $block_validator ) {
		$this->block_validator = $block_validator;
	}

	/**
	 * Run all active validation layers on block markup.
	 *
	 * @param string $markup Block markup to validate.
	 * @return array{valid: bool, issues: array[], layers: array}
	 */
	public function lint( string $markup ): array {
		$all_issues = array();
		$layers     = array();

		// Layer 1 — Mutation constraints.
		$layer1          = $this->run_layer1( $markup );
		$layers['layer1'] = array(
			'name'   => 'Mutation constraints',
			'passed' => empty( $layer1 ),
			'count'  => count( $layer1 ),
		);
		$all_issues = array_merge( $all_issues, $layer1 );

		// Layer 2 — Schema validation.
		$layer2          = $this->block_validator->validate( $markup );
		$layers['layer2'] = array(
			'name'   => 'Schema validation',
			'passed' => $layer2['valid'],
			'count'  => count( $layer2['issues'] ),
		);
		$all_issues = array_merge( $all_issues, $layer2['issues'] );

		// Determine overall validity (no errors).
		$has_errors = false;
		foreach ( $all_issues as $issue ) {
			if ( 'error' === $issue['severity'] ) {
				$has_errors = true;
				break;
			}
		}

		return array(
			'valid'  => ! $has_errors,
			'issues' => $all_issues,
			'layers' => $layers,
		);
	}

	/**
	 * Summarize a lint result into a compact shape for MCP responses.
	 *
	 * Returns a small summary object instead of the full issues array,
	 * dramatically reducing response size when patterns have many
	 * autofix-safe violations (e.g. 161 C-03 spacing snaps).
	 *
	 * @param array $lint_result Result from lint().
	 * @return array{valid: bool, blockers: int, autofixed: int, warnings: int, issues_by_rule: array, blocker_details: array}
	 */
	public function summarize( array $lint_result ): array {
		$blockers  = 0;
		$autofixed = 0;
		$warnings  = 0;
		$by_rule   = array();
		$blocker_details = array();

		foreach ( $lint_result['issues'] as $issue ) {
			$rule_id  = $issue['id'] ?? 'unknown';
			$severity = $issue['severity'] ?? 'error';
			$autofix  = $issue['autofix'] ?? '';

			if ( ! isset( $by_rule[ $rule_id ] ) ) {
				$by_rule[ $rule_id ] = array(
					'count'    => 0,
					'severity' => $severity,
					'autofix'  => $autofix,
				);
			}
			$by_rule[ $rule_id ]['count']++;

			if ( 'warning' === $severity ) {
				$warnings++;
			} elseif ( '' !== $autofix && 'none' !== $autofix ) {
				$autofixed++;
			} else {
				$blockers++;
				// Include full detail for blockers — these need human attention.
				$blocker_details[] = $issue;
			}
		}

		$summary = array(
			'valid'      => $lint_result['valid'],
			'blockers'   => $blockers,
			'autofixed'  => $autofixed,
			'warnings'   => $warnings,
			'by_rule'    => $by_rule,
		);

		if ( ! empty( $blocker_details ) ) {
			$summary['blocker_details'] = $blocker_details;
		}

		return $summary;
	}

	/**
	 * REST API callback for POST /ollie/v1/lint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function rest_lint( \WP_REST_Request $request ): \WP_REST_Response {
		$markup = $request->get_param( 'markup' );

		if ( empty( $markup ) ) {
			return new \WP_REST_Response(
				array(
					'valid'  => false,
					'issues' => array(
						array(
							'id'       => 'LINT-00',
							'severity' => 'error',
							'message'  => 'No markup provided.',
							'autofix'  => '',
						),
					),
				),
				400
			);
		}

		$result = $this->lint( $markup );

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Layer 1 — Mutation constraints.
	 *
	 * Checks that colors, font sizes, and spacing values use
	 * Ollie design tokens rather than arbitrary values.
	 *
	 * @param string $markup Block markup.
	 * @return array[] Issues found.
	 */
	private function run_layer1( string $markup ): array {
		$this->ensure_tokens_loaded();

		$issues = array();
		$blocks = parse_blocks( $markup );

		foreach ( $blocks as $block ) {
			$this->check_constraints_recursive( $block, $issues );
		}

		return $issues;
	}

	/**
	 * Recursively check Layer 1 constraints on a block tree.
	 *
	 * @param array $block   Parsed block.
	 * @param array &$issues Collected issues.
	 */
	private function check_constraints_recursive( array $block, array &$issues ): void {
		$block_name = $block['blockName'] ?? null;
		if ( null === $block_name ) {
			return;
		}

		$attrs = $block['attrs'] ?? array();

		// C-01 — Colors must be design tokens.
		$this->check_color_tokens( $block_name, $attrs, $issues );

		// C-02 — Typography must use type scale.
		$this->check_font_size_tokens( $block_name, $attrs, $issues );

		// C-03 — Spacing uses spacing scale.
		$this->check_spacing_tokens( $block_name, $attrs, $issues );

		// Recurse.
		foreach ( $block['innerBlocks'] ?? array() as $inner ) {
			$this->check_constraints_recursive( $inner, $issues );
		}
	}

	/**
	 * C-01 — Verify color attributes reference design tokens.
	 *
	 * @param string $block_name Block name.
	 * @param array  $attrs      Block attributes.
	 * @param array  &$issues    Collected issues.
	 */
	private function check_color_tokens( string $block_name, array $attrs, array &$issues ): void {
		$color_attrs = array( 'backgroundColor', 'textColor', 'gradient' );

		foreach ( $color_attrs as $attr ) {
			if ( isset( $attrs[ $attr ] ) ) {
				$value = $attrs[ $attr ];
				if ( ! in_array( $value, $this->color_tokens, true ) ) {
					$issues[] = array(
						'id'       => 'C-01',
						'severity' => 'error',
						'message'  => sprintf(
							'Block "%s" uses color "%s" for "%s" which is not a registered design token.',
							$block_name,
							$value,
							$attr
						),
						'autofix'  => 'map-to-nearest',
					);
				}
			}
		}

		// Check inline style colors for hardcoded hex/rgb.
		$style = $attrs['style'] ?? array();
		$color = $style['color'] ?? array();

		foreach ( array( 'background', 'text', 'gradient' ) as $prop ) {
			if ( isset( $color[ $prop ] ) ) {
				$val = $color[ $prop ];
				// If it's a var:preset reference, it's fine.
				if ( is_string( $val ) && 0 === strpos( $val, 'var:preset|color|' ) ) {
					continue;
				}
				// Hardcoded color value.
				if ( is_string( $val ) && preg_match( '/^(#|rgb|hsl)/i', $val ) ) {
					$issues[] = array(
						'id'       => 'C-01',
						'severity' => 'error',
						'message'  => sprintf(
							'Block "%s" uses hardcoded color "%s" for style.color.%s. Use a design token instead.',
							$block_name,
							$val,
							$prop
						),
						'autofix'  => 'map-to-nearest',
					);
				}
			}
		}
	}

	/**
	 * C-02 — Verify font sizes reference the type scale.
	 *
	 * @param string $block_name Block name.
	 * @param array  $attrs      Block attributes.
	 * @param array  &$issues    Collected issues.
	 */
	private function check_font_size_tokens( string $block_name, array $attrs, array &$issues ): void {
		// Named font size slug.
		if ( isset( $attrs['fontSize'] ) && ! in_array( $attrs['fontSize'], $this->font_size_tokens, true ) ) {
			$issues[] = array(
				'id'       => 'C-02',
				'severity' => 'error',
				'message'  => sprintf(
					'Block "%s" uses font size "%s" which is not in the Ollie type scale.',
					$block_name,
					$attrs['fontSize']
				),
				'autofix'  => 'snap-to-scale',
			);
		}

		// Inline style font size.
		$style_typography = $attrs['style']['typography'] ?? array();
		if ( isset( $style_typography['fontSize'] ) ) {
			$val = $style_typography['fontSize'];
			if ( is_string( $val ) && 0 !== strpos( $val, 'var:preset|font-size|' ) ) {
				$issues[] = array(
					'id'       => 'C-02',
					'severity' => 'error',
					'message'  => sprintf(
						'Block "%s" uses custom font size "%s". Use a type scale preset instead.',
						$block_name,
						$val
					),
					'autofix'  => 'snap-to-scale',
				);
			}
		}
	}

	/**
	 * C-03 — Verify spacing values use the spacing scale.
	 *
	 * @param string $block_name Block name.
	 * @param array  $attrs      Block attributes.
	 * @param array  &$issues    Collected issues.
	 */
	private function check_spacing_tokens( string $block_name, array $attrs, array &$issues ): void {
		$style   = $attrs['style'] ?? array();
		$spacing = $style['spacing'] ?? array();

		$spacing_props = array( 'padding', 'margin' );

		foreach ( $spacing_props as $prop ) {
			if ( ! isset( $spacing[ $prop ] ) || ! is_array( $spacing[ $prop ] ) ) {
				continue;
			}

			foreach ( $spacing[ $prop ] as $side => $val ) {
				if ( ! is_string( $val ) ) {
					continue;
				}
				// Zero is always valid — no spacing to snap to a preset.
				if ( $this->is_zero_spacing( $val ) ) {
					continue;
				}
				// Preset references are valid.
				if ( 0 === strpos( $val, 'var:preset|spacing|' ) ) {
					continue;
				}
				// Hardcoded value — flag it.
				$issues[] = array(
					'id'       => 'C-03',
					'severity' => 'error',
					'message'  => sprintf(
						'Block "%s" uses custom spacing "%s" for %s.%s. Use a spacing scale preset instead.',
						$block_name,
						$val,
						$prop,
						$side
					),
					'autofix'  => 'snap-to-scale',
				);
			}
		}

		// Block gap.
		$block_gap = $spacing['blockGap'] ?? null;
		if ( is_string( $block_gap ) && ! $this->is_zero_spacing( $block_gap ) && 0 !== strpos( $block_gap, 'var:preset|spacing|' ) ) {
			$issues[] = array(
				'id'       => 'C-03',
				'severity' => 'error',
				'message'  => sprintf(
					'Block "%s" uses custom blockGap "%s". Use a spacing scale preset instead.',
					$block_name,
					$block_gap
				),
				'autofix'  => 'snap-to-scale',
			);
		}
	}

	/**
	 * Check whether a spacing value is zero and therefore exempt from the
	 * spacing scale preset requirement.
	 *
	 * Zero margins/padding are Ollie's own canonical convention (e.g. section
	 * wrappers using margin:0 to butt up against adjacent sections), so they
	 * are always valid regardless of unit.
	 *
	 * @param string $val Spacing value, e.g. "0", "0px", "13px".
	 * @return bool True if the value is zero.
	 */
	private function is_zero_spacing( string $val ): bool {
		return (bool) preg_match( '/^0(px|rem|em|vh|vw|%)?$/', trim( $val ) );
	}

	/**
	 * Lazy-load and return design tokens from the active theme's theme.json.
	 *
	 * Tokens are loaded once on first access and cached in memory.
	 * This avoids calling wp_get_global_settings() at construction
	 * time when the data may not be needed.
	 */
	private function ensure_tokens_loaded(): void {
		if ( null !== $this->color_tokens ) {
			return;
		}

		$this->color_tokens     = array();
		$this->font_size_tokens = array();
		$this->spacing_tokens   = array();

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return;
		}

		// Color palette tokens.
		$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
		if ( is_array( $palette ) ) {
			foreach ( $palette as $entry ) {
				if ( ! empty( $entry['slug'] ) ) {
					$this->color_tokens[] = $entry['slug'];
				}
			}
		}

		// Also include gradient slugs as valid color tokens.
		$gradients = wp_get_global_settings( array( 'color', 'gradients', 'theme' ) );
		if ( is_array( $gradients ) ) {
			foreach ( $gradients as $entry ) {
				if ( ! empty( $entry['slug'] ) ) {
					$this->color_tokens[] = $entry['slug'];
				}
			}
		}

		// Font size tokens.
		$font_sizes = wp_get_global_settings( array( 'typography', 'fontSizes', 'theme' ) );
		if ( is_array( $font_sizes ) ) {
			foreach ( $font_sizes as $entry ) {
				if ( ! empty( $entry['slug'] ) ) {
					$this->font_size_tokens[] = $entry['slug'];
				}
			}
		}

		// Spacing size tokens.
		$spacing_sizes = wp_get_global_settings( array( 'spacing', 'spacingSizes', 'theme' ) );
		if ( is_array( $spacing_sizes ) ) {
			foreach ( $spacing_sizes as $entry ) {
				if ( ! empty( $entry['slug'] ) ) {
					$this->spacing_tokens[] = $entry['slug'];
				}
			}
		}
	}
}
