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

### Library Layout

| File | Content |
|---|---|
| `src/include/xmetadb/xmetadb_core.php` | helper functions (descriptor parsing, `xmetadb_frm_type()`, `xmetadb_sql_column_type()`, `xmetadb_escape_like()`, `addxmltablefield()`, thumbnails, ...); loads `XMETATable.php` |
| `src/include/xmetadb/XMETATable.php` | class `XMETATable`: descriptor, driver, files of the records |
| `src/include/xmetadb/XMETAField.php` | class `XMETAField`: one field of a descriptor |
| `src/include/xmetadb/XMETADatabase.php` | class `XMETADatabase`: the SQL-like query engine |
| `src/include/xmetadb/XMETATable_<driver>.php` | the drivers, loaded when a table uses them |
| `src/include/xmetadb.php`, `src/include/xmetadb_query.php` | loaders kept for compatibility: they include `xmetadb_core.php` and `XMETADatabase.php` |
| `src/include/xmetadb_frm.php`, `xmetadb_frm_search.php`, `xmetadb_editor.php`, `xmetadbfrm_fields/` | forms, search forms, table editor and form widgets |
| `src/include/functions_xmetadb.inc.php` | the FINIS wrappers `FN_XMDBTable()`, `FN_XMDBForm()`, `FN_XMETADBQuery()`, `FN_setCommonParams()` |

The classes are in the `Xmetadb` namespace (`Xmetadb\XMETATable`, `Xmetadb\XMETAField`,
`Xmetadb\XMETADatabase`, `Xmetadb\XMETATable_mysql`, ...) and each one is aliased with
`class_alias()` to its old global name, so `new XMETATable(...)` and
`XMETATable::createMetadbTable()` keep working. The helper functions of `xmetadb_core.php` are
global; the functions defined in a driver file are in the namespace (`\Xmetadb\xml_to_sql()`).

The library also works outside FINIS: include `src/include/xmetadb.php` (it defines
`FN_GetParam()`, `FN_SaveFile()` and `FN_Copy()` when they are missing).

### Available Drivers

FINIS supports the following database drivers:

- `xmlphp`: XML/PHP file storage (default option)
- `mysql`: MySQL/MariaDB
- `sqlite3`: SQLite version 3 (PHP `SQLite3` extension)
- `sqlserver`: Microsoft SQL Server (PHP `sqlsrv` extension)
- `csv`: CSV files
- `serialize`: Serialized PHP files
- `sqlite`: legacy, it needs the `SQLiteDatabase` class of the old PHP 5 SQLite extension and
  does not work on current PHP versions: use `sqlite3`

## Basic Usage

### Getting an XMETATable Instance

To get a table instance, use the `FN_XMDBTable()` function:

```php
// Get a reference to the table app_users of the site database
$usersTable = FN_XMDBTable("app_users");
```

`FN_XMDBTable($tablename, $params = array())` opens the table in `$_FN['database']` (folder
`$_FN['datadir']`) with the common parameters (`FN_setCommonParams()`: every string `$_FN` key
containing `xmetadb`, the site url, the charset and the default driver) and returns a shared
instance: the same table with the same parameters is built once per request
(`XMETATable::xmetadbTable($databasename, $tablename, $path, $params)`).

The site users (`fn_users`, primary key `username`, bcrypt hash in `passwd`) are not written
directly: use `FN_AddUser()`, `FN_UpdateUser()` and `FN_GetUser()`.

### Table Definition

Tables are defined through XML files with `.php` extension, in `{datadir}/{database}/{table}.php`
(`misc/fndatabase/app_users.php`); the `xmlphp` driver keeps the records in the folder
`misc/fndatabase/app_users/`. Example descriptor file:

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
    <type>varchar</type>
    <size>50</size>
  </field>
  <field>
    <name>email</name>
    <type>varchar</type>
    <size>100</size>
  </field>
  <field>
    <name>password</name>
    <type>varchar</type>
    <size>255</size>
  </field>
  <field>
    <name>regdate</name>
    <type>datetime</type>
  </field>
  <driver>xmlphp</driver>
