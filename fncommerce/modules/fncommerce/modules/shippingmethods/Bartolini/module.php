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
		$cost=0;
		include ("modules/fncommerce/modules/shippingmethods/Bartolini/config.php");
		$cost = array (
			'title' => "Bartolini",
			'total' => $cost
		); //deve tornare dalla funzione del modulo
		$this->order['costs']["shippingmethods"] = $cost;
		return $this->order;
	}
	function get_orderstatus()
	{
		$urltraching="";
		include ("modules/fncommerce/modules/shippingmethods/Bartolini/config.php");
		if ($this->order['trackingnumber']!="")
		{
			$link=str_replace("{trackingnumber}",$this->order['trackingnumber'],$urltraching);
			return "<br /><br />"._BARTOLINIORDERTRACKING.":<a target=\"_blank\" href=\"$link\">Tracking number:".$this->order['trackingnumber']."</a>";
		}
		return "";
	}	
}


?>

