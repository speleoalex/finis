<?php
/**
 * module.php created on 22/gen/2009
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
global $_FN;

if (file_exists("modules/fncommerce/modules/discount/Discount_total/languages/{$_FN['lang']}.php"))
	require_once ("modules/fncommerce/modules/discount/Discount_total/languages/{$_FN['lang']}.php");
else
	require_once ("modules/fncommerce/modules/discount/Discount_total/languages/en.php");

/**
 * il nome della classe deve essere: fnc_ + [tipodimodulo] + _ + [nome modulo]
 * 
 */
class fnc_discount_Discount_total
{
	var $order;
	/**
	 * costruttore, riceve sempre l' ordine come parametro
	 * 
	 */
	function fnc_discount_Discount_total ($order)
	{
		$this->order=$order;
	}
	
	/**
	 * Titolo del modulo chiamato nel riassunto dell'ordine
	 * 
	 */
	function title()
	{
		return "Sconto sul totale";
	}
	/**
	 * Descrizione chiamata nel riassunto dell'ordine
	 * 
	 */
	function description()
	{
		$minprice=0;
		include ("modules/fncommerce/modules/discount/Discount_total/config.php");
		
		return "per una spesa superiore a ".fnc_format_price($minprice);
	}
	/**
	 * Viene chiamata durante lo step
	 * 
	 */
	function show_option($order)
	{
		global $_FN;
		$nextstep=fnc_get_next_order_step("discount");
		//dprint_r($this->order);
		$minprice=0;
		include ("modules/fncommerce/modules/discount/Discount_total/config.php");
		
		$discount=$this->get_discount();
		if ($discount>0)
		{
			if (!isset($_POST['discount']))
			{
				echo str_replace("{discount}",fnc_format_price($discount),_FFDISCOUNTTOTALMESSAGE)." ".fnc_format_price($minprice);
				echo "<input type=\"hidden\" name=\"discount\" value=\"Discount_total\" />";
				return;
			}
		}
		else
                    FN_Redirect("?mod={$_FN['vmod']}&op=ordersteps&orderstep=$nextstep");
	}
	
	/**
	 * viene chiamata alla fine dello step per aggiornare l'ordine
	 * con il costo
	 * 
	 */
	function get_total ()
	{
		//--ricavo lo sconto per questo ordine
		$discount=$this->get_discount();
		if ($discount>0)
		{
			// aggiorno i costi  dell' ordine con un costo negativo
			$cost = array (
				'title' => _FFDISCOUNT,
				'total' => (0.0 - $discount)
			);
			$this->order['costs']["discount"] = $cost;		
		}
		return $this->order;
	}
	

	/**
	 * verifica se questa opzione è abilitata
	 * 
	 */	
	function is_enabled()
	{
		if ($this->get_discount()==0)
			return false;
		return true;
	}
	
	/**
	 * RICHIESTO 
	 * viene chiamata alla conferma dell' ordine
	 * 
	 */
	function on_order_confirm($order)
	{
 
	}
	
	
	/**
	 * METODO INTERNO NON OCORRE DICHIARARLO
	 * ricava lo sconto sul totale
	 * 
	 */
	function get_discount()
	{
		$discount=0;
		$minprice=0;
		include ("modules/fncommerce/modules/discount/Discount_total/config.php");
		//-----totale sul carrello  --->
		$totalcart=0;
		foreach($this->order['cart'] as $cartitem)
		{		
			$p=fnc_getproduct($cartitem['pid']);
			$totalcart+=$cartitem['qta']*$p['price'];
		}
		
		//die();
		//-----totale sul carrello  ---<
		if ($minprice!=0 && $totalcart>$minprice)
		{
			return $discount;
		}
		return 0;
	}
	
}

?>