<?php

/**
 * FINIS MCP server (Model Context Protocol, JSON-RPC 2.0 over HTTP).
 *
 * Endpoint: index.php?fnapp=mcp
 * Authentication: "Authorization: Bearer <apikey>" (or "X-Api-Key: <apikey>",
 * or index.php?fnapp=mcp&apikey=<apikey> for clients configured with a URL only)
 * where the key belongs to a FINIS user (profile page -> API key), or an
 * OAuth access token issued by index.php?fnapp=oauthserver.
 * Every tool runs with the identity of the key owner: permission checks use
 * the user's level and groups, and the log carries the username.
 *
 * Applications can add tools in {src_application}/include/mcp_tools.php
 * with FN_MCP_AddTool($name, $description, $inputSchema, $callback, $write).
 * The write tools are listed and allowed only for the administrators and the
 * users of the group $_FN['mcp_write_group'] (FN_MCP_CanWrite).
 *
 * @package Finis
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;

ob_start();
$_FN['mcp_site_maintenance'] = !empty($_FN['maintenance']);
$_FN['maintenance'] = 0;
$_FN['mcp_protocol_versions'] = array("2025-06-18", "2025-03-26", "2024-11-05");
$_FN['mcp_tools'] = array();

//------------------------------- helpers ------------------------------------->

/**
 * Register an MCP tool.
 *
 * @global array $_FN
 * @param string $name tool name (letters, digits, underscore)
 * @param string $description what the tool does, shown to the LLM
 * @param array $inputSchema JSON schema of the arguments
 * @param callable $callback function($arguments) returning an array (result)
 *        or throwing an Exception on error
 * @param bool $write the tool changes something: listed and allowed only for
 *        the users that may write (FN_MCP_CanWrite)
 * @return void
 */
function FN_MCP_AddTool($name, $description, $inputSchema, $callback, $write = false)
{
    global $_FN;
    if (!isset($inputSchema['type']))
        $inputSchema['type'] = "object";
    if (!isset($inputSchema['properties']))
        $inputSchema['properties'] = new stdClass();
    $_FN['mcp_tools'][$name] = array(
        "name" => $name,
        "description" => $description,
        "inputSchema" => $inputSchema,
        "callback" => $callback,
        "write" => (bool) $write,
    );
}

/**
 * Tell whether the current user may use the write tools: administrators
 * always, the other users when an administrator put them in the group
 * $_FN['mcp_write_group'] (FN_UserCanWriteByApiKey).
 *
 * @global array $_FN
 * @return bool
 */
function FN_MCP_CanWrite()
{
    global $_FN;
    static $canwrite = null;
    if ($canwrite === null)
        $canwrite = FN_UserCanWriteByApiKey($_FN['user']);
    return $canwrite;
}

/**
 * Send the HTTP response and stop.
 *
 * @param int $status HTTP status code
 * @param mixed $body array encoded as JSON, or null for an empty body
 * @return void
 */
function FN_MCP_Respond($status, $body = null)
{
    while (ob_get_level() > 0)
        ob_end_clean();
    http_response_code($status);
    header("Cache-Control: no-cache");
    if ($body !== null)
    {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit();
}

/**
 * Build a JSON-RPC error object.
 */
function FN_MCP_Error($id, $code, $message, $data = null)
{
    $err = array("code" => $code, "message" => $message);
    if ($data !== null)
        $err['data'] = $data;
    return array("jsonrpc" => "2.0", "id" => $id, "error" => $err);
}

/**
 * Build a JSON-RPC result object.
 */
function FN_MCP_Result($id, $result)
{
    return array("jsonrpc" => "2.0", "id" => $id, "result" => $result);
}

/**
 * Exception thrown by tools: the message is returned to the client
 * as a tool error (isError = true), not as a protocol error.
 */
class FN_MCP_ToolException extends Exception
{

}

/**
 * Throw a tool error.
 *
 * @param string $message
 * @throws FN_MCP_ToolException
 */
function FN_MCP_Fail($message)
{
    throw new FN_MCP_ToolException($message);
}

/**
 * Require the current user to be an administrator.
 */
function FN_MCP_RequireAdmin()
{
    if (!FN_IsAdmin())
        FN_MCP_Fail("permission denied: administrator level required");
}

/**
 * Read an argument of the tool call.
 *
 * @param array $args
 * @param string $key
 * @param mixed $default
 * @param bool $required
 * @return mixed
 */
function FN_MCP_Arg($args, $key, $default = "", $required = false)
{
    if (isset($args[$key]) && $args[$key] !== null && $args[$key] !== "")
        return $args[$key];
    if ($required)
        FN_MCP_Fail("missing required argument: $key");
    return $default;
}

/**
 * Validate an identifier used as directory name (section, block, file).
 */
function FN_MCP_SafeId($id, $what = "id")
{
    $id = "$id";
    if ($id == "" || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $id) || strpos($id, "..") !== false)
        FN_MCP_Fail("invalid $what: $id");
    return $id;
}

/**
 * Validate a language code against the languages of the site.
 *
 * @global array $_FN
 */
function FN_MCP_Lang($lang)
{
    global $_FN;
    if ($lang == "")
        return $_FN['lang_default'];
    if (!in_array($lang, $_FN['listlanguages']))
        FN_MCP_Fail("unknown language: $lang (available: " . implode(",", $_FN['listlanguages']) . ")");
    return $lang;
}

/**
 * Resolve a relative path inside a base directory, refusing path traversal.
 *
 * @param string $base absolute directory
 * @param string $relative path requested by the client
 * @return string absolute path (the file may not exist yet)
 */
/**
 * Converts the absolute URLs of the site contained in href/src attributes to
 * local relative links, as the editor does on save (FN_RewriteLinksAbsoluteToLocal).
 *
 * @param string $content HTML content
 * @param string $directory directory of the content relative to the application root (e.g. sections/home)
 * @return string
 */
function FN_MCP_LocalizeLinks($content, $directory)
{
    if (function_exists("FN_RewriteLinksAbsoluteToLocal"))
        return FN_RewriteLinksAbsoluteToLocal($content, $directory);
    return $content;
}

function FN_MCP_SafePath($base, $relative)
{
    $relative = str_replace("\\", "/", "$relative");
    $relative = ltrim($relative, "/");
    if ($relative == "" || preg_match('#(^|/)\.\.(/|$)#', $relative) || strpos($relative, "\0") !== false)
        FN_MCP_Fail("invalid path: $relative");
    $base = rtrim(str_replace("\\", "/", $base), "/");
    $realbase = realpath($base);
    if ($realbase === false)
        FN_MCP_Fail("base directory not found");
    $full = "$realbase/$relative";
    $dir = realpath(dirname($full));
    if ($dir === false || strpos($dir . "/", $realbase . "/") !== 0)
        FN_MCP_Fail("invalid path: $relative");
    return $dir . "/" . basename($full);
}

/**
 * Remove secrets from a user record.
 */
function FN_MCP_PublicUser($uservalues)
{
    foreach (array("passwd", "password", "apikey", "rnd") as $k)
        unset($uservalues[$k]);
    return $uservalues;
}

/**
 * Drop the empty values ("", null, empty array) of an associative array:
 * the results are read by a language model, every key costs tokens.
 *
 * @param array $values
 * @return array
 */
function FN_MCP_NoEmpty($values)
{
    $out = array();
    foreach ($values as $k => $v)
    {
        if ($v === "" || $v === null || (is_array($v) && count($v) == 0))
            continue;
        $out[$k] = $v;
    }
    return $out;
}

/**
 * Type of a section: an empty type is a page of the "finis" module.
 */
