<?php

/**
 * OAuth 2.1 authorization server of the MCP endpoints: shared functions.
 * The endpoints are in methods/oauthserver.php (index.php?fnapp=oauthserver).
 *
 * Nothing is stored for clients and tokens: client ids, authorization codes,
 * access and refresh tokens are JSON payloads signed with the site secret
 * ("fnoa.<payload>.<signature>", never mistaken for a hexadecimal API key).
 * Codes and refresh tokens are single use: a marker file in {datadir}/_oauth
 * records the used ones until they expire.
 * Every token carries a fingerprint of the API key and of the password of its
 * user: regenerating or revoking the API key, or changing the password,
 * disconnects every OAuth client of the user.
 *
 * @package Finis
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
define("FN_OAUTH_CODE_TTL", 300);
define("FN_OAUTH_ACCESS_TTL", 3600);
define("FN_OAUTH_REFRESH_TTL", 30 * 86400);

/**
 * @return string issuer of the tokens: the site URL without the trailing slash
 */
function FN_OAuthServerIssuer()
{
    global $_FN;
    return rtrim(isset($_FN['siteurl']) ? $_FN['siteurl'] : "", "/");
}

/**
 * @param string $op metadata, prm, register, authorize, token
 * @return string absolute URL of an endpoint
 */
function FN_OAuthServerUrl($op)
{
    return FN_OAuthServerIssuer() . "/index.php?fnapp=oauthserver&op=$op";
}

/**
 * Header of the 401 answers of an MCP endpoint: tells the OAuth clients
 * where the protected resource metadata is (RFC 9728)
 *
 * @param string $resource absolute URL of the MCP endpoint
 */
function FN_OAuthServerChallenge($resource)
{
    header('WWW-Authenticate: Bearer resource_metadata="' . FN_OAuthServerUrl("prm") . "&resource=" . rawurlencode($resource) . '"');
}

/**
 * @return string key of the OAuth signatures, separate from the other uses of the site secret
 */
function FN_OAuthServerKey()
{
    return hash_hmac("sha256", "oauth server", FN_GetSiteSecret());
}

/**
 * @param string $data
 * @return string
 */
function FN_OAuthServerBase64Url($data)
{
    return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
}

/**
 * @param string $type c = client id, k = authorization code, a = access token, r = refresh token
 * @param array $payload x = expiry time
 * @return string
 */
function FN_OAuthServerSign($type, $payload)
{
    $data = FN_OAuthServerBase64Url(json_encode(array("t" => $type) + $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return "fnoa.$data." . FN_OAuthServerBase64Url(hash_hmac("sha256", $data, FN_OAuthServerKey(), true));
}

/**
 * @param string $value
 * @param string $type see FN_OAuthServerSign()
 * @return array|null payload of a value of that type signed here and not expired
 */
function FN_OAuthServerVerify($value, $type)
{
    $parts = explode(".", (string) $value);
    if (count($parts) != 3 || $parts[0] != "fnoa")
        return null;
    if (!hash_equals(FN_OAuthServerBase64Url(hash_hmac("sha256", $parts[1], FN_OAuthServerKey(), true)), $parts[2]))
        return null;
    $payload = json_decode(base64_decode(strtr($parts[1], "-_", "+/")), true);
    if (!is_array($payload) || !isset($payload['t']) || $payload['t'] !== $type || (isset($payload['x']) && $payload['x'] < time()))
        return null;
    return $payload;
}

/**
 * @param array $uservalues
 * @return string changes when the API key or the password of the user change
 */
function FN_OAuthServerUserFingerprint($uservalues)
{
    $apikey = isset($uservalues['apikey']) ? $uservalues['apikey'] : "";
    return substr(hash_hmac("sha256", "$apikey|{$uservalues['passwd']}", FN_OAuthServerKey()), 0, 16);
}

/**
 * @param array $payload code or token payload
 * @return array|null its user, if still active, still allowed to use the MCP
 *         servers and with the API key and password of when the client was authorized
 */
function FN_OAuthServerGrantUser($payload)
{
    $uservalues = FN_GetUser(isset($payload['u']) ? $payload['u'] : "", false);
    if (empty($uservalues['active']) || $uservalues['active'] != 1 || !FN_UserCanUseApiKey($uservalues))
        return null;
    if (!hash_equals(FN_OAuthServerUserFingerprint($uservalues), isset($payload['k']) ? (string) $payload['k'] : ""))
        return null;
    return $uservalues;
}

/**
 * Authenticates the request with an OAuth access token, as FN_LoginByApiKey()
 * does with an API key: no cookie, no session
 *
 * @param string $token
 * @return bool
 */
function FN_OAuthServerLoginByToken($token)
{
    global $_FN;
    $payload = FN_OAuthServerVerify($token, "a");
    $uservalues = $payload ? FN_OAuthServerGrantUser($payload) : null;
    if (!$uservalues)
        return false;
    $_FN['user'] = $uservalues['username'];
    $_FN['uservalues'] = $uservalues;
    $_FN['auth_method'] = "oauth";
    return true;
}

/**
 * Marks a code or refresh token as used
 *
 * @param string $value
 * @param int $expires expiry time of the value: the marker is kept until then
 * @return bool false when it was already used
 */
function FN_OAuthServerUseOnce($value, $expires)
{
    global $_FN;
    $dir = "{$_FN['datadir']}/_oauth";
    if (!file_exists("$dir/cleanup") || filemtime("$dir/cleanup") < time() - 3600)
    {
        FN_MkDir($dir);
        @touch("$dir/cleanup");
        $files = glob("$dir/*.used");
        foreach (is_array($files) ? $files : array() as $file)
        {
            //the margin keeps a marker just created, before touch() sets its expiry
            if (@filemtime($file) < time() - 60)
                @unlink($file);
        }
    }
    $file = "$dir/" . hash("sha256", $value) . ".used";
    //mode "x" fails if the file exists: two parallel requests cannot both use the value
    $fp = @fopen($file, "x");
    if (!$fp)
    {
        if (!file_exists($file))
            FN_Log("OAuth: cannot write $file");
        return false;
    }
    fclose($fp);
    @touch($file, $expires);
    return true;
}
