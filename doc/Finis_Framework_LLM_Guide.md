# Coding Conventions

**IMPORTANT**: Use English for all comments and variable names.

# Creating a New Page

To create a new page, you need to create a new folder inside the `sections` directory. The name of the folder will correspond to the page's path. For example, if you create a `test` folder, the page will be accessible at `http://<your-domain>/test`.

Inside the new page's folder, you need to create three files:

*   `default.xml.php`: This file contains the page's metadata.
*   `section.html`: This file is the page's template.
*   `section.php`: This file contains the page's logic.

## `default.xml.php`

This file contains the page's configuration in XML format.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<fn_sections>
    <type>finis</type>
    <parent></parent>
    <position>30</position>
    <title>example</title>
    <description></description>
    <startdate></startdate>
    <enddate></enddate>
    <status>1</status>
    <hidden>0</hidden>
    <accesskey></accesskey>
    <keywords>example</keywords>
    <sectionpath>sections</sectionpath>
    <level></level>
    <group_view></group_view>
    <group_edit></group_edit>
    <blocksmode></blocksmode>
    <blocks></blocks>
    <title_en>example</title_en>
    <title_it></title_it>
    <description_en></description_en>
    <description_it></description_it>
</fn_sections>
```

**Important Fields:**

*   **`<title>`**: The page title.
*   **`<description>`**: The page description.
*   **`<status>`**: The page status (1 for active, 0 for inactive).
*   **`<hidden>`**: Whether the page is hidden (1 for hidden, 0 for visible).
*   **`<title_en>`**, **`<title_it>`**: The page titles for the different languages.
*   **`<description_en>`**, **`<description_it>`**: The page descriptions for the different languages.
*   **`<parent>`**: (Optional) The ID of the parent section. This is used to create a hierarchical menu structure. For example, if you set `<parent>admin_assets</parent>`, this section will appear as a sub-item under the "admin_assets" section in the menu.

## `section.html`

This file contains the page's HTML template. It uses a template syntax to display dynamic data.

```html
<h2>{section_title}</h2>
{i18n:description}
<br />
{text}
<!-- if {user} -->
{user}
<!-- end if {user} -->
<!-- if not {user} -->
{user}
<!-- end if not {user} -->
<p>
    SITE URL: {siteurl}
</p>
<!-- if {items} -->
<table>
    <!-- foreach {items} -->
    <tr>
        <td>{name}</td>
        <td>{title}</td>
    </tr>

    <!-- end foreach {items} -->
</table>
<!-- end if {items} -->
```

**Template Syntax:**

*   `{variable}`: Displays the value of a variable.
*   `{i18n:key}`: Displays a translated string.
*   `<!-- if {condition} --> ... <!-- end if {condition} -->`: Conditional block.
*   `<!-- foreach {array} --> ... <!-- end foreach {array} -->`: Loop.

## `section.php`

This file contains the page's PHP logic. Here you can retrieve data from a database, process user input, and prepare the data to be displayed in the template.

```php
<?php
global $_FN; // the contents of this variable are always passed to $SECTION