</tables>
```

A descriptor can also be created from code. `createMetadbTable()` returns false when it creates
the table and never overwrites an existing descriptor (it returns `"xml table exists"` and only
recreates the data folder if it is missing); `addxmltablefield()` adds a field or updates the
properties of an existing one. The values are XML-escaped.

```php
$fields = array(
    array('name' => 'id', 'type' => 'int', 'primarykey' => '1', 'extra' => 'autoincrement'),
    array('name' => 'title', 'type' => 'varchar', 'size' => '100'),
);
XMETATable::createMetadbTable($_FN['database'], 'app_notes', $fields, $_FN['datadir']);
addxmltablefield($_FN['database'], 'app_notes', array('name' => 'body', 'type' => 'text'), $_FN['datadir']);
```

### Basic CRUD Operations

#### Record Insertion

```php
// Create a new user
$userData = array(
    'username' => 'newuser',
    'email' => 'user@example.com',
    'password' => password_hash('password123', PASSWORD_DEFAULT),
    'regdate' => FN_Now()
);

// Insert record and get complete record with generated ID
$newUser = $usersTable->InsertRecord($userData);
if (!is_array($newUser)) {
    // false (SQL drivers) or an "error:..." string (xmlphp: duplicate or missing key)
}
```

#### Record Reading

```php
// Get all records
$allUsers = $usersTable->GetRecords();

// Get records with filter
$adminUsers = $usersTable->GetRecords(array('group' => 'admin'));

// Only some columns
$names = $usersTable->GetRecords(false, false, false, false, false, array('id', 'username'));

// Get record by primary key
$user = $usersTable->GetRecordByPrimaryKey(5);

// Get a single record with filter
$user = $usersTable->GetRecord(array('username' => 'admin'));
```

`GetRecord()` returns null when no record matches. `GetRecordByPrimaryKey()` (alias of
`GetRecordByPk()`) returns an empty value when the key does not exist: null with `mysql` and
`sqlite3`, false with `xmlphp` and `sqlserver`. With `mysql`, false means that the query failed.

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

Both return the record after the update, false when there is no record with that key, or an
error string when the update fails: test the result with `is_array()`.

#### Record Deletion

```php
// Delete a record by ID
$usersTable->DelRecord(5);
```

`DelRecord()` also removes the folder with the files of the record.

## Advanced Features

### Pagination and Sorting

```php
// Get records sorted by name (ascending)
$users = $usersTable->GetRecords(false, false, false, 'username');

// Get records sorted by date (descending)
$users = $usersTable->GetRecords(false, false, false, 'regdate', true);

// Multiple sorting, direction per field (SQL drivers only)
$users = $usersTable->GetRecords(false, false, false, 'group,username:DESC');

