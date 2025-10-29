<?php
global $_FN;
class fnc_shippingmethods_DHL
{
	var $order;
	function __construct($order)
	{
		$this->order = $order;
	}
	function title()
	{
		return "DHL corriere espresso ";
	}
	function description()
	{
		return "(consegna in 24/48 ore)";
	}
	function show_option($order)
	{
		$ck="";
		if (isset($order['shippingmethods']) && $order['shippingmethods']=="DHL")
			$ck="checked=\"checked\"";
		echo "<input $ck  name=\"shippingmethods\" value=\"DHL\" type=\"radio\" />DHL<br>";
	}
	function get_total()
	{
		global $_FN;
		$cost = 0;
		$costzonestable ="";
		include ("modules/fncommerce/modules/shippingmethods/DHL/config.php");
		if ($costzonestable!="" && xmltableexists("fndatabase",$costzonestable,$_FN['datadir']))
		{
			
			$weight=0;
			foreach ($this->order['cart'] as $p)
			{
				$qta=$p['qta'];
				$p=fnc_getproduct($p['pid']);
				if (!isset($p['weight']) || $p['weight']=="")
					$p['weight']=0;
				$weight+=$p['weight']*$qta;
			}
			$table_cost_zones = new XMETATable("fndatabase", "$costzonestable", $_FN['datadir']);
			//-----recupero country dell' utente------>
			$listaddress = fnc_get_shipping_values($_FN['user']);
			$zone="";
			if (isset ($listaddress[0]['unirecid']))
			{
				$country = $listaddress[0]['shippingcountry'];
				$zone = $listaddress[0]['shippingzone'];
			}
			//-----recupero country dell' utente------<
			$record=false;
			if ($zone!="")
				$record=$table_cost_zones->GetRecord(array("zone"=>$zone));
			
			
			if (!$record)
			{
				$record=$table_cost_zones->GetRecord(array("country"=>$country));
			}

			if (!$record)
			{
				$record=$table_cost_zones->GetRecord(array("country"=>""));
			}
			$rows=array();
			if (isset($record['tablename']) && $record['tablename']!="")
			{
				$costtable=$record['tablename'];
				$table_cost = new XMETATable("fndatabase", "$costtable", $_FN['datadir']);
				$rows=$table_cost->GetRecords();
			}
			$minok=false;
			foreach ($rows as $row)
			{
				if ($weight<=$row['weight'])
				{
					if ($minok===false)
						$minok=$row['weight'];
					if ($row['weight']<=$minok)
						$min=$row;
				}
			
			}
			$cost=isset($min['price'])?$min['price']:0;		
		
		}
		
		$cost = array (
			'title' => "DHL",
			'total' => $cost
		); //deve tornare dalla funzione del modulo
		$this->order['costs']["shippingmethods"] = $cost;
		return $this->order;
	}
	function get_orderstatus()
	{
		$urltraching="";
		include ("modules/fncommerce/modules/shippingmethods/DHL/config.php");
		if ($this->order['trackingnumber']!="")
		{
			$link=str_replace("{trackingnumber}",$this->order['trackingnumber'],$urltraching);
			return "<br /><br />"._DHLORDERTRACKING.":<a target=\"_blank\" href=\"$link\">Tracking number:".$this->order['trackingnumber']."</a>";
		}
		return "";
	}	
}
?>