$SECTION = array();
$SECTION['text'] = FN_Translate("hello");
$SECTION['is_admin'] = FN_IsAdmin();
$table = FN_XMDBTable("fn_sectionstypes");
$items = $table->GetRecords();
$SECTION['items'] = $items;
```

The `$SECTION` variable is an array that is passed to the template. Each key of the array is available as a variable in the template. For example, `$SECTION['text']` is accessible as `{text}` in the `section.html` file.

### Special Case: `admin_assets` Section

The `sections/admin_assets/` directory contains a `section.php` file that directly calls `FN_XMETATableEditor` for the `rnt_products` table. This section serves as an administrative interface for managing assets. While it doesn't have a `default.xml.php` file itself, other sections can reference `admin_assets` as their parent using the `<parent>` tag in their `default.xml.php` files. This allows for logical grouping in the menu structure, even if `admin_assets` doesn't explicitly define itself as a menu item.


# Main Framework Functions

This section documents the main functions of the Finis framework that are available globally.

## Internationalization

### `FN_Translate($english_string, $uppercase_mode = "Aa", $language = "")`

This function translates a string into the current language. It is an alias for `FN_i18n`.

*   `$english_string`: The string to be translated.
*   `$uppercase_mode`: (Optional) The text formatting. Possible values are:
    *   `Aa`: Initial capital (default)
    *   `aa`: All lowercase
    *   `AA`: All uppercase
    *   `Aa Aa`: Initial capitals for each word
*   `$language`: (Optional) The language to translate to. If not specified, the current language is used.

**Example:**

```php
$SECTION['translated_text'] = FN_Translate("hello world"); // Returns "Hello world" in the current language
```

### `FN_i18n($constant, $language = "", $uppercase_mode = "")`

This is the main function for translation. It looks for the translation in language-specific `.csv` files.

## Parameter Management

### `FN_GetParam($key, $var = false, $type = "")`

This function retrieves a parameter from a variable (default `$_REQUEST`).

*   `$key`: The key of the parameter to retrieve.
*   `$var`: (Optional) The array from which to retrieve the parameter. If not specified, `$_REQUEST` is used.
*   `$type`: (Optional) The data type to return. Possible values are `html`, `int`, `float`, or the name of a custom function.

**Example:**

```php
$user_id = FN_GetParam("user_id", $_GET, "int"); // Retrieves 'user_id' from $_GET as an integer
```

## Content Management

### `FN_HtmlContent($folder, $use_cache = true)`

This function loads the content of a section, executing `section.php` if it exists, otherwise loading `section.html`.

### `FN_HtmlStaticContent($folder, $use_cache = false)`

This function loads static content from a `section.[lang].html` file.

## User Management

### `FN_UserInGroup($user, $group)`

This function checks if a user belongs to a specific group.

*   `$user`: The username.
*   `$group`: The group name.

**Example:**

```php
if (FN_UserInGroup('admin', 'administrators')) {
    // The user is an administrator
}
```

## Other Useful Functions

*   `FN_GetExecuteTimer()`: Returns the total execution time of the script.
*   `FN_GetPartialTimer()`: Returns the partial execution time since the last call to this function.
*   `FN_LogEvent($event, $context = "cms")`: Logs an event in the log.
*   `FN_AddNotification($notification_values, $users)`: Adds a notification for one or more users.
*   `FN_SendMail($to, $subject, $body, $is_html = false, $from = "")`: Sends an email.
*   `FN_Redirect($url)`: Redirects the user to another URL.
*   `FN_FromTheme($file, $absolute = true)`: Returns the path of a file in the current theme.

## Error display

`$_FN['display_errors']` is "off" in `src/include/config.vars.php`: set it to "on" in the site's
`config.vars.local.php` while developing (a value passed to `new FINIS(array(...))` is overwritten
by `config.vars.php`). With "off", `website.php` also discards what is printed outside the theme
template, e.g. an `echo` in an autoexec script. The control center and the file manager always
display errors.

# Application structure and entry points

## FINIS class (`src/FINIS.php`)

*   `new FINIS($config)` copies every key of `$config` into `$_FN`, then loads
    `src/include/finis.php`, which reads `src/include/config.vars.php` and
    `{src_application}/config.vars.local.php`: a key defined there overwrites the constructor
    value. `src_application` (default `.`) is the folder of the site: its `sections/`,
    `themes/`, `modules/`, `misc/` (data, `$_FN['datadir']`) and `config.vars.local.php`.
*   `$FINIS->finis()` runs `index.php?fnapp=<name>` (default `website`) through `__call()`: the
    name must match `^[A-Za-z0-9_]+$`, and the first file found among
    `{src_application}/include/applications/<name>.php`, `{src_finis}/include/applications/`,
    `{src_application}/include/methods/`, `{src_finis}/include/methods/` is included; otherwise
    an `Exception` is thrown. Methods of FINIS: `api`, `captcha`, `controlcenter`, `cron`,
    `filemanager`, `install`, `mcp`, `notifications`, `oAuth`, `oauthserver`, `print`, `thumb`,
    `website`.
*   `runSection($section)` runs one section if `FN_UserCanViewSection()` (framework mode),
    `runFolder($folder)` a folder, `setVar($id, $value)` sets `$_FN[$id]`,
    `setTable($tablename, $params)` sets `$_FN['tables'][$tablename]`, `isConsole()` returns
    `$_FN['consolemode']`.

## Extensions

`$FINIS->addExtension($path)` (before `finis()`) appends a folder to `$_FN['path_extensions']`
(it dies when the folder does not exist). An extension has the layout of a site: `modules/`,
`sections/`, `controlcenter/sections/`, `misc/fndatabase/`, `languages/`, ... Reference:
`fncommerce/`, loaded by `examples/ecommerce/index.php`.

*   Looked up after the application and FINIS by `FN_GetModulePath($module)` (an application
    module needs its `section.php`, a FINIS or extension module only its folder; false when not
    found), `FN_GetAllModulePaths()`, `FN_LoadConfig()`, `FN_FromTheme()`,
    `FNCC_GetSectionPath()` and `FNCC_GetSectionTitle()` (control center sections and menu),
    `FN_HtmlEditConfFile()` and the scan of the module types in `FN_InitSections()`.
*   The `header.php` and `style.css` of a module are read only from `{src_finis}/modules/<type>/`
    (a section adds its own `sections/<id>/header.php` and `style.css`): a module of the
    application or of an extension puts them in its sections.
*   `FN_FinisPathToApplicationPath()` does not look in the extensions: it copies a missing FINIS
    file into the application folder and returns the relative path.
*   The installer (`FN_Install()`) copies the `sections/` and `misc/fndatabase/` of the
    extensions into the site once, at the first install.

## Configuration keys of the core

| Key | Default | Meaning |
|---|---|---|
| `home_section_force` | "" | home section that wins over the one saved in the control center (deployments) |
| `datadir` | `misc` | writable data folder |
| `default_database_driver` | `xmlphp` | driver of the tables without their own |
| `display_errors` | "off" | see "Error display" |

The home section may also live in the application's `sections/`.

## Output of the website method

*   Response headers of every page: `X-Finis-Visitor: public` (anonymous GET, the same for
    every visitor) or `private` (logged user or POST), `X-Finis-Section`, `X-Finis-Lang`: a
    service worker keeps only the public pages.
*   `fnajax=1`: only the output of the section in a minimal HTML page, without the theme; the
    head hooks run first, so `<title>` is the one of the full page. Used by the ajax navigation
    of dbview (`call_ajax`).
*   `fnshell=1`: the page with the theme and the marker `<!--fnsection-->` in place of the
    section, which is not generated (`$_FN['fnshell']`, `FN_TPL_tp_create_section`).
*   The login and logout forms never post back to a `fnajax`/`fnshell` address.

## Menu

`FN_GetMenuTree()` lists a section only when `FN_MenuSectionIsVisible($section)` too: for
`dbview` sections the user must be in `groupadmin`, `groupview` or `groupinsert` of the module
config (or `groupview` empty), `table_editor` sections are shown only to the administrators;
other types follow the section permissions. A container whose entries are all hidden is left
out. Administrators see everything.

## Search

For each section the search module (`src/modules/search/section.php`) looks in the title and
description, then includes a `search.php` and calls its function with
`($tosearch_array, $method, $sectionvalues, $maxresults)`, which returns rows with `title`,
`link`, `text`:

*   typed section: `search.php` of the module folder found with `FN_GetModulePath($type)`
    (application, FINIS, extensions), function `FNSEARCH_module_<type>`;
*   section without type: `{src_application}/sections/<id>/search.php`, function
    `FNSEARCH_section_<id>`.

A hidden section is skipped unless its module has a `search.php` (then its title and
description are searched too). Static pages are searched in the current language (another
language only when the page does not exist in it). Results are grouped per section (at most 30
each, a flag when there are more, count and link to the filtered list); how they are shown
depends on the search template.

## Compatibility and helpers

*   The code must run on PHP 7.1: no arrow functions, typed properties, `??=`, `match`,
    `str_contains()`, `str_starts_with()`. Set cookies with
    `FN_SetCookie($name, $value, $opts)` (`src/include/functions_sessions.inc.php`): the options
    array of `setcookie()` (`expires`, `path`, `domain`, `secure`, `httponly`, `samesite`), also
    on PHP < 7.3 where SameSite is appended to the path.
*   `FN_GetGlobalVarValue($varname, $maxtime = false)` / `FN_SetGlobalVarValue($varname,
    $value, $ttl = 0)`: a value shared by all the requests, in APCu when available (`$ttl` only
    there), otherwise in `{datadir}/_cache/<md5>.cache` with `flock()`. `FN_ClearCache()` does
    not empty APCu.
*   `FN_BackupFile($file)` keeps a `*.bak~` copy before a content write (online editor, MCP).

## MySQL server busy

`xmetadb_mysql_connect()` retries a refused connection (errors 1040, 1203, and 1226 for
`max_user_connections`) for about 5 seconds; then a web request ends with a 503 page with
`Retry-After: 10` that reloads itself (no cookies, no account name). `FN_ManageLogin()` treats a
user that could not be read as anonymous for that request only and keeps the login cookies.
The mysql `GetRecordByPk()` returns false when the query fails and null when the record does not
exist.

# Database

The application uses a custom XML-based database system called `xmetadb`. The database files are located in the `misc/fndatabase/` directory. Each `.php` file in this directory represents a table.

## Table Structure

The table structure is defined in an XML format within each table file. The file contains a `<tables>` element, which in turn contains a series of `<field>` elements. Each `<field>` element defines a column in the table.

**Important**: Always start with an `id` field as the primary key with auto_increment for standard tables.

**Example: `fn_users.php`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0); ?>
<tables>
    <field>
        <name>username</name>
        <primarykey>1</primarykey>
        ...
    </field>
    <field>
        <name>email</name>
        ...
    </field>
    ...
</tables>
```

