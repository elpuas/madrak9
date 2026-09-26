<?php
/**
 * AI Content Control
 *
 * Registers the content-rewrite ability with the WP AI plugin and
 * passes AI availability data to the editor script.
 *
 * @package OlliePro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the content-rewrite ability.
 */
function ollie_ai_content_register_ability() {
	// Needs the Abilities API AND the WP AI plugin (the class extends Abstract_Ability).
	if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( 'WordPress\\AI\\Abstracts\\Abstract_Ability' ) ) {
		return;
	}

	require_once __DIR__ . '/class-content-rewrite.php';

	wp_register_ability(
		'ollie/content-rewrite',
		array(
			'label'         => __( 'AI Content', 'ollie-pro' ),
			'description'   => __( 'Select text in the editor and rewrite it with AI-generated variations.', 'ollie-pro' ),
			'ability_class' => 'Ollie_Content_Rewrite',
		),
	);
}
add_action( 'wp_abilities_api_init', 'ollie_ai_content_register_ability' );

/**
 * Pass AI availability data to the editor script.
 */
function ollie_ai_content_localize_data() {
	if ( ! is_admin() ) {
		return;
	}

	wp_localize_script(
		'ollie-extensions-editor',
		'ollieAIContentData',
		array(
			'aiAvailable' => function_exists( 'olpo_is_ai_available' ) && olpo_is_ai_available(),
		)
	);
}
add_action( 'enqueue_block_assets', 'ollie_ai_content_localize_data', 20 );
