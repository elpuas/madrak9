<?php
/**
 * Shared helper for accessing and managing the wp_global_styles custom post.
 *
 * Centralizes global styles post retrieval and theme.json cache busting
 * so all abilities use one consistent path.
 *
 * @package OlliePro
 * @since   3.0.0
 */

namespace olpo\Abilities\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Global_Styles_Helper {

	/**
	 * Get the wp_global_styles custom post for the active theme.
	 *
	 * Tries the core function first, then falls back to a direct query.
	 *
	 * @return \WP_Post|\WP_Error
	 */
	public static function get_post() {
		if ( function_exists( 'wp_get_global_styles_custom_post' ) ) {
			$post = wp_get_global_styles_custom_post();
			if ( $post instanceof \WP_Post ) {
				return $post;
			}
		}

		// Fallback: query for the global styles CPT.
		$stylesheet = get_stylesheet();
		$posts      = get_posts(
			array(
				'post_type'              => 'wp_global_styles',
				'post_status'            => array( 'publish', 'draft' ),
				'name'                   => 'wp-global-styles-' . urlencode( $stylesheet ),
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $posts ) ) {
			return new \WP_Error( 'no_global_styles', __( 'No global styles post found for the active theme.', 'ollie-pro' ) );
		}

		return $posts[0];
	}

	/**
	 * Bust all theme.json related caches after global styles updates.
	 *
	 * Should be called after any write to the wp_global_styles post
	 * to ensure WP serves fresh data.
	 */
	public static function bust_cache(): void {
		$stylesheet = get_stylesheet();

		delete_transient( 'global_styles' );
		delete_transient( 'global_styles_' . $stylesheet );

		wp_cache_delete( 'global_styles_' . $stylesheet, 'global_styles' );

		if ( function_exists( 'wp_theme_has_theme_json_clean_cache' ) ) {
			wp_theme_has_theme_json_clean_cache();
		}
	}
}
