<?php

/**
 * FINIS MCP server (Model Context Protocol, JSON-RPC 2.0 over HTTP).
 *
 * Endpoint: index.php?fnapp=mcp
 * Authentication: "Authorization: Bearer <apikey>" (or "X-Api-Key: <apikey>")
 * where the key belongs to a FINIS user (profile page -> API key).
 * Every tool runs with the identity of the key owner: permission checks use
 * the user's level and groups, and the log carries the username.
 *
 * Applications can add tools in {src_application}/include/mcp_tools.php
 * with FN_MCP_AddTool($name, $description, $inputSchema, $callback).
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
 * @return void
 */
function FN_MCP_AddTool($name, $description, $inputSchema, $callback)
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
    );
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
 * Section values with the title/description of every language.
 *
 * @global array $_FN
 */
function FN_MCP_SectionInfo($values)
{
    global $_FN;
    $out = $values;
    $out['titles'] = array();
    $out['descriptions'] = array();
    foreach ($_FN['listlanguages'] as $lang)
    {
        $out['titles'][$lang] = isset($values["title_$lang"]) ? $values["title_$lang"] : "";
        $out['descriptions'][$lang] = isset($values["description_$lang"]) ? $values["description_$lang"] : "";
    }
    $folder = "{$_FN['src_application']}/sections/{$values['id']}";
    $out['content_files'] = array();
    if (is_dir($folder))
    {
        foreach (glob("$folder/section.*") as $f)
            $out['content_files'][] = basename($f);
    }
    $out['url'] = FN_GetMcpSiteUrl() . "index.php?mod={$values['id']}";
    return $out;
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

//------------------------------- tools --------------------------------------->

FN_MCP_AddTool("whoami", "Return the FINIS user that owns the API key, with level, groups and permissions.", array(), function ($args)
{
    global $_FN;
    $user = FN_MCP_PublicUser(FN_GetUser($_FN['user']));
    return array(
        "username" => $_FN['user'],
        "is_admin" => FN_IsAdmin(),
        "user" => $user,
        "groups" => isset($user['group']) ? array_values(array_filter(explode(",", $user['group']))) : array(),
    );
});

FN_MCP_AddTool("site_info", "Return general information about the site: title, languages, theme, home section, version, urls.", array(), function ($args)
{
    global $_FN;
    return array(
        "site_title" => $_FN['site_title'],
        "site_subtitle" => $_FN['site_subtitle'],
        "siteurl" => FN_GetMcpSiteUrl(),
        "languages" => $_FN['listlanguages'],
        "lang_default" => $_FN['lang_default'],
        "theme" => $_FN['theme'],
        "home_section" => $_FN['home_section'],
        "maintenance" => !empty($_FN['maintenance']),
        "version" => trim(@file_get_contents("{$_FN['src_finis']}/VERSION")),
        "database_driver" => isset($_FN['dbtype']) ? $_FN['dbtype'] : "",
        "section_types" => array_keys(FN_GetAllSectionTypes()),
    );
});

FN_MCP_AddTool("list_sections", "List the pages (sections) of the site map as a flat list with parent and position. Administrators see every section, other users only the ones they can view.", array(
    "properties" => array(
        "parent" => array("type" => "string", "description" => "Return only the children of this section id. Empty = root level. Use recursive=true to get the whole tree."),
        "recursive" => array("type" => "boolean", "description" => "Return all the sections (default false)."),
        "include_disabled" => array("type" => "boolean", "description" => "Include disabled and hidden sections (default true)."),
    ),
), function ($args)
{
    global $_FN;
    $parent = FN_MCP_Arg($args, "parent", "");
    $recursive = (bool) FN_MCP_Arg($args, "recursive", false);
    $include_disabled = FN_MCP_Arg($args, "include_disabled", true);
    $include_disabled = ($include_disabled === false || $include_disabled === "false" || $include_disabled === 0) ? false : true;
    $all = FN_GetAllSections();
    $out = array();
    foreach ($all as $id => $values)
    {
        if (!$recursive && $values['parent'] != $parent)
            continue;
        if (!FN_IsAdmin() && !FN_UserCanViewSection($id))
            continue;
        if (!$include_disabled && (empty($values['status']) || !empty($values['hidden'])))
            continue;
        $out[] = array(
            "id" => $id,
            "title" => $values['title'],
            "type" => $values['type'],
            "parent" => $values['parent'],
            "position" => $values['position'],
            "status" => $values['status'],
            "hidden" => $values['hidden'],
            "can_edit" => FN_UserCanEditSection($id),
        );
    }
    return array("sections" => $out, "count" => count($out));
});

FN_MCP_AddTool("get_section", "Return all the properties of a section (page): titles and descriptions per language, type, dates, permissions, content files.", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Section id"),
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

FN_MCP_AddTool("list_section_types", "List the section types (modules) available to create pages.", array(), function ($args)
{
    global $_FN;
    $types = FN_GetAllSectionTypes();
    $out = array();
    foreach ($types as $name => $values)
    {
        $out[] = array(
            "name" => $name,
            "title" => isset($values['title']) ? $values['title'] : $name,
            "has_template" => file_exists("{$_FN['src_finis']}/modules/$name/section_template"),
        );
    }
    return array("types" => $out);
});

FN_MCP_AddTool("create_section", "Create a new page (section) in the site map. Administrators only. Returns the new section.", array(
    "properties" => array(
        "title" => array("type" => "string", "description" => "Title in the default language"),
        "id" => array("type" => "string", "description" => "Section id (directory name). Generated from the title when empty."),
        "type" => array("type" => "string", "description" => "Section type (module). Empty for a static html page. See list_section_types."),
        "parent" => array("type" => "string", "description" => "Parent section id. Empty = root level."),
        "titles" => array("type" => "object", "description" => "Titles per language, e.g. {\"it\":\"Chi siamo\",\"en\":\"About us\"}"),
        "descriptions" => array("type" => "object", "description" => "Descriptions per language"),
        "description" => array("type" => "string"),
        "keywords" => array("type" => "string"),
        "status" => array("type" => "string", "description" => "1 = enabled (default), 0 = disabled"),
        "hidden" => array("type" => "string", "description" => "1 = not shown in menus"),
        "group_view" => array("type" => "string", "description" => "Comma separated groups allowed to view (empty = everyone)"),
        "group_edit" => array("type" => "string", "description" => "Comma separated groups allowed to edit"),
        "content" => array("type" => "string", "description" => "Optional HTML content written in section.<lang>.html (links to other pages as index.php?mod=<section id>; absolute URLs of this site are converted to local links on save)"),
        "lang" => array("type" => "string", "description" => "Language of the content (default: site default language)"),
    ),
    "required" => array("title"),
), function ($args)
{
    global $_FN;
    FN_MCP_RequireAdmin();
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
});

FN_MCP_AddTool("update_section", "Update the properties of a section. Administrators can change every field; editors (group_edit) only title, description, keywords, status, hidden, dates. Content is changed with set_section_content.", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Section id"),
        "title" => array("type" => "string", "description" => "Title in the default language"),
        "titles" => array("type" => "object", "description" => "Titles per language"),
        "description" => array("type" => "string"),
        "descriptions" => array("type" => "object", "description" => "Descriptions per language"),
        "keywords" => array("type" => "string"),
        "status" => array("type" => "string"),
        "hidden" => array("type" => "string"),
        "startdate" => array("type" => "string"),
        "enddate" => array("type" => "string"),
        "parent" => array("type" => "string", "description" => "Move under another section (admin)"),
        "position" => array("type" => "integer", "description" => "Order among siblings (admin)"),
        "type" => array("type" => "string", "description" => "Section type (admin)"),
        "group_view" => array("type" => "string", "description" => "(admin)"),
        "group_edit" => array("type" => "string", "description" => "(admin)"),
        "level" => array("type" => "string", "description" => "(admin)"),
        "accesskey" => array("type" => "string"),
        "blocksmode" => array("type" => "string", "description" => "(admin)"),
        "blocks" => array("type" => "string", "description" => "(admin)"),
    ),
    "required" => array("id"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    $oldvalues = FN_GetSectionValues($id);
    if (!$oldvalues)
        FN_MCP_Fail("section not found: $id");
    if (!FN_UserCanEditSection($id))
        FN_MCP_Fail("permission denied: you cannot edit section $id");
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
});

FN_MCP_AddTool("delete_section", "Delete a section and its directory with all the content files. Administrators only. Sections with children are refused unless force=true (children are deleted too).", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Section id"),
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
});

