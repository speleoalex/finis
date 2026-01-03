<?php
/**
 * @package flatnux_controlcenter_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
FN_LoadMessagesFolder(__DIR__ . "/../../../../modules/fncommerce/modules/vouchers/voucher_code/");
$params['fields'] = "code|discount|minprice|startdate|enddate|enabled|max_uses|NumberUsed()";
FNCC_XMETATableEditor("fnc_vouchercodes",$params);
function NumberUsed($id,$Table)
{
	$code = $Table->xmltable->GetRecordByPrimaryKey($id);
	$code = isset($code['code']) ? $code['code'] : 0;
	$used = FN_XMLQuery("SELECT COUNT(*) AS used FROM fnc_vouchercodesused WHERE code LIKE '$code'");


	$used = isset($used[0]['used']) ? $used[0]['used'] : "<span style=\"color:red\">-</span>";
	return $used;
}

?>
