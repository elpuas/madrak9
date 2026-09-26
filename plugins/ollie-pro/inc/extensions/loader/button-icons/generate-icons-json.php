<?php
/**
 * Generate icons JSON file
 * Run this script to generate icons.json from the PHP registry
 */

// Include the icons registry from the same directory
require_once __DIR__ . '/icons-registry.php';

// Get icons data
$icons = Ollie_Button_Icons_Registry::get_icons();

// Write to JSON file in the loader folder
$json_content = json_encode( $icons, JSON_PRETTY_PRINT );
file_put_contents( __DIR__ . '/icons.json', $json_content );

echo "Icons JSON file generated successfully!\n";
