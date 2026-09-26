<?php
/**
 * Register the Ollie icon set with the WordPress core icon registry
 * (WP 7.1+), so the core/icon block's "Icon library" offers the same
 * Phosphor + Ollie icons as the Icon Block plugin integration.
 *
 * Icons register with file_path (one .svg per icon, emitted by
 * scripts/generate-icons.js alongside manifest.php), so SVG content is
 * only read from disk when an icon is actually listed or rendered.
 * Registration runs on every request: the core/icon block stores just a
 * slug and resolves it through this registry at render time, frontend
 * included.
 *
 * @package OlliePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Custom-SVG support for the core Icon block ships under the same
// setting; load it alongside the icon registry.
require_once __DIR__ . '/../icon-custom-svg/icon-custom-svg.php';

add_action( 'init', 'olpo_register_core_icons', 20 );

/**
 * Register the generated icon collections and icons.
 *
 * @return void
 */
function olpo_register_core_icons() {
	// The icon registry ships in WP 7.1; on older versions the Icon
	// Block integration remains the only consumer of the icon set.
	if ( ! function_exists( 'wp_register_icon_collection' ) || ! function_exists( 'wp_register_icon' ) ) {
		return;
	}

	$manifest_file = __DIR__ . '/manifest.php';
	if ( ! file_exists( $manifest_file ) ) {
		return;
	}

	$manifest = include $manifest_file;
	if ( empty( $manifest['collections'] ) || empty( $manifest['icons'] ) ) {
		return;
	}

	foreach ( $manifest['collections'] as $slug => $label ) {
		wp_register_icon_collection( $slug, array( 'label' => $label ) );
	}

	foreach ( $manifest['icons'] as $icon ) {
		list( $collection, $name, $label ) = $icon;
		wp_register_icon(
			$collection . '/' . $name,
			array(
				'label'     => $label,
				'file_path' => __DIR__ . '/svg/' . $collection . '/' . $name . '.svg',
			)
		);
	}
}
