<?php
/**
 * Cliente Beepay para pagar.php / return.php / return3ds.php.
 * Notifica a WordPress por HTTP y redirige al navegador desde aquí (no desde WP).
 */

if (!function_exists('beepay_notify_wordpress')) {
    /**
     * POST server-to-server al webhook wc-api (curl, sin pasar por el stack HTTP de WP).
     *
     * @param string $webhook_url
     * @param array  $payload
     *
     * @return array{success:bool,order_id:int,order_key:string,error:string}
     */
    function beepay_notify_wordpress($webhook_url, array $payload)
    {
        $default = array(
            'success'   => false,
            'order_id'  => isset($payload['x_invoice_num']) ? (int) $payload['x_invoice_num'] : 0,
            'order_key' => '',
            'error'     => 'No se pudo contactar a WordPress.',
        );

        if (empty($webhook_url)) {
            return $default;
        }

        $ch = curl_init($webhook_url);
        curl_setopt_array(
            $ch,
            array(
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_FOLLOWLOCATION => false,
            )
        );

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            error_log('Beepay webhook HTTP error: code=' . $code . ' body=' . $body);
            return $default;
        }

        $decoded = json_decode((string) $body, true);

        if (!is_array($decoded)) {
            error_log('Beepay webhook invalid JSON: ' . $body);
            return $default;
        }

        return array(
            'success'   => !empty($decoded['success']),
            'order_id'  => isset($decoded['order_id']) ? (int) $decoded['order_id'] : $default['order_id'],
            'order_key' => isset($decoded['order_key']) ? (string) $decoded['order_key'] : '',
            'error'     => isset($decoded['error']) ? (string) $decoded['error'] : '',
        );
    }
}

if (!function_exists('beepay_build_webhook_payload')) {
    function beepay_build_webhook_payload($order_id, $success, array $extra = array())
    {
        $payload = array(
            'x_invoice_num'   => (int) $order_id,
            'x_response_code' => $success ? 1 : 0,
        );

        return array_merge($payload, $extra);
    }
}

if (!function_exists('beepay_build_thankyou_url')) {
    /**
     * Arma la URL de order-received sin usar get_return_url() de WordPress.
     */
    function beepay_build_thankyou_url($order_id, $order_key)
    {
        $order_id = (int) $order_id;

        if ($order_id <= 0) {
            return '';
        }

        if (function_exists('wc_get_endpoint_url') && function_exists('wc_get_checkout_url')) {
            $url = wc_get_endpoint_url('order-received', (string) $order_id, wc_get_checkout_url());

            if ($order_key !== '') {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'key=' . rawurlencode($order_key);
            }

            return $url;
        }

        $base = function_exists('home_url') ? home_url('/') : ('https://' . $_SERVER['HTTP_HOST'] . '/');
        $url  = rtrim($base, '/') . '/checkout/order-received/' . $order_id . '/';

        if ($order_key !== '') {
            $url .= '?key=' . rawurlencode($order_key);
        }

        return $url;
    }
}

if (!function_exists('beepay_build_checkout_error_url')) {
    function beepay_build_checkout_error_url($cancel_url, $error_message)
    {
        $sep = (strpos($cancel_url, '?') !== false) ? '&' : '?';

        return $cancel_url . $sep . 'message=' . rawurlencode($error_message);
    }
}

if (!function_exists('beepay_finish_payment')) {
    /**
     * Notifica a WordPress y redirige al cliente desde el script de pago (no desde WP).
     */
    function beepay_finish_payment($webhook_url, array $payload, $cancel_url)
    {
        $result = beepay_notify_wordpress($webhook_url, $payload);

        if (!empty($result['success']) && !empty($result['order_id'])) {
            $target = beepay_build_thankyou_url($result['order_id'], $result['order_key']);
        } else {
            $error = !empty($result['error']) ? $result['error'] : 'Pago fallido';

            if (!empty($payload['error_mensaje'])) {
                $error = $payload['error_mensaje'];
            }

            $target = beepay_build_checkout_error_url($cancel_url, $error);
        }

        if ($target === '') {
            $target = $cancel_url;
        }

        $safe = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html><head><meta charset="UTF-8"><title>Beepay</title></head><body>';
        echo '<p style="text-align:center;padding:40px;">Redirigiendo...</p>';
        echo '<script>window.top.location.replace(' . json_encode($target) . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safe . '"></noscript>';
        echo '</body></html>';
        exit;
    }
}
