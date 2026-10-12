<?php
/**
 * Página de administración para transacciones Beepay
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Definir funciones auxiliares globalmente
if (!function_exists('completePay')) {
function completePay( $amount, $currency, $orderId, $rrn, $intRef, $orden,$baseDeDatos){
    $Token = beepayToken();
    $fecha = new \DateTime();
    //$fecha->modify('+6 hours');
    
    $timestamp = $fecha->format('YmdHis');
 
    $curl = curl_init();
    
    // Preparar datos para completePay
    $DataPOST = '{
        "trtype": "21",
        "terminal": "' . beepay_get_api_terminal() . '",
        "amount": "'.number_format($amount, 2, '.', '').'",
        "currency": "'.$currency.'",
        "timestamp": "'.$timestamp.'",
        "backRefUrl": "' . esc_url_raw(untrailingslashit(plugin_dir_url(__FILE__)) . 'return3ds.php') . '",
        "orderId": "'.$orderId.' ",
        "rrn": "'.$rrn.'",
        "intRef": "'.$intRef.'"
        }';
    
   echo $DataPOST ;
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.apibeep.com/api/v1/ecommercegw',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $DataPOST,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: Bearer '.$Token['access_token']
      ),
    ));
    
    $response = curl_exec($curl);
    
    // Guardar respuesta en archivo para debugging
    error_log( "=== COMPLETE PAY RESPONSE ===");
    error_log( "Timestamp: " . date('Y-m-d H:i:s'));
    error_log( "Order ID: " . $orderId );
    error_log( "Amount: " . $amount );
    error_log("Raw Response: " . $response );
    error_log("================================");

    print_r($response);
    
    $Result = array('status' => 'success','message' => 'Liquidación procesada exitosamente');
    $Return = json_decode($response, true);
    if(isset($Return['rctext'])){

        $Result = array('status' => 'error','message' => $Return['rctext']);
    }
    $Return = print_r($Return, true);
    
    curl_close($curl);
    $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET completeDate = ?, completeStatus = ?,completeComments = ?, complete =? WHERE orden = ?");
    $sql->execute([$fecha->format('Y-m-d H:i:s'), 2, 'Liquidada '.$Return, $amount,$orden]);
    return json_encode($Result);
}
} // Cerrar if (!function_exists('completePay'))



if (!function_exists('reversePay')) {
function reversePay( $amount, $currency, $orderId, $rrn, $intRef, $authCode,$orden,$baseDeDatos){
    $Token = beepayToken();
    $fecha = new \DateTime();
    //$fecha->modify('+6 hours');
    
    $timestamp = $fecha->format('YmdHis');
 
    $curl = curl_init();

    $DataPOST = '{
        "trtype": "22",
        "terminal": "' . beepay_get_api_terminal() . '",
        "amount": "'.number_format($amount, 2, '.', '').'",
        "currency": "'.$currency.'",
        "rrn": "'.$rrn.'",
        "intRef": "'.$intRef.'",
        "orderId": "'.$orderId.' ",
        "authCode": "'.$authCode.'"
        }';

   
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.apibeep.com/api/v1/ecommercegw',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$DataPOST,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: Bearer '.$Token['access_token']
      ),
    ));
    
    $response = curl_exec($curl);
    
    error_log( "=== REVERSE PAY RESPONSE ===");
    error_log( "Timestamp: " . date('Y-m-d H:i:s'));
    error_log( "Order ID: " . $orderId );
    error_log( "Amount: " . $amount );
    error_log("Raw Response: " . $response );
    error_log("================================");
    
    curl_close($curl);
    $Result = array('status' => 'success','message' => 'Reverso procesado exitosamente');
    $Return = json_decode($response, true);
    if(isset($Return['rctext'])){

        $Result = array('status' => 'error','message' => $Return['rctext']);
    }
    $Return = print_r($Return, true);

   
    
    
    $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET refundDate = ?, refundStatus = ?,refundComments = ?,refund = ? WHERE orden = ?");
    $sql->execute([$fecha->format('Y-m-d H:i:s'), 2, 'Liquidada '.$Return, $amount,$orden]);
    return json_encode($Result);
    
}
} // Cerrar if (!function_exists('reversePay'))
if (!function_exists('beepayToken')) {
function beepayToken(){
    $BEE_TOKENURL      = 'https://idcs-6f21207f63b8421b911fe0680f5f102c.identity.oraclecloud.com/oauth2/v1/token';
    $BEE_CLIENT_ID     = '8e63c0c5cb474e7ea01944ddfd773797';
    $BEE_CLIENT_SECRET = '34164e36-b0ab-4ebf-8a78-c1dffcf1b1a1';
    $BEE_SCOPE         = 'https://igi3zvgw2v5kt7iaiqz6iesxdi.apigateway.us-ashburn-1.oci.customer-oci.com:443https://igi3zvgw2v5kt7iaiqz6iesxdi.apigateway.us-ashburn-1.oci.customer-oci.com:443/faas';
    $BEE_USER          = beepay_get_api_user();
    $BEE_PASSWORD      = beepay_get_api_password();
    $data = [
    "grant_type"    => "password",
    "client_id"     => $BEE_CLIENT_ID,
    "client_secret" => $BEE_CLIENT_SECRET,
    "username"      => $BEE_USER,
    "password"      => $BEE_PASSWORD,
    "scope"         => $BEE_SCOPE,
  ];
  

  $ch = curl_init();
  
  curl_setopt($ch, CURLOPT_URL, $BEE_TOKENURL);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/x-www-form-urlencoded",
  ]);
  
  $response = curl_exec($ch);
  curl_close($ch);
  
  $TokenData = json_decode($response, true);
  return $TokenData;
}
} // Cerrar if (!function_exists('beepayToken'))

// Función para obtener transacciones
if (!function_exists('get_beepay_transactions')) {
function get_beepay_transactions($baseDeDatos, $limite = 50, $offset = 0) {
   
    $Query = "SELECT * FROM beepay_transactions  ORDER BY date DESC  LIMIT $limite OFFSET $offset";
    try {
        $stmt = $baseDeDatos->prepare($Query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error getting transactions: ' . $e->getMessage());
        return [];
    }
}
} // Cerrar if (!function_exists('get_beepay_transactions'))

// Función para obtener el total de transacciones
if (!function_exists('get_beepay_transactions_count')) {
function get_beepay_transactions_count($baseDeDatos) {
   
    
    try {
        $stmt = $baseDeDatos->prepare("SELECT COUNT(*) as total FROM beepay_transactions");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    } catch (Exception $e) {
        error_log('Error getting transactions count: ' . $e->getMessage());
        return 0;
    }
}
} // Cerrar if (!function_exists('get_beepay_transactions_count'))

// Función para obtener el estado de la transacción
if (!function_exists('get_transaction_status')) {
function get_transaction_status($transaction) {
    if ($transaction['status'] == '1') {
        return '<span style="color: green;">✓ Completada</span>';
    } elseif ($transaction['status'] == '3') {
        return '<span style="color: red;">✗ Fallida</span>';
    } else {
        return '<span style="color: orange;">⏳ Pendiente</span>';
    }
}
} // Cerrar if (!function_exists('get_transaction_status'))

// Función para verificar si se puede liquidar
if (!function_exists('can_liquidate')) {
function can_liquidate($transaction) {
    if ($transaction['status'] != '1') {
        return false;
    }
    
    // Si ya está liquidada completamente, no se puede liquidar más
    if ($transaction['completeStatus'] !== null && $transaction['completeStatus'] !== '') {
        return false;
    }
    
    // Verificar que haya monto disponible para liquidar
    $monto_original = floatval($transaction['monto']);
    $monto_liquidado = floatval($transaction['complete'] ?? 0);
    $monto_reversado = floatval($transaction['refund'] ?? 0);
    $monto_disponible = $monto_original - $monto_liquidado - $monto_reversado;
    
    return $monto_disponible > 0;
}
} // Cerrar if (!function_exists('can_liquidate'))

// Ventana de reverso: mismo día de la transacción, antes de las 11 p.m. (Costa Rica)
if (!function_exists('beepay_is_within_reverse_window')) {
function beepay_is_within_reverse_window($transaction) {
    if (empty($transaction['date'])) {
        return false;
    }

    $timezone = new DateTimeZone('America/Costa_Rica');
    $now      = new DateTime('now', $timezone);
    $date_raw = trim(str_replace(',', '', $transaction['date']));
    $transaction_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_raw, $timezone);

    if (!$transaction_date) {
        $transaction_date = new DateTime($date_raw, $timezone);
    }

    if (!$transaction_date) {
        return false;
    }

    if ($transaction_date->format('Y-m-d') !== $now->format('Y-m-d')) {
        return false;
    }

    $cutoff = clone $now;
    $cutoff->setTime(23, 0, 0);

    return $now < $cutoff;
}
} // Cerrar if (!function_exists('beepay_is_within_reverse_window'))

// Función para verificar si se puede reversar
if (!function_exists('can_reverse')) {
function can_reverse($transaction) {
    if ($transaction['status'] != '1') {
        return false;
    }
    
    // Si ya está reversada completamente, no se puede reversar más
    if ($transaction['refundStatus'] !== null && $transaction['refundStatus'] !== '') {
        return false;
    }

    if (!beepay_is_within_reverse_window($transaction)) {
        return false;
    }
    
    // Verificar que haya monto disponible para reversar
    $monto_original = floatval($transaction['monto']);
    $monto_liquidado = floatval($transaction['complete'] ?? 0);
    $monto_reversado = floatval($transaction['refund'] ?? 0);
    $monto_disponible = $monto_original - $monto_liquidado - $monto_reversado;
    
    return $monto_disponible > 0;
}
} // Cerrar if (!function_exists('can_reverse'))

// Función para mostrar el estado de liquidación
if (!function_exists('get_liquidation_status')) {
function get_liquidation_status($transaction) {
    if ($transaction['completeStatus'] !== null && $transaction['completeStatus'] !== '') {
        $date = $transaction['completeDate'] ? format_date($transaction['completeDate']) : 'N/A';
        return '<span style="color: blue;">✓ Liquidada (' . $date . ')</span>';
    }
    return '';
}
} // Cerrar if (!function_exists('get_liquidation_status'))

// Función para mostrar el estado de reverso
if (!function_exists('get_reverse_status')) {
function get_reverse_status($transaction) {
    if ($transaction['refundStatus'] !== null && $transaction['refundStatus'] !== '') {
        $date = $transaction['refundDate'] ? format_date($transaction['refundDate']) : 'N/A';
        return '<span style="color: purple;">✓ Reversada (' . $date . ')</span>';
    }
    return '';
}
} // Cerrar if (!function_exists('get_reverse_status'))

// Función para formatear el monto
if (!function_exists('format_amount')) {
function format_amount($amount, $currency = 'CRC') {
    return number_format(floatval($amount), 2) . ' ' . $currency;
}
} // Cerrar if (!function_exists('format_amount'))

// Función para formatear fecha
if (!function_exists('format_date')) {
function format_date($date) {
    return date('d/m/Y H:i:s', strtotime($date));
}
} // Cerrar if (!function_exists('format_date'))

// Función para calcular el monto disponible
if (!function_exists('get_available_amount')) {
function get_available_amount($transaction) {
    $monto_original = floatval($transaction['monto']);
    $monto_liquidado = floatval($transaction['complete'] ?? 0);
    $monto_reversado = floatval($transaction['refund'] ?? 0);
    return $monto_original - $monto_liquidado - $monto_reversado;
}
} // Cerrar if (!function_exists('get_available_amount'))

// Función principal para mostrar la página de transacciones
function display_beepay_transactions_page() {
    $baseDeDatos = beepay_get_db();

    // Obtener parámetros de paginación
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($current_page - 1) * $per_page;

    // Obtener transacciones
    $transactions = get_beepay_transactions($baseDeDatos, $per_page, $offset);
    $total_transactions = get_beepay_transactions_count($baseDeDatos);
    $total_pages = ceil($total_transactions / $per_page);

    // Mostrar el contenido HTML
    ?>
    <div class="wrap">
    <h1>Transacciones Beepay by LAFISE</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <span class="displaying-num"><?php echo $total_transactions; ?> transacciones</span>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_transactions; ?> elementos</span>
            <span class="pagination-links">
                <?php
                $page_links = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $current_page
                ));
                echo $page_links;
                ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Orden</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Monto</th>
                <th>Auth Code</th>
                <th>RRN</th>
                <th>Estado</th>
                <th>Mensaje</th>
                <th>Liquidación</th>
                <th>Reverso</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
            <tr>
                <td colspan="11" style="text-align: center; padding: 20px;">
                    No hay transacciones disponibles
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td><?php echo esc_html($transaction['id']); ?></td>
                    <td>
                        <?php if ($transaction['orden']): ?>
                            <a href="<?php echo admin_url('post.php?post=' . $transaction['orden'] . '&action=edit'); ?>" target="_blank">
                                #<?php echo esc_html($transaction['orden']); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?php echo format_date($transaction['date']); ?></td>
                    <td>
                        <?php 
                        $nombre = trim($transaction['nombre'] . ' ' . $transaction['apellido']);
                        echo esc_html($nombre ?: 'N/A');
                        ?>
                    </td>
                    <td>
                        <?php 
                        $monto_original = format_amount($transaction['monto'], $transaction['currency'] ?: 'CRC');
                        $monto_liquidado = floatval($transaction['complete'] ?? 0);
                        $monto_reversado = floatval($transaction['refund'] ?? 0);
                        $monto_disponible = get_available_amount($transaction);
                        
                        echo $monto_original;
                        if ($monto_liquidado > 0 || $monto_reversado > 0) {
                            echo '<br><small style="color: #666;">';
                            if ($monto_liquidado > 0) {
                                echo 'Liquidado: ' . format_amount($monto_liquidado, $transaction['currency'] ?: 'CRC') . '<br>';
                            }
                            if ($monto_reversado > 0) {
                                echo 'Reversado: ' . format_amount($monto_reversado, $transaction['currency'] ?: 'CRC') . '<br>';
                            }
                            echo 'Disponible: ' . format_amount($monto_disponible, $transaction['currency'] ?: 'CRC');
                            echo '</small>';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html($transaction['authCode'] ?: '-'); ?></td>
                    <td><?php echo esc_html($transaction['rrn'] ?: '-'); ?></td>
                    <td><?php echo get_transaction_status($transaction); ?></td>
                    <td><?php echo esc_html($transaction['comments'] ?: '-'); ?></td>
                    <td>
                        <?php 
                        $liquidation_status = get_liquidation_status($transaction);
                        if ($liquidation_status): 
                            echo $liquidation_status;
                        else: 
                            echo '<span style="color: #999;">Pendiente</span>';
                        endif; 
                        ?>
                    </td>
                    <td>
                        <?php 
                        $reverse_status = get_reverse_status($transaction);
                        if ($reverse_status): 
                            echo $reverse_status;
                        else: 
                            echo '<span style="color: #999;">Pendiente</span>';
                        endif; 
                        ?>
                    </td>
                    <td>
                        <?php if ($transaction['status'] == '1'): ?>
                            <?php if (can_liquidate($transaction)): ?>
                                <button type="button" class="button button-secondary liquidation-btn" 
                                        data-transaction-id="<?php echo $transaction['id']; ?>"
                                        data-amount="<?php echo $transaction['monto']; ?>"
                                        data-currency="<?php echo $transaction['currency'] ?: 'CRC'; ?>"
                                        data-available="<?php echo get_available_amount($transaction); ?>">
                                    Liquidación
                                </button>
                            <?php endif; ?>
                            
                            <?php if (can_reverse($transaction)): ?>
                                <button type="button" class="button button-secondary reverse-btn" 
                                        data-transaction-id="<?php echo $transaction['id']; ?>"
                                        data-amount="<?php echo $transaction['monto']; ?>"
                                        data-currency="<?php echo $transaction['currency'] ?: 'CRC'; ?>"
                                        data-available="<?php echo get_available_amount($transaction); ?>">
                                    Reverso
                                </button>
                            <?php endif; ?>
                            
                            <?php if (!can_liquidate($transaction) && !can_reverse($transaction)): ?>
                                <span style="color: #999;">Procesada</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #999;">No disponible</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal para confirmación de Liquidación -->
<div id="liquidation-modal" class="beepay-modal" style="display: none;">
    <div class="beepay-modal-content">
        <span class="beepay-modal-close">&times;</span>
        <h3>Confirmar Liquidación</h3>
        <p>¿Está seguro que desea procesar la liquidación de esta transacción?</p>
        <div class="transaction-details">
            <p><strong>Monto:</strong> <span id="liquidation-amount"></span></p>
        </div>
        <form id="liquidation-form">
            <?php wp_nonce_field('beepay_admin_nonce', '_wpnonce'); ?>
            <input type="hidden" id="liquidation-transaction-id-input" name="transaction_id">
            <p>
                <label for="liquidation-amount-input">Monto a liquidar:</label>
                <input type="number" id="liquidation-amount-input" name="amount" step="0.01" min="0.01" required>
                <span id="liquidation-currency"></span>
            </p>
            <p>
                <label for="liquidation-comments">Comentarios (opcional):</label>
                <textarea id="liquidation-comments" name="comments" rows="3" cols="50"></textarea>
            </p>
            <p>
                <button type="submit" class="button button-primary">Confirmar Liquidación</button>
                <button type="button" class="button button-secondary beepay-modal-close">Cancelar</button>
            </p>
        </form>
    </div>
</div>

<!-- Modal para confirmación de Reverso -->
<div id="reverse-modal" class="beepay-modal" style="display: none;">
    <div class="beepay-modal-content">
        <span class="beepay-modal-close">&times;</span>
        <h3>Confirmar Reverso</h3>
        <p>¿Está seguro que desea procesar el reverso de esta transacción?</p>
        <div class="transaction-details">
            <p><strong>Monto:</strong> <span id="reverse-amount"></span></p>
        </div>
        <form id="reverse-form">
            <?php wp_nonce_field('beepay_admin_nonce', '_wpnonce'); ?>
            <input type="hidden" id="reverse-transaction-id-input" name="transaction_id">
            <p>
                <label for="reverse-amount-input">Monto a reversar:</label>
                <input type="number" id="reverse-amount-input" name="amount" step="0.01" min="0.01" required>
                <span id="reverse-currency"></span>
            </p>
            <p>
                <label for="reverse-comments">Comentarios (opcional):</label>
                <textarea id="reverse-comments" name="comments" rows="3" cols="50"></textarea>
            </p>
            <p>
                <button type="submit" class="button button-primary">Confirmar Reverso</button>
                <button type="button" class="button button-secondary beepay-modal-close">Cancelar</button>
            </p>
        </form>
    </div>
</div>

<style>
/* Asegurar que el contenido no se oculte debajo del sidebar */
.wrap {
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding: 0 20px;
}

