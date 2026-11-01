<?php

/**
 * OAuth 2.1 authorization server of the MCP endpoints: clients such as
 * Claude Desktop connect with the username and password of a FINIS user
 * instead of an API key. Token format and shared functions:
 * include/functions_oauth_server.inc.php
 *
 *  op=metadata   authorization server metadata (RFC 8414)
 *  op=prm        protected resource metadata (RFC 9728) of &resource=<MCP url>
 *  op=register   dynamic client registration (RFC 7591), public clients only
 *  op=authorize  FINIS login form, then consent
 *  op=token      authorization_code (PKCE S256 required) and refresh_token grants
 *
 * Only the users allowed to use an API key (FN_UserCanUseApiKey) can authorize a client.
 *
 * @package Finis
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;

/**
 * @param int $status
 * @param array|null $body sent as JSON
 */
function FN_OAuthServerRespond($status, $body = null)
{
    while (ob_get_level() > 0)
        ob_end_clean();
    http_response_code($status);
    header("Cache-Control: no-store");
    if ($body !== null)
    {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit();
}

/**
 * Sends the browser back to the client
 *
 * @param string $uri registered redirect uri
 * @param array $params empty values are left out
 */
function FN_OAuthServerRedirect($uri, $params)
{
    header("Location: $uri" . (strpos($uri, "?") === false ? "?" : "&") . http_build_query(array_filter($params, "strlen")));
    FN_OAuthServerRespond(302);
}

/**
 * Standalone HTML page of the authorization (login, consent, errors)
 *
 * @param int $status
 * @param string $body html
 */
function FN_OAuthServerPage($status, $body)
{
    global $_FN;
    while (ob_get_level() > 0)
        ob_end_clean();
    http_response_code($status);
    header("Content-Type: text/html; charset=utf-8");
    header("Cache-Control: no-store");
    header("X-Frame-Options: DENY");
    header("Content-Security-Policy: frame-ancestors 'none'");
    $title = htmlspecialchars(strip_tags($_FN['site_title']), ENT_QUOTES, "UTF-8", false);
    echo "<!DOCTYPE html>\n<html lang=\"" . htmlspecialchars($_FN['lang']) . "\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>$title</title>
<style>
:root{color-scheme:light dark}
body{margin:0;padding:16px;font-family:system-ui,sans-serif;line-height:1.5;background:Canvas;color:CanvasText}
main{max-width:30rem;margin:2rem auto}
input,button{font:inherit;min-height:44px;box-sizing:border-box}
input:not([type=checkbox]){display:block;width:100%;margin:0 0 .5rem;padding:.5rem}
button{padding:.5rem 1.25rem;margin:.25rem .5rem .25rem 0;cursor:pointer}
a{display:inline-block;min-height:44px;line-height:44px}
dt{font-weight:bold}dd{margin:0 0 .5rem;overflow-wrap:anywhere}
</style></head><body><main><h1>$title</h1>\n$body\n</main></body></html>";
    exit();
}

/**
 * @param mixed $uri
 * @return bool https, or http on the loopback interface (native clients)
 */
function FN_OAuthServerValidRedirectUri($uri)
{
    if (!is_string($uri) || strlen($uri) > 500 || strpos($uri, "#") !== false)
        return false;
    $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
    $host = strtolower((string) parse_url($uri, PHP_URL_HOST));
    return $host != "" && ($scheme == "https" || ($scheme == "http" && in_array($host, array("localhost", "127.0.0.1", "[::1]"))));
}

function FN_OAuthServerRegister()
{
    global $_FN;
    if ($_SERVER['REQUEST_METHOD'] != "POST")
        FN_OAuthServerRespond(405, array("error" => "invalid_request"));
    $request = json_decode($_FN['INPUT'], true);
    $uris = isset($request['redirect_uris']) && is_array($request['redirect_uris']) ? array_values($request['redirect_uris']) : array();
    if (!$uris || count($uris) > 10 || count(array_filter($uris, "FN_OAuthServerValidRedirectUri")) != count($uris))
        FN_OAuthServerRespond(400, array("error" => "invalid_redirect_uri", "error_description" => "redirect_uris: https urls, or http on localhost"));
    $name = isset($request['client_name']) && is_string($request['client_name']) ? trim(mb_substr($request['client_name'], 0, 100, "UTF-8")) : "";
    $client = array("client_name" => $name != "" ? $name : parse_url($uris[0], PHP_URL_HOST), "redirect_uris" => $uris);
    FN_OAuthServerRespond(201, array(
        "client_id" => FN_OAuthServerSign("c", $client),
        "client_id_issued_at" => time(),
        "token_endpoint_auth_method" => "none",
        "grant_types" => array("authorization_code", "refresh_token"),
        "response_types" => array("code"),
        ) + $client);
}

function FN_OAuthServerAuthorize()
{
    global $_FN;
    $clientId = FN_GetParam("client_id", $_GET);
    $client = FN_OAuthServerVerify($clientId, "c");
    $redirect = FN_GetParam("redirect_uri", $_GET);
    //an unknown client or redirect uri gets an error page: redirecting there would make this an open redirector
    if (!$client || !in_array($redirect, $client['redirect_uris'], true))
        FN_OAuthServerPage(400, "<p>" . FN_Translate("invalid authorization request: unknown client or redirect address") . "</p>");
    $state = FN_GetParam("state", $_GET);
    $challenge = FN_GetParam("code_challenge", $_GET);
    if (FN_GetParam("response_type", $_GET) != "code")
        FN_OAuthServerRedirect($redirect, array("error" => "unsupported_response_type", "state" => $state));
    if (FN_GetParam("code_challenge_method", $_GET) != "S256" || !preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge))
        FN_OAuthServerRedirect($redirect, array("error" => "invalid_request", "error_description" => "PKCE S256 required", "state" => $state));
    //not FN_RewriteLink(): with mod_rewrite it would turn op=authorize into a section url
    $url = "index.php?" . preg_replace('/(^|&)fnlogin=[a-z]*/', "", FN_GetParam("QUERY_STRING", $_SERVER)) . "&fnlogin=";
    //the login form posts to this url with fnlogin=login: FN_ManageLogin() checks the password before this code runs
    if ($_FN['user'] == "" || !empty($_FN['auth_method']))
        FN_OAuthServerPage(200, "<p>" . FN_Translate("log in to connect the application to this site") . "</p>" . FN_HtmlLoginForm(false, htmlspecialchars($url . "login")));
    $logout = "<p><a href=\"" . htmlspecialchars($url . "logout") . "\">" . FN_Translate("use another account") . "</a></p>";
    if (!FN_UserCanUseApiKey($_FN['user']))
        FN_OAuthServerPage(403, "<p>" . FN_Translate("your account is not enabled to use the MCP server, ask an administrator") . "</p>$logout");
    //the secid cookie is known only to the browser of the user
    $csrf = hash_hmac("sha256", "consent|{$_FN['user']}|" . FN_GetParam("secid", $_COOKIE), FN_OAuthServerKey());
    $consent = FN_GetParam("consent", $_POST);
    if ($consent != "" && hash_equals($csrf, FN_GetParam("csrf", $_POST)))
    {
        if ($consent != "allow")
            FN_OAuthServerRedirect($redirect, array("error" => "access_denied", "state" => $state));
        //the tokens are bound to the API key: it must exist, so that revoking it revokes them
        FN_GetUserApiKey($_FN['user'], true);
        $uservalues = FN_GetUser($_FN['user'], false);
        FN_LogEvent("OAuth: {$_FN['user']} authorized the client {$client['client_name']} (" . parse_url($redirect, PHP_URL_HOST) . ")", "oauth");
        $code = FN_OAuthServerSign("k", array(
            "u" => $_FN['user'],
            "k" => FN_OAuthServerUserFingerprint($uservalues),
            "c" => hash("sha256", $clientId),
            "r" => $redirect,
            "p" => $challenge,
            "x" => time() + FN_OAUTH_CODE_TTL,
            "n" => bin2hex(random_bytes(8))
        ));
        FN_OAuthServerRedirect($redirect, array("code" => $code, "state" => $state));
    }
    $h = function ($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    };
    FN_OAuthServerPage(200, "<p>" . FN_Translate("an application asks to access this site with your account") . "</p>
<dl><dt>" . FN_Translate("application") . "</dt><dd>" . $h($client['client_name']) . "</dd>
<dt>" . FN_Translate("account") . "</dt><dd>" . $h($_FN['user']) . "</dd>
<dt>" . FN_Translate("then you will go back to") . "</dt><dd>" . $h(parse_url($redirect, PHP_URL_HOST)) . "</dd></dl>
<p>" . FN_Translate("the application will read and change the data with your permissions") . "</p>
<form method=\"post\"><input type=\"hidden\" name=\"csrf\" value=\"$csrf\">
<button type=\"submit\" name=\"consent\" value=\"allow\">" . FN_Translate("authorize") . "</button>
<button type=\"submit\" name=\"consent\" value=\"deny\">" . FN_Translate("deny") . "</button></form>$logout");
}

