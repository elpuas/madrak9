<?php
/*
Plugin Name: Beepay Payment by LAFISE
Description: Compatible WooCommerce 6.x (o superior) para realizar pagos con el Beepay, liquidación y reverso.
Version: 2.0.5
Plugin URI: http://www.jarscr.com
Author: Alfredo Rodriguez Siles
Author URI: http://www.jarscr.com
*/

require_once plugin_dir_path(__FILE__) . 'includes/beepay-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/beepay-webhook-end.php';

register_activation_hook(__FILE__, 'beepay_activate_plugin');

function beepay_activate_plugin()
{
    beepay_create_tables();
    beepay_migrate_sqlite_to_wp();
}

add_action('plugins_loaded', 'beepay_bootstrap', 5);

function beepay_bootstrap()
{
    $db_version = '2.0.4';

    if (get_option('beepay_db_ready') !== $db_version) {
        beepay_create_tables();
        beepay_migrate_sqlite_to_wp();
        update_option('beepay_db_ready', $db_version);
    }
}

function beepay_get_settings()
{
    $settings = get_option('woocommerce_beepaybylafise_settings', array());

    if (empty($settings)) {
        $legacy_options = array(
            'woocommerce_beepaybylafise_settings',
            'woocommerce_jarscrbeepaylafise_settings',
        );

        foreach ($legacy_options as $legacy_option) {
            $legacy_settings = get_option($legacy_option, array());
            if (!empty($legacy_settings)) {
                update_option('woocommerce_beepaybylafise_settings', $legacy_settings);
                $settings = $legacy_settings;
                break;
            }
        }
    }

    return $settings;
}

function beepay_get_api_user()
{
    $settings = beepay_get_settings();

    return isset($settings['bee_user']) ? $settings['bee_user'] : '';
}

function beepay_get_api_password()
{
    $settings = beepay_get_settings();

    return isset($settings['bee_password']) ? $settings['bee_password'] : '';
}

function beepay_get_api_terminal()
{
    $settings = beepay_get_settings();

    if (!empty($settings['bee_terminal'])) {
        return $settings['bee_terminal'];
    }

    return isset($settings['terminal_id']) ? $settings['terminal_id'] : '';
}

add_action('plugins_loaded', 'beepay_register_webhook_handler', 20);

function beepay_register_webhook_handler()
{
    add_action('woocommerce_api_wc_beepay_by_lafise', 'beepay_handle_payment_webhook');
}

function beepay_handle_payment_webhook()
{
    nocache_headers();
    status_header(200);
    header('Content-Type: application/json; charset=utf-8');

    $post_data = wp_unslash($_POST);
    error_log('Beepay webhook: ' . wp_json_encode($post_data));

    $result = beepay_process_payment_webhook(is_array($post_data) ? $post_data : array());

    echo wp_json_encode($result);
    exit;
}

add_action('plugins_loaded', 'woocommerce_beepaybylafise_init', 0);

