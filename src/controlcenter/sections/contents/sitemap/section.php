<?php

/**
 * @package Finis_controlcenter
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');


//echo FN_HtmlSectionEditor();
global $_FN;
require_once ("{$_FN['src_finis']}/include/admin.php");

global $currentSectionType;
$currentSectionType=null;
$opt=FN_GetParam("opt",$_GET);
$modcont=FN_GetParam("edit",$_GET);
FN_InitTables();

$is_in_details=false;
if (!empty($_GET['pk___xdb_fn_sections']))
    $is_in_details=true;

echo "<br />";
FN_FixSections();

if ($modcont)
{
    $filename=basename($modcont);
    $filedir=dirname($modcont);
    $lang=str_replace(".html","",str_replace("section.","",$filename));

    $editor_params=false;
    if (file_exists(dirname($modcont)."/style.css"))
    {
        $editor_params['css_file']=dirname($modcont)."/style.css";
    }

    if (!file_exists($modcont))
    {
        if (isset($_GET['create']))
        {
            //---from default language ---------------------------------------->
            if (!file_exists($modcont))
            {
                if (isset($_GET['copy']))
                {
                    $text=file_get_contents("$filedir/section.{$_FN['lang_default']}.html");
                    FN_Write($text,$modcont);
                }
            }
            //---from default language ----------------------------------------<
            FN_EditContent($modcont,"{$_FN['controlcenter']}opt=$opt&edit=$modcont","{$_FN['controlcenter']}opt=$opt",$editor_params);
        }
        else
        {
            $base="{$_FN['controlcenter']}opt=$opt";
            $buttons=array(
                FNCC_UI_Button(FN_Translate("yes"),array(
                    "href"=>"$base&edit=$modcont&create",
                    "variant"=>"primary",
                )),
                FNCC_UI_Button(FN_Translate("no"),array("href"=>$base)),
            );
            if ($lang!= $_FN['lang_default'] && file_exists("$filedir/section.{$_FN['lang_default']}.html"))
            {
                $buttons[]=FNCC_UI_Button(FN_Translate("copy contents from the default translation and edit it"),array(
                    "href"=>"$base&edit=$modcont&create&copy",
                ));
            }
            echo FNCC_UI_Alert(FNCC_UI_Escape($modcont)." ".FNCC_UI_Escape(FN_i18n("does not exist"))
                ."<br />".FNCC_UI_Escape(FN_i18n("you want to create the file?")),"warning",true);
            echo FNCC_UI_Toolbar($buttons);
        }
    }
    else
    {
        $file_restore=FN_GetParam("restore",$_GET);
        if (!empty($file_restore) && file_exists($file_restore) && FN_GetFileExtension($file_restore)== "bak~")
        {
            $editor_params['force_value']=file_get_contents($file_restore);
            $editor_params['text_save']=FN_Translate("restore");
        }
        $_FN['editor_folder']=dirname($modcont);
        FN_EditContent($modcont,"{$_FN['controlcenter']}opt=$opt&edit=$modcont","{$_FN['controlcenter']}opt=$opt",$editor_params);
        //-----old versions---------------------------------------------------->
        {
            $rows=array();
            $files=glob("$modcont.*");
            usort($files,"FN_UsortFilemtime");

            $bk_user="";
            foreach($files as $file)
            {
                $attr=explode(".",basename($file));
                $date=DateTime::createFromFormat('YmdHis',$attr[count($attr) - 3]);
                $dateFile=$attr[count($attr) - 4];
                if (is_numeric($dateFile))
                {
                    $dateFile=FN_FormatDate($dateFile);
                }
                else
                {
                    $dateFile="unknown";
                }
                if (is_object($date))
                {
                    $bk_date=$date->getTimestamp();
                    $bk_date=FN_FormatDate($bk_date);
                }
                else
                {
                    $bk_date="";
                }
                $row=array(
                    FNCC_UI_Escape($dateFile),
                    FNCC_UI_Escape($bk_user),
                    FNCC_UI_Escape($bk_date),
                );
                $bk_user=$attr[count($attr) - 2];
                $row[]=FNCC_UI_Escape($bk_user);
                $row[]=FNCC_UI_Button(FN_Translate("restore"),array(
                    "href"=>"{$_FN['controlcenter']}mod={$_FN['mod']}&opt=$opt&edit=$modcont&restore=$file",
                    "variant"=>"sm",
                    "confirm"=>FN_Translate("restore")."?",
                ));
                $rows[]=$row;
            }

            $rows[]=array(
                FNCC_UI_Escape(FN_FormatDate(filemtime($modcont))),
                FNCC_UI_Escape($bk_user),
                "-",
                "-",
                "<span class=\"fncc-badge fncc-badge--success\">".FNCC_UI_Escape(FN_Translate("current version"))."</span>",
            );
            $headers=array(
                FN_Translate("creation date"),
                FN_Translate("created by"),
                FN_Translate("delete date"),
                FN_Translate("overwritten by"),
                "",
            );
            echo FNCC_UI_Card(FN_Translate("old versions"),FNCC_UI_Table($headers,$rows));
        }
        //-----old versions----------------------------------------------------<
    }
}
else
{

    $newsection=FN_GetParam("newsection",$_GET);

//-------sitemap--------------------------------------------------------------->
    if (!$newsection)
    {
        FNCC_UpdateSections();
        $table=FNCC_XMDBForm("fn_sections");
//($section="", $recursive=false, $onlyreadable = true, $hidden = false, $onlyenabled=true)
        $sections=FN_GetSections("",true,false,true,false,true);
        $sections=FN_ArraySortByKey($sections,"position");
        $sections=FN_ArraySortByKey($sections,"parent");
        $htmlgrid=FNCC_HtmlSitemapEditor($sections,$opt,empty($_GET["pk___xdb_fn_sections"]));
    }
//-------sitemap---------------------------------------------------------------<
//-------section grid---------------------------------------------------------->
    if (!$newsection)
    {
        //dprint_r("qui 1:" . FN_GetExecuteTimer());
        $table=FNCC_XMDBForm("fn_sections");
        $table->formvals['id']['frm_show']=1;
        $table->formvals['position']['frm_show']=0;

        //------------rename section id---------------------------------------->
        $pk___xdb_fn_sections=FN_GetParam("pk___xdb_fn_sections",$_GET,"html");
        $id=FN_GetParam("id",$_POST,"html");
        $error=false;
        if ($id!= "" && $pk___xdb_fn_sections!= "" && $id!= $pk___xdb_fn_sections)
        {
            if (file_exists($_FN['src_application'] ."/sections/$id"))
            {
                echo "<div>";
                echo FN_Translate("a file already exists with this name");
                echo "</div>";
                $error=true;
            }
            else
            {
                if (false=== FN_Rename($_FN['src_application'] ."/sections/$pk___xdb_fn_sections",$_FN['src_application'] ."/sections/$id"))
                {
                    echo FN_Translate("error");
                    $error=true;
                }
                else
                {
                    //rename parents ----->
                    $sectionstorenameparent=FN_XMETADBQuery("SELECT * FROM fn_sections WHERE parent LIKE '$pk___xdb_fn_sections'");
                    if (is_array($sectionstorenameparent))
                    {
                        $tb=FN_XMDBTable("fn_sections");
                        foreach($sectionstorenameparent as $sectiontorenameparent)
                        {
                            $sectiontorenameparent['parent']=$id;
                            $nv=$table->UpdateRecord($sectiontorenameparent);
                            FN_UpdateDefaultXML($nv);
                        }
                    }
                    //rename parents -----<
                }
            }
        }
        //------------rename section id----------------------------------------<

        if (!$error)
        {
            //-------table editor---------------------------------------------->
            $params=array();
            $params['enablenew']=false;
            $params['table']=$table;
            $params['htmlgrid']=$htmlgrid."";

            $params['list_onupdate']=false;
            $params['textviewlist']="";
            $params['textcancel']="";
            $params['function_on_update']="FNCC_OnUpdateSection";
            $mod=FN_GetParam("pk___xdb_fn_sections",$_GET,"html");
            if (!empty($mod))
            {
                $sectionvalues=FN_GetSectionValues($mod);
                echo "<h2>".FNCC_UI_Escape(FN_Translate("page"))."</h2>";
            }

            FNCC_XMETATableEditor("fn_sections",$params); //editor
            $sectionvalues=FN_GetSectionValues($mod,false);
            //-------table editor----------------------------------------------<
            //-------config editor--------------------------------------------->
            $block="";
            if (!empty($mod))
            {
                $sectiontype=$sectionvalues['type'];
                if ($currentSectionType!== null)
                    $sectiontype=$currentSectionType;
                if ($sectiontype!= "" && file_exists($_FN['src_finis'] ."/modules/$sectiontype/config.php") && file_exists($_FN['src_application']."/sections/{$mod}"))
                {
                    $t=FNCC_XMDBForm("fn_sectionstypes");
                    $values=$t->GetRecordTranslatedByPrimarykey("$sectiontype");
                    $title=isset($values['title']) ? $values['title'] : $sectiontype;
                    echo "<h2>".FNCC_UI_Escape($title)."</h2>";
                    $formaction=FNCC_UI_Escape("{$_FN['controlcenter']}opt=$opt&op___xdb_fn_sections=insnew&pk___xdb_fn_sections=$mod");
                    //($file,$formaction = "",$exit = "",$allow = false,$write_to_file = false,$mod = "",$block = "")
                    echo FNCC_HtmlEditConfFile($_FN['src_finis']."/modules/$sectiontype/config.php",$formaction,"",false,false,$mod,$block);
                }

                echo FNCC_UI_Toolbar(array(FNCC_UI_Button(FN_Translate("site map"),array(
                    "href"=>"{$_FN['controlcenter']}opt=$opt",
                    "icon"=>"\xE2\x86\x90",
                ))));
            }
            //-------config editor---------------------------------------------<
        }
        else
        {
            echo FNCC_UI_Toolbar(array(FNCC_UI_Button(FN_Translate("site map"),array(
                "href"=>"{$_FN['controlcenter']}opt=$opt",
                "icon"=>"\xE2\x86\x90",
            ))));
        }

        //------del section----------------------------------------------------->
        if (!empty($_GET['op___xdb_fn_sections']) && $_GET['op___xdb_fn_sections']== "del")
        {
            $sid=FN_GetParam("pk___xdb_fn_sections",$_GET);
            if ($sid!= "" && file_exists($_FN['src_application']."/sections/$sid"))
            {
                FN_RemoveDir($_FN['src_application']."/sections/$sid");
            }
            FN_JsRedirect("{$_FN['controlcenter']}mod={$_FN['mod']}&opt=$opt");
            FN_Log("section deleted:$sid");
            FN_OnSitemapChange();
        }
        //------del section-----------------------------------------------------<
    }
    else
    {
        if (!FN_IsWritable("sections"))
        {
            echo FNCC_UI_Alert("sections ".FN_i18n("is read-only"),"danger");
        }
        else
        {
            $forminsert=FNCC_XMDBForm("fn_sections");
            $newvalues = isset($_POST['newsection']) ? $forminsert->GetByPost() : array();
            $newvalues =  (!is_array($newvalues)) ? array():$newvalues;
            $errors=array();
            $forminsert->formvals['id']['frm_show']="0";
            $forminsert->formvals['title']['frm_required']="1";
            $forminsert->formvals['parent']['frm_show']="0";
            $forminsert->formvals['position']['frm_show']="0";
            $sections=FN_GetSections("sections",true);
            if (!isset($newvalues['status']))
            {
                
                $newvalues['status']="1";

            }
            if (isset($_POST['newsection']))
            {
                if (isset($newvalues['title']))
                    $newvalues['id']=FN_MakeSectionId($newvalues['title']);
                $before_after=FN_GetParam("before_after",$_POST);
                $before_after_section=FN_GetParam("before_after_section",$_POST);
                $newvalues['parent']=isset($sections[$before_after_section]['parent']) ? $sections[$before_after_section]['parent'] : "";
                $newvalues['sectionpath']="sections";

                $errors=$forminsert->VerifyInsert($newvalues);
                if (count($errors)== 0)
                {
                    if ($newvalues['type']!= "" && file_exists("{$_FN['src_finis']}/modules/{$newvalues['type']}/section_template"))
                    {
                        $r=FN_CopyDir($_FN['src_finis']."/modules/{$newvalues['type']}/section_template",$_FN['src_application']."{$_FN['src_application']}/sections/{$newvalues['id']}",false);
                    }
                    else
                    {
                        $r=FN_MkDir($_FN['src_application']."/sections/{$newvalues['id']}");
                    }
                    if ($r)
                    {
                        //fix position --------->
                        $i=1;
                        $newsections=array();
                        foreach($sections as $k=> $section)
                        {
                            $newsections[$k]=$section;
                            $newsections[$k]['position']=$i;
                            if ($k== $before_after_section)
                            {
                                if ($before_after== "before")
                                {
                                    $newvalues['position']=$i;
                                    $i++;
                                    $newsections[$k]['position']=$i;
                                }
                                else
                                {
                                    $i++;
                                    $newvalues['position']=$i;
                                }
                            }
                            $i++;
                        }
                        if ($before_after== "inside")
                            $newvalues['parent']=$before_after_section;
                        foreach($newsections as $k=> $newsection)
                        {
                            if ($newsections[$k]['position']!= $sections[$k]['position'])
                            {
                                //dprint_r("update {$newsections[$k]['position']} != {$sections[$k]['position']}");
                                $nv=$forminsert->UpdateRecord(array("id"=>$newsections[$k]['id'],"position"=>$newsections[$k]['position']));
                                FN_UpdateDefaultXML($nv);
                            }
                        }
                        //fix position ---------<
                        $nv=$forminsert->InsertRecord($newvalues);
                        FN_UpdateDefaultXML($nv);
                        echo FNCC_UI_Alert(FN_i18n("the page has been created"),"success");
                        $buttons=array(FNCC_UI_Button(FN_Translate("site map"),array(
                            "href"=>"{$_FN['controlcenter']}mod={$_FN['mod']}&opt=$opt",
                            "variant"=>"primary",
                        )));
                        if ($newvalues['type']!= "" && file_exists("{$_FN['src_finis']}/modules/{$newvalues['type']}/config.php"))
                        {
                            $buttons[]=FNCC_UI_Button(FN_Translate("module options that is loaded in this page"),array(
                                "href"=>"{$_FN['controlcenter']}mod={$_FN['mod']}&opt=$opt&op___xdb_fn_sections=insnew&pk___xdb_fn_sections="
                                    .rawurlencode($newvalues['id']),
                            ));
                        }
                        echo FNCC_UI_Toolbar($buttons);
                        FN_Log("created new section:{$newvalues['id']}");
                        FN_OnSitemapChange();
                        return;
                    }
                    else
                    {
                        echo FNCC_UI_Alert(FN_i18n("error"),"danger");
                    }
                }
            }
            $body="<input type=\"hidden\" name=\"newsection\" value=\"1\"/>";
            $body.=FNADMIN_HtmlSectionsTree();
            $body.=$forminsert->HtmlShowInsertForm(false,$newvalues,$errors);
            $body.=FNCC_UI_Toolbar(array(
                FNCC_UI_Button(FN_i18n("save"),array("type"=>"submit","variant"=>"primary")),
                FNCC_UI_Button(FN_i18n("cancel"),array(
                    "href"=>"{$_FN['controlcenter']}mod={$_FN['mod']}&opt=$opt",
                )),
            ));
            $action=FNCC_UI_Escape("{$_FN['controlcenter']}mod={$_FN['mod']}&opt=$opt&newsection=1");
            $html="<form method=\"post\" action=\"$action\">$body</form>";
            echo FNCC_UI_Card(FN_i18n("create a new page"),$html);
        }
    }
//-------section grid----------------------------------------------------------<
}

/**
 * The sitemap editor: the tree of the pages, the controls to reorder it and
 * the form that saves it.
 *
 * The order and the nesting are sent back in one hidden field, "sectionstring"
 * ("id:level:hidden,..." in the order of the rows), which FNCC_UpdateSections()
 * turns into parent and position. The markup carries no style and no inline
 * handler: everything is driven by the .fncc-* classes and the data-fncc-*
 * attributes read by sitemap.js.
 *
 * @global array $_FN
 * @param array $sections sections sorted by parent and position
 * @param string $opt path of this control center section
 * @param bool $showNewLink false while a single page is being edited
 * @return string
 */
