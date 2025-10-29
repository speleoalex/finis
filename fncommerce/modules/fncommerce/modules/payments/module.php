<?php
/**
 * module.php created on 22/gen/2009
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
 class fnc_payments
 {
 	var $orderstatus;
 	function __construct($orderstatus)
 	{
 		$this->orderstatus=$orderstatus;
 	}
 
  	
 	//verifica se con l' ordine corrente questo step e' abilitato
 	function is_enabled()
 	{
 		return true;
 	}

 }
 
?>