function woocommerce_beepaybylafise_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Beepay by LAFISE Payment Gateway class
     */
    class WC_BEEPAY_BY_LAFISE extends WC_Payment_Gateway
    {
        protected $msg = [];

        public $classic;
        public $success_message;
        public $failed_message;

        public $terminal_id;

        public $devurl;

        public $liveurl;

        public $orden_url;

        public function __construct()
        {
            //$this->plugin_slug        = plugin_basename(__DIR__);
            //$this->cache_key          = 'beepaybylafise_custom_upd';
            //$this->cache_allowed      = false;
            $this->method_description = __('Procesa pago en linea con Beepay.', 'beepaybylafise');
            $this->icon               = plugin_dir_url(__FILE__) . 'images/logo.png';

            $this->supports = [
                'products',
            ];

            $this->id           = 'beepaybylafise';
            $this->method_title = __('Beepay by LAFISE', 'beepaybylafise');

            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();

            $this->title           = $this->settings['title'];
            $this->description     = $this->settings['description'];
            $this->orden_url       = $this->settings['orden_url'];
            $this->terminal_id     = $this->settings['terminal_id'];
            $this->classic         = $this->settings['classic'];
            $this->success_message = $this->settings['success_message'];
            $this->failed_message  = $this->settings['failed_message'];
            $this->liveurl         = plugin_dir_url(__FILE__) . 'pagar.php';
            $this->msg['message']  = "";
            $this->msg['class']    = "";

            $this->supports = ['default_credit_card_form'];

            //add_action('init', array(&$this, 'check_jarscr_response'));

            add_action('woocommerce_payment_complete', [$this, 'successful_request']);

            if (version_compare(WOOCOMMERCE_VERSION, '2.1.2', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [&$this, 'process_admin_options']);
            } else {
                add_action('woocommerce_update_options_payment_gateways', [&$this, 'process_admin_options']);
            }
            add_action('woocommerce_receipt_beepaybylafise', [&$this, 'receipt_page']);
            add_action('woocommerce_thankyou_beepaybylafise', [&$this, 'thankyou_page']);
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled'          => [
                    'title'   => __('Activar/Desactivar', 'beepaybylafise'),
                    'type'    => 'checkbox',
                    'label'   => __('Activar modulo de Beepay by LAFISE.', 'beepaybylafise'),
                    'default' => 'no'
                ],
                'title'            => [
                    'title'       => __('Titulo:', 'beepaybylafise'),
                    'type'        => 'text',
                    'description' => __('Ingrese el nombre que desea vean sus clientes.', 'beepaybylafise'),
                    'default'     => __('Tarjeta de Crédito/Débito', 'beepaybylafise')
                ],
                'description'      => [
                    'title'       => __('Descripción:', 'beepaybylafise'),
                    'type'        => 'textarea',
                    'description' => __('Mensaje que se muestra mientras se raliza el Pago', 'beepaybylafise'),
                    'default'     => __('Pago seguro con Beepay by LAFISE.', 'beepaybylafise')
                ],
                'terminal_id'      => [
                    'title'       => __('Terminal ID', 'beepaybylafise'),
                    'type'        => 'text',
                    'description' => __('Terminal usado en el checkout y pagos.', 'beepaybylafise'),
                    'default'     => '51485445'
                ],
                'bee_user'         => [
                    'title'       => __('Usuario API Beepay', 'beepaybylafise'),
                    'type'        => 'text',
                    'description' => __('Usuario para autenticación con la API de Beepay.', 'beepaybylafise'),
                    'default'     => ''
                ],
                'bee_password'     => [
                    'title'       => __('Contraseña API Beepay', 'beepaybylafise'),
                    'type'        => 'password',
                    'description' => __('Contraseña para autenticación con la API de Beepay.', 'beepaybylafise'),
                    'default'     => ''
                ],
                'bee_terminal'     => [
                    'title'       => __('Terminal API (liquidación/reverso)', 'beepaybylafise'),
                    'type'        => 'text',
                    'description' => __('Terminal para liquidaciones y reversos en el admin.', 'beepaybylafise'),
                    'default'     => '51485445'
                ],
                'orden_url'        => [
                    'title'       => __('URL de Retorno', 'beepaybylafise'),
                    'type'        => 'text',
                    'description' => __('Ingrese el URL de retorno para WooCommerce'),
                    'default'     => __('/checkout/order-received/', 'beepaybylafise')
                ],
                'success_message'  => [
                    'title'       => __('Mensaje de Pago Exitoso', 'beepaybylafise'),
                    'type'        => 'textarea',
                    'description' => __('Ingrese el mensaje que se muestra al realizarse un pago exitoso.', 'beepaybylafise'),
                    'default'     => __('Su pago se ha realizado exitosamente', 'beepaybylafise')
                ],
                'failed_message'   => [
                    'title'       => __('Mensaje de Pago Fallido', 'beepaybylafise'),
                    'type'        => 'textarea',
                    'description' => __('Ingrese el mensaje que se muestra al realizase un pago fallido.', 'beepaybylafise'),
                    'default'     => __('Su transacción ha presentado problemas.', 'beepaybylafise')
                ],
                'classic'          => [
                    'title'       => __('Modo WooCommerce Clasic', 'beepaybylafise'),
                    'type'        => 'checkbox',
                    'description' => __('Esta opción permite usar el plugin en modo Classic o Blocks de WooCommerce, por defecto es Blocks.', 'beepaybylafise'),
                    'label'       => __('Activar modo Classic', 'beepaybylafise'),
                    'default'     => 'no'
                ]
            ];
        }

        /**
         * Panel Admin
         **/
        public function admin_options()
        {
            echo '<h3>' . __('Beepay by LAFISE', 'beepaybylafise') . '</h3>';
            echo '<p>' . __('Pago en linea por medio de Beepay by LAFISE') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        public function process_admin_options()
        {
            $saved_password = $this->get_option('bee_password');

            parent::process_admin_options();

            if ('' === $this->get_option('bee_password') && !empty($saved_password)) {
                $this->update_option('bee_password', $saved_password);
            }
        }

        public function successful_request($order_id) {
            $order = wc_get_order($order_id);

            if (!$order) {
                return;
            }

            if (!in_array($order->get_status(), ['processing', 'completed'], true)) {
                $order->payment_complete();
            }

            $order->add_order_note('Pago procesado exitosamente via Beepay by LAFISE');
        }

        public function convertUrlQuery($query)
        {
            $queryParts = explode('?', $query, 2);
            if (isset($queryParts[1])) {
                $params = [];
                parse_str($queryParts[1], $params);
                return $params;
            }
            return [];
        }

        public function payment_fields()
        {
            if ($this->description) {
                echo '<p style="font-size:16px; margin-left: 15px; font-weight:bold;">' . $this->description . '</p>';
            }

            $var_info = print_r($_REQUEST, true);
            if (strpos($var_info, 'message') !== false) {
                echo '<span style="color:red; font-size:12px; font-weight:bold" >';
                echo 'Se ha presentado un problema: ';

                $varr = $_POST['post_data'];
                if (!empty($varr)) {
                    $varr = preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($varr));
                    $varr = html_entity_decode($varr, ENT_QUOTES, 'UTF-8');
                    parse_str($varr, $myArray);
                    $parts = $this->convertUrlQuery($myArray['_wp_http_referer']);
                    echo $parts['message'];
                }
                echo '</span>
                     <script>
            (function($) {
                     $("html, body").animate({ scrollTop: $("#payment").offset().top }, 2000);
            })(jQuery);
                     </script>';
            }
            ?>
               <section class="beepaybylafise-wrapper">
                  <div class="credit-card-wrapper">
                     

                     <div class="first-row form-group">
                        <div class="col-sm-12 controls">
                           <label for="ccNo"><?php echo __('Numero de Tarjeta', 'woocommerce') ?> <span class="required">*</span></label>
                           <input type="text" class="number credit-card-number input-text" id="ccNoCRBE" name="ccNoCRBE"
                           inputmode="numeric" autocomplete="cc-number" autocompletetype="cc-number"
                           x-autocompletetype="cc-number" onkeyup="beepaybylafise_format(event);"
                           placeholder="&#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149; &#149;&#149;&#149;&#149;">
                           <input type="hidden" name="cardtypeBE" id="cardtypeBE" value="">
                        </div>
                     </div>


               
               <div class="second-row form-group">
                  <div class="col-md-4 col-sm-12 controls">
                     <label class="control-label"><?php echo __('Vencimiento', 'woocommerce') ?><span class="required">*</span></label>
                     <input class="expiration-month-and-year input-text" type="text" name="expMonthCRBE"
                        id="expMonthCRBE" placeholder="MM/YY" onkeyup="formatStringBeepayByLafise(event);" maxlength='5' autocomplete="off" >
                     </div>
                  <div class="col-md-4 col-sm-12 controls">
                     <label class="control-label"><?php _e('Codigo CVV', 'woocommerce')?><span class="required">*</span></label>
                     <input type="text" class="security-code input-text" id="cvvNoCRBE" name="cvvNoCRBE"  inputmode="numeric"  autocomplete="off" maxlength="4" placeholder="&#149;&#149;&#149;"/>
                  </div>
               </div>

               <div class="second-row form-group"><div class="col-sm-12 controls"><p>&nbsp;</p></div></div>

            </div>

            </section>                                                                                         <?php

        }

        private function get_post($name)
        {
            if (isset($_POST[$name])) {
                return $_POST[$name];
            }
            return null;
        }

        public function thankyou_page($order_id)
        {

        }

        /**
         * Comprobante
         **/
        public function receipt_page($order)
        {
            echo '<p>';
            _e('Thanks for you order. Please Wait.', 'beepaybylafise');
            echo '</p>';
            echo $this->generate_beepay_form($order);
        }

        /**
         * Proceso de Pago
         **/
        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            session_start();
            $base_request = [
                'cardnumber' => $this->get_post('ccNoCRBE'),
                'ccv'        => $this->get_post('cvvNoCRBE'),
                'expdate'    => $this->get_post('expMonthCRBE'),
                'cardtypeCR' => $this->get_post('cardtypeCRBE'),
                'brand'      => $this->get_post('cardtypeBE'),
            ];
            $_SESSION['pago_beepay_contado'] = $base_request;

            if ($order->get_payment_method() == $this->id) {
                return [
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_payment_url(true),
                ];
            }
        }

        public static function plugin_url()
        {
            return untrailingslashit(plugins_url('/', __FILE__));
        }

        public static function plugin_abspath()
        {
            return trailingslashit(plugin_dir_path(__FILE__));
        }

        /**
         * Generar enlace de pago para el formulario
         **/
        public function generate_beepay_form($order_id)
        {
            global $woocommerce;

            $order     = new WC_Order($order_id);
            $OrderID   = $order->get_id();
            $sequence  = rand(1, 1000);
            $timeStamp = time();

            $fingerprint = hash_hmac("md5", $sequence . "^" . $timeStamp . "^" . $order->get_total() . "^", '388763A8JHA8');
           
            $webhook_url = WC()->api_request_url(get_class($this));

            if ($this->classic == 'no') {
                $method = 'cardform';
            } else {
                $method = 'paynow';
            }

            $beepay_args = [
                'x_amount'         => $order->get_total(),
                'x_currency'       => get_woocommerce_currency(),
                'x_invoice_num'    => $OrderID,
                'x_terminal_id'    => $this->terminal_id,
                'x_relay_response' => "TRUE",
                'x_fp_sequence'    => $sequence,
                'x_fp_hash'        => $fingerprint,
                'x_show_form'      => 'PAYMENT_FORM',
                'x_first_name'     => $order->get_billing_first_name(),
                'x_last_name'      => $order->get_billing_last_name(),
                'x_billing_phone'  => $order->get_billing_phone(),
                'x_billing_email'  => $order->get_billing_email(),
                'x_relay_url'      => $webhook_url,
                'x_cancel_url'     => wc_get_checkout_url(),
                'x_method'         => $method,
            ];

            $beepay_args['x_fp_hash'] = 'gateway';

            $beepay_args_array = [];

            foreach ($beepay_args as $key => $value) {
                $beepay_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }

            $processURI = plugin_dir_url(__FILE__) . 'pagar.php';

            $CancelarBoton = __('Cancelar orden &amp; limpiar el carrito', 'beepaybylafise');
            $RealizarPago  = __('Realizar Pago', 'beepaybylafise');
            $Espere        = __('Espere por favor…', 'beepaybylafise');
            $Gracias       = __('Espere por favor.', 'beepaybylafise');

            @session_start();
            $beepaybylafiseCard_args_array = [];
            foreach ($_SESSION['pago_beepay_contado'] as $key => $value) {
                $beepaybylafiseCard_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            if ($this->classic == 'yes') {
                $html_form = '<div id="paymentModal" class="modal">
                                    <div class="modal-content">
                                       <span class="close">&times;</span>
                                       <iframe id="paymentFrame" name="paymentFrame" frameborder="0"></iframe>
                                    </div>
                                 </div>
                                 <form action="' . $processURI . '" method="post" id="beepay_payment_form" frameborder="0">'
                    . implode('', $beepay_args_array)
                    . implode('', $beepaybylafiseCard_args_array)
                    . '<input type="submit" class="button" id="submit_beepay_payment_form" value="' . $RealizarPago . '" style="display:none" />'
                    . '<script type="text/javascript">
                     jQuery(function(){
                     setTimeout(function() {
                        jQuery("#openPaymentModal").click();
                     }, 500); // 500ms de retraso
                  });
                  </script>'
                    . '<button type="button" id="openPaymentModal" class="button alt" style="display:none">Realizar Pago</button>'
                    . '</form>';
                return $html_form;
            } else {
                $html_form = '<div id="paymentModal" class="modal">
                                    <div class="modal-content">
                                       <span class="close">&times;</span>
                                       <iframe id="paymentFrame" name="paymentFrame" frameborder="0"></iframe>
                                    </div>
                                 </div>
                                    <form action="' . $processURI . '" method="post" id="beepay_payment_form" target="paymentFrame">'
                    . implode('', $beepay_args_array)
                    . implode('', $beepaybylafiseCard_args_array)
                    . '<input type="submit" class="button" id="submit_beepay_payment_form" value="' . $RealizarPago . '" style="display:none"/>'
                    . '<script type="text/javascript">

               jQuery(function(){
                  setTimeout(function() {
                     jQuery("#openPaymentModal").click();
                  }, 500); // 500ms de retraso
               });
               </script>'
                    . '<button type="button" id="openPaymentModal" class="button alt" style="display:none">Realizar Pago</button>'
                    . '</form>';
                return $html_form;
            }
        }

                                                                                                     

    }

    function purge_updater_beepay($upgrader, $options)
    {
        // Esta función parece estar incompleta o mal ubicada
        // Se necesita acceso a las propiedades de la clase para funcionar correctamente
        if (
            'update' === $options['action']
            && 'plugin' === $options['type']
        ) {
            // just clean the cache when new plugin version is installed
            // delete_transient($cache_key);
        }
    }

    /**
     * Add this Gateway to WooCommerce
     **/
    function woocommerce_add_beepaybylafise_gateway($methods)
    {
        $methods[] = 'WC_BEEPAY_BY_LAFISE';
        return $methods;
    }

    function woocommerce_gateway_beepaybylafise_woocommerce_block_support()
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once 'blocks/class-wc-beepay-by-lafise-payments-blocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new WC_BEEPAY_BY_LAFISE_Blocks_Support());
                }
            );
        }
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_beepaybylafise_gateway');
}

