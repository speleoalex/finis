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
$opt=FN_GetParam("opt",$_GET,"flat");
$edit=FN_GetParam("edit",$_GET,"flat");

//-------------------------steps list----------------------------------------->
$steps_modules="";
include ("modules/fncommerce/modules/config.php");
$liststeps=$steps_modules=explode(",",$steps_modules);
$liststeps_tmp=FN_ListDir("modules/fncommerce/modules/");
foreach ($liststeps_tmp as $step)
{
	if (!in_array($step,$liststeps))
	{
		$liststeps[]=$step;
	}
}
//-------------------------steps list-----------------------------------------<
if ($edit == "")
{
	echo "<div><a href=\"?fnapp=controlcenter&opt=$opt&amp;edit=modules/fncommerce/modules\">" . FN_Translate("choose modules") . "</a><br /><br /><br /></div>";
	foreach ($liststeps as $itemstep)
	{

		echo "<table border=\"0\">";
		echo "<tr><td>";
		echo "\n<a href=\"?fnapp=controlcenter&mod={$_FN['mod']}&amp;opt=$opt&amp;edit=modules/fncommerce/modules/\">";
		if (in_array($itemstep,$steps_modules))
		{
			echo FN_Translate("enabled");
		}
		else
		{
			echo FN_Translate("hidden");
		}
		echo "</a>";

		echo "</td>";
		$title=FN_GetFolderTitle("modules/fncommerce/modules/$itemstep");
		echo "\n<td colspan=\"4\"><b>{$title}:</b>";
		echo "\n<a href=\"?fnapp=controlcenter&mod={$_FN['mod']}&amp;opt=$opt&amp;edit=modules/fncommerce/modules/{$itemstep}\">[" . FN_Translate("modify") . "]</a></td>";
		echo "</tr>";

		//--------------------list modules------------------------------------->
		$listitems_tmp=FN_ListDir("modules/fncommerce/modules/$itemstep");
		$list_enabled_modules="";
		include ("modules/fncommerce/modules/$itemstep/config.php");
		$listitems=$list_enabled_modules=explode(",",$list_enabled_modules);
		foreach ($listitems_tmp as $itemdir)
		{
			if (!in_array($itemdir,$listitems))
			{
				$listitems[]=$itemdir;
			}
		}
		//--------------------list modules-------------------------------------<
		//$listitems=list_sections_translated("modules/fncommerce/modules/" . basename($itemstep['link']));
		foreach ($listitems as $item)
		{
			echo "<tr>";
			echo "<td>";
			echo "\n<a href=\"?fnapp=controlcenter&mod={$_FN['mod']}&amp;opt=$opt&amp;edit=modules/fncommerce/modules/{$itemstep}\">";
			if (in_array($item,$list_enabled_modules))
			{
				echo FN_Translate("enabled");
			}
			else
			{
				echo FN_Translate("hidden");
			}
			echo "</a>";
			echo "</td>";
			echo "<td></td>";
			echo "<td><div style=\"background-position: bottom right;background-image:url(controlcenter/sections/contents/sitemap/node.png);width:20px;height:20px;\"</td>";

			$title=FN_GetFolderTitle("modules/fncommerce/modules/$itemstep/$item");
			echo "<td>{$title}</td>";
			echo "<td>";
			echo "<a href=\"?fnapp=controlcenter&opt=$opt&amp;edit=modules/fncommerce/modules/{$itemstep}/{$item}\">";
			echo "[" . FN_Translate("configure","aa") . "]</a>";
			echo "</td>";
			echo "</tr>";
		}
		echo "</table><br /><br />";
	}
}
else
{

	echo "<br />$edit";
	fn_editconffile("$edit/config.php","?fnapp=controlcenter&opt=$opt&amp;edit=$edit","?fnapp=controlcenter&opt=$opt");
}
?>
