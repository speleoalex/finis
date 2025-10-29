<?php
/**
 * config.php created on 17/gen/2009
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

//[it]Costo spedizione di default
//[en]Default shipping cost
$cost = 9;
//[it]Tabella per costi di spedizione {misc/fndatabase/fnc_shippingzones_*.php}
//[en]Shipping cost table {misc/fndatabase/*.php)
$costzonestable = "fnc_shippingzones_Default";
//[it]URL per tracciamento 
//[en]URL traccking
$urltraching = "http://www.dhl.it/publish/it/it/eshipping/track.high.html?pageToInclude=RESULTS&AWB={trackingnumber}&type=fasttrack";

?>