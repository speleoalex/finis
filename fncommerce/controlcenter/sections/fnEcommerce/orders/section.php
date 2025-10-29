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
require_once("modules/fncommerce/functions/fncommerce.php");
$opt = FN_GetParam("opt", $_GET, "html");
$opmod = FN_GetParam("opmod", $_GET, "html");
if ($opmod == "")
	$opmod = FN_GetParam("op___xdb_fnc_orders", $_GET, "html");

$mode = FN_GetParam("mode", $_GET, "html");

$order_id = FN_GetParam("unirecid", $_POST, "flat");
$status = FN_GetParam("orderstatus", $_GET, "html");
$restr = false;
if ($status != "")
	$restr = array("orderstatus" => $status);
$status_items = fnc_get_orderstatus();
if ($opmod == "") {
	echo "<form method=\"get\"  action=\"\" name=\"ord\">";
	echo FN_Translate("order status") . ":<select  name=\"orderstatus\" onchange=\"window.location='?opt=$opt&orderstatus='+ document.ord.orderstatus.options[document.ord.orderstatus.selectedIndex].value + '&amp;mod={$_FN['mod']}&amp;mode=$mode&amp;opt=$opt'\">";
	echo "\n<option value=\"\">" . FN_TRanslate("all") . "</option>";
	foreach ($status_items as $item) {
		echo "\n<option value=\"{$item['unirecid']}\"";
		if ($status == $item['unirecid']) {
			echo ' selected="selected" ';
		}
		echo ">{$item['name']}</option>";
	}
	echo "
			</select>";
	echo "</form>";
}
if ($mode == "summary") {
	echo "<a href=\"?mod={$_FN['mod']}&amp;opt=$opt&amp;mode=\">Orders</a><br />";
	$table = new XMETATable("fndatabase", "fnc_orders", $_FN['datadir']);
	//($restr = false,$min = false,$length = false,$order = false,$reverse = false,$fields = false)
	$orders = $table->GetRecords($restr, false, false, "unirecid");
	foreach ($orders as $order) {
		echo "<hr />";
		echo html_orderstatus($order['unirecid']);
		echo "<hr />";
	}
	//dprint_r($orders);
} else {
	echo "<a href=\"?mod={$_FN['mod']}&amp;opt=$opt&amp;mode=summary\">Summary</a><br />";
	//FN_XmltableEditor("fnc_orders","fndatabase","","fnc_admin_orders",$restr,false,false,false,false,array("enableview"=>true));

	FNCC_XMETATableEditor("fnc_orders", array("enabledelete" => false, "enableview" => true, "restr" => $restr, "functioninsert" => "fnc_admin_orders", "list_onupdate" => false));

	if ($order_id == "") {
		$order_id = FN_GetParam("pk___xdb_fnc_orders", $_GET, "flat");
	}
	if ($order_id != "" && $opmod != "del") {
		echo "<div style=\"border:1px inset\">";
		echo html_orderstatus($order_id);
		echo "</div>";
		echo "<br />";
	}
}
/**
 *
 * @param type $newvalues 
 */
function fnc_admin_orders($newvalues)
{
	$opmod = FN_GetParam("op___xdb_fnc_orders", $_GET, "html");
	$order_id = isset($newvalues['unirecid']) ? $newvalues['unirecid'] : "";
	if ($order_id == "")
		$order_id = FN_GetParam("pk", $_GET, "html");

	switch ($opmod) {
		case "insnew":
			if (!isset($newvalues['orderstatus'])) {
				$pk = FN_GetParam("pk___xdb_fnc_orders", $_GET, "flat");
				$newvalues = fnc_get_order($pk);
			}
			if (isset($_POST['unirecid'])) {
				$ordervalues = fnc_get_order($newvalues['unirecid']);
				fnc_send_changestatus($ordervalues);
				//dprint_r($ordervalues);
			}
			break;
		default:
			break;
	}
}
