<?php
/*
 * module.php created on 13/feb/2009
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
 class fnc_discount
 {
 	var $orderstatus;
 	function fnc_discount($orderstatus)
 	{
 		$this->orderstatus=$orderstatus;
 	}
 	//verifica se con l' ordine corrente questo step e' abilitato
 	function is_enabled()
 	{
 		
 		$_config = FN_LoadConfig("modules/fncommerce/modules/discount/config.php");
 		$list_enabled_modules = isset($_config['list_enabled_modules']) ? $_config['list_enabled_modules'] : "";
 		if ($list_enabled_modules=="")
 			return false;
 		$list_enabled_modules=explode(",",$list_enabled_modules);
 		//cerco di capire a priori se c'è qualche sconto da mostrare
 		foreach($list_enabled_modules as $modulo)
 		{
 			$classname="fnc_discount_".$modulo;
 			if (file_exists("modules/fncommerce/modules/discount/$modulo/module.php"))
 			require_once ("modules/fncommerce/modules/discount/$modulo/module.php");
 			$class= new $classname($this->orderstatus);
 		 	if ($class->is_enabled())
 		 		return true;
 		}
 		
 		return false;
 	}
 }
 
?>