function FN_MCP_SectionType($values)
{
    return empty($values['type']) ? "finis" : $values['type'];
}

/**
 * Files of a section folder, without the backups (*.bak~).
 *
 * @global array $_FN
 * @param string $id section id
 * @return array file names
 */
function FN_MCP_SectionFiles($id)
{
    global $_FN;
    $out = array();
    $folder = "{$_FN['src_application']}/sections/$id";
    if (is_dir($folder))
    {
        foreach (scandir($folder) as $f)
        {
            if ($f == "." || $f == ".." || substr($f, -5) == ".bak~")
                continue;
            $out[] = is_dir("$folder/$f") ? "$f/" : $f;
        }
    }
    return $out;
}

/**
 * Section values for the tools: titles and descriptions per language,
 * files, url; empty values left out.
 *
 * @global array $_FN
 */
function FN_MCP_SectionInfo($values)
{
    global $_FN;
    $id = $values['id'];
    $out = array("id" => $id, "type" => FN_MCP_SectionType($values));
    foreach (array("parent", "position", "status", "hidden", "level", "group_view", "group_edit", "startdate", "enddate", "keywords", "accesskey", "blocksmode", "blocks") as $k)
    {
        if (isset($values[$k]) && "{$values[$k]}" !== "")
            $out[$k] = $values[$k];
    }
    foreach (array("title" => "titles", "description" => "descriptions") as $field => $key)
    {
        $perlang = array();
        foreach ($_FN['listlanguages'] as $lang)
        {
            if (!empty($values["{$field}_$lang"]))
                $perlang[$lang] = $values["{$field}_$lang"];
        }
        if (count($perlang) == 0 && !empty($values[$field]))
            $perlang[$_FN['lang_default']] = $values[$field];
        if (count($perlang))
            $out[$key] = $perlang;
    }
    $out['files'] = FN_MCP_SectionFiles($id);
    $out['url'] = FN_GetMcpSiteUrl() . "index.php?mod=$id";
    return FN_MCP_NoEmpty($out);
}

/**
 * Site URL with trailing slash.
 *
 * @global array $_FN
 */
function FN_GetMcpSiteUrl()
{
    global $_FN;
    $siteurl = isset($_FN['siteurl']) ? $_FN['siteurl'] : "";
    if ($siteurl != "" && substr($siteurl, -1) != "/")
        $siteurl .= "/";
    return $siteurl;
}

/**
 * Reload the sections cache after a change.
 *
 * @global array $_FN
 */
function FN_MCP_ReloadSections()
{
    global $_FN;
    $_FN['sections'] = false;
    $_FN['sections'] = FN_GetAllSections();
    FN_GetSections("", false, true, false, true, true);
    FN_GetSectionValues("", false);
}

/**
 * Fields of fn_sections an editor (non admin) may change.
 */
function FN_MCP_EditorSectionFields()
{
    global $_FN;
    $fields = array("title", "description", "keywords", "status", "hidden", "startdate", "enddate");
    foreach ($_FN['listlanguages'] as $lang)
    {
        $fields[] = "title_$lang";
        $fields[] = "description_$lang";
    }
    return $fields;
}

/**
 * Write a content file keeping a backup of the old version (*.bak~, as the
 * online editor does), then clear the cache.
 *
 * @param string $file absolute path
 * @param string $content
 * @return void
 */
function FN_MCP_WriteContent($file, $content)
{
    global $_FN;
    if (!FN_CanModifyFile($_FN['user'], $file))
        FN_MCP_Fail("permission denied on file " . basename($file));
    if (file_exists($file))
    {
        if (file_get_contents($file) === $content)
            return;
        FN_BackupFile($file);
    }
    if (false === FN_Write($content, $file))
        FN_MCP_Fail("cannot write " . basename($file));
    FN_ClearCache();
}

/**
 * Block positions printed by the theme (left, right, top, bottom), read from
 * its templates; null when the theme has no template to look at.
 *
 * @global array $_FN
 * @return array|null
 */
function FN_MCP_ThemeBlockPositions()
{
    global $_FN;
    $dir = "{$_FN['src_application']}/themes/{$_FN['theme']}";
    $files = glob("$dir/template*.tp.html");
    if (!$files)
        return null;
    if (file_exists("$dir/theme.php"))
        $files[] = "$dir/theme.php";
    $src = "";
    foreach ($files as $f)
        $src .= file_get_contents($f);
    $out = array();
    foreach (array("left", "right", "top", "bottom") as $where)
    {
        if (strpos($src, "blocks_$where") !== false || preg_match('/FN_(GetBlocks\w*|HtmlBlocks)\(\s*["\']' . $where . '["\']/', $src))
            $out[] = $where;
    }
    return $out;
}

/**
 * What a module type puts in its pages, for the site guide.
 * Applications can describe their own types in $_FN['mcp_type_info'][$type].
 *
 * @global array $_FN
 * @param string $type
 * @return string
 */
function FN_MCP_TypeInfo($type)
{
    global $_FN;
    if (!empty($_FN['mcp_type_info'][$type]))
        return $_FN['mcp_type_info'][$type];
    $known = array(
        "block_menu" => "menu group, no text of its own: its page lists the children",
        "dbview" => "database records (list and sheets): data, not page text",
        "navigator" => "database records and files",
        "table_editor" => "database table editor",
        "news" => "news articles (records)",
        "calendar" => "calendar events",
        "login" => "login, registration and profile forms",
        "search" => "site search results",
        "sitemap_tree" => "generated site map",
        "redirect" => "redirect to another address",
        "filemanager" => "file manager",
        "contacts" => "contact form",
        "rssviewer" => "external RSS feed",
    );
    return isset($known[$type]) ? $known[$type] : "generated by the module";
}

/**
 * Flags of a section in the compact site map.
 *
 * @global array $_FN
 */
function FN_MCP_SectionFlags($id, $values, $markEdit)
{
    global $_FN;
    $flags = array();
    if (!empty($values['hidden']))
        $flags[] = "hidden";
    if (empty($values['status']))
        $flags[] = "off";
    if (!empty($values['group_view']) || (isset($values['level']) && "{$values['level']}" !== ""))
        $flags[] = "private";
    $folder = "{$_FN['src_application']}/sections/$id";
    if (file_exists("$folder/section.php"))
        $flags[] = "php";
    if ($markEdit && FN_UserCanEditSection($id))
        $flags[] = "edit";
    if (FN_MCP_SectionType($values) == "finis")
    {
        $langs = array();
        foreach ($_FN['listlanguages'] as $lang)
        {
            if (file_exists("$folder/section.$lang.html"))
                $langs[] = $lang;
        }
        if (count($langs) == 0)
            $flags[] = file_exists("$folder/section.html") ? "html:section.html" : "nohtml";
        elseif (count($langs) < count($_FN['listlanguages']))
            $flags[] = "langs:" . implode(",", $langs);
    }
    return $flags;
}

/**
 * Compact site map: one line per section (id [type] "title" flags),
 * children indented under their parent, in menu order.
 *
 * @param string $root parent of the subtree, "" = whole site
 * @param int $max maximum number of lines
 * @return string
 */
