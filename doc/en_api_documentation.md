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
- Classes start with uppercase letters (e.g., `XMETATable`)
- Constants are in uppercase with underscores (e.g., `_FNEXEC`)
- Global variables are inside the `$_FN` array

## Core API

### FINIS Class
The main class that initializes and manages the framework.

#### Constructor
```php
/**
 * Initialize the FINIS framework
 * @param array $config Optional configurations
 */
function __construct($config = array())
```

Example:
```php
require_once "path/to/FINIS.php";
$FINIS = new FINIS(array(
    "src_application" => ".",
    "display_errors" => "on"
));
```

#### Main Methods

```php
/**
 * Execute a specific section
 * @param string $section Section ID
 */
function runSection($section = "")

/**
 * Execute a folder as if it were a section
 * @param string $folder Folder path
 */
function runFolder($folder)

/**
 * Execute the main application
 */
function finis()

/**
 * Set a configuration variable
 * @param string $id Variable name
 * @param mixed $value Value
 */
function setVar($id, $value)

/**
 * Check if the application is running in console mode
 * @return bool
 */
function isConsole()
```

### General Functions

#### Parameter Handling
```php
/**
 * Get a parameter from a variable
 * @param string $key Parameter name
 * @param array $var Array to extract the parameter from
 * @param string $type Data type (html, int, float)
 * @return mixed Parameter value
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
 * Get the path for a file considering the theme
 * @param string $file File to search for
 * @param bool $absolute Whether to return an absolute path
 * @return string Path to the file
 */
function FN_FromTheme($file, $absolute = true)

/**
 * Convert a local path to absolute
 * @param string $filepath File path
 * @param bool $urlAbsolute Whether to return absolute URL
 * @return string Complete path
 */
function FN_PathSite($filepath, $urlAbsolute = false)

/**
 * Return the appropriate icon for a file type
 * @param string $filename File name
 * @return string Icon URL
 */
function FN_GetIconByFilename($filename)
```

#### Localization and Translation
```php
/**
 * Translate a string to the current language
 * @param string $text Text to translate
 * @param string $context Optional context
 * @return string Translated text
 */
function FN_i18n($text, $context = "")

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
 * Log an event in the system log
 * @param string $event Event message
 * @param string $context Event context
 */
function FN_LogEvent($event, $context = "cms")

/**
 * Write a message to the log
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
Main class for interacting with the database.

#### Creation and Initialization
```php
/**
 * Get an instance of the table
 * @param string $tablename Table name
 * @return object XMETATable instance
 */
function FN_XMDBTable($tablename)
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
 * @param array $record Record data
 * @return mixed Inserted record ID or false
 */
function InsertRecord($record)

/**
 * Update an existing record
 * @param array $record New data
 * @param string $fieldname Key field
 * @param mixed $fieldvalue Key value
 * @return bool Success
 */
function UpdateRecord($record, $fieldname, $fieldvalue)

/**
 * Update a record via primary key
 * @param array $record New data
 * @param string $pkfield Primary key field name
 * @param mixed $pkvalue Primary key value
 * @return bool Success
 */
function UpdateRecordBypk($record, $pkfield, $pkvalue)

/**
 * Delete a record
 * @param mixed $id Primary key value
 * @return bool Success
 */
function DelRecord($id)

/**
 * Get all records
 * @param array $filter Optional filter
 * @return array List of records
 */
function GetRecords($filter = array())

/**
 * Get a record by primary key
 * @param mixed $id Primary key value
 * @return array Record or false
 */
function GetRecordByPrimaryKey($id)
```

Example:
```php
// Insert a new user
$userData = array(
    'username' => 'newuser',
    'email' => 'user@example.com',
    'password' => md5('password123'),
    'regdate' => FN_Now(),
    'active' => 1
);
$userId = $usersTable->InsertRecord($userData);

// Update a user
$usersTable->UpdateRecord(
    array('email' => 'new@example.com'),
    'username',
    'newuser'
);

// Get a specific user
$user = $usersTable->GetRecordByPrimaryKey(5);

// Get filtered users
$admins = $usersTable->GetRecords(array('group' => 'admin'));

