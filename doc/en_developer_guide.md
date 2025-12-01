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

### Execution Flow
1. User requests a page (`index.php`)
2. Framework is initialized (`FINIS.php`)
3. The `mod` parameter is analyzed to determine the requested section
4. The section is loaded and executed
5. The result is rendered through the template system

## Creating Modules

### Basic Module Structure
Modules are contained in the `modules/` folder and each module has its own subfolder.

```
modules/
  └── modulename/
      ├── section.php       # Module main logic
      ├── config.php        # Module configurations
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

// Module main output
function MyModule_Main() {
    global $_FN;

    // Example of reading GET/POST parameters
    $action = FN_GetParam("action", $_GET, "string");

    // Example of reading configuration
    $config = FN_LoadConfig("modules/mymodule/config.php");

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
            $html .= "<li><a href=\"?mod={$_FN['mod']}&action=view&id={$item['id']}\">{$item['title']}</a></li>";
        }
        $html .= "</ul>";
    } else {
        $html .= "<p>" . FN_i18n("no_items_found") . "</p>";
    }

    return $html;
}

// Example function to view an item
function MyModule_ViewItem() {
    $id = FN_GetParam("id", $_GET, "int");

    $table = FN_XMDBTable("my_custom_table");
    $item = $table->GetRecordByPrimaryKey($id);

    if (isset($item['id'])) {
        $html = "<h2>{$item['title']}</h2>";
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
global $_FN;

// Default module configurations
$config = array(
    'items_per_page' => 10,
    'enable_comments' => 1,
    'default_sort' => 'title'
);

// Custom database definition for the module
if (!file_exists("{$_FN['datadir']}/fndatabase/my_custom_table.php")) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <name>id</name>
        <type>integer</type>
        <autoincrement>1</autoincrement>
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
```

### Control Panel Integration

To add your module to the control panel, create a folder in `controlcenter/sections/`:

```
controlcenter/
  └── sections/
      └── mymodule/
          ├── section.php
          ├── title.it.fn
          └── title.en.fn
```

**section.php**:
```php
<?php
/**
 * Admin panel for mymodule
 */
global $_FN;

// Check if user is administrator
if (!FN_IsAdmin()) {
    echo FN_i18n("access_denied");
    return;
}

// Handle administration operations
$op = FN_GetParam("op", $_GET);
switch ($op) {
    case "save":
        // Save configurations
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $config = FN_LoadConfig("modules/mymodule/config.php");
            $config['items_per_page'] = FN_GetParam("items_per_page", $_POST, "int");
            $config['enable_comments'] = FN_GetParam("enable_comments", $_POST, "int");
            $config['default_sort'] = FN_GetParam("default_sort", $_POST);

            // Save configurations
            $table = FN_XMDBTable("fncf_mymodule");
            foreach ($config as $key => $value) {
                $table->UpdateRecord(array("varname" => $key, "varvalue" => $value), "varname", $key);
            }

            echo "<div class='alert alert-success'>" . FN_i18n("settings_saved") . "</div>";
        }
        break;
}

// Load current configurations
$config = FN_LoadConfig("modules/mymodule/config.php");

// Configuration form
echo "<h2>" . FN_i18n("mymodule_settings") . "</h2>";
echo "<form method='post' action='?mod=" . $_FN['mod'] . "&op=save'>";
echo "<div class='form-group'>";
echo "<label>" . FN_i18n("items_per_page") . "</label>";
echo "<input type='number' name='items_per_page' value='" . $config['items_per_page'] . "' class='form-control'>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>" . FN_i18n("enable_comments") . "</label>";
echo "<select name='enable_comments' class='form-control'>";
echo "<option value='1' " . ($config['enable_comments'] ? "selected" : "") . ">" . FN_i18n("yes") . "</option>";
echo "<option value='0' " . (!$config['enable_comments'] ? "selected" : "") . ">" . FN_i18n("no") . "</option>";
echo "</select>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>" . FN_i18n("default_sort") . "</label>";
echo "<select name='default_sort' class='form-control'>";
echo "<option value='title' " . ($config['default_sort'] == 'title' ? "selected" : "") . ">" . FN_i18n("title") . "</option>";
echo "<option value='created_date' " . ($config['default_sort'] == 'created_date' ? "selected" : "") . ">" . FN_i18n("date") . "</option>";
echo "</select>";
echo "</div>";

echo "<button type='submit' class='btn btn-primary'>" . FN_i18n("save") . "</button>";
echo "</form>";
```