function FN_MCP_SectionsMap($root = "", $max = 400)
{
    $all = FN_GetAllSections();
    $visible = array();
    $children = array();
    foreach ($all as $id => $values)
    {
        if (!FN_IsAdmin() && !FN_UserCanViewSection($id))
            continue;
        $visible[$id] = $values;
        $children["{$values['parent']}"][] = $id;
    }
    foreach ($children as $p => $ids)
    {
        usort($ids, function ($a, $b) use ($visible)
        {
            return (int) $visible[$a]['position'] - (int) $visible[$b]['position'];
        });
        $children[$p] = $ids;
    }
    $markEdit = !FN_IsAdmin();
    $lines = array();
    $done = array();
    $line = function ($id, $depth, $suffix = "") use ($visible, $markEdit)
    {
        $values = $visible[$id];
        $type = FN_MCP_SectionType($values);
        $flags = FN_MCP_SectionFlags($id, $values, $markEdit);
        if ($suffix != "")
            $flags[] = $suffix;
        return str_repeat("  ", $depth) . $id . ($type != "finis" ? " [$type]" : "") . " \"" . $values['title'] . "\"" . (count($flags) ? " " . implode(" ", $flags) : "");
    };
    $walk = function ($parent, $depth) use (&$walk, &$lines, &$done, $children, $line, $max)
    {
        if (empty($children[$parent]))
            return;
        foreach ($children[$parent] as $id)
        {
            if (isset($done[$id]) || count($lines) >= $max)
                continue;
            $done[$id] = true;
            $lines[] = $line($id, $depth);
            $walk($id, $depth + 1);
        }
    };
    $walk($root, 0);
    if ($root == "")
    {
        // sections whose parent is missing or not visible
        foreach ($visible as $id => $values)
        {
            if (!isset($done[$id]) && count($lines) < $max)
            {
                $done[$id] = true;
                $lines[] = $line($id, 0, "(parent {$values['parent']} not visible)");
                $walk($id, 1);
            }
        }
    }
    if (count($lines) >= $max)
        $lines[] = "... list cut at $max lines: use list_sections parent=<id>";
    return implode("\n", $lines);
}

/**
 * Text of the site_guide tool: how the pages of this site are stored and
 * edited, the blocks shown by the theme and the site map. Applications add
 * lines with $_FN['mcp_guide'] (string or array of strings).
 *
 * @global array $_FN
 * @return string
 */
function FN_MCP_SiteGuide()
{
    global $_FN;
    $home = $_FN['home_section'];
    $langs = array();
    foreach ($_FN['listlanguages'] as $l)
        $langs[] = $l == $_FN['lang_default'] ? "$l (default)" : $l;
    $g = array();
    $g[] = "SITE {$_FN['site_title']} " . FN_GetMcpSiteUrl() . " | user {$_FN['user']}" . (FN_IsAdmin() ? " (admin, can edit everything)" : (FN_MCP_CanWrite() ? " (can edit only the sections flagged edit)" : " (READ ONLY: no write tools, an administrator can allow them)")) . " | languages " . implode(", ", $langs);
    $g[] = "PAGES";
    $g[] = "- Every page is a section <id>, address index.php?mod=<id>. The home page is section '$home', also shown at the site root.";
    $g[] = "- Page text (sections without [type] below): sections/<id>/section.<lang>.html, one file per language: change every language. A language without its file shows section.html or nothing.";
    $g[] = "- Read it with get_section_content. Change a part with edit_section_content (exact old text -> new text, cheapest), the whole page with set_section_content. Titles, menu, visibility: update_section.";
    $g[] = "- The html is a template filled when shown: keep {name} placeholders and <!-- if {x} --> / <!-- foreach {x} --> ... <!-- end if|foreach {x} --> blocks as they are; text in {braces} is not printed literally.";
    $g[] = "- Flag php: sections/<id>/section.php runs first and prepares the template data or prints dynamic parts (maps, statistics, lists): that text is not in the html; only admins can read or change it (read_section_file, write_section_file).";
    $g[] = "- Sections with a [type] are modules: the module builds the content (records, forms, results); html files in their folder are layouts, not page text.";
    $g[] = "- Links: other pages index.php?mod=<id>; images and files with a path relative to sections/<id>/ (images/a.jpg); never a leading '/'. Absolute addresses of this site are converted on save.";
    $g[] = "- Every write keeps the old file as *.bak~ (list_section_files backups=true, read it to restore), clears the cache and is logged with your name. Only text files can be written.";
    $g[] = "- Menu = sections tree by parent and position. hidden: not in the menu but reachable; off: disabled; private: limited to groups or logged users.";
    // module types in use
    $types = array();
    foreach (FN_GetAllSections() as $id => $values)
    {
        $type = FN_MCP_SectionType($values);
        if ($type != "finis" && (FN_IsAdmin() || FN_UserCanViewSection($id)))
            $types[$type] = FN_MCP_TypeInfo($type);
    }
    if (count($types))
    {
        $g[] = "MODULES IN USE";
        foreach ($types as $type => $info)
            $g[] = "- $type: $info";
    }
    // blocks
    $positions = FN_MCP_ThemeBlockPositions();
    $blocks = array();
    $allblocks = FN_GetAllBlocks();
    foreach (is_array($allblocks) ? $allblocks : array() as $id => $values)
    {
        if (!FN_IsAdmin() && !FN_UserCanViewBlock($id))
            continue;
        $shown = !empty($values['status']) && ($positions === null || in_array($values['where'], $positions));
        $blocks[] = "$id" . (!empty($values['type']) ? " [{$values['type']}]" : "") . " {$values['where']} \"{$values['title']}\"" . ($shown ? "" : " not shown");
    }
    if ($positions === array())
        $g[] = "BLOCKS: the theme '{$_FN['theme']}' shows no block positions, so blocks (" . count($blocks) . ") never appear on the pages: ignore them.";
    elseif (count($blocks))
    {
        $g[] = "BLOCKS (side boxes; theme '{$_FN['theme']}' shows: " . ($positions === null ? "unknown" : implode(", ", $positions)) . "; html in blocks/<id>/section.<lang>.html, get_block_content/set_block_content):";
        foreach ($blocks as $b)
            $g[] = "- $b";
    }
    $g[] = "SECTIONS (indent = child; flags: hidden off private php edit, langs:<only these language files>, nohtml = no text file)";
    $g[] = FN_MCP_SectionsMap("");
    if (!empty($_FN['mcp_guide']))
    {
        $g[] = "NOTES";
        foreach ((array) $_FN['mcp_guide'] as $line)
            $g[] = "- $line";
    }
    return implode("\n", $g);
}

/**
 * Path of the html file of a section in a language, for reading: falls back
 * to the default language and then to section.html.
 *
 * @global array $_FN
 * @return array (absolute path or "", language of the file)
 */
function FN_MCP_SectionHtmlFile($id, $lang)
{
    global $_FN;
    $folder = "{$_FN['src_application']}/sections/$id";
    foreach (array($lang, $_FN['lang_default']) as $l)
    {
        if (file_exists("$folder/section.$l.html"))
            return array("$folder/section.$l.html", $l);
    }
    if (file_exists("$folder/section.html"))
        return array("$folder/section.html", "");
    return array("", "");
}

/**
 * The tools take the optional properties in a "fields" object (short
 * schemas); the same names are accepted at the top level too.
 *
 * @param array $args
 * @return array arguments with the fields merged (top level wins)
 */
function FN_MCP_MergeFields($args)
{
    if (isset($args['fields']) && is_array($args['fields']))
    {
        $fields = $args['fields'];
        unset($args['fields']);
        $args = array_merge($fields, $args);
    }
    return $args;
}

/**
 * Check that a section exists and can be edited by the current user.
 */
function FN_MCP_EditableSection($args)
{
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    if (!FN_SectionExists($id))
        FN_MCP_Fail("section not found: $id");
    if (!FN_UserCanEditSection($id))
        FN_MCP_Fail("permission denied: you cannot edit section $id");
    return $id;
}

//------------------------------- tools --------------------------------------->