// Delete a user
$usersTable->DelRecord(5);
```

#### Advanced Queries
```php
/**
 * Execute a custom SQL query
 * @param string $query SQL query
 * @return array Query results
 */
function FN_XMETADBQuery($query)
```

Example:
```php
// Custom query
$results = FN_XMETADBQuery("
    SELECT u.username, g.groupname
    FROM fn_users u
    JOIN fn_groups g ON u.group = g.groupname
    WHERE u.active = 1
");
```

### XMETAForm Class
Class for managing forms linked to tables.

```php
/**
 * Get a form instance for a table
 * @param string $tablename Table name
 * @return object XMETAForm instance
 */
function FN_XMDBForm($tablename)
```

Example:
```php
// Get a form for the users table
$usersForm = FN_XMDBForm("fn_users");

// Generate HTML for the form
$formHtml = $usersForm->GetForm(array(
    'action' => 'add',
    'record' => array(),  // For edit, insert existing record
    'redirect' => '?mod=users'
));
```

## Template API

### Template Management
```php
/**
 * Load a configuration from a file
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
 * Include CSS from framework and sections
 * @return string HTML tags for CSS
 */
function FN_IncludeCSS()

/**
 * Include JavaScript from framework
 * @return string HTML tags for JavaScript
 */
function FN_IncludeJS()
```

### HTML and URL Manipulation
```php
/**
 * Convert BBCode to HTML
 * @param string $string Text with BBCode
 * @return string Resulting HTML
 */
function FN_Tag2Html($string)

/**
 * Convert relative links to absolute
 * @param string $str HTML content
 * @param string $folder Base directory
 * @return string HTML with absolute links
 */
function FN_RewriteLinksLocalToAbsolute($str, $folder)

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

## Sections API

### Section Management
```php
/**
 * Check if a user can view a section
 * @param string $section Section ID
 * @param string $user Username (optional)
 * @return bool
 */
function FN_UserCanViewSection($section, $user = "")

/**
 * Check if a user can edit a section
 * @param string $section Section ID
 * @param string $user Username (optional)
 * @return bool
 */
function FN_UserCanEditSection($section, $user = "")

/**
 * Get the values of a section
 * @param string $sectionid Section ID
 * @return array Section data
 */
function FN_GetSectionValues($sectionid)

/**
 * Execute a section
 * @param string $section Section ID
 * @param bool $return Whether to return output instead of printing
 * @return mixed Output or null
 */
function FN_RunSection($section, $return = false)

/**
 * Execute a folder as a section
 * @param string $folder Folder path
 * @param bool $return Whether to return output
 * @return mixed Output or null
 */
function FN_RunFolder($folder, $return = false)
```

### Navigation
```php
/**
 * Get menu items
 * @param string $level Menu level
 * @param string $parent Parent section
 * @return array Menu items
 */
function FN_GetMenuEntries($level = "top", $parent = "")

/**
 * Get the navigation path
 * @param string $section Section ID
 * @return array Path
 */
function FN_GetPath($section = "")
```

## Users and Groups API

### User Management
```php
/**
 * Check if the current user is an administrator
 * @return bool
 */
function FN_IsAdmin()

/**
 * Get user data
 * @param string $username Username
 * @return array User data or false
 */
function FN_GetUser($username)

/**
 * Check if a user belongs to a group
 * @param string $user Username
 * @param string $group Group name or comma-separated groups
 * @return bool
 */
function FN_UserInGroup($user, $group)

/**
 * Create a group if it doesn't exist
 * @param string $groupname Group name
 */
function FN_CreateGroupIfNotExists($groupname)
```

### Authentication
```php
/**
 * Verify user credentials
 * @param string $username Username
 * @param string $password Password
 * @return bool Success
 */
function FN_CheckUserPass($username, $password)

/**
 * Start a user session
 * @param string $username Username
 * @param bool $remember Persistent cookie
 */
function FN_Login($username, $remember = false)

/**
 * End the current user session
 */
function FN_Logout()
```

## File and Directory API

### File Operations
```php
/**
 * Write content to a file
 * @param string $string Content
 * @param string $path File path
 * @param string $mode Mode (default: w)
 * @return bool Success
 */
function FN_Write($string, $path, $mode = "w")

/**
 * Copy a file
 * @param string $source Source file
 * @param string $dest Destination file
 * @param bool $overwrite Overwrite if exists
 * @return bool Success
 */
function FN_Copy($source, $dest, $overwrite = false)

/**
 * Copy a directory recursively
 * @param string $source Source dir
 * @param string $dest Destination dir
 * @param bool $overwrite Overwrite existing files
 * @return bool Success
 */
function FN_CopyDir($source, $dest, $overwrite = false)

/**
 * Create a directory
 * @param string $path Directory path
 * @param int $mode Permissions (default: 0755)
 * @return bool Success
 */
function FN_MkDir($path, $mode = 0755)

/**
 * Remove a directory recursively
 * @param string $dir Directory path
 * @return bool Success
 */
function FN_RemoveDir($dir)
```

### File Utilities
```php
/**
 * Get the file extension
 * @param string $filename File name
 * @return string Extension
 */
function FN_GetFileExtension($filename)

/**
 * Get the MIME type of a file
 * @param string $filename File name
 * @return string MIME type
 */
function FN_GetMimeType($filename)

/**
 * Generate a safe filename
 * @param string $filename Original file name
 * @return string Safe file name
 */
function FN_CreateSafeFilename($filename)
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
 * Redirect to another page
 * @param string $url Destination URL
 */
function FN_Redirect($url)

/**
 * Check if the referrer is external
 * @return bool
 */
function FN_IsExternalReferer()

/**
 * Send a file to the browser for download
 * @param string $filecontents File content
 * @param string $filename File name
 * @param string $HeaderContentType MIME type
 */
function FN_SaveFile($filecontents, $filename, $HeaderContentType = "application/force-download")
```

## REST API

### Base Endpoint
FINIS provides a REST API system accessible through the `fnapi` parameter:

```
http://yoursite.com/?fnapi=api_name&action=action_name&param1=value1
```

#### API Request Handling
```php
/**
 * Handle an API request
 * @param string $apiName API name
 * @param string $action Requested action
 * @param array $params Additional parameters
 * @return mixed API result
 */
function FN_HandleApiRequest($apiName, $action, $params = array())
```

### Users API Example
```php
// Users API (include/methods/api.php)
function api_users($action, $params) {
    switch ($action) {
        case 'get':
            // Check permissions
            if (!FN_IsAdmin()) {
                return array('error' => 'Unauthorized');
            }

            $userId = FN_GetParam('id', $params, 'int');
            if ($userId) {
                $user = FN_XMDBTable('fn_users')->GetRecordByPrimaryKey($userId);
                // Remove sensitive data
                unset($user['password']);
                return $user;
            } else {
                $users = FN_XMDBTable('fn_users')->GetRecords();
                foreach ($users as &$user) {
                    unset($user['password']);
                }
                return $users;
            }
            break;

        case 'create':
            // User creation implementation
            break;

        // Other actions...
    }
}
```

### API Request Example
API access:
```
http://yoursite.com/?fnapi=users&action=get&id=5
```

Response (in JSON format):
```json
{
    "id": 5,
    "username": "johndoe",
    "email": "john@example.com",
    "name": "John Doe",
    "regdate": "2023-01-15 10:30:00",
    "group": "users,editors",
    "active": 1
}
```

## Extensibility

### Hooks and Callbacks
FINIS uses a hook system to allow extending behavior:

```php
// Register a function for a hook
$_FN['hooks']['user_login'][] = 'my_login_callback';

// Callback function
function my_login_callback($username) {
    // Actions to perform at login
    FN_Log("User $username has logged in");
}

// Execute a hook
if (!empty($_FN['hooks']['user_login'])) {
    foreach ($_FN['hooks']['user_login'] as $callback) {
        if (function_exists($callback)) {
            call_user_func($callback, $username);
        }
    }
}
```

### Autoexec Scripts
Files in the `include/autoexec.d/` folder are automatically executed at framework startup:

```php
// include/autoexec.d/99_custom.php
global $_FN;

// Add custom CSS or JS
if ($_FN['mod'] == 'home') {
    $_FN['header_append'] .= '<script src="path/to/script.js"></script>';
}

// Register hooks
$_FN['hooks']['user_login'][] = 'custom_login_handler';
```

### on_site_change Scripts
Files in the `include/on_site_change.d/` folder are executed when site contents change:

```php
// include/on_site_change.d/sitemap_generator.php
global $_FN;

// Generate sitemap when contents change
function regenerate_sitemap() {
    // Logic to generate sitemap
}

// Register function to be called when site changes
$_FN['on_site_change_callbacks'][] = 'regenerate_sitemap';
```

## Security

### Input Sanitization
```php
/**
 * Sanitize input to prevent XSS
 * @param string $str String to sanitize
 * @return string Sanitized string
 */
function FN_HtmlEncode($str)

/**
 * Check if a string matches a regex pattern
 * @param string $pattern Regex pattern
 * @param string $string String to check
 * @return bool Match found
 */
function FN_erg($pattern, $string)

/**
 * Check if an email is valid
 * @param string $email Email address
 * @return bool Validity
 */
function FN_CheckMail($email)
```

### Permission Management
```php
/**
 * Check if the current user can access a feature
 * @param string $permission Permission name
 * @return bool
 */
function FN_UserCan($permission)

/**
 * Generate a CSRF token
 * @param string $action Action name
 * @return string Token
 */
function FN_GetCSRFToken($action)

/**
 * Verify a CSRF token
 * @param string $token Token to verify
 * @param string $action Action name
 * @return bool Validity
 */
function FN_VerifyCSRFToken($token, $action)
```

## Cache

### Cache Management
```php
/**
 * Clear the cache
 * @return bool Success
 */
function FN_ClearCache()

/**
 * Set a value in the cache
 * @param string $key Key
 * @param mixed $value Value
 * @param int $ttl Expiration in seconds
 * @return bool Success
 */
function FN_SetCache($key, $value, $ttl = 3600)

/**
 * Get a value from the cache
 * @param string $key Key
 * @return mixed Value or false
 */
function FN_GetCache($key)

/**
 * Remove a value from the cache
 * @param string $key Key
 * @return bool Success
 */
function FN_DeleteCache($key)
```

## Modules

### Module Management
```php
/**
 * Check if a module exists
 * @param string $moduleName Module name
 * @return bool
 */
function FN_ModuleExists($moduleName)

/**
 * Get the path of a module
 * @param string $moduleName Module name
 * @return string Path or false
 */
function FN_ModulePath($moduleName)

/**
 * Load a module
 * @param string $moduleName Module name
 * @return bool Success
 */
function FN_LoadModule($moduleName)
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
                echo "<h2>{$item['title']}</h2>";
                echo "<div>{$item['content']}</div>";
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
            echo "<li><a href=\"?mod={$_FN['mod']}&action=view&id={$item['id']}\">{$item['title']}</a></li>";
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
        <input type="email" id="email" name="email" value="<?php echo FN_GetParam("email", $_POST); ?>" required>
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
// Example of using the FINIS API from JavaScript
function getUserData(userId) {
    return fetch(`?fnapi=users&action=get&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            return data;
        });
}