## Working with the Database

### Creating Tables
FINIS uses an XML-based table description system. Here's how to define a table:

```php
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <name>id</name>
        <type>integer</type>
        <autoincrement>1</autoincrement>
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

// Insert a record
$newRecord = array(
    'name' => 'New item',
    'description' => 'Item description'
);
$id = $table->InsertRecord($newRecord);

// Read a record
$record = $table->GetRecordByPrimaryKey($id);

// Update a record
$updatedRecord = array(
    'name' => 'Updated name',
    'description' => 'Updated description'
);
$table->UpdateRecord($updatedRecord, "id", $id);

// Delete a record
$table->DelRecord($id);

// Get all records
$records = $table->GetRecords();

// Get filtered records
$filteredRecords = $table->GetRecords(array('name' => 'Filter'));
```

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
- Files in `include/autoexec.d/` are executed at startup
- Files in `include/on_site_change.d/` are executed when the site changes

### Hook Example
```php
<?php
// Save as include/autoexec.d/99_mymodule.php
global $_FN;

// Add script only for certain pages
if ($_FN['mod'] == 'home') {
    $_FN['header_append'] .= '<script src="modules/mymodule/js/script.js"></script>';
}
```

## Debugging
The framework offers several functions for debugging:

```php
// Display a structured array
dprint_r($myArray);

// Logging
FN_Log("Debug message");

// Execution time
echo FN_GetExecuteTimer(); // time from start in seconds

// Partial timer
echo FN_GetPartialTimer(); // time since last call
```

## Best Practices

### Security
- Always sanitize user input with `FN_GetParam()`
- Use prepared statements for SQL queries
- Verify user authorization with `FN_UserInGroup()` or `FN_UserCanViewSection()`

### Code Structure
- Maintain separation between logic and presentation
- Use configuration files for settings
- Document functions with PHPDoc comments
- Use framework functions instead of reimplementing the same functionality

### Performance
- Use cache when possible
- Consider using optimized queries for large databases
- Load JS scripts asynchronously

## Advanced Examples

### Creating a Plugin
Plugins are components that extend core functionality without creating new sections.

**Plugin structure**:
```
plugins/
  └── myplugin/
      ├── plugin.php        # Plugin main code
      ├── controlcenter/    # Administration interface
      │   └── settings.php
      └── functions.php     # Helper functions
```

**plugin.php**:
```php
<?php
/**
 * Example plugin
 */
global $_FN;

// Add global scripts or functionality
$_FN['header_append'] .= '<script src="plugins/myplugin/js/script.js"></script>';

// Add hook to intercept events
function myplugin_on_user_login($username) {
    FN_Log("User $username has logged in");
}

// Register the function in the hook
$_FN['hooks']['user_login'][] = 'myplugin_on_user_login';
```

### Extending the Control Panel
To add functionality to the administration panel:

```php
<?php
// In controlcenter/sections/myplugin/section.php
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
- Check PHP logs and framework logs in `misc/log/`

### Database Problems
- Verify write permissions on `misc/fndatabase/` folders
- Check database connection for external drivers (MySQL, etc.)
- Use `$table->GetError()` to get detailed error messages

### Module Problems
- Ensure the folder structure is correct
- Verify that configuration files are accessible
- Check for name conflicts between modules
