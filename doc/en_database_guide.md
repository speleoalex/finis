# FINIS Database Abstraction Layer Guide

## Introduction

The FINIS framework offers a powerful database abstraction layer called XMETATable that allows interacting with different data storage systems in a unified way. This guide illustrates how to use XMETATable to manage application data regardless of the underlying database.

## Fundamental Concepts

### XMETATable Architecture

XMETATable is a class that implements a multi-driver architecture for data access. The main features are:

1. **Multiple Drivers**: Supports various storage systems (MySQL, SQLite, XML/PHP, CSV, etc.)
2. **Unified Interface**: Same functions for all drivers
3. **Simplified Migration**: Ability to switch from one system to another without modifying application code
4. **Built-in File Management**: Functionality to manage files and images linked to records

### Available Drivers

FINIS supports the following database drivers:

- `xmlphp`: XML/PHP file storage (default option)
- `mysql`: MySQL/MariaDB
- `sqlite`: SQLite
- `sqlite3`: SQLite version 3
- `sqlserver`: Microsoft SQL Server
- `csv`: CSV files
- `serialize`: Serialized PHP files

## Basic Usage

### Getting an XMETATable Instance

To get a table instance, use the `FN_XMDBTable()` function:

```php
// Get a reference to the users table
$usersTable = FN_XMDBTable("fn_users");
```

### Table Definition

Tables are defined through XML files with `.php` extension. Example descriptor file:

```xml
<?php exit(0);?>
<tables>
  <field>
    <name>id</name>
    <type>int</type>
    <primarykey>1</primarykey>
    <extra>autoincrement</extra>
  </field>
  <field>
    <name>username</name>
    <type>string</type>
    <size>50</size>
  </field>
  <field>
    <name>email</name>
    <type>string</type>
    <size>100</size>
  </field>
  <field>
    <name>password</name>
    <type>string</type>
    <size>32</size>
  </field>
  <field>
    <name>regdate</name>
    <type>datetime</type>
  </field>
  <driver>xmlphp</driver>
</tables>
```

### Basic CRUD Operations

#### Record Insertion

```php
// Create a new user
$userData = array(
    'username' => 'newuser',
    'email' => 'user@example.com',
    'password' => md5('password123'),
    'regdate' => FN_Now()
);

// Insert record and get complete record with generated ID
$newUser = $usersTable->InsertRecord($userData);
```

#### Record Reading

```php
// Get all records
$allUsers = $usersTable->GetRecords();

// Get records with filter
$adminUsers = $usersTable->GetRecords(array('group' => 'admin'));

// Get record by primary key
$user = $usersTable->GetRecordByPrimaryKey(5);

// Get a single record with filter
$user = $usersTable->GetRecord(array('username' => 'admin'));
```

#### Record Update

```php
// Update a record by primary key
$usersTable->UpdateRecordBypk(
    array('email' => 'new@example.com'),
    'id',
    5
);

// Update a record providing complete data
$userData = array(
    'id' => 5,
    'email' => 'new@example.com',
    'lastlogin' => FN_Now()
);
$usersTable->UpdateRecord($userData);
```

#### Record Deletion

```php
// Delete a record by ID
$usersTable->DelRecord(5);
```

## Advanced Features

### Pagination and Sorting

```php
// Get paginated records (from 10th to 20th record)
$users = $usersTable->GetRecords(false, 10, 10);

// Get records sorted by name (ascending)
$users = $usersTable->GetRecords(false, false, false, 'username');

// Get records sorted by date (descending)
$users = $usersTable->GetRecords(false, false, false, 'regdate', true);

// Multiple sorting
$users = $usersTable->GetRecords(false, false, false, 'group,username');
```

### Complex Filters

```php
// Simple filtering with array
$activeUsers = $usersTable->GetRecords(array(
    'active' => 1,
    'group' => 'users'
));

// Advanced filtering with SQL string (only with SQL drivers)
$recentUsers = $usersTable->GetRecords("regdate > '2023-01-01' AND active = 1");
```

