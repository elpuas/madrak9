<?php
/**
 * AI Availability Helper
 *
 * Single source of truth for "is the WP AI plugin installed AND does a usable
 * AI provider connector have credentials configured?". Use this to gate any
 * UI that depends on a working AI pipeline (toolbar buttons, block notices,
 * etc.) so we don't show features the user can't actually use.
 *
 * @package OlliePro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'olpo_is_ai_available' ) ) {
	/**
	 * Whether the WP AI plugin is active AND at least one ai_provider connector
	 * has authentication credentials configured (env var, PHP constant, or
	 * stored option).
	 *
	 * Mirrors the WP AI plugin's own hasCredentials check that drives its
	 * "Verify you have one or more AI Connectors configured" admin notice.
	 *
	 * @return bool
	 */
	function olpo_is_ai_available() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return false;
		}

		foreach ( (array) wp_get_connectors() as $cdata ) {
			if ( ! is_array( $cdata ) || 'ai_provider' !== ( $cdata['type'] ?? '' ) ) {
				continue;
			}

			$auth = $cdata['authentication'] ?? null;
			if ( ! is_array( $auth ) || 'api_key' !== ( $auth['method'] ?? '' ) ) {
				continue;
			}

			$env_var = (string) ( $auth['env_var_name'] ?? '' );
			if ( '' !== $env_var ) {
				$env_value = getenv( $env_var );
				if ( false !== $env_value && '' !== $env_value ) {
					return true;
				}
			}

			$const_name = (string) ( $auth['constant_name'] ?? '' );
			if ( '' !== $const_name && defined( $const_name ) ) {
				$const_value = constant( $const_name );
				if ( is_string( $const_value ) && '' !== $const_value ) {
					return true;
				}
			}

			$setting_name = (string) ( $auth['setting_name'] ?? '' );
			if ( '' !== $setting_name ) {
				$db_value = get_option( $setting_name, '' );
				if ( '' !== $db_value ) {
					return true;
				}
			}
		}

		return false;
	}
}
