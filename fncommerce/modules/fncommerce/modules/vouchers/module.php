<?php
/**
 * module.php created on 22/gen/2009
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
 class fnc_vouchers
 {
 	var $orderstatus;
 	function __construct($orderstatus)
 	{
 		$this->orderstatus=$orderstatus;
 	}
 
  	
 	//verifica se con l' ordine corrente questo step e' abilitato
 	function is_enabled()
 	{
 		$list_enabled_modules="";
 		include("modules/fncommerce/modules/vouchers/config.php");
 		if ($list_enabled_modules=="")
 			return false;
 		
		$list_enabled_modules=explode(",",$list_enabled_modules);
 		//cerco di capire a priori se c'è qualche sconto da mostrare
 		foreach($list_enabled_modules as $modulo)
 		{
 			$classname="fnc_vouchers_".$modulo;
 			if (file_exists("modules/fncommerce/modules/vouchers/$modulo/module.php"))
 			require_once ("modules/fncommerce/modules/vouchers/$modulo/module.php");
 			$class= new $classname($this->orderstatus);
 		 	if ($class->is_enabled())
 		 		return true;
 		}
  		
 		return true;
 	}
 }
 
?>