### Record Counting

```php
// Count all records
$totalUsers = $usersTable->GetNumRecords();

// Count with filter
$totalActiveUsers = $usersTable->GetNumRecords(array('active' => 1));
```

### File and Image Management

XMETATable automatically handles uploading, storing, and retrieving files and images associated with records:

```php
// In an HTML form:
<form method="post" enctype="multipart/form-data">
    <input type="file" name="avatar">
    <!-- other fields -->
    <button type="submit">Save</button>
</form>

// In PHP code to save:
if ($_FILES['avatar']['tmp_name']) {
    $userData['avatar'] = $_FILES['avatar']['name'];
    $usersTable->UpdateRecordBypk($userData, 'id', $userId);
    // File upload is automatically handled by XMETATable
}

// To retrieve the image path:
$user = $usersTable->GetRecordByPrimaryKey($userId);
$avatarPath = $usersTable->getFilePath($user, 'avatar');

// To retrieve the thumbnail URL (for images):
$thumbUrl = $usersTable->get_thumb($user, 'avatar');
```

## Database Configuration

### MySQL Configuration

To configure a MySQL connection, modify the `config.vars.local.php` file:

```php
// Configuration for MySQL driver
$_FN['default_database_driver'] = 'mysql';
$_FN['xmetadb_mysqlhost'] = 'localhost';
$_FN['xmetadb_mysqldatabase'] = 'finis';
$_FN['xmetadb_mysqlusername'] = 'username';
$_FN['xmetadb_mysqlpassword'] = 'password';
```

### SQLite Configuration

To use SQLite as database:

```php
// Configuration for SQLite driver
$_FN['default_database_driver'] = 'sqlite';
$_FN['xmetadb_sqlitepath'] = $path_to_db_file;
```

### Table-Level Configuration

It's possible to override global configuration at the table level in the XML descriptor file:

```xml
<tables>
  <!-- field definitions -->
  <driver>mysql</driver>
  <host>db.example.com</host>
  <user>username</user>
  <password>password</password>
  <database>my_database</database>
  <sqltable>table_name</sqltable>
</tables>
```

## Database Migration

### From XML/PHP to MySQL

To migrate a table from XML/PHP to MySQL:

```php
require_once "path/to/include/xmetadb/XMETATable_mysql.php";

$connection = array(
    'host' => 'localhost',
    'user' => 'username',
    'password' => 'password',
    'database' => 'my_database',
    'sqltable' => 'users'
);

xml_to_sql('database_name', 'fn_users', 'misc', $connection);
```

## Schemas and Field Definitions

### Supported Field Types

- `string` / `varchar`: Text strings (limited length)
- `text`: Long text
- `html`: HTML text
- `int`: Integers
- `datetime`: Dates and timestamps
- `file`: File upload field
- `image`: Image upload field (with automatic thumbnail generation)
- `base64file`: Files stored as base64 in database

### Field Properties

```xml
<field>
  <name>field_name</name>         <!-- field name -->
  <type>string</type>              <!-- field type -->
  <size>100</size>                 <!-- size (for varchar) -->
  <primarykey>1</primarykey>       <!-- indicates primary key -->
  <extra>autoincrement</extra>     <!-- auto-incrementing field -->
  <thumbsize>100</thumbsize>       <!-- thumbnail size for images -->
  <mysql_default>NULL</mysql_default> <!-- default value (MySQL) -->
  <mysql_on_update>CURRENT_TIMESTAMP</mysql_on_update> <!-- automatic update -->
</field>
```

## Performance Optimization

### Using Cache

XMETATable can use a cache to improve performance:

```xml
<tables>
  <!-- field definitions -->
  <usecachefile>1</usecachefile>
</tables>
```

### Fast Operations

For fast inserts or updates without file management:

