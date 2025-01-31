<?php

/**
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2024
 */
//-------------------------- init timer---------------------------------------->
$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = doubleval($mtime[1]) + doubleval($mtime[0]);
//-------------------------- init timer----------------------------------------<
define("_FNEXEC", 1);
defined('_FNEXEC') or die('Restricted access');
global $_FN;
$FN_IsSet = array();
if ($_FN)
{
    $FN_IsSet = $_FN;
}
$_FN['src_finis'] = !empty($_FN['src_finis']) ? $_FN['src_finis'] : realpath(__DIR__ . "/..");


$_FN['src_application'] = !empty($_FN['src_application']) ? $_FN['src_application'] : ".";
$_FN['oauth_providers'] = !empty($_FN['oauth_providers']) ? $_FN['oauth_providers'] : array();
$_FN['display_errors'] = !empty($_FN['display_errors']) ? $_FN['oauth_providers'] : "off";
$_FN['datadir'] = !empty($_FN['datadir']) ? $_FN['datadir'] :"{$_FN['src_application']}/mi"
. "sc";


//-------- -global variables shared between config.php and flatnux.php -------->
$_FN['upload_max_filesize'] = "20M";
$_FN['default_auth_method'] = "local";
$_FN['default_database_driver'] = !empty($_FN['default_database_driver']) ? $_FN['default_database_driver'] : "xmlphp"; 

$_FN['default_section_type'] = "finis";
require_once __DIR__ . "/config.vars.php";
if (file_exists($_FN['src_application'] . "/config.vars.local.php"))
    require_once $_FN['src_application'] . "/config.vars.local.php";



$_FN = array_merge($FN_IsSet,$_FN);

//-------- -global variables shared between config.php and flatnux.php --------<
//----------------- need in framework mode ------------------------------------>
if (empty($_FN))
{
    $_FN = array();
}
else
{
    $_FN_TMP = $_FN; //save $_FN values
}
if (!isset($_FN['return']))
{
    $_FN['return'] = array();
}

//----------------- need in framework mode ------------------------------------<
ini_set("display_errors", $_FN['display_errors']);
error_reporting(E_ALL);
ini_set("upload_max_filesize", "{$_FN['upload_max_filesize']}");

$_FN['timestart'] = $mtime; // start time
$_FN['consolemode'] = false;
$_FN['src_finis'] = realpath(__DIR__ . "/..");

$_FN['charset_lang'] = "UTF-8";  //default
$_FN['charset_page'] = "UTF-8";
$_FN['database'] = "fndatabase";
$_FN['use_urlserverpath'] = false; //use url path in link to generate the html ,false:href="http://example/include/css/style.css" , true:href="/include/css/style.css"
//-------------------console mode---------------------------------------------->
if (isset($_SERVER['SHELL']))
{
    $_FN['src_finis'] = realpath(__DIR__ . "/..");
    $_FN['consolemode'] = true;
    $_FN['script_path'] = $_SERVER['PWD'];
    chdir(dirname($_SERVER["PHP_SELF"]));
}
//-------------------console mode----------------------------------------------<
//------------------------ include files extra cms ---------------------------->
$files = glob($_FN['src_application'] . "/extra/*.inc.php");
if (is_array($files))
{
    foreach ($files as $file)
    {
        require_once $file;
    }
}

//------------------------ include files extra cms ----------------------------<
//------------------------------files in cms ---------------------------------->
$files = glob("{$_FN['src_finis']}/include/*.inc.php");
foreach ($files as $file)
{
    include_once $file;
}
//------------------------------files in cms ----------------------------------<
//--------------------------------   xmetadb ------------------------------------>
require_once "{$_FN['src_finis']}/include/xmetadb.php";
require_once "{$_FN['src_finis']}/include/xmetadb_frm.php";
require_once "{$_FN['src_finis']}/include/xmetadb_query.php";
require_once "{$_FN['src_finis']}/include/xmetadb_frm_search.php";
//--------------------------------   xmetadb ------------------------------------<
if (file_exists($_FN['src_application'] . "/include/auth/{$_FN['default_auth_method']}.php"))
    require_once $_FN['src_application'] . "/include/auth/{$_FN['default_auth_method']}.php";
