<?php
global $_FN;
if (file_exists("modules/fncommerce/modules/payments/Bank_transfert/languages/{$_FN['lang']}.php"))
	require_once ("modules/fncommerce/modules/payments/Bank_transfert/languages/{$_FN['lang']}.php");
else
	require_once ("modules/fncommerce/modules/payments/Bank_transfert/languages/en.php");

class fnc_payments_Bank_transfert
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
		return _FFBONIFICO;
	}
	function description()
	{
		return "";
	}
	
	
	function show_option($order)
	{

		$ck="";
		if (isset($order['payments']) && $order['payments']=="Bank_transfert")
			$ck="checked=\"checked\"";
		echo "<input $ck name=\"payments\" value=\"Bank_transfert\" type=\"radio\">Bank transfer<br>";
	}

	/**
	 * ricava costo transazione
	 * 
	 */
	function get_total()
	{
		$cost=0;
		include ("modules/fncommerce/modules/payments/Bank_transfert/config.php");
		$cost = array (
			'title' => "Bank_transfer",
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
		include ("modules/fncommerce/modules/payments/Bank_transfert/config.php");

		$str = "<b>" . _FFINFOPAGAMENTO . ":</b><br />";
		$str .= fnc_format_price($this->order['total']) . "<br /><br />";
		$str .= _FFDAINTESTAREA . ":";
		$str .= "<br />" . _FFINTESTATARIO . ":$owner";
		$str .= "<br />" . _FFBANCA . ":$bank";
		$str .= "<br />" . _FFIBAN . ":$iban";
		$str .= "<br /><br />" . _FFPAGABONIFICO;
		return $str;
	}
}
