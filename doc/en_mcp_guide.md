# FINIS MCP server and API keys

FINIS exposes an [MCP](https://modelcontextprotocol.io) (Model Context Protocol) server
so that an LLM client (Claude Code, Claude Desktop, Cursor, custom agents, ...) can
operate the CMS and the control center: read and write pages, manage the site map,
blocks, settings and users.

Every call is authenticated with a **personal API key** or an **OAuth login** (username
and password, see below) and runs with the identity of the user: the same permissions of the control center apply, and every change is
written in the site log with the user name.

## API keys

* Each user has one API key, stored in the `apikey` field of `fn_users`. It is never
  shown in user lists or exported by the API.
* The key is generated automatically the first time the user opens the profile page.
  It is shown only to its owner:
  * control center: **Users and groups > Profile** (`index.php?fnapp=controlcenter&opt=users_and_groups/profile`)
  * front end: **Edit profile** of the login module (`index.php?mod=login&op=editreg`)
* The **Regenerate API key** button creates a new key; the old one stops working at once.
* Administrators cannot read other users' keys; each user manages their own.

The key is sent in the HTTP header of every request:

```
Authorization: Bearer <apikey>
```

or, when the `Authorization` header is stripped by the web server:

```
X-Api-Key: <apikey>
```

The header is accepted by the whole site, not only by the MCP endpoint: the function
`FN_ManageLogin()` logs the user in from the API key whenever there is no cookie session.

If the installation predates the feature, the `apikey` field is added to the
`fn_users` table definition automatically (`FN_EnsureUserApiKeyField()`); with the
MySQL driver the column is created on the first write.

### Restricting the API keys to some users

By default every user can use their key. To allow only some users, set the groups in
`config.php` (or in an `extra/*.inc.php` file of the application):

```php
$_FN['apikey_groups'] = "mcp_users";   // comma separated groups, "" = every user
```

Then only the administrators (level 10) and the members of those groups can use an API
key: the keys of the other users are refused everywhere (MCP endpoint, site login with the
header) and the profile page neither shows nor generates the key. The check is
`FN_UserCanUseApiKey($user)`. To enable a user, add one of the groups to the user.

## Endpoint

```
POST {siteurl}/index.php?fnapp=mcp
Content-Type: application/json
Authorization: Bearer <apikey>
```

* Transport: MCP *Streamable HTTP*, POST only. `GET` answers 405 (no SSE stream),
  `DELETE` answers 200, `OPTIONS` answers 204 with CORS headers.
* Protocol versions: `2025-06-18`, `2025-03-26`, `2024-11-05`.
* Without a valid key or token the answer is `401` with
  `WWW-Authenticate: Bearer resource_metadata="..."`. Browser cookies are ignored on
  purpose: only API key and OAuth clients can use the endpoint.
* When the site is in maintenance mode only administrators can use the endpoint.
* The server is stateless: no session id, each request is complete by itself.
  JSON-RPC batches are supported; notifications get `202 Accepted`.

### Client configuration

Claude Code:

```sh
claude mcp add --transport http finis "https://www.example.com/index.php?fnapp=mcp" \
    --header "Authorization: Bearer 39bc8f0d0185bbad495d40c5f866b763ec9fdbc2e8d221bd"
```

Generic JSON configuration (Claude Desktop, Cursor, ...):

```json
{
  "mcpServers": {
    "finis": {
      "type": "http",
      "url": "https://www.example.com/index.php?fnapp=mcp",
      "headers": { "Authorization": "Bearer 39bc8f0d0185bbad495d40c5f866b763ec9fdbc2e8d221bd" }
    }
  }
}
```

Manual test with curl:

```sh
URL="https://www.example.com/index.php?fnapp=mcp"; KEY="..."
curl -s -X POST "$URL" -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'
curl -s -X POST "$URL" -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"whoami","arguments":{}}}'
```

## OAuth login (Claude Desktop, claude.ai)

Clients with OAuth 2.1 (custom connectors of Claude Desktop and claude.ai, Claude Code,
MCP Inspector, ...) need only the endpoint URL. On the first call the client opens the
browser, the user logs in with username and password and authorizes the client: there is
no key to copy.

* The users allowed are those allowed to use an API key (`FN_UserCanUseApiKey()`, see
  `$_FN['apikey_groups']`), with the same permissions.
* The login is the FINIS one (`FN_ManageLogin()`: captcha, lockout after
  `login_max_attempts` failures). Then a consent page shows the client name and the host
  the browser goes back to.
* The access token lasts one hour, the refresh token 30 days and is replaced at every
  refresh; the client renews them by itself.
* Generating a new API key, revoking it or changing the password disconnects every OAuth
  client of the user. Disabling the user or removing them from `apikey_groups` takes
  effect at the next request.

Endpoints (`src/include/methods/oauthserver.php`, functions in
`src/include/functions_oauth_server.inc.php`):

| URL | Purpose |
|---|---|
| `index.php?fnapp=oauthserver&op=metadata` | authorization server metadata (RFC 8414) |
| `index.php?fnapp=oauthserver&op=prm&resource=<url>` | protected resource metadata (RFC 9728) |
| `index.php?fnapp=oauthserver&op=register` | dynamic client registration (RFC 7591), public clients only |
| `index.php?fnapp=oauthserver&op=authorize` | login and consent, PKCE `S256` required |
| `index.php?fnapp=oauthserver&op=token` | `authorization_code` and `refresh_token` grants |

The 401 of the MCP endpoint carries `WWW-Authenticate: Bearer resource_metadata="<url>"`,
and the client finds the rest from there. Clients and tokens are not stored: they are
signed with the site secret (`misc/secret.key.php`). Deleting that file invalidates every
token and every login cookie.

An application protects its own endpoint the same way: `FN_ManageLogin()` already accepts
the access token (`$_FN['auth_method'] = "oauth"`), and the 401 answer calls
`FN_OAuthServerChallenge(<endpoint url>)`.

### Requirements

* HTTPS: the clients refuse `http://` addresses outside localhost. Behind a proxy that
  hides HTTPS, set `siteurl` in the settings.
* The `Authorization` header must reach PHP. The `.htaccess` written by FINIS when
  mod_rewrite is enabled has the rule; otherwise add it:

  ```apache
  RewriteEngine On
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
  ```

* The clients look for the metadata at the root of the host
  (`/.well-known/oauth-authorization-server/<site path>`). A site at the root of the domain
  is covered by the `.htaccess` of FINIS. A site in a folder needs these rules in the
  `.htaccess` of the domain root (example for a site in `/catasto/`):

  ```apache
  RewriteEngine On
  RewriteRule ^\.well-known/oauth-authorization-server/catasto/?$ /catasto/index.php?fnapp=oauthserver&op=metadata [L]
  RewriteRule ^\.well-known/oauth-protected-resource/(catasto/.*)$ /catasto/index.php?fnapp=oauthserver&op=prm&path=$1 [L]
  ```

  On Plesk/nginx check that `/.well-known/` reaches Apache and is not answered only by the
  Let's Encrypt location.

## Tools

Tool results carry the data twice: as JSON text in `content[0].text` and as
`structuredContent`. Errors are returned with `isError: true` and an `error` message.
"admin" means the user must have level 10 or more.

| Tool | Who | Description |
|------|-----|-------------|
| `whoami` | all | User of the key, level, groups |
| `site_info` | all | Title, languages, theme, home section, version, section types |
| `list_sections` | all | Site map (`parent`, `recursive`, `include_disabled`); non admins see only the sections they can view |
| `get_section` | viewers | All the fields of a section, titles per language, content files |
| `list_section_types` | all | Section types (modules) |
| `create_section` | admin | New page: `title`, `id`, `type`, `parent`, `titles`, `descriptions`, `group_view`, `group_edit`, optional `content` |
| `update_section` | editors | Editors (group_edit) can change title, description, keywords, status, hidden, dates; admins every field (parent, position, type, groups, ...) |
| `delete_section` | admin | Removes record and directory; refuses the home section; `force=true` deletes children |
| `get_section_content` | viewers | `sections/<id>/section.<lang>.html` |
| `set_section_content` | editors | Writes `section.<lang>.html` and clears the cache; absolute URLs of the site are converted to local links |
| `list_section_files` | editors | Files inside the section directory |
| `read_section_file` | editors | Reads a file of the section (php files: admin only) |
| `write_section_file` | editors | Writes a file of the section (php files: admin only) |
| `list_blocks` | all | Blocks the user can view |
| `get_block_content` / `set_block_content` | viewers / editors | `blocks/<id>/section.<lang>.html` |
| `list_settings` | admin | `fn_settings` (passwords and keys hidden) |
| `update_setting` | admin | Changes one setting |
| `list_users` / `get_user` | admin (get_user: also self) | Users without passwords and keys |
| `create_user` / `update_user` | admin | Users; the API key of a user cannot be set from here |
| `list_groups` | all | Groups |
| `get_log` | admin | Last rows of `fn_log` (`limit`, `context`) |
| `log_event` | all | Writes a custom event with the user name |
| `clear_cache` | editors | Clears the site cache |

### Links in the content

The HTML written with `set_section_content`, `set_block_content` and the `content`
argument of `create_section` is rendered by the template engine, which rewrites the
links at display time. Follow the same rules as the editor:

* link to another page as `index.php?mod=<section id>` (the engine turns it into
  `<id>.html` / `<id>.<lang>.html` when mod_rewrite is enabled, or into the proper
  `index.php?mod=` URL otherwise);
* link to other resources of the site (control center, files, images) with a path
  relative to the section directory, e.g. `../../index.php?fnapp=controlcenter`
  or `../../misc/images/logo.png`;
* absolute URLs of the site (`https://yoursite/path/...`) are accepted: on save they
  are converted to local relative links with `FN_RewriteLinksAbsoluteToLocal()`,
  exactly as the editor does, so the content keeps working if the site moves.

Never write `/index.php?mod=...` (leading slash): the engine would prefix it with
the section path.

Paths are confined to the section or block directory: `..` and absolute paths are refused.
Section and block ids accept only letters, digits, `_`, `-` and `.`.

## Logging

Every mutation calls `FN_LogEvent($message, "mcp")`: the `user`, `ip` and `date`
columns of `fn_log` identify who did what. The key generation is logged with context
`apikey`. The log can be read from the control center or with the `get_log` tool.

## Adding tools from an application

Create `{src_application}/include/mcp_tools.php`; it is included after the built in
tools. Register a tool with:

```php
<?php
defined('_FNEXEC') or die('Restricted access');

FN_MCP_AddTool(
    "list_products",
    "List the products of the shop",
    array(
        "properties" => array(
            "category" => array("type" => "string", "description" => "Category id"),
        ),
    ),
    function ($args)
    {
        global $_FN;
        if (!FN_UserInGroup($_FN['user'], "shop"))
            FN_MCP_Fail("permission denied");
        $table = FN_XMDBTable("products");
        $restr = empty($args['category']) ? false : array("category" => $args['category']);
        return array("products" => $table->GetRecords($restr));
    }
);
```

* The callback receives the arguments array and returns an array (JSON result).
* `FN_MCP_Fail($message)` returns a tool error to the client.
* Helpers: `FN_MCP_Arg($args, $key, $default, $required)`, `FN_MCP_RequireAdmin()`,
  `FN_MCP_SafeId($id)`, `FN_MCP_Lang($lang)`, `FN_MCP_SafePath($base, $relative)`.
* Use `FN_LogEvent($msg, "mcp")` in tools that change data.

## Security notes

* Treat the key as a password: use HTTPS, do not put it in URLs, regenerate it if leaked.
* The endpoint ignores cookies, so a logged in browser cannot be driven by a malicious
  page through the MCP endpoint.
* Non administrators can write only html files, and only in the sections and blocks
  whose `group_edit` contains one of their groups.
