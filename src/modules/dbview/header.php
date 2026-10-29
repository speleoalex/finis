<?php

/**
 * @package Finis_module_navigator
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2011
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
#<fnmodule>navigator</fnmodule>

defined('_FNEXEC') or die('Restricted access');
$op=FN_GetParam("op",$_GET);
//--config-->
$config=FN_LoadConfig(__DIR__ . "/config.php");
//--config--<
//dprint_r($config);
$tables=explode(",",$config['tables']);
$_tablename=$tables[0];
if ($_tablename== "")
{
    $tablename=$tables[0];
}
else
{
    $tablename=$_tablename;
}

$tplvars="";
$title=$_FN['site_title'];
if ($op== "view")
{
    $id=FN_GetParam("id",$_GET);
    $Table=FN_XMDBForm($tablename);
    $row=$Table->xmltable->GetRecordByPrimaryKey($id);
    // a visitor who may not view the record gets no title, url or image of it
    if (is_array($row))
    {
        require_once __DIR__ . "/FNDBVIEW.php";
        $dbview=new FNDBVIEW($config);
        if (!FN_UserCanViewSection($_FN['mod']) || !$dbview->CanViewRecords($config) || !$dbview->CanViewRecord($id,$tablename,$config))
        {
            $row=false;
        }
    }
    if (is_array($row))
    {
        $titles=explode(",",$config['titlefield']);
        $t=array();

        foreach($titles as $tt)
        {
            $t[]=isset($row[$tt]) ? $row[$tt] : "";
        }
        $title=implode(" ",$t);
        $_FN['site_title'].=" - ".$title;
    }
    if (!is_array($row))
    {
        $row=array();
    }
    $vars=$row;
    $image="";
    if ($config['image_titlefield'] && !empty($row[$config['image_titlefield']]))
    {
        $image=$Table->xmltable->getFileUrl($row,$config['image_titlefield']);
    }
    $vars['image']=$image;
    $vars['url']=FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=view&amp;id=$id","",true);
    $vars['title']=$title;
    if (count($row) > 0)
    {
        $_FN['section_header_footer'].=FN_TPL_ApplyTplFile(FN_FromTheme(__DIR__ . "/header.tp.html",false),$vars);
    }
}



if (file_exists("themes/{$_FN['theme']}/modules/dbview/style.css"))
{
    $_FN['section_header_footer'].="\n<style>\n".file_get_contents("themes/{$_FN['theme']}/modules/dbview/style.css")."\n</style>\n";
}
?>