function FNCC_HtmlSitemapEditor($sections,$opt,$showNewLink=true)
{
    global $_FN;
    $html="";
    if ($showNewLink)
    {
        $html.=FNCC_UI_Toolbar(array(
            FNCC_UI_Button(FN_i18n("create a new page"),array(
                "href"=>"{$_FN['controlcenter']}mod={$_FN['mod']}&opt=$opt&newsection=1",
                "variant"=>"primary",
                "icon"=>"\xE2\x9E\x95",
            )),
        ));
    }

    $visited=array();
    $rows=FNCC_HtmlSitemapRows($sections,"",0,$opt,$visited);
    if ($rows== "")
    {
        return $html.FNCC_UI_Alert(FN_Translate("no pages"),"info");
    }

    $html.="<form method=\"post\" action=\"".FNCC_UI_Escape("{$_FN['controlcenter']}mod={$_FN['mod']}&opt=$opt")."\" data-fncc-dirty-guard>";
    $html.="<ul class=\"fncc-tree\" id=\"fncc-sitemap\">$rows</ul>";
    $html.="<ul class=\"fncc-legend\">";
    $html.="<li>&#x1F512; ".FNCC_UI_Escape(FN_Translate("level"))."</li>";
    $html.="<li>&#x1F511; ".FNCC_UI_Escape(FN_Translate("groups for viewing"))."</li>";
    $html.="<li>&#x1F510; ".FNCC_UI_Escape(FN_Translate("permissions inside the page type"))."</li>";
    $html.="<li>&#x1F4A1; ".FNCC_UI_Escape(FN_Translate("hidden"))."</li>";
    $html.="</ul>";
    $html.="<input type=\"hidden\" id=\"sectionstring\" name=\"sectionstring\" value=\"\" />";
    $html.="<div class=\"fncc-savebar\" id=\"fncc-sitemap-savebar\">";
    $html.=FNCC_UI_Button(FN_i18n("save"),array("type"=>"submit","variant"=>"primary"));
    $html.=FNCC_UI_Button(FN_Translate("undo changes"),array(
        "attributes"=>array("id"=>"fncc-sitemap-reset","hidden"=>"hidden"),
    ));
    $html.="<span class=\"fncc-savebar__status\" id=\"fncc-sitemap-status\" role=\"status\""
        ." data-text-clean=\"".FNCC_UI_Escape(FN_Translate("all changes saved"))."\""
        ." data-text-dirty=\"".FNCC_UI_Escape(FN_Translate("unsaved changes"))."\">"
        .FNCC_UI_Escape(FN_Translate("all changes saved"))."</span>";
    $html.="</div>";
    $html.="</form>";
    $html.="<script src=\"".FNCC_UI_Escape(FNCC_UI_PublishAsset("controlcenter/sections/contents/sitemap/sitemap.js"))."\" defer=\"defer\"></script>";
    return $html;
}

