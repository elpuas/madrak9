<div style="text-align: center; width: 100%"><br><br><br><img src="logo.png" width="200px"><br><br><br>
<div class="lds-spinner"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
<h3>Espere por favor</h3>
</div>
<?php

    include 'config.php';


    $data = [
        "grant_type"    => "password",
        "client_id"     => $BEE_CLIENT_ID,
        "client_secret" => $BEE_CLIENT_SECRET,
        "username"      => $BEE_USER,
        "password"      => $BEE_PASSWORD,
        "scope"         => $BEE_SCOPE,
      ];

      $order_id = $_REQUEST['pid'];

      $sql = $baseDeDatos->prepare("SELECT * FROM beepay_transactions WHERE orden = ? ORDER BY id DESC LIMIT 1");
      $sql->execute([$order_id]);
      $results = $sql->fetchAll(PDO::FETCH_OBJ);
  
      foreach ($results as $Transaccion) {
  
        
          $webhook_url    = $Transaccion->retorno;
          $Cancelar       = $Transaccion->cancelar;
          $PurchaseAmount = $Transaccion->monto;
          $Tarjeta        = $Transaccion->cuenta;
        
  
      }
      
      
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
      
      $Token = json_decode($response, true);



        $merchRefId = isset($_REQUEST['merchRefId']) ? $_REQUEST['merchRefId'] : '';
        $data       = [
            "threeDSMethodData"  => $_POST['threeDSMethodData'],
            "threeDSMethodState" => "N",
        ];

        $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET threeDSSessionData = ? WHERE orden = ?");
        $sql->execute([ $_POST['threeDSMethodData'], $order_id]);

        $json_data = json_encode($data);

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
            CURLOPT_POSTFIELDS     => $json_data,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $Token['token_type'] . ' ' . $Token['access_token'],
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);


        curl_close($curl);
        $Return = json_decode($response, true);

        $Recurrent = false;
        if (isset($Return['errMsg'])) {

            
             $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET status = ?, rrn = ?,  ipsRc = ?,eci = ?, comments = ? WHERE orden = ?");
              $sql->execute([3,  $Return['rrn'],  $Return['ipsRc'], $Return['eci'], $Return['errMsg'], $order_id]);

              beepay_finish_payment(
                  $webhook_url,
                  beepay_build_webhook_payload(
                      $order_id,
                      false,
                      array(
                          'x_tarjet'      => $Tarjeta,
                          'error_mensaje' => $Return['errMsg'],
                      )
                  ),
                  $Cancelar
              );
        
        }
        if (isset($Return['ipsRc'])) {
            if ($Return['ipsRc'] == '00') {
              

                    $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET status = ?, authCode = ?, rrn = ?, intRef = ?, merchTokenId = ?, eci = ?, ipsRc = ?, threeDSSessionData = ?, orderId = ? WHERE orden = ?");
                    $sql->execute([1, $Return['authCode'], $Return['rrn'], $Return['intRef'], $Return['merchTokenId'], $Return['eci'], $Return['ipsRc'], $Return['threeDSSessionData'], $Return['orderId'], $order_id]);

                    beepay_finish_payment(
                        $webhook_url,
                        beepay_build_webhook_payload(
                            $order_id,
                            true,
                            array(
                                'x_trans_id' => $Return['authCode'],
                                'x_amount'   => $PurchaseAmount,
                                'x_rrn'      => $Return['rrn'],
                                'x_tarjet'   => $Tarjeta,
                            )
                        ),
                        $Cancelar
                    );
            }
        }
        if (isset($Return['action'])) {


            
            $sql = $baseDeDatos->prepare("UPDATE beepay_transactions SET threeDSSessionData = ? WHERE orden = ?");
            $sql->execute([ $Return['threeDSSessionData'], $order_id]);

            echo 'Please wait...<form id="threeDSMethodDataForm" name="threeDSMethodDataForm" method="post" action="' . $Return['action'] . '">
                            <input name="creq" type="hidden" value="' . $Return['creq'] . '">
                            <input name="threeDSSessionData" type="hidden" value="' . $Return['threeDSSessionData'] . '">
                            <input type="submit" value="enviar" style="display:none">
                            <script type="text/javascript">
                        setTimeout(function() {
                                document.getElementById("threeDSMethodDataForm").submit();
                                }, 200);
                        </script>
                        </form>';
        }
