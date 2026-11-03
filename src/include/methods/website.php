<?php
global $_FN;
$str = "";
FN_InitSections();
ob_start();
//if ($_FN['enable_mod_rewrite'] > 0)
{
    header("Cache-Control: no-cache");
    header("Pragma: no-cache");
}
//accesskey  ----->
FN_GetSections("", true);
//accesskey  -----<
//--------------------------  auto scripts  ----------------------------------->
include ("{$_FN['src_finis']}/include/autoexec.php");
//--------------------------  auto scripts  -----------------------------------<
// Offline copies (service worker of an application installed from the site): a page is
// "public" when it is the same for every anonymous visitor, so a browser may keep it and
// show it again without network; a page seen by a logged user or answering a POST is
// "private" and must not be kept. Section and language tell the worker which theme shell
// (fnshell=1) goes around a fragment (fnajax=1) of the page.
$fn_publicpage = ($_FN['user'] == "" && ($_SERVER['REQUEST_METHOD'] ?? "GET") === "GET");
header("X-Finis-Visitor: " . ($fn_publicpage ? "public" : "private"));
header("X-Finis-Section: " . $_FN['mod']);
header("X-Finis-Lang: " . $_FN['lang']);
// fnshell=1: the page of the section with the theme, the marker <!--fnsection--> in place of
// the content of the section (FN_TPL_tp_create_section), which is not generated
$_FN['fnshell'] = empty($_GET['fnajax']) && !empty($_GET['fnshell']);
if (!empty($_GET['fnajax']))
{
    // Fragment for the ajax navigation (dbview call_ajax adds fnajax=1 to the url it loads):
    // only the output of the section, without the theme. The client replaces its containers
    // with the ones of the same id found here, so the page is much smaller than the full one.
    // The head of the page is built first, as for the full page: its hooks run before the
    // section (dbview header.php adds the record to the title of a sheet), and the title is
    // the one of the full page, for a worker that puts the fragment back in its theme.
    FN_HtmlHeader();
    $fn_htmlsection = FN_HtmlSection();
    $str = "<!DOCTYPE html>\n<html><head><meta charset=\"" . ($_FN['charset_page'] ?? 'UTF-8') . "\"><title>" . htmlspecialchars($_FN['site_title'], ENT_QUOTES, $_FN['charset_page'] ?? 'UTF-8', false) . "</title></head><body>"
        . $fn_htmlsection
        . "</body></html>";
}
elseif (file_exists("{$_FN['src_application']}/themes/{$_FN['theme']}/structure.php"))
{
    
    include "{$_FN['src_application']}/themes/{$_FN['theme']}/structure.php";
    $str = ob_get_clean();
}

elseif (file_exists("{$_FN['src_application']}/themes/{$_FN['theme']}/template.{$_FN['mod']}.tp.html"))
{
    $str = FN_TPL_html_MakeThemeFromTemplate("{$_FN['src_application']}/themes/{$_FN['theme']}/template.{$_FN['mod']}.tp.html");
}
elseif (!empty($_FN['sectionvalues']['type']) && file_exists("{$_FN['src_application']}/themes/{$_FN['theme']}/template.type.{$_FN['sectionvalues']['type']}.tp.html"))
{
    $str = FN_TPL_html_MakeThemeFromTemplate("{$_FN['src_application']}/themes/{$_FN['theme']}/template.type.{$_FN['sectionvalues']['type']}.tp.html");
}
elseif (file_exists(FN_FinisPathToApplicationPath("{$_FN['src_application']}/themes/{$_FN['theme']}/template.tp.html")))
{
    $str = FN_TPL_html_MakeThemeFromTemplate("{$_FN['src_application']}/themes/{$_FN['theme']}/template.tp.html");

}



if (file_exists(FN_FinisPathToApplicationPath("{$_FN['src_application']}/sections/{$_FN['mod']}/footer.php")))
{
    ob_start();
    include ("{$_FN['src_application']}/sections/{$_FN['mod']}/footer.php");
    $strfooter = ob_get_clean();
    $str = str_replace("</body>", $strfooter . "</body>", $str);
}
$str .= "<!-- Page generated in " . FN_GetExecuteTimer() . " seconds. -->";

$mem = memory_get_usage(true);
$peak = memory_get_peak_usage(true);

$str .= "<!-- Page memory: " . round($mem / 1024 / 1024, 2) . " MB | Peak: " . round($peak / 1024 / 1024, 2) . " MB -->";
 
if ($tmp = @ob_get_clean())
{
    if (($_FN['display_errors'] ?? 'off') !== "on")
    {
        $tmp = "";
    }
    header("Content-Type: text/html; charset=" . ($_FN['charset_page'] ?? 'UTF-8'));
    if (!empty($_FN['enable_compress_gzip']))
    {
        header("Content-Encoding: gzip");
        print gzencode($tmp . $str);
    }
    else
    {
        print ($tmp . $str);
    }
}
else
{
    print ($str);
}