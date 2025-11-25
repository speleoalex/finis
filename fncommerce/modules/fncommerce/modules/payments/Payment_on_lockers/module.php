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
     * Returns option data for display (new style)
     * Returns array with: id, title, description, cost
     */
    function show_option($order)
    {
        $payment = "Pagamento alla consegna";
        if (isset($order['shippingmethods']) && $order['shippingmethods'] == "LOCKERS")
        {
            $payment = "Pagamento al ritiro tramite NEXIPay";
        }

        $cost = $this->get_cost();

        $option = array(
            'id' => 'Payment_on_lockers',
            'title' => $payment,
            'description' => '', // Optional description
            'cost' => ($cost > 0) ? fnc_format_price($cost) : ''
        );

        return $option;
    }

    function get_cost()
    {
        $config = FN_LoadConfig("modules/fncommerce/modules/payments/Payment_on_lockers/config.php");
        return isset($config['cost']) ? $config['cost'] : 0;
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
        $config = FN_LoadConfig("modules/fncommerce/modules/payments/Payment_on_lockers/config.php");

        $str = Payment_on_lockers;
        return $str;
    }

}

?>
