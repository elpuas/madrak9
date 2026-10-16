<?php
/**
 * Procesador del webhook en WordPress (solo JSON, sin redirecciones).
 * Version: 2.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('beepay_process_payment_webhook')) {
    /**
     * Procesa el pago y devuelve datos para que pagar/return redirijan al cliente.
     *
     * @param array $post_data
     *
     * @return array{success:bool,order_id:int,order_key:string,error:string}
     */
    function beepay_process_payment_webhook(array $post_data)
    {
        $order_id = isset($post_data['x_invoice_num']) ? absint($post_data['x_invoice_num']) : 0;

        if (!$order_id) {
            return array(
                'success'   => false,
                'order_id'  => 0,
                'order_key' => '',
                'error'     => 'No order ID received',
            );
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'success'   => false,
                'order_id'  => $order_id,
                'order_key' => '',
                'error'     => 'Order not found',
            );
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway  = isset($gateways['beepaybylafise']) ? $gateways['beepaybylafise'] : null;
        $legacy_payment_methods = array('beepaybylafise', 'beepaybyjarscr');

        if (!$gateway || !in_array($order->get_payment_method(), $legacy_payment_methods, true)) {
            return array(
                'success'   => false,
                'order_id'  => $order_id,
                'order_key' => $order->get_order_key(),
                'error'     => 'Invalid payment method',
            );
        }

        $is_success = isset($post_data['x_response_code']) && (int) $post_data['x_response_code'] === 1;

        if (in_array($order->get_status(), array('processing', 'completed'), true)) {
            return array(
                'success'   => true,
                'order_id'  => $order_id,
                'order_key' => $order->get_order_key(),
                'error'     => '',
            );
        }

        try {
            if ($is_success) {
                $order->add_order_note('Pago Exitoso');

                if (!empty($post_data['x_trans_id'])) {
                    $order->add_order_note('Voucher: ' . sanitize_text_field(wp_unslash($post_data['x_trans_id'])));
                }

                if (!empty($post_data['x_rrn'])) {
                    $order->add_order_note('RRN: ' . sanitize_text_field(wp_unslash($post_data['x_rrn'])));
                }

                $order->add_order_note('Forma de Pago: Beepay by LAFISE');

                if (!empty($post_data['x_tarjet'])) {
                    $order->add_order_note('Tarjeta: ' . sanitize_text_field(wp_unslash($post_data['x_tarjet'])));
                }

                $order->add_order_note($gateway->success_message);
                $order->payment_complete();

                if (function_exists('wc_empty_cart')) {
                    wc_empty_cart();
                }

                return array(
                    'success'   => true,
                    'order_id'  => $order_id,
                    'order_key' => $order->get_order_key(),
                    'error'     => '',
                );
            }

            $error_msg = !empty($post_data['error_mensaje'])
                ? sanitize_text_field(wp_unslash($post_data['error_mensaje']))
                : $gateway->failed_message;

            $order->add_order_note($gateway->failed_message . ' ' . $error_msg);

            if (!empty($post_data['x_tarjet'])) {
                $order->add_order_note('Tarjeta: ' . sanitize_text_field(wp_unslash($post_data['x_tarjet'])));
            }

            $order->update_status('failed');

            return array(
                'success'   => false,
                'order_id'  => $order_id,
                'order_key' => $order->get_order_key(),
                'error'     => $error_msg,
            );
        } catch (Exception $e) {
            error_log('Beepay webhook exception: ' . $e->getMessage());
            $order->add_order_note('Error en el procesamiento del pago: ' . $e->getMessage());
            $order->update_status('failed');

            return array(
                'success'   => false,
                'order_id'  => $order_id,
                'order_key' => $order->get_order_key(),
                'error'     => $e->getMessage(),
            );
        }
    }
}
