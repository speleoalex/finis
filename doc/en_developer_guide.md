# FINIS Framework Developer Guide

## Introduction
This guide is intended for developers who want to extend the functionality of the FINIS framework by creating custom modules, integrating new features, or modifying existing ones.

## Framework Architecture

### Main Components
- **Core (FINIS.php)**: Main class that initializes and manages the framework
- **XMETATable**: Database management system with support for different drivers (see [Database Guide](en_database_guide.md))
- **Theme Engine**: System for managing and rendering themes (see [Theme Guide](en_theme_guide.md))
- **Section Manager**: Manages site sections and their routing
- **Module System**: System for extending functionality with modules
- **Extensions**: Folders with extra modules, sections, control center pages and tables, registered with `addExtension()` (see [Extensions](#extensions))

### Framework and Application Folders
FINIS keeps the framework apart from the site that uses it:

- `$_FN['src_finis']`: the `src/` folder of FINIS (core functions, modules, themes, control center)
- `$_FN['src_application']`: the folder of the site, where its `index.php` is (`"."` by default)
- `$_FN['datadir']`: the data folder (`misc` by default)

The application folder can contain:

| Path | Content |
|---|---|
| `index.php` | entry point that creates the `FINIS` object |
| `config.vars.local.php` | configuration of this installation (database, `display_errors`, ...) |
| `sections/<id>/` | the pages of the site |
| `modules/<name>/` | modules of the application (found only when the folder has a `section.php`) |
| `themes/<name>/` | themes of the application |
| `controlcenter/sections/<group>/<page>/` | control center pages |
| `include/methods/<name>.php`, `include/applications/<name>.php` | `fnapp` methods |
| `include/autoexec.d/*.php` | scripts run before each page |
| `include/mcp_tools.php` | MCP tools of the application |
| `include/auth/<method>.php` | replaces the authentication method of FINIS (`local`) |
| `extra/*.inc.php` | PHP files loaded at startup by every method, before the FINIS functions |
| `languages/<lang>/lang.csv` | translations |
| `plugins/<name>/` | settings pages shown in the control center |

When FINIS looks for a module, a control center page or a configuration file it searches the
application first, then FINIS, then the registered extensions. Files of FINIS that the browser
needs (images, CSS, JavaScript) are copied into the application folder at their first use
(`FN_PathSite()`), so `src/` does not have to be under the web root.

### The FINIS Class
The `index.php` of a site:

```php
<?php
require_once "../finis/src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->finis();
```

- `new FINIS($config = array())`: copies every entry of `$config` into `$_FN`, then runs the
  bootstrap (`src/include/finis.php`): configuration, database, login, language, sections,
  theme, translations. `src/include/config.vars.php` and the site's `config.vars.local.php` are
  loaded after the constructor values and replace them: set `display_errors`, `datadir`,
  `default_database_driver` and the database parameters in `config.vars.local.php`. A `theme`
  passed to the constructor wins over the theme chosen in the control center.
- `finis()`: runs the method named by `fnapp` in the query string, `website` when it is missing.
- `addExtension($path)`: registers an extension folder (see [Extensions](#extensions)); call it
  after the constructor and before `finis()`. It stops with an error when the folder does not exist.
- `setVar($name, $value)`: sets `$_FN[$name]`.
- `setTable($tablename, $params)`: parameters of one table, merged into the parameters that
  `FN_XMDBTable()`, `FN_XMDBForm()` and `FN_XMETATableEditor()` use for it (for example
  `default_database_driver` for a table whose descriptor names no `driver`).
- `runSection($section = "")`: prints a section without the theme (default: the current one,
  `$_FN['mod']`), only if the user may view it.
- `runFolder($folder)`: runs the `section.php` of a folder, or prints its static content
  (`section.<lang>.html`). It does not check permissions.
- `isConsole()`: true when the script runs from the command line.
- Any other method name is dispatched like `fnapp`: `$FINIS->controlcenter()` runs the control center.

### Methods (`fnapp`)
`index.php?fnapp=<name>` (or `$FINIS-><name>()`) includes the first file found among:

1. `{src_application}/include/applications/<name>.php`
2. `{src_finis}/include/applications/<name>.php`
3. `{src_application}/include/methods/<name>.php`
4. `{src_finis}/include/methods/<name>.php`

The name may contain only letters, digits and `_`: any other name, or a name without a file,
throws an exception. FINIS provides `website` (the default), `controlcenter`, `install`, `api`,
`print`, `thumb`, `captcha`, `cron`, `filemanager`, `notifications`, `oAuth`, `mcp` and
`oauthserver`; a file of the application with the same name replaces the one of FINIS. The file
is included inside a method of the class, so it must declare `global $_FN;`. Only `website` and
`print` run the scripts of `include/autoexec.d/`.

Example, `include/methods/export_csv.php` (`index.php?fnapp=export_csv`):

```php
<?php
global $_FN;
// only the administrators may download the list
if (!FN_IsAdmin())
{
    http_response_code(403);
    die();
}
$table = FN_XMDBTable("my_table");
$rows = $table->GetRecords();
header("Content-Type: text/csv; charset=UTF-8");
if (is_array($rows))
{
    foreach ($rows as $row)
    {
        echo $row['id'] . ";" . str_replace(";", ",", $row['name']) . "\n";
    }
}
```

### Execution Flow
1. User requests a page (`index.php`), which creates the `FINIS` object
2. The bootstrap loads `config.vars.php`, `config.vars.local.php`, the application's `extra/*.inc.php`, the core functions, the database layer, the authentication, the settings saved in `fn_settings`, the login (`FN_ManageLogin()`), the language, the theme and the sections
3. `finis()` runs the method of `fnapp` (default `website`)
4. `website` runs the scripts of `include/autoexec.d/` (FINIS first, then the application)
5. The `mod` parameter names the requested section; without it the home section is used (the control center setting, or `$_FN['home_section_force']` from `config.vars.local.php`). A home section that exists only in the application's `sections/` folder is used only by `index.php`
6. The theme template is rendered; `<!-- include section -->` runs the section: the module of its type (found with `FN_GetModulePath()`), otherwise the section folder (`section.php`, or `section.<lang>.html`)

### Output Modes of the Website Method
Besides the full page, `website` has two partial outputs, used by the ajax navigation and by
service workers that keep offline copies of the pages:

- `fnajax=1`: only the output of the section, without the theme:
  `<!DOCTYPE html><html><head><meta charset="..."><title>...</title></head><body>...</body></html>`.
  The head hooks run first (`FN_HtmlHeader()`), so the title is the one of the full page (for
  example the dbview `header.php` adds the title of the record). The dbview ajax navigation
  (`call_ajax()`) loads its links with `fnajax=1`.
- `fnshell=1`: the page with the theme, with the marker `<!--fnsection-->` in place of the
  section, which is not run. It is ignored when `fnajax` is also present, and works with the
  templates that use `<!-- include section -->`.

Every response of `website` carries these headers:

| Header | Value |
|---|---|
| `X-Finis-Visitor` | `public` for a GET by an anonymous visitor (the page is the same for every anonymous visitor and may be kept), `private` otherwise |
| `X-Finis-Section` | the section (`$_FN['mod']`) |
| `X-Finis-Lang` | the language (`$_FN['lang']`) |

## Extensions
An extension is a folder that adds modules, sections, control center pages and tables to a site
without copying its code into the application. The repository folder `fncommerce/` (the shop
used by `examples/ecommerce`) is the reference example:

```php
<?php
// examples/ecommerce/index.php
require_once "../../src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("../../fncommerce/");
$FINIS->finis();
```

`addExtension()` appends the path to `$_FN['path_extensions']`; several extensions are searched
in the order they were added, after the application and FINIS.

```
myextension/
  ├── modules/<name>/                        # modules, used from the extension folder
  ├── sections/<id>/                         # sections, copied into the site by the installer
  ├── controlcenter/sections/<group>/<page>/ # control center pages, used from the extension folder
  └── misc/fndatabase/                       # table descriptors and data folders, copied by the installer
```

- **Modules**: `FN_GetModulePath($name)` returns the folder of a module:
  `{src_application}/modules/<name>` when it contains `section.php`, otherwise
  `{src_finis}/modules/<name>`, otherwise `<extension>/modules/<name>`; it returns false when the
  module does not exist. `FN_GetAllModulePaths()` lists the modules of the three places. The
  section runner, the search and the translations use it, and `FN_InitSections()` registers the
  modules of the extensions as section types.
- **Control center**: `FNCC_GetSectionPath("<group>/<page>")` searches the application, FINIS and
  the extensions in this order; the menu merges the groups and pages of the three places, and
  `FNCC_GetSectionTitle()` takes the first `title.*.fn` found.
- **Installer**: during the first installation (`fnapp=install`, while `{datadir}/firstinstall`
  exists) the folders in `sections/` and the files and folders in `misc/fndatabase/` of each
  extension are copied into the application when they are missing. `FN_Install($path)` takes the
  file from an extension when FINIS has none.
- **Other files**: `FN_LoadConfig()`, `FN_HtmlEditConfFile()` and `FN_FromTheme()` also find the
  files of the extensions. A control center page of fncommerce edits the module settings so:

```php
<?php
defined('_FNEXEC') or die('Restricted access');
global $_FN;
// settings of the section "fncommerce": the defaults are in the config.php of the module
$_FN['mod'] = "fncommerce";
echo FNCC_HtmlEditConfFile("modules/fncommerce/config.php");
```

Limits: the extensions are known only after `addExtension()`, that is after the bootstrap run by
the constructor. The `header.php` and `style.css` of a module are read only for the modules of
FINIS (`src/modules/`): an extension ships them in its section folder
(`sections/<id>/header.php`, `sections/<id>/style.css`), which the installer copies.

## Creating Modules

### Basic Module Structure
A module is a section type: a section whose `type` is `mymodule` runs `modules/mymodule/section.php`.
Modules are in the `modules/` folder of the application, of FINIS (`src/modules/`) or of an
extension; a module of the application is found only when its folder contains `section.php`.

```
modules/
  └── modulename/
      ├── section.php       # Module main logic
      ├── config.php        # Default configuration of each section
      ├── default.xml       # Title and description of the module type
      ├── search.php        # Optional: results for the search module
      ├── languages/        # Translations
      │   ├── en/
      │   │   └── lang.csv
      │   └── it/
      │       └── lang.csv
      ├── css/              # Stylesheets
      └── js/               # JavaScript files
```

### Basic Module Example

Create a new folder in `modules/` (e.g., `modules/mymodule/`) with these files:

**section.php**:
```php
<?php
/**
 * Example module
 * @author DeveloperName
 */
global $_FN;

// Create the table of the module at the first use
if (!file_exists("{$_FN['datadir']}/fndatabase/my_custom_table.php")) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <name>id</name>
        <type>int</type>
        <extra>autoincrement</extra>
        <primarykey>1</primarykey>
    </field>
    <field>
        <name>title</name>
        <type>string</type>
        <size>255</size>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>content</name>
        <type>text</type>
    </field>
    <field>
        <name>created_date</name>
        <type>datetime</type>
    </field>
    <filename>my_custom_table</filename>
</tables>';
    FN_Write($xml, "{$_FN['datadir']}/fndatabase/my_custom_table.php");
}

// Module main output
function MyModule_Main() {
    global $_FN;

    // Example of reading GET/POST parameters
    $action = FN_GetParam("action", $_GET, "string");

    // Configuration of the current section (config.php of the module + saved values)
    $config = FN_LoadConfig();

    // Conditional logic based on action
    $output = "";
    switch ($action) {
        case "view":
            $output = MyModule_ViewItem();
            break;
        case "list":
        default:
            $output = MyModule_ListItems();
            break;
    }

    return $output;
}

// Example function to list items
function MyModule_ListItems() {
    global $_FN;
    $html = "<h2>" . FN_i18n("list_items") . "</h2>";

    // Example of database access
    $table = FN_XMDBTable("my_custom_table");
    $items = $table->GetRecords();

    if (is_array($items) && count($items) > 0) {
        $html .= "<ul>";
        foreach ($items as $item) {
            // values from the database are escaped before they go into the page
            $html .= "<li><a href=\"?mod={$_FN['mod']}&action=view&id=" . urlencode($item['id']) . "\">" . htmlspecialchars($item['title']) . "</a></li>";
        }
        $html .= "</ul>";
    } else {
        $html .= "<p>" . FN_i18n("no_items_found") . "</p>";
    }

    return $html;
}

// Example function to view an item
function MyModule_ViewItem() {
    global $_FN;
    $id = FN_GetParam("id", $_GET, "int");

    $table = FN_XMDBTable("my_custom_table");
    $item = $table->GetRecordByPrimaryKey($id);

    if (isset($item['id'])) {
        $html = "<h2>" . htmlspecialchars($item['title']) . "</h2>";
        $html .= "<div>{$item['content']}</div>";
        $html .= "<p><a href=\"?mod={$_FN['mod']}\">" . FN_i18n("back_to_list") . "</a></p>";
    } else {
        $html = "<p>" . FN_i18n("item_not_found") . "</p>";
        $html .= "<p><a href=\"?mod={$_FN['mod']}\">" . FN_i18n("back_to_list") . "</a></p>";
    }

    return $html;
}

// Main entry point
$output = MyModule_Main();
echo $output;
```

**config.php**:
```php
<?php
//[i18n]items per page
$config['items_per_page'] = 10;
//[i18n]enable comments {1=yes,0=no}
$config['enable_comments'] = 1;
//[i18n]default order
$config['default_sort'] = "title";
```

`FN_LoadConfig()` called without arguments by the module returns the configuration of the
current section: the values of the module's `config.php`, replaced by the ones saved for that
section (table `fncf_<section id>`). The `//[i18n]` comments are the labels of the configuration
editor, `{1=yes,0=no}` its options.

### Search Integration
The `search` module searches the sections the user may view, hidden sections included. For each
section it includes a `search.php`:

- for a section with a type: `<module folder>/search.php` (found with `FN_GetModulePath()`), which
  defines `FNSEARCH_module_<type>()`;
- for a section without a type: `{src_application}/sections/<id>/search.php`, which defines
  `FNSEARCH_section_<id>()`.

The file is included once (`include_once`) and the function is called for each section of that
type:

```php
<?php
// modules/mymodule/search.php
/**
 * @param array $tosearch_array the words to find
 * @param string $method "AND" or "OR"
 * @param array $sectionvalues the section searched
 * @param int $maxres maximum number of results to return
 * @return array results: title, link, text
 */
function FNSEARCH_module_mymodule($tosearch_array, $method, $sectionvalues, $maxres)
{
    $results = array();
    $table = FN_XMDBTable("my_custom_table");
    $rows = $table->GetRecords();
    if (!is_array($rows))
        return $results;
    foreach ($rows as $row)
    {
        $found = ($method == "AND");
        foreach ($tosearch_array as $word)
        {
            $match = (stripos($row['title'] . " " . $row['content'], $word) !== false);
            $found = ($method == "AND") ? ($found && $match) : ($found || $match);
        }
        // the module applies its own permissions here: the search checks only the section
        if ($found)
        {
            $results[] = array(
                "title" => $row['title'],
                "link" => FN_RewriteLink("index.php?mod={$sectionvalues['id']}&action=view&id={$row['id']}"),
                "text" => strip_tags($row['content']),
            );
        }
        if (count($results) >= $maxres)
            break;
    }
    return $results;
}
```

The results are grouped by section: at most 30 for each section (the function is asked one more
to know whether there are others) and 500 in all. Each group has the variables `count`, `more`,
`anchor` and, for dbview sections, `url_list` (the list of the section filtered by the same
text). The title and description of each section are searched too. A hidden section is searched
only when its module has a `search.php`. A section without type and without `search.php` is
also searched in its `section.<lang>.html` (the current language; the others only when it is
missing). A search box without the `method` field looks for all the words.

### Menu Visibility
`FN_GetMenuTree()` lists the sections the user may view (`FN_GetSections()`) and then asks
`FN_MenuSectionIsVisible($section)`, which hides the entries whose module would refuse the
content: a dbview section is shown only to the users of its `groupadmin`, `groupview` or
`groupinsert` groups (to everybody when `groupview` is empty), a `table_editor` section only to
the administrators. A `block_menu` container whose entries are all hidden is hidden too.
Administrators see everything. The function knows only these two modules: a module with its own
permissions should set the groups of the section (`group_view`) so that the menu and the module
agree. A theme or application that defines `FN_GetSectionProprieties($section_id)` adds the
values it returns to each menu entry.

### Control Panel Integration

A control center page is a folder `controlcenter/sections/<group>/<page>/` of the application,
of FINIS or of an extension. The groups of FINIS are `contents`, `settings`, `users_and_groups`
and `utilities`; a new group folder becomes a new menu group.

```
controlcenter/
  └── sections/
      └── settings/
          └── mymodule/
              ├── section.php
              ├── icon.png
              ├── title.it.fn
              └── title.en.fn
```

The page is opened with `?fnapp=controlcenter&opt=settings/mymodule`. The control center admits
the administrators and the users enabled for that page (Utilities > Control center users).

**section.php**:
```php
<?php
/**
 * Admin panel for mymodule
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;

// Check if user is administrator
if (!FN_IsAdmin()) {
    echo FN_i18n("access_denied");
    return;
}

// Settings of the section "mymodule": the form is built from the comments of config.php,
// the values are saved in the table fncf_mymodule
$_FN['mod'] = "mymodule";
echo FNCC_HtmlEditConfFile("modules/mymodule/config.php");
```

`FNCC_HtmlEditConfFile()` is `FN_HtmlEditConfFile($file, $formaction, $exit, $allow, $write_to_file, $mod, $block, $htmltemplate)`
with the template of the control center theme.

### The interface of a control center page

A control center page does not decide how it looks: it describes what it shows, and the
`FNCC_UI_*` helpers (`src/include/controlcenter_ui.php`) emit the markup, with `.fncc-*` classes
only. The look comes from `controlcenter/css/fncc-ui.css` and from the tokens a theme redefines,
so the same page works on every control center theme, in the light and in the dark palette, and
stays usable on a phone.

Rules: no `style=""` and no hard coded colour, no inline `onclick` (use the `data-fncc-*`
attributes read by `fncc-ui.js`), every text goes through `FN_Translate()`/`FN_i18n()`.

```php
echo FNCC_UI_Toolbar(array(
    FNCC_UI_Button(FN_i18n("create a new page"), array(
        "href" => "{$_FN['controlcenter']}opt=$opt&newsection=1",
        "variant" => "primary",
    )),
    FNCC_UI_Button(FN_Translate("delete"), array(
        "href" => $linkdelete,
        "variant" => "quiet icon danger",
        "icon" => "\xE2\x9C\x95\xEF\xB8\x8E",
        "confirm" => FN_i18n("you want to permanently delete this page?"),
    )),
));

echo FNCC_UI_Alert(FN_Translate("the data were successfully updated"), "success");
echo FNCC_UI_Card(FN_Translate("old versions"), FNCC_UI_Table($headers, $rows));
```

| Function | What it emits |
|---|---|
| `FNCC_UI_Button($label, $options)` | a link or a button; `variant` (`primary`, `danger`, `quiet`, `icon`, `sm`), `icon`, `confirm`, `title`, `attributes` |
| `FNCC_UI_Toolbar($items)` | a row of buttons; the item `"spacer"` pushes what follows to the right |
| `FNCC_UI_Alert($text, $type, $isHtml)` | a message (`info`, `success`, `warning`, `danger`) |
| `FNCC_UI_Card($title, $bodyHtml, $headerExtraHtml)` | a titled panel |
| `FNCC_UI_Table($headers, $rows, $emptyText)` | a table that becomes a list of cards on a phone |
| `FNCC_UI_Escape($text)` / `FNCC_UI_PlainText($text)` | escaping for HTML; `PlainText` first decodes the entities already in the data (section titles are stored as `Cavit&agrave;`) |

The output of `section.php` is wrapped by `FNCC_UI_Section()`, which adds the `.fncc-section`
container and, when the page has a `help/` folder, its instructions.

### Instructions of a page

`help/section.<lang>.html` inside the folder of the page is shown on top, in a collapsible
`<details>` box titled "How this page works". The file is looked up in the current language, then
in the default one, then in english, in the application first and then in FINIS, so a site can
replace the text of a page it customised. This is where the less obvious parts belong, instead of
filling the page with notes.

## Working with the Database

### Creating Tables
FINIS uses an XML-based table description system. Here's how to define a table:

```php
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <name>id</name>
        <type>int</type>
        <extra>autoincrement</extra>
        <primarykey>1</primarykey>
    </field>
    <field>
        <name>name</name>
        <type>string</type>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>description</name>
        <type>text</type>
    </field>
    <filename>my_table</filename>
</tables>';
FN_Write($xml, "{$_FN['datadir']}/fndatabase/my_table.php");
```

### CRUD Operations
FINIS offers a set of functions to manipulate data:

```php
// Get a table instance
$table = FN_XMDBTable("my_table");

// Insert a record (returns the saved record)
$newRecord = array(
    'name' => 'New item',
    'description' => 'Item description'
);
$record = $table->InsertRecord($newRecord);
$id = $record['id'];

// Read a record
$record = $table->GetRecordByPrimaryKey($id);

// Update a record: the values contain the primary key
$updatedRecord = array(
    'id' => $id,
    'name' => 'Updated name',
    'description' => 'Updated description'
);
$table->UpdateRecord($updatedRecord);

// Delete a record
$table->DelRecord($id);

// Get all records
$records = $table->GetRecords();

// Get filtered records
$filteredRecords = $table->GetRecords(array('name' => 'Filter'));
```

`UpdateRecord($values, $pkvalue)` with a second argument updates the record with that key (use
it to change the primary key). With the SQL drivers a filter of `GetRecords()` is a `LIKE`
pattern: when a value from the request must match one record, compare the field again on the
result. See the [Database Guide](en_database_guide.md) for the details of the drivers.

## Localization and Internationalization

### Translation Files
Translation files are in CSV format and are located in `languages/[lang]/lang.csv` and `modules/[module]/languages/[lang]/lang.csv`.

Example `lang.csv` file:
```
"original_text","translated_text"
"list_items","List items"
"no_items_found","No items found"
"back_to_list","Back to list"
"item_not_found","Item not found"
```

### Using Translations
```php
echo FN_i18n("my_translation_key");
```

## System Hooks
FINIS supports a hook system to extend functionality without modifying the core.

### Autoexec and On Site Change
- The files in `include/autoexec.d/` of FINIS, then the ones of the application, are executed in natural order before each page of the `website` and `print` methods (not by the control center, `api`, `mcp` and the other methods)
- The files in `src/include/on_site_change.d/` (FINIS only; names starting with `none_` are skipped) are executed by `FN_OnSitemapChange()` when sections or blocks are created, moved or deleted
- The `extra/*.inc.php` files of the application are loaded at startup by every method, before the FINIS functions: define functions and set `$_FN` keys there, do not call `FN_` functions at load time

### Hook Example
```php
<?php
// Save as include/autoexec.d/99_mymodule.php in the application
global $_FN;

// Add script only for certain pages
if ($_FN['mod'] == 'home') {
    $_FN['section_header_footer'] .= '<script src="modules/mymodule/js/script.js"></script>';
}
```

`FN_HtmlHeader()` builds the head of the page from `$_FN['section_header']`, the output of the
`header.php` of the module (FINIS modules only) and of the section (`sections/<id>/header.php`),
the CSS and JavaScript of the page and, last, `$_FN['section_header_footer']`.

### Callback Functions
The core calls these functions when the application defines them (for example in `extra/*.inc.php`):

| Function | Called |
|---|---|
| `FN_OnAddUser($values, $password)` | after `FN_AddUser()` saved a new user |
| `FN_OnUpdateUser($values, $password)` | after `FN_UpdateUser()` saved a user |
| `FN_OnDeleteUser($uservalues)` | after `FN_DeleteUser()` deleted a user |
| `FN_OnConfirmUser($uservalues)` | when a registration is confirmed (login module) |
| `FN_SendMailWelcome($uservalues)` | in place of the default welcome mail |
| `FN_PasswordVerifyConstraints_overwrite($password)` | password rules: returns an error message, `""` when the password is accepted |
| `FN_GetSectionProprieties($section_id)` | extra values (array) for each menu entry |

## The dbview Module
`src/modules/dbview` shows the records of a table as a list with search, filters and pages,
a record page, and the forms to insert and edit. The table and the options are in the section
configuration (`tables`, `groupview`, `groupinsert`, `groupadmin`, `search_fields`,
`search_orders`, `navigate_groups`, ...: see `src/modules/dbview/config.php`). The class is
`FNDBVIEW` (`FNDBVIEW.php`), with traits in `traits/` (CRUD, export, permissions, utilities,
view cache).

### Section Files
These files of the section folder (`{src_application}/sections/<id>/`) are used when present:

| File | Use |
|---|---|
| `custom_functions.php` | included at the start of every request of the section |
| `section.php` or `section.<lang>.html` | content shown above the list |
| `top.php` | included when the list is built |
| `grid_header.php` | output placed in `{html_header}` of the list template |
| `bottom.php` | included after the list |
| `viewheader.php`, `viewfooter.php` | output placed in `{header}` and `{footer}` of the record page |
| `bottom_edit.php` | included after the edit form (`op=edit`, and `op=updaterecord` after saving) |
| `exports.csv` | extra export links of the list (column `script` plus the template values, e.g. `title`), when `enable_export` is on |
| `default.png` | image of the records without a photo |
| `header.php`, `style.css` | head of the page and CSS (any section) |

`index.php?mod=<id>&file=<name>` includes the file `<name>` of the section folder instead of the
module, so a section can have its own scripts (an export, a print page). `<name>` must be a plain
file name (letters, digits, `_`, `-`, `.`, no path) and the visitor must be allowed to view the
records of the section (`CanViewRecords()`, otherwise "you are not authorized to view the data").
The files of the table above that the module includes by itself, and `header.php`, `footer.php`,
`section.php`, `search.php`, `pdf.php`, `default.xml.php`, `config.php`, are never called this
way: the parameter is ignored and the module runs. A script called with `file=` checks any other
permission it needs (for example `$dbview->CanViewRecord($id, $tablename)` for one record).

### Templates
The templates are looked up first in the section folder, then in the theme
(`themes/<theme>/modules/dbview/`, of the application and then of FINIS, through `FN_FromTheme()`),
then in the module: `list.tp.html` (list), `detail.tp.html` (record page), `detail_inner.tp.html`
(linked records; read from the section folder only when it also has `detail.tp.html`),
`formedit.tp.html` (edit form), `forminner.tp.html` (editor of the linked tables), `form.tp.html`
(new record), `history.tp.html` (versions), `users.tp.html` (users enabled on a record),
`toolbar.tp.html` (record toolbar). `form_offline.html` is looked up only in the theme and in the
module.

For the list template the order is: `themes/<theme>/sections/<id>/list.tp.html` of the
application, then `sections/<id>/list.tp.html`, then the list template of the theme, then
`$_FN['dbview_list_template']` (a file path, for every section without its own template), then
the module's.

Each item of `{items}` in `list.tp.html` has `{item_title}`, `{item_urlview}`, `{item_urledit}`,
`{item_urldelete}` (false when the user may not delete or edit the record), `{item_urlimage}` (thumbnail),
`{item_urlimage_fullsize}`, `{item_hasimage}` (true when the record has a photo file),
`{url_offlineform}`, `{itemvalues}` (title/value of the fields with `frm_showinlist`),
`{viewvalue_<field>}` and `{title_<field>}` for the same fields, and `{value_<field>}`: the raw
value of every scalar field of the record. `{value_<field>}` is not escaped: use
`{value_<field>.addHtmlEntities()}` for text typed by users. The module template shows the records
as cards (classes `dbview-cards`, `dbview-card`, `dbview-card-media`, `dbview-card-values`, ...).

The page links, the category filters and the search form of the list reload only the containers
`pageresults` and `collapseFilters`: `call_ajax(url, "pageresults,collapseFilters")` and
`<form onsubmit="return call_ajax_form(this, 'pageresults,collapseFilters');" method="get">`.
The URL is loaded with `fnajax=1`, the containers are replaced with the ones with the same id,
and the browser URL follows with the history API (back and forward work, the URL is a link to
the current page and filters).

The record toolbar is `{toolbar_html}` in `detail.tp.html`, `formedit.tp.html`,
`history.tp.html` and `users.tp.html`, built from `toolbar.tp.html` with the variable `{items}`
(`key`, `link`, `title`, `label`, `icon`, `active`). The keys are `list`, `prev`, `next`, `view`,
`history`, `edit` and `users`; the icons are UTF characters, changed with
`$_FN['dbview_toolbar_icons']`:

```php
$_FN['dbview_toolbar_icons'] = array("edit" => "✏", "list" => "≡");
```

### Application Keys
The application sets these keys in `config.vars.local.php` or, for the functions, in `extra/*.inc.php`:

| Key | Value |
|---|---|
| `$_FN['dbview_list_template']` | path of a `list.tp.html` for the sections without their own |
| `$_FN['dbview_toolbar_icons']` | array key => character, replaces the toolbar icons |
| `$_FN['dbview_excluded_values']` | function `($tablename)` returning `array(field => array(values))`: records with one of these values are left out of lists and searches and refused by the record page; not applied to the site administrators |
| `$_FN['dbview_view_cache']` | cache of the record pages for anonymous visitors: `array(section => seconds)`, or a number for every section (0 = off) |
| `$_FN['dbview_view_cache_tables']` | `array(section => array(tables))`: tables whose changes make the cached pages stale (default: the table of the section) |
| `$_FN['dbview_view_cache_files']` | array of extra glob patterns of files that render the page (the cache is stale when they change) |
| `$_FN['dbview_view_cache_keep']` | days after which unused cached pages are removed (default 30) |
| `$_FN['xmetadb_file_url']` | function `($table, $recordvalues, $field, $thumb)` returning the web address of a record file (`$table` is the `XMETATable`), false when there is no file |
| `$_FN['xmetadb_file_access']` | function `($tablename, $id, $field)` returning true when the user may read that file |

```php
<?php
// extra/restricted.inc.php
global $_FN;
// records of the table "caves" with status "restricted" only for the group "speleo"
$_FN['dbview_excluded_values'] = function ($tablename)
{
    global $_FN;
    if ($tablename != "caves" || FN_UserInGroup($_FN['user'], "speleo"))
        return array();
    return array("status" => array("restricted"));
};
```

The record page cache is used only for a GET with no parameters other than `mod`, `op=view`,
`id`, `lang` and `fnajax`, by an anonymous visitor who may see the record. Files:
`{datadir}/_cache/pages/<section>/<theme>/<lang>/<id div 1000>/<id>.html`. A file is valid
for the configured seconds, while it is newer than the tables of the page and than the files that
render it (section folder, dbview, forms, theme templates, `dbview_view_cache_files`). Saving or
deleting a record from dbview removes its files; `FN_ClearCache()` removes them all.

### Protected Record Files
By default a record file is served from the data folder. To protect the files, an application
sets `$_FN['xmetadb_file_url']` so that `getFileUrl()` returns the address of its own script
(for example an `fnapp` method that checks the user), and `$_FN['xmetadb_file_access']` for the
thumbnail endpoint. With `xmetadb_file_access` set, `fnapp=thumb` serves a thumbnail asked by
record (`t`, `i`, `c`: table, id, field; `d` only the site database) only when the function
returns true, refuses a path `f` inside the data folder, and dbview asks the list thumbnails by
record.

### Numeric Sort
A text field that holds numbers is sorted by value when it is the order field of the list:
`<frm_sort>numeric</frm_sort>` (or `abs`, by absolute value) in the field definition, or a field
class with a method `SortNumeric()` (returning true, `"numeric"` or `"abs"`). Empty values go
last and a comma is accepted as decimal separator. It applies on the SQL drivers, when the list
is sorted by one field.

### Offline Form
`op=offlineform` (with `id` for an update) downloads an HTML file with the form, to be filled in
without access to the site; the record page shows its link when `enable_offlineform` is on. The template is `form_offline.html`, with
`{form}`, `{formtitle}`, `{doctitle}`, `{mailsubject}`, `{mode}` (`insert` or `update`),
`{version}`, `{adminemail}` and `{titlefield}`. File and image fields are left out; the section
options `offlineform_hidden_fields` (fields not shown) and `offlineform_text_fields` (foreign keys
and lists shown as plain text inputs) take comma separated field names.

### Callbacks on Save
`$_FN['modparams'][<section>]['editorparams']['table']['function_on_insert']` is the name of a
function called with the inserted record; `function_on_update` is called with the posted values
after each save of the edit form (also when the values were refused). Set them in
`custom_functions.php` of the section:

```php
<?php
// sections/caves/custom_functions.php
global $_FN;
function caves_on_insert($record)
{
    FN_LogEvent("new cave {$record['id']}", "caves");
}
$_FN['modparams']['caves']['editorparams']['table']['function_on_insert'] = "caves_on_insert";
```

### Permissions
- `CanViewRecords()`: the list; admins, `groupadmin`, `groupview`, `groupinsert`, everybody when `groupview` is empty.
- `CanViewRecord($id, $tablename)`: one record; also `viewonlycreator`, the per-record view groups (`enable_permissions_each_records`, field `groupview`) and `$_FN['dbview_excluded_values']`.
- `CanEditRecords()`: needed by every write operation (`edit`, `new`, `del`, `updaterecord`, `insertrecord`, `users`, `admingroups`); false for anonymous visitors, so crawlers only see records. Admins, `groupadmin`, `groupinsert`, any logged user when `groupinsert` is empty.
- `CanUpdateRecord($row)`: saving an existing record (edit form and save): the users accepted by `IsAdminRecord($row)` (site admin, owner in `username` or `user`, `groupadmin`, and with `enable_permissions_edit_each_records` the groups in the `groupinsert` field of the record, or every logged user when it is empty) and the users enabled on the record; when per-record edit groups are on, nobody else, otherwise also `CanAddRecord()`.
- Delete: `enable_delete`, `IsAdminRecord($row)`, and a POST with the token of the confirmation page (a GET shows the confirmation).
- `UsersForm()` (users enabled on a record): `IsAdminRecord($row)`.
- Version history (`op=history`): `IsAdmin()`, that is site administrators and `groupadmin`.

### SQL Pagination
On the SQL drivers (`mysql`, `sqlite`, `sqlite3`, `sqlserver`), or with `search_query_native_mysql`,
the list query runs in the database: `COUNT(*)` for the total, `LIMIT` for the page, `GROUP BY`
for the category counts, column names in backquotes, ties ordered by primary key. With the other
drivers the same query is run by the xmetadb query engine. The `order` parameter is accepted only
when it is in `search_orders`, `defaultorder` or the primary key; the `rpp` parameter (records
per page) is capped at 200.

## MCP Tools from an Application
The MCP server (`index.php?fnapp=mcp`) includes `{src_application}/include/mcp_tools.php` after
its own tools. Register a tool with
`FN_MCP_AddTool($name, $description, $inputSchema, $callback, $write = false)`; the callback
receives the arguments array and returns an array or a string, and calls `FN_MCP_Fail($message)`
to return an error. Write tools (`$write = true`) are listed and allowed only for the
administrators and the group `$_FN['mcp_write_group']`. The tools run as the user of the API
key or OAuth token: check permissions with the usual functions. See the
[MCP Guide](en_mcp_guide.md).

## Debugging
`$_FN['display_errors']` is `"off"` by default (`src/include/config.vars.php`): set it to `"on"`
in the site's `config.vars.local.php` while developing (a value passed to the `FINIS`
constructor is overwritten). With `"off"`, `website` also discards what is printed outside the
theme template, for example an `echo` in an autoexec script. The control center always shows the
errors.

The framework offers several functions for debugging:

```php
// Display a structured array
dprint_r($myArray);

// Logging (table fn_log, context "cms")
FN_Log("Debug message");

// Logging with a context
FN_LogEvent("Record imported", "mymodule");

// Execution time
echo FN_GetExecuteTimer(); // time from start in seconds

// Partial timer
echo FN_GetPartialTimer(); // time since last call
```

The events are in the table `fn_log` (context, user, IP, date), shown in the control center under
Utilities > Event log, with filters by context, user, event and date.

## Best Practices

### Security
- Always sanitize user input with `FN_GetParam()`; cast numbers (`"int"`, `"float"`, `intval()`) before saving them or putting them in a query
- `FN_XMETADBQuery()` takes a plain SQL string, without bound parameters: prefer the table methods (`GetRecords($restr)`, `InsertRecord()`, `UpdateRecord()`), which escape the values, and never put request strings into a query
- Verify user authorization with `FN_UserInGroup()` or `FN_UserCanViewSection()`
- A value from the request goes into HTML or a template only escaped: `FN_GetParam("q", $_GET, "html")`, `htmlspecialchars()`, or `{var.addHtmlEntities()}` in the template; `FN_RewriteLink()` and `FN_TPL_ApplyTplString()` escape nothing
- Before a redirect to a URL from the request, check `FN_IsInternalUrl($url)` (true for URLs under `$_FN['siteurl']` and relative URLs without scheme)
- Use `FN_GetClientIp()` for the client address (it reads `X-Forwarded-For` only from the proxies in `$_FN['trusted_proxies']`)
- Tokens, codes and generated passwords come from `random_bytes()` or `random_int()`, never from `rand()`, `mt_rand()` or `uniqid()`
- Set cookies with `FN_SetCookie($name, $value, $options)` instead of `setcookie()`, for PHP 7.1 compatibility
- Write operations (save, delete) only on POST, with a token checked with `hash_equals()`
- Never pass the stored password hash to `FN_UpdateUser()`: it hashes again any non empty `passwd`

```php
// anti CSRF token bound to the user, the action and the record
$token = hash_hmac("sha256", "archive|{$_FN['user']}|$id", FN_GetSiteSecret());
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!hash_equals($token, FN_GetParam("fn_csrf", $_POST))) {
        die(FN_Translate("you may not do that"));
    }
    // ... change the data
}
echo "<form method=\"post\"><input type=\"hidden\" name=\"fn_csrf\" value=\"$token\" />...</form>";

// cookie with the options on every PHP version
FN_SetCookie("mycookie", $value, array(
    'expires' => time() + 86400,
    'path' => $_FN['urlcookie'],
    'secure' => FN_IsHttps(),
    'httponly' => true,
    'samesite' => 'Lax',
));

// change the email of a user without touching the password
$values = FN_GetUser($user, false);
unset($values['passwd']);
$values['email'] = $email;
FN_UpdateUser($user, $values);
```

See the "Login security" section of the [LLM Guide](Finis_Framework_LLM_Guide.md) for the
session cookie, the site secret (`{datadir}/secret.key.php`) and the login limits.

### PHP Compatibility
FINIS must run on PHP 7.1 as well as on PHP 8.x. Do not use functions of PHP 8 (`str_starts_with()`,
`str_contains()`), check constants added later (`defined("JSON_INVALID_UTF8_SUBSTITUTE")`) and
use `FN_SetCookie()` (the options array of `setcookie()` exists from PHP 7.3).

### Code Structure
- Maintain separation between logic and presentation
- Use configuration files for settings
- Document functions with PHPDoc comments
- Use framework functions instead of reimplementing the same functionality

### Performance
- `FN_GetGlobalVarValue($name, $maxtime = false)` and `FN_SetGlobalVarValue($name, $value, $ttl = 0)` keep values between requests: in APCu when it is available (with an optional time to live in seconds), otherwise in `{datadir}/_cache` (with file locks). `$maxtime` is a Unix time: a value stored before it is discarded. The APCu keys are not separated by site: put something of the site in the name when the value depends on it. `FN_ClearCache()` empties `{datadir}/_cache` only
- On SQL drivers dbview reads only the records of the current page; for anonymous visitors enable the record page cache (`$_FN['dbview_view_cache']`)
- Consider using optimized queries for large databases
- Load JS scripts asynchronously

## Advanced Examples

### Creating a Plugin
A plugin is a folder `plugins/<name>/` of the application that adds settings to the control
center. Nothing in it runs by itself: code that must run on every page goes in
`include/autoexec.d/`.

**Plugin structure**:
```
plugins/
  └── myplugin/
      ├── title.en.fn          # Title (or title.i18n.fn, translated)
      ├── config.php           # Optional: settings, editable under Settings
      └── controlcenter/
          ├── settings.php     # Page in the group "Plugins" of the control center
          └── icon.png
```

`config.php` uses the same comments as a module configuration and is read with
`FN_LoadConfig("plugins/myplugin/config.php")`.

**include/autoexec.d/50_myplugin.php**:
```php
<?php
global $_FN;
$config = FN_LoadConfig("plugins/myplugin/config.php");
if (!empty($config['enabled'])) {
    $_FN['section_header_footer'] .= '<script src="plugins/myplugin/js/script.js"></script>';
}
```

To react to user events, define the [callback functions](#callback-functions) in `extra/*.inc.php`:

```php
<?php
// extra/myplugin.inc.php
function FN_OnAddUser($values, $password)
{
    FN_LogEvent("new user {$values['username']}", "myplugin");
}
```

### Extending the Control Panel
To add functionality to the administration panel:

```php
<?php
// In controlcenter/sections/utilities/myplugin/section.php
global $_FN;

// Verify permissions
if (!FN_IsAdmin()) {
    echo FN_i18n("access_denied");
    return;
}

// Administration interface
echo "<h2>" . FN_i18n("my_plugin_admin") . "</h2>";

// Rest of the code to manage plugin settings
```

## Troubleshooting Common Issues

### Debugging Errors
- Enable error display in `config.vars.local.php`:
  ```php
  $_FN['display_errors'] = "on";
  ```
- Check the PHP logs and the event log (control center, Utilities > Event log; the monthly files in `misc/log/` written by older versions are shown there as archive)

### Database Problems
- Verify write permissions on `misc/fndatabase/` folders
- Check database connection for external drivers (MySQL, etc.)
- Use `$table->GetError()` to get detailed error messages

### Module Problems
- Ensure the folder structure is correct (a module of the application needs `section.php`)
- Verify that configuration files are accessible
- Check for name conflicts between modules: a module of the application hides the one of FINIS or of an extension with the same name
