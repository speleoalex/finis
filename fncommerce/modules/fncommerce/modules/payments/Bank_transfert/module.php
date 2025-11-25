<?php
global $_FN;
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
		return FN_Translate("Bank transfer");
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
		$config = FN_LoadConfig("modules/fncommerce/modules/payments/Bank_transfert/config.php");
		$costvalue = isset($config['cost']) ? $config['cost'] : 0;

		$option = array(
			'id' => 'Bank_transfert',
			'title' => FN_Translate("Bank transfer"),
			'description' => '', // Optional description
			'cost' => ($costvalue > 0) ? fnc_format_price($costvalue) : ''
		);

		return $option;
	}

	/**
	 * ricava costo transazione
	 * 
	 */
	function get_total()
	{
		$config = FN_LoadConfig("modules/fncommerce/modules/payments/Bank_transfert/config.php");
		$costvalue = isset($config['cost']) ? $config['cost'] : 0;
		$cost = array(
			'title' => "Bank_transfer",
			'total' => $costvalue
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
		$config = FN_LoadConfig("modules/fncommerce/modules/payments/Bank_transfert/config.php");
		$owner = isset($config['owner']) ? $config['owner'] : "";
		$bank = isset($config['bank']) ? $config['bank'] : "";
		$iban = isset($config['iban']) ? $config['iban'] : "";

		$str = "<b>" . FN_Translate("Payment information") . ":</b><br />";
		$str .= fnc_format_price($this->order['total']) . "<br /><br />";
		$str .= FN_Translate("To pay to") . ":";
		$str .= "<br />" . FN_Translate("Owner") . ":$owner";
		$str .= "<br />" . FN_Translate("Bank") . ":$bank";
		$str .= "<br />" . FN_Translate("IBAN") . ":$iban";
		$str .= "<br /><br />" . FN_Translate("We will process your order as soon as we receive the payment. Thank you");
		return $str;
	}
}