// Using the function
getUserData(5)
    .then(user => {
        console.log(`Username: ${user.username}`);
        console.log(`Email: ${user.email}`);
    })
    .catch(error => {
        console.error('Error:', error.message);
    });
```

## Common Error Codes

| Code | Description | Possible Solution |
|------|-------------|-------------------|
| 403 | Access denied | Check user permissions |
| 404 | Section not found | Verify section ID |
| 500 | Internal server error | Check logs for details |
| FN001 | DB configuration error | Verify database settings |
| FN002 | File not found | Check file path |
| FN003 | File permission error | Check directory CHMOD |

## Best Practices

### Performance Optimization
- Use cache when possible
- Limit database queries
- Minimize CSS and JavaScript
- Use optimized images

### Security
- Always sanitize user input
- Use FN_GetParam() to get parameters
- Always verify permissions
- Implement CSRF protection for forms

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

### Supported Database Drivers
- `xmlphp`: XML/PHP files (default)
- `mysql`: MySQL/MariaDB
- `sqlite`: SQLite
- `sqlserver`: Microsoft SQL Server
- `csv`: CSV files

### System Requirements
- PHP 7.0 or higher
- PDO extension for SQL databases
- GD library for image manipulation
- Write permissions for data directories

### Browser Compatibility
- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 16+
- Opera 47+