### Field Properties

**Primary Key with Auto-Increment:**
```xml
<field>
    <name>id</name>
    <type>int</type>
    <primarykey>1</primarykey>
    <extra>autoincrement</extra>
    <frm_show>0</frm_show>
</field>
```
**Note**: Use `autoincrement` (all lowercase, no underscore) not `auto_increment`

**Foreign Key:**
```xml
<field>
    <name>category_id</name>
    <frm_i18n>Category</frm_i18n>
    <foreignkey>fn_categories</foreignkey>
    <fk_link_field>id</fk_link_field>
    <fk_show_field>name</fk_show_field>
    <frm_type>select</frm_type>
    <frm_required>1</frm_required>
</field>
```
- `<foreignkey>`: name of the referenced table
- `<fk_link_field>`: field to use for the actual link (usually `id`)
- `<fk_show_field>`: field to display in the form (e.g., `name`)

**Unique Field:**
```xml
<field>
    <name>code</name>
    <frm_i18n>Unique Code</frm_i18n>
    <unique>1</unique>
    <size>32</size>
</field>
```

**Checkbox Field:**
```xml
<field>
    <name>is_available</name>
    <frm_i18n>Available</frm_i18n>
    <type>check</type>
    <frm_type>check</frm_type>
    <defaultvalue>1</defaultvalue>
</field>
```

**Select Field (Enum):**
```xml
<field>
    <name>access_level</name>
    <frm_i18n>Access Level</frm_i18n>
    <frm_type>select</frm_type>
    <frm_options>user,cashier,admin</frm_options>
    <frm_options_i18n>User,Cashier,Administrator</frm_options_i18n>
    <defaultvalue>user</defaultvalue>
</field>
```

**Decimal Field:**
```xml
<field>
    <name>balance</name>
    <frm_i18n>Balance</frm_i18n>
    <type>decimal</type>
    <frm_type>text</frm_type>
    <defaultvalue>0.00</defaultvalue>
</field>
```

**DateTime Field:**
```xml
<field>
    <name>created_at</name>
    <frm_i18n>Created At</frm_i18n>
    <frm_type>datetime</frm_type>
    <frm_show>0</frm_show>
</field>
```

**Password Field:**
```xml
<field>
    <name>password</name>
    <frm_i18n>Password</frm_i18n>
    <frm_type>password</frm_type>
    <frm_retype>1</frm_retype>
</field>
```
- `<frm_retype>1</frm_retype>`: requires re-typing the password for confirmation

### Common Field Properties

- `<name>`: The field name
- `<type>`: The SQL column type (`varchar`, `char`, `text`, `int`, `double`, `decimal`, `datetime`, `date`, ...). Legacy widget names (`string`, `check`, `image`) create `VARCHAR(255)`
- `<frm_type>`: The form widget (e.g., `text`, `select`, `check`, `image`, `password`, `datetime`); defaults to `<type>`
- `<frm_i18n>`: The translatable label for forms
- `<frm_required>`: Whether the field is required in forms (1 or 0)
- `<frm_show>`: Whether to show in forms (1 or 0)
- `<frm_setonlyadmin>`: Only admins can set this field (1 or 0)
- `<frm_allowupdate>`: Control update permissions (`0`, `1`, `onlyadmin`)
- `<size>`: Length of VARCHAR/CHAR columns (default 255), `"precision,scale"` for DECIMAL
- `<defaultvalue>`: Default value for the field
- `<unique>`: Whether the field must be unique (1 or 0)
- `<primarykey>`: Whether this is a primary key (1 or 0)
- `<extra>`: `autoincrement` for an auto-increment primary key
- `<defaultvalue>` is a literal: PHP expressions and variables in it are not evaluated. The
  `xmlphp`, `csv` and `serialize` drivers write it when a record is inserted without the field;
  MySQL uses `<mysql_default>` (an SQL expression of the column)
