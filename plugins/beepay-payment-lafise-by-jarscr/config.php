<?php

$Path = __DIR__;

if (!defined('ABSPATH')) {
    $wp_load = $Path . '/../../../wp-load.php';
    if (!file_exists($wp_load)) {
        die('WordPress no disponible para Beepay.');
    }
    require_once $wp_load;
}

require_once $Path . '/includes/beepay-db.php';
require_once $Path . '/includes/beepay-client.php';

$BEE_BASEURL       = 'https://api.apibeep.com';
$BEE_TOKENURL      = 'https://idcs-6f21207f63b8421b911fe0680f5f102c.identity.oraclecloud.com/oauth2/v1/token';
$BEE_CLIENT_ID     = '8e63c0c5cb474e7ea01944ddfd773797';
$BEE_CLIENT_SECRET = '34164e36-b0ab-4ebf-8a78-c1dffcf1b1a1';
$BEE_SCOPE         = 'https://igi3zvgw2v5kt7iaiqz6iesxdi.apigateway.us-ashburn-1.oci.customer-oci.com:443https://igi3zvgw2v5kt7iaiqz6iesxdi.apigateway.us-ashburn-1.oci.customer-oci.com:443/faas';

if (function_exists('beepay_get_api_user')) {
    $BEE_USER     = beepay_get_api_user();
    $BEE_PASSWORD = beepay_get_api_password();
    $BEE_TERMINAL = beepay_get_api_terminal();
} else {
    $BEE_USER     = '';
    $BEE_PASSWORD = '';
    $BEE_TERMINAL = '';
}

$logs = $Path . '/log';
if (!file_exists($logs)) {
    mkdir($logs, 0777, true);
}

if (!defined('BEEPAY_PLUGIN_URL')) {
    if (function_exists('plugin_dir_url')) {
        define('BEEPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
    } else {
        define('BEEPAY_PLUGIN_URL', '/wp-content/plugins/' . basename($Path) . '/');
    }
}

$baseDeDatos = beepay_get_db();
