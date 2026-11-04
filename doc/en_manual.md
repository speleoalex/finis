# Finis CMS

## Installation

1. Download the source code and upload the content of `src/` to the provider.
2. Rename `index.php.example` to `index.php`.
3. Create the `config.vars.local.php` file if necessary (e.g. for MySQL).
4. Access the installation URL, for example, `http://[your-site]/`.
5. Follow the installation wizard.

Requirements, PHP extensions, web server and data folder are described in the [Installation Manual](en_installation_manual.md).

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
- `sections/` (default sections, copied into the site by the installer)

### Website Files

- `sections/`
- `themes/`
- `modules/` (section types of the application)
- `controlcenter/sections/` (control center pages of the application)
- `include/` (`autoexec.d/`, `methods/`, `auth/`, `mcp_tools.php`)
- `config.vars.local.php`
- `index.php`

### Extension Files

An extension (for example `fncommerce/`) is a separate folder with `modules/`, `sections/`, `controlcenter/sections/` and `misc/fndatabase/`, loaded with `$FINIS->addExtension()`.

### Data Files

- `misc/`

Besides the database, the data folder holds the site secret `misc/secret.key.php` (created automatically: back it up with the data, never commit it), the server sessions (`_sessions/`), the login attempt counters (`_login_attempts/`) and the cache (`_cache/`).

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
//display error ("on" only while developing), http://php.net/manual/it/errorfunc.configuration.php#ini.display-errors
$_FN['display_errors'] = "off";
//authentication method (include/auth/)
$_FN['default_auth_method'] = "local";
//specific options for the mysql driver:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "finis";
$_FN['xmetadb_mysqlusername'] = "root";
$_FN['xmetadb_mysqlpassword'] = "";
```

The defaults of these keys are in `src/include/config.vars.php`. Other keys of this file: `trusted_proxies` (reverse proxies), `home_section_force` (home section that wins over the control center setting), the login security keys and `apikey_groups` / `mcp_write_group` (see the [Installation Manual](en_installation_manual.md)). The other settings of the site are edited in the control center (`index.php?fnapp=controlcenter`).

## Application and Sources

The files can coexist in the same folder, but it is possible to separate the framework sources from the website sources, for example in `finis_src/` and `website/`.

The `index.php` in the website folder includes `../finis_src/FINIS.php`:

```php
<?php
require_once "../finis_src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("../fncommerce/"); // optional, one call per extension
$FINIS->finis();
```

Modules (section types) and control center sections are looked up in the application folder, then in the FINIS sources, then in the extensions. `$FINIS->finis()` serves the site and the entry points chosen with `?fnapp=`: `controlcenter`, `api`, `mcp`, `oauthserver`, `thumb`, ... An application adds its own in `include/methods/<name>.php` (names made of letters, digits and `_`).

The MCP server `index.php?fnapp=mcp` lets AI clients work on the site as a FINIS user, with a personal API key or an OAuth login: see the [MCP guide](en_mcp_guide.md).
