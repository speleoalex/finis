<?php

if (!defined("FN_AUTH_COST"))
{
    define("FN_AUTH_COST", 10);
}
if (!defined("FN_AUTH_METHOD"))
{
    //PASSWORD_DEFAULT OR PASSWORD_BCRYPT
    if (defined('PASSWORD_DEFAULT'))
    {
        define("FN_AUTH_METHOD", PASSWORD_DEFAULT);
    }
}

/**
 * Returns the per-site secret used to sign the session cookie.
 * Generated once with random_bytes() and stored in the data directory.
 * The file starts with "<?php exit(0); ?>" like the xmetadb data files,
 * so it is not readable even if the data directory is served via web.
 *
 * @global array $_FN
 * @return string
 */
function FN_GetSiteSecret()
{
    global $_FN;
    static $secret = null;
    if ($secret !== null)
        return $secret;
    $file = "{$_FN['datadir']}/secret.key.php";
    $header = "<?php exit(0); ?>\n";
    if (file_exists($file))
    {
        $content = file_get_contents($file);
        $secret = trim(substr($content, strlen($header)));
    }
    if (empty($secret) || strlen($secret) < 32)
    {
        $secret = bin2hex(random_bytes(32));
        if (!FN_Write($header . $secret, $file))
        {
            FN_Log("Cannot write site secret to $file");
        }
        @chmod($file, 0600);
    }
    return $secret;
}

/**
 * Computes the value of the "secid" cookie for a user.
 * Changing the password of the user invalidates the cookie.
 *
 * @param string $user
 * @param string $passwd stored password hash
 * @return string
 */
function FN_GetSecid($user, $passwd)
{
    return hash_hmac('sha256', $user . '|' . $passwd, FN_GetSiteSecret());
}

/**
 * True when the current request uses HTTPS (directly or behind a proxy).
 *
 * @return bool
 */
function FN_IsHttps()
{
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
        return true;
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == "https")
        return true;
    return false;
}

/**
 * IP address of the client. Behind a reverse proxy REMOTE_ADDR is the address
 * of the proxy: when it is in $_FN['trusted_proxies'] (IP addresses, comma
 * separated or an array) the client is the last address in X-Forwarded-For
 * not added by a trusted proxy. Without trusted proxies the header is ignored,
 * since any client can send it.
 *
 * @global array $_FN
 * @return string
 */
