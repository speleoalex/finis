<?php
/**
 * @package flatnux_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;
include ("modules/fncommerce/functions/fncommerce.php");

$cat=FN_GetPAram("cat",$_GET,"html");
$pid=FN_GetPAram("id",$_GET,"html");
$op=FN_GetPAram("op",$_GET,"html");

$title=$_FN['sitename'];
if ($cat != "")
{
	$p=fnc_getcategory($cat);

	$title .= " - " . htmlentities($p ['name'],ENT_QUOTES);
}

$_FN['sitename']=$title;

if ($op == "view")
{

	if ($pid != "")
	{
		$p=fnc_getproduct($pid);
		$title .= " - " . htmlentities($p ['name'],ENT_QUOTES);
	}
}
$_FN['site_title'] .= " - ".$title;
$_FN['section_header_footer'] .= "\n<link rel='StyleSheet' type='text/css' href='modules/fncommerce/functions.css' >\n";
?>