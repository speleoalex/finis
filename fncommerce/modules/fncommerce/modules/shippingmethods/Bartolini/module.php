<?php
global $_FN;

if (file_exists("modules/fncommerce/modules/shippingmethods/Bartolini/languages/{$_FN['lang']}.php"))
	require_once ("modules/fncommerce/modules/shippingmethods/Bartolini/languages/{$_FN['lang']}.php");
else
	require_once ("modules/fncommerce/modules/shippingmethods/Bartolini/languages/en.php");

class fnc_shippingmethods_Bartolini
{
	var $order;
	function __construct ($order)
	{
		$this->order=$order;
	}
	function title()
	{
		return "Bartolini corriere espresso ";
	}
	function description()
	{
		return "(consegna in 24/48 ore)";
	}
	function show_option($order)
	{
		$ck="";
		if (isset($order['shippingmethods']) && $order['shippingmethods']=="Bartolini")
			$ck="checked=\"checked\"";
		echo "<input $ck  name=\"shippingmethods\" value=\"Bartolini\" type=\"radio\" />".$this->title()."<br>";
	}
	
	function get_total ()
	{
		$config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/Bartolini/config.php");
		$costvalue = isset($config['cost']) ? $config['cost'] : 0;
		$cost = array (
			'title' => "Bartolini",
			'total' => $costvalue
		); //deve tornare dalla funzione del modulo
		$this->order['costs']["shippingmethods"] = $cost;
		return $this->order;
	}
	function get_orderstatus()
	{
		$config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/Bartolini/config.php");
		$urltraching = isset($config['urltraching']) ? $config['urltraching'] : "";
		if (isset($this->order['trackingnumber']) && $this->order['trackingnumber']!="" && $urltraching!="")
		{
			$link=str_replace("{trackingnumber}",$this->order['trackingnumber'],$urltraching);
			return "<br /><br />".FN_Translate("Order tracking").":<a target=\"_blank\" href=\"$link\">".FN_Translate("Tracking number").":".$this->order['trackingnumber']."</a>";
		}
		return "";
	}	
}


?>