/**
 * Walk the tree and return the rows, parents before children.
 *
 * @param array $sections
 * @param string $parent
 * @param int $level
 * @param string $opt
 * @param array $visited ids already printed; guards against a loop in parent
 * @return string
 */
function FNCC_HtmlSitemapRows($sections,$parent,$level,$opt,&$visited)
{
    $html="";
    foreach($sections as $section)
    {
        if ($section['parent']!= $parent)
        {
            continue;
        }
        if (in_array($section['id'],$visited))
        {
            continue;
        }
        $visited[]=$section['id'];
        $html.=FNCC_HtmlSitemapRow($section,$level,$opt);
        $html.=FNCC_HtmlSitemapRows($sections,$section['id'],$level + 1,$opt,$visited);
    }
    return $html;
}

/**
 * One page of the sitemap.
 *
 * @global array $_FN
 * @param array $section
 * @param int $level
 * @param string $opt
 * @return string
 */
function FNCC_HtmlSitemapRow($section,$level,$opt)
{
    global $_FN;
    $id=$section['id'];
    $idurl=rawurlencode($id);
    $base="{$_FN['controlcenter']}opt=$opt";
    $linkdelete="$base&page___xdb_fn_sections=1&order___xdb_fn_sections=id&op___xdb_fn_sections=del&pk___xdb_fn_sections=$idurl";
    $linksettings="$base&page___xdb_fn_sections=1&order___xdb_fn_sections=id&op___xdb_fn_sections=insnew&pk___xdb_fn_sections=$idurl";

    $isHidden=!empty($section['hidden']);
    $isDisabled=($section['status']!= "1");
    $classes="fncc-tree__item";
    if ($isHidden)
    {
        $classes.=" is-hidden";
    }
    if ($isDisabled)
    {
        $classes.=" is-disabled";
    }

    $html="<li class=\"$classes\" data-id=\"".FNCC_UI_Escape($id)."\" data-level=\"".(int) $level."\""
        ." data-hidden=\"".($isHidden ? "1" : "0")."\">";

    //-----the drag handle, which is also the keyboard entry point----------->
    $moveLabel=FN_Translate("move")." ".FNCC_UI_PlainText($section['title']);
    $html.="<button type=\"button\" class=\"fncc-tree__handle\" title=\"".FNCC_UI_Escape($moveLabel)."\">"
        ."<span aria-hidden=\"true\">&#x2630;</span>"
        ."<span class=\"fncc-visually-hidden\">".FNCC_UI_Escape($moveLabel)."</span></button>";

    //-----title and preview------------------------------------------------->
    $html.="<span class=\"fncc-tree__title\">";
    $html.="<a class=\"fncc-tree__name\" target=\"_blank\" rel=\"noopener\" title=\""
        .FNCC_UI_Escape(FN_Translate("preview"))."\" href=\""
        .FNCC_UI_Escape(FN_RewriteLink("index.php?mod=$id"))."\">".FNCC_UI_Escape(FNCC_UI_PlainText($section['title']))."</a>";
    if ($section['type']!= "")
    {
        $html.="<span class=\"fncc-tree__type\">".FNCC_UI_Escape($section['type'])."</span>";
    }
    if ($isDisabled)
    {
        $html.="<span class=\"fncc-badge fncc-badge--danger\">".FNCC_UI_Escape(FN_Translate("disabled"))."</span>";
    }
    $html.="</span>";

    //-----what restricts the page------------------------------------------->
    $html.="<span class=\"fncc-tree__meta\">";
    if ($section['level']!== "")
    {
        $html.="<span class=\"fncc-badge\" title=\"".FNCC_UI_Escape(FN_Translate("level"))."\">&#x1F512; "
            .FNCC_UI_Escape($section['level'])."</span>";
    }
    if ($section['group_view']!== "")
    {
        $html.="<span class=\"fncc-badge\" title=\"".FNCC_UI_Escape(FN_Translate("groups for viewing"))."\">&#x1F511; "
            .FNCC_UI_Escape($section['group_view'])."</span>";
    }
    if ($section['group_edit']!== "")
    {
        $html.="<span class=\"fncc-badge\" title=\"".FNCC_UI_Escape(FN_Translate("groups for editing"))."\">&#x270E; "
            .FNCC_UI_Escape($section['group_edit'])."</span>";
    }
    // permissions inside the page type (module settings, e.g. the groups of a dbview section)
    $function=FN_GetModuleMenuFunction($section['type'],"SectionViewRestriction");
    $restriction=($function!= "") ? $function($section) : "";
    if ($restriction!= "")
    {
        $html.="<span class=\"fncc-badge\" title=\"".FNCC_UI_Escape(FN_Translate("permissions inside the page type"))."\">&#x1F510; "
            .FNCC_UI_Escape(FNCC_UI_PlainText($restriction))."</span>";
    }
    foreach(array("startdate"=>"publication start date","enddate"=>"publication end date") as $field=> $label)
    {
        if (!empty($section[$field]))
        {
            $html.="<span class=\"fncc-badge\" title=\"".FNCC_UI_Escape(FN_Translate($label))."\">&#x1F4C5; "
                .FNCC_UI_Escape(FN_FormatDate($section[$field]))."</span>";
        }
    }
    $html.="</span>";

    //-----the files of the page--------------------------------------------->
    // Filled when the file exists, outlined when it does not. Their own
    // element: on a phone they sit next to the name, the rest goes below.
    $files=array("PHP"=>"sections/$id/section.php");
    foreach($_FN['listlanguages'] as $language)
    {
        $files[strtoupper($language)]="sections/$id/section.$language.html";
    }
    $html.="<span class=\"fncc-tree__files\">";
    foreach($files as $label=> $file)
    {
        $exists=file_exists("{$_FN['src_application']}/$file");
        $title=($exists ? FN_Translate("edit") : FN_Translate("create")) ." - $file";
        $html.="<a class=\"fncc-filelink".($exists ? " is-present" : "")."\" title=\"".FNCC_UI_Escape($title)."\""
            ." href=\"".FNCC_UI_Escape("$base&mod=$id&edit=$file")."\">"
            .FNCC_UI_Escape($label)."</a>";
    }
    $html.="</span>";

    //-----actions----------------------------------------------------------->
    $html.="<span class=\"fncc-tree__actions\">";
    $hideLabel=FN_Translate("hide the page in the menu");
    $showLabel=FN_Translate("show the page in the menu");
    $html.="<button type=\"button\" class=\"fncc-btn fncc-btn--quiet fncc-btn--icon\" data-fncc-hidden-toggle"
        ." aria-pressed=\"".($isHidden ? "true" : "false")."\""
        ." data-text-hide=\"".FNCC_UI_Escape($hideLabel)."\" data-text-show=\"".FNCC_UI_Escape($showLabel)."\""
        ." title=\"".FNCC_UI_Escape($isHidden ? $showLabel : $hideLabel)."\">"
        ."<span aria-hidden=\"true\">&#x1F4A1;</span>"
        ."<span class=\"fncc-visually-hidden\">".FNCC_UI_Escape($hideLabel)."</span></button>";

    $html.=FNCC_UI_Button(FN_Translate("settings"),array(
        "href"=>$linksettings,
        "variant"=>"quiet icon",
        "icon"=>"\xE2\x9A\x99\xEF\xB8\x8E",
        "title"=>FN_Translate("settings"),
    ));
    $html.=FNCC_UI_Button(FN_Translate("delete"),array(
        "href"=>$linkdelete,
        "variant"=>"quiet icon danger",
        "icon"=>"\xE2\x9C\x95\xEF\xB8\x8E",
        "title"=>FN_Translate("delete"),
        "confirm"=>FN_i18n("you want to permanently delete this page?")." ".FNCC_UI_PlainText($section['title']),
    ));

    //-----move the page----------------------------------------------------->
    $moves=array(
        "up"=>array("\xE2\x96\xB2",FN_Translate("move up")),
        "down"=>array("\xE2\x96\xBC",FN_Translate("move down")),
        "left"=>array("\xE2\x97\x84",FN_Translate("move left")),
        "right"=>array("\xE2\x96\xBA",FN_Translate("move right")),
    );
    $html.="<span class=\"fncc-tree__moves\">";
    foreach($moves as $action=> $move)
    {
        $html.=FNCC_UI_Button($move[1],array(
            "variant"=>"quiet icon",
            "icon"=>$move[0],
            "title"=>$move[1],
            "attributes"=>array("data-fncc-move"=>$action),
        ));
    }
    $html.="</span>";

    $html.="</span>";
    return $html."</li>";
}