FN_MCP_AddTool("site_guide", "START HERE. How this site stores and edits its pages, the blocks the theme shows and the full site map. Read it before the other tools.", array(), function ($args)
{
    return FN_MCP_SiteGuide();
});

FN_MCP_AddTool("whoami", "Current user: username, admin, level, groups.", array(), function ($args)
{
    global $_FN;
    $user = FN_GetUser($_FN['user']);
    return FN_MCP_NoEmpty(array(
        "username" => $_FN['user'],
        "admin" => FN_IsAdmin(),
        "level" => isset($user['level']) ? $user['level'] : "",
        "groups" => isset($user['group']) ? array_values(array_filter(explode(",", $user['group']))) : array(),
        "email" => isset($user['email']) ? $user['email'] : "",
    ));
});

FN_MCP_AddTool("site_info", "Site title, address, languages, theme, home section, version.", array(), function ($args)
{
    global $_FN;
    return FN_MCP_NoEmpty(array(
        "site_title" => $_FN['site_title'],
        "site_subtitle" => $_FN['site_subtitle'],
        "siteurl" => FN_GetMcpSiteUrl(),
        "languages" => $_FN['listlanguages'],
        "lang_default" => $_FN['lang_default'],
        "theme" => $_FN['theme'],
        "home_section" => $_FN['home_section'],
        "maintenance" => !empty($_FN['maintenance']) ? true : "",
        "version" => trim(@file_get_contents("{$_FN['src_finis']}/VERSION")),
    ));
});

FN_MCP_AddTool("list_sections", "Site map, one line per section as in site_guide.", array(
    "properties" => array(
        "parent" => array("type" => "string", "description" => "Only the subtree of this section"),
    ),
), function ($args)
{
    $parent = FN_MCP_Arg($args, "parent", "");
    if ($parent != "")
        FN_MCP_SafeId($parent, "section id");
    $map = FN_MCP_SectionsMap($parent);
    return $map == "" ? "no sections" : $map;
});

FN_MCP_AddTool("get_section", "Properties of a section: type, parent, titles and descriptions per language, permissions, files, address.", array(
    "properties" => array(
        "id" => array("type" => "string"),
    ),
    "required" => array("id"),
), function ($args)
{
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    $values = FN_GetSectionValues($id);
    if (!$values)
        FN_MCP_Fail("section not found: $id");
    if (!FN_IsAdmin() && !FN_UserCanViewSection($id))
        FN_MCP_Fail("permission denied");
    $info = FN_MCP_SectionInfo($values);
    $info['can_edit'] = FN_UserCanEditSection($id);
    return $info;
});

FN_MCP_AddTool("list_section_types", "Module types for create_section (empty type = text page).", array(), function ($args)
{
    return array("types" => array_keys(FN_GetAllSectionTypes()));
});

FN_MCP_AddTool("create_section", "Admin. New section (page), added after its last sibling. Without type it is a text page.", array(
    "properties" => array(
        "title" => array("type" => "string", "description" => "Default language"),
        "id" => array("type" => "string", "description" => "Default: from the title"),
        "parent" => array("type" => "string", "description" => "Empty = top level"),
        "type" => array("type" => "string", "description" => "Module (list_section_types)"),
        "content" => array("type" => "string", "description" => "Html in lang"),
        "lang" => array("type" => "string"),
        "fields" => array("type" => "object", "description" => "titles {lang:..}, descriptions {lang:..}, description, keywords, status (1 default, 0 off), hidden (1 = not in menu), group_view, group_edit (comma separated groups)"),
    ),
    "required" => array("title"),
), function ($args)
{
    global $_FN;
    FN_MCP_RequireAdmin();
    $args = FN_MCP_MergeFields($args);
    $title = FN_MCP_Arg($args, "title", "", true);
    $id = FN_MCP_Arg($args, "id", "");
    if ($id == "")
        $id = FN_MakeSectionId($title);
    $id = FN_MCP_SafeId($id, "section id");
    if (FN_SectionExists($id) || file_exists("{$_FN['src_application']}/sections/$id"))
        FN_MCP_Fail("section already exists: $id");
    $parent = FN_MCP_Arg($args, "parent", "");
    if ($parent != "")
    {
        FN_MCP_SafeId($parent, "parent");
        if (!FN_SectionExists($parent))
            FN_MCP_Fail("parent section not found: $parent");
    }
    $type = FN_MCP_Arg($args, "type", "");
    if ($type != "")
    {
        FN_MCP_SafeId($type, "type");
        $types = FN_GetAllSectionTypes();
        if (!isset($types[$type]))
            FN_MCP_Fail("unknown section type: $type");
    }
    $form = FN_XMDBForm("fn_sections");
    $newvalues = array();
    $newvalues['id'] = $id;
    $newvalues['title'] = $title;
    $newvalues['type'] = $type;
    $newvalues['parent'] = $parent;
    $newvalues['sectionpath'] = "sections";
    $newvalues['description'] = FN_MCP_Arg($args, "description", "");
    $newvalues['keywords'] = FN_MCP_Arg($args, "keywords", "");
    $newvalues['status'] = "" . FN_MCP_Arg($args, "status", "1");
    $newvalues['hidden'] = "" . FN_MCP_Arg($args, "hidden", "0");
    $newvalues['group_view'] = FN_MCP_Arg($args, "group_view", "");
    $newvalues['group_edit'] = FN_MCP_Arg($args, "group_edit", "");
    $titles = FN_MCP_Arg($args, "titles", array());
    $descriptions = FN_MCP_Arg($args, "descriptions", array());
    foreach ($_FN['listlanguages'] as $lang)
    {
        $newvalues["title_$lang"] = isset($titles[$lang]) ? $titles[$lang] : $title;
        $newvalues["description_$lang"] = isset($descriptions[$lang]) ? $descriptions[$lang] : $newvalues['description'];
    }
    // position: after the last sibling
    $position = 0;
    foreach (FN_GetAllSections() as $values)
    {
        if ($values['parent'] == $parent && $values['position'] > $position)
            $position = (int) $values['position'];
    }
    $newvalues['position'] = $position + 1;
    // keep only fields known by the table
    foreach ($newvalues as $k => $v)
    {
        if (!isset($form->xmltable->fields[$k]))
            unset($newvalues[$k]);
    }
    $errors = $form->VerifyInsert($newvalues);
    if (is_array($errors) && count($errors) > 0)
        FN_MCP_Fail("invalid values: " . json_encode($errors));
    if ($type != "" && file_exists("{$_FN['src_finis']}/modules/$type/section_template"))
        $r = FN_CopyDir("{$_FN['src_finis']}/modules/$type/section_template", "{$_FN['src_application']}/sections/$id", false);
    else
        $r = FN_MkDir("{$_FN['src_application']}/sections/$id");
    if (!$r)
        FN_MCP_Fail("cannot create the section directory");
    $nv = $form->InsertRecord($newvalues);
    if (!is_array($nv))
        FN_MCP_Fail("database insert failed");
    FN_UpdateDefaultXML($nv);
    $content = FN_MCP_Arg($args, "content", null);
    if ($content !== null)
    {
        $lang = FN_MCP_Lang(FN_MCP_Arg($args, "lang", ""));
        FN_Write(FN_MCP_LocalizeLinks($content, "sections/$id"), "{$_FN['src_application']}/sections/$id/section.$lang.html");
    }
    FN_LogEvent("created new section:$id", "mcp");
    FN_OnSitemapChange();
    FN_ClearCache();
    FN_MCP_ReloadSections();
    return FN_MCP_SectionInfo(FN_GetSectionValues($id, false));
}, true);