else
    require_once "{$_FN['src_finis']}/include/auth/{$_FN['default_auth_method']}.php";

include "{$_FN['src_finis']}/config.php";
if ($_FN['consolemode'])
{
    $_FN['datadir'] = realpath($_FN['datadir']);
}
$configvars = array(
    "sitename",
    "site_title",
    "site_subtitle",
    "keywords",
    "languages",
    "theme",
    "controlcenter_theme",
    "switchtheme",
    "siteurl",
    "site_email_address",
    "log_email_address",
    "enable_compress_gzip",
    "home_section",
    "jet_lag",
    "showaccesskey",
    "enable_log_email",
    "enable_mod_rewrite",
    "links_mode",
    "enable_registration",
    "username_is_email",
    "registration_by_email",
    "remember_login",
    "enable_captcha",
    "htmleditor",
    "enable_online_administration",
    "credits",
    "maintenance",
    "url_update",
    "use_cache",
    "timezone",
    "use_urlserverpath"
);
$ignore = array("script_path", "datadir", "return", "timestart", "consolemode", "filesystempath", "charset_lang", "default_database_driver", "section_header_footer", "FN_SendMail", "frameworkmode", "selfscript");
FN_LoadVarsFromTable($_FN, "fn_settings", $configvars, $ignore);
//----------------------------------timezone----------------------------------->
if (function_exists("date_default_timezone_get"))
{
    if ($_FN['timezone'] == "")
    {
        $_FN['timezone'] = date_default_timezone_get();
    }
    if (trim(ltrim($_FN['timezone'])) == "")
    {
        $_FN['timezone'] = "UTC";
    }
    date_default_timezone_set($_FN['timezone']);
}
//----------------------------------timezone-----------------------------------<
$_FN['enable_mod_rewrite_default'] = $_FN['enable_mod_rewrite'];
require_once "{$_FN['src_finis']}/include/modrewrite.php";
strstr(PHP_OS, "WIN") ? $_FN ['slash'] = "\\" : $_FN ['slash'] = "/";
$_FN['self'] = FN_GetParam("PHP_SELF", $_SERVER);
$_FN['selfscript'] = basename($_FN['self']);
$_FN['user'] = "";
$mod = basename(FN_GetParam("mod", $_GET));
if (!file_exists("{$_FN['src_finis']}/sections/{$_FN['home_section']}"))
    $_FN['home_section'] = "";
if ($mod == "")
{
    $mod = $_FN['home_section'];
}
$_FN['block'] = "";
$_FN['mod'] = $mod;
$php_self = FN_GetParam("PHP_SELF", $_SERVER);
$SCRIPT_NAME = FN_GetParam("SCRIPT_NAME", $_SERVER);

$_FN ['self'] = $php_self;
//--------------------------site url ------------------------------------------>
if ($_FN['siteurl'] == "")
{
    if (!$_FN['consolemode'] /* &&  !$_FN['frameworkmode'] */) //consolemode need explicit siteurl
    {
        $dirname = dirname($SCRIPT_NAME);
        if (isset($_SERVER ['SCRIPT_FILENAME']))
        {
            $serverpath = dirname($_SERVER ['SCRIPT_FILENAME']);
        }
        elseif (isset($_SERVER ['PATH_TRANSLATED']))
        {
            $_SERVER ['SCRIPT_FILENAME'] = $_SERVER ['PATH_TRANSLATED'];
            $serverpath = dirname($_SERVER ['PATH_TRANSLATED']);
        }
        if ($dirname == "/" || $dirname == "\\")
            $dirname = "";
        // server windows
        $dirname = str_replace("\\", "/", $dirname);
        $protocol = "http://";
        if (isset($_SERVER ['HTTPS']) && $_SERVER ['HTTPS'] == "on")
            $protocol = "https://";
        if (isset($_SERVER ['HTTP_X_FORWARDED_PROTO']) && $_SERVER ['HTTP_X_FORWARDED_PROTO'] == "https")
            $protocol = "https://";
        if (isset($_SERVER ['HTTP_HOST']))
        {
            $siteurl = "$protocol" . $_SERVER ['HTTP_HOST'] . $dirname;
            if (substr($siteurl, strlen($siteurl) - 1, 1) != "/")
            {
                $siteurl = $siteurl . "/";
            }
        }
        else
        {
            $siteurl = "";
        }
    }
    else
    {
        $siteurl = "";
    }
    $_FN['siteurl'] = $siteurl;
}