/**
 *
 * @global array $_FN
 */
function FNCC_UpdateSections()
{
    global $_FN;
    $table=FNCC_XMDBForm("fn_sections");
    $sectionstring=FN_GetParam("sectionstring",$_POST);
    $i=1;
    $sects=array();
    if ($sectionstring!= "")
    {
        $str_sectionsvalues=explode(",",$sectionstring);
        {
            foreach($str_sectionsvalues as $str_section)
            {
                $tmp=explode(":",$str_section);
                if (isset($tmp[2]))
                {
                    $sec['id']=$tmp[0];
                    $sec['position']=$i;
                    $sec['leveltree']=$tmp[1];
                    $sec['hidden']=$tmp[2];
                    $sects[]=$sec;
                    $i++;
                }
            }
        }
        $errors=false;
        $idtree=0;
        $curparent="";
        $tree[$idtree]="";
        $newvalues=array();
        for($i=0; isset($sects[$i]); $i++)
        {
            $oldvalues=FN_GetSectionValues($sects[$i]['id']);
            $newvalues['id']=$sects[$i]['id'];
            $newvalues['hidden']=$sects[$i]['hidden'];
            $newvalues['position']=$sects[$i]['position'] * 10;

            if ($i== 0)
            {
                $newvalues['parent']="";
            }
            else
            {
                if ($sects[$i]['leveltree'] > $sects[$i - 1]['leveltree'])
                {
                    $curparent=$newvalues['parent']=$sects[$i - 1]['id'];
                    $idtree=$idtree + ($sects[$i]['leveltree'] - $sects[$i - 1]['leveltree'] );
                    $tree[$idtree]=$curparent;
                }
                elseif ($sects[$i]['leveltree'] < $sects[$i - 1]['leveltree'])
                {
                    $idtree=$idtree - ($sects[$i - 1]['leveltree'] - $sects[$i]['leveltree']);
                    if (isset($tree[$idtree]))
                        $curparent=$newvalues['parent']=$tree[$idtree];
                }
                else
                {
                    $newvalues['parent']=$curparent;
                }
            }
            if ($newvalues['hidden']!= $oldvalues['hidden'] || $newvalues['position']!= $oldvalues['position'] || $newvalues['parent']!= $oldvalues['parent'])
            {
                //dprint_r($newvalues);
                if (!($nv=$table->xmltable->UpdateRecord($newvalues)))
                {
                    $errors.=1;
                }
                else
                {
                    FN_UpdateDefaultXML($nv);
                }
            }
        }


        if (!$errors)
        {
            // In the page instead of a javascript alert: the confirmation
            // stays readable while the new order is checked.
            echo FNCC_UI_Alert(FN_Translate("the data were successfully updated"),"success");
            FN_Log("sitemap changed");
            FN_OnSitemapChange();
        }
        else
        {
            echo FNCC_UI_Alert(FN_Translate("error"),"danger");
        }
    }
}


/**
 *
 * @global type $currentSectionType
 * @param type $newvalues
 * @param type $oldvalues
 */
function FNCC_OnUpdateSection($newvalues,$oldvalues)
{
    global $currentSectionType;

    // Aggiorna sempre il file default.xml.php quando cambia qualcosa
    FN_UpdateDefaultXML($newvalues);

    if (isset($oldvalues["type"]) && isset($newvalues["type"]))
    {
        $currentSectionType=$newvalues["type"];
        FN_OnSitemapChange();
    }
}
