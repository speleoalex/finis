<?php

/**
 * Dashboard widget: the pages of the site, read only.
 *
 * @package Finis_controlcenter
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2011
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;

$sections=FN_GetSections("",true,false,true,false,true);
$sections=FN_ArraySortByKey($sections,"position");
$sections=FN_ArraySortByKey($sections,"parent");

$visited=array();
echo "<ul class=\"fncc-tree\">".FNCC_HtmlDashboardSitemapRows($sections,"",0,$visited)."</ul>";
echo FNCC_UI_Toolbar(array(
    FNCC_UI_Button(FN_Translate("edit the pages and the structure of the website"),array(
        "href"=>"index.php?fnapp=controlcenter&amp;opt=contents/sitemap",
        "variant"=>"primary",
    )),
    FNCC_UI_Button(FN_Translate("create a new page"),array(
        "href"=>"index.php?fnapp=controlcenter&amp;opt=contents/sitemap&amp;newsection=1",
    )),
));

/**
 * The rows of the tree, parents before children.
 *
 * @param array $sections
 * @param string $parent
 * @param int $level
 * @param array $visited ids already printed; guards against a loop in parent
 * @return string
 */
function FNCC_HtmlDashboardSitemapRows($sections,$parent,$level,&$visited)
{
    $html="";
    foreach($sections as $section)
    {
        if ($section['parent']!= $parent || in_array($section['id'],$visited))
        {
            continue;
        }
        $visited[]=$section['id'];
        $classes="fncc-tree__item";
        if (!empty($section['hidden']))
        {
            $classes.=" is-hidden";
        }
        if ($section['status']!= "1")
        {
            $classes.=" is-disabled";
        }
        $html.="<li class=\"$classes\" data-level=\"".(int) $level."\">";
        $html.="<span class=\"fncc-tree__title\"><span class=\"fncc-tree__name\">"
            .FNCC_UI_Escape(FNCC_UI_PlainText($section['title']))."</span>";
        if ($section['type']!= "")
        {
            $html.="<span class=\"fncc-tree__type\">".FNCC_UI_Escape($section['type'])."</span>";
        }
        $html.="</span></li>";
        $html.=FNCC_HtmlDashboardSitemapRows($sections,$section['id'],$level + 1,$visited);
    }
    return $html;
}
