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
            FN_Login($fnuser, $rememberlogin);
            $justLoggedIn = true;
        }
        else
        {
            FN_Logout();
            $_FN['login_error'] = FN_Translate("error username or password");
        }
    }
    $_FN['user'] = FN_GetParam("fnuser", $_COOKIE);
    // Skip the session check on the very request that performed login:
    // FN_Login() already set the cookies; re-checking immediately would race
    // against the browser not yet having sent back the new cookie.
    if (!$justLoggedIn && (!FN_CheckUser() || $fnlogin == "logout"))
    {
        FN_Logout();
    }
    //-------------------API key (MCP and API clients)------------------------>
    if ($_FN['user'] == "")
    {
        $apikey = FN_GetRequestApiKey();
        if ($apikey != "")
        {
            FN_LoginByApiKey($apikey);
        }
    }
    //-------------------API key (MCP and API clients)------------------------<
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
    $querystring = str_replace("&", "&amp;", $querystring);
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
    $querystring = str_replace("&", "&amp;", $querystring);
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
            setcookie("fnuser", $fnuser, $cookieOpts);
            setcookie("secid",  FN_GetSecid($fnuser, $password), $cookieOpts);
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
        setcookie("fnuser", "", $clearOpts + ['path' => $path]);
        setcookie("secid",  "", $clearOpts + ['path' => $path]);
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
 * Make sure the installed fn_users table definition contains the apikey field.
 * Installations created before the field existed copied an older definition:
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
    if (isset($form->xmltable->fields['apikey']))
    {
        $checked = true;
        return true;
    }
    $definition = "{$_FN['datadir']}/{$_FN['database']}/fn_users.php";
    if (!file_exists($definition) || !is_writable($definition))
        return false;
    $xml = file_get_contents($definition);
    if (strpos($xml, "<name>apikey</name>") === false)
    {
        $field = "    <field>\n"
            . "        <name>apikey</name>\n"
            . "        <frm_i18n>API key</frm_i18n>\n"
            . "        <frm_show>0</frm_show>\n"
            . "        <frm_allowupdate>0</frm_allowupdate>\n"
            . "        <size>128</size>\n"
            . "    </field>\n";
        $pos = strrpos($xml, "</tables>");
        if ($pos === false)
            return false;
        $xml = substr($xml, 0, $pos) . $field . substr($xml, $pos);
        if (false === FN_Write($xml, $definition))
            return false;
    }
    // the field is loaded by the table definition at the next request
    $checked = true;
    return true;
}

/**
 * Read the API key sent by the client in the HTTP headers.
 * Accepted headers: "Authorization: Bearer <key>" and "X-Api-Key: <key>".
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
    return "";
}

/**
 * Generate a new random API key for a user and store it in fn_users.
 * The key replaces the previous one, so old clients stop working.
 *
 * @global array $_FN
 * @param string $user username
 * @return string the new API key, empty string on failure
 */
function FN_GenerateApiKey($user)
{
    global $_FN;
    if ($user == "" || !FN_GetUser($user, false))
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
    $result = $table->xmltable->UpdateRecordBypk(array("apikey" => $apikey), $table->xmltable->primarykey, $user);
    if (!is_array($result))
        return "";
    FN_GetUser($user, false);
    FN_LogEvent("API key regenerated for user $user", "apikey");
    return $apikey;
}

/**
 * Return the API key of a user, generating it when it does not exist yet.
 *
 * @param string $user username
 * @param bool $generate create the key when missing
 * @return string the API key or an empty string
 */
function FN_GetUserApiKey($user, $generate = true)
{
    $uservalues = FN_GetUser($user, false);
    if (!$uservalues)
        return "";
    if (!empty($uservalues['apikey']))
        return $uservalues['apikey'];
    if ($generate)
        return FN_GenerateApiKey($user);
    return "";
}

/**
 * Find the active user owning an API key.
 *
 * @param string $apikey
 * @return array|null user values or null when the key is unknown
 */
function FN_GetUserByApiKey($apikey)
{
    $apikey = trim("$apikey");
    // keys are hexadecimal strings, refuse anything else before querying
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $apikey))
        return null;
    FN_EnsureUserApiKeyField();
    $users = FN_GetUsers(array("apikey" => $apikey));
    if (!is_array($users) || count($users) != 1)
        return null;
    $uservalues = reset($users);
    if (empty($uservalues['username']) || empty($uservalues['apikey']))
        return null;
    // the filter of some drivers is not case sensitive: compare again
    if (!hash_equals("{$uservalues['apikey']}", $apikey))
        return null;
    if (empty($uservalues['active']) || $uservalues['active'] != 1)
        return null;
    if (!FN_UserCanUseApiKey($uservalues))
        return null;
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
 * Shared logic of the profile editors (control center and login module):
 * handles the "regenerate_apikey" POST, creates the key when missing and
 * fills the template variables used by the API key block of the form.
 * The key is shown only to its owner.
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
    $tplvars['apikey_message'] = "";
    $tplvars['mcp_url'] = FN_GetMcpUrl();
    if ($user == "" || $user != $_FN['user'])
        return;
    if (!FN_UserCanUseApiKey($user))
        return;
    if (!empty($_POST['regenerate_apikey']))
    {
        // the regenerate button is inside the profile form: do not save the profile
        unset($_POST['updateuser']);
        $apikey = FN_GenerateApiKey($user);
        if ($apikey != "")
            $tplvars['apikey_message'] = FN_Translate("the API key has been regenerated");
        else
            $tplvars['apikey_message'] = FN_Translate("error");
    }
    $tplvars['apikey'] = FN_GetUserApiKey($user, true);
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
