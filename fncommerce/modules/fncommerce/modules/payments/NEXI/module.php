<?php

function NEXI_Button($order)
{
    global $_FN;
    $html = "";
    $config = FN_LoadConfig("modules/fncommerce/modules/payments/NEXI/config.php");
    if ($order['orderstatus'] != "opened")
    {
        return "pagamento eseguito";
    }
// Pagamento semplice - Avvio pagamento
// Alias e chiave segreta
    /*
      $orderid = FN_GetParam("orderid",$_GET,"flat");
      if ($orderid=="")
      $orderid = FN_GetParam("pk___xdb_fnc_orders",$_GET,"flat");
      $order = fnc_get_order($orderid);
     *
     */
    $res = FN_GetParam("res", $_REQUEST, "html");
    if ($res == "ok")
    {
        //dprint_r($order);
        $order['orderstatus'] = "working";
        fnc_save_order_status($order);
        return "pagamento eseguito";
//        die ("pagamento eseguito");
    }
    $user = FN_GetUser($order['username']);
    if (!FN_IsAdmin() && $_FN['user'] != $order['username'])
    {
        die();
    }
    $ALIAS = isset($config['ALIAS']) ? $config['ALIAS'] : ''; // Sostituire con il valore fornito da Nexi
    $CHIAVESEGRETA = isset($config['CHIAVESEGRETA']) ? $config['CHIAVESEGRETA'] : ''; // Sostituire con il valore fornito da Nexi
    $requestUrl = "https://int-ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet";
    // $merchantServerUrl = "https://" . $_SERVER['HTTP_HOST'] . "/xpay/php/pagamento_semplice/codice_base/";
    $divisa = "EUR";
    $importo = $order['total'] * 100; //; 5000 = 50 euro;
    $ordernumber = $order['unirecid'];
    $codTrans = "ORDER_{$ordernumber}";

    // Calcolo MAC
    $mac = sha1('codTrans=' . $codTrans . 'divisa=' . $divisa . 'importo=' . $importo . $CHIAVESEGRETA);

    // Parametri obbligatori
    $obbligatori = array(
        'alias' => $ALIAS,
        'importo' => $importo,
        'divisa' => $divisa,
        'codTrans' => $codTrans,
        'url' => $_FN['siteurl'] . "index.php?mod={$_FN['mod']}&op=orderstatus&orderid=$ordernumber&res=ok",
        'url_back' => $_FN['siteurl'] . "index.php?mod={$_FN['mod']}&op=orderstatus&orderid=$ordernumber", //annullo
        'mac' => $mac,
    );

// Parametri facoltativi
    $facoltativi = array(
        'Note1' => "NOTA 1",
        'nome' => $user['name'],
        'cognome' => $user['surname'],
    );

    $requestParams = array_merge($obbligatori, $facoltativi);
    $html .= "<form method='post' action='$requestUrl'>";
    foreach ($requestParams as $name => $value)
    {
        $html .= "<input type='hidden' name='$name' value='" . htmlentities($value) . "' />";
    }
    $html .= "<br /><input class='btn btn-primary' type='submit' value='Procedi con il pagamento' /> </form>";
    return $html;
}

global $_FN;

class fnc_payments_NEXI
{

    var $order;

    function __construct($order)
    {
        $this->order = $order;
    }

    function title()
    {
        return "NEXI Pay";
    }

    function description()
    {
        return "";
    }

    function show_option($order)
    {
        global $_FN;
        $ck = "";
        if (isset($order['payments']) && $order['payments'] == "Paypal")
            $ck = "checked=\"checked\"";

        echo "<input $ck name=\"payments\" value=\"NEXI\" type=\"radio\">" . $this->title() . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "<br />";
    }

    function get_cost()
    {
        $paypal_cost = 0;
        return $paypal_cost;
    }

    function get_total()
    {
        $paypal_cost = $this->get_cost();
        $cost = array(
            'title' => "NEXI Pay",
            'total' => $paypal_cost
        ); //deve tornare dalla funzione del modulo
        $this->order['costs']["payments"] = $cost;
        return $this->order;
    }

    /**
     * METODO RICHIESTO PER TUTTI I MODULI DI PAGAMENTO
     * Visualizza le istruzioni per pagare l'ordine
     * 
     * 
     */
    function do_payment()
    {
        global $_FN;
        $paypal_email = "";
        $valuta = $_FN['currency'];

        return NEXI_Button($this->order);

        return $ret;
    }

}
?>

