# FINIS Framework API Documentation

## Introduction
This documentation provides a comprehensive overview of the API (Application Programming Interface) available in the FINIS framework. This API allows developers to extend the framework's functionality and interact with it programmatically.

## Basic Concepts

### API Architecture
FINIS uses a modular approach with different APIs covering various aspects of the system:

1. **Core API**: Main functions and classes of the framework
2. **Database API**: Interface for data access
3. **Template API**: Template system and rendering
4. **Module API**: Functionality to extend the system
5. **REST API**: Endpoints for external interactions

### Naming Conventions
- Core framework functions are prefixed with `FN_` (e.g., `FN_GetParam()`)
- Control center functions are prefixed with `FNCC_` (e.g., `FNCC_GetSectionPath()`)
- Template engine functions are prefixed with `TPL_` or `FN_TPL_` (e.g., `TPL_ApplyTplString()`)
- Classes start with uppercase letters (e.g., `XMETATable`)
- Constants are in uppercase with underscores (e.g., `_FNEXEC`)
- Global variables are inside the `$_FN` array

## Core API

### FINIS Class
The main class that initializes and manages the framework (`src/FINIS.php`).

#### Constructor
```php
/**
 * Initialize the FINIS framework
 * Copies every key of $config into $_FN, then loads src/include/finis.php
 * (configuration, include files, database, login, language and theme)
 * @param array $config Optional configurations
 */
function __construct($config = array())
```

Example:
```php
require_once "path/to/FINIS.php";
$FINIS = new FINIS(array(
    "src_application" => "."
));
$FINIS->finis();
```

Options that `src/include/config.vars.php` sets (such as `display_errors`) are overwritten by it: set them in `config.vars.local.php`. The settings saved from the control center (`fn_settings`: `sitename`, `theme`, `siteurl`, ...) also replace the values passed to the constructor; only `theme` and `maintenance` passed to the constructor win over `fn_settings`. Since the bootstrap runs inside the constructor, the login of the request (`FN_ManageLogin()`) is already done when the constructor returns.

#### Main Methods

```php
/**
 * Execute the application requested by ?fnapp= (default "website")
 * through the __call dispatch
 */
function finis()

/**
 * Execute a specific section, if the current user can view it
 * @param string $section Section ID (default: $_FN['mod'])
 */
function runSection($section = "")

/**
 * Execute a folder as if it were a section (section.php, or the static
 * section.<lang>.html when there is no section.php)
 * @param string $folder Folder path
 */
function runFolder($folder)

/**
 * Set a configuration variable ($_FN[$id] = $value)
 * @param string $id Variable name
 * @param mixed $value Value
 */
function setVar($id, $value)

/**
 * Set the parameters of a table ($_FN['tables'][$tablename] = $params).
 * They are merged into the parameters of FN_XMDBTable(), FN_XMDBForm() and
 * FN_XMETATableEditor() for that table, and passed to FN_XMETADBQuery()
 * @param string $tablename Table name
 * @param array $params Table parameters (e.g. driver settings)
 */
function setTable($tablename, $params)

/**
 * Check if the application is running in console mode ($_FN['consolemode'],
 * true when $_SERVER['SHELL'] is set)
 * @return bool
 */
function isConsole()

/**
 * Register an extension: a folder with extra modules, sections, control
 * center sections and tables, used without copying it into the site.
 * The path is added to $_FN['path_extensions']; the script stops with
 * "<path> not exists" when the folder does not exist
 * @param string $path_src Path of the extension folder
 */
function addExtension($path_src)
```

`setVar()`, `setTable()` and `addExtension()` act after the bootstrap: call them before `finis()`.

#### Application Methods (`fnapp`)
Any method not defined in the class is handled by `__call($name, $arguments)`, which includes a PHP file named after the method. `finis()` calls it with the value of `?fnapp=` (or `website`), and a script can call a method directly, e.g. `$FINIS->website()`.

The name may contain only letters, digits and `_` (otherwise an `Exception` "Method does not exist" is thrown). The file is searched, in this order, in:

1. `{src_application}/include/applications/{name}.php`
2. `{src_finis}/include/applications/{name}.php`
3. `{src_application}/include/methods/{name}.php`
4. `{src_finis}/include/methods/{name}.php`

The first file found is included (`include_once`); when none exists an `Exception` "Method {name} does not exist" is thrown. An application can therefore add its own methods or replace the FINIS ones.

Methods provided by FINIS (`src/include/methods/`):