// 10 records starting from the 11th
$users = $usersTable->GetRecords(false, 10, 10); // mysql, sqlite3, sqlserver
$users = $usersTable->GetRecords(false, 11, 10); // xmlphp, csv, serialize
```

- The SQL drivers read `$min` as the offset of `LIMIT $min,$length` (from 0). The file drivers
  read it as a position counted from 1 and apply the limit only when `$min` and `$length` are
  both set: `GetRecords(false, 0, 10)` returns every record. For code that must work on every
  driver, read the records and use `array_slice()`, or check `$usersTable->driver`.
- The SQL drivers accept a comma separated list of fields, each one with an optional `:ASC` or
  `:DESC`. `xmlphp` sorts only by a single existing field and ignores any other value.

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

A SQL string is passed to the database as it is: never build it with values from the request.
`xmlphp` does not support it (PHP warning, empty array).

### Matching Rules

- **Primary key**: `GetRecordByPrimaryKey()`, `DelRecord()` and `UpdateRecordBypk()` of the
  `mysql`, `sqlite3` and `sqlserver` drivers touch only the record with exactly that key: the
  value is quoted and escaped, and `%` and `_` are escaped (`xmetadb_escape_like()`). Whether
  the case counts depends on the database: tables created by FINIS on MySQL (`utf8_bin`) are
  case sensitive, the default SQL Server collations are not, SQLite ignores the case of ASCII
  letters in `GetRecordByPrimaryKey()` and `DelRecord()` but not in `UpdateRecordBypk()`.
- **Filters**: on the SQL drivers each value of the array passed to `GetRecords()` or
  `GetNumRecords()` is a `LIKE` pattern (`%` and `_` are wildcards). `xmlphp` compares the value
  exactly (case sensitive); a leading and/or trailing `%` makes it an "ends with", "starts with"
  or "contains" match that ignores the case.
- **`int` fields**: `mysql` and `sqlserver` write a value without quotes only when it is an
  integer (`-?[0-9]+`), any other value is quoted and left to the database; `sqlite3` applies
  `intval()`. The forms do not check the type: cast request values with `intval()`.

When a value from the request must find exactly one record, compare the field again:

```php
$user = $usersTable->GetRecord(array('username' => $username));
if (!$user || $user['username'] !== $username) {
    $user = null; // on SQL drivers "a_b" also matches "axb"
}
```

### Record Counting

```php
// Count all records
$totalUsers = $usersTable->GetNumRecords();

// Count with filter
$totalActiveUsers = $usersTable->GetNumRecords(array('active' => 1));
```

### SQL-like Queries

`FN_XMETADBQuery($query, $params = array())` runs a query on the tables of the site with the
`XMETADatabase` engine. The engine reads the records through XMETATable and filters and sorts
them in PHP: the same query works on every driver, but it always reads the whole table.
To use the class directly, build it as `FN_XMETADBQuery()` does, otherwise the MySQL tables do
not get the connection settings:

```php
$DB = new XMETADatabase($_FN['database'], $_FN['datadir'], FN_setCommonParams());
$rows = $DB->Query("SELECT name, city FROM app_people WHERE age > 17 ORDER BY name");
```

| Statement | Notes |
|---|---|
| `SELECT fields FROM table` | `*`, a list of fields, `field AS alias`, `DISTINCT`; `COUNT(*)` returns one row with the key `COUNT(*)`, `COUNT(*) AS n` with the key `n` |
| `WHERE` | see below |
| `ORDER BY field [DESC]` | one field; with several fields the last one is the main key |
| `LIMIT start,count` | `count` records from position `start`, counted from 1 (`LIMIT 1,10` = the first ten); `LIMIT n` and `LIMIT 0,n` do not limit the result |
| `DESCRIBE table` | one row per field: `Field`, `Type`, `Null`, `Key`, `Extra` |
| `SHOW TABLES` | one row per table, key `Tables_in_<database>` |
| `INSERT INTO t (f1, f2) VALUES ('a', 'b')` | returns the inserted record; quoted values may contain commas, `''` is a quote |
| `UPDATE t SET f1 = 'a', f2 = 'b' WHERE ...` | values without commas or `=`; returns null |
| `DELETE FROM t WHERE ...` | returns `""` |

WHERE conditions:

- `field operator value`, with the operator between spaces: `=`, `<>`, `<`, `<=`, `>`, `>=`,
  `LIKE`; conditions are combined with `AND`, `OR` and parentheses. `!=`, `NOT`, `IN`,
  `IS NULL` and `BETWEEN` are not supported, and `name='x'` (no spaces) is refused.
- `=` and `LIKE` without `%` match the exact value (case sensitive). `LIKE 'abc%'`, `'%abc'` and
  `'%abc%'` match the start, the end or any part of the value ignoring the case;
  `<> '%abc%'` matches the values that do not contain `abc`.
- Integers without quotes are compared as numbers (`age > 9`); a number with a leading zero
  must be quoted.

An unknown table returns a string that starts with `xmetadb:`; an unknown field or a refused
condition gives a PHP warning and false.

**Security**: the WHERE is translated into a PHP expression. The characters `` ` ``, `$`, `{`
and `}` are removed, and the expression is evaluated only when it contains nothing but fields,
comparison and logical operators, numbers and quoted strings; anything else is refused. A quote
in a value still changes the conditions: filter request values with the XMETATable methods.

