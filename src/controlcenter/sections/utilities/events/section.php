<?php

/**
 * @package Finis_controlcenter
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2021
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;
$tablename = "fn_log";
if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/fn_log.php"))
{
    FN_Copy("{$_FN['src_finis']}/include/install/fndatabase/fn_log.php", "{$_FN['datadir']}/{$_FN['database']}/fn_log.php");
}

$tablefrm = FN_XMDBForm($tablename);
$opt = FN_GetParam("opt", $_GET, "html");

$params['enableview'] = true;
$params['enabledelete'] = false;
$params['enablenew'] = false;
$params['enableedit'] = false;
$params['defaultorder'] = "data";
$params['defaultorderdesc'] = true;
$link_filters = "";
$fields_filters = array("context", "user", "date%"); //todo
$fields_filters = array("context", "user", "date");

$params['link'] = "opt=$opt&amp;mod={$_FN['mod']}&amp;{$link_filters}";

//echo FNCC_HtmlFilters($tablefrm, $fields_filters, $link_filters);

$array_filters = json_decode($link_filters, JSON_OBJECT_AS_ARRAY);
$params['filters'] = "";
$link_filters = "&amp;filter=" . json_encode($array_filters);
if (is_array($array_filters))
{
    $params['filters'] = $array_filters;
}



FNCC_XmltableEditor($tablename, $params);
?>