FN_MCP_AddTool("update_section", "Change section properties (page text: edit_section_content).", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "fields" => array("type" => "object", "description" => "title, titles {lang:..}, description, descriptions {lang:..}, keywords, status (1/0), hidden (1 = not in menu), startdate, enddate; admin only: parent, position (order among siblings), type, group_view, group_edit, level, accesskey, blocksmode, blocks"),
    ),
    "required" => array("id"),
), function ($args)
{
    global $_FN;
    $args = FN_MCP_MergeFields($args);
    $id = FN_MCP_EditableSection($args);
    $oldvalues = FN_GetSectionValues($id);
    $form = FN_XMDBForm("fn_sections");
    $allowed = FN_IsAdmin() ? array_keys($form->xmltable->fields) : FN_MCP_EditorSectionFields();
    $newvalues = array();
    foreach ($args as $k => $v)
    {
        if ($k == "id" || $k == "titles" || $k == "descriptions" || is_array($v))
            continue;
        if (!isset($form->xmltable->fields[$k]))
            continue;
        if (!in_array($k, $allowed))
            FN_MCP_Fail("permission denied: field $k can be changed only by administrators");
        $newvalues[$k] = "$v";
    }
    foreach (array("titles" => "title", "descriptions" => "description") as $argkey => $field)
    {
        $perlang = FN_MCP_Arg($args, $argkey, array());
        if (is_array($perlang))
        {
            foreach ($perlang as $lang => $v)
            {
                FN_MCP_Lang($lang);
                if (isset($form->xmltable->fields["{$field}_$lang"]))
                    $newvalues["{$field}_$lang"] = "$v";
            }
        }
    }
    if (isset($newvalues['parent']) && $newvalues['parent'] != "")
    {
        FN_MCP_SafeId($newvalues['parent'], "parent");
        if (!FN_SectionExists($newvalues['parent']) || $newvalues['parent'] == $id)
            FN_MCP_Fail("invalid parent: {$newvalues['parent']}");
    }
    if (isset($newvalues['type']) && $newvalues['type'] != "")
    {
        $types = FN_GetAllSectionTypes();
        if (!isset($types[$newvalues['type']]))
            FN_MCP_Fail("unknown section type: {$newvalues['type']}");
    }
    // the site shows title_<lang>: keep the default language aligned with the plain title
    foreach (array("title", "description") as $field)
    {
        $langfield = "{$field}_{$_FN['lang_default']}";
        if (isset($newvalues[$field]) && !isset($newvalues[$langfield]) && isset($form->xmltable->fields[$langfield]))
            $newvalues[$langfield] = $newvalues[$field];
    }
    if (count($newvalues) == 0)
        FN_MCP_Fail("nothing to update");
    $changed = array_keys($newvalues);
    $newvalues['id'] = $id;
    $nv = $form->xmltable->UpdateRecordBypk($newvalues, "id", $id);
    if (!is_array($nv))
        FN_MCP_Fail("database update failed");
    FN_UpdateDefaultXML(array_merge($oldvalues, $nv));
    FN_LogEvent("section updated:$id (" . implode(",", $changed) . ")", "mcp");
    FN_OnSitemapChange();
    FN_ClearCache();
    FN_MCP_ReloadSections();
    return FN_MCP_SectionInfo(FN_GetSectionValues($id, false));
}, true);

FN_MCP_AddTool("delete_section", "Admin. Delete a section and its folder; children only with force=true. Not the home.", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "force" => array("type" => "boolean", "description" => "Delete the children too"),
    ),
    "required" => array("id"),
), function ($args)
{
    global $_FN;
    FN_MCP_RequireAdmin();
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    if (!FN_SectionExists($id))
        FN_MCP_Fail("section not found: $id");
    if ($id == $_FN['home_section'])
        FN_MCP_Fail("the home section cannot be deleted");
    $force = (bool) FN_MCP_Arg($args, "force", false);
    $all = FN_GetAllSections();
    $todelete = array($id);
    $queue = array($id);
    while (count($queue))
    {
        $p = array_shift($queue);
        foreach ($all as $sid => $values)
        {
            if ($values['parent'] == $p && !in_array($sid, $todelete))
            {
                $todelete[] = $sid;
                $queue[] = $sid;
            }
        }
    }
    if (count($todelete) > 1 && !$force)
        FN_MCP_Fail("section $id has children (" . implode(",", array_slice($todelete, 1)) . "): use force=true to delete them too");
    $form = FN_XMDBForm("fn_sections");
    foreach ($todelete as $sid)
    {
        if (file_exists("{$_FN['src_application']}/sections/$sid"))
            FN_RemoveDir("{$_FN['src_application']}/sections/$sid");
        $form->xmltable->DelRecord($sid);
        FN_LogEvent("section deleted:$sid", "mcp");
    }
    FN_OnSitemapChange();
    FN_ClearCache();
    FN_MCP_ReloadSections();
    return array("deleted" => $todelete);
}, true);

FN_MCP_AddTool("get_section_content", "Html of a page in one language (falls back to the default language).", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "lang" => array("type" => "string", "description" => "Default: site language"),
    ),
    "required" => array("id"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    $values = FN_GetSectionValues($id);
    if (!$values)
        FN_MCP_Fail("section not found: $id");
    if (!FN_IsAdmin() && !FN_UserCanViewSection($id))
        FN_MCP_Fail("permission denied");
    $lang = FN_MCP_Lang(FN_MCP_Arg($args, "lang", ""));
    list($file, $filelang) = FN_MCP_SectionHtmlFile($id, $lang);
    $type = FN_MCP_SectionType($values);
    $out = array("id" => $id, "lang" => $lang);
    if ($file == "")
        $out['note'] = "no html file for this page";
    elseif ($filelang != $lang)
        $out['file'] = basename($file) . " (no section.$lang.html)";
    if ($type != "finis")
        $out['type'] = "$type: module page, this html is not its main content";
    if (file_exists("{$_FN['src_application']}/sections/$id/section.php"))
        $out['php'] = true;
    $out['content'] = $file != "" ? file_get_contents($file) : "";
    return $out;
});

FN_MCP_AddTool("edit_section_content", "Replace text in the html of a page (section.<lang>.html). old must match exactly, once unless all=true. Cheapest way to change a page.", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "old" => array("type" => "string", "description" => "Exact current text, with enough context to be unique"),
        "new" => array("type" => "string"),
        "lang" => array("type" => "string", "description" => "Default: site language"),
        "all" => array("type" => "boolean", "description" => "Replace every occurrence"),
    ),
    "required" => array("id", "old", "new"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_EditableSection($args);
    if (!isset($args['old']) || !is_string($args['old']) || $args['old'] === "")
        FN_MCP_Fail("missing required argument: old");
    if (!isset($args['new']) || !is_string($args['new']))
        FN_MCP_Fail("missing required argument: new");
    $lang = FN_MCP_Lang(FN_MCP_Arg($args, "lang", ""));
    $file = "{$_FN['src_application']}/sections/$id/section.$lang.html";
    if (!file_exists($file))
        FN_MCP_Fail("section.$lang.html does not exist: write it with set_section_content");
    $content = file_get_contents($file);
    $count = substr_count($content, $args['old']);
    if ($count == 0)
        FN_MCP_Fail("old text not found in section.$lang.html (it must match exactly, spaces and entities included)");
    if ($count > 1 && empty($args['all']))
        FN_MCP_Fail("old text found $count times: add context or set all=true");
    $content = str_replace($args['old'], FN_MCP_LocalizeLinks($args['new'], "sections/$id"), $content);
    FN_MCP_WriteContent($file, $content);
    FN_LogEvent("section content edited:$id ($lang)", "mcp");
    return array("replaced" => $count, "bytes" => strlen($content));
}, true);

