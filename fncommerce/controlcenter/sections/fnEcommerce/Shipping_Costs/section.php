<?php
/**
 * @package flatnux_controlcenter_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;
require_once (__DIR__ . "/../../../../modules/fncommerce/functions/fncommerce.php");
require_once (__DIR__ . "/../../../../modules/fncommerce/functions/fnc_pages.php");
$opmod = FN_GetParam("opmod", $_GET, "html");
$op = FN_GetParam("opt", $_GET, "html");
$name = FN_GetParam("name", $_POST, "html");




if($opmod=="newtable" &&  $name!="")
{
	if (!is_alphanumeric($name))
	{
		echo FN_Translate("not valid field");	
	}
	else
	{
		$fields=array();
		$fields[1]['name'] = "unirecid";
		$fields[1]['extra'] = "autoincrement";
		$fields[1]['frm_show'] = "0";		
		$fields[1]['primarykey'] = "1";

		$fields[2]['name'] = "weight";
		$fields[2]['frm_it'] = "Peso massimo";
		$fields[2]['frm_en'] = "Max weight";
		$fields[2]['frm_es'] = "Max weight";
		$fields[2]['frm_de'] = "Max weight";


		$fields[3]['name'] = "height";
		$fields[3]['frm_it'] = "Altezza massima";
		$fields[3]['frm_en'] = "Max height";
		$fields[3]['frm_es'] = "Max height";
		$fields[3]['frm_de'] = "Max height";


		$fields[4]['name'] = "width";
		$fields[4]['frm_it'] = "Larghezza massima";
		$fields[4]['frm_en'] = "Max width";
		$fields[4]['frm_es'] = "Max width";
		$fields[4]['frm_de'] = "Max width";


		$fields[5]['name'] = "depth";
		$fields[5]['frm_it'] = "Profondit&agrave; massima";
		$fields[5]['frm_en'] = "Max depth";
		$fields[5]['frm_es'] = "Max depth";
		$fields[5]['frm_de'] = "Max depth";
		

		$fields[6]['name'] = "price";
		$fields[6]['frm_it'] = "Prezzo";
		$fields[6]['frm_en'] = "Price";
		$fields[6]['frm_es'] = "Price";
		$fields[6]['frm_de'] = "Price";
		
	if (!file_exists("{$_FN['datadir']}/fndatabase/fnc_shippingcosts_$name.php"))	
		echo createxmltable('fndatabase', 'fnc_shippingcosts_'.$name, $fields, $_FN['datadir'], "shippingcosts");
	}
}




if ($opmod == "")
{
	echo "<div>";
	echo FN_Translate("Here you can define shipping cost tables calculated by weight, height, width, depth. You can associate a different table for each zone");
	echo "</div>";
}




//---lista tabelle che riguardano i costi di spedizione
$tables=glob("{$_FN['datadir']}/fndatabase/fnc_shippingcosts_*.php");


foreach($tables as $table)
{
	$tablename=basename(fn_erg_replace('.php$','',$table));
	if ($opmod == "" || $opmod == "insnew_$tablename" || ereg("^del_",$opmod)|| $opmod == "newtable" )
	{
		$tabletitle=fn_erg_replace('^fnc_shippingcosts_','',$tablename);
		echo "<h1>$tabletitle</h1>";
		 FNCC_XMETATableEditor($tablename);
	}
}


	if ($opmod == "")
	{
		echo "<form method=\"post\" action=\"?mod={$_FN['mod']}&amp;opt=$op&amp;opmod=newtable\" >";
		echo "<b>".FN_Translate("new shipping table").":</b><br />";
		echo FN_i18n("name").":&nbsp;<input type=\"text\" name=\"name\" value=\"\" />";
		echo "&nbsp;<input type=\"submit\"  value=\"". FN_i18n("execute")."\" />";
		echo "</form>";
	}

 
?>