```php
// Fast insert (without file management)
$usersTable->InsertRecordFast($userData);

// Fast update (without file management)
$usersTable->UpdateRecordFast($userData);
```

## System Extension

### Creating a Custom Driver

To create a new driver for XMETATable:

1. Create a class extending `stdClass` in the file `XMETATable_mydriver.php`
2. Implement all required methods (GetRecords, InsertRecord, UpdateRecordBypk, DelRecord, etc.)
3. Register the driver in the table's XML descriptor

Basic structure example:

```php
<?php
class XMETATable_mydriver extends stdClass
{
    function __construct(&$xmltable, $params = false)
    {
        // Initialization
    }

    function GetRecords($restr = false, $min = false, $length = false, $order = false, $reverse = false, $fields = array())
    {
        // Implementation
    }

    function InsertRecord($values)
    {
        // Implementation
    }

    // Other required methods
}
```

## Best Practices

1. **Primary Keys**: Always define a primary key for each table
2. **Transactions**: When possible, use transactions for multiple operations
3. **Null Fields**: Handle null values correctly for each driver
4. **Security**: Always use `FN_GetParam()` to get user data before inserting
5. **Backup**: Create regular data backups, especially when using the xmlphp driver

## Troubleshooting

### Common Problems

- **"Table not exists" error**: Verify the table descriptor file exists in the correct path.
- **"File not writable" error**: Check directory write permissions.
- **"Database not writable" error**: Verify the database user has correct permissions.
- **MySQL connection error**: Check credentials and that the server is running.

### Debug

To enable database error logging:

```php
define("XMETADB_DEBUG_FILE_LOG", "/path/to/logfile.log");
```

## Practical Examples

### Example 1: User Management System

```php
// Get users table instance
$usersTable = FN_XMDBTable("fn_users");

// User registration
function registerUser($username, $email, $password) {
    global $usersTable;

    // Check if user already exists
    $existingUser = $usersTable->GetRecord(array('username' => $username));
    if ($existingUser) {
        return array('error' => 'Username already in use');
    }

    // Register new user
    $userData = array(
        'username' => $username,
        'email' => $email,
        'password' => md5($password),
        'regdate' => FN_Now(),
        'active' => 1,
        'group' => 'users'
    );

    $newUser = $usersTable->InsertRecord($userData);
    return $newUser ? array('success' => true, 'user' => $newUser) : array('error' => 'Registration error');
}

// User login
function loginUser($username, $password) {
    global $usersTable;

    $user = $usersTable->GetRecord(array(
        'username' => $username,
        'password' => md5($password),
        'active' => 1
    ));

    if (!$user) {
        return array('error' => 'Invalid credentials');
    }

    // Update last access
    $usersTable->UpdateRecordBypk(
        array('lastlogin' => FN_Now()),
        'id',
        $user['id']
    );

    return array('success' => true, 'user' => $user);
}
```

### Example 2: Blog Management

```php
// Get posts table
$postsTable = FN_XMDBTable("fn_posts");

// Create a new post
function createPost($title, $content, $userId) {
    global $postsTable;

    $postData = array(
        'title' => $title,
        'content' => $content,
        'user_id' => $userId,
        'date_created' => FN_Now(),
        'status' => 'published'
    );

    return $postsTable->InsertRecord($postData);
}

// Get recent posts
function getRecentPosts($limit = 10) {
    global $postsTable;

    return $postsTable->GetRecords(
        array('status' => 'published'),
        0,
        $limit,
        'date_created',
        true
    );
}

// Get posts by category
function getPostsByCategory($categoryId) {
    global $postsTable;

    return $postsTable->GetRecords(array(
        'category_id' => $categoryId,
        'status' => 'published'
    ));
}
```

## Complete API Reference

For a complete list of available functions and methods, see the API documentation in the "XMETATable Class" section of the [FINIS Framework API Documentation](en_api_documentation.md#xmetatable-class).
