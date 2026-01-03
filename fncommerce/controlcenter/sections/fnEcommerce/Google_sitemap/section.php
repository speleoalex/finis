<?php
if (!file_exists("./index.php") || !file_exists("./sections"))
	die();
require_once (__DIR__ . "/../../../../modules/fncommerce/functions/fncommerce.php");
/**
 * 10_Google_sitemap.php created on 10/dic/2008
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
global $_FN;
$op = FN_GetParam("opt", $_GET, "flat");
$opmod = FN_GetParam("opmod", $_GET, "flat");


if (!file_exists("fncommerce_sitemap.xml") && !is_writable("."))
{
	echo "<b>" . _ERROR_PERM . "</b><br /><br />" . _ERROR_PERM_FIX1;
	echo "<br /><pre>" . dirname($_SERVER['SCRIPT_FILENAME']) . "</pre>";
}
else
	if ( file_exists("fncommerce_sitemap.xml") && !is_writable("fncommerce_sitemap.xml"))
	{
		echo "fncommerce_sitemap.xml : " . _READONLY."<br />";
	}
	else
	{
		if ($opmod == "update")
		{
			fn_create_google_sitemap("fncommerce_sitemap.xml");
			 FN_Alert("fncommerce_sitemap.xml updated");
		}
		echo "<a href=\"?opt=$op&amp;opmod=update\">Update ecommerce sitemap.xml</a><br />";
	}
$imghelp="<img style=\"vertical-align:middle\" alt=\"\" src=\"".FN_FromTheme("images/help.png")."\"/>";
echo "<div style=\"text-align:right\">$imghelp&nbsp;<a href=\"https://www.google.com/webmasters/tools/docs/{$_FN['lang']}/about.html\">" . FN_i18n("help") . "</a></div>";
if (file_exists("fncommerce_sitemap.xml"))
	echo "<pre style=\"border:1px inset;height:300px;overflow:auto;\">" . htmlspecialchars(file_get_contents("fncommerce_sitemap.xml")) . "</pre>";

echo "<br /><b>URL sitemep:</b> ".$_FN['siteurl']."fncommerce_sitemap.xml<br/>";

/**
 * Crea la sitemap
 *
 * @param string $filename path del file da scrivere
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 *
 */
function fn_create_google_sitemap($filename)
{
	global $_FN;
	$modlist = fnc_getproducts(array("status"=>1));
	$str = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.google.com/schemas/sitemap/0.84\">";
	foreach ($modlist as $mod)
	{
		$str .= "\n\t<url><loc>{$_FN['siteurl']}" . fn_rewritelink("index.php?mod=fncommerce&amp;op=view&amp;id={$mod['unirecid']}")."</loc></url>";
		if (count($_FN['listlanguages']) > 1)
		{
			foreach ($_FN['listlanguages'] as $l)
			{
				if ($l != $_FN['lang_default'])
					$str .= "\n\t<url><loc>{$_FN['siteurl']}" .
					fn_rewritelink("index.php?mod=fncommerce&amp;op=view&amp;id={$mod['unirecid']}&amp;lang=$l")."</loc></url>";
			}
		}
	}
	$str .= "\n</urlset>";
	$handle = fopen($filename, "w");
	fwrite($handle, $str);
	fclose($handle);
}
?>