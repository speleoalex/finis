<?php
/**
 * config.php created on 17/gen/2009
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

//[it]Costo spedizione di default
//[en]Default shipping cost
$config['cost'] = 10;
//[it]Tabella per costi di spedizione {misc/fndatabase/fnc_shippingzones_*.php}
//[en]Shipping cost table {misc/fndatabase/*.php)
$config['costzonestable'] = "fnc_shippingzones_Default.php";
//[it]URL per tracciamento
//[en]URL traccking
$config['urltraching'] = "http://www.dhl.it/publish/it/it/eshipping/track.high.html?pageToInclude=RESULTS&AWB={trackingnumber}&type=fasttrack";

?>