FN_MCP_AddTool("get_section_content", "Return the HTML content of a static page: sections/<id>/section.<lang>.html (falls back to the default language file).", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Section id"),
        "lang" => array("type" => "string", "description" => "Language code (default: site default language)"),
    ),
    "required" => array("id"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    if (!FN_SectionExists($id))
        FN_MCP_Fail("section not found: $id");
    if (!FN_IsAdmin() && !FN_UserCanViewSection($id))
        FN_MCP_Fail("permission denied");
    $lang = FN_MCP_Lang(FN_MCP_Arg($args, "lang", ""));
    $folder = "{$_FN['src_application']}/sections/$id";
    $file = "$folder/section.$lang.html";
    $used = $lang;
    if (!file_exists($file) && file_exists("$folder/section.{$_FN['lang_default']}.html"))
    {
        $file = "$folder/section.{$_FN['lang_default']}.html";
        $used = $_FN['lang_default'];
    }
    $out = array("id" => $id, "lang" => $lang, "file_lang" => $used, "exists" => file_exists($file), "content" => "");
    if (file_exists($file))
        $out['content'] = file_get_contents($file);
    $out['has_php'] = file_exists("$folder/section.php");
    return $out;
});

FN_MCP_AddTool("set_section_content", "Write the HTML content of a static page: sections/<id>/section.<lang>.html. The user must be allowed to edit the section (administrator or member of group_edit).", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Section id"),
        "content" => array("type" => "string", "description" => "Full HTML content of the page (body fragment, no <html>/<body>). Links to other pages must be written as index.php?mod=<section id>; absolute URLs of this site are converted to local relative links on save."),
        "lang" => array("type" => "string", "description" => "Language code (default: site default language)"),
    ),
    "required" => array("id", "content"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    if (!FN_SectionExists($id))
        FN_MCP_Fail("section not found: $id");
    if (!FN_UserCanEditSection($id))
        FN_MCP_Fail("permission denied: you cannot edit section $id");
    if (!isset($args['content']) || !is_string($args['content']))
        FN_MCP_Fail("missing required argument: content");
    $lang = FN_MCP_Lang(FN_MCP_Arg($args, "lang", ""));
    $folder = "{$_FN['src_application']}/sections/$id";
    if (!is_dir($folder))
        FN_MkDir($folder);
    $file = "$folder/section.$lang.html";
    if (!FN_CanModifyFile($_FN['user'], $file))
        FN_MCP_Fail("permission denied on file " . basename($file));
    $content = FN_MCP_LocalizeLinks($args['content'], "sections/$id");
    if (false === FN_Write($content, $file))
        FN_MCP_Fail("cannot write " . basename($file));
    FN_ClearCache();
    FN_LogEvent("section content updated:$id ($lang)", "mcp");
    return array("id" => $id, "lang" => $lang, "file" => "sections/$id/section.$lang.html", "bytes" => strlen($content));
});

FN_MCP_AddTool("list_section_files", "List the files inside the directory of a section (sections/<id>/).", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Section id"),
        "path" => array("type" => "string", "description" => "Sub directory (optional)"),
    ),
    "required" => array("id"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    if (!FN_SectionExists($id))
        FN_MCP_Fail("section not found: $id");
    if (!FN_UserCanEditSection($id))
        FN_MCP_Fail("permission denied");
    $base = "{$_FN['src_application']}/sections/$id";
    $path = FN_MCP_Arg($args, "path", "");
    $dir = $path == "" ? realpath($base) : FN_MCP_SafePath($base, $path);
    if (!$dir || !is_dir($dir))
        FN_MCP_Fail("directory not found");
    $out = array();
    foreach (scandir($dir) as $f)
    {
        if ($f == "." || $f == "..")
            continue;
        $out[] = array(
            "name" => $f,
            "type" => is_dir("$dir/$f") ? "dir" : "file",
            "size" => is_file("$dir/$f") ? filesize("$dir/$f") : 0,
            "modified" => date("Y-m-d H:i:s", filemtime("$dir/$f")),
        );
    }
    return array("id" => $id, "path" => $path, "files" => $out);
});

FN_MCP_AddTool("read_section_file", "Read a text file inside the directory of a section.", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Section id"),
        "path" => array("type" => "string", "description" => "File path relative to sections/<id>/"),
    ),
    "required" => array("id", "path"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    if (!FN_SectionExists($id))
        FN_MCP_Fail("section not found: $id");
    if (!FN_UserCanEditSection($id))
        FN_MCP_Fail("permission denied");
    $file = FN_MCP_SafePath("{$_FN['src_application']}/sections/$id", FN_MCP_Arg($args, "path", "", true));
    if (!is_file($file))
        FN_MCP_Fail("file not found");
    if (!FN_IsAdmin() && FN_GetFileExtension($file) == "php")
        FN_MCP_Fail("permission denied: php files are reserved to administrators");
    return array("id" => $id, "path" => $args['path'], "content" => file_get_contents($file));
});

FN_MCP_AddTool("write_section_file", "Write a text file inside the directory of a section (creates sub directories). Non administrators can write only html files.", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Section id"),
        "path" => array("type" => "string", "description" => "File path relative to sections/<id>/"),
        "content" => array("type" => "string"),
    ),
    "required" => array("id", "path", "content"),
), function ($args)
{
    global $_FN;
    $id = FN_MCP_SafeId(FN_MCP_Arg($args, "id", "", true), "section id");
    if (!FN_SectionExists($id))
        FN_MCP_Fail("section not found: $id");
    if (!FN_UserCanEditSection($id))
        FN_MCP_Fail("permission denied");
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
    if (!FN_CanModifyFile($_FN['user'], $file))
        FN_MCP_Fail("permission denied on file " . basename($file));
    if (false === FN_Write($args['content'], $file))
        FN_MCP_Fail("cannot write the file");
    FN_ClearCache();
    FN_LogEvent("file written:sections/$id/$relative", "mcp");
    return array("id" => $id, "path" => $relative, "bytes" => strlen($args['content']));
});

FN_MCP_AddTool("list_blocks", "List the blocks (side contents shown in the theme positions).", array(), function ($args)
{
    $blocks = FN_GetAllBlocks();
    $out = array();
    foreach ($blocks as $id => $values)
    {
        if (!FN_IsAdmin() && !FN_UserCanViewBlock($id))
            continue;
        $out[] = array(
            "id" => $id,
            "title" => $values['title'],
            "type" => $values['type'],
            "where" => $values['where'],
            "position" => $values['position'],
            "status" => $values['status'],
            "sections" => $values['sections'],
            "can_edit" => FN_UserCanEditBlock($id),
        );
    }
    return array("blocks" => $out);
});

FN_MCP_AddTool("get_block_content", "Return the HTML content of a block: blocks/<id>/section.<lang>.html.", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Block id"),
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
    return array("id" => $id, "lang" => $lang, "block" => $blocks[$id], "exists" => file_exists($file), "content" => file_exists($file) ? file_get_contents($file) : "", "has_php" => file_exists("$folder/section.php"));
});

FN_MCP_AddTool("set_block_content", "Write the HTML content of a block: blocks/<id>/section.<lang>.html.", array(
    "properties" => array(
        "id" => array("type" => "string", "description" => "Block id"),
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
    $file = "$folder/section.$lang.html";
    if (!FN_CanModifyFile($_FN['user'], $file))
        FN_MCP_Fail("permission denied on file " . basename($file));
    $content = FN_MCP_LocalizeLinks($args['content'], "blocks/$id");
    if (false === FN_Write($content, $file))
        FN_MCP_Fail("cannot write " . basename($file));
    FN_ClearCache();
    FN_LogEvent("block content updated:$id ($lang)", "mcp");
    return array("id" => $id, "lang" => $lang, "bytes" => strlen($content));
});

FN_MCP_AddTool("list_settings", "List the site settings (fn_settings). Administrators only.", array(), function ($args)
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
            $out[$r['varname']] = array("value" => $r['varvalue'], "default" => $r['defaultvalue']);
        }
    }
    return array("settings" => $out);
});