| Method | Purpose |
|---|---|
| `website` | The site page (theme + section); default method |
| `api` | Runs the section and prints `$_FN['return']` as JSON (see [REST API](#rest-api)) |
| `controlcenter` | Control center |
| `filemanager` | File manager |
| `install` | Installation wizard |
| `captcha` | Captcha image (`&t=<name>`) |
| `thumb` | Thumbnails of images and record files |
| `print` | Printable version of a page |
| `notifications` | Notifications of the user |
| `cron` | Scheduled tasks |
| `oAuth` | Login with external OAuth providers |
| `mcp` | MCP server for LLM clients |
| `oauthserver` | OAuth 2.1 authorization server for the MCP clients |

#### Extensions
An extension is a folder with the same layout as the application (`modules/`, `sections/`, `controlcenter/sections/`, `misc/fndatabase/`, `include/install/`). Example (`examples/ecommerce/index.php`):

```php
require_once "../../src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("../../fncommerce/");
$FINIS->finis();
```

Files are looked up in the application, then in FINIS, then in the extensions, in the order they were added: `FN_GetModulePath()`, `FNCC_GetSectionPath()`, `FNCC_GetSectionTitle()`, `FN_LoadConfig()`, `FN_FromTheme()` and `FN_HtmlEditConfFile()` know the extensions, and the module types of the extensions are registered in `fn_sectionstypes`. The installer copies the `sections/` and `misc/fndatabase/` folders of the extensions into the site (existing files are kept).

### General Functions

#### Parameter Handling
```php
/**
 * Get a parameter from a variable
 * @param string $key Parameter name
 * @param array $var Array to extract the parameter from (default: $_REQUEST)
 * @param string $type Data type: html (htmlentities with ENT_QUOTES), int,
 *                     float, or the name of a function applied to the value
 * @return mixed Parameter value ("" when missing or when the value is an array)
 */
function FN_GetParam($key, $var = false, $type = "")
```

Example:
```php
// Get the 'id' parameter from GET request
$id = FN_GetParam("id", $_GET, "int");

// Get the 'name' parameter from POST request and sanitize as HTML
$name = FN_GetParam("name", $_POST, "html");
```

#### File and Path Handling
```php
/**
 * Get the path for a file considering the theme. A relative path is looked
 * up in FINIS, then in the extensions; a copy of the file in the theme
 * (themes/<theme>/ of the application, then of FINIS) wins
 * @param string $file File to search for
 * @param bool $absolute Whether to return an absolute URL
 * @return string Path to the file
 */
function FN_FromTheme($file, $absolute = true)

/**
 * Path of a FINIS file in the site (alias of FN_FinisPathToApplicationPath)
 * @param string $filepath File path
 * @param bool $urlAbsolute Whether to return absolute URL
 * @return string Complete path
 */
function FN_PathSite($filepath, $urlAbsolute = false)

/**
 * Convert a path under src_finis into the same path under src_application,
 * copying the file (or the files of the folder) there when missing
 * @param string $filepath File path
 * @param bool $urlAbsolute Whether to return absolute URL
 * @return string Path relative to the application, or URL
 */
function FN_FinisPathToApplicationPath($filepath, $urlAbsolute = false)

/**
 * Return the appropriate icon for a file type
 * @param string $filename File name
 * @return string Icon URL (images/mime/ of the theme)
 */
function FN_GetIconByFilename($filename)

/**
 * Icon of a file as a unicode character (HTML entity), by its extension:
 * no image to load
 * @param string $filename File name
 * @return string HTML entity, e.g. "&#x1F4C4;"
 */
function FN_GetIconCharByFilename($filename)
```

#### Localization and Translation
```php
/**
 * Translate a string to the current language (language .csv files)
 * @param string $constant Text to translate
 * @param string $language Language (default: current language)
 * @param string $uppercasemode Optional case: "Aa", "aa", "AA", "Aa Aa"
 * @return string Translated text
 */
function FN_i18n($constant, $language = "", $uppercasemode = "")

/**
 * Translate a string with an initial capital by default
 * @param string $english_string Text to translate
 * @param string $uppercasemode "Aa" (default), "aa", "AA", "Aa Aa"
 * @param string $language Language (optional)
 * @return string Translated text
 */
function FN_Translate($english_string, $uppercasemode = "Aa", $language = "")

/**
 * Get the title of a folder in the current language
 * @param string $path Folder path
 * @param string $lang Language (optional)
 * @return string Localized title
 */
function FN_GetFolderTitle($path, $lang = "")

/**
 * Set the title of a folder
 * @param string $path Folder path
 * @param string $title Title to set
 * @param string $lang Language (optional)
 */
function FN_SetFolderTitle($path, $title, $lang = "")
```

#### Dates and Times
```php
/**
 * Return the current formatted date and time
 * @param string $format Date format (default: Y-m-d H:i:s)
 * @return string Formatted date
 */
function FN_Now($format = "Y-m-d H:i:s")

/**
 * Format a date in the localized format
 * @param string $time Date/time to format
 * @param bool $showtime Show time as well
 * @return string Formatted date
 */
function FN_FormatDate($time, $showtime = true)

/**
 * Return a Unix timestamp
 * @return int Current timestamp
 */
function FN_Time()
```

#### Logging and Debug
```php
/**
 * Log an event in the fn_log table (context, event, user = $_FN['user'],
 * ip, date)
 * @param string $event Event message
 * @param string $context Event context (lowercase, [a-z0-9_])
 */
function FN_LogEvent($event, $context = "cms")

/**
 * Write a message to the log (FN_LogEvent), and mail it to
 * $_FN['log_email_address'] when $_FN['enable_log_email'] is 1
 * @param string $txt Message to log
 */
function FN_Log($txt)

/**
 * Get the execution time from the start
 * @return string Execution time in seconds
 */
function FN_GetExecuteTimer()

/**
 * Get the partial time since the last call
 * @return string Partial and total time
 */
function FN_GetPartialTimer()
```

#### Notifications
```php
/**
 * Add a notification for specific users
 * @param mixed $notificationvalues Notification content or array
 * @param mixed $users Username or array of usernames
 */
function FN_AddNotification($notificationvalues, $users)

/**
 * Get undisplayed notifications for a user
 * @param string $user Username
 * @param string $context Optional context
 * @return array List of notifications
 */
function FN_GetNotificationsUndisplayed($user, $context = "")

/**
 * Mark a notification as displayed
 * @param int $id Notification ID
 */
function FN_SetNotificationDisplayed($id)
```

## Database API

> **Note**: For more complete documentation on the database system, see the [Database Abstraction Layer Guide](en_database_guide.md).

### XMETATable Class
Main class for interacting with the database (`src/include/xmetadb/`, namespace `Xmetadb`; the old class names such as `XMETATable` and `XMETADatabase` remain available as aliases).

#### Creation and Initialization
```php
/**
 * Get an instance of the table
 * @param string $tablename Table name
 * @param array $params Optional parameters (merged with $_FN['tables'][$tablename])
 * @return object XMETATable instance
 */
function FN_XMDBTable($tablename, $params = array())
```

Example:
```php
// Get an instance of the users table
$usersTable = FN_XMDBTable("fn_users");
```

#### CRUD Operations

```php
/**
 * Insert a new record
 * @param array $values Record data
 * @return mixed The saved record (array); false or an error string on failure
 */
function InsertRecord($values)

/**
 * Update an existing record
 * @param array $values New data, with the primary key
 * @param mixed $pkvalue Primary key value (default: taken from $values)
 * @return mixed The saved record (array); false or an error string on failure
 */
function UpdateRecord($values, $pkvalue = false)

/**
 * Update a record via primary key
 * @param array $values New data
 * @param string $pkey Primary key field name
 * @param mixed $pvalue Primary key value
 * @return mixed The saved record (array); false or an error string on failure
 */
function UpdateRecordBypk($values, $pkey, $pvalue)

/**
 * Delete a record
 * @param mixed $pkvalue Primary key value
 * @return bool Success
 */
function DelRecord($pkvalue)

/**
 * Get the records
 * @param mixed $restr Filter: array(field => value), or a SQL condition
 *                     string with the SQL drivers
 * @param int $min First record
 * @param int $length Number of records
 * @param string $order Order field(s)
 * @param bool $reverse Descending order
 * @param array $fields Fields to read
 * @return array List of records
 */
function GetRecords($restr = false, $min = false, $length = false, $order = false, $reverse = false, $fields = false)

/**
 * Get a record by primary key
 * @param mixed $unirecid Primary key value
 * @return array Record; null when not found (the mysql driver returns false
 *               when the query fails)
 */
function GetRecordByPrimaryKey($unirecid)
```

Example:
```php
$productsTable = FN_XMDBTable("products");

// Insert a new record
$product = $productsTable->InsertRecord(array(
    'name' => 'Lamp',
    'price' => '12.50'
));

// Update it
$productsTable->UpdateRecord(array(
    'id' => $product['id'],
    'price' => '11.90'
));

// Get a specific record
$product = $productsTable->GetRecordByPrimaryKey(5);

// Get filtered records
$lamps = $productsTable->GetRecords(array('name' => 'Lamp'));

// Delete a record
$productsTable->DelRecord(5);
```

To create or change users, use `FN_AddUser()` and `FN_UpdateUser()` (see [User Management](#user-management)): they hash the password.

#### Advanced Queries
```php
/**
 * Execute a SQL-like query (XMETADatabase, every driver)
 * @param string $query SQL query
 * @param array $params Optional parameters
 * @return array Query results
 */
function FN_XMETADBQuery($query, $params = array())
```

Example:
```php
// Custom query
$results = FN_XMETADBQuery("
    SELECT username, email
    FROM fn_users
    WHERE active = 1
    ORDER BY username
");
```

### Forms
`FN_XMDBForm()` returns the form object of a table (class `FieldFrm`, `src/include/xmetadb_frm.php`); the table object is in its `xmltable` property.

```php
/**
 * Get a form instance for a table
 * @param string $tablename Table name
 * @param array $params Optional parameters (merged with $_FN['tables'][$tablename])
 * @return object FieldFrm instance
 */
function FN_XMDBForm($tablename, $params = array())
```

Main methods: `HtmlShowInsertForm($currentleveladmin = false, $values = false, $errors = false)`, `HtmlShowUpdateForm($pk, $currentleveladmin = false, $nvalues = false, $errors = false)`, `HtmlShowView($values, $currentleveladmin = false, $errors = false)`, `GetByPost($oldvalues = array())`, `Verify($newvalues, $update = false, $pk = null)`, `InsertRecord($newvalues)`, `UpdateRecord($newvalues, $pkvalue = false)`. Unlike the table methods, the form methods convert the values of the fields (e.g. a `cryptpasswd` field is hashed).

Example:
```php
$form = FN_XMDBForm("products");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $values = $form->GetByPost();
    $errors = $form->Verify($values);
    if (count($errors) == 0) {
        $form->InsertRecord($values);
    }
}
echo $form->HtmlShowInsertForm();
```

## Template API

### Template Management
```php
/**
 * Load a configuration from a file (application, FINIS, extensions)
 * @param string $fileconfig Configuration file path
 * @param string $sectionid Section ID
 * @param bool $usecache Use cache
 * @return array Configuration
 */
function FN_LoadConfig($fileconfig = "", $sectionid = "", $usecache = true)

/**
 * Execute a section and return HTML
 * @param string $folder Section path
 * @param bool $usecache Use cache
 * @return string Generated HTML
 */
function FN_HtmlContent($folder, $usecache = true)

/**
 * Load the static content section.<lang>.html of a folder
 * @param string $folder Section path
 * @param bool $usecache Use cache
 * @return string HTML
 */
function FN_HtmlStaticContent($folder, $usecache = false)

/**
 * Include CSS from framework and sections
 * @return string HTML tags for CSS
 */
function FN_IncludeCSS()

/**
 * Include JavaScript from framework
 * @return string HTML tags for JavaScript
 */
function FN_IncludeJS()

/**
 * Apply the variables to a template string ({var}, if/foreach blocks)
 * @param string $str Template
 * @param array $vars Variables
 * @param string $basepath Base path of the template
 * @param array $config Options
 * @return string HTML
 */
function TPL_ApplyTplString($str, $vars, $basepath = false, $config = array())

/**
 * Same as TPL_ApplyTplString() with the FINIS variables ($_FN, i18n, links)
 * @param string $str Template
 * @param array $vars Variables
 * @param string $basepath Base path of the template
 * @return string HTML
 */
function FN_TPL_ApplyTplString($str, $vars, $basepath = false)
```

The template functions do not escape the values: write `{var.addHtmlEntities()}` in the template, or escape the value in PHP (see [Security](#security)).

### HTML and URL Manipulation
```php
/**
 * Convert BBCode to HTML
 * @param string $string Text with BBCode
 * @return string Resulting HTML
 */
function FN_Tag2Html($string)

/**
 * Rewrite a link according to the mod_rewrite settings of the site
 * @param string $href Link (e.g. "index.php?mod=news")
 * @param string $sep Separator of the parameters ("&" or "&amp;")
 * @param bool $full Return an absolute URL
 * @return string Link
 */
function FN_RewriteLink($href, $sep = "", $full = false)

/**
 * Convert relative links to absolute
 * @param string $str HTML content
 * @param string $folder Base directory
 * @return string HTML with absolute links
 */
function FN_RewriteLinksLocalToAbsolute($str, $folder)

/**
 * Convert the absolute URLs of the site to links relative to a folder
 * (the conversion applied by the editors on save)
 * @param string $content HTML content
 * @param string $directory Base directory (e.g. "sections/<id>")
 * @return string HTML with local links
 */
function FN_RewriteLinksAbsoluteToLocal($content, $directory)

/**
 * Normalize all paths in HTML content
 * @param string $content HTML content
 * @return string Content with normalized paths
 */
function FN_NormalizeAllPaths($content)

/**
 * Normalize a single path
 * @param string $path Path to normalize
 * @return string Normalized path
 */
function FN_NormalizePath($path)
```

### Website Method Parameters
The `website` method (`src/include/methods/website.php`) accepts two parameters for ajax navigation and offline copies (service workers):

- `fnajax=1`: only the output of the section, without the theme, in a minimal HTML page whose `<title>` is the title of the full page. The page head is built first (`FN_HtmlHeader()`), so its hooks run as for the full page. The dbview ajax navigation adds it to the URLs it loads.
- `fnshell=1`: the page with the theme and the marker `<!--fnsection-->` in place of the section, which is not generated. Ignored together with `fnajax=1`.

Every page of the `website` method has these response headers:

| Header | Value |
|---|---|
| `X-Finis-Visitor` | `public` for a GET by an anonymous visitor (the same page for every anonymous visitor, it may be kept offline), `private` otherwise |
| `X-Finis-Section` | The section (`$_FN['mod']`) |
| `X-Finis-Lang` | The language (`$_FN['lang']`) |

The login and logout forms remove `fnajax` and `fnshell` from the URL they post to.

## Sections API

### Section Management
```php
/**
 * Check if a user can view a section
 * @param string $section Section ID (default: current section)
 * @param string $user Username (default: current user)
 * @return bool
 */
function FN_UserCanViewSection($section = "", $user = "")

/**
 * Check if a user can edit a section
 * @param string $section Section ID (default: current section)
 * @param string $user Username (default: current user)
 * @return bool
 */
function FN_UserCanEditSection($section = "", $user = "")

/**
 * Get the values of a section
 * @param string $section Section ID
 * @param bool $usecache Use cache
 * @return array Section data
 */
function FN_GetSectionValues($section, $usecache = true)

/**
 * Execute a section: section.php of its module (FN_GetModulePath) or of
 * sections/<id>/, or the static content
 * @param string $section Section ID
 * @param bool $return_html Whether to return output instead of printing
 * @return string Output (empty when printed)
 */
function FN_RunSection($section, $return_html)

/**
 * Execute a folder as a section
 * @param string $folder Folder path
 * @param bool $return_html Whether to return output
 * @return string Output (empty when printed)
 */
function FN_RunFolder($folder, $return_html)

/**
 * HTML of the section with the administration options, or the message
 * "you don't have permission to view this page"
 * @param string $section Section ID (default: current section)
 * @return string HTML
 */
function FN_HtmlSection($section = "")

/**
 * Fill the empty per-language titles and descriptions of the sections in
 * the database with the values of sections/<id>/default.xml.php (used by
 * the install wizard after the languages are chosen)
 * @return int Number of updated sections
 */
function FN_SyncSectionsFromDefaults()
```

### Navigation
```php
/**
 * Get the sections under a parent
 * @param string $section Parent section ("" = root)
 * @param bool $recursive Include the subsections
 * @param bool $onlyreadable Only sections the user can view
 * @param bool $hidden Include hidden sections
 * @param bool $onlyenabled Only enabled sections
 * @param bool $nocache Read again
 * @return array Sections
 */
function FN_GetSections($section = "", $recursive = false, $onlyreadable = true, $hidden = false, $onlyenabled = true, $nocache = false)

/**
 * Menu items as a tree (entries hidden by FN_MenuSectionIsVisible() are left out)
 * @param string $parent Parent section
 * @return array Menu items
 */
function FN_GetMenuTree($parent = "")

/**
 * Whether a menu entry leads to content the user may see. Administrators
 * see everything; a dbview section is visible only to the groups of its
 * module configuration (groupadmin, groupview, groupinsert) when groupview
 * is set; a table_editor section only to administrators
 * @param array $section Values of the section (FN_GetSections())
 * @return bool
 */
function FN_MenuSectionIsVisible($section)

/**
 * Get the navigation path (breadcrumbs): the section and its parents, from the root
 * @param string $section Section ID (default: current section)
 * @return array Path
 */
function FN_GetSectionsTree($section = "")
```

## Users and Groups API

### User Management
```php
/**
 * Check if a user is an administrator (level >= 10)
 * @param string $user Username (default: current user)
 * @return bool
 */
function FN_IsAdmin($user = false)

/**
 * Get user data (with the password hash in passwd: never return it to the client)
 * @param string $user Username
 * @param bool $usecache Use the cache of the request
 * @return array User data or null
 */
function FN_GetUser($user, $usecache = array())

/**
 * The user values without passwd, password, apikey, apikey_enc and rnd (and the
 * password field of the form, when it has another name): use it for a user record
 * put in $_FN['return'] or sent to a client
 * @param array $values User record
 * @param string $passwordfield Name of the password field of the form
 * @return array
 */
function FN_UserValuesForOutput($values, $passwordfield = "")

/**
 * Get the users
 * @param array $arrayfilter Optional filter
 * @return array Users
 */
function FN_GetUsers($arrayfilter = false)

/**
 * Create a user. The username is saved in lowercase unless
 * $_FN['username_case_sensitive']; usernames made only of dots are refused.
 * The password (third argument or "passwd") is hashed with password_hash().
 * Calls the hook FN_OnAddUser($newvalues, $password) on success
 * @param array $newvalues User data
 * @param string $password Password in clear
 * @return bool True only when the record was saved (an existing username is refused)
 */
function FN_AddUser($newvalues, $password = "")

/**
 * Update a user. A non empty passwd (or $password) is hashed: never pass
 * the hash read by FN_GetUser(), unset passwd instead. A new password
 * revokes the API key. Activating or deactivating a user clears its
 * registration code. Calls the hook FN_OnUpdateUser($newvalues, $password)
 * @param string $user Username
 * @param array $newvalues New data
 * @param string $password New password in clear
 * @return mixed The saved record (array); false or an error string on failure
 */
function FN_UpdateUser($user, $newvalues, $password = "")

/**
 * Delete a user and its files folder; calls the hook FN_OnDeleteUser($uservalues)
 * @param string $user Username
 */
function FN_DeleteUser($user)

/**
 * Check if a user belongs to a group
 * @param string $user Username
 * @param string $group Group name or comma-separated groups
 * @return bool
 */
function FN_UserInGroup($user, $group)

/**
 * Add or remove a user from a group
 * @param string $user Username
 * @param string $group Group name
 * @param bool $member true = add, false = remove
 * @return bool Success
 */
function FN_SetUserGroup($user, $group, $member)

/**
 * Create a group if it doesn't exist
 * @param string $groupname Group name
 */
function FN_CreateGroupIfNotExists($groupname)
```

### Authentication
The login state comes from the cookies `fnuser` and `secid`; see [Login security](Finis_Framework_LLM_Guide.md#login-security) for the details.

```php
/**
 * Manage the login of the request; called by the bootstrap for every web
 * request. Handles the login form (?fnlogin=login with username and
 * password in POST, captcha, attempt limit), ?fnlogin=logout, the check of
 * the cookies, and, without a cookie session, an OAuth access token or an
 * API key sent in the headers. Sets $_FN['user']
 */
function FN_ManageLogin()

/**
 * Verify a password. Modern hashes only with password_verify (rehashed when
 * needed); legacy md5 and plaintext values are rehashed at the first
 * successful login. $_FN['FN_VerifyUserPassword'] can name a function that
 * replaces this check
 * @param string $fnuser Username
 * @param string $fnpwd Password
 * @return bool
 */
function FN_VerifyUserPassword($fnuser, $fnpwd)

/**
 * Start a user session: sets the cookies fnuser and secid (HttpOnly,
 * SameSite=Lax, Secure on HTTPS). It does not check the password. With
 * $_FN['remember_login'] and $rememberlogin the cookies last
 * $_FN['remember_login_days'] days (default 30), otherwise the browser session
 * @param string $fnuser Username
 * @param bool $rememberlogin Persistent cookie
 * @return bool false when the user does not exist or is not active
 */
function FN_Login($fnuser, $rememberlogin = false)

/**
 * End the current user session (clears the login cookies)
 */
function FN_Logout()

/**
 * Check the secid cookie of $_FN['user'] (HMAC with the site secret);
 * false for a user that is not active
 * @return bool
 */
function FN_CheckUser()

/**
 * Record a login attempt of a username from the client IP, before the
 * password check. Refused after $_FN['login_max_attempts'] attempts
 * (default 5) in $_FN['login_lockout_minutes'] minutes (default 15)
 * @param string $user Username
 * @return bool false if the username is locked out from this IP
 */
function FN_LoginAttempt($user)

/**
 * Clear the login attempts of a username from the client IP
 * @param string $user Username
 */
function FN_LoginResetAttempts($user)

/**
 * Value of the secid cookie: HMAC-SHA256 of "user|passwd" with the site secret
 * @param string $user Username
 * @param string $passwd Stored password hash
 * @return string
 */
function FN_GetSecid($user, $passwd)

/**
 * Whether a password in clear is the one stored (any stored format)
 * @param string $password Password in clear
 * @param string $stored Stored hash
 * @return bool
 */
function FN_SamePassword($password, $stored)
```

### Request and Site Helpers
```php
/**
 * setcookie() with the options array (expires, path, domain, secure,
 * httponly, samesite) on every PHP version: before PHP 7.3 SameSite is
 * appended to the path
 * @param string $name Cookie name
 * @param string $value Value
 * @param array $opts Options
 * @return bool
 */
function FN_SetCookie($name, $value, $opts = array())

/**
 * IP address of the client: REMOTE_ADDR, or, when REMOTE_ADDR is one of
 * $_FN['trusted_proxies'], the last address in X-Forwarded-For not added
 * by a trusted proxy
 * @return string
 */
function FN_GetClientIp()

/**
 * True when the request uses HTTPS (HTTPS=on or X-Forwarded-Proto: https)
 * @return bool
 */
function FN_IsHttps()

/**
 * True when the URL points to this site (under $_FN['siteurl'], or relative
 * without scheme). Check it before a redirect to a URL from the request
 * @param string $url
 * @return bool
 */
function FN_IsInternalUrl($url)

/**
 * Secret of the site, read from {datadir}/secret.key.php or created once
 * with random_bytes(32) (mode 0600). It signs the login cookies, the OAuth
 * tokens and encrypts the copies of the API keys: deleting the file logs
 * out every user. Never commit it
 * @return string
 */
function FN_GetSiteSecret()

/**
 * New code for the captcha $name (fnlogin_code for the login form,
 * security_code for the registration form), drawn by ?fnapp=captcha&t=$name.
 * The codes of the other forms are kept
 * @param string $name Captcha name
 * @return int The code (random_int(1000, 9999))
 */
function FN_NewCaptchaCode($name)
```

### API Keys, MCP and OAuth Server
Each user can have a personal API key, used by the API and MCP clients; the OAuth 2.1 server issues access tokens to the MCP clients. Full documentation: [MCP server and API keys](en_mcp_guide.md).

- API keys (`src/include/auth/local.php`): `FN_GetRequestApiKey()`, `FN_LoginByApiKey($apikey)`, `FN_GetUserByApiKey($apikey)`, `FN_GenerateApiKey($user)`, `FN_RevokeApiKey($user)`, `FN_ApiKeyInfo($user)`, `FN_ApiKeyReveal($user)`, `FN_ApiKeyHash($apikey)`, `FN_UserCanUseApiKey($user)`, `FN_UserCanWriteByApiKey($user)`, `FN_McpWriteGroup()`, `FN_ProfileApiKey($user, &$tplvars)`, `FN_ApiKeyFormToken()`, `FN_GetMcpEndpoints()`, `FN_EnsureUserApiKeyField()`; `FN_GetUserApiKey($user, $generate = false)` is deprecated.
- MCP tools (`src/include/methods/mcp.php`, available in `{src_application}/include/mcp_tools.php`): `FN_MCP_AddTool($name, $description, $inputSchema, $callback, $write = false)`, `FN_MCP_Fail($message)`, `FN_MCP_CanWrite()`.
- OAuth server (`src/include/functions_oauth_server.inc.php`): `FN_OAuthServerLoginByToken($token)`, `FN_OAuthServerChallenge($resource)`.

### User Hooks
The framework calls these functions when the application defines them:

| Function | Called by |
|---|---|
| `FN_OnAddUser($newvalues, $password)` | `FN_AddUser()`, after the record is saved |
| `FN_OnUpdateUser($newvalues, $password)` | `FN_UpdateUser()`, after the record is saved |
| `FN_OnDeleteUser($uservalues)` | `FN_DeleteUser()` |
| `FN_OnConfirmUser($uservalues)` | The login module, when a registration is confirmed |

## File and Directory API

### File Operations
```php
/**
 * Write content to a file
 * @param string $string Content
 * @param string $file File path
 * @param string $mode Mode (default: w)
 * @return bool Success
 */
function FN_Write($string, $file, $mode = "w")

/**
 * Copy a file (into the folder when $d is a folder)
 * @param string $s Source file
 * @param string $d Destination file
 * @param bool $createdir Create the destination folder if missing
 * @return bool Success
 */
function FN_Copy($s, $d, $createdir = false)

/**
 * Copy a directory recursively
 * @param string $fromDir Source dir
 * @param string $toDir Destination dir
 * @param bool $verbose Print the copied paths
 * @return bool Success
 */
function FN_CopyDir($fromDir, $toDir, $verbose = false)

/**
 * Create a directory with its parents
 * @param string $pathname Directory path
 * @return bool Success
 */
function FN_MkDir($pathname)

/**
 * Remove a directory recursively (a path with "../" stops the script)
 * @param string $dirtodelete Directory path
 */
function FN_RemoveDir($dirtodelete)

/**
 * Copy a file to <file>.<mtime>.<YmdHis>.<user>.bak~ before changing it
 * (only when the file exists and is not empty)
 * @param string $file File path
 */
function FN_BackupFile($file)
```

### File Utilities
```php
/**
 * Get the file extension
 * @param string $filename File name
 * @return string Extension ("" without a dot)
 */
function FN_GetFileExtension($filename)

/**
 * Read a CSV file with a header row
 * @param string $filename File name
 * @param string $delimiter Field delimiter
 * @param string $enclosure Enclosure character
 * @param string $escape Escape character
 * @return array Rows as arrays indexed by the column names
 */
function FN_ReadCsvDatabase($filename, $delimiter, $enclosure = '"', $escape= '\\')
```

## Communication API

### Email
```php
/**
 * Send an email
 * @param string $to Recipient
 * @param string $subject Subject
 * @param string $body Message body
 * @param bool $ishtml Whether content is HTML
 * @param string $from Sender (optional)
 * @return bool Success
 */
function FN_SendMail($to, $subject, $body, $ishtml = false, $from = "")

/**
 * Fix newline characters for email
 * @param string $text Text to fix
 * @return string Fixed text
 */
function FN_FixNewline($text)
```

### HTTP
```php
/**
 * Redirect to another page (discards the output buffers and stops the script).
 * Check a URL from the request with FN_IsInternalUrl() first
 * @param string $url Destination URL
 */
function FN_Redirect($url)

/**
 * Check if the referrer is external
 * @return bool
 */
function FN_IsExternalReferer()

/**
 * Send a file to the browser for download (discards the output buffers
 * and stops the script)
 * @param string $filecontents File content
 * @param string $filename File name
 * @param string $HeaderContentType MIME type
 */
function FN_SaveFile($filecontents, $filename, $HeaderContentType = "application/force-download")
```

## REST API

### Base Endpoint
The `api` method runs a section like the `website` method, discards its HTML output and prints the array `$_FN['return']` as JSON:

```
http://yoursite.com/index.php?fnapp=api&mod=section_id&param1=value1
```

A section (or module) returns data by filling `$_FN['return']`; the same code serves the page and the API. The response has `Cache-Control: no-cache` and the CORS headers `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Methods: GET, POST, OPTIONS`, `Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With`. With `&debug=1` the method shows an HTML page with the parameters and the returned value.

### Authentication
`fnapp=api` requests are authenticated by `FN_ManageLogin()`, like any other request:

1. **Cookie session**: the cookies `fnuser` and `secid` set by a login. A client can log in with a POST to `index.php?fnapp=api&mod=login&fnlogin=login` with `username` and `password` (plus `fnlogin_code` when the captcha is enabled), then send the cookies it received.
2. **OAuth access token**: without a cookie session, the value of `Authorization: Bearer <token>` is first checked as an access token issued by `fnapp=oauthserver` (`$_FN['auth_method'] = "oauth"`).
3. **API key**: otherwise the value of `Authorization: Bearer <key>` or of the header `X-Api-Key: <key>` is checked as the API key of an active user allowed by `$_FN['apikey_groups']` (`$_FN['auth_method'] = "apikey"`). A key in the URL (`&apikey=`) is accepted only by the MCP endpoint.

API keys and tokens do not create cookies or sessions: send them with every request.

The server session (`FN_GetSessionValue()`, used for example by the captcha) is identified only by the cookie `fnsid`, never by the URL. When the request uses it, the id is also in `$_FN['return']['fnsid']`: a client without a cookie jar sends it back as `Cookie: fnsid=<id>`.

Data that must never reach the client: the login module (`op=profile`, `op=editreg`) and the control center profile pass the user record they put in `$_FN['return']` through `FN_UserValuesForOutput()`, which removes `passwd`, `apikey`, `apikey_enc` and `rnd`. Do the same in your sections.

### Section Example
```php
<?php
// sections/products/section.php
global $_FN;
$items = FN_XMDBTable("products")->GetRecords();

// returned as JSON by fnapp=api
$_FN['return']['items'] = $items;

// printed by the website method, discarded by fnapp=api
echo "<ul>";
foreach ($items as $item) {
    echo "<li>" . htmlspecialchars($item['name']) . "</li>";
}
echo "</ul>";
```

### API Request Example
Profile of the user who owns the API key:
```
curl -H "Authorization: Bearer <key>" "http://yoursite.com/index.php?fnapp=api&mod=login&op=profile"
```

Response (in JSON format, the fields of `fn_users` without `passwd`, `apikey` and `rnd`), for example:
```json
{
    "username": "johndoe",
    "email": "john@example.com",
    "name": "John",
    "surname": "Doe",
    "level": "0",
    "group": "users,editors",
    "active": "1",
    "registrationdate": "2023-01-15 10:30:00"
}
```

## Extensibility

### Hooks and Callbacks
FINIS calls optional functions defined by the application (see [User Hooks](#user-hooks)):

```php
// e.g. in a file included at startup (extra/*.inc.php of the application)
function FN_OnAddUser($newvalues, $password) {
    // Actions to perform when a user is created
    FN_LogEvent("New user {$newvalues['username']}", "myapp");
}
```

Other overridable functions: `$_FN['FN_VerifyUserPassword']` can name a function that replaces the password check, and `FN_PasswordVerifyConstraints_overwrite($password)` replaces the password rules (it returns an error message, or "" when the password is valid).

### Startup Files
- `{src_application}/extra/*.inc.php` are included by the bootstrap, before the FINIS include files.
- `{src_application}/config.vars.local.php` sets the configuration (see the constructor).

### Autoexec Scripts
The `website` method runs the `*.php` files of `{src_finis}/include/autoexec.d/` and then of `{src_application}/include/autoexec.d/`, in natural order, before the page is built:

```php
// include/autoexec.d/99_custom.php
global $_FN;

// Add custom tags to the head of the page
if ($_FN['mod'] == 'home') {
    $_FN['section_header_footer'] .= '<script src="path/to/script.js"></script>';
}
```

### on_site_change Scripts
`FN_OnSitemapChange()` runs the `*.php` files of `{src_finis}/include/on_site_change.d/` (files starting with `none_` are skipped) when the sections or blocks change (control center, editors, MCP, `FN_SyncSectionsFromDefaults()`). FINIS uses it to clear the cache and to rebuild the sitemap:

```php
// include/on_site_change.d/00_cache.php
FN_ClearCache();
```

## Security

### Escaping
FINIS does not escape values for you:

- `FN_GetParam($key, $var, "html")` returns the value with `htmlentities(..., ENT_QUOTES)`; without a type the value is returned as received.
- `FN_RewriteLink()`, `TPL_ApplyTplString()` and `FN_TPL_ApplyTplString()` escape nothing: a value from the request or from the database goes into a template only after `FN_GetParam(..., "html")`, `htmlspecialchars()` or `{var.addHtmlEntities()}` in the template.
- Escape a value written in an HTML attribute with `htmlspecialchars($value, ENT_QUOTES)`.
- Before a redirect to a URL from the request, check it with `FN_IsInternalUrl()`.

```php
/**
 * Check if a string matches a regex pattern (preg_match with "/" delimiters)
 * @param string $find Regex pattern
 * @param string $str String to check
 * @param array $regs Matches (optional)
 * @return int Match found
 */
function FN_erg($find, $str, $regs = null)

/**
 * Check if an email is valid
 * @param string $email Email address
 * @return bool Validity
 */
function FN_CheckMail($email)
```

### Random Values
Tokens, captcha codes, session ids, API keys and generated passwords use `random_bytes()` or `random_int()`; do not use `rand()`, `mt_rand()` or `uniqid()` for anything secret. Compare secrets with `hash_equals()`.

### Permission Management
There is no generic permission function: use `FN_IsAdmin()`, `FN_UserInGroup()`, `FN_UserCanViewSection()`, `FN_UserCanEditSection()`, `FN_UserCanViewBlock($block, $user = "")`, `FN_UserCanEditBlock($block = "", $user = "")` and `FN_CanModifyFile($user, $file)`.

### CSRF Tokens
FINIS has no generic CSRF functions. Its forms use these patterns:

- `FN_ApiKeyFormToken()`: HMAC bound to the logged user, for the forms that generate or revoke API keys.
- dbview deletes records only with a POST that carries `fn_csrf`, an HMAC of the operation with a per-user secret kept in `FN_SetUserSessionValue('fn_csrf_secret', ...)`.

Do the same in your forms: accept changes only with POST, add a token derived from a secret of the user or of the session (`hash_hmac()` with a value from `random_bytes()`), and check it with `hash_equals()`.

```php
$secret = FN_GetUserSessionValue('myform_secret');
if (empty($secret)) {
    $secret = bin2hex(random_bytes(16));
    FN_SetUserSessionValue('myform_secret', $secret);
}
$token = hash_hmac('sha256', "save|$id", $secret);
$ok = $_SERVER['REQUEST_METHOD'] === 'POST'
    && hash_equals($token, (string) FN_GetParam("token", $_POST));
```

## Sessions and Cache

### Server Session
```php
/**
 * Session id of the browser, taken only from the fnsid cookie (32 hex
 * chars); otherwise a random id is created and sent in a session cookie
 * (HttpOnly, SameSite=Lax, Secure on HTTPS). Also in $_FN['fnsid'] and
 * $_FN['return']['fnsid']
 * @return string
 */
function FN_GetSessionId()

/**
 * Read a value of the server session ({datadir}/_sessions/<fnsid>.session)
 * @param string $varname Key
 * @return mixed Value or null
 */
function FN_GetSessionValue($varname)

/**
 * Write a value of the server session; session files are removed one hour
 * after their last write
 * @param string $key Key
 * @param mixed $value Value
 */
function FN_SetSessionValue($key, $value)

/**
 * Read a value saved for the logged user (table fn_userssessions)
 * @param string $varname Key
 * @return mixed Value or null (also without a logged user)
 */
function FN_GetUserSessionValue($varname)

/**
 * Save a value for the logged user (table fn_userssessions, created when
 * missing); does nothing without a logged user
 * @param string $varname Key
 * @param mixed $value Value
 */
function FN_SetUserSessionValue($varname, $value)
```

### Cache Management
```php
/**
 * Clear the cache: removes and creates again {datadir}/_cache
 */
function FN_ClearCache()

/**
 * Read a value shared by all the requests. Uses APCu when available,
 * otherwise the file {datadir}/_cache/<md5>.cache (read with a shared lock)
 * @param string $varname Key
 * @param int $maxtime Timestamp: a value saved before it is discarded
 * @return mixed Value or null
 */
function FN_GetGlobalVarValue($varname, $maxtime = false)

/**
 * Save a value shared by all the requests (APCu, or a file written with
 * an exclusive lock)
 * @param string $varname Key
 * @param mixed $value Value
 * @param int $ttl Time to live in seconds, only with APCu (0 = no expiration)
 * @return bool Success
 */
function FN_SetGlobalVarValue($varname, $value, $ttl = 0)
```

With APCu the global values are not in `{datadir}/_cache`: `FN_ClearCache()` does not remove them (they expire with their TTL or when PHP restarts). The login attempt counters (`{datadir}/_login_attempts`) and the sessions (`{datadir}/_sessions`) are outside the cache folder as well.

## Modules

### Module Management
A module is a folder `modules/<name>/` with a `section.php`; a section uses it through its `type`.

```php
/**
 * Get the path of a module: {src_application}/modules/<name> (when it has
 * a section.php), then {src_finis}/modules/<name>, then the extensions
 * @param string $module_name Module name
 * @return string|false Path or false
 */
function FN_GetModulePath($module_name)

/**
 * Get the folders of all the modules (application, FINIS, extensions)
 * @return array Module paths
 */
function FN_GetAllModulePaths()

/**
 * Save the type of a module in fn_sectionstypes, unless the table has it already
 * @param array $values Record (name, title, ...)
 * @return array|false The record saved or found, false on error
 */
function FN_InsertSectionType($values)
```

### Control Center Sections
These functions are defined by the `controlcenter` method:

```php
/**
 * Path of a control center section: application, FINIS, then extensions
 * @param string $section_path e.g. "users_and_groups/users"
 * @return string|false Path or false
 */
function FNCC_GetSectionPath($section_path)

/**
 * Title of a control center section or group: the first title file found
 * in the application, FINIS and the extensions (default: the folder name)
 * @param string $section_path e.g. "users_and_groups"
 * @return string
 */
function FNCC_GetSectionTitle($section_path)

/**
 * Path of a file in the control center theme ($_FN['controlcenter_theme']):
 * the copy in {src_application}/controlcenter/themes/ wins over FINIS
 * @param string $file File
 * @param bool $absolute Whether to return an absolute URL
 * @return string Path to the file
 */
function FNCC_FromTheme($file, $absolute = true)
```

## Usage Examples

### Creating a Dynamic Page
```php
<?php
// sections/mypage/section.php
global $_FN;

// Get parameters
$action = FN_GetParam("action", $_GET);
$id = FN_GetParam("id", $_GET, "int");

// Output HTML
echo "<h1>My Dynamic Page</h1>";

// Handle actions
switch ($action) {
    case "view":
        if ($id) {
            $item = FN_XMDBTable("mytable")->GetRecordByPrimaryKey($id);
            if ($item) {
                echo "<h2>" . htmlspecialchars($item['title']) . "</h2>";
                echo "<div>" . htmlspecialchars($item['content']) . "</div>";
            } else {
                echo "<p>Item not found</p>";
            }
        }
        break;

    case "list":
    default:
        $items = FN_XMDBTable("mytable")->GetRecords();
        echo "<ul>";
        foreach ($items as $item) {
            echo "<li><a href=\"?mod={$_FN['mod']}&action=view&id=" . intval($item['id']) . "\">" . htmlspecialchars($item['title']) . "</a></li>";
        }
        echo "</ul>";
        break;
}
```

### Creating a Form
```php
<?php
// sections/contact/section.php
global $_FN;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = FN_GetParam("name", $_POST, "html");
    $email = FN_GetParam("email", $_POST);
    $message = FN_GetParam("message", $_POST, "html");

    // Validation
    $errors = array();
    if (empty($name)) $errors[] = "Name required";
    if (empty($email) || !FN_CheckMail($email)) $errors[] = "Invalid email";
    if (empty($message)) $errors[] = "Message required";

    // If no errors, proceed
    if (empty($errors)) {
        // Send email
        $body = "Name: $name\nEmail: $email\n\nMessage:\n$message";
        if (FN_SendMail($_FN['site_email_address'], "Contact from website", $body)) {
            echo "<div class='success'>Message sent successfully!</div>";
        } else {
            echo "<div class='error'>Error sending message.</div>";
        }
    } else {
        // Show errors
        echo "<div class='error'>";
        foreach ($errors as $error) {
            echo "<p>$error</p>";
        }
        echo "</div>";
    }
}

// Show form
?>
<h1>Contact Us</h1>
<form method="post" action="?mod=<?php echo $_FN['mod']; ?>">
    <div class="form-group">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" value="<?php echo FN_GetParam("name", $_POST, "html"); ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo FN_GetParam("email", $_POST, "html"); ?>" required>
    </div>

    <div class="form-group">
        <label for="message">Message:</label>
        <textarea id="message" name="message" rows="5" required><?php echo FN_GetParam("message", $_POST, "html"); ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit">Send</button>
    </div>
</form>
```

### Using the API in JavaScript
```javascript
// Example of using the FINIS API from JavaScript (same site: the login cookies are sent)
function getProducts() {
    return fetch('index.php?fnapp=api&mod=products', { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            return data.items;
        });
}

// Using the function
getProducts()
    .then(items => {
        items.forEach(item => console.log(item.name));
    })
    .catch(error => {
        console.error('Error:', error.message);
    });
```

## Common Error Codes

| Code | Description | Possible Solution |
|------|-------------|-------------------|
| 401 | MCP endpoint without a valid API key or OAuth token (with `WWW-Authenticate`) | Send `Authorization: Bearer <key or token>` |
| 403 | Thumbnail of a protected record file refused (`$_FN['xmetadb_file_access']`) | Check the permissions of the user on the record |
| 405 | MCP endpoint called without POST | Use POST |
| 503 | MySQL unreachable, or busy (too many connections) for about 5 seconds of retries; the page has `Retry-After: 10` and a GET reloads itself | Check the MySQL server and its connection limits |
| Exception "Method ... does not exist" | Unknown `fnapp` value | Check the method name and the `include/methods/` folders |

A section the user may not view shows "you don't have permission to view this page".

## Best Practices

### Performance Optimization
- Use cache when possible
- Limit database queries
- Minimize CSS and JavaScript
- Use optimized images

### Security
- Always sanitize user input
- Use FN_GetParam() to get parameters, escape every value written in HTML
- Always verify permissions
- Implement CSRF protection for forms (POST plus a token)
- Never return `passwd`, `apikey`, `apikey_enc` or `rnd` of a user in `$_FN['return']`: use `FN_UserValuesForOutput()`

### Maintainability
- Document code with PHPDoc comments
- Follow naming conventions
- Organize code into logical functions
- Use constants for repeated values

## Appendix

### Constants and Global Variables
- `_FNEXEC`: Indicates the framework is running
- `$_FN['lang']`: Current language
- `$_FN['siteurl']`: Site base URL
- `$_FN['sitepath']`: Site base path
- `$_FN['datadir']`: Data directory
- `$_FN['mod']`: Current section
- `$_FN['theme']`: Current theme
- `$_FN['user']`: Current user
- `$_FN['uservalues']`: Record of the current user
- `$_FN['return']`: Data returned as JSON by `fnapp=api`
- `$_FN['consolemode']`: True in console mode
- `$_FN['src_finis']`, `$_FN['src_application']`: FINIS and application folders
- `$_FN['path_extensions']`: Folders registered with `addExtension()`
- `$_FN['tables']`: Table parameters set with `setTable()`
- `$_FN['auth_method']`: `apikey` or `oauth` when the request was authenticated by an API key or an OAuth token
- `$_FN['fnsid']`: Id of the server session (after `FN_GetSessionId()`)
- `$_FN['fnshell']`: True when the `website` method builds the theme shell (`fnshell=1`)

### Configuration Keys
Set in `config.vars.local.php` (defaults in the code or in `src/include/config.vars.php`):

| Key | Default | Meaning |
|---|---|---|
| `display_errors` | "off" | "on" shows the PHP errors (development) |
| `home_section_force` | "" | Home section that wins over the one saved in the control center |
| `remember_login_days` | 30 | Lifetime of the login cookies with "remember me" |
| `recovery_link_minutes` | 60 | Validity of a password recovery link |
| `login_max_attempts` | 5 | Login attempts allowed per username and IP |
| `login_lockout_minutes` | 15 | Time window of the attempts |
| `trusted_proxies` | "" | Reverse proxies whose `X-Forwarded-For` is trusted |
| `username_case_sensitive` | not set | Keep the case of the usernames |
| `apikey_groups` | "" | Groups allowed to use API keys ("" = everybody) |
| `mcp_write_group` | "mcp_write" | Group allowed to use the MCP write tools |
| `mcp_endpoints`, `mcp_guide`, `mcp_type_info` | not set | MCP endpoints and notes of the application (see [MCP guide](en_mcp_guide.md)) |
| `controlcenter_theme` | | Theme of the control center |
| `xmetadb_file_url` | not set | Callback `($table, $recordvalues, $recordkey, $thumb)` returning the URL of a record file |
| `xmetadb_file_access` | not set | Callback `($table, $id, $field)` deciding who may read a record file (thumbnail endpoint) |
| `dbview_list_template` | not set | List template file for every dbview section of the application |
| `dbview_toolbar_icons` | not set | Icons of the dbview toolbar |
| `dbview_excluded_values` | not set | Callback `($tablename)` returning `array(field => values)` of the records hidden from the user |
| `dbview_view_cache` | not set | Cache of the record sheets for anonymous visitors: `array(section => seconds)` or seconds for every section |
| `dbview_view_cache_tables`, `dbview_view_cache_files`, `dbview_view_cache_keep` | | Tables and files that invalidate a cached sheet; days the files are kept (30) |

### Supported Database Drivers
Drivers in `src/include/xmetadb/` (`XMETATable_<driver>.php`):
- `xmlphp`: XML/PHP files (default)
- `mysql`: MySQL/MariaDB (mysqli, utf8mb4)
- `sqlite3`: SQLite 3 (SQLite3 extension)
- `sqlite`: Legacy SQLite (needs the old `SQLiteDatabase` class)
- `sqlserver`: Microsoft SQL Server (sqlsrv extension)
- `csv`: CSV files
- `serialize`: Serialized PHP files

### System Requirements
- PHP 7.1 or higher (PHP 8.x supported)
- mysqli, SQLite3 or sqlsrv extension for the SQL drivers
- GD library for image manipulation (thumbnails)
- Optional: APCu for `FN_GetGlobalVarValue()` / `FN_SetGlobalVarValue()`; openssl to let the administrators show the users' API keys
- Write permissions for data directories

### Browser Compatibility
- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 16+
- Opera 47+
