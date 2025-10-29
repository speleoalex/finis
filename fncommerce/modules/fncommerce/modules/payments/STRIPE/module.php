<?php
global $_FN;

if (file_exists("modules/fncommerce/modules/payments/Paypal/languages/{$_FN['lang']}.php"))
	require_once ("modules/fncommerce/modules/payments/Paypal/languages/{$_FN['lang']}.php");
else
	require_once ("modules/fncommerce/modules/payments/Paypal/languages/en.php");

class fnc_payments_Paypal
{
	var $order;
	function __construct ($order)
	{
		$this->order=$order;
	}
	function title()
	{
		return FN_Translate("Credit card with Stripe");
	}
	function description()
	{
		return "";
	}
	function show_option($order)
	{
		global $_FN;
		$ck="";
		if (isset($order['payments']) && $order['payments']=="Paypal")
			$ck="checked=\"checked\"";

		echo "<input $ck name=\"payments\" value=\"Paypal\" type=\"radio\">".$this->title()."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
		//echo "<br />";
$h=opendir("modules/fncommerce/modules/payments/Paypal/carts");
while (false !== $file= readdir($h)) 
	if (FN_GetFileExtension($file)=="gif")
		echo	"&nbsp;<img style=\"vertical-align:middle\" alt=\"\" src=\"{$_FN['siteurl']}/fncommerce/modules/payments/Paypal/carts/$file\" />";
closedir($h);


		if (($c=$this->get_cost())>0)
			echo "&nbsp;(".FN_Translate("surcharge of"). " ".
			fnc_format_price($c).
			")";
		echo "<br />";
	
	
	}
	function get_cost ()
	{
		$config = FN_LoadConfig("modules/fncommerce/modules/payments/STRIPE/config.php");
		return isset($config['stripe_cost']) ? $config['stripe_cost'] : 0;
	}
	
	function get_total ()
	{
		$paypal_cost=$this->get_cost();
		$cost = array (
			'title' => "Paypal",
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
		$config = FN_LoadConfig("modules/fncommerce/modules/payments/STRIPE/config.php");
		$stripe_api_key = isset($config['stripe_api_key']) ? $config['stripe_api_key'] : "";
		$valuta = $_FN['currency'];

		$ret= "
<p align=\"center\">
	<br />
	<b>".FN_Translate("Pay now via Stripe")."</b>
	<br />
	<br />
";
$h=opendir("modules/fncommerce/modules/payments/Paypal/carts");
while (false !== $file= readdir($h)) 
	if (FN_GetFileExtension($file)=="gif")
		$ret.=	"&nbsp;<img alt=\"\" src=\"{$_FN['siteurl']}/fncommerce/modules/payments/Paypal/carts/$file\" />";
closedir($h);
$ret.= "<br />
	<form action=\"https://www.paypal.com/cgi-bin/webscr\" method=\"get\">
		<input type=\"hidden\" name=\"cmd\" value=\"_xclick\" />
		<input type=\"hidden\" name=\"business\" value=\"$paypal_email\" />
		<input type=\"hidden\" name=\"item_name\" value=\"{$_FN['sitename']}"." ".FN_Translate("Order number")." : ".$this->order['unirecid']."\" />
		<input type=\"hidden\" name=\"currency_code\" value=\"$valuta\" />
		<input type=\"hidden\" name=\"amount\" value=\"{$this->order['total']}\">
		<p align=\"center\">
		<input type=\"image\" src=\"http://www.paypal.com/it_IT/i/btn/x-click-but01.gif\" name=\"submit\" alt=\"".FN_Translate("Make your payments with Stripe. It is a fast, free and secure system")."\" />	
		</p>
	</form>
<br /><br />".FN_Translate("We will process your order as soon as we receive the payment. Thank you")."
</p>                                                        
";	
return $ret;
	}
}


?>

