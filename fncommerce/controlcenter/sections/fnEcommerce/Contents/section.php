<?php
/**
 * 85_Contents.php created on 02/mar/2009
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */


global $_FN;
require_once (__DIR__ . "/../../../../modules/fncommerce/functions/fncommerce.php");
require_once (__DIR__ . "/../../../../modules/fncommerce/functions/fnc_pages.php");
$params['fields']="title|text";
FNCC_XMETATableEditor("fnc_contents",$params);


 
?>