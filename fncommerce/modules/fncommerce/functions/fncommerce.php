<?php
global $_FN;
$config = FN_LoadConfig("modules/fncommerce/config.php","fncommerce");
FN_LoadMessagesFolder(__DIR__."/");
foreach ($config as $k=>$v)
{
	$_FN[$k]=$v;
}
require_once (__DIR__."/fnc_functions.php");
require_once (__DIR__."/fnc_pages.php");
