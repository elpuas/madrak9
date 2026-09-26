<?php
/**
 * Ollie Abilities — Main loader for the Abilities integration.
 *
 * Bootstraps validators, preview handler, REST endpoints,
 * and registers Ollie abilities on the Abilities API.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities;

use olpo\Abilities\Validators\Ollie_Block_Validator;
use olpo\Abilities\Validators\Ollie_Design_Linter;
use olpo\Abilities\Preview\Ollie_Preview_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ollie_Abilities {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Pattern index helper.
	 *
	 * @var Ollie_Pattern_Index
	 */
	private Ollie_Pattern_Index $pattern_index;

	/**
	 * Block validator (Layer 2).
	 *
	 * @var Ollie_Block_Validator
	 */
	private Ollie_Block_Validator $block_validator;

	/**
	 * Design linter orchestrator.
	 *
	 * @var Ollie_Design_Linter
	 */
	private Ollie_Design_Linter $design_linter;

	/**
	 * Preview handler.
	 *
	 * @var Ollie_Preview_Handler
	 */
	private Ollie_Preview_Handler $preview_handler;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Ability names registered by Ollie.
	 *
	 * @var string[]
	 */
	public const TOOL_ABILITIES = array(
		'ollie/manage-patterns',
		'ollie/manage-global-styles',
		'ollie/manage-posts',
		'ollie/manage-content',
		'ollie/manage-blocks',
		'ollie/manage-navigation',
		'ollie/manage-templates',
	);

	/**
	 * Constructor — wire hooks.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Boot editor refresh (heartbeat-based external change detection).
		Ollie_Editor_Refresh::init();
	}

	/**
	 * Require all class files.
	 */
	private function load_dependencies(): void {
		$base = __DIR__;

		// Core helpers.
		require_once $base . '/class-ollie-pattern-index.php';
		require_once $base . '/class-ollie-editor-refresh.php';

		// Validators.
		require_once $base . '/validators/class-ollie-block-validator.php';
		require_once $base . '/validators/class-ollie-design-linter.php';

		// Preview.
		require_once $base . '/preview/class-ollie-preview-handler.php';

		// Abilities.
		require_once $base . '/abilities/class-ollie-global-styles-helper.php';
		require_once $base . '/abilities/class-ollie-ability-manage-patterns.php';
		require_once $base . '/abilities/class-ollie-ability-manage-global-styles.php';
		require_once $base . '/abilities/class-ollie-ability-manage-posts.php';
		require_once $base . '/abilities/class-ollie-ability-manage-content.php';
		require_once $base . '/abilities/class-ollie-ability-manage-blocks.php';
		require_once $base . '/abilities/class-ollie-ability-manage-navigation.php';
		require_once $base . '/abilities/class-ollie-ability-manage-templates.php';
	}

	/**
	 * Instantiate components.
	 */
	private function init_components(): void {
		$this->pattern_index   = new Ollie_Pattern_Index();
		$this->block_validator = new Ollie_Block_Validator();
		$this->design_linter   = new Ollie_Design_Linter( $this->block_validator );
		$this->preview_handler = new Ollie_Preview_Handler();
	}

	/**
	 * Register the ollie-design ability category.
	 */
	public function register_ability_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'ollie-design',
			array(
				'label'       => __( 'Ollie Design', 'ollie-pro' ),
				'description' => __( 'Abilities for managing Ollie theme patterns, styles, and design tokens.', 'ollie-pro' ),
			)
		);
	}

	/**
	 * Register all P1 abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		Abilities\Manage_Patterns::register( $this->pattern_index, $this->block_validator, $this->design_linter, $this->preview_handler );
		Abilities\Manage_Global_Styles::register();
		Abilities\Manage_Posts::register( $this->pattern_index, $this->block_validator );
		Abilities\Manage_Content::register( $this->block_validator );
		Abilities\Manage_Blocks::register( $this->block_validator );
		Abilities\Manage_Navigation::register();
		Abilities\Manage_Templates::register();
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'ollie/v1',
			'/lint',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->design_linter, 'rest_lint' ),
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'markup' => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => __( 'Block markup to lint.', 'ollie-pro' ),
						'sanitize_callback' => function ( $value ) {
							return wp_kses_post( $value );
						},
					),
				),
			)
		);
	}

	/**
	 * Accessors for components.
	 */
	public function pattern_index(): Ollie_Pattern_Index {
		return $this->pattern_index;
	}

	public function block_validator(): Ollie_Block_Validator {
		return $this->block_validator;
	}

	public function design_linter(): Ollie_Design_Linter {
		return $this->design_linter;
	}

	public function preview_handler(): Ollie_Preview_Handler {
		return $this->preview_handler;
	}
}