### File and Image Management

The fields with widget `file` or `image` keep the file name in the record. XMETATable saves the
file in `{datadir}/{database}/{table}/{primary key}/{field}/{file name}` when a record is
inserted or updated with an upload named as the field (`$_FILES['avatar']`) or a file passed
with `SetFile()`. Put the primary key in the values: the `mysql` driver handles the files only
when it is there.

```php
// In an HTML form:
<form method="post" enctype="multipart/form-data">
    <input type="file" name="avatar">
    <!-- other fields -->
    <button type="submit">Save</button>
</form>

// In PHP code to save:
if (!empty($_FILES['avatar']['tmp_name'])) {
    $userData = array('id' => $userId, 'avatar' => $_FILES['avatar']['name']);
    $usersTable->UpdateRecordBypk($userData, 'id', $userId);
    // File upload is automatically handled by XMETATable
}

// A file already on the server
$usersTable->SetFile('avatar', '/tmp/photo.jpg', 'photo.jpg');
$usersTable->UpdateRecordBypk(array('id' => $userId, 'avatar' => 'photo.jpg'), 'id', $userId);

// To retrieve the image path:
$user = $usersTable->GetRecordByPrimaryKey($userId);
$avatarPath = $usersTable->getFilePath($user, 'avatar');

// To retrieve the URL of the file and of its thumbnail:
$fileUrl = $usersTable->getFileUrl($user, 'avatar');
$thumbUrl = $usersTable->get_thumb($user, 'avatar');
```

- `getFilePath($record, $field)`: path of the file in the data folder, false when the field is
  empty. Use it instead of building the path: when a folder holds 10000 records the next ones
  go to `{table}.1`, `{table}.2`, ...
- `getThumbPath($record, $field)`: the thumbnail, or the file itself when there is no thumbnail.
- `getFileUrl($record, $field, $thumb = false)`: web address of the file (`$_FN['siteurl']`
  followed by the path), false when the file does not exist. `get_file()` and `get_thumb()` are
  shortcuts for the file and the thumbnail.
- The thumbnail `thumbs/{file name}.jpg` is created on upload when the field `type` is `image`,
  with the size `thumbsize` (default 22, minimum 16) or `thumbsize_w` / `thumbsize_h`.
- Uploads are refused when the file name contains an executable extension followed by a dot or
  by the end of the name: `.php`, `.php0` ... `.php9`, `.phtml`, `.phar`, `.shtml`, `.pl`,
  `.cgi`, `.sh`, `.py` (so `photo.php.jpg` too). The file is not saved. `/` and `\` are removed
  from the names.
- The record and field names that reach the file system are checked: `DelRecord()` removes a
  folder only when its real path is exactly `{table}/{primary key}`.

#### Thumbnails

`index.php?fnapp=thumb` returns a PNG (or JPEG with `format=jpg`) of exactly `w` x `h` pixels
(default 32 x 32, one value gives a square) with the image resized and centred:

```text
index.php?fnapp=thumb&t=app_users&i=5&c=avatar&w=200&h=200
index.php?fnapp=thumb&f=images/logo.png&w=64
```

- By record: `t` table, `i` primary key, `c` field, `d` database folder (default
  `fndatabase`). The tables of the site database are read with `FN_XMDBTable()`, so with their
  driver; the original file is resized, not the `thumbs/` copy.
- By path: `f` path of an image file.
- A missing file or a file that is not an image gives the generic icon
  `images/mime/image.png` (the copy of the site or the one of FINIS). The EXIF orientation is
  applied to JPEG and TIFF files.

#### Protected Files

An application that must check who reads the files of its records sets two hooks, for example
in a file loaded at every request (`extra/protected_files.inc.php` of the application):

```php
global $_FN;
// who may read a file: checked by fnapp=thumb for the thumbnails asked by record
$_FN['xmetadb_file_access'] = function ($tablename, $id, $field) {
    return MyApp_CanReadRecord($tablename, $id); // true: the thumbnail is served
};
// web address of a file: returned by getFileUrl(), get_file() and get_thumb()
$_FN['xmetadb_file_url'] = function ($table, $record, $field, $thumb) {
    global $_FN;
    return $_FN['siteurl'] . "getfile.php?t=" . rawurlencode($table->tablename)
        . "&id=" . rawurlencode($record[$table->primarykey])
        . "&f=" . rawurlencode($field) . ($thumb ? "&thumb=1" : "");
};
```

- `$_FN['xmetadb_file_access']`: callable `($tablename, $id, $field)` returning true when the
  current visitor may read the file. When it is set, `fnapp=thumb` serves a thumbnail by record
  only when the callback allows it, only from the site database and for table and field names
  made of letters, digits and `_` (403 otherwise), and refuses an `f` path inside the data
  folder. dbview then asks its list thumbnails by record.
- `$_FN['xmetadb_file_url']`: callable `($table, $record, $field, $thumb)` (`$table` is the
  XMETATable object) returning the address to use instead of the path in the data folder,
  e.g. a script of the application that checks the access and sends the file. The data folder
  can then be closed to direct requests.

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
$_FN['xmetadb_timezone'] = '+00:00'; // optional: time_zone of the connection
```