add_action('admin_menu', 'beepaybylafise_AddMenuAdministrator');

function beepaybylafise_public_scripts()
{
    wp_register_style('beepaybylafise_public_styles', plugin_dir_url(__FILE__) . 'src/beepaybylafise.css', '', '1.2.7', 'all');
    wp_enqueue_style('beepaybylafise_public_styles');

    wp_register_script('beepaybylafise_public_code_js', plugin_dir_url(__FILE__) . 'src/beepaybylafise.js', '', '1.2.7', 'all');
    wp_enqueue_script('beepaybylafise_public_code_js');
}

function beepaybylafise_AddMenuAdministrator()
{
    add_submenu_page(
        'woocommerce',
        'Beepay by LAFISE',
        'Beepay by LAFISE',
        'manage_woocommerce',
        'beepaybylafise-settings',
        function () {
            wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=beepaybylafise'));
            exit;
        }
    );
    add_submenu_page('woocommerce', 'Beepay Transactions', 'Beepay Transactions', 'manage_woocommerce', 'beepay-transactions', 'beepay_transactions_page');
}

add_action('wp_enqueue_scripts', 'beepaybylafise_public_scripts');
add_action('woocommerce_blocks_loaded', 'woocommerce_gateway_beepaybylafise_woocommerce_block_support');

