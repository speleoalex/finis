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
- `<type>`: The SQL data type (e.g., `check`, `decimal`, `datetime`, `image`)
- `<frm_type>`: The form input type (e.g., `text`, `select`, `check`, `password`, `datetime`)
- `<frm_i18n>`: The translatable label for forms
- `<frm_required>`: Whether the field is required in forms (1 or 0)
- `<frm_show>`: Whether to show in forms (1 or 0)
- `<frm_setonlyadmin>`: Only admins can set this field (1 or 0)
- `<frm_allowupdate>`: Control update permissions (`0`, `1`, `onlyadmin`)
- `<size>`: Maximum field size for VARCHAR fields
- `<defaultvalue>`: Default value for the field
- `<unique>`: Whether the field must be unique (1 or 0)
- `<primarykey>`: Whether this is a primary key (1 or 0)
- `<extra>`: Additional SQL attributes (e.g., `auto_increment`)

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

## Main Tables

*   `fn_users`: Stores user information.
*   `fn_groups`: Stores user groups.
*   `fn_sections`: Stores information about the pages of the site.
*   `fn_settings`: Stores application settings.