function FN_OAuthServerToken()
{
    global $_FN;
    if ($_SERVER['REQUEST_METHOD'] != "POST")
        FN_OAuthServerRespond(405, array("error" => "invalid_request"));
    $params = $_POST ? $_POST : (array) json_decode($_FN['INPUT'], true);
    $grantType = FN_GetParam("grant_type", $params);
    $grantTypes = array("authorization_code" => array("code", "k"), "refresh_token" => array("refresh_token", "r"));
    if (!isset($grantTypes[$grantType]))
        FN_OAuthServerRespond(400, array("error" => "unsupported_grant_type"));
    $value = FN_GetParam($grantTypes[$grantType][0], $params);
    $grant = FN_OAuthServerVerify($value, $grantTypes[$grantType][1]);
    if ($grant && $grantType == "authorization_code")
    {
        $verifier = FN_GetParam("code_verifier", $params);
        if (!hash_equals($grant['r'], FN_GetParam("redirect_uri", $params)) || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier) || !hash_equals($grant['p'], FN_OAuthServerBase64Url(hash("sha256", $verifier, true))))
            $grant = null;
    }
    $uservalues = $grant && hash_equals($grant['c'], hash("sha256", FN_GetParam("client_id", $params))) ? FN_OAuthServerGrantUser($grant) : null;
    if (!$uservalues || !FN_OAuthServerUseOnce($value, $grant['x']))
    {
        FN_Log("OAuth: $grantType grant refused, ip " . FN_GetClientIp());
        FN_OAuthServerRespond(400, array("error" => "invalid_grant"));
    }
    $token = array("u" => $uservalues['username'], "k" => $grant['k'], "c" => $grant['c']);
    FN_OAuthServerRespond(200, array(
        "access_token" => FN_OAuthServerSign("a", $token + array("x" => time() + FN_OAUTH_ACCESS_TTL)),
        "token_type" => "Bearer",
        "expires_in" => FN_OAUTH_ACCESS_TTL,
        "refresh_token" => FN_OAuthServerSign("r", $token + array("x" => time() + FN_OAUTH_REFRESH_TTL, "n" => bin2hex(random_bytes(8))))
    ));
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, MCP-Protocol-Version");
if ($_SERVER['REQUEST_METHOD'] == "OPTIONS")
    FN_OAuthServerRespond(204);
