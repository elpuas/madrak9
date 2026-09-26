<?php
/**
 * Pattern Editing - PHP Loader
 *
 * Disables WordPress 7.0's content-only editing default for unsynced patterns.
 * Users can re-enable it for themselves via a toggle in the editor Preferences modal.
 *
 * @package OlliePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Use '7.0-alpha' so WP 7.0 betas/RCs from the Beta Tester plugin also qualify;
// version_compare treats prerelease suffixes as lower than the final release.
if ( version_compare( get_bloginfo( 'version' ), '7.0-alpha', '>=' ) ) {
	add_filter(
		'block_editor_settings_all',
		function ( $settings ) {
			if ( ! isset( $settings['disableContentOnlyForUnsyncedPatterns'] ) ) {
				$settings['disableContentOnlyForUnsyncedPatterns'] = true;
			}
			return $settings;
		}
	);
}
