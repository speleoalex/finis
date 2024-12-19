# Finis CMS

## Installation

1. Download the source code and upload it to the provider.
2. Create the `config.vars.local.php` file if necessary.
3. Access the installation URL, for example, `http://[your-site]/`.
4. Follow the installation wizard.

## Folder Structure

### Framework Files

- `config.php`
- `FINIS.php`
- `modules/`
- `themes/`
- `controlcenter/`
- `extra/`
- `images/`
- `languages/`
- `plugins/`
- `include/`

### Website Files

- `sections/`
- `themes/`
- `config.vars.local.php`
- `index.php`

### Data Files

- `misc/`

The database is described within XML files in the `misc/fndatabase` folder. Each file describes a table, and each table can have its own driver to support different data storage systems such as MySQL, CSV files, MSSQL, XMLPHP, etc.

### Example of Table Descriptor `fn_sections`

The XML file describing a table includes fields such as `id`, `type`, `parent`, `position`, `title`, `description`, `startdate`, `enddate`, `status`, `hidden`, `accesskey`, `keywords`, `sectionpath`, `level`, `group_view`, `group_edit`, `blocksmode`, and `blocks`.

## Description of Folders

### `sections/`

Contains the list of sections, with a folder for each section. For example, `sections/home` will be the homepage. In website mode, each section corresponds to a web page and can be of a different type, defined in the `modules/` folder.

### `modules/`

Contains the types of sections, one for each subfolder. For example, `/modules/login/section.php` defines the sources for login-type pages. If the section type is not specified, it will be of type Finis.

### `themes/`

Example theme for Finis:

`themes/mytheme/template.tp.html` includes a basic HTML layout with headers, menus, main content, and footer.

## Configuration

Example of `config.vars.local.php` file:

```php
<?php
global $_FN;
//display error, http://php.net/manual/it/errorfunc.configuration.php#ini.display-errors
$_FN['display_errors'] = "on";
//authentication method (include/auth/)
$_FN['default_auth_method'] = "local";
//specific options for the mysql driver:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "finis";
$_FN['xmetadb_mysqlusername'] = "root";
$_FN['xmetadb_mysqlpassword'] = "";
```

## Application and Sources

The files can coexist in the same folder, but it is possible to separate the framework sources from the website sources, for example in `finis_src/` and `website/`.

The `index.php` in the website folder includes `../finis_src/FINIS.php`.
