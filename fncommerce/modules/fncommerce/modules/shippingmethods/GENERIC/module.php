<?php
global $_FN;
class fnc_shippingmethods_GENERIC
{
	var $order;
	function __construct($order)
	{
		$this->order = $order;
	}
	function title()
	{
		return FN_Translate("express courier");
	}
	function description()
	{
		return FN_Translate("Shipping via courier, shipping times vary depending on the destination");
	}
	/**
	 * Calcola il costo di spedizione in base alla tabella zone/costi.
	 * Ritorna un valore numerico. Usato sia da show_option() (visualizzazione)
	 * sia da get_total() (totale ordine), cosi' il prezzo mostrato in pagina
	 * coincide con quello applicato all'ordine.
	 */
	function calculate_cost()
	{
		global $_FN;
		$config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/GENERIC/config.php");
		$cost = isset($config['cost']) ? $config['cost'] : 0;
		$costzonestable = isset($config['costzonestable']) ? $config['costzonestable'] : "";
		$costzonestable = preg_replace('/\.php$/', '', $costzonestable); //normalize: table name has no .php suffix
		//fallback robusto: se la tabella zone configurata non esiste (es. valore di config errato dal pannello), usa Default
		if ($costzonestable=="" || !xmltableexists("fndatabase",$costzonestable,$_FN['datadir']))
			$costzonestable = "fnc_shippingzones_Default";
		if ($costzonestable=="" || !xmltableexists("fndatabase",$costzonestable,$_FN['datadir']))
			return $cost;

		//-----peso totale del carrello----->
		$cart = (isset($this->order['cart']) && is_array($this->order['cart'])) ? $this->order['cart'] : fnc_get_cart();
		if (!is_array($cart))
			$cart = array();
		$weight = 0;
		foreach ($cart as $p)
		{
			$qta = isset($p['qta']) ? $p['qta'] : 1;
			$p = fnc_getproduct($p['pid']);
			if (!isset($p['weight']) || $p['weight']=="")
				$p['weight'] = 0;
			$weight += $p['weight'] * $qta;
		}
		//-----peso totale del carrello-----<

		$table_cost_zones = new XMETATable("fndatabase", "$costzonestable", $_FN['datadir']);
		//-----recupero country/zona dell'utente----->
		$listaddress = fnc_get_shipping_values($_FN['user']);
		$country = "";
		$zone = "";
		if (isset ($listaddress[0]['unirecid']))
		{
			$country = $listaddress[0]['shippingcountry'];
			$zone = $listaddress[0]['shippingzone'];
		}
		//-----recupero country/zona dell'utente-----<

		//-----scelta della riga zona: matching lato PHP per gestire valori vuoti/mancanti----->
		$allzones = $table_cost_zones->GetRecords();
		if (!is_array($allzones))
			$allzones = array();
		$record = false;
		//1) match esatto per zona (non vuota)
		if ($zone !== "")
		{
			foreach ($allzones as $r)
			{
				if (isset($r['zone']) && trim($r['zone']) !== "" && (string)$r['zone'] === (string)$zone)
				{
					$record = $r;
					break;
				}
			}
		}
		//2) match esatto per nazione (non vuota)
		if (!$record && $country !== "")
		{
			foreach ($allzones as $r)
			{
				if (isset($r['country']) && trim($r['country']) !== "" && (string)$r['country'] === (string)$country)
				{
					$record = $r;
					break;
				}
			}
		}
		//3) riga di default: nazione e zona vuote (o assenti) = resto del mondo
		if (!$record)
		{
			foreach ($allzones as $r)
			{
				$rc = isset($r['country']) ? trim($r['country']) : "";
				$rz = isset($r['zone']) ? trim($r['zone']) : "";
				if ($rc === "" && $rz === "")
				{
					$record = $r;
					break;
				}
			}
		}
		//-----scelta della riga zona-----<

		if (isset($record['tablename']) && $record['tablename']!="")
		{
			$costtable = preg_replace('/\.php$/', '', $record['tablename']); //normalize: table name has no .php suffix
			$rows = array();
			if (xmltableexists("fndatabase", $costtable, $_FN['datadir']))
			{
				$table_cost = new XMETATable("fndatabase", "$costtable", $_FN['datadir']);
				$rows = $table_cost->GetRecords();
			}
			if (!is_array($rows))
				$rows = array();
			//scelta della fascia: il peso massimo piu' piccolo >= peso carrello
			$best = false;
			foreach ($rows as $row)
			{
				$w = (isset($row['weight']) && $row['weight'] !== "") ? (float)$row['weight'] : 0;
				if ($weight <= $w)
				{
					if ($best === false || $w < (float)$best['weight'])
						$best = $row;
				}
			}
			//carrello piu' pesante di tutte le fasce: usa la fascia piu' alta disponibile
			if ($best === false)
			{
				foreach ($rows as $row)
				{
					if ($best === false || (float)$row['weight'] > (float)$best['weight'])
						$best = $row;
				}
			}
			if ($best !== false && isset($best['price']))
				$cost = $best['price'];
		}

		return $cost;
	}
	/**
	 * Returns option data for display (new style)
	 * Returns array with: id, title, description, cost
	 */
	function show_option($order)
	{
		$cost = $this->calculate_cost();
		$option = array(
			'id' => 'GENERIC',
			'title' => $this->title(),
			'description' => $this->description(),
			'cost' => ($cost > 0) ? fnc_format_price($cost) : ''
		);
		return $option;
	}
	function get_total()
	{
		$cost = $this->calculate_cost();
		$cost = array (
			'title' => $this->title(),
			'total' => $cost
		); //deve tornare dalla funzione del modulo
		$this->order['costs']["shippingmethods"] = $cost;
		return $this->order;
	}
	function get_orderstatus()
	{
		$config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/GENERIC/config.php");
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