/* Estilos para la tabla de transacciones */
.wp-list-table {
    margin-top: 20px;
    width: 100%;
    overflow-x: auto;
}

/* Asegurar que la página use todo el ancho disponible */
#wpbody-content {
    margin-right: 0 !important;
}

/* Estilos para los botones de acción */
.liquidation-btn, .reverse-btn {
    margin: 2px;
    padding: 5px 10px;
    font-size: 12px;
}

.beepay-modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.beepay-modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 500px;
    border-radius: 5px;
}

.beepay-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.beepay-modal-close:hover {
    color: black;
}

.transaction-details {
    background-color: #f9f9f9;
    padding: 10px;
    margin: 10px 0;
    border-radius: 3px;
}

.transaction-details p {
    margin: 5px 0;
}
</style>

<script>
// Fallback para cargar jQuery si no está disponible
if (typeof jQuery === 'undefined') {
    var script = document.createElement('script');
    script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
    script.onload = function() {
        initializeBeepayScripts();
    };
    document.head.appendChild(script);
} else {
    initializeBeepayScripts();
}

function initializeBeepayScripts() {
    jQuery(document).ready(function($) {
    // Abrir modal de liquidación
    $('.liquidation-btn').click(function() {
        var transactionId = $(this).data('transaction-id');
        var amount = $(this).data('amount');
        var currency = $(this).data('currency');
        var available = $(this).data('available');
        
        $('#liquidation-transaction-id').text(transactionId);
        $('#liquidation-amount').text(amount + ' ' + currency);
        $('#liquidation-transaction-id-input').val(transactionId);
        $('#liquidation-amount-input').val(available).attr('max', available);
        $('#liquidation-currency').text(currency);
        
        // Mostrar información adicional
        $('#liquidation-amount').html(amount + ' ' + currency + '<br><small>Disponible: ' + available + ' ' + currency + '</small>');
        
        $('#liquidation-modal').show();
    });
    
    // Abrir modal de reverso
    $('.reverse-btn').click(function() {
        var transactionId = $(this).data('transaction-id');
        var amount = $(this).data('amount');
        var currency = $(this).data('currency');
        var available = $(this).data('available');
        
        $('#reverse-transaction-id').text(transactionId);
        $('#reverse-amount').text(amount + ' ' + currency);
        $('#reverse-transaction-id-input').val(transactionId);
        $('#reverse-amount-input').val(available).attr('max', available);
        $('#reverse-currency').text(currency);
        
        // Mostrar información adicional
        $('#reverse-amount').html(amount + ' ' + currency + '<br><small>Disponible: ' + available + ' ' + currency + '</small>');
        
        $('#reverse-modal').show();
    });
    
    // Cerrar modales
    $('.beepay-modal-close').click(function() {
        $('.beepay-modal').hide();
    });
    
    // Cerrar modal al hacer clic fuera
    $(window).click(function(event) {
        if (event.target.classList.contains('beepay-modal')) {
            $('.beepay-modal').hide();
        }
    });
    
    // Enviar formulario de liquidación
    $('#liquidation-form').submit(function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=beepaybylafise_liquidation',
            success: function(response) {
                if (response.success) {
                    alert('Liquidación procesada exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error al procesar la liquidación');
            }
        });
    });
    
    // Enviar formulario de reverso
    $('#reverse-form').submit(function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=beepaybylafise_reverse',
            success: function(response) {
                if (response.success) {
                    alert('Reverso procesado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error al procesar el reverso');
            }
        });
    });
    });
}
</script>

<?php
} // Cerrar la función display_beepay_transactions_page()