FN_MCP_AddTool("set_section_content", "Write the whole html of a page (section.<lang>.html): body fragment, no <html>/<body>.", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "content" => array("type" => "string"),
        "lang" => array("type" => "string", "description" => "Default: site language"),
    ),
    "required" => array("id", "content"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_EditableSection($args);
    if (!isset($args['content']) || !is_string($args['content']))
        FN_MCP_Fail("missing required argument: content");
    $lang = FN_MCP_Lang(FN_MCP_Arg($args, "lang", ""));
    $folder = "{$_FN['src_application']}/sections/$id";
    if (!is_dir($folder))
        FN_MkDir($folder);
    $content = FN_MCP_LocalizeLinks($args['content'], "sections/$id");
    FN_MCP_WriteContent("$folder/section.$lang.html", $content);
    FN_LogEvent("section content updated:$id ($lang)", "mcp");
    return array("file" => "sections/$id/section.$lang.html", "bytes" => strlen($content));
}, true);

FN_MCP_AddTool("list_section_files", "Files in sections/<id>/: name size date.", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "path" => array("type" => "string", "description" => "Sub folder"),
        "backups" => array("type" => "boolean", "description" => "Also the *.bak~ copies"),
    ),
    "required" => array("id"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_EditableSection($args);
    $base = "{$_FN['src_application']}/sections/$id";
    $path = FN_MCP_Arg($args, "path", "");
    $dir = $path == "" ? realpath($base) : FN_MCP_SafePath($base, $path);
    if (!$dir || !is_dir($dir))
        FN_MCP_Fail("directory not found");
    $withbackups = !empty($args['backups']);
    $out = array();
    $backups = 0;
    foreach (scandir($dir) as $f)
    {
        if ($f == "." || $f == "..")
            continue;
        if (substr($f, -5) == ".bak~")
        {
            $backups++;
            if (!$withbackups)
                continue;
        }
        $out[] = is_dir("$dir/$f") ? "$f/" : "$f " . filesize("$dir/$f") . " " . date("Y-m-d H:i", filemtime("$dir/$f"));
    }
    return FN_MCP_NoEmpty(array("files" => $out, "backups" => $withbackups ? "" : $backups));
});

FN_MCP_AddTool("read_section_file", "Read a text file of sections/<id>/ (php: admin).", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "path" => array("type" => "string", "description" => "Relative to sections/<id>/"),
    ),
    "required" => array("id", "path"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_EditableSection($args);
    $file = FN_MCP_SafePath("{$_FN['src_application']}/sections/$id", FN_MCP_Arg($args, "path", "", true));
    if (!is_file($file))
        FN_MCP_Fail("file not found");
    if (!FN_IsAdmin() && FN_GetFileExtension($file) == "php")
        FN_MCP_Fail("permission denied: php files are reserved to administrators");
    return file_get_contents($file);
});

FN_MCP_AddTool("write_section_file", "Write a text file in sections/<id>/ (creates folders; php: admin).", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "path" => array("type" => "string", "description" => "Relative to sections/<id>/"),
        "content" => array("type" => "string"),
    ),
    "required" => array("id", "path", "content"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_EditableSection($args);
    if (!isset($args['content']) || !is_string($args['content']))
        FN_MCP_Fail("missing required argument: content");
    $path = FN_MCP_Arg($args, "path", "", true);
    $base = "{$_FN['src_application']}/sections/$id";
    $relative = ltrim(str_replace("\\", "/", $path), "/");
    if (preg_match('#(^|/)\.\.(/|$)#', $relative))
        FN_MCP_Fail("invalid path");
    $subdir = dirname($relative);
    if ($subdir != "." && $subdir != "")
        FN_MkDir("$base/$subdir");
    $file = FN_MCP_SafePath($base, $relative);
    FN_MCP_WriteContent($file, $args['content']);
    FN_LogEvent("file written:sections/$id/$relative", "mcp");
    return array("file" => "sections/$id/$relative", "bytes" => strlen($args['content']));
}, true);

FN_MCP_AddTool("list_blocks", "Blocks (side boxes): id [type] position \"title\", and whether the theme shows them.", array(), function ($args)
{
    $positions = FN_MCP_ThemeBlockPositions();
    $out = array();
    $allblocks = FN_GetAllBlocks();
    foreach (is_array($allblocks) ? $allblocks : array() as $id => $values)
    {
        if (!FN_IsAdmin() && !FN_UserCanViewBlock($id))
            continue;
        $flags = array();
        if (empty($values['status']))
            $flags[] = "off";
        elseif ($positions !== null && !in_array($values['where'], $positions))
            $flags[] = "not shown by the theme";
        if (!empty($values['sections']))
            $flags[] = "only in: {$values['sections']}";
        if (!FN_IsAdmin() && FN_UserCanEditBlock($id))
            $flags[] = "edit";
        $out[] = "$id" . (!empty($values['type']) ? " [{$values['type']}]" : "") . " {$values['where']} \"{$values['title']}\"" . (count($flags) ? " " . implode(", ", $flags) : "");
    }
    return count($out) ? implode("\n", $out) : "no blocks";
});

FN_MCP_AddTool("get_block_content", "Html of a block (blocks/<id>/section.<lang>.html).", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "lang" => array("type" => "string"),
    ),
    "required" => array("id"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "block id");
    $blocks = FN_GetAllBlocks();
    if (!isset($blocks[$id]))
        FN_MCP_Fail("block not found: $id");
    if (!FN_IsAdmin() && !FN_UserCanViewBlock($id))
        FN_MCP_Fail("permission denied");
    $lang = FN_MCP_Lang(FN_MCP_Arg($args, "lang", ""));
    $folder = "{$_FN['src_application']}/blocks/$id";
    $file = "$folder/section.$lang.html";
    if (!file_exists($file))
        $file = "$folder/section.{$_FN['lang_default']}.html";
    $out = array("id" => $id, "lang" => $lang);
    if (!file_exists($file))
        $out['note'] = "no html file for this block";
    if (file_exists("$folder/section.php"))
        $out['php'] = true;
    $out['content'] = file_exists($file) ? file_get_contents($file) : "";
    return $out;
});

FN_MCP_AddTool("set_block_content", "Write the html of a block (blocks/<id>/section.<lang>.html).", array(
    "properties" => array(
        "id" => array("type" => "string"),
        "content" => array("type" => "string"),
        "lang" => array("type" => "string"),
    ),
    "required" => array("id", "content"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "block id");
    $blocks = FN_GetAllBlocks();
    if (!isset($blocks[$id]))
        FN_MCP_Fail("block not found: $id");
    if (!FN_UserCanEditBlock($id))
        FN_MCP_Fail("permission denied: you cannot edit block $id");
    if (!isset($args['content']) || !is_string($args['content']))
        FN_MCP_Fail("missing required argument: content");
    $lang = FN_MCP_Lang(FN_MCP_Arg($args, "lang", ""));
    $folder = "{$_FN['src_application']}/blocks/$id";
    if (!is_dir($folder))
        FN_MkDir($folder);
    $content = FN_MCP_LocalizeLinks($args['content'], "blocks/$id");
    FN_MCP_WriteContent("$folder/section.$lang.html", $content);
    FN_LogEvent("block content updated:$id ($lang)", "mcp");
    return array("file" => "blocks/$id/section.$lang.html", "bytes" => strlen($content));
}, true);

FN_MCP_AddTool("list_settings", "Admin. Site settings {name: value} (secrets hidden).", array(), function ($args)
{
    FN_MCP_RequireAdmin();
    $table = FN_XMDBTable("fn_settings");
    $rows = $table->GetRecords();
    $out = array();
    if (is_array($rows))
    {
        foreach ($rows as $r)
        {
            if (preg_match('/pass|secret|key/i', $r['varname']))
                continue;
            $out[$r['varname']] = $r['varvalue'];
        }
    }
    ksort($out);
    return $out;
});

