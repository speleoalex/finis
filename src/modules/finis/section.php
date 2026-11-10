<?php
defined('_FNEXEC') or die('Restricted access');
global $_FN;
$folder = "{$_FN['src_application']}/sections/{$_FN['mod']}";
$SECTION = $_FN;
if (file_exists("$folder/section.php")) {
    include("$folder/section.php");
}
// section.php runs in this scope: its own $html and $folder must not leak into the output below
$folder = "{$_FN['src_application']}/sections/{$_FN['mod']}";
$html = "";
if (is_array($SECTION) && !empty($SECTION)) {
    $lang = $_FN['lang'] ?? '';
    if ($lang && file_exists("$folder/section.{$lang}.html")) {
        $html = FN_NormalizeAllPaths(FN_TPL_ApplyTplFile("$folder/section.{$lang}.html", $SECTION));
    } else {
        if (file_exists("$folder/section.html")) {
            $html = FN_NormalizeAllPaths(FN_TPL_ApplyTplFile("$folder/section.html", $SECTION));
        }
    }
    echo $html;
}