//--------------------------site url ------------------------------------------<
//--------------------------site path------------------------------------------>
if (empty($_FN['sitepath']))
{
    $_FN['sitepath'] = FN_GetParam("PHP_SELF", $_SERVER);
    if ($_FN['sitepath'] == "")
        $_FN['sitepath'] = "/";
    else
    {
        $_FN['sitepath'] = dirname($_FN['sitepath']) . "/";
        if ($_FN['sitepath'] == "//")
            $_FN['sitepath'] = "/";
    }
}
//--------------------------site path------------------------------------------<
//--------------------------languages------------------------------------------>
$_FN['listlanguages'] = explode(",", $_FN['languages']);
$_FN['lang'] = $_FN['listlanguages'][0];
$_FN['multilanguage'] = false;
if (count($_FN['listlanguages']) > 1)
{
    $_FN['multilanguage'] = true;
}
if (empty($_FN['sitelanguages']))
{
    $_FN['sitelanguages'] = array();
    foreach ($_FN['listlanguages'] as $l)
    {
        $params = array();
        $params['langname'] = $l;
        $params['langtitle'] = FN_Translate("_LANGUAGE", "", $l);
        $params['langimg'] = FN_PathSite("{$_FN['src_finis']}/images/flags/$l.png", true);
        $params['langflag'] = FN_getCountryFlag($l);

        $_FN['sitelanguages'][$l] = $params;
    }
}
$_FN['lang_default'] = $_FN['lang'];
//--------------------------languages------------------------------------------<
//--------------------------  set theme ---- ---------------------------------->
if (!empty($_FN_TMP['theme']))
{
    $_FN['theme'] = $_FN_TMP['theme'];
}




global $FN_THEME;
if (!empty($FN_THEME))
{
    $_FN['theme'] = $FN_THEME;
}
$_FN['theme_default'] = $_FN['theme'];
if ($_FN['theme'] == "" ||
        (
        !file_exists("{$_FN['src_finis']}/themes/{$_FN['theme']}") &&
        !file_exists($_FN['src_application'] . "/themes/{$_FN['theme']}")
        )
)
{
    $_FN['theme'] = "base";
}