How the `mysql` driver works:

- One connection per request, shared by all the MySQL tables. Once per connection it runs
  `SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'` and, when `$_FN['xmetadb_timezone']` is
  set, `SET time_zone`.
- A missing table is created from the descriptor with `DEFAULT CHARACTER SET utf8 COLLATE
  utf8_bin` (text comparisons are case sensitive), and the records of its `xmlphp` data folder,
  if any, are copied into it. A field added to the descriptor becomes a new column; existing
  columns are never modified. The list of the tables and the `DESCRIBE` of each table are read
  once per request.
- Databases created by older versions may have columns `NOT NULL` without a default: an insert
  that leaves them out writes the value non-strict MySQL used to store (0, the zero date, the
  first value of an ENUM, `''`), and an empty value in such a column is written the same way.
  The schema is not changed.
- A server that refuses the connection because it is busy (errors 1040, 1203, or 1226 for
  `max_user_connections`) is asked again for about 5 seconds. When the connection still fails,
  or the server cannot be reached (1226 for other limits, 2002, 2003, 2006, 2013), a web request
  ends with an HTTP 503 page (`Retry-After: 10`) that reloads itself after 10 seconds for GET
  requests; on the command line the driver returns false. The failure is logged with
  `error_log()` and is not retried in the same request.
- `GetRecordByPk()` returns false when the query fails or there is no connection, null when no
  record has that key.

### SQLite Configuration

To use SQLite as database:

```php
// Configuration for SQLite driver
$_FN['default_database_driver'] = 'sqlite3';
```

The database file is `{datadir}/{database}.sqlite3` (`misc/fndatabase.sqlite3`); a table can use
another file with `<sqlite3filename>` in its descriptor.

### Table-Level Configuration

The descriptor can choose the driver of a single table and the name of its SQL table:

```xml
<tables>
  <!-- field definitions -->
  <driver>mysql</driver>
  <sqltable>table_name</sqltable>
</tables>
```

The `mysql` driver also reads `<host>`, `<user>`, `<password>`, `<database>` and `<port>`, but
in FINIS the `$_FN['xmetadb_mysql*']` settings replace them whenever host, database and username
are set (`config.vars.php` sets them by default), and every MySQL table uses the connection
already opened in the request.

## Database Migration

### From XML/PHP to MySQL

To migrate a table from XML/PHP to MySQL:

```php
require_once $_FN['src_finis'] . "/include/xmetadb/XMETATable_mysql.php";

$connection = array(
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'username',
    'password' => 'password',
    'database' => 'my_database',
    'sqltable' => 'app_users'
);

// xml_to_sql() is in the Xmetadb namespace
\Xmetadb\xml_to_sql($_FN['database'], 'app_users', $_FN['datadir'], $connection);
```

