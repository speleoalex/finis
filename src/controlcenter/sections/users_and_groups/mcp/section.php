<?php

/**
 * MCP servers: the endpoints of this site, the OAuth login, and the API keys of
 * the users (fn_users.apikey hashed, fn_users.apikey_enc encrypted with the site
 * secret: the administrator shows the key of a user, generates a new one or
 * revokes it; every key shown is logged) with the permission to use the write
 * tools (group $_FN['mcp_write_group']).
 *
 * @package Finis_controlcenter
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;
$opt = FN_GetParam("opt", $_GET, "html");
$link = "?fnapp=controlcenter&amp;opt=" . urlencode($opt);
$h = function ($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES);
};
if (!FN_IsAdmin())
{
    echo "<p>" . FN_Translate("operation is not permitted") . "</p>";
    return;
}
//apikey_enc must be in the table definition before a key is generated, or the key could not be shown
FN_EnsureUserApiKeyField();
$writegroup = FN_McpWriteGroup();
if ($writegroup != "")
    FN_CreateGroupIfNotExists($writegroup);
$message = "";
$error = "";
$newkey = "";
$newkeyuser = "";

//--------------------------- actions (POST only) ---------------------------->
$action = FN_GetParam("mcp_action", $_POST, "html");
$user = FN_GetParam("mcp_user", $_POST, "html");
if ($action != "")
{
    if (!hash_equals(FN_ApiKeyFormToken(), (string) FN_GetParam("apikey_token", $_POST, "html")))
        $error = FN_Translate("invalid request, reload the page and try again");
    elseif ($user == "" || !FN_GetUser($user, false))
        $error = FN_Translate("user not found");
    elseif ($action == "generate")
    {
        $newkey = FN_GenerateApiKey($user);
        $newkeyuser = $user;
        if ($newkey != "")
            $message = FN_Translate("new key of") . " $user. " . FN_Translate("The previous key, if any, no longer works.");
        else
            $error = FN_Translate("error");
    }
    elseif ($action == "show")
    {
        $newkey = FN_ApiKeyReveal($user);
        $newkeyuser = $user;
        if ($newkey != "")
            FN_LogEvent("API key of user $user shown to {$_FN['user']}", "apikey");
        elseif (FN_ApiKeyInfo($user)['exists'])
            $error = FN_Translate("this key cannot be shown: it was created when only its hash was stored. Generate a new key to see it") . ": $user";
        else
            $error = FN_Translate("no API key") . ": $user";
    }
    elseif ($action == "revoke")
    {
        if (FN_RevokeApiKey($user))
            $message = FN_Translate("the key has been revoked") . ": $user";
        else
            $error = FN_Translate("error");
    }
    elseif (($action == "allow_write" || $action == "deny_write") && $writegroup != "")
    {
        $allow = $action == "allow_write";
        if (FN_SetUserGroup($user, $writegroup, $allow))
        {
            $message = ($allow ? FN_Translate("write tools allowed") : FN_Translate("read only: the write tools are not allowed")) . ": $user";
            FN_LogEvent("MCP write tools " . ($allow ? "allowed" : "denied") . " for user $user by {$_FN['user']}", "apikey");
        }
        else
            $error = FN_Translate("error");
    }
}
//--------------------------- actions (POST only) ----------------------------<

$token = FN_ApiKeyFormToken();
if ($message != "")
    echo "<div class=\"alert alert-success\">" . $h($message) . "</div>";
if ($error != "")
    echo "<div class=\"alert alert-danger\">" . $h($error) . "</div>";
if ($newkey != "")
{
    echo "<div class=\"card mb-3 border-success\"><div class=\"card-body\">";
    echo "<label class=\"form-label\"><b>" . FN_Translate("API key") . " &middot; " . $h($newkeyuser) . "</b></label>";
    echo "<input type=\"text\" class=\"form-control\" readonly=\"readonly\" onclick=\"this.select()\" value=\"" . $h($newkey) . "\" />";
    echo "<div class=\"form-text\">" . FN_Translate("give it to the user through a safe channel; whoever has it acts on the site as this user") . "</div>";
    echo "</div></div>";
}

//--------------------------- servers ---------------------------------------->
echo "<div class=\"card mb-3\"><div class=\"card-header\"><h5 class=\"card-title mb-0\">" . FN_Translate("MCP servers") . "</h5></div><div class=\"card-body\">";
echo "<p>" . FN_Translate("The MCP servers let AI assistants (Claude, ChatGPT, ...) read and edit this site. A client connects with the personal key of a user, or with the username and password (OAuth): it works with the same permissions the user has on the website. Reading is open to every active user; the write tools are enabled user by user below.") . "</p>";
echo "<table class=\"table table-sm\"><tbody>";
foreach (FN_GetMcpEndpoints() as $name => $url)
    echo "<tr><th style=\"width:12em\">" . $h($name == "cms" ? FN_Translate("CMS (pages, menus, users)") : $name) . "</th><td><input type=\"text\" class=\"form-control form-control-sm\" readonly=\"readonly\" onclick=\"this.select()\" value=\"" . $h($url) . "\" /></td></tr>";
echo "</tbody></table>";
echo "<ul class=\"mb-0\">";
echo "<li>" . FN_Translate("authentication") . ": <code>Authorization: Bearer &lt;" . FN_Translate("key") . "&gt;</code>, <code>X-Api-Key: &lt;" . FN_Translate("key") . "&gt;</code>, <code>&amp;apikey=&lt;" . FN_Translate("key") . "&gt;</code> " . FN_Translate("in the address (clients without custom headers)", "") . ", " . FN_Translate("or OAuth (login with username and password)", "") . "</li>";
$siteurl = isset($_FN['siteurl']) ? rtrim($_FN['siteurl'], "/") . "/" : "";
echo "<li>OAuth: <code>" . $h($siteurl . ".well-known/oauth-authorization-server") . "</code></li>";
echo "<li>" . FN_Translate("keys are stored as a hash and as a copy encrypted with the site secret (misc/secret.key.php): the administrators can show them here, every key shown is logged; a new password revokes the key") . "</li>";
if (!empty($_FN['apikey_groups']))
    echo "<li>" . FN_Translate("only the administrators and the members of these groups can use a key") . ": <code>" . $h($_FN['apikey_groups']) . "</code> (<code>\$_FN['apikey_groups']</code>)</li>";
echo "<li>" . FN_Translate("write tools") . ": " . ($writegroup != "" ? FN_Translate("administrators and group") . " <code>" . $h($writegroup) . "</code> (<code>\$_FN['mcp_write_group']</code>)" : FN_Translate("every user") . " (<code>\$_FN['mcp_write_group']</code> " . FN_Translate("empty") . ")") . "</li>";
echo "<li>" . FN_Translate("documentation") . ": <code>finis/doc/it_guida_mcp.md</code>, <code>finis/doc/en_mcp_guide.md</code></li>";
echo "</ul></div></div>";
//--------------------------- servers ----------------------------------------<

//--------------------------- users ------------------------------------------>
$q = trim(FN_GetParam("q", $_GET, "html"));
$all = FN_GetParam("all", $_GET, "html") != "";
$table = FN_GetUserForm();
$users = $table->xmltable->GetRecords();
if (!is_array($users))
    $users = array();
$list = array();
foreach ($users as $u)
{
    $haskey = !empty($u['apikey']);
    if ($q != "")
    {
        $text = strtolower("{$u['username']} " . (isset($u['email']) ? $u['email'] : "") . " " . (isset($u['name']) ? $u['name'] : "") . " " . (isset($u['surname']) ? $u['surname'] : ""));
        if (strpos($text, strtolower($q)) === false)
            continue;
    }
    elseif (!$all && !$haskey && !($writegroup != "" && FN_UserCanWriteByApiKey($u) && empty($u['level'])))
        continue;
    $list[] = $u;
}
usort($list, function ($a, $b)
{
    return strcasecmp($a['username'], $b['username']);
});
echo "<div class=\"card\"><div class=\"card-header\"><h5 class=\"card-title mb-0\">" . FN_Translate("users") . "</h5></div><div class=\"card-body\">";
echo "<form method=\"get\" action=\"\" class=\"mb-3\"><input type=\"hidden\" name=\"fnapp\" value=\"controlcenter\" /><input type=\"hidden\" name=\"opt\" value=\"" . $h($opt) . "\" />";
echo "<div class=\"input-group\" style=\"max-width:36em\"><input type=\"text\" class=\"form-control\" name=\"q\" value=\"" . $h($q) . "\" placeholder=\"" . FN_Translate("username, email, name") . "\" />";
echo "<button class=\"btn btn-primary\" type=\"submit\">" . FN_Translate("search") . "</button>";
echo "<a class=\"btn btn-outline-secondary\" href=\"$link" . ($all ? "" : "&amp;all=1") . "\">" . ($all ? FN_Translate("only with a key") : FN_Translate("all the users")) . "</a></div></form>";
if ($q == "" && !$all)
    echo "<p>" . FN_Translate("users with a key or allowed to write") . ": " . count($list) . ". " . FN_Translate("Search a user to generate a key or to allow the write tools; the users generate their own key in the profile.") . "</p>";
$max = 200;
if (count($list) > $max)
    echo "<p>" . FN_Translate("too many results, only the first ones are shown") . " ($max / " . count($list) . ")</p>";
if (count($list) == 0)
{
    echo "<p>" . FN_Translate("no users") . "</p></div></div>";
    return;
}
echo "<div class=\"table-responsive\"><table class=\"table table-sm table-striped table-hover\"><thead><tr><th>" . FN_Translate("user") . "</th><th>" . FN_Translate("name") . "</th><th>" . FN_Translate("level") . "</th><th>" . FN_Translate("key") . "</th><th>" . FN_Translate("write tools") . "</th><th></th></tr></thead><tbody>";
foreach (array_slice($list, 0, $max) as $u)
{
    $username = $u['username'];
    $active = !empty($u['active']) && $u['active'] == 1;
    $isadmin = !empty($u['level']) && $u['level'] >= 10;
    $info = FN_ApiKeyInfo($u);
    $class = $active ? "" : " class=\"text-muted\"";
    echo "<tr$class><td>" . $h($username) . ($active ? "" : " <small>(" . FN_Translate("not active") . ")</small>") . "</td>";
    echo "<td>" . $h(trim((isset($u['name']) ? $u['name'] : "") . " " . (isset($u['surname']) ? $u['surname'] : ""))) . "</td>";
    echo "<td>" . $h(isset($u['level']) ? $u['level'] : "") . "</td>";
    if (!$info['exists'])
        $key = "<span class=\"text-muted\">&#x2014;</span>";
    elseif ($info['legacy'])
        $key = "&#x1F511; <small class=\"text-warning\">" . FN_Translate("old key, stored in clear until its next use") . "</small>";
    else
        $key = "&#x1F511; <small>" . $h($info['created']) . "</small>";
    if ($info['exists'] && !$info['viewable'])
        $key .= " <small class=\"text-muted\">" . FN_Translate("cannot be shown: generate a new key") . "</small>";
    if ($info['exists'] && !empty($_FN['apikey_groups']) && !FN_UserCanUseApiKey($u))
        $key .= " <small class=\"text-danger\">" . FN_Translate("refused: the user is not in the groups allowed to use a key") . "</small>";
    echo "<td>$key</td>";
    if ($isadmin)
        $write = "&#x2705; " . FN_Translate("administrator");
    elseif ($writegroup == "")
        $write = "&#x2705;";
    else
        $write = FN_UserCanWriteByApiKey($u) ? "&#x270F;&#xFE0F; " . FN_Translate("allowed") : "&#x1F441;&#xFE0F; " . FN_Translate("read only");
    echo "<td>$write</td>";
    echo "<td class=\"text-nowrap\"><form method=\"post\" action=\"$link" . ($q != "" ? "&amp;q=" . urlencode($q) : "") . ($all ? "&amp;all=1" : "") . "\" style=\"display:inline\">";
    echo "<input type=\"hidden\" name=\"apikey_token\" value=\"$token\" /><input type=\"hidden\" name=\"mcp_user\" value=\"" . $h($username) . "\" />";
    if ($info['viewable'])
        echo "<button class=\"btn btn-outline-secondary btn-sm\" type=\"submit\" name=\"mcp_action\" value=\"show\">" . FN_Translate("show key") . "</button> ";
    echo "<button class=\"btn btn-secondary btn-sm\" type=\"submit\" name=\"mcp_action\" value=\"generate\"" . ($info['exists'] ? " onclick=\"return confirm('" . addslashes(FN_Translate("generate a new key? The clients that use the current key will stop working")) . "')\"" : "") . ">" . FN_Translate("generate key") . "</button> ";
    if ($info['exists'])
        echo "<button class=\"btn btn-outline-danger btn-sm\" type=\"submit\" name=\"mcp_action\" value=\"revoke\" onclick=\"return confirm('" . addslashes(FN_Translate("revoke the key? The clients that use it will stop working")) . "')\">" . FN_Translate("revoke") . "</button> ";
    if (!$isadmin && $writegroup != "")
    {
        if (FN_UserCanWriteByApiKey($u))
            echo "<button class=\"btn btn-outline-warning btn-sm\" type=\"submit\" name=\"mcp_action\" value=\"deny_write\">" . FN_Translate("set read only") . "</button>";
        else
            echo "<button class=\"btn btn-outline-primary btn-sm\" type=\"submit\" name=\"mcp_action\" value=\"allow_write\" onclick=\"return confirm('" . addslashes(FN_Translate("allow the write tools? The assistant will be able to change the site as this user")) . "')\">" . FN_Translate("allow the write tools") . "</button>";
    }
    echo "</form></td></tr>";
}
echo "</tbody></table></div></div></div>";
//--------------------------- users ------------------------------------------<