// Cargar archivos de administración
add_action('admin_init', 'beepay_load_admin_files');

function beepay_load_admin_files() {
    if (is_admin()) {
        require_once plugin_dir_path(__FILE__) . 'ajax-handlers.php';
    }
}

// Función para cargar scripts en la página de transacciones
function beepay_admin_enqueue_scripts($hook) {
    // Solo cargar en la página de transacciones Beepay
    if ($hook === 'woocommerce_page_beepay-transactions') {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
    }
}
add_action('admin_enqueue_scripts', 'beepay_admin_enqueue_scripts');

// Función para mostrar la página de transacciones
function beepay_transactions_page() {
    // Incluir el archivo de transacciones
    require_once plugin_dir_path(__FILE__) . 'transactions.php';
    
    // Llamar a la función que muestra el contenido
    display_beepay_transactions_page();
}

function beepaybylafise_register_payment_method_data()
{
    if (!function_exists('wp_set_script_translations')) {
        return;
    }

    $gateway = new WC_BEEPAY_BY_LAFISE();

    wp_localize_script(
        'wc-beepaybylafise-blocks',
        'beepaybylafise_data',
        [
            'title'       => $gateway->get_title(),
            'description' => $gateway->get_description(),
            'supports'    => $gateway->supports,
        ]
    );
}
add_action('woocommerce_blocks_loaded', 'beepaybylafise_register_payment_method_data');
