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
require_once ("modules/fncommerce/functions/fncommerce.php");
require_once ("modules/fncommerce/functions/fnc_pages.php");
$opmod = FN_GetParam("opmod", $_GET, "html");
$op = FN_GetParam("opt", $_GET, "html");
$name = FN_GetParam("name", $_POST, "html");


if ($opmod == "")
{
	echo "<div>";
	echo "Qui è possibile definire le tabelle che associano le zone ai costi di spedizione";
	echo "</div>";
}


if($opmod=="newtable" &&  $name!="")
{
	if (!is_alphanumeric($name))
	{
		echo _FNNOTVALIDFIED;	
	}
	else
	{
		$fields=array();
		$fields[1]['name'] = "unirecid";
		$fields[1]['extra'] = "autoincrement";
		$fields[1]['frm_show'] = "0";		
		$fields[1]['primarykey'] = "1";

		$fields[2]['name'] = "country";
		$fields[2]['frm_it'] = "Nazione";
		$fields[2]['frm_en'] = "Country";
		$fields[2]['frm_es'] = "Country";
		$fields[2]['frm_de'] = "Country";
		$fields[2]['foreignkey'] = "fnc_countries";
		$fields[2]['fk_link_field'] = "unirecid";
		$fields[2]['fk_show_field'] = "name";


		$fields[3]['name'] = "zone";
		$fields[3]['frm_it'] = "Zona";
		$fields[3]['frm_en'] = "Max height";
		$fields[3]['frm_es'] = "Max height";
		$fields[3]['frm_de'] = "Max height";
		$fields[3]['foreignkey'] = "fnc_zones";
		$fields[3]['fk_link_field'] = "unirecid";
		$fields[3]['fk_show_field'] = "name";


		$fields[4]['name'] = "tablename";
		$fields[4]['frm_it'] = "Tabella";
		$fields[4]['frm_en'] = "Tablename";
		$fields[4]['frm_es'] = "Tablename";
		$fields[4]['frm_de'] = "Tablename";
		$fields[4]['frm_type'] = "select";



		
	if (!file_exists("{$_FN['datadir']}/fndatabase/fnc_shippingzones_$name.php"))	
		echo createxmltable('fndatabase', 'fnc_shippingzones_'.$name, $fields, $_FN['datadir'], "shippingcostszones");
	}
}



//---lista tabelle che riguardano i costi di spedizione
$tables=glob("{$_FN['datadir']}/fndatabase/fnc_shippingzones_*.php");


//----------lista delle tabelle dei costi----------->
$options=array();
$tableshippingcosts=glob("{$_FN['datadir']}/fndatabase/fnc_shippingcosts_*.php");

foreach($tableshippingcosts as $table)
{
	$k = array();
	$k['value']=basename(fn_erg_replace('.php$','',$table));
	$k['title']=basename(fn_erg_replace('.php$','',$table));
	$k['title']=fn_erg_replace('^fnc_shippingcosts_','',$k['title']);
	$options[]=$k;
}

//----------lista delle tabelle dei costi-----------<


foreach($tables as $table)
{
	$tablename=basename(fn_erg_replace('.php$','',$table));
	if ($opmod == "" || $opmod == "insnew_$tablename" || ereg("^del_",$opmod)|| $opmod == "newtable" )
	{
		$tabletitle=fn_erg_replace('^fnc_shippingzones_','',$tablename);
		echo "<h1>$tabletitle</h1>";
		$table = new FieldFrm("fndatabase", "$tablename",  $_FN['datadir'], $_FN['lang'], $_FN['languages']);
		$table->formvals['tablename']['options'] = $options;
		//FN_XmltableEditor($tablename, "fndatabase", $_FN['datadir'], "", false,$table);
		FNCC_XMETATableEditor($tablename);
		
	}
}


if ($opmod == "")
{	echo "<form method=\"post\" action=\"?mod={$_FN['mod']}&amp;opt=$op&amp;opmod=newtable\" >";
	echo "<b>"._FNC_NEWSHIPPINGTABLEZONES.":</b><br />";
	echo FN_i18n("name").":&nbsp;<input type=\"text\" name=\"name\" value=\"\" />";
	echo "&nbsp;<input type=\"submit\"  value=\"".FN_i18n("execute")."\" />";
	echo "</form>";
}

?>