FN_MCP_AddTool("update_setting", "Admin. Change a site setting (site_title, home_section, theme, maintenance...).", array(
    "properties" => array(
        "name" => array("type" => "string"),
        "value" => array("type" => "string"),
    ),
    "required" => array("name", "value"),
), function ($args)
{
    FN_MCP_RequireAdmin();
    $name = FN_MCP_Arg($args, "name", "", true);
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name))
        FN_MCP_Fail("invalid setting name");
    if (!isset($args['value']))
        FN_MCP_Fail("missing required argument: value");
    $value = is_array($args['value']) ? json_encode($args['value']) : "{$args['value']}";
    $table = FN_XMDBTable("fn_settings");
    $old = $table->GetRecordByPk($name);
    if (!is_array($old) || !isset($old['varname']))
        FN_MCP_Fail("unknown setting: $name");
    $r = $table->UpdateRecord(array("varname" => $name, "varvalue" => $value));
    if (!is_array($r))
        FN_MCP_Fail("database update failed");
    FN_ClearCache();
    FN_LogEvent("setting updated:$name", "mcp");
    return array("name" => $name, "value" => $value, "old_value" => $old['varvalue']);
}, true);

FN_MCP_AddTool("list_users", "Admin. Users: username, email, level, group, active, name (full=true: every field).", array(
    "properties" => array(
        "filter" => array("type" => "object", "description" => "{field: value}, e.g. {\"active\":\"1\"}"),
        "full" => array("type" => "boolean"),
    ),
), function ($args)
{
    FN_MCP_RequireAdmin();
    $filter = FN_MCP_Arg($args, "filter", array());
    $users = FN_GetUsers(is_array($filter) && count($filter) ? $filter : false);
    $full = !empty($args['full']);
    $out = array();
    if (is_array($users))
    {
        foreach ($users as $u)
        {
            $u = FN_MCP_PublicUser($u);
            if (!$full)
            {
                $short = array();
                foreach (array("username", "email", "level", "group", "active", "name", "surname") as $k)
                    $short[$k] = isset($u[$k]) ? $u[$k] : "";
                $u = $short;
            }
            $out[] = FN_MCP_NoEmpty($u);
        }
    }
    return array("count" => count($out), "users" => $out);
});

FN_MCP_AddTool("get_user", "A user without secrets (admin, or yourself).", array(
    "properties" => array("username" => array("type" => "string")),
    "required" => array("username"),
), function ($args)
{
    global $_FN;
    $username = FN_MCP_Arg($args, "username", "", true);
    if (!FN_IsAdmin() && $username != $_FN['user'])
        FN_MCP_Fail("permission denied");
    $u = FN_GetUser($username);
    if (!$u)
        FN_MCP_Fail("user not found: $username");
    return FN_MCP_NoEmpty(FN_MCP_PublicUser($u));
});

FN_MCP_AddTool("create_user", "Admin. New user.", array(
    "properties" => array(
        "username" => array("type" => "string"),
        "password" => array("type" => "string"),
        "fields" => array("type" => "object", "description" => "email, level (10 = admin, default 1), group (comma separated), active (default 1), other user fields"),
    ),
    "required" => array("username", "password"),
), function ($args)
{
    FN_MCP_RequireAdmin();
    $username = FN_MCP_Arg($args, "username", "", true);
    $password = FN_MCP_Arg($args, "password", "", true);
    if (FN_GetUser($username))
        FN_MCP_Fail("user already exists: $username");
    $newvalues = FN_MCP_Arg($args, "fields", array());
    if (!is_array($newvalues))
        $newvalues = array();
    foreach (array("email", "level", "group", "active") as $k)
    {
        if (isset($args[$k]) && !is_array($args[$k]))
            $newvalues[$k] = "{$args[$k]}";
    }
    $newvalues['username'] = $username;
    $newvalues += array("email" => "", "level" => "1", "group" => "", "active" => "1");
    $newvalues['level'] = "{$newvalues['level']}";
    $newvalues['active'] = "{$newvalues['active']}";
    unset($newvalues['apikey'], $newvalues['passwd'], $newvalues['password']);
    $r = FN_AddUser($newvalues, $password);
    if (!$r)
        FN_MCP_Fail("user creation failed" . (!empty($GLOBALS['_FN']['return']['errors']) ? ": " . json_encode($GLOBALS['_FN']['return']['errors']) : ""));
    FN_LogEvent("user created:$username", "mcp");
    return FN_MCP_NoEmpty(FN_MCP_PublicUser(FN_GetUser($username, false)));
}, true);

FN_MCP_AddTool("update_user", "Admin. Change user fields or password (not the API key).", array(
    "properties" => array(
        "username" => array("type" => "string"),
        "fields" => array("type" => "object", "description" => "e.g. {\"level\":\"10\",\"email\":\"a@b.c\"}"),
        "password" => array("type" => "string"),
    ),
    "required" => array("username"),
), function ($args)
{
    FN_MCP_RequireAdmin();
    $username = FN_MCP_Arg($args, "username", "", true);
    $old = FN_GetUser($username);
    if (!$old)
        FN_MCP_Fail("user not found: $username");
    $fields = FN_MCP_Arg($args, "fields", array());
    if (!is_array($fields))
        $fields = array();
    unset($fields['apikey'], $fields['passwd'], $fields['password'], $fields['username']);
    $password = FN_MCP_Arg($args, "password", "");
    if (count($fields) == 0 && $password == "")
        FN_MCP_Fail("nothing to update");
    //the stored hash is not saved again (it would be hashed twice): the new password is passed apart
    unset($old['passwd']);
    $newvalues = array_merge($old, $fields);
    $r = FN_UpdateUser($username, $newvalues, $password);
    if (!$r)
        FN_MCP_Fail("user update failed");
    FN_LogEvent("user updated:$username (" . implode(",", array_keys($fields)) . ($password != "" ? ",password" : "") . ")", "mcp");
    return FN_MCP_NoEmpty(FN_MCP_PublicUser(FN_GetUser($username, false)));
}, true);

FN_MCP_AddTool("list_groups", "User groups.", array(), function ($args)
{
    return array_values(FN_GetGroups());
});

FN_MCP_AddTool("get_log", "Admin. Last log events, newest first: date user context: event.", array(
    "properties" => array(
        "limit" => array("type" => "integer", "description" => "Default 50"),
        "context" => array("type" => "string", "description" => "cms, mcp, apikey..."),
    ),
), function ($args)
{
    FN_MCP_RequireAdmin();
    $limit = (int) FN_MCP_Arg($args, "limit", 50);
    if ($limit < 1 || $limit > 1000)
        $limit = 50;
    $context = FN_MCP_Arg($args, "context", "");
    $table = FN_XMDBTable("fn_log");
    $rows = $table->GetRecords($context != "" ? array("context" => $context) : false);
    if (!is_array($rows))
        $rows = array();
    $rows = array_values($rows);
    usort($rows, function ($a, $b)
    {
        return strcmp("{$b['date']}", "{$a['date']}");
    });
    $out = array();
    foreach (array_slice($rows, 0, $limit) as $r)
        $out[] = "{$r['date']} " . ($r['user'] != "" ? $r['user'] : "-") . " {$r['context']}: {$r['event']}";
    return count($out) ? implode("\n", $out) : "no events";
});