- `<frm_sort>`: `numeric` or `abs` makes dbview sort a text column by its value (SQL drivers)
- `<frm_functionview>`: function called as `function($values, $fieldname)` in the record view;
  the HTML it returns is shown with the field title (left out when empty or the field is hidden)

Widgets (`src/include/xmetadbfrm_fields/xmetadbfrm_field_<name>.php`, class
`xmetadbfrm_field_<name>`) added recently: `int` (digits and `-`), `number` (`min`, `max`),
`float` (`min`, `max`, `step` default `0.1`, `decimals` in the view), `json` (text area, the view
shows the decoded value), `fn_cron_text` (schedules `Y-M-D h:m:s weekday`, used by `fn_cron`).
`datetime` refuses a date that does not follow `frm_dateformat` or does not exist ("the date is
not valid, use the format ..."), also accepts a value already in the storage format
(`Y-m-d[ H:i[:s]]`, as API clients write it), saves the zero date as empty and shows an old
partial value (`1993`) as it is. `Verify()` calls `checkformvalue($value, $fieldvalues)` of
the field class, when it exists, for every non empty value: a non empty return is the error
message of the field.

## Interacting with the Database

The `xmetadb` system provides a set of functions for interacting with the database.

### `FN_XMDBTable($tablename, $params = array())`

This function returns an `XMETATable` object for the specified table.

**Example:**

```php
$users_table = FN_XMDBTable('fn_users');
$all_users = $users_table->GetRecords();
```

### `FN_XMDBForm($tablename, $params = array())`

This function returns an `xmetadb_frm` object, which is used to build forms for a specific table.

### `FN_XMETADBQuery($query, $params = array())`

This function allows you to execute SQL-like queries on the database.

**Example:**

```php
$query = "SELECT * FROM fn_users WHERE level > 5 ORDER BY username";
$high_level_users = FN_XMETADBQuery($query);
```

The query engine (`XMETADatabase`) reads the whole table through XMETATable and filters it in
PHP, so it works on every driver. To build it directly:
`new XMETADatabase($_FN['database'], $_FN['datadir'], FN_setCommonParams())`. Rules:

*   operators between spaces (`name = 'x'`, not `name='x'`): `=`, `<>`, `<`, `<=`, `>`, `>=`,
    `LIKE`, with `AND`, `OR` and parentheses; `!=`, `NOT`, `IN`, `IS NULL`, `BETWEEN` are not
    supported (`NOT (...)` ends with a fatal error);
*   unquoted integers are compared as numbers;
*   `LIMIT a,b` counts from 1 (`LIMIT 1,10` = the first ten); `LIMIT n` and `LIMIT 0,n` do not
    limit the result;
*   the characters `` ` `` `$` `{` `}` are removed from the WHERE and the PHP expression it becomes
    is evaluated only when it contains fields, operators, numbers and quoted strings. A quote in
    a value still changes the condition: filter request values with `GetRecords()` instead.

`FN_XMDBTable()`, `FN_XMDBForm()` and `FN_XMETADBQuery()` are in
`src/include/functions_xmetadb.inc.php`. The library is in `src/include/xmetadb/`
(`xmetadb_core.php`, `XMETATable.php`, `XMETAField.php`, `XMETADatabase.php`, drivers
`XMETATable_<driver>.php`; `XMETATable_sqlite.php` is legacy, PHP 5 only), in the namespace
`Xmetadb` with `class_alias()` to the old global names; `src/include/xmetadb.php` and
`xmetadb_query.php` are loaders kept for compatibility. `GetRecords($restr, $min, $length)`:
`$min` is a 0-based offset on the SQL drivers, a 1-based position on the file drivers.

### Files of the records

*   A `file`/`image` widget keeps the file name; the file is in
    `{datadir}/{database}/{table}/{primary key}/{field}/`. Use `$table->getFilePath($record,
    $field)`, `getThumbPath()`, `getFileUrl($record, $field, $thumb = false)` (false when the
    file is missing; `get_file()`, `get_thumb()`), `SetFile($field, $filepath, $filename = "")`
    to attach a file without an upload. Put the primary key in the values: the `mysql` driver
    handles the files only when it is there.
*   Uploads matching `/\.(php[0-9]?|phtml|phar|shtml|pl|cgi|sh|py)(\.|$)/i` are not saved
    (no error).
*   `index.php?fnapp=thumb`: by record `t` (table), `i` (key), `c` (field), `d` (database
    folder, default `fndatabase`), or `f` (path); `w`, `h` (default 32), `format` (`png`/`jpg`).
    Tables of the site database are read with `FN_XMDBTable()`; a missing file or a non image
    gives the generic icon.
*   `$_FN['xmetadb_file_url']`: callable `($table, $record, $field, $thumb)` (`$table` is the
    XMETATable) returning the address of the file, used by `getFileUrl()`; for an application
    script that checks the access.
*   `$_FN['xmetadb_file_access']`: callable `($tablename, $id, $field)` returning true when the
    visitor may read the file; then `fnapp=thumb` serves a thumbnail by record only when it
    allows it, only from the site database, and refuses an `f` path inside the data folder.

### Matching in the SQL drivers

*   **Primary key**: `GetRecordByPk()`, `DelRecord()` and `UpdateRecordBypk()` of the `mysql`,
    `sqlite3` and `sqlserver` drivers (and `UpdateRecordFast()` of `mysql`) touch only the
    record with exactly that key: the value is always quoted and escaped, and `%` and `_` are
    escaped (`xmetadb_escape_like()` with `ESCAPE '!'`, brackets on SQL Server). The case
    follows the database: MySQL tables (`utf8_bin`) are case sensitive, the default SQL
    Server collations are not, SQLite ignores the case of ASCII letters in `GetRecordByPk()`
    and `DelRecord()` (`LIKE`) but not in `UpdateRecordBypk()` (`=`).
*   **Restrictions**: a filter passed to `GetRecords($restr)` or `GetNumRecords($restr)` is
    still a `LIKE` pattern on the SQL drivers (`_` and `%` in the value are wildcards), while
    `xmlphp` compares the value exactly and reads a leading or trailing `%` as a wildcard
    that ignores the case. When a value from the request must find one record, compare the
    field again on the result: the OAuth login does it on the email (`strcasecmp`),
    `FN_GetUserByApiKey()` on the key (`hash_equals`), the unique check of `Verify()` on the
    fields of the filter (`strcasecmp`, so whether the case counts is still up to the driver).
*   **`int` fields**: the `mysql` and `sqlserver` drivers write a value without quotes only
    when it is an integer (`-?[0-9]+`); any other value is quoted and escaped, and the
    database converts it or refuses it (MySQL rounds `1.5` to 2 and in strict mode refuses
    `abc`, SQL Server refuses both). `sqlite3` casts the values with `intval`. Neither
    `GetByPost()` nor `Verify()` checks that they are numbers: cast a value from the request
    (`intval`) before saving it.

## Main Tables

*   `fn_users`: Stores user information.
*   `fn_groups`: Stores user groups.
*   `fn_sections`: Stores information about the pages of the site.
*   `fn_settings`: Stores application settings.
*   `fn_log`: Event log (`FN_LogEvent($event, $context)`), the `user` column is `$_FN['user']`.

## Login security

Helpers in `src/include/auth/local.php`; the login module
(`src/modules/login/functions_login.php`) uses them for registration and password recovery.

*   **Session cookie**: `FN_Login($user)` sets `fnuser` and `secid` (HttpOnly, SameSite=Lax,
    `secure` when `FN_IsHttps()`). `secid` is `FN_GetSecid($user, $passwd_hash)`, an
    HMAC-SHA256 of `user|passwd` with the site secret, checked by `FN_CheckUser()` with
    `hash_equals`. Changing the password invalidates the cookie. `FN_CheckUser()` also
    requires `active == 1`: a user deactivated after the login is logged out at the next
    request. `FN_Login()` returns false for an inactive user, and `FN_ManageLogin()` then
    checks the cookies sent by the client as on any other request (the check is skipped only
    after a successful `FN_Login()`).
*   **Remember me**: `FN_Login($user, $rememberlogin)` gives the cookies a lifetime of
    `remember_login_days` and sets the cookie `fnremember=1` with the same lifetime (cleared
    otherwise and by `FN_Logout()`). After saving a profile, call
    `FN_Login($user, FN_GetParam("fnremember", $_COOKIE))` to renew the cookies with the same
    duration (the lifetime restarts). Renew them only when the edited user is `$_FN['user']`:
    an administrator who edits another user must stay logged in with the own account.
*   **Password field**: `FN_GetUser()` returns the record with the bcrypt hash in `passwd`, and
    `FN_UpdateUser()` hashes any non empty `passwd` it receives (form type `cryptpasswd`). When
    the values to save come from `FN_GetUser()`, unset `passwd` (or set it to `""`, which
    `FN_UpdateUser()` drops) and pass the new password as the third argument: otherwise the
    stored hash is hashed again and the user can no longer log in. The registration always
    requires the password (`FN_GetUserForm()` makes it optional with `pk___xdb_fn_users` in the
    url, which the users editor sends).
*   **Fields managed by the framework**: `apikey`, `rnd`, `ip` and `registrationdate` are never
    taken from the post by the registration and the profile forms (login module and control
    center); the API key is created only by `regenerate_apikey` (`FN_ProfileApiKey()`). The
    user record put in `$_FN['return']` (printed as JSON by `fnapp=api`) must not contain
    `passwd`, `apikey`, `apikey_enc` or `rnd`: pass it through
    `FN_UserValuesForOutput($values, $passwordfield = "")` (`src/include/functions.inc.php`), as
    `op=profile`, `op=editreg` and the control center profile do.
*   **Templates**: the query string that the login and logout forms put in `{formaction}` is
    escaped with `htmlspecialchars`; `FN_RewriteLink()` and `TPL_ApplyTplString()` escape
    nothing, so a value from the request goes into a template only after `FN_GetParam(...,
    "html")` or `{var.addHtmlEntities()}`.
*   **Site secret**: `FN_GetSiteSecret()` reads `{datadir}/secret.key.php` (starts with
    `<?php exit(0); ?>`, mode 0600) or creates it once with `random_bytes(32)`. Deleting the
    file logs out every user. Never commit it.
*   **Server session**: `FN_GetSessionValue($key)` and `FN_SetSessionValue($key, $value)`
    (`src/include/functions_sessions.inc.php`) keep values in
    `{datadir}/_sessions/<fnsid>.session`, removed one hour after the last write.
    `FN_GetSessionId()` takes `fnsid` only from the cookie and accepts only 32 hex chars;
    otherwise it creates a random id and sends it in a session cookie (HttpOnly,
    SameSite=Lax, `secure` when `FN_IsHttps()`). A `fnsid` in the url is ignored: API
    clients without a cookie jar send `Cookie: fnsid=<id>` (the id is in `$_FN['return']`).
*   **Passwords**: `FN_VerifyUserPassword($user, $password)` uses only `password_verify` for
    modern hashes (the stored hash sent as password is refused) and rehashes when
    `password_needs_rehash`. Legacy md5 and plaintext values are compared with `hash_equals`
    and rehashed at the first successful login. An empty stored password never matches.
*   **Brute force**: `FN_LoginAttempt($user)` runs before the password check and returns
    false after `login_max_attempts` attempts of the same username from the same IP in
    `login_lockout_minutes`; the counter is `{datadir}/_login_attempts/<sha256(user|ip)>.cache`,
    outside the cache, so `FN_ClearCache()` does not reset it: to unlock a user before the
    end of the window, delete the folder. Once in the window (`_login_attempts/cleanup`
    keeps the time) the counters not updated in it are removed. `FN_LoginResetAttempts($user)`
    runs after a successful login. A locked out login gets the usual generic error and one
    `FN_Log` entry.
*   **Client IP**: `FN_GetClientIp()` gives the address used by the limiter. It is
    `REMOTE_ADDR`, unless that is one of `$_FN['trusted_proxies']`: then it is the last
    address in `X-Forwarded-For` not added by a trusted proxy (the addresses on the left are
    sent by the client and can be forged). Behind a reverse proxy, without the key all the
    clients share the address of the proxy, so anyone can lock out a username for everybody.
    The lockout log entry names the client address; the `ip` field of `fn_log` and the other
    modules still record `REMOTE_ADDR`.
*   **Captcha**: the session key `captcha` is an array with one code per form, drawn by
    `fnapp=captcha&t=<name>`: `fnlogin_code` for the login when `$_FN['enable_captcha']`,
    `security_code` for the registration when `enable_captcha` is set in the login module
    config. `FN_NewCaptchaCode($name)` sets the code of one form and keeps the others, so
    both forms work in the same session. The posted code is compared with `hash_equals` and
    used once, right or wrong: `FN_ManageLogin()` checks and removes the login code only on a
    login post with username and password (other requests, like the image, leave it as it
    is), the registration removes its code, and each form shows a new one. A wrong captcha
    does not count as a login attempt. The image needs an `index.php` that calls
    `$FINIS->finis()`: `website()` does not serve `fnapp`.
*   **Usernames**: `FN_AddUser()` saves the username in lowercase, and the login, the
    registration, the resend of the code and the password recovery lowercase the typed name,
    unless `$_FN['username_case_sensitive']` is set: then names are saved and looked up as
    typed. The registration mails the code saved with the user to the address saved with it.
    A username made only of dots (`.`, `..`) is refused: with the `xmlphp` driver it would
    name the folder of the users or its parent. `FN_AddUser()` returns false, and the
    registration and the installer show "the value is not valid" on the field.
    `FN_AddUser()` returns true, and calls the hook `FN_OnAddUser()`, only when
    `InsertRecord()` returns the saved record: an existing username is refused (`xmlphp`
    returns an error string), except with the `serialize` driver, which overwrites it.
    The registration confirms and logs in the user, or mails the code, only when
    `FN_AddUser()` returns true: when another registration saved the same username first,
    it shows the form again with the errors of `Verify()` ("this value already exists").
*   **Tokens in `fn_users.rnd`**: password recovery stores `<32 hex>.<timestamp>` (valid
    `recovery_link_minutes`), registration confirmation stores `<32 hex>` (single use). One
    is never accepted in place of the other, both are compared with `hash_equals`, and
    recovery answers the same way for existing and unknown users.
*   **Registration code**: kept only while the registration waits for confirmation (user
    not active, `rnd` of 32 hex). `FN_UpdateUser()` clears it when the user is activated
    (confirmation, control center, api) or an active user is deactivated, unless the same
    update sets `rnd`; recovery leaves it as it is and sends no token. The resend
    (`op=send_code`) mails the same code only to such a user, and answers with the same page
    and `$_FN['return']` for any username, without the email address. Old 5 character codes
    are not resent: an administrator activates the user.
*   **OAuth**: `FN_ManageLoginWithOpenAuthentication()` sends a random `state` kept in the
    session (`session_oauth_state`, single use) and refuses callbacks without it. A callback
    with `error` (e.g. `access_denied`) ends the login: the state is removed, the error code is
    logged and the user is not sent to the provider again. When the
    provider returns `email_verified` or `verified_email`, it must be true. The return url
    `session_oauth_returnurl` is followed only if `FN_IsInternalUrl($url)` (under
    `$_FN['siteurl']`, or relative without scheme). The user logged in is the one whose
    `email` is exactly the address given by the provider (`_` and `%` are not wildcards; the
    case is ignored only where the filter of the driver ignores it: `sqlite3` and the
    default SQL Server collations, not `mysql` and `xmlphp`). Without one, a new user is
    created when `$_FN['enable_registration']`, with the address as username (lowercase
    unless `$_FN['username_case_sensitive']`), and logged in with the saved username. If it
    is not created (e.g. another account already has that username), nobody is logged in
    and the refusal is logged.
*   **Random values**: tokens, captcha codes and generated passwords use `random_bytes` or
    `random_int`; do not use `rand`, `mt_rand` or `uniqid` for anything secret.

Configuration keys (in `config.vars.local.php`, defaults in the code):

| Key | Default | Meaning |
|---|---|---|
| `remember_login_days` | 30 | lifetime of the login cookies with "remember me" (`remember_login` enabled) |
| `recovery_link_minutes` | 60 | validity of a password recovery link |
| `login_max_attempts` | 5 | login attempts allowed per username and IP |
| `login_lockout_minutes` | 15 | time window of the attempts, and longest lockout |
| `trusted_proxies` | "" | reverse proxies whose `X-Forwarded-For` is trusted: IP addresses, comma separated or an array (no ranges) |

## API keys and MCP server

Every user can have a personal API key (48 hex chars); the key is found by its hash (`apikey`
field of `fn_users`, `v1$<sha256>`, `frm_show=0`; creation date in `apikey_date`) and a copy
encrypted with the site secret is kept in `apikey_enc` (`e1$`, AES-256-GCM, key derived from
`FN_GetSiteSecret()`) so that the administrators can show it. The key
is generated on request (never automatically) and shown once to its owner in the profile
pages (control center `users_and_groups/profile` and the login module `op=editreg`:
"Generate API key" / "Revoke API key" buttons, anti CSRF `apikey_token`); administrators
manage every key in the control center section `users_and_groups/mcp` (show the key of a
user, logged; generate for a user; revoke; allow the write tools). A new password revokes the key
(`FN_UpdateUser()`, unless the password is the same: `FN_SamePassword()`). Helpers in
`src/include/auth/local.php`:

*   `FN_GenerateApiKey($user)` (`random_bytes(24)`, stores `FN_ApiKeyRecord()`, returns the
    key once; without a random source it logs the error and returns ""), `FN_RevokeApiKey($user)`,
    `FN_ApiKeyInfo($user)` (`exists`, `created`, `legacy` = key stored in clear by an old version,
    migrated at its first use, `viewable`), `FN_ApiKeyHash($key)`, `FN_ApiKeyReveal($user)` (the
    key decrypted from `apikey_enc` and checked against the hash, "" for a key stored only
    hashed), `FN_ApiKeyEncrypt()` / `FN_ApiKeyDecrypt()`
*   `FN_GetRequestApiKey()`: reads `Authorization: Bearer <key>` or `X-Api-Key`; on the MCP
    endpoint only (`fnapp=mcp`) also `&apikey=<key>` in the URL (headers win)
*   `FN_GetUserByApiKey($apikey)`, `FN_LoginByApiKey($apikey)` (sets `$_FN['user']`,
    `$_FN['uservalues']` and `$_FN['auth_method'] = "apikey"`)
*   `FN_UserCanUseApiKey($user)` (`$_FN['apikey_groups']`, "" = everybody),
    `FN_UserCanWriteByApiKey($user)` (administrators and the group `$_FN['mcp_write_group']`,
    default `mcp_write`; `FN_McpWriteGroup()`, `FN_SetUserGroup()`)
*   `FN_ProfileApiKey($user, &$tplvars)`: fills `{apikey_allowed}`, `{apikey_exists}`,
    `{apikey_status}`, `{apikey_new}`, `{apikey_message}`, `{apikey_token}`, `{apikey_write}`,
    `{mcp_url}`, `{mcp_endpoints}` for the profile templates and handles the
    `generate_apikey` / `revoke_apikey` POST
*   `FN_GetMcpEndpoints()`: the CMS endpoint plus `$_FN['mcp_endpoints']` (name => url) of
    the application; `FN_ApiKeyFormToken()`
*   `FN_EnsureUserApiKeyField()`: adds the fields to an old `fn_users.php` definition

`FN_ManageLogin()` tries the API key when no cookie session exists, so any request
(`fnapp=api`, `fnapp=mcp`, ...) can be authenticated with the header.

`src/include/methods/oauthserver.php` (`index.php?fnapp=oauthserver`) is an OAuth 2.1
authorization server for the MCP clients (Claude Desktop, claude.ai): dynamic client
registration, login with the FINIS login form plus a consent page, PKCE S256, access token
1 h, single use refresh token 30 days. Client ids, codes and tokens are not stored: they
are signed with `FN_GetSiteSecret()` (`fnoa.<payload>.<hmac>`, helpers in
`src/include/functions_oauth_server.inc.php`); codes and refresh tokens leave a marker in
`misc/_oauth/` when used. Tokens carry a fingerprint of the user's API key and password,
so a new API key or password revokes them; only users allowed by `FN_UserCanUseApiKey()`
get them. `FN_ManageLogin()` tries `FN_OAuthServerLoginByToken()` before the API key and
sets `$_FN['auth_method'] = "oauth"`. An MCP endpoint answers 401 with
`FN_OAuthServerChallenge($endpointUrl)` (`WWW-Authenticate: Bearer resource_metadata=...`).

`src/include/methods/mcp.php` (`index.php?fnapp=mcp`) is an MCP server (JSON-RPC 2.0,
Streamable HTTP, POST only) that accepts **only** API key or OAuth authentication (no
cookies) and runs every tool as the user: permission checks use `FN_IsAdmin()`, `FN_UserCanEditSection()`,
`FN_UserCanEditBlock()`, `FN_CanModifyFile()`, and mutations call
`FN_LogEvent($msg, "mcp")` so `fn_log.user` is the real user. Tools are registered with
`FN_MCP_AddTool($name, $description, $inputSchema, $callback, $write = false)`; a tool throws
`FN_MCP_Fail($message)` to return an error. Write tools (`$write = true`) are listed and
allowed only when `FN_MCP_CanWrite()` (administrators and the group `$_FN['mcp_write_group']`). Applications add their own tools in
`{src_application}/include/mcp_tools.php`. Content written by `set_section_content`,
`set_block_content` and `create_section` goes through `FN_MCP_LocalizeLinks()`
(= `FN_RewriteLinksAbsoluteToLocal($content, "sections/$id")`), the same conversion the
editor applies on save: absolute URLs of the site become relative links; page links must
be written as `index.php?mod=<id>` (rewritten by the template engine at render time).
Results are compact (JSON without indentation and empty fields, or plain text, no
`structuredContent`). The `site_guide` tool (called first, as the `initialize`
instructions say) explains how the pages of the site are stored and edited, the block
positions shown by the theme and the site map (`FN_MCP_SiteGuide()`; applications add
`$_FN['mcp_type_info'][$type]` and `$_FN['mcp_guide'][$key]`). `edit_section_content`
replaces a snippet; content writes keep a `*.bak~` backup (`FN_BackupFile()`).
Full documentation: `doc/en_mcp_guide.md`.

# dbview module

`src/modules/dbview` (class `FNDBVIEW` in `FNDBVIEW.php`, traits in `traits/`) shows a table as a
list with search and filters, a record page and the insert/edit forms. Options in the section
configuration (`src/modules/dbview/config.php`: `tables`, `groupview`, `groupinsert`,
`groupadmin`, `search_fields`, `search_orders`, ...). Full description:
`doc/en_developer_guide.md`, "The dbview Module".

*   **Section files** (`{src_application}/sections/<id>/`): `custom_functions.php` (every request
    of the section), `top.php`, `grid_header.php`, `bottom.php`, `viewheader.php`,
    `viewfooter.php`, `bottom_edit.php` (after the edit form, also after `op=updaterecord`),
    `exports.csv`, `default.png`. `index.php?mod=<id>&file=<name>` includes `<name>` of the
    section folder instead of the module when `<name>` is a plain file name
    (`[A-Za-z0-9_-][A-Za-z0-9_.-]*`), not one of the files that dbview or FINIS include by
    themselves (`custom_functions.php`, `top.php`, `grid_header.php`, `grid_footer.php`,
    `bottom.php`, `bottom_edit.php`, `viewheader.php`, `viewfooter.php`, `header.php`,
    `footer.php`, `section.php`, `search.php`, `pdf.php`, `default.xml.php`, `config.php`: these
    are ignored and the module runs) and the visitor passes `CanViewRecords()`; the file checks
    any other permission itself.
*   **Templates**: section folder, then the theme (`themes/<theme>/modules/dbview/`, through
    `FN_FromTheme()`), then the module: `list.tp.html`, `detail.tp.html`, `formedit.tp.html`,
    `form.tp.html`, `history.tp.html`, `users.tp.html`, `toolbar.tp.html`, ...
    `list.tp.html`: `themes/<theme>/sections/<id>/`, `sections/<id>/`, theme,
    `$_FN['dbview_list_template']` (path, for every section without its own), module.
    `form_offline.html`: theme or module only.
*   **List items**: `{item_title}`, `{item_urlview}`, `{item_urledit}`, `{item_urldelete}`,
    `{item_urlimage}`, `{item_hasimage}`, `{itemvalues}`, `{viewvalue_<field>}`,
    `{title_<field>}`, and `{value_<field>}` = raw value of every scalar field (not escaped: use
    `{value_<field>.addHtmlEntities()}` for user text). The module template shows cards
    (`.dbview-cards`, `.dbview-card*` in `style.css`).
*   **Ajax navigation**: page links, filters and the search form (GET) reload the containers
    `pageresults,collapseFilters` with `call_ajax(url, ids)` / `call_ajax_form(form, ids)`; the
    url gets `fnajax=1`, the browser url follows with the history API. Without JavaScript the
    links work as normal pages.
*   **Toolbar**: `{toolbar_html}` from `toolbar.tp.html`, `{items}` with `key` (`list`, `prev`,
    `next`, `view`, `history`, `edit`, `users`), `link`, `title`, `label`, `icon`, `active`;
    UTF icons, replaced with `$_FN['dbview_toolbar_icons'] = array(key => char)`.
*   **`$_FN['dbview_excluded_values']`**: callable `($tablename)` returning
    `array(field => array(values))` for the current user: records with one of those values are
    left out of lists and searches and refused by `CanViewRecord()`. Computed once per table per
    request, not applied to the site administrators.
*   **Cached record pages** for anonymous visitors: `$_FN['dbview_view_cache']`
    (`array(section => seconds)` or one number, 0 = off), `$_FN['dbview_view_cache_tables']`
    (`array(section => array(tables))`, default the table of the section),
    `$_FN['dbview_view_cache_files']` (glob patterns of other files that render the page),
    `$_FN['dbview_view_cache_keep']` (days, default 30). Only a GET with `mod`, `op=view`, `id`,
    `lang` (and `fnajax`); files in
    `{datadir}/_cache/pages/<section>/<theme>/<lang>/<id div 1000>/<id>.html`, stale when the
    tables or the code are newer, removed when the record is saved or deleted from dbview and by
    `FN_ClearCache()`.
*   **Permissions**: every write operation (`edit`, `new`, `del`, `updaterecord`,
    `insertrecord`, `users`, `admingroups`) needs `CanEditRecords()`, false for anonymous
    visitors; saving an existing record needs `CanUpdateRecord($row)` (with
    `enable_permissions_edit_each_records` only the users of `IsAdminRecord($row)` and the users
    enabled on the record); delete needs a POST with the token of the confirmation page; the
    version history is for `IsAdmin()` only (site administrators and `groupadmin`). The record
    page sets no title or social tags for a record the visitor may not view.
*   **Lists**: on the SQL drivers the query runs in the database (`COUNT(*)`, `LIMIT`,
    `GROUP BY`, backquoted columns, ties by primary key, NULL first as in `ORDER BY`); `order` is
    accepted only from `search_orders`, `defaultorder` or the primary key, `rpp` at most 200.
    Foreign keys in the search form are selects that match the whole code.
*   **Offline form**: `op=offlineform` downloads `form_offline.html` filled with the form; section
    options `offlineform_hidden_fields`, `offlineform_text_fields` (comma separated fields).
*   **Callbacks**: `$_FN['modparams'][<section>]['editorparams']['table']['function_on_insert']`
    (called with the inserted record) and `function_on_update`, set in `custom_functions.php`.