`xml_to_sql()` appends `<driver>mysql</driver>` and the connection data (password included) to
the descriptor and opens the table: the MySQL table is created and the records are copied. With
`$_FN['default_database_driver'] = 'mysql'` the same happens to every table without `<driver>`
the first time it is opened.

## Schemas and Field Definitions

### Field Types: `type` and `frm_type`

- `type` is the **SQL column type**, used when the table is created or a column is added.
- `frm_type` is the **form widget**. When it is missing the widget is taken from `type`.

SQL types (`type`):

| `type` | column | empty value |
|---|---|---|
| `varchar` | `VARCHAR(size)`, default size 255 | `''` (NOT NULL) |
| `char` | `CHAR(size)`, default size 1 | `''` (NOT NULL) |
| `text`, `html`, `mediumtext`, `longtext` | `TEXT`, `TEXT`, `MEDIUMTEXT`, `LONGTEXT` | `NULL` |
| `int`, `number`, `tinyint`, `smallint`, `bigint` | integer types (`number` = `INT`) | `NULL` |
| `float`, `double` | `FLOAT`, `DOUBLE` | `NULL` |
| `decimal` | `DECIMAL(size)`, size `"precision,scale"`, default `10,2` | `NULL` |
| `date`, `datetime`, `time` | `DATE`, `DATETIME`, `TIME` | `NULL` |
| `json` | `JSON` | |
| anything else (`string`, `check`, `image`, ... legacy widget names) | `VARCHAR(255)` | `''` (NOT NULL) |

Numeric and date columns are nullable: an empty form field is stored as `NULL` and read back
as `""`, never as `0`. finis never modifies an existing column: changing `type` in a
descriptor affects new tables and new columns only.

Form widgets (`frm_type`), one class `xmetadbfrm_field_<name>` each in
`src/include/xmetadbfrm_fields/`: `varchar`, `varchar_uppercase`, `varchar_lowercase`, `text`,
`html`, `bbcode`, `check`, `radio`, `select`, `multiselect`, `multicheck`, `stringselect`, `int`,
`number`, `float`, `datetime`, `password`, `cryptpasswd`, `file`, `image`, `base64file`,
`localfile`, `json`, `fn_cron_text`, `separator` (`md5passwd` and `md5password` are legacy).
An unknown name falls back to `varchar`; a field with a `foreignkey` and no widget (or
`varchar`) gets `select`. SQL types without a widget of their own get the closest one
(`mediumtext` → `text`, `double` → `varchar`, `date` → `varchar`, ...).

```xml
<field>
  <name>elevation</name>
  <type>double</type>          <!-- SQL type -->
  <frm_type>varchar</frm_type> <!-- form widget -->
</field>
<field>
  <name>photo</name>
  <type>varchar</type>
  <frm_type>image</frm_type>
</field>
```

### Form Widgets

- `int`: text box that accepts only digits and `-`.
- `number`: `<input type="number">` with the field properties `min` and `max`.
- `float`: `<input type="number">` with `min`, `max` and `step` (default `0.1`); the record view
  shows the value with `decimals` decimals (default 2).
- `json`: text area (`frm_rows`, default 4; `frm_cols`); the record view shows the decoded JSON
  as a nested list, or the raw text when it is not valid JSON.
- `fn_cron_text`: editor of schedules, one per line, `Y-M-D h:m:s weekday` with `*` and `*/n`
  (`*-*-* 13:30:00 5`, weekday 0 = Sunday) and an optional `# comment`; the record view
  describes them in words. Used by the `cron_lines` field of `fn_cron`.