FN_MCP_AddTool("log_event", "Write an event in the site log with your name.", array(
    "properties" => array(
        "event" => array("type" => "string"),
        "context" => array("type" => "string", "description" => "Default mcp"),
    ),
    "required" => array("event"),
), function ($args)
{
    $event = FN_MCP_Arg($args, "event", "", true);
    $context = FN_MCP_Arg($args, "context", "mcp");
    FN_LogEvent("$event", $context);
    return array("logged" => true);
});

FN_MCP_AddTool("clear_cache", "Clear the site cache (writes already do it).", array(), function ($args)
{
    global $_FN;
    $allowed = FN_IsAdmin();
    if (!$allowed)
    {
        foreach (FN_GetAllSections() as $id => $v)
        {
            if (FN_UserCanEditSection($id))
            {
                $allowed = true;
                break;
            }
        }
    }
    if (!$allowed)
        FN_MCP_Fail("permission denied");
    FN_ClearCache();
    FN_LogEvent("cache cleared", "mcp");
    return array("cleared" => true);
});

//------------------------------- custom tools -------------------------------->
if (file_exists("{$_FN['src_application']}/include/mcp_tools.php"))
{
    include_once "{$_FN['src_application']}/include/mcp_tools.php";
}

//------------------------------- transport ----------------------------------->

$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : "GET";
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key, Mcp-Session-Id, MCP-Protocol-Version, Accept");
header("Access-Control-Expose-Headers: Mcp-Session-Id");

if ($method == "OPTIONS")
    FN_MCP_Respond(204);

if (empty($_FN['user']) || empty($_FN['auth_method']) || !in_array($_FN['auth_method'], array("apikey", "oauth")))
{
    // only API key and OAuth clients are accepted: cookies of a browser session are ignored
    FN_OAuthServerChallenge(FN_GetMcpUrl());
    FN_MCP_Respond(401, FN_MCP_Error(null, -32001, "unauthorized: send a valid API key or OAuth access token in the Authorization header (Bearer <token>)"));
}

if (!empty($_FN['mcp_site_maintenance']) && !FN_IsAdmin())
    FN_MCP_Respond(503, FN_MCP_Error(null, -32002, "site in maintenance mode"));

if ($method == "DELETE")
    FN_MCP_Respond(200, array("ok" => true));
if ($method == "GET")
{
    header("Allow: POST, DELETE, OPTIONS");
    FN_MCP_Respond(405, FN_MCP_Error(null, -32000, "method not allowed: this server does not open SSE streams, use POST"));
}
if ($method != "POST")
    FN_MCP_Respond(405, FN_MCP_Error(null, -32000, "method not allowed"));

$input = isset($_FN['INPUT']) && $_FN['INPUT'] !== "" ? $_FN['INPUT'] : file_get_contents("php://input");
$request = json_decode($input, true);
if ($request === null || !is_array($request))
    FN_MCP_Respond(400, FN_MCP_Error(null, -32700, "parse error"));

$batch = isset($request[0]) && is_array($request[0]);
$messages = $batch ? $request : array($request);
$responses = array();

foreach ($messages as $msg)
{
    $r = FN_MCP_HandleMessage($msg);
    if ($r !== null)
        $responses[] = $r;
}
if (count($responses) == 0)
    FN_MCP_Respond(202);
FN_MCP_Respond(200, $batch ? $responses : $responses[0]);

/**
 * Handle a single JSON-RPC message.
 *
 * @global array $_FN
 * @param array $msg
 * @return array|null response, null for notifications
 */
function FN_MCP_HandleMessage($msg)
{
    global $_FN;
    $id = isset($msg['id']) ? $msg['id'] : null;
    $isNotification = !array_key_exists('id', $msg);
    if (!is_array($msg) || !isset($msg['method']) || !is_string($msg['method']))
        return $isNotification ? null : FN_MCP_Error($id, -32600, "invalid request");
    $params = isset($msg['params']) && is_array($msg['params']) ? $msg['params'] : array();
    $rpcmethod = $msg['method'];

    if (strpos($rpcmethod, "notifications/") === 0)
        return null;

    switch ($rpcmethod)
    {
        case "initialize":
            $requested = isset($params['protocolVersion']) ? "{$params['protocolVersion']}" : "";
            $version = in_array($requested, $_FN['mcp_protocol_versions']) ? $requested : $_FN['mcp_protocol_versions'][0];
            return FN_MCP_Result($id, array(
                "protocolVersion" => $version,
                "capabilities" => array(
                    "tools" => array("listChanged" => false),
                ),
                "serverInfo" => array(
                    "name" => "finis-mcp",
                    "title" => "FINIS CMS: " . $_FN['site_title'],
                    "version" => trim(@file_get_contents("{$_FN['src_finis']}/VERSION")),
                ),
                "instructions" => "FINIS CMS of the site '" . $_FN['site_title'] . "' (" . FN_GetMcpSiteUrl() . "), user '" . $_FN['user'] . "'" . (FN_IsAdmin() ? " (admin)" : (FN_MCP_CanWrite() ? "" : " (read only)")) . ". Call site_guide first: it explains how the pages (sections) of this site are stored and edited and lists them. Changes are logged with your name.",
            ));
        case "ping":
            return FN_MCP_Result($id, new stdClass());
        case "tools/list":
            $tools = array();
            $canwrite = FN_MCP_CanWrite();
            foreach ($_FN['mcp_tools'] as $t)
            {
                if (!empty($t['write']) && !$canwrite)
                    continue;
                $tools[] = array("name" => $t['name'], "description" => $t['description'], "inputSchema" => $t['inputSchema']);
            }
            return FN_MCP_Result($id, array("tools" => $tools));
        case "tools/call":
            $name = isset($params['name']) ? "{$params['name']}" : "";
            if (!isset($_FN['mcp_tools'][$name]))
                return FN_MCP_Error($id, -32602, "unknown tool: $name");
            if (!empty($_FN['mcp_tools'][$name]['write']) && !FN_MCP_CanWrite())
                return FN_MCP_Result($id, array("content" => array(array("type" => "text", "text" => '{"error":"permission denied: the write tools are not enabled for this user, ask an administrator"}')), "isError" => true));
            $arguments = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : array();
            $output = array();
            $isError = false;
            try
            {
                $output = call_user_func($_FN['mcp_tools'][$name]['callback'], $arguments);
                if ($output === null)
                    $output = array("ok" => true);
            }
            catch (FN_MCP_ToolException $e)
            {
                $isError = true;
                $output = array("error" => $e->getMessage());
            }
            catch (Exception $e)
            {
                $isError = true;
                $output = array("error" => "internal error: " . $e->getMessage());
                FN_LogEvent("tool $name failed: " . $e->getMessage(), "mcp");
            }
            catch (Error $e)
            {
                $isError = true;
                $output = array("error" => "internal error: " . $e->getMessage());
                FN_LogEvent("tool $name failed: " . $e->getMessage(), "mcp");
            }
            // compact JSON (or plain text) only once: the result is read by a
            // language model, indentation and a structuredContent copy cost tokens
            $text = is_string($output) ? $output : json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $result = array(
                "content" => array(array("type" => "text", "text" => $text)),
                "isError" => $isError,
            );
            return FN_MCP_Result($id, $result);
        case "prompts/list":
            return FN_MCP_Result($id, array("prompts" => array()));
        case "resources/list":
            return FN_MCP_Result($id, array("resources" => array()));
        case "resources/templates/list":
            return FN_MCP_Result($id, array("resourceTemplates" => array()));
        case "logging/setLevel":
            return FN_MCP_Result($id, new stdClass());
        default:
            return FN_MCP_Error($id, -32601, "method not found: $rpcmethod");
    }
}