FN_MCP_AddTool("update_setting", "Change a site setting (fn_settings). Administrators only. The new value is used from the next request.", array(
    "properties" => array(
        "name" => array("type" => "string", "description" => "Setting name, e.g. site_title, maintenance, home_section, theme"),
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
});

FN_MCP_AddTool("list_users", "List the users (without passwords and keys). Administrators only.", array(
    "properties" => array(
        "filter" => array("type" => "object", "description" => "Field => value filter, e.g. {\"active\":\"1\"}"),
    ),
), function ($args)
{
    FN_MCP_RequireAdmin();
    $filter = FN_MCP_Arg($args, "filter", array());
    $users = FN_GetUsers(is_array($filter) && count($filter) ? $filter : false);
    $out = array();
    if (is_array($users))
        foreach ($users as $u)
            $out[] = FN_MCP_PublicUser($u);
    return array("users" => $out, "count" => count($out));
});

FN_MCP_AddTool("get_user", "Return a user record (without secrets). Administrators, or the user themself.", array(
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
    return FN_MCP_PublicUser($u);
});

FN_MCP_AddTool("create_user", "Create a new user. Administrators only.", array(
    "properties" => array(
        "username" => array("type" => "string"),
        "password" => array("type" => "string"),
        "email" => array("type" => "string"),
        "level" => array("type" => "string", "description" => "User level: 10 = administrator"),
        "group" => array("type" => "string", "description" => "Comma separated groups"),
        "active" => array("type" => "string", "description" => "1 = active (default)"),
        "fields" => array("type" => "object", "description" => "Other fields of the users table"),
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
    $newvalues['username'] = $username;
    $newvalues['email'] = FN_MCP_Arg($args, "email", isset($newvalues['email']) ? $newvalues['email'] : "");
    $newvalues['level'] = "" . FN_MCP_Arg($args, "level", "1");
    $newvalues['group'] = FN_MCP_Arg($args, "group", "");
    $newvalues['active'] = "" . FN_MCP_Arg($args, "active", "1");
    unset($newvalues['apikey'], $newvalues['passwd'], $newvalues['password']);
    $r = FN_AddUser($newvalues, $password);
    if (!$r)
        FN_MCP_Fail("user creation failed" . (!empty($GLOBALS['_FN']['return']['errors']) ? ": " . json_encode($GLOBALS['_FN']['return']['errors']) : ""));
    FN_LogEvent("user created:$username", "mcp");
    return FN_MCP_PublicUser(FN_GetUser($username, false));
});

FN_MCP_AddTool("update_user", "Update a user. Administrators only (level, group, active, email, password and other fields). API keys cannot be changed here.", array(
    "properties" => array(
        "username" => array("type" => "string"),
        "fields" => array("type" => "object", "description" => "Fields to change, e.g. {\"level\":\"10\",\"email\":\"a@b.c\"}"),
        "password" => array("type" => "string", "description" => "New password (optional)"),
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
    return FN_MCP_PublicUser(FN_GetUser($username, false));
});

FN_MCP_AddTool("list_groups", "List the user groups.", array(), function ($args)
{
    return array("groups" => array_values(FN_GetGroups()));
});

FN_MCP_AddTool("get_log", "Return the last events of the site log (fn_log). Administrators only.", array(
    "properties" => array(
        "limit" => array("type" => "integer", "description" => "Max rows (default 50)"),
        "context" => array("type" => "string", "description" => "Filter by context (cms, mcp, apikey, ...)"),
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
    return array("events" => array_slice($rows, 0, $limit));
});

FN_MCP_AddTool("log_event", "Write a custom event in the site log, with the identity of the API key owner.", array(
    "properties" => array(
        "event" => array("type" => "string"),
        "context" => array("type" => "string", "description" => "Default: mcp"),
    ),
    "required" => array("event"),
), function ($args)
{
    $event = FN_MCP_Arg($args, "event", "", true);
    $context = FN_MCP_Arg($args, "context", "mcp");
    FN_LogEvent("$event", $context);
    return array("logged" => true);
});

FN_MCP_AddTool("clear_cache", "Clear the site cache. Users that can edit at least one section may call it.", array(), function ($args)
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

if (empty($_FN['user']) || empty($_FN['auth_method']) || $_FN['auth_method'] != "apikey")
{
    // only API key clients are accepted: cookies of a browser session are ignored
    header('WWW-Authenticate: Bearer realm="FINIS MCP"');
    FN_MCP_Respond(401, FN_MCP_Error(null, -32001, "unauthorized: send a valid API key in the Authorization header (Bearer <key>)"));
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
                "instructions" => "FINIS CMS server for the site '" . $_FN['site_title'] . "' (" . FN_GetMcpSiteUrl() . "). You are operating as user '" . $_FN['user'] . "'" . (FN_IsAdmin() ? " (administrator)" : "") . ". Pages are called sections: use list_sections/get_section to explore the site map, get_section_content/set_section_content to read and write the HTML of a page. Every change is logged with the name of the user.",
            ));
        case "ping":
            return FN_MCP_Result($id, new stdClass());
        case "tools/list":
            $tools = array();
            foreach ($_FN['mcp_tools'] as $t)
            {
                $tools[] = array("name" => $t['name'], "description" => $t['description'], "inputSchema" => $t['inputSchema']);
            }
            return FN_MCP_Result($id, array("tools" => $tools));
        case "tools/call":
            $name = isset($params['name']) ? "{$params['name']}" : "";
            if (!isset($_FN['mcp_tools'][$name]))
                return FN_MCP_Error($id, -32602, "unknown tool: $name");
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
            $text = is_string($output) ? $output : json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $result = array(
                "content" => array(array("type" => "text", "text" => $text)),
                "isError" => $isError,
            );
            if (is_array($output))
                $result['structuredContent'] = count($output) ? $output : new stdClass();
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