$_FN['charset_page'] = $_FN['charset_lang'];
//--------------------------  set theme ---- ----------------------------------<
if (!$_FN['consolemode'])
{
//---------------------url cookie---------------------------------------------->
    if (empty($_FN['urlcookie']))
    {
        $urlcookie = FN_GetParam("PHP_SELF", $_SERVER);
        $path = pathinfo($urlcookie);
        $urlcookie = $path["dirname"] . "/";
        $urlcookie = str_replace("\\", "/", $urlcookie);
        if ($urlcookie == "" || $urlcookie == "\\" || $urlcookie == "//")
            $urlcookie = "/";

        $_FN['urlcookie'] = $urlcookie;
    }
//---------------------url cookie----------------------------------------------<


    FN_ManageLogin();

//---------------vars in cookie------------------------------------------------>
    $_FN['fneditmode'] = FN_SaveGetPostParam("fneditmode");
    $_FN['lang'] = FN_SaveGetPostParam("lang");
    if (!in_array($_FN['lang'], $_FN['listlanguages']))
        $_FN['lang'] = $_FN['lang_default'];
    $_FN['showaccesskey'] = FN_SaveGetPostParam("showaccesskey");
    $change_theme = isset($_REQUEST['change_theme']);
    if ($change_theme)
    {
        $usertheme = FN_SaveGetPostParam("theme", false, false, false);
    }
    else
    {
        $usertheme = FN_SaveGetPostParam("theme", true, true, !$_FN['switchtheme']);
    }

    $_FN['section_header_footer'] = isset($_FN['section_header_footer']) ? $_FN['section_header_footer'] : "";
    if ($usertheme != "")
    {
        if (file_exists("{$_FN['src_finis']}/themes/$usertheme") || file_exists("{$_FN['src_application']}/themes/$usertheme"))
            $_FN['theme'] = $usertheme;
        else
            $_FN['theme'] = $_FN['theme_default'];
    }
    if (!empty($_FN['switchtheme']) || FN_IsAdmin())//preview theme
    {
        $themepreview = FN_GetParam("themepreview", $_GET);
        if ($themepreview != "" && (file_exists("{$_FN['src_finis']}/themes/{$_FN['theme']}") || file_exists("{$_FN['src_application']}/themes/{$_FN['theme']}") ))
            $_FN['theme'] = $themepreview;
    }
//---------------vars in cookie------------------------------------------------<    
}



//-------------------init var sections,blocks,sectiontypes -------------------->





if (empty($_FN['sectionstypes']))
    $_FN['sectionstypes'] = FN_GetAllSectionTypes();

if (empty($_FN['blocks']))
    $_FN['blocks'] = FN_GetAllBlocks();

if (empty($_FN['sections']))
    $_FN['sections'] = FN_GetAllSections();

$_FN['sectionvalues'] = FN_GetSectionValues($_FN['mod']);
if (!empty($_FN['sectionvalues']['keywords']))
    $_FN['keywords'] = "{$_FN['sectionvalues']['keywords']}";


//-------------------init var sections,blocks,sectiontypes --------------------<
//----------------------------include theme------------------------------------>
if (!file_exists("{$_FN['src_finis']}/themes/{$_FN['theme']}"))
{
    $_FN['theme'] = $_FN['theme_default'];
    if (!file_exists("{$_FN['src_finis']}/themes/{$_FN['theme']}") && !file_exists("{$_FN['src_application']}/themes/{$_FN['theme']}"))
    {
        $_FN['theme'] = "base";
    }
}

if (file_exists("{$_FN['src_application']}/themes/{$_FN['theme']}/theme.php"))
{
    include_once("{$_FN['src_application']}/themes/{$_FN['theme']}/theme.php");
}
elseif (file_exists("{$_FN['src_finis']}/themes/{$_FN['theme']}/theme.php"))
{
    include_once("{$_FN['src_finis']}/themes/{$_FN['theme']}/theme.php");
}

include_once("{$_FN['src_finis']}/include/theme.php");
//----------------------------include theme------------------------------------<
FN_LoadMessagesFolder("{$_FN['src_finis']}/");
FN_LoadMessagesFolder("{$_FN['src_application']}/");
FN_LoadMessagesFolder("{$_FN['src_finis']}/themes/{$_FN['theme']}/");

if (isset($_FN_TMP['maintenance']))
{
    $_FN['maintenance'] = $_FN_TMP['maintenance'];
}

$fnapp = FN_GetParam("fnapp", $_GET);
if (!$_FN['consolemode'] && !empty($_FN['maintenance']) && $fnapp != "controlcenter")
{
    if (!FN_IsAdmin())
    {
        die(FN_HtmlMainteanceMode());
    }
}
//--language from module
if (!empty($_FN['sectionvalues']['type']))
{
    FN_LoadMessagesFolder("{$_FN['src_finis']}/modules/{$_FN['sectionvalues']['type']}");
}
//--language from section
FN_LoadMessagesFolder("{$_FN['src_finis']}/sections/{$_FN['mod']}");
if (!empty($_FN['languages_path']) && is_array($_FN['languages_path']))
    foreach ($_FN['languages_path'] as $lp)
    {
        FN_LoadMessagesFolder($lp);
    }
