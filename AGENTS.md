# FINIS Framework - Agent Development Guide

This guide provides essential information for agentic coding assistants working with the FINIS PHP framework and CMS.

## Build/Test/Development Commands

### Development Server
```bash
# Start PHP development server (from src/ directory)
php -S localhost:8000 -t src/

# Navigate to site and run setup wizard
# http://localhost:8000/
```

### Testing
```bash
# Run specific test file
php src/test_cron.php

# Test via web browser
# http://localhost:8000/test_cron.php
```

### Cache Management
```bash
# Clear cache (default 1000 files per batch)
php utils/clear_cache.php

# Clear all cache files
php utils/clear_cache.php --all

# Clear with custom batch size and verbose output
php utils/clear_cache.php ./misc/_cache 500 --all --verbose
```

### Utilities
```bash
# Process i18n translations
php utils/i18n.php
```

## Code Style Guidelines

### General Rules
- **Language**: Use English for all comments and variable names
- **Indentation**: 4 spaces (no tabs)
- **Encoding**: UTF-8
- **PHP Tags**: Use `<?php` opening tag; closing `?>` optional for pure PHP files
- **Line Length**: Keep lines under 120 characters when practical

### Naming Conventions
| Type | Convention | Example |
|------|------------|---------|
| Functions | PascalCase with `FN_` prefix | `FN_GetParam()`, `FN_Translate()` |
| Variables | snake_case | `$user_data`, `$cache_dir` |
| Global Variables | Access via `$_FN` array | `$_FN['siteurl']` |
| Classes | PascalCase | `FINIS`, `XMETATable` |
| Field Classes | lowercase with prefix | `xmetadbfrm_field_check` |
| Constants | UPPER_CASE | `_FNEXEC`, `_PATH_NEWS_` |

### File Organization
```
src/
  ├── controlcenter/    # Admin panel files
  ├── include/          # Core framework files
  │   ├── classes/      # PHP classes
  │   ├── methods/      # Dynamic methods for FINIS class
  │   ├── xmetadb/      # Database abstraction layer
  │   └── xmetadbfrm_fields/  # Form field type classes
  ├── modules/          # Reusable modules (news, login, etc.)
  ├── sections/         # Site pages/sections
  ├── themes/           # Theme templates
  └── languages/        # Translation files
misc/fndatabase/        # Database table definitions (XML)
```

### PHPDoc Standards
```php
/**
 * Brief description of function
 *
 * @param string $key Parameter description
 * @param array $var Source array (default $_REQUEST)
 * @param string $type Return type (html, int, float)
 * @return mixed Description of return value
 * @global array $_FN
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
function FN_GetParam($key, $var = false, $type = "")
```

## Security Patterns

### File Access Protection
All PHP files that should not be directly accessed must include:
```php
<?php
defined('_FNEXEC') or die('Restricted access');
```

### Input Validation
Always use `FN_GetParam()` with type parameter:
```php
$id = FN_GetParam("id", $_GET, "int");        // Integer
$name = FN_GetParam("name", $_POST, "html");  // HTML-escaped
$price = FN_GetParam("price", $_REQUEST, "float");  // Float
```

## Section Structure

Each section requires a folder in `sections/` with these files:

### 1. `default.xml.php` - Metadata
```xml
<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<fn_sections>
    <type>finis</type>
    <title>Page Title</title>
    <status>1</status>
    <hidden>0</hidden>
    <parent></parent>
    <title_en>English Title</title_en>
</fn_sections>
```

### 2. `section.php` - Logic
```php
<?php
global $_FN;
$SECTION = array();
$SECTION['items'] = FN_XMDBTable("table_name")->GetRecords();
```

### 3. `section.html` - Template
```html
<h2>{section_title}</h2>
<!-- if {items} -->
<!-- foreach {items} -->
<p>{name}: {value}</p>
<!-- end foreach {items} -->
<!-- end if {items} -->
```

## Database Operations

### Table Definition (misc/fndatabase/tablename.php)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0); ?>
<tables>
    <field>
        <name>id</name>
        <type>int</type>
        <primarykey>1</primarykey>
        <extra>autoincrement</extra>
    </field>
    <field>
        <name>title</name>
        <type>varchar</type>
        <size>255</size>
        <frm_type>text</frm_type>
        <frm_i18n>Title</frm_i18n>
        <frm_required>1</frm_required>
    </field>
</tables>
```

**Note**: Use `autoincrement` (no underscore), not `auto_increment`

### Database API
```php
// Get table object
$table = FN_XMDBTable("fn_users");

// CRUD operations
$records = $table->GetRecords();
$table->AddRecord($data);
$table->UpdateRecord($id, $data);
$table->DeleteRecord($id);

// Custom queries
$results = FN_XMETADBQuery("SELECT * FROM fn_users WHERE level > 5");
```

## Common Functions Reference

| Function | Purpose |
|----------|---------|
| `FN_GetParam($key, $var, $type)` | Get request parameter with type casting |
| `FN_Translate($str, $mode, $lang)` | Translate string (alias: `FN_i18n`) |
| `FN_IsAdmin()` | Check if current user is admin |
| `FN_UserInGroup($user, $group)` | Check user group membership |
| `FN_Redirect($url)` | HTTP redirect |
| `FN_LogEvent($event, $context)` | Log event |
| `FN_FromTheme($file)` | Get path to theme file |
| `FN_HtmlContent($folder)` | Load section content |

## Error Handling

```php
// Logging
FN_LogEvent("User login failed", "auth");
FN_Log("Debug message");

// Check return values
$result = $table->AddRecord($data);
if (!$result) {
    FN_LogEvent("Failed to add record", "database");
}
```

## Development Workflow

1. **Read Documentation**: Always check `doc/Finis_Framework_LLM_Guide.md` first
2. **Create Section**: Add folder in `sections/` with required files
3. **Database Changes**: Add XML definition in `misc/fndatabase/`
4. **Test**: Run test files or access via browser
5. **Clear Cache**: `php utils/clear_cache.php --all`
6. **Translations**: Add strings to `languages/[lang]/lang.csv`

## Important Notes

- Clear cache after template or configuration changes
- Test thoroughly after database schema modifications
- Use English for all new code and comments
- Follow existing patterns in the codebase
- The `$_FN` global array contains all framework configuration
