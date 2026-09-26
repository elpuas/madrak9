<?php
/**
 * Ollie Editor Refresh — Detects external content changes and refreshes the block editor.
 *
 * Uses the WordPress Heartbeat API to periodically compare the post's
 * last-modified timestamp. When an external update is detected (e.g. via MCP),
 * the editor is instructed to reload the post content.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ollie_Editor_Refresh {

	/**
	 * Boot the editor refresh hooks.
	 */
	public static function init(): void {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_script' ) );
		add_filter( 'heartbeat_received', array( self::class, 'heartbeat_received' ), 10, 2 );
	}

	/**
	 * Enqueue the lightweight editor-refresh script in the block editor.
	 */
	public static function enqueue_editor_script(): void {
		$screen = get_current_screen();

		// Only load in the post editor (not widgets, site editor, etc.).
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		wp_enqueue_script(
			'ollie-editor-refresh',
   OLPO_URL . '/inc/abilities/assets/editor-refresh.js',
			array( 'heartbeat', 'wp-data', 'wp-dom-ready', 'wp-blocks', 'wp-block-editor' ),
			OLPO_VERSION,
			true
		);
	}

	/**
	 * Respond to heartbeat tick with the current post_modified timestamp.
	 *
	 * @param array $response Heartbeat response data.
	 * @param array $data     Heartbeat request data from the client.
	 * @return array Modified response.
	 */
	public static function heartbeat_received( array $response, array $data ): array {
		if ( empty( $data['ollie_editor_refresh'] ) ) {
			return $response;
		}

		$post_id = (int) ( $data['ollie_editor_refresh']['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return $response;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $response;
		}

		$response['ollie_editor_refresh'] = array(
			'post_modified' => $post->post_modified_gmt,
		);

		return $response;
	}
}