$_FN['days'] = array(FN_i18n("sunday"), FN_i18n("monday"), FN_i18n("tuesday"), FN_i18n("wednesday"), FN_i18n("thursday"), FN_i18n("friday"), FN_i18n("saturday"));
$_FN['months'] = array(FN_i18n("january"), FN_i18n("february"), FN_i18n("march"), FN_i18n("april"), FN_i18n("may"), FN_i18n("june"), FN_i18n("july"), FN_i18n("august"), FN_i18n("september"), FN_i18n("october"), FN_i18n("november"), FN_i18n("december"));
$_FN['site_title'] = FN_i18n($_FN['site_title']);
$_FN['site_subtitle'] = FN_i18n($_FN['site_subtitle']);
$_FN['site_title'] = FN_i18n($_FN['site_title']);
$_FN['site_subtitle'] = FN_i18n($_FN['site_subtitle']);
//$_FN['formlogin'] = FN_HtmlLoginForm();
$_FN['section_title'] = isset($_FN['sectionvalues']['title']) ? $_FN['sectionvalues']['title'] : "";
$_FN['GET'] = &$_GET;
$_FN['POST'] = &$_POST;
$_FN['REQUEST'] = &$_REQUEST;
$_FN['INPUT'] = file_get_contents("php://input");

if (!empty($_FN['include']))
{
    $_FN['include'] = array_unique($_FN['include']);

    foreach ($_FN['include'] as $value)
    {
        if (file_exists($value))
        {
            include($value);
        }
    }
}
////////////////////////////////////////////////////////////////////////////////
//////////////////////////////   debug functions  //////////////////////////////
////////////////////////////////////////////////////////////////////////////////

/**
 *
 * @param type $var
 * @param string $str 
 */
function dprint_r($var, $str = "", $color = "green")
{
    global $_FN;
    if (empty($_FN['consolemode']))
        echo "<pre style=\"font-size:10px;line-height:12px;border:1px solid $color\">";
    echo "$str\n";
    print_r($var);
    if (empty($_FN['consolemode']))
        echo "</pre>";
}

/**
 *
 * @param string $var
 * @param string $str 
 */
function dprint_xml($var, $str = "", $color = "magenta")
{
    global $_FN;
    if (empty($_FN['consolemode']))
    {
        echo "<pre style=\"font-size:10px;line-height:12px;border:1px solid $color\">";
        echo "$str\n";
        if (is_object($var))
        {
            $var = get_object_vars($var);
        }
        if (is_array($var))
        {
            foreach ($var as $k => $val)
            {
                echo "\n[$k]=>\n";
                dprint_xml($val);
            }
        }
        else
            echo htmlspecialchars($var);
        echo "</pre>";
    }
    else
    {
        echo "\n---$str--->\n$var\n<---$str---\n";
    }
}

/**
 * 
 * @global type $_FN
 * @staticvar boolean $oldTimer
 * @param type $str
 * 
 * use: FN_Debug_timer(__FILE__.":".__LINE__);
 */
function FN_Debug_timer($str)
{
    global $_FN;
    static $oldTimer = false;
    $mtime = explode(" ", microtime());
    $mtime = doubleval($mtime[1]) + doubleval($mtime[0]);
    if ($oldTimer === false)
        $oldTimer = $mtime;
    $str .= " total " . sprintf("%.4f", abs($mtime - $_FN['timestart']));
    $str .= " -  last:" . sprintf("%.4f", abs($mtime - $oldTimer));
    $oldTimer = $mtime;
    echo("<pre style=\"border:1px solid red\">$str</pre>");
}

function FN_AddLanguagePath($path)
{
    global $_FN;
    $_FN['languages_path'][] = $path;
}

/**
 * 
 * @param type $file_to_Include
 */
function FN_IncludeScript($file_to_Include)
{
    global $_FN;
    if (empty($_FN['include']))
    {
        $_FN['include'] = array();
    }
    $_FN['include'][] = $file_to_Include;
}




