<?php

include 'config.php';

if (empty($baseDeDatos)) {
  error_log('Beepay: base de datos no disponible en pagar.php');
  die('Error de configuracion Beepay.');
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
$order_id = $_POST['x_invoice_num'];
$x_amount = $_POST['x_amount'];
$fp_hash  = $_POST['x_fp_hash'];
$nombre   = $_POST['x_first_name'];
$apellido = $_POST['x_last_name'];
$Terminal_Id  = $_POST['x_terminal_id'];
$telefono = $_POST['x_billing_phone'];
$email    = $_POST['x_billing_email'];
$webhook_url = $_POST['x_relay_url'];
$cancelar = $_POST['x_cancel_url'];
$currency = $_POST['x_currency'];
$method   = $_POST['x_method'];



function cc_masking($number)
{
  //return substr($number, -4);//Ultimos 4 Digitos
  return substr($number, 0, 4) . str_repeat("X", strlen($number) - 8) . substr($number, -4); // Filtro de Tarjeta ingresa pimeros 4 y ultimos 4. Los restantes 8 caracteres les coloca X

}
// Now you can use WordPress functions


$DateCard     = explode("/", $_POST['expdate']);
$ccNo         = str_replace(" ", "", trim($_POST['cardnumber']));
$cvvNo        = $_POST['ccv'];
$TipoTarjeta  = $_POST['brand'];



$amount = number_format($_REQUEST['x_amount'], 2, '.', '');
$FiltroTarjeta = cc_masking($ccNo);
$ip   = $_SERVER["REMOTE_ADDR"];


$zone = 3600 * -6;
$fecha = gmdate('Y-m-d H:i:s', time() + $zone);

try {
  $sql = $baseDeDatos->prepare("INSERT INTO beepay_transactions (monto, orden, ip, date, status, comments, nombre, apellido, telefono, email, hash, retorno, cancelar, cuenta, marca, cuotas, currency, authCode, rrn, intRef, merchTokenId, eci, ipsRc, threeDSSessionData) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $sql->execute([$amount, $order_id, $ip, $fecha, '1', 'Iniciada', $nombre, $apellido, $telefono, $email, $fp_hash, $webhook_url, $cancelar, $FiltroTarjeta, $TipoTarjeta, '', $currency, '', '', '', '', '', '', '']);
} catch (Exception $e) {
  error_log('Beepay INSERT error: ' . $e->getMessage());
  die('Error al registrar la transaccion.');
}

if ($currency == "CRC") {
  $CurrencyCode = 188;
} else {
  $CurrencyCode = 840;
}



//FORMULARIO DE LANZAMIENTO PARA CREDOMATIC
?>
<!doctype html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Beepay</title>
  <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lato">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .lds-spinner {
      color: official;
      display: inline-block;
      position: relative;
      width: 80px;
      height: 80px;
    }

    .lds-spinner div {
      transform-origin: 40px 40px;
      animation: lds-spinner 1.2s linear infinite;
    }

    .lds-spinner div:after {
      content: " ";
      display: block;
      position: absolute;
      top: 3px;
      left: 37px;
      width: 6px;
      height: 18px;
      border-radius: 20%;
      background: #ff5733;
    }

    .lds-spinner div:nth-child(1) {
      transform: rotate(0deg);
      animation-delay: -1.1s;
    }

    .lds-spinner div:nth-child(2) {
      transform: rotate(30deg);
      animation-delay: -1s;
    }

    .lds-spinner div:nth-child(3) {
      transform: rotate(60deg);
      animation-delay: -0.9s;
    }

    .lds-spinner div:nth-child(4) {
      transform: rotate(90deg);
      animation-delay: -0.8s;
    }

    .lds-spinner div:nth-child(5) {
      transform: rotate(120deg);
      animation-delay: -0.7s;
    }

    .lds-spinner div:nth-child(6) {
      transform: rotate(150deg);
      animation-delay: -0.6s;
    }

    .lds-spinner div:nth-child(7) {
      transform: rotate(180deg);
      animation-delay: -0.5s;
    }

    .lds-spinner div:nth-child(8) {
      transform: rotate(210deg);
      animation-delay: -0.4s;
    }

    .lds-spinner div:nth-child(9) {
      transform: rotate(240deg);
      animation-delay: -0.3s;
    }

    .lds-spinner div:nth-child(10) {
      transform: rotate(270deg);
      animation-delay: -0.2s;
    }

    .lds-spinner div:nth-child(11) {
      transform: rotate(300deg);
      animation-delay: -0.1s;
    }

    .lds-spinner div:nth-child(12) {
      transform: rotate(330deg);
      animation-delay: 0s;
    }

    @keyframes lds-spinner {
      0% {
        opacity: 1;
      }

      100% {
        opacity: 0;
      }
    }
  </style>
</head>

<body>
  <div style="text-align: center; width: 100%"><br><br><br><img src="logo.png" width="200px"><br><br><br>
    <div class="lds-spinner">
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
      <div></div>
    </div>

  </div>

  <?php


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

  $fecha = new \DateTime('now', new \DateTimeZone('UTC'));
  $timestamp = $fecha->format('YmdHis');


  $data = array(
    "trtype" => "1",
    "terminal" => $Terminal_Id,
    "orderId" => $timestamp,
    "amount" => $amount,
    "currency" => $currency,
    "desc" => "JALELO",
    "timestamp" => $timestamp,
    "backRefUrl" => untrailingslashit(BEEPAY_PLUGIN_URL) . "/return3ds.php?pid=" . $order_id,
    "merch3dFingerUrl" => untrailingslashit(BEEPAY_PLUGIN_URL) . "/return.php?pid=" . $order_id,
    "merch3dTermUrl" => untrailingslashit(BEEPAY_PLUGIN_URL) . "/return3ds.php?pid=" . $order_id,
    "card" =>  $ccNo,
    "expMonth" => $DateCard[0],
    "expYear" => $DateCard[1],
    "cvc2" => $cvvNo

  );

  $BeepayData = json_encode($data);



  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL            => $BEE_BASEURL . '/api/v1/ecommercegw',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING       => '',
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => $BeepayData,
    CURLOPT_HTTPHEADER     => [
      'Authorization: ' . $TokenData['token_type'] . ' ' . $TokenData['access_token'],
      'Content-Type: application/json',
    ],
  ]);

  $response = curl_exec($curl);


  curl_close($curl);
  $Return = json_decode($response, true);



  $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET threeDSSessionData = ? WHERE orden = ?");
  $sql->execute([$Return['threeDSSessionData'], $order_id]);

  if (isset($Return['errMsg'])) {
    $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET status = ?, comments = ? WHERE orden = ?");
    $sql->execute([3,   $Return['errMsg'], $order_id]);

    beepay_finish_payment(
      $webhook_url,
      beepay_build_webhook_payload(
        $order_id,
        false,
        array(
          'x_tarjet'        => $FiltroTarjeta,
          'error_mensaje'   => $Return['errMsg'],
        )
      ),
      $cancelar
    );
  }

  if (isset($Return['ipsRc'])) {
    if ($Return['ipsRc'] == '00') {


      $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET status = ?, authCode = ?, rrn = ?, intRef = ?, merchTokenId = ?, eci = ?, ipsRc = ?, orderId = ? WHERE orden = ?");
      $sql->execute([1, $Return['authCode'], $Return['rrn'], $Return['intRef'], $Return['merchTokenId'], $Return['eci'], $Return['ipsRc'], $Return['orderId'], $order_id]);

      beepay_finish_payment(
        $webhook_url,
        beepay_build_webhook_payload(
          $order_id,
          true,
          array(
            'x_trans_id' => $Return['authCode'],
            'x_amount'   => $amount,
            'x_rrn'      => $Return['rrn'],
            'x_tarjet'   => $FiltroTarjeta,
          )
        ),
        $cancelar
      );
    }
  }

  $threeDSMethodURL  = "";
  $threeDSMethodData = "";
  if (isset($Return['threeDSMethodURL'])) {
    $threeDSMethodURL  = $Return['threeDSMethodURL'];
    $threeDSMethodData = $Return['threeDSMethodData'];

    echo '<form id="threeDSMethodDataForm" name="threeDSMethodDataForm" method="post" action="' . $threeDSMethodURL . '" style="display:none;">
                    <input type="hidden" id="threeDSMethodData" name="threeDSMethodData" value="' . $threeDSMethodData . '">
                    <input type="submit" value="enviar">
                </form>
                <script>
                setTimeout(function() {
                    document.getElementById("threeDSMethodDataForm").submit();
                }, 200);
                </script>';
  } else {
    if (isset($Return['errMsg'])) {
      $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET status = ?,  comments = ? WHERE orden = ?");
      $sql->execute([3,   $Return['errMsg'], $order_id]);

      echo '<script>
                  setTimeout(function(){
                   top.location="' . $cancelar . '?message=Transaction Failed: ' . $Return['errMsg'] . '&result=error";
                      },2500);
                  </script>';
      exit();
    }

    if (isset($Return['creq'])) {
      echo 'Please wait...<form id="threeDSMethodDataForm" name="threeDSMethodDataForm" method="post" action="' . $Return['action'] . '">
                      <input name="creq" type="hidden" value="' . $Return['creq'] . '">
                      <input name="threeDSSessionData" type="hidden" value="' . $Return['threeDSSessionData'] . '">
                      <input type="submit" value="enviar" style="display:none">
                      <script type="text/javascript">
                  setTimeout(function() {
                      document.threeDSMethodDataForm.submit();
                  }, 200);
                  </script>
                  </form>';
    }
  }



  if (! empty($respuesta)) {
    if ($respuesta['IsoResponseCode'] == "SP4") {
      echo $respuesta['RedirectData'];
    } else {
  ?>
      <script>
        //window.parent.location.href='<?php echo $cancelar; ?>?error_mensaje=<?php echo $respuesta['ResponseMessage']; ?>#fac';
      </script>
    <?php
    }
  } else {
    ?>
    <script>
      //window.parent.location.href='<?php echo $cancelar; ?>?error_mensaje=#fac';
    </script>
  <?php
  }
  ?>
</body>

</html>