- `datetime`: date picker. `frm_dateformat` (default `y-mm-dd 00:00:00`: `y` year, `mm` month,
  `dd` day, `00:00:00` or `00:00` time) is the format of the form, `view_dateformat` the one of
  the record view; the value is stored as `Y-m-d H:i:s`. When the record is saved, a date that
  does not follow the format or does not exist (`2020-02-31`) is refused with "the date is not
  valid, use the format YYYY-MM-DD hh:mm:ss" (the time may be left out). A value already in the
  storage format (`Y-m-d`, optionally with `H:i` or `H:i:s`, as written by API clients) is also
  accepted, and the zero date is saved as an empty value. A stored value that is not a full date
  (an old `1993`) is shown as it is, so saving the form asks for a valid date.

### Field Properties

```xml
<field>
  <name>field_name</name>         <!-- field name -->
  <type>varchar</type>             <!-- SQL column type -->
  <size>100</size>                 <!-- size (for varchar) -->
  <primarykey>1</primarykey>       <!-- indicates primary key -->
  <extra>autoincrement</extra>     <!-- auto-incrementing field -->
  <thumbsize>100</thumbsize>       <!-- thumbnail size for images -->
  <defaultvalue>0</defaultvalue>   <!-- literal default value (file drivers) -->
  <frm_default>0</frm_default>     <!-- value proposed by the form for a new record -->
  <mysql_default>NULL</mysql_default> <!-- default value (MySQL) -->
  <mysql_on_update>CURRENT_TIMESTAMP</mysql_on_update> <!-- automatic update -->
</field>
```

- `defaultvalue` is a literal: PHP expressions are not evaluated (`date('Y')` is stored as that
  text). The `xmlphp`, `csv` and `serialize` drivers write it when a record is inserted without
  the field and return it for stored records that lack the field. On MySQL the column default
  comes from `mysql_default` (an SQL expression used in `CREATE TABLE` / `ALTER TABLE`).
- `frm_sort`: `numeric` or `abs` makes dbview sort the list by the numeric value (or the
  absolute value) of a text column: empty values last, the comma accepted as decimal separator.
  It applies to tables with an SQL driver.
- `frm_functionview`: name of a PHP function called as `function($values, $fieldname)` in the
  record view; the HTML it returns is shown with the field title, and left out when it is empty
  or the field is hidden.

Foreign key:

```xml
<field>
  <name>category_id</name>
  <foreignkey>app_categories</foreignkey>   <!-- linked table -->
  <fk_link_field>id</fk_link_field>         <!-- value saved in the field -->
  <fk_show_field>code,name</fk_show_field>  <!-- fields shown in the options -->
  <fk_filter_field>active='1'</fk_filter_field>
</field>
```

- `fk_show_field`: comma separated fields joined by a space; `{field}_{lang}` is used when it
  is not empty.
- `fk_filter_field`: comma separated `field='value'` (a constant) or `field=otherfield` (the
  value of another field of the record).
- `frm_show_image`: image field of the linked table shown next to the options.
- `fk_databasename`, `fk_databasepath`: the linked table is in another database folder.

The options read only the columns they are built from (primary key, link field, show fields
with their language variants, image field).

## Performance Optimization

### Using Cache

The `xmlphp`, `csv` and `serialize` drivers can keep the results of `GetRecords()` (with
`xmlphp` also of `GetRecordByPrimaryKey()`) in files (`{datadir}/{database}/cache/`):

```xml
<tables>
  <!-- field definitions -->
  <usecachefile>1</usecachefile>
</tables>
```

### Fast Operations

The `mysql` driver has an insert and an update without file management, reached through the
driver object:

```php
// Fast insert (without file management), returns true or false
$usersTable->driverclass->InsertRecordFast($userData);

// Fast update by the primary key in $userData (without file management)
$usersTable->driverclass->UpdateRecordFast($userData);
```

## System Extension

### Creating a Custom Driver

To create a new driver for XMETATable:

1. Create the class `XMETATable_mydriver` (global name) in `src/include/xmetadb/XMETATable_mydriver.php`, or define it in the application before the table is opened
2. Implement the methods XMETATable delegates to the driver: `GetRecords`, `GetRecord`, `GetRecordByPrimaryKey`, `GetRecordByPk`, `GetNumRecords`, `GetAutoincrement`, `InsertRecord`, `UpdateRecordBypk`, `DelRecord`, `GetFileRecord`, `Truncate`
3. Register the driver in the table's XML descriptor (`<driver>mydriver</driver>`)

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

