<?php
/**
 * Writing Prompt Block Backend
 *
 * Registers the content-generate ability with the WP AI plugin.
 *
 * @package OlliePro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the content-generate ability.
 */
function ollie_writing_prompt_register_ability() {
	// Needs the Abilities API AND the WP AI plugin (the class extends Abstract_Ability).
	if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( 'WordPress\\AI\\Abstracts\\Abstract_Ability' ) ) {
		return;
	}

	require_once __DIR__ . '/class-writing-prompt.php';

	wp_register_ability(
		'ollie/content-generate',
		array(
			'label'         => __( 'Writing Prompt', 'ollie-pro' ),
			'description'   => __( 'Generate new content from a prompt using the configured AI provider.', 'ollie-pro' ),
			'ability_class' => 'Ollie_Writing_Prompt',
		),
	);
}
add_action( 'wp_abilities_api_init', 'ollie_writing_prompt_register_ability' );

/**
 * Pass AI availability data to the block editor.
 */
function ollie_writing_prompt_localize_data() {
	if ( ! is_admin() ) {
		return;
	}

	wp_localize_script(
		'ollie-writing-prompt-editor-script',
		'ollieWritingPromptData',
		array(
			'aiAvailable'   => function_exists( 'olpo_is_ai_available' ) && olpo_is_ai_available(),
			'connectorsUrl' => admin_url( 'options-connectors.php' ),
		)
	);
}
add_action( 'enqueue_block_assets', 'ollie_writing_prompt_localize_data', 20 );