function FN_GetClientIp()
{
    global $_FN;
    $ip = FN_GetParam("REMOTE_ADDR", $_SERVER, "html");
    $proxies = !empty($_FN['trusted_proxies']) ? $_FN['trusted_proxies'] : array();
    if (!is_array($proxies))
        $proxies = explode(",", $proxies);
    $proxies = array_filter(array_map("trim", $proxies));
    if (in_array($ip, $proxies, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR']))
    {
        //each proxy appends the address the request comes from: the addresses on the left can be forged
        foreach (array_reverse(explode(",", $_SERVER['HTTP_X_FORWARDED_FOR'])) as $hop)
        {
            $hop = trim($hop);
            if (!filter_var($hop, FILTER_VALIDATE_IP))
                break;
            $ip = $hop;
            if (!in_array($hop, $proxies, true))
                break;
        }
    }
    return $ip;
}

/**
 * True when the url points to this site: an absolute url under $_FN['siteurl']
 * or a relative url without scheme and host.
 * Check it before a redirect, so that it cannot lead to another site.
 *
 * @param string $url
 * @global array $_FN
 * @return bool
 */
function FN_IsInternalUrl($url)
{
    global $_FN;
    //browsers drop tabs and newlines, trim spaces and read "\" as "/": "/\evil.com" is "//evil.com"
    if (!is_string($url) || $url === "" || preg_match('/[\x00-\x20\x7F\\\\]/', $url) || substr($url, 0, 2) == "//")
    {
        return false;
    }
    if (!empty($_FN['siteurl']) && strpos($url, rtrim($_FN['siteurl'], "/") . "/") === 0)
    {
        return true;
    }
    //relative url: no scheme (http:, javascript:, data: ...)
    return !preg_match('/^[a-z][a-z0-9+.-]*:/i', $url);
}

/**
 * File that keeps the recent login attempts of a username from the client IP
 * (FN_GetClientIp()).
 * It is in {datadir}/_login_attempts, not in the cache folder: FN_ClearCache()
 * does not reset the counters.
 *
 * @param string $user
 * @global array $_FN
 * @return string
 */
function FN_LoginAttemptsFile($user)
{
    global $_FN;
    $ip = FN_GetClientIp();
    return "{$_FN['datadir']}/_login_attempts/" . hash('sha256', $user . '|' . $ip) . ".cache";
}

/**
 * Records a login attempt of a username from the client IP, before the
 * password is checked, so parallel requests cannot pass the limit.
 * After $_FN['login_max_attempts'] attempts (default 5) in the last
 * $_FN['login_lockout_minutes'] minutes (default 15) the attempt is refused.
 * FN_LoginResetAttempts() clears the attempts after a successful login.
 * Once in the time window, the files of the counters not updated in it are removed.
 *
 * @param string $user
 * @global array $_FN
 * @return bool false if the username is locked out from this IP
 */
function FN_LoginAttempt($user)
{
    global $_FN;
    $max = !empty($_FN['login_max_attempts']) ? intval($_FN['login_max_attempts']) : 5;
    $minutes = !empty($_FN['login_lockout_minutes']) ? intval($_FN['login_lockout_minutes']) : 15;
    $file = FN_LoginAttemptsFile($user);
    //once in the time window, remove the counters not updated in it: they have only expired attempts
    $dir = dirname($file);
    if (!file_exists("$dir/cleanup") || filemtime("$dir/cleanup") < time() - $minutes * 60)
    {
        FN_MkDir($dir);
        @touch("$dir/cleanup");
        $files = glob("$dir/*.cache");
        if (is_array($files))
        {
            foreach ($files as $oldfile)
            {
                if (@filemtime($oldfile) < time() - $minutes * 60)
                    @unlink($oldfile);
            }
        }
    }
    //wait for a concurrent request, a stale lock is removed by FN_UnlockFile
    for ($i = 0; $i < 20 && !FN_LockFile($file); $i++)
    {
        usleep(50000);
    }
    $content = file_exists($file) ? file_get_contents($file) : "";
    $attempts = array();
    foreach (explode("\n", $content) as $time)
    {
        if (intval($time) > time() - $minutes * 60)
            $attempts[] = intval($time);
    }
    $allowed = (count($attempts) < $max);
    if ($allowed)
    {
        $attempts[] = time();
        FN_Write(implode("\n", $attempts), $file);
    }
    elseif (strpos($content, "locked") === false)
    {
        //log once for each lockout
        FN_Write(implode("\n", $attempts) . "\nlocked", $file);
        FN_Log("User $user locked out for $minutes minutes after $max failed logins from " . FN_GetClientIp());
    }
    FN_UnlockFile($file);
    return $allowed;
}

/**
 * Clears the login attempts of a username from the client IP.
 *
 * @param string $user
 */
function FN_LoginResetAttempts($user)
{
    $file = FN_LoginAttemptsFile($user);
    if (file_exists($file))
    {
        @unlink($file);
    }
}

function FN_LoginInitUrl($script="")
{
    global $_FN;
    $loginmod = "login";
    if (!$script)
    {
        $script = "index.php";
    }
    if (isset($_FN['mod']) && isset($_FN['sections']) && isset($_FN['sections'][$_FN['mod']]['type']) && $_FN['sections'][$_FN['mod']]['type'] == "login")
    {
        $loginmod = $_FN['mod'];
    }
    elseif (empty($_FN['sections']['login']) || $_FN['sections']['login']['type'] == "login")
    {
        if (isset($_FN['sections']))
        {
            foreach ($_FN['sections'] as $k => $v)
            {
                if ($v['type'] == "login")
                {
                    $loginmod = $k;
                }
            }
        }
    }
    $_FN['urlregister'] = FN_RewriteLink("$script?mod=$loginmod&amp;op=register", "&amp;", true);
    $_FN['urlprofile'] = FN_RewriteLink("$script?mod=$loginmod&amp;op=profile", "&amp;", true);
    $_FN['urleditprofile'] = FN_RewriteLink("$script?mod=$loginmod&amp;op=editreg", "&amp;", true);
    $_FN['urllogin'] = FN_RewriteLink("$script?mod=$loginmod", "&amp;", true);
    $_FN['urlloginform'] = FN_RewriteLink("$script?mod={$_FN['mod']}&fnlogin=login", "&amp;", true);
    $_FN['urllogout'] = FN_RewriteLink("$script?fnlogin=logout", "&amp;", true);
    $_FN['urlpasswordrecovery'] = FN_RewriteLink("$script?mod=$loginmod&op=recovery", "&amp;", true);
    $_FN['urlresendcode'] = false;
    if ($_FN['registration_by_email'])
        $_FN['urlresendcode'] = FN_RewriteLink("$script?mod=$loginmod&op=send_code", "&amp;", true);
}

/**
 * @package Finis_auth_local
 * @param array $arrayfilter
 * @return int
 */
function FN_CountUsers($arrayfilter = false)
{
    $Table = FN_GetUserForm();
    return $Table->xmltable->GetNumRecords($arrayfilter);
}

/**
 * 
 * @return type
 */
function FN_GetUserForm()
{
    static $form = null;
    if (!$form)
    {
        FN_LoginInitUrl();
        $form = FN_XMDBForm("fn_users" );
        $form->formvals['passwd']['frm_type'] = "cryptpasswd";
        $op = FN_GetParam("op", $_GET, "html");
        $pk___xdb_fn_users = FN_GetParam("pk___xdb_fn_users", $_GET, "html");
        if ($op == "editreg" || $pk___xdb_fn_users != "")
            $form->formvals['passwd']['frm_required'] = false;
        $form->LoadFieldsClasses();
        $form->fieldname_active = "active";
        $form->fieldname_user = "username";
        $form->fieldname_password = "passwd";

    }
    return $form;
}

/**
 *
 * @param array $arrayfilter
 * @return array
 */
function FN_GetUsers($arrayfilter = false)
{
    $Table = FN_GetUserForm();
    return $Table->xmltable->GetRecords($arrayfilter);
}

/**
 *
 */
function FN_ManageLogin()
{
    global $_FN;
    FN_LoginInitUrl();
    $fnlogin = FN_GetParam("fnlogin", $_GET);
    $fnuser = FN_GetParam("username", $_POST);
    $fnpwd = FN_GetParam("password", $_POST);
    $captcha_ok = true;
    FN_ManageLoginWithOpenAuthentication();
    //-------------------captcha----------------------------------------------->
    //checked only on a login: the other requests (e.g. the captcha image) leave the code as it is
    if (!empty($_FN['enable_captcha']) && $fnlogin == "login" && $fnuser != "" && $fnpwd != "")
    {
        $captcha = FN_GetSessionValue("captcha");
        if (!is_array($captcha))
            $captcha = array();
        $fnlogincode = FN_GetParam("fnlogin_code", $_POST);
        if ($fnlogincode == "" || empty($captcha['fnlogin_code']) || !hash_equals((string) $captcha['fnlogin_code'], $fnlogincode))
        {
            $captcha_ok = false;
        }
        //each code is checked once: the login form shows a new one, the code of the registration form is kept
        unset($captcha['fnlogin_code']);
        FN_SetSessionValue("captcha", $captcha);
    }
    //-------------------captcha-----------------------------------------------<
    $rememberlogin  = FN_GetParam("rememberlogin", $_POST);
    $justLoggedIn   = false;
    if ($fnlogin == "login" && $fnuser != "" && $fnpwd != "")
    {
        if (empty($_FN['username_case_sensitive']))
            $fnuser = strtolower($fnuser);
        if ($captcha_ok && FN_LoginAttempt($fnuser) && FN_VerifyUserPassword($fnuser, $fnpwd))
        {
            FN_LoginResetAttempts($fnuser);
            //FN_Login fails when the user is not active: the cookies sent by the client are then checked as usual
            $justLoggedIn = FN_Login($fnuser, $rememberlogin);
        }
        else
        {
            FN_Logout();
            $_FN['login_error'] = FN_Translate("error username or password");
        }
    }
    $_FN['user'] = FN_GetParam("fnuser", $_COOKIE);
    $_FN['user_read_error'] = false;
    // Skip the session check on the very request that performed login:
    // FN_Login() already set the cookies; re-checking immediately would race
    // against the browser not yet having sent back the new cookie.
    if (!$justLoggedIn && (!FN_CheckUser() || $fnlogin == "logout"))
    {
        if ($fnlogin != "logout" && !empty($_FN['user_read_error']))
        {
            // the user could not be read (database error): anonymous for this
            // request only, the login cookies are kept for the next ones
            $_FN['user'] = "";
        }
        else
        {
            FN_Logout();
        }
    }
    //-------------------API key or OAuth token (MCP and API clients)--------->
    if ($_FN['user'] == "")
    {
        $apikey = FN_GetRequestApiKey();
        if ($apikey != "" && !FN_OAuthServerLoginByToken($apikey))
        {
            FN_LoginByApiKey($apikey);
        }
    }
    //-------------------API key or OAuth token (MCP and API clients)---------<
}

/**
 * 
 */
function FN_LoginForm($templateForm = false)
{
    echo FN_HtmlLoginForm($templateForm);
}

/**
 *
 * @global array $_FN
 */
function FN_HtmlLoginForm($templateForm = false, $url = "")
{
    global $_FN;
    FN_LoginInitUrl();

    if ($templateForm)
    {
        if (file_exists($templateForm))
        {
            $templateForm = file_get_contents($templateForm);
            $tplbasepath = dirname($templateForm);
        }
        else
        {
            $tplbasepath = "themes/{$_FN['theme']}";
        }
    }
    if (!$templateForm)
    {
        if (file_exists(FN_PathSite("themes/{$_FN['theme']}/template.{$_FN['mod']}.tp.html")))
        {
            $templateForm = FN_TPL_GetHtmlPart("include FN_HtmlLoginForm", file_get_contents(FN_PathSite("themes/{$_FN['theme']}/template.{$_FN['mod']}.tp.html")));
            $tplbasepath = "{$_FN['src_application']}/themes/{$_FN['theme']}";
        }
    }
    if (!empty($_FN['sectionvalues']['type']) && file_exists("themes/{$_FN['theme']}/template.type.{$_FN['sectionvalues']['type']}.tp.html"))
    {
        $templateForm = FN_TPL_GetHtmlPart("include FN_HtmlLoginForm", file_get_contents("themes/{$_FN['theme']}/template.type.{$_FN['sectionvalues']['type']}.tp.html"));
        $tplbasepath = "{$_FN['src_application']}/themes/{$_FN['theme']}";
    }
    if (!$templateForm)
    {
        if (file_exists("themes/{$_FN['theme']}/template.tp.html"))
        {
            $templateForm = FN_TPL_GetHtmlPart("include FN_HtmlLoginForm", file_get_contents("themes/{$_FN['theme']}/template.tp.html"));
            $tplbasepath = "{$_FN['src_application']}/themes/{$_FN['theme']}";
        }
    }
    if (!$templateForm)
    {
        $templateForm = file_get_contents(FN_FromTheme("{$_FN['src_finis']}/modules/login/login.tp.html", false));
        $tplbasepath = dirname(FN_FromTheme("{$_FN['src_finis']}/modules/login/login.tp.html", false));
    }
    $querystring = FN_GetParam("QUERY_STRING", $_SERVER);
    //a theme shell (fnshell=1) or a fragment (fnajax=1) is shown again under the url of
    //another page (offline copies, ajax navigation): the form must not post back to it
    $querystring = ltrim(preg_replace('/(^|&)fn(shell|ajax)=[^&]*/', '', $querystring), "&");
    //the query string ends in the action attribute of the form
    $querystring = htmlspecialchars($querystring, ENT_QUOTES);
    $querystring = str_replace("fnlogin=login&amp;", "", $querystring);
    $querystring = str_replace("fnlogin=logout&amp;", "", $querystring);
    $querystring = str_replace("?fnlogin=login", "?", $querystring);
    $querystring = str_replace("?fnlogin=logout", "?", $querystring);
    $querystring = str_replace("&amp;fnlogin=login", "", $querystring);
    $querystring = str_replace("&amp;fnlogin=logout", "", $querystring);
    $querystring .= "&amp;fnlogin=login";
    $scriptname = (basename($_SERVER['PHP_SELF']));
    if ($scriptname == "index.php")
    {
        $querystring = FN_RewriteLink("index.php?" . $querystring, "&amp;", true);
    }
    else
    {
        $querystring = "?" . $querystring;
    }
    if ($url)
    {
        $querystring = $url;
    }
    $tplvars = array();
    $tplvars['login_error'] = "";
    $tplvars['formaction'] = $querystring;
    $tplvars['txtusername'] = (!empty($_FN['username_is_email'])) ? FN_Translate("email") : FN_Translate("username");

    //------------------------------------ captcha ---------------------------->
    $htmlcaptcha = "";
    if (!empty($_FN['enable_captcha']))
    {
        //the code is checked by FN_ManageLogin
        FN_NewCaptchaCode("fnlogin_code");
        $htmlcaptcha .= "<img style=\"vertical-align:middle\" src=\"{$_FN['siteurl']}?fnapp=captcha&amp;t=fnlogin_code&amp;" . time() . "\" alt=\"\" title=\"\" /> <input size=\"4\" name=\"fnlogin_code\"  value = \"\" />";
        $tplvars['htmlcaptcha'] = $htmlcaptcha;
    }
    else
    {
        $templateForm = FN_TPL_ReplaceHtmlPart("captcha", "", $templateForm);
    }
    //------------------------------------ captcha ----------------------------<
    if (empty($_FN['remember_login']))
    {
        $templateForm = FN_TPL_ReplaceHtmlPart("rememberlogin", "", $templateForm);
    }
    if (!empty($_FN['login_error']))
    {
        $tplvars['login_error'] = FN_Translate("authentication failure");
    }
    else
    {
        $templateForm = FN_TPL_ReplaceHtmlPart("loginerror", "", $templateForm);
    }
    if (empty($_FN['enable_registration']))
    {
        $templateForm = FN_TPL_ReplaceHtmlPart("register", "", $templateForm);
    }
    $tplvars['oauth_providers'] = FN_GetOpenAuthProviders();
    
    $html = FN_TPL_ApplyTplString($templateForm, $tplvars, $tplbasepath);

    return $html;
}

/**
 *
 * @global array $_FN
 */
function FN_HtmlLogoutForm($templateForm = false)
{
    global $_FN;
    if ($templateForm)
    {
        $tplbasepath = "{$_FN['src_application']}/themes/{$_FN['theme']}";
    }
    elseif (file_exists(FN_PathSite("themes/{$_FN['theme']}/template.tp.html")))
    {
        $templateForm = FN_TPL_GetHtmlPart("include FN_HtmlLogoutForm", file_get_contents("{$_FN['src_application']}/themes/{$_FN['theme']}/template.tp.html"));
        $tplbasepath = "{$_FN['src_application']}/themes/{$_FN['theme']}";
    }
    if (!$templateForm)
    {
        $templateForm = file_get_contents(FN_FromTheme("{$_FN['src_finis']}/modules/login/logout.tp.html", false));
        $tplbasepath = dirname(FN_FromTheme("{$_FN['src_finis']}/modules/login/logout.tp.html", false));
    }
    $tplvars = array();
    $querystring = FN_GetParam("QUERY_STRING", $_SERVER);
    //a theme shell (fnshell=1) or a fragment (fnajax=1) is shown again under the url of
    //another page (offline copies, ajax navigation): the form must not post back to it
    $querystring = ltrim(preg_replace('/(^|&)fn(shell|ajax)=[^&]*/', '', $querystring), "&");
    //the query string ends in the action attribute of the form
    $querystring = htmlspecialchars($querystring, ENT_QUOTES);
    $tplvars['formaction'] = "?$querystring&amp;fnlogin=logout";
    $html = FN_TPL_ApplyTplString($templateForm, $tplvars, $tplbasepath);
    return $html;
}

/**
 *
 * @global array $_FN
 */
function FN_LogoutForm($templateForm = false)
{
    echo FN_HtmlLogoutForm($templateForm);
}

/**
 *
 * @global  $_FN
 * @param string $fnuser
 * @param string $fnpwd
 * @return bool
 */
function FN_VerifyUserPassword($fnuser, $fnpwd)
{
    global $_FN;
    if (!empty($_FN['FN_VerifyUserPassword']) && $_FN['FN_VerifyUserPassword'] != "FN_VerifyUserPassword" && function_exists($_FN['FN_VerifyUserPassword']))
    {
        return $_FN['FN_VerifyUserPassword']($fnuser, $fnpwd);
    }
    if (!function_exists("password_hash") || !function_exists("password_verify"))
    {
        die("functions password_hash password_verify dont exists ");
    }
    $us = FN_GetUser($fnuser);
    $passwd = !empty($us['passwd']) ? $us['passwd'] : "";
    if ($passwd === "" || $fnpwd === "")
        return false;
    $info = password_get_info($passwd);
    //----modern hash: only password_verify (no pass-the-hash)---------------->
    if (!empty($info['algo']))
    {
        if (!password_verify($fnpwd, $passwd))
            return false;
        if (password_needs_rehash($passwd, FN_AUTH_METHOD, array('cost' => FN_AUTH_COST)))
        {
            FN_UpdateUser($fnuser, array(), $fnpwd);
        }
        return true;
    }
    //----modern hash: only password_verify (no pass-the-hash)----------------<
    //----legacy: md5, crypt() or plaintext, rehashed at the first login------>
    if (preg_match('/^[0-9a-f]{32}$/i', $passwd))
    {
        $ok = hash_equals(strtolower($passwd), md5($fnpwd));
    }
    elseif ($passwd[0] == '$')
    {
        $ok = password_verify($fnpwd, $passwd);
    }
    else
    {
        $ok = hash_equals($passwd, (string) $fnpwd);
    }
    if ($ok)
    {
        FN_UpdateUser($fnuser, array(), $fnpwd);
        FN_Log("User $fnuser: legacy password rehashed");
    }
    //----legacy: md5, crypt() or plaintext, rehashed at the first login------<
    return $ok;
}

/**
 *
 * @global  $_FN
 * @staticvar array $usercache
 * @param string $user
 * @param bool $usecache
 * @return array
 */
function FN_GetUser($user, $usecache = array())
{
    if ($user == "")
        return null;
    static $usercache = array();
    global $_FN;
    if ($usecache)
    {
        if ($usercache && is_array($usercache) && isset($usercache[$user]))
            return $usercache[$user];
    }
    $table = FN_GetUserForm();
    $UserValues = $table->xmltable->GetRecordByPrimaryKey($user);
    // false: the users table could not be read (database error), see FN_ManageLogin()
    $_FN['user_read_error'] = ($UserValues === false);
    if (is_array($UserValues) && $UserValues['level'] === "")
        $UserValues['level'] = "0";
    if (empty($UserValues['username']))
        return null;
    $_FN['uservalues'] = $usercache[$user] = $UserValues;
    return $usercache[$user];
}

/**
 *
 * @global  $_FN
 * @staticvar array $usercache
 * @param string $user
 * @param bool $usecache
 * @return int
 */
function FN_GetUserLevel($user, $usecache = true)
{
    $uservalues = FN_GetUser($user, $usecache);
    if (!isset($uservalues['level']))
        return -1;
    return $uservalues['level'];
}

/**
 *
 * @param string $user
 * @return string
 */
function FN_GetPassword($user)
{
    $uservalues = FN_GetUser($user, false);
    if (!isset($uservalues['passwd']))
        return null;
    $pass = $uservalues['passwd'];
    return $pass;
}

/**
 * 
 * @global type $_FN
 * @global type $_FN
 * @param type $fnuser
 * @param type $rememberlogin
 * @return boolean
 */
function FN_Login($fnuser, $rememberlogin = false)
{
    global $_FN;
    $password = Fn_GetPassword($fnuser);
    $us = FN_GetUser($fnuser, false);
    if (is_array($us) && $us['active'] == 1)
    {
//---------------------url cookie---------------------------------------------->
        global $_FN;
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
        // setcookie() is a no-op in CLI mode (no HTTP context)
        if (empty($_FN['consolemode']))
        {
            $remember_days = !empty($_FN['remember_login_days']) ? intval($_FN['remember_login_days']) : 30;
            $expires = (empty($_FN['remember_login']) || $rememberlogin == "")
                ? 0
                : time() + $remember_days * 86400;
            $cookieOpts = [
                'expires'  => $expires,
                'path'     => $_FN['urlcookie'],
                'secure'   => FN_IsHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            FN_SetCookie("fnuser", $fnuser, $cookieOpts);
            FN_SetCookie("secid",  FN_GetSecid($fnuser, $password), $cookieOpts);
            //"remember me" is kept in a cookie: the cookies renewed after the save of the profile keep the same duration
            if ($expires)
                FN_SetCookie("fnremember", "1", $cookieOpts);
            else
                FN_SetCookie("fnremember", "", ['expires' => 1] + $cookieOpts);
        }
        FN_Log("User $fnuser login (function FN_Login).");
        $_FN['user'] = $_COOKIE['fnuser'] = $fnuser;
        $_COOKIE['secid'] = FN_GetSecid($fnuser, $password);

        return true;
    }
    else
    {
        $_FN['login_error'] = "user is not active";
        return false;
    }
}

/**
 *
 * @global  $_FN
 * @param string $fnuser
 */
function FN_Logout()
{
    global $_FN;
    $_FN['user'] = "";
    unset($_COOKIE['fnuser']);
    unset($_COOKIE['secid']);
    unset($_COOKIE['fnremember']);

    // setcookie() is a no-op in CLI mode
    if (!empty($_FN['consolemode'])) return;

//---------------------url cookie---------------------------------------------->
    // Determine the path used by this application
    $urlcookie = $_FN['urlcookie'] ?? '';
    if (empty($urlcookie))
    {
        $self      = FN_GetParam("PHP_SELF", $_SERVER);
        $urlcookie = rtrim(pathinfo($self, PATHINFO_DIRNAME), '/\\') . '/';
        $urlcookie = str_replace("\\", "/", $urlcookie);
        if ($urlcookie == "" || $urlcookie == "/" || $urlcookie == "//")
            $urlcookie = "/";
    }
//---------------------url cookie----------------------------------------------<

    $clearOpts = ['expires' => 1, 'secure' => FN_IsHttps(), 'httponly' => true, 'samesite' => 'Lax'];
    foreach ([$urlcookie, '/'] as $path) {
        FN_SetCookie("fnuser", "", $clearOpts + ['path' => $path]);
        FN_SetCookie("secid",  "", $clearOpts + ['path' => $path]);
        FN_SetCookie("fnremember", "", $clearOpts + ['path' => $path]);
    }
}

/**
 * deleta user
 * @param string $user
 */
function FN_DeleteUser($user)
{
    global $_FN;
    if ($user != "")
    {
        $table = FN_GetUserForm();
        $uservalues = FN_GetUser($user, false);
        $table->xmltable->DelRecord($user);
        if (function_exists("FN_OnDeleteUser"))
        {
            FN_OnDeleteUser($uservalues);
        }
        //folder with the files of the user: only the folder named as the user right inside fn_users (no "..", "." or links)
        $userdir = realpath("{$_FN['datadir']}/fndatabase/fn_users/" . $user);
        if ($userdir && is_dir($userdir) && basename($userdir) == $user && dirname($userdir) == realpath("{$_FN['datadir']}/fndatabase/fn_users"))
        {
            FN_RemoveDir($userdir);
        }
    }
}

/**
 * Tell whether a password is the one stored (any of the stored formats).
 *
 * @param string $password plain password
 * @param string $stored stored hash (or legacy md5 / plain value)
 * @return bool
 */
function FN_SamePassword($password, $stored)
{
    $stored = "$stored";
    if ($password === "" || $stored === "")
        return false;
    $info = password_get_info($stored);
    if (!empty($info['algo']) || $stored[0] == '$')
        return password_verify($password, $stored);
    if (preg_match('/^[0-9a-f]{32}$/i', $stored))
        return hash_equals(strtolower($stored), md5($password));
    return hash_equals($stored, "$password");
}

/**
 * @param string $user
 * @param array $newvalues
 */
function FN_UpdateUser($user, $newvalues, $password = "")
{

    if ($user != "")
    {
        if (!isset($newvalues['username']))
            $newvalues['username'] = $user;
        if ($password != "")
            $newvalues['passwd'] = $password;
        if (isset($newvalues['passwd']) && $newvalues['passwd'] == "")
        {
            unset($newvalues['passwd']);
        }
        $table = FN_GetUserForm();
        //a new password revokes the API key (MCP and API clients): a key never expires by itself.
        //The same password stored again (rehash at the login) keeps the key
        if ($password != "" && !isset($newvalues['apikey']) && isset($table->xmltable->fields['apikey']))
        {
            $oldvalues = $table->xmltable->GetRecordByPrimaryKey($newvalues['username']);
            if (!empty($oldvalues['apikey']) && !FN_SamePassword($password, $oldvalues['passwd']))
            {
                $newvalues['apikey'] = "";
                if (isset($table->xmltable->fields['apikey_enc']))
                    $newvalues['apikey_enc'] = "";
                FN_LogEvent("API key revoked for user $user: password changed", "apikey");
            }
        }
        //the registration code (32 hex) is needed only until the first activation: a user activated
        //without it (control center, api) or deactivated after the activation must not keep it,
        //otherwise the code would activate the user again
        $active_field = empty($table->fieldname_active) ? "active" : $table->fieldname_active;
        if (isset($newvalues[$active_field]) && !isset($newvalues['rnd']))
        {
            $oldvalues = $table->xmltable->GetRecordByPrimaryKey($newvalues['username']);
            if (isset($oldvalues['rnd']) && preg_match('/^[0-9a-f]{32}$/', $oldvalues['rnd']) && (!empty($newvalues[$active_field]) || !empty($oldvalues[$active_field])))
            {
                $newvalues['rnd'] = "";
            }
        }
        //dprint_r($newvalues);
        $newvalues = $table->UpdateRecord($newvalues);
        if ($newvalues && function_exists("FN_OnUpdateUser"))
        {
            FN_OnUpdateUser($newvalues, $password);
        }
        FN_GetUser($user, false);
        return $newvalues;
    }
    return false;
}

/**
 *
 * @param array $newvalues
 * @return bool
 */
function FN_AddUser($newvalues, $password = "")
{
    global $_FN;
    //a username made only of dots ("." or "..") would name the folder of the users or its parent
    if ($newvalues['username'] != "" && trim($newvalues['username'], ".") != "")
    {
        if (empty($_FN['username_case_sensitive']))
        {
            $newvalues['username'] = strtolower($newvalues['username']);
        }
        $table = FN_GetUserForm();
        $newvalues['registrationdate'] = FN_Now();
        if ($password != "")
            $newvalues['passwd'] = $password;
        if (isset($newvalues['passwd']) && $newvalues['passwd'] == "")
        {
            unset($newvalues['passwd']);
        }
        //InsertRecord returns the saved record (the xmlphp driver returns an error string when the insert fails)
        if (is_array($table->InsertRecord($newvalues)))
        {
            if (function_exists("FN_OnAddUser"))
            {
                FN_OnAddUser($newvalues, $password);
            }
            return true;
        }
    }
    return false;
}

/**
 *
 * @global array $_FN
 * @return bool 
 */
function FN_CheckUser()
{
    global $_FN;
    $secid = FN_GetParam("secid", $_COOKIE);
    $UserValues = FN_GetUser($_FN['user']);
    if (empty($secid) || empty($UserValues['passwd']))
        return false;
    //a user deactivated after the login is logged out
    if (!isset($UserValues['active']) || $UserValues['active'] != 1)
        return false;
    $RequiredSecid = FN_GetSecid($_FN['user'], $UserValues['passwd']);
    if (hash_equals($RequiredSecid, (string) $secid))
        return true;
    return false;
}

/**
 *
 * @global array $_FN
 * @param string $user
 * @return string 
 */
function FN_GetUserImage($user)
{
    global $_FN;
    $user = FN_GetUser($user);
    if (!empty($user['avatar']))
    {
        $image = urldecode(FN_GetParam("avatar", $user, "html"));
        $image = "{$_FN['datadir']}/{$_FN['database']}/fn_users/{$user['username']}/avatar/$image";
    }
    elseif (!empty($user['avatarimage']))
    {
        $image = urldecode($user['avatarimage']);
        $image = "{$_FN['datadir']}/{$_FN['database']}/fn_avatars/$image/filename/$image";
    }
    else
    {
        if (!empty($_FN['userimage']))
        {
            return $_FN['userimage'];
        }
        $image = FN_FromTheme("images/user.png", false);
    }
    return $_FN['siteurl'] . $image;
}

/**
 *
 * @global array $_FN
 * @param string $user
 * @return string 
 */
function FN_HtmlUserImage($user)
{
    global $_FN;
    $imagesrc = FN_GetUserImage($user);
    $image = "<img alt=\"\" src=\"$imagesrc\" />";
    return $image;
}

/**
 * 
 * @param type $pasword
 */
function FN_PasswordVerifyConstraints($password)
{

    if (function_exists("FN_PasswordVerifyConstraints_overwrite"))
    {

        return FN_PasswordVerifyConstraints_overwrite($password);
    }
//    if (!preg_match('/^[0-9A-Za-z!@#$%]{3,12}$/',$password))
    if (false !== strstr($password, " "))
    {
        return 'the password does not meet the requirements';
    }
    return "";
}

function FN_ManageLoginWithOpenAuthentication()
{

    global $_FN;
    $session_provider = FN_GetSessionValue("session_provider");
    $session_access_token = FN_GetSessionValue("session_access_token");
    $_FN['userimage'] = FN_GetSessionValue("session_userimage");
    // Provider configurations
    $providers = FN_GetOpenAuthProviders();
    if (!$providers)
    {
        return;
    }
    $op = FN_GetParam("fnlogin", $_GET);
    if ($op == "logout")
    {
        FN_SetSessionValue("session_access_token", "");
        FN_SetSessionValue("session_provider", "");
        FN_SetSessionValue("session_userimage", "");
        $providerId = "";
        $session_provider = "";
        $session_access_token = "";
        FN_Logout();
        return;
    }
    $providerId = FN_GetParam("fnloginprovider", $_GET);
    //$redirectUri = $_FN['siteurl'] . "?fnloginprovider=$providerId";
    $redirectUri = $_FN['siteurl'] . "{$_FN['selfscript']}?fnloginprovider=$providerId";
//dprint_r($redirectUri);
    $found_provider = false;
    foreach ($providers as $provider)
    {
        if ($providerId == $provider['id'])
        {
            $found_provider = $provider;
            break;
        }
    }
    if (!$found_provider)
    {
        return;
    }
    $config = $found_provider;

    // Check if the user is already authenticated
    if (empty($session_access_token))
    {
        // If not authenticated, start the OAuth flow
        if (!isset($_GET['code']))
        {
            //error from the provider (access_denied when the user does not allow the login): no new authorization request, it would send the user back to the provider
            if (isset($_GET['error']))
            {
                FN_SetSessionValue("session_oauth_state", "");
                FN_Log("OAuth login with provider {$config['id']} not completed: " . substr(preg_replace('/[^A-Za-z0-9_.-]/', "", FN_GetParam("error", $_GET)), 0, 64));
                return;
            }
            // Redirect to provider's authorization page
            //random state kept in the session, the provider sends it back with the code
            $state = bin2hex(random_bytes(16));
            FN_SetSessionValue("session_oauth_state", $state);
            $params = array(
                'client_id' => $config['client_id'],
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => $config['scope'],
                'state' => $state,
            );

            $authRedirect = $config['auth_url'] . '?' . http_build_query($params);
            header('Location: ' . $authRedirect);
            exit;
        }
        else
        {
            //the code must come back with the state of this session (no login CSRF), each state is used once
            $state = FN_GetSessionValue("session_oauth_state");
            FN_SetSessionValue("session_oauth_state", "");
            if (empty($state) || !is_string($state) || !hash_equals($state, FN_GetParam("state", $_GET)))
            {
                FN_Log("OAuth login with provider {$config['id']} refused: invalid state");
                return;
            }
            // Exchange the authorization code for an access token
            $postData = array(
                'code' => $_GET['code'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            );

            $ch = curl_init($config['token_url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            curl_close($ch);
            $tokenData = json_decode($response, true);
            if (isset($tokenData['access_token']))
            {
                $session_access_token = $tokenData['access_token'];
                $session_provider = $provider['id'];
                FN_SetSessionValue("session_access_token", $session_access_token);
                FN_SetSessionValue("session_provider", $session_provider);
            }
            else
            {
                return;
            }
        }
    }

// Get user information using the access token
    $ch = curl_init($config['userinfo_url']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $session_access_token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $userInfo = json_decode($response, true);
    FN_SetSessionValue("session_provider", $session_provider);
    $user_email = isset($userInfo['email']) ? $userInfo['email'] : "";
    //when the provider says whether the email is verified (email_verified in OpenID Connect, verified_email in Google v2) it must be true
    foreach (array("email_verified", "verified_email") as $key)
    {
        if ($user_email && is_array($userInfo) && array_key_exists($key, $userInfo) && !in_array($userInfo[$key], array(true, "true", 1, "1"), true))
        {
            FN_Log("OAuth login with provider {$config['id']} refused: email $user_email not verified");
            $user_email = "";
        }
    }
    $user_image = !empty($userInfo['picture']) ? $userInfo['picture'] : "";
    if (!$user_image || is_array($user_image))
    {
        $user_image = !empty($userInfo['picture']['data']['url']) ? $userInfo['picture']['data']['url'] : "";
    }
    if ($user_email)
    {
        $users = FN_GetUsers(array("email" => $user_email));
        //in the filter _ and % are wildcards (LIKE): keep only this email, ignoring the case
        $found = array();
        if (is_array($users))
        {
            foreach ($users as $user)
            {
                if (isset($user['email']) && strcasecmp("{$user['email']}", "$user_email") == 0)
                {
                    $found[] = $user;
                }
            }
        }
        $users = $found;
        if ($users)
        {
            $uservalues = $users[0];
            FN_Login($uservalues['username']);
        }
        else
        {
            if ($_FN['enable_registration'])
            {
                $uservalues = array("username" => $user_email, "email" => $user_email, "active" => 1);
                //FN_AddUser saves the username in lowercase: the login uses the saved name
                if (empty($_FN['username_case_sensitive']))
                {
                    $uservalues['username'] = strtolower($uservalues['username']);
                }
                //no login when the user is not created (eg. another user already has this username)
                if (FN_AddUser($uservalues, generateSecurePassword()))
                {
                    FN_Login($uservalues['username']);
                }
                else
                {
                    FN_Log("OAuth login with provider {$config['id']} refused: user $user_email not created");
                }
            }
        }
        $_FN['userimage'] = $user_image;
        FN_SetSessionValue("session_userimage", $user_image);
        //go back to the page that started the login (eg. the checkout), if any.
        //the url is stored server side in the session, it never comes from the request
        $returnurl = FN_GetSessionValue("session_oauth_returnurl");
        if (!empty($_FN['user']) && $returnurl)
        {
            FN_SetSessionValue("session_oauth_returnurl", "");
            //only to pages of this site, never an open redirect
            if (FN_IsInternalUrl($returnurl))
            {
                FN_Redirect($returnurl);
            }
        }
        return;
    }
    else
    {
        FN_Logout();
    }
}

function generateSecurePassword($length = 12)
{
    // Define the character sets to use in the password
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $specialChars = '!@#$%^&*()-_=+<>?';

    // Combine all character sets
    $allChars = $lowercase . $uppercase . $numbers . $specialChars;

    // Shuffle the characters to ensure randomness
    $shuffledChars = str_shuffle($allChars);

    // Initialize the password variable
    $password = '';

    // Generate a password of the specified length
    for ($i = 0; $i < $length; $i++)
    {
        // Select a random character from the shuffled character set (random_int is cryptographically secure, rand is not)
        $randomIndex = random_int(0, strlen($shuffledChars) - 1);
        $password .= $shuffledChars[$randomIndex];
    }

    return $password;
}

//------------------------------- API key ------------------------------------->

/**
 * Make sure the installed fn_users table definition contains the apikey, apikey_date
 * and apikey_enc fields.
 * Installations created before the fields existed copied an older definition:
 * once the field is appended, the SQL drivers add the column automatically.
 *
 * @global array $_FN
 * @return bool true when the field is available
 */
function FN_EnsureUserApiKeyField()
{
    global $_FN;
    static $checked = null;
    if ($checked !== null)
        return $checked;
    $checked = false;
    $form = FN_GetUserForm();
    $missing = array();
    foreach (array("apikey", "apikey_date", "apikey_enc") as $name)
    {
        if (!isset($form->xmltable->fields[$name]))
            $missing[] = $name;
    }
    if (count($missing) == 0)
    {
        $checked = true;
        return true;
    }
    $definition = "{$_FN['datadir']}/{$_FN['database']}/fn_users.php";
    if (!file_exists($definition) || !is_writable($definition))
        return isset($form->xmltable->fields['apikey']);
    $xml = file_get_contents($definition);
    $labels = array("apikey" => "API key", "apikey_date" => "API key date", "apikey_enc" => "API key (encrypted)");
    $sizes = array("apikey" => 128, "apikey_date" => 32, "apikey_enc" => 255);
    $added = false;
    foreach ($missing as $name)
    {
        if (strpos($xml, "<name>$name</name>") !== false)
            continue;
        $field = "    <field>\n"
            . "        <name>$name</name>\n"
            . "        <frm_i18n>{$labels[$name]}</frm_i18n>\n"
            . "        <frm_show>0</frm_show>\n"
            . "        <frm_allowupdate>0</frm_allowupdate>\n"
            . "        <size>{$sizes[$name]}</size>\n"
            . "    </field>\n";
        $pos = strrpos($xml, "</tables>");
        if ($pos === false)
            return false;
        $xml = substr($xml, 0, $pos) . $field . substr($xml, $pos);
        $added = true;
    }
    if ($added && false === FN_Write($xml, $definition))
        return false;
    // the fields are loaded by the table definition at the next request
    $checked = true;
    return true;
}

/**
 * Read the API key sent by the client in the HTTP headers.
 * Accepted headers: "Authorization: Bearer <key>" and "X-Api-Key: <key>".
 * The MCP endpoint (index.php?fnapp=mcp) also accepts the key in the URL
 * (&apikey=<key>) for the clients that can be configured only with a URL;
 * the headers win when both are sent. The rest of the site ignores the
 * parameter, so a key in a link does not log anyone in on normal pages.
 *
 * @return string the API key or an empty string
 */
function FN_GetRequestApiKey()
{
    $authorization = "";
    foreach (array("HTTP_AUTHORIZATION", "REDIRECT_HTTP_AUTHORIZATION") as $k)
    {
        if (!empty($_SERVER[$k]))
        {
            $authorization = $_SERVER[$k];
            break;
        }
    }
    if ($authorization == "" && function_exists("apache_request_headers"))
    {
        $headers = apache_request_headers();
        if (is_array($headers))
        {
            foreach ($headers as $k => $v)
            {
                if (strtolower($k) == "authorization")
                    $authorization = $v;
            }
        }
    }
    if ($authorization != "" && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authorization, $m))
    {
        return $m[1];
    }
    if (!empty($_SERVER['HTTP_X_API_KEY']))
    {
        return trim($_SERVER['HTTP_X_API_KEY']);
    }
    if (isset($_GET['fnapp']) && $_GET['fnapp'] === "mcp" && !empty($_GET['apikey']) && is_string($_GET['apikey']))
    {
        return trim($_GET['apikey']);
    }
    return "";
}

/**
 * Value stored in fn_users.apikey for a key: "v1$<sha256 of the key>", used to
 * find the owner of a key. The keys are random (48 hex digits), so a plain
 * sha256 is enough. The creation date goes in fn_users.apikey_date, a copy
 * encrypted with the site secret in fn_users.apikey_enc (FN_ApiKeyEncrypt).
 *
 * @param string $apikey
 * @return string
 */
function FN_ApiKeyHash($apikey)
{
    return "v1$" . hash("sha256", trim("$apikey"));
}

/**
 * Encryption key of the stored copies of the API keys, derived from the site
 * secret (misc/secret.key.php): a copy of the users table without that file
 * gives no usable key.
 *
 * @return string 32 raw bytes
 */
function FN_ApiKeyCipherKey()
{
    return hash_hmac("sha256", "finis apikey encryption", FN_GetSiteSecret(), true);
}

/**
 * Encrypt an API key for fn_users.apikey_enc: "e1$" + base64(iv, tag, data),
 * AES-256-GCM. Without openssl the key is not stored at all ("" returned): it
 * still works, but it cannot be shown again.
 *
 * @param string $apikey
 * @return string
 */
function FN_ApiKeyEncrypt($apikey)
{
    if (!function_exists("openssl_encrypt") || !in_array("aes-256-gcm", array_map("strtolower", openssl_get_cipher_methods())))
        return "";
    try
    {
        $iv = random_bytes(12);
    }
    catch (Exception $e)
    {
        return "";
    }
    $tag = "";
    $data = openssl_encrypt(trim("$apikey"), "aes-256-gcm", FN_ApiKeyCipherKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($data === false || strlen($tag) != 16)
        return "";
    return "e1$" . base64_encode($iv . $tag . $data);
}

/**
 * Decrypt a value written by FN_ApiKeyEncrypt().
 *
 * @param string $stored
 * @return string the key, "" when the value is empty or cannot be decrypted
 */
function FN_ApiKeyDecrypt($stored)
{
    if (!preg_match('#^e1\$([A-Za-z0-9+/=]+)$#', trim("$stored"), $m) || !function_exists("openssl_decrypt"))
        return "";
    $raw = base64_decode($m[1], true);
    if ($raw === false || strlen($raw) <= 28)
        return "";
    $apikey = openssl_decrypt(substr($raw, 28), "aes-256-gcm", FN_ApiKeyCipherKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $apikey === false ? "" : $apikey;
}

/**
 * Values to store for a new key (or for a legacy key being migrated).
 *
 * @param string $apikey
 * @return array fields of fn_users
 */
function FN_ApiKeyRecord($apikey)
{
    $form = FN_GetUserForm();
    $values = array("apikey" => FN_ApiKeyHash($apikey));
    if (isset($form->xmltable->fields['apikey_date']))
        $values['apikey_date'] = FN_Now();
    if (isset($form->xmltable->fields['apikey_enc']))
        $values['apikey_enc'] = FN_ApiKeyEncrypt($apikey);
    return $values;
}

/**
 * State of the API key of a user, without the key.
 *
 * @param string|array $user username or user values
 * @return array exists (bool), created ("Y-m-d H:i:s" or ""), legacy (bool: key
 *         stored in clear by a version before the hashes), viewable (bool: an
 *         administrator can show it, FN_ApiKeyReveal)
 */
function FN_ApiKeyInfo($user)
{
    $uservalues = is_array($user) ? $user : FN_GetUser($user, false);
    $stored = isset($uservalues['apikey']) ? "{$uservalues['apikey']}" : "";
    $info = array("exists" => $stored != "", "created" => "", "legacy" => false, "viewable" => false);
    if ($stored == "")
        return $info;
    if (preg_match('/^v1\$[a-f0-9]{64}$/', $stored))
    {
        $info['created'] = !empty($uservalues['apikey_date']) ? substr("{$uservalues['apikey_date']}", 0, 16) : "";
        $info['viewable'] = !empty($uservalues['apikey_enc']);
    }
    else
    {
        $info['legacy'] = true;
        $info['viewable'] = true;
    }
    return $info;
}

/**
 * The API key of a user in clear, for the page of the administrators
 * (control center, users_and_groups/mcp): decrypted from fn_users.apikey_enc
 * and checked against the stored hash. A key created when only its hash was
 * stored cannot be read: "" (a new key has to be generated).
 *
 * @param string|array $user username or user values
 * @return string the key or ""
 */
function FN_ApiKeyReveal($user)
{
    $uservalues = is_array($user) ? $user : FN_GetUser($user, false);
    $stored = isset($uservalues['apikey']) ? "{$uservalues['apikey']}" : "";
    if ($stored == "")
        return "";
    $info = FN_ApiKeyInfo($uservalues);
    if ($info['legacy'])
        return $stored;
    $apikey = FN_ApiKeyDecrypt(isset($uservalues['apikey_enc']) ? $uservalues['apikey_enc'] : "");
    if ($apikey == "" || !hash_equals($stored, FN_ApiKeyHash($apikey)))
        return "";
    return $apikey;
}

/**
 * Find the active user owning an API key.
 * Keys are stored hashed (FN_ApiKeyHash); a key stored in clear by a version
 * before the hashes is still accepted and replaced by its hash at the first use.
 *
 * @param string $apikey
 * @return array|null user values
 */
function FN_GetUserByApiKey($apikey)
{
    $apikey = trim("$apikey");
    // keys are hexadecimal strings, refuse anything else before querying
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $apikey))
        return null;
    FN_EnsureUserApiKeyField();
    $hash = FN_ApiKeyHash($apikey);
    $table = FN_GetUserForm();
    $users = FN_GetUsers(array("apikey" => $hash));
    $legacy = false;
    if (!is_array($users) || count($users) != 1)
    {
        $users = FN_GetUsers(array("apikey" => $apikey));
        $legacy = true;
    }
    if (!is_array($users) || count($users) != 1)
        return null;
    $uservalues = reset($users);
    if (empty($uservalues['username']) || empty($uservalues['apikey']))
        return null;
    // the filter of some drivers is a pattern or not case sensitive: compare again
    $stored = "{$uservalues['apikey']}";
    if ($legacy)
    {
        if (!hash_equals($stored, $apikey))
            return null;
    }
    elseif (!hash_equals($stored, $hash))
        return null;
    if (empty($uservalues['active']) || $uservalues['active'] != 1)
        return null;
    if (!FN_UserCanUseApiKey($uservalues))
        return null;
    if ($legacy)
    {
        // the key stays the same for the client, only the storage changes
        $table->xmltable->UpdateRecordBypk(FN_ApiKeyRecord($apikey), $table->xmltable->primarykey, $uservalues['username']);
        $uservalues = FN_GetUser($uservalues['username'], false);
    }
    return $uservalues;
}

/**
 * Tell whether a user may use an API key. By default everybody can; when
 * $_FN['apikey_groups'] is set (comma separated groups) only the administrators
 * and the members of those groups can: the key of the other users is refused
 * and is not shown in the profile.
 *
 * @global array $_FN
 * @param string|array $user username or user values
 * @return bool
 */
function FN_UserCanUseApiKey($user)
{
    global $_FN;
    if (empty($_FN['apikey_groups']))
        return true;
    $uservalues = is_array($user) ? $user : FN_GetUser($user, false);
    if (empty($uservalues['username']))
        return false;
    if (!empty($uservalues['level']) && $uservalues['level'] >= 10)
        return true;
    $usergroups = array_map("trim", explode(",", isset($uservalues['group']) ? $uservalues['group'] : ""));
    foreach (explode(",", $_FN['apikey_groups']) as $group)
    {
        $group = trim($group);
        if ($group != "" && in_array($group, $usergroups))
            return true;
    }
    return false;
}

/**
 * Group of the users allowed to use the write tools of the MCP servers
 * ($_FN['mcp_write_group'], default "mcp_write"); empty = every user.
 *
 * @global array $_FN
 * @return string
 */
function FN_McpWriteGroup()
{
    global $_FN;
    return isset($_FN['mcp_write_group']) ? trim("{$_FN['mcp_write_group']}") : "mcp_write";
}

/**
 * Tell whether a user may use the write tools of the MCP servers: the
 * administrators always, the other users only when they belong to the group
 * FN_McpWriteGroup(). Reading is open to every user allowed to use a key; a
 * client that writes acts on texts it reads (prompt injection), so writing
 * is a choice of the administrator, user by user.
 *
 * @param string|array $user username or user values
 * @return bool
 */
function FN_UserCanWriteByApiKey($user)
{
    $uservalues = is_array($user) ? $user : FN_GetUser($user, false);
    if (empty($uservalues['username']))
        return false;
    if (!empty($uservalues['level']) && $uservalues['level'] >= 10)
        return true;
    $group = FN_McpWriteGroup();
    if ($group == "")
        return true;
    $usergroups = array_map("trim", explode(",", isset($uservalues['group']) ? $uservalues['group'] : ""));
    return in_array($group, $usergroups);
}

/**
 * Add or remove a user from a group.
 *
 * @param string $user
 * @param string $group
 * @param bool $member
 * @return bool
 */
function FN_SetUserGroup($user, $group, $member)
{
    $uservalues = FN_GetUser($user, false);
    $group = trim("$group");
    if ($group == "" || empty($uservalues['username']))
        return false;
    $groups = array();
    foreach (explode(",", isset($uservalues['group']) ? $uservalues['group'] : "") as $g)
    {
        $g = trim($g);
        if ($g != "" && $g != $group && !in_array($g, $groups))
            $groups[] = $g;
    }
    if ($member)
        $groups[] = $group;
    $table = FN_GetUserForm();
    $result = $table->xmltable->UpdateRecordBypk(array("group" => implode(",", $groups)), $table->xmltable->primarykey, $uservalues['username']);
    FN_GetUser($uservalues['username'], false);
    return is_array($result);
}

/**
 * Authenticate the current request with an API key: the user becomes the
 * current user ($_FN['user']) exactly as after a cookie login, but no cookie
 * is sent and no session is created. Operations performed afterwards are
 * logged and authorized with the identity of the key owner.
 *
 * @global array $_FN
 * @param string $apikey
 * @return bool
 */
function FN_LoginByApiKey($apikey)
{
    global $_FN;
    $uservalues = FN_GetUserByApiKey($apikey);
    if (!$uservalues)
    {
        $_FN['login_error'] = FN_Translate("invalid API key");
        return false;
    }
    $_FN['user'] = $uservalues['username'];
    $_FN['uservalues'] = $uservalues;
    $_FN['auth_method'] = "apikey";
    FN_GetUser($_FN['user'], false);
    return true;
}

/**
 * Generate a new random API key for a user and store its hash and its
 * encrypted copy in fn_users. The key replaces the previous one, so old
 * clients stop working. Afterwards only the administrators can show it again
 * (FN_ApiKeyReveal).
 *
 * @global array $_FN
 * @param string $user username
 * @return string the new API key, empty string on failure
 */
function FN_GenerateApiKey($user)
{
    global $_FN;
    $uservalues = FN_GetUser($user, false);
    if ($user == "" || !$uservalues)
        return "";
    FN_EnsureUserApiKeyField();
    try
    {
        $apikey = bin2hex(random_bytes(24));
    }
    catch (Exception $e)
    {
        //never a predictable key: no key at all
        FN_LogEvent("API key not generated for user $user: " . $e->getMessage(), "apikey");
        return "";
    }
    $table = FN_GetUserForm();
    $result = $table->xmltable->UpdateRecordBypk(FN_ApiKeyRecord($apikey), $table->xmltable->primarykey, $uservalues['username']);
    if (!is_array($result))
        return "";
    FN_GetUser($user, false);
    FN_LogEvent("API key generated for user $user" . ($_FN['user'] != "" && $_FN['user'] != $user ? " by {$_FN['user']}" : ""), "apikey");
    return $apikey;
}

/**
 * Remove the API key of a user: the clients that use it stop working.
 *
 * @global array $_FN
 * @param string $user
 * @return bool
 */
function FN_RevokeApiKey($user)
{
    global $_FN;
    $uservalues = FN_GetUser($user, false);
    if ($user == "" || !$uservalues)
        return false;
    FN_EnsureUserApiKeyField();
    $table = FN_GetUserForm();
    $values = array("apikey" => "");
    foreach (array("apikey_date", "apikey_enc") as $field)
    {
        if (isset($table->xmltable->fields[$field]))
            $values[$field] = "";
    }
    $result = $table->xmltable->UpdateRecordBypk($values, $table->xmltable->primarykey, $uservalues['username']);
    FN_GetUser($user, false);
    if (is_array($result))
        FN_LogEvent("API key revoked for user $user" . ($_FN['user'] != "" && $_FN['user'] != $user ? " by {$_FN['user']}" : ""), "apikey");
    return is_array($result);
}

/**
 * Kept for the applications written before the hashed keys: the key of the
 * user (FN_ApiKeyReveal, "" when it cannot be read); with $generate a key is
 * created only when the user has none, an existing key is never replaced
 * (the OAuth consent relies on it).
 *
 * @param string $user username
 * @param bool $generate create a key when the user has none
 * @return string the API key or an empty string
 * @deprecated use FN_ApiKeyInfo(), FN_ApiKeyReveal() and FN_GenerateApiKey()
 */
function FN_GetUserApiKey($user, $generate = false)
{
    $info = FN_ApiKeyInfo($user);
    if ($info['exists'])
        return FN_ApiKeyReveal($user);
    if ($generate)
        return FN_GenerateApiKey($user);
    return "";
}

/**
 * Anti CSRF token for the forms that generate or revoke keys, bound to the
 * logged user and derived from server side secrets only (no session needed).
 *
 * @global array $_FN
 * @return string
 */
function FN_ApiKeyFormToken()
{
    global $_FN;
    $uservalues = FN_GetUser($_FN['user'], false);
    $form = FN_GetUserForm();
    $pwfield = empty($form->fieldname_password) ? "passwd" : $form->fieldname_password;
    $secret = (isset($uservalues[$pwfield]) ? $uservalues[$pwfield] : "") . "|" . __FILE__ . "|" . (isset($_FN['datadir']) ? realpath($_FN['datadir']) : "");
    return hash_hmac("sha256", "apikey|" . $_FN['user'], $secret);
}

/**
 * MCP endpoints of this site: the FINIS CMS server and the ones declared by
 * the application in $_FN['mcp_endpoints'] (name => url, relative to the site
 * root or absolute).
 *
 * @global array $_FN
 * @return array name => absolute url
 */
function FN_GetMcpEndpoints()
{
    global $_FN;
    $siteurl = isset($_FN['siteurl']) ? $_FN['siteurl'] : "";
    if ($siteurl != "" && substr($siteurl, -1) != "/")
        $siteurl .= "/";
    $out = array();
    if (!empty($_FN['mcp_endpoints']) && is_array($_FN['mcp_endpoints']))
    {
        foreach ($_FN['mcp_endpoints'] as $name => $url)
            $out[$name] = preg_match('#^https?://#i', $url) ? $url : $siteurl . ltrim($url, "/");
    }
    $out['cms'] = FN_GetMcpUrl();
    return $out;
}

/**
 * Shared logic of the profile editors (control center and login module):
 * handles the generate / revoke buttons and fills the template variables of
 * the API key block. The key is shown once, only to its owner, when generated.
 *
 * Template variables: apikey_allowed (the block is shown), apikey_exists,
 * apikey_status (text), apikey_new (the key just generated, once),
 * apikey_message, apikey_token (hidden field), apikey_write (bool),
 * mcp_url (CMS server), mcp_endpoints (array name => url).
 *
 * @global array $_FN
 * @param string $user username being edited
 * @param array $tplvars template variables (modified by reference)
 * @return void
 */
function FN_ProfileApiKey($user, &$tplvars)
{
    global $_FN;
    $tplvars['apikey'] = "";
    $tplvars['apikey_allowed'] = "";
    $tplvars['apikey_exists'] = "";
    $tplvars['apikey_status'] = "";
    $tplvars['apikey_new'] = "";
    $tplvars['apikey_message'] = "";
    $tplvars['apikey_token'] = "";
    $tplvars['apikey_write'] = "";
    $tplvars['mcp_url'] = FN_GetMcpUrl();
    $tplvars['mcp_endpoints'] = array();
    if ($user == "" || $user != $_FN['user'])
        return;
    if (!FN_UserCanUseApiKey($user))
        return;
    $tplvars['apikey_allowed'] = true;
    $tplvars['apikey_token'] = FN_ApiKeyFormToken();
    $tplvars['mcp_endpoints'] = array();
    foreach (FN_GetMcpEndpoints() as $name => $url)
        $tplvars['mcp_endpoints'][] = array("name" => $name, "url" => $url);
    $action = "";
    foreach (array("generate_apikey", "regenerate_apikey", "revoke_apikey") as $button)
    {
        if (!empty($_POST[$button]))
            $action = $button;
    }
    if ($action != "")
    {
        // the buttons are inside the profile form: do not save the profile
        unset($_POST['updateuser']);
        if (!hash_equals(FN_ApiKeyFormToken(), (string) FN_GetParam("apikey_token", $_POST, "html")))
            $tplvars['apikey_message'] = FN_Translate("invalid request, reload the page and try again");
        elseif ($action == "revoke_apikey")
            $tplvars['apikey_message'] = FN_RevokeApiKey($user) ? FN_Translate("the API key has been revoked") : FN_Translate("error");
        else
        {
            $apikey = FN_GenerateApiKey($user);
            if ($apikey != "")
            {
                $tplvars['apikey_new'] = $apikey;
                $tplvars['apikey_message'] = FN_Translate("copy the key now: it is shown only this time");
            }
            else
                $tplvars['apikey_message'] = FN_Translate("error");
        }
    }
    $info = FN_ApiKeyInfo($user);
    $tplvars['apikey_exists'] = $info['exists'] ? true : "";
    if (!$info['exists'])
        $tplvars['apikey_status'] = FN_Translate("no API key");
    elseif ($info['created'] != "")
        $tplvars['apikey_status'] = FN_Translate("API key created on") . " " . $info['created'];
    else
        $tplvars['apikey_status'] = FN_Translate("API key present");
    $tplvars['apikey_write'] = FN_UserCanWriteByApiKey($user) ? true : "";
}

/**
 * Return the absolute URL of the MCP endpoint of this site.
 *
 * @global array $_FN
 * @return string
 */
function FN_GetMcpUrl()
{
    global $_FN;
    $siteurl = isset($_FN['siteurl']) ? $_FN['siteurl'] : "";
    if ($siteurl != "" && substr($siteurl, -1) != "/")
        $siteurl .= "/";
    return $siteurl . "index.php?fnapp=mcp";
}