switch (FN_GetParam("op", $_GET))
{
    case "metadata":
        FN_OAuthServerRespond(200, array(
            "issuer" => FN_OAuthServerIssuer(),
            "authorization_endpoint" => FN_OAuthServerUrl("authorize"),
            "token_endpoint" => FN_OAuthServerUrl("token"),
            "registration_endpoint" => FN_OAuthServerUrl("register"),
            "response_types_supported" => array("code"),
            "grant_types_supported" => array("authorization_code", "refresh_token"),
            "code_challenge_methods_supported" => array("S256"),
            "token_endpoint_auth_methods_supported" => array("none")
        ));
    case "prm":
        //path: from the rewrite of /.well-known/oauth-protected-resource/<path>, where the query of the resource is lost
        $resource = FN_GetParam("path", $_GET) != "" ? preg_replace('#^(https?://[^/]+).*$#', '$1', FN_OAuthServerIssuer()) . "/" . ltrim(FN_GetParam("path", $_GET), "/") : FN_GetParam("resource", $_GET);
        //index.php is the FINIS MCP endpoint; only the resources of this site are described
        if (strpos($resource, FN_OAuthServerIssuer() . "/") !== 0 || basename((string) parse_url($resource, PHP_URL_PATH)) == "index.php")
            $resource = FN_GetMcpUrl();
        FN_OAuthServerRespond(200, array(
            "resource" => $resource,
            "authorization_servers" => array(FN_OAuthServerIssuer()),
            "bearer_methods_supported" => array("header")
        ));
    case "register":
        FN_OAuthServerRegister();
    case "authorize":
        FN_OAuthServerAuthorize();
    case "token":
        FN_OAuthServerToken();
}
FN_OAuthServerRespond(404, array("error" => "not_found"));