### Custom Form Widgets

A widget is the class `xmetadbfrm_field_<frm_type>`. The forms use the class when it is already
defined (e.g. in a file `extra/*.inc.php` of the application) or load it from
`src/include/xmetadbfrm_fields/`. The constructor receives `array('fieldvalues' => <field>)`;
the methods receive the field properties with `name` and `value`.

```php
class xmetadbfrm_field_percent
{
    function __construct($field)
    {
    }

    // input of the form
    function show($params)
    {
        return "<input type=\"number\" min=\"0\" max=\"100\" name=\"{$params['name']}\" value=\""
            . htmlspecialchars($params['value']) . "\" /> %";
    }

    // value in the record view
    function view($params)
    {
        return htmlspecialchars($params['value']) . " %";
    }

    // optional: error shown by the form, "" when the value is valid
    function checkformvalue($value, $params)
    {
        return ($value >= 0 && $value <= 100) ? "" : "0 - 100";
    }
}
```

Other optional methods: `formtovalue($value, $params)` (posted value → stored value),
`htmlfilter($params)` (filter input of the table editor), `SortNumeric()` (true, `"numeric"` or
`"abs"`: same as `frm_sort`).

## Best Practices

1. **Primary Keys**: Always define a primary key for each table
2. **Exact Matches**: Filters are `LIKE` patterns on the SQL drivers: compare the field again when a value must find one record
3. **Null Fields**: Handle null values correctly for each driver
4. **Security**: Always use `FN_GetParam()` to get user data before inserting, and `intval()` for integers
5. **Backup**: Create regular data backups, especially when using the xmlphp driver

## Troubleshooting

### Common Problems

- **"Table not exists" error**: Verify the table descriptor file exists in the correct path.
- **"File not writable" error**: Check directory write permissions.
- **"Database not writable" error**: Verify the database user has correct permissions.
- **MySQL connection error**: Check credentials and that the server is running.
- **HTTP 503 "Service temporarily unavailable"**: the MySQL server was busy or unreachable for
  about 5 seconds (see MySQL Configuration).
- **Uploaded file missing**: the file name contains an executable extension.

### Debug

To log the write operations on the tables:

```php
define("XMETADB_DEBUG_FILE_LOG", "/path/to/logfile.log");
```

Every `InsertRecord()`, `UpdateRecordBypk()` and `DelRecord()` writes the time and the table
name (and the values for `fn_settings`). MySQL errors are reported as PHP warnings
(`MySQL INSERT failed: ...`), connection failures with `error_log()`.

## Practical Examples

### Example 1: User Management System

```php
// Get users table instance
$usersTable = FN_XMDBTable("app_users");

// User registration
function registerUser($username, $email, $password) {
    global $usersTable;

    // Check if user already exists
    $existingUser = $usersTable->GetRecord(array('username' => $username));
    if ($existingUser && $existingUser['username'] === $username) {
        return array('error' => 'Username already in use');
    }

    // Register new user
    $userData = array(
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'regdate' => FN_Now(),
        'active' => 1,
        'group' => 'users'
    );

    $newUser = $usersTable->InsertRecord($userData);
    return is_array($newUser) ? array('success' => true, 'user' => $newUser) : array('error' => 'Registration error');
}

// User login
function loginUser($username, $password) {
    global $usersTable;

    $user = $usersTable->GetRecord(array(
        'username' => $username,
        'active' => 1
    ));

    if (!$user || $user['username'] !== $username || !password_verify($password, $user['password'])) {
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
$postsTable = FN_XMDBTable("app_posts");

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

    $posts = $postsTable->GetRecords(
        array('status' => 'published'),
        false,
        false,
        'date_created',
        true
    );
    // the same on every driver (the meaning of $min differs, see Pagination)
    return is_array($posts) ? array_slice($posts, 0, $limit) : array();
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
