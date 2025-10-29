<?php

global $_FN;
if (file_exists("modules/fncommerce/modules/payments/Payment_on_lockers/languages/{$_FN['lang']}.php"))
    require_once ("modules/fncommerce/modules/payments/Payment_on_lockers/languages/{$_FN['lang']}.php");
else
    require_once ("modules/fncommerce/modules/payments/Payment_on_lockers/languages/en.php");

class fnc_payments_Payment_on_lockers
{

    var $order;

    /**
     * 
     * 
     */
    function __construct($order)
    {
        $this->order = $order;
    }

    function title()
    {
        return Payment_on_lockers;
    }

    function description()
    {
        return "";
    }

    /**
     * ricava costo transazione e ritorna l' ordine aggiornato
     * 
     */
    function show_option($order)
    {
        $payment = "Pagamento alla consegna";
        if (isset($order['shippingmethods']) && $order['shippingmethods'] == "LOCKERS")
        {
            $payment = "Pagamento al ritiro tramite NEXIPay";
        }
        $ck = "";
        if (isset($order['payments']) && $order['payments'] == "Payment_on_lockers")
            $ck = "checked=\"checked\"";
        echo "<input $ck name=\"payments\" value=\"Payment_on_lockers\" type=\"radio\">" . $payment;
        if (($c = $this->get_cost()) > 0)
            echo "&nbsp;(" . Payment_on_lockers_price . " " .
            fnc_format_price($c) .
            ")";
        echo "<br />";
    }

    function get_cost()
    {
        $cost = 0;
        include ("modules/fncommerce/modules/payments/Payment_on_lockers/config.php");
        return $cost;
    }

    /**
     * ricava costo transazione e ritorna l' ordine aggiornato
     * 
     */
    function get_total()
    {
        $cost = $this->get_cost();
        $cost = array(
            'title' => Payment_on_lockers,
            'total' => $cost
        ); //deve tornare dalla funzione del modulo
        $this->order['costs']["payments"] = $cost;
        return $this->order;
    }

    /**
     *  costo pagamento
     * 
     */
    function do_payment()
    {
        $iban = "";
        include ("modules/fncommerce/modules/payments/Payment_on_lockers/config.php");

        $str = Payment_on_lockers;
        return $str;
    }

}

?>
