<?php
/**
 * Manejadores AJAX para transacciones Beepay
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Incluir las funciones de liquidación y reverso
require_once plugin_dir_path(__FILE__) . 'transactions.php';

/**
 * Manejador AJAX para liquidación
 */
function beepaybylafise_liquidation_handler() {
    $baseDeDatos = beepay_get_db();
    // Verificar nonce para seguridad
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'beepay_admin_nonce')) {
        wp_send_json_error('Error de seguridad');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('No tienes permisos para realizar esta acción');
    }
    
    $transaction_id = intval($_POST['transaction_id']);
    $amount = floatval($_POST['amount']);
    $comments = sanitize_textarea_field($_POST['comments']);
    
    if (!$transaction_id || $amount <= 0) {
        wp_send_json_error('Datos inválidos');
        return;
    }
    
    
    try {
        // Verificar que la transacción existe y está completada
        $stmt = $baseDeDatos->prepare("SELECT * FROM beepay_transactions WHERE id = ? AND status = '1'");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            wp_send_json_error('Transacción no encontrada o no completada');
            return;
        }
        
        // Verificar que no se haya liquidado ya
        if ($transaction['completeStatus'] !== null && $transaction['completeStatus'] !== '') {
            wp_send_json_error('Esta transacción ya ha sido liquidada');
            return;
        }
        
        // Calcular montos ya liquidados y reversados
        $monto_original = floatval($transaction['monto']);
        $monto_liquidado = floatval($transaction['complete'] ?? 0);
        $monto_reversado = floatval($transaction['refund'] ?? 0);
        
        // Verificar que el monto de liquidación no exceda el disponible
        $monto_disponible_liquidacion = $monto_original - $monto_liquidado - $monto_reversado;
        
        if ($amount > $monto_disponible_liquidacion) {
            wp_send_json_error("El monto de liquidación no puede exceder el disponible. Disponible: {$monto_disponible_liquidacion}");
            return;
        }
        
        if ($amount <= 0) {
            wp_send_json_error('El monto de liquidación debe ser mayor a 0');
            return;
        }
        
        // Llamar a la función de liquidación con los datos de la transacción
        try {
            $result = completePay(
                $amount,                                    // Monto a liquidar
                $transaction['currency'] ?: 'CRC',          // Moneda
                $transaction['orderId'],                    // Order ID
                $transaction['rrn'],                        // RRN
                $transaction['intRef'],                     // IntRef
                $transaction['orden'],                      // Orden (ID de WooCommerce)
                $baseDeDatos                               // Conexión a BD
            );
            
            // Decodificar la respuesta JSON
            $result_data = json_decode($result, true);
            
            if ($result_data['status'] === 'success') {
                // Log de la acción exitosa
                error_log("Beepay Liquidation Success: Transaction ID {$transaction_id}, Amount: {$amount}");
                wp_send_json_success($result_data['message']);
            } else {
                // Log del error
                error_log("Beepay Liquidation Error: Transaction ID {$transaction_id}, Error: " . $result_data['message']);
                wp_send_json_error($result_data['message']);
            }
            
        } catch (Exception $e) {
            error_log('Error en liquidación API: ' . $e->getMessage());
            wp_send_json_error('Error al procesar la liquidación: ' . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log('Error en liquidación: ' . $e->getMessage());
        wp_send_json_error('Error al procesar la liquidación: ' . $e->getMessage());
    }
}

/**
 * Manejador AJAX para reverso
 */
function beepaybylafise_reverse_handler() {
    $baseDeDatos = beepay_get_db();
    // Verificar nonce para seguridad
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'beepay_admin_nonce')) {
        wp_send_json_error('Error de seguridad');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('No tienes permisos para realizar esta acción');
    }
    
    $transaction_id = intval($_POST['transaction_id']);
    $amount = floatval($_POST['amount']);
    $comments = sanitize_textarea_field($_POST['comments']);
    
    if (!$transaction_id || $amount <= 0) {
        wp_send_json_error('Datos inválidos');
        return;
    }
    
    try {
        // Verificar que la transacción existe y está completada
        $stmt = $baseDeDatos->prepare("SELECT * FROM beepay_transactions WHERE id = ? AND status = '1'");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            wp_send_json_error('Transacción no encontrada o no completada');
            return;
        }

        if (!can_reverse($transaction)) {
            if (!beepay_is_within_reverse_window($transaction)) {
                wp_send_json_error('El reverso solo está disponible el mismo día de la transacción antes de las 11:00 p.m. (hora Costa Rica).');
            } else {
                wp_send_json_error('Esta transacción no puede reversarse.');
            }
            return;
        }
        
        // Calcular montos ya liquidados y reversados
        $monto_original = floatval($transaction['monto']);
        $monto_liquidado = floatval($transaction['complete'] ?? 0);
        $monto_reversado = floatval($transaction['refund'] ?? 0);
        
        // Verificar que el monto de reverso no exceda el disponible
        $monto_disponible_reverso = $monto_original - $monto_liquidado - $monto_reversado;
        
        if ($amount > $monto_disponible_reverso) {
            wp_send_json_error("El monto de reverso no puede exceder el disponible. Disponible: {$monto_disponible_reverso}");
            return;
        }
        
        if ($amount <= 0) {
            wp_send_json_error('El monto de reverso debe ser mayor a 0');
            return;
        }
        
        // Llamar a la función de reverso con los datos de la transacción
        try {
            $result = reversePay(
                $amount,                                    // Monto a reversar
                $transaction['currency'] ?: 'CRC',          // Moneda
                $transaction['orderId'],                    // Order ID
                $transaction['rrn'],                        // RRN
                $transaction['intRef'],                     // IntRef
                $transaction['authCode'],                   // Auth Code
                $transaction['orden'],                      // Orden (ID de WooCommerce)
                $baseDeDatos                               // Conexión a BD
            );
            
            // Decodificar la respuesta JSON
            $result_data = json_decode($result, true);
            
            if ($result_data['status'] === 'success') {
                // Log de la acción exitosa
                error_log("Beepay Reverse Success: Transaction ID {$transaction_id}, Amount: {$amount}");
                wp_send_json_success($result_data['message']);
            } else {
                // Log del error
                error_log("Beepay Reverse Error: Transaction ID {$transaction_id}, Error: " . $result_data['message']);
                wp_send_json_error($result_data['message']);
            }
            
        } catch (Exception $e) {
            error_log('Error en reverso API: ' . $e->getMessage());
            wp_send_json_error('Error al procesar el reverso: ' . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log('Error en reverso: ' . $e->getMessage());
        wp_send_json_error('Error al procesar el reverso: ' . $e->getMessage());
    }
}

// Registrar los hooks AJAX
add_action('wp_ajax_beepaybylafise_liquidation', 'beepaybylafise_liquidation_handler');
add_action('wp_ajax_beepaybylafise_reverse', 'beepaybylafise_reverse_handler');
