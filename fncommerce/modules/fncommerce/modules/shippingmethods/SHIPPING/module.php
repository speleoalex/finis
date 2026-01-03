<?php
global $_FN;

class fnc_shippingmethods_SHIPPING
{
	var $order;
	function __construct($order)
	{
		$this->order = $order;
	}
	function title()
	{
		return FN_Translate("shipping");
	}
	function description()
	{
		return "(".FN_Translate("shipping via courier").")";
	}
	/**
	 * Returns option data for display (new style)
	 * Returns array with: id, title, description, cost
	 */
	function show_option($order)
	{
		$config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/SHIPPING/config.php");
		$cost = isset($config['cost']) ? $config['cost'] : 0;

		$option = array(
			'id' => 'SHIPPING',
			'title' => $this->title(),
			'description' => $this->description(),
			'cost' => ($cost > 0) ? fnc_format_price($cost) : ''
		);

		return $option;
	}
	function get_total()
	{
		global $_FN;
		$config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/SHIPPING/config.php");
		$cost = isset($config['cost']) ? $config['cost'] : 0;
		$costzonestable = isset($config['costzonestable']) ? $config['costzonestable'] : "";
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
			$max=array();
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
			$cost=$min['price'];
		
		
		}
		
		$cost = array (
			'title' => FN_TRanslate("shipping via courier"),
			'total' => $cost
		); //deve tornare dalla funzione del modulo
		$this->order['costs']["shippingmethods"] = $cost;
		return $this->order;
	}
	function get_orderstatus()
	{
		$config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/SHIPPING/config.php");
		$urltraching = isset($config['urltraching']) ? $config['urltraching'] : "";

		return "";
	}	
}
?>