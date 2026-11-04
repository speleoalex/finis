# FINIS Framework Installation Manual

## Introduction
FINIS (Flatnux Is Now Infinitely Scalable) is a PHP framework and CMS designed to be flexible and adaptable. This manual will guide you through the installation process and initial system configuration.

## Prerequisites
- Web server (Apache with mod_rewrite for friendly URLs and for the OAuth login of the MCP clients; any web server for `index.php?...` addresses)
- PHP 7.1 or higher (PHP 8.x is supported)
- Support for one of the databases:
  - File system (default, no database server needed)
  - MySQL / MariaDB
  - SQLite 3
  - SQL Server
  - Other databases supported through specific drivers (`csv`, `serialize`)
- Write permissions for the web server on the data folder (`misc/`) and on the `sections/` folder of the site

### PHP Extensions
FINIS runs with the extensions of a standard PHP installation. The following ones are needed only by some features:

| Extension | Used for |
|---|---|
| `mysqli` | `mysql` database driver |
| `sqlite3` | `sqlite3` database driver |
| `sqlsrv` | `sqlserver` database driver |
| `gd` (with FreeType) | thumbnails, image fields, file manager previews, captcha image |
| `mbstring` | search results of the database views, client registration of the OAuth server |
| `curl` | login with external OAuth providers |
| `openssl` (AES-256-GCM) | encrypted copy of the API keys, so that the administrators can show them; without it a key is stored only as a hash and is shown only once |
| `exif` | orientation of JPEG photos in the thumbnails |
| `zlib` | gzip compression of the pages (setting `enable_compress_gzip`) |
| `apcu` | faster cache; without it the cache is kept in files in `misc/_cache/` |

## Download
1. Download the latest version from the official repository
2. Extract the archive to a temporary folder

## Installation Methods

### Standard Installation (Complete CMS)
1. Upload the content of the `src/` folder to your website's root folder
2. Rename `index.php.example` to `index.php`
3. To use MySQL instead of files, copy `config.vars.local.php.mysql.example` to `config.vars.local.php` and set your database access credentials
4. Make the site folder writable by the web server (FINIS creates the data folder `misc/` in it), together with `sections/`
5. Access your site URL (e.g., `http://yoursite.com/`)
6. Follow the guided installation procedure (`index.php?fnapp=install`) that will appear automatically: it starts when the data folder does not exist yet (if you create `misc/` yourself, also create an empty file `misc/firstinstall`)

### Installation with Separate Sources
FINIS allows separating the framework source files from the application files:

1. Create two folders: `finis_src/` for framework sources and `website/` for the site
2. Copy the content of `src/` to the `finis_src/` folder
3. Create an `index.php` file in the `website/` folder with the following content:
   ```php
   <?php
   require_once "../finis_src/FINIS.php";
   $FINIS = new FINIS(array("src_application" => "."));
   $FINIS->finis();
   ```
4. Put `config.vars.local.php` (when needed) in the `website/` folder
5. Configure the web server to use `website/` as the root folder

The application folder holds its own `sections/`, `themes/`, `modules/`, `misc/` (data) and `config.vars.local.php`; whatever it does not have is taken from the framework sources. At the first visit the installer copies the default sections of FINIS into `website/sections/`. `$FINIS->finis()` serves the site and the other entry points chosen with `?fnapp=` (`controlcenter`, `api`, `mcp`, `oauthserver`, `thumb`, ...). Several sites can share one copy of the framework sources.

### Extensions
An extension is a folder with additional modules, sections, control center sections and tables, loaded without copying it into the site:

```php
<?php
require_once "../finis_src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("../fncommerce/");
$FINIS->finis();
```

An extension folder can contain `modules/`, `sections/`, `controlcenter/sections/` and `misc/fndatabase/`. Modules (section types) and control center sections are looked up first in the application, then in the FINIS sources, then in the extensions (in the order of the `addExtension()` calls). The modules and the control center sections stay in the extension folder; the installer copies the `sections/` and the table descriptors of `misc/fndatabase/` of the extensions into the site, only those that the site does not have yet. When an extension is added to a site that is already installed, copy them by hand. A path that does not exist stops the request with the message `<path> not exists`.

### Examples
The `examples/` folder holds ready-made sites that use the sources in `../../src/` and run from inside the repository:

- `examples/website`: a site with the default sections; the installer runs at the first visit
- `examples/fullcms`: the complete CMS with the control center
- `examples/ecommerce`: a shop (cart, categories, products) built on the `fncommerce` extension, loaded with `$FINIS->addExtension("../../fncommerce/")`; the catalogue data is already in its `misc/` folder

## Configuration

### Main Configuration File
The `config.vars.local.php` file of the site contains the settings of the installation. The defaults are in `src/include/config.vars.php`: a key set there (such as `display_errors`) must be changed in `config.vars.local.php`, a value passed to `new FINIS(array(...))` would be overwritten.

```php
<?php
global $_FN;
// Error display
$_FN['display_errors'] = "off"; // "on" only while developing

// Authentication method (in include/auth/)
$_FN['default_auth_method'] = "local";

// Behind a reverse proxy: its IP addresses, comma separated, so that the login
// limiter reads the client address from X-Forwarded-For ("" = no proxy)
$_FN['trusted_proxies'] = "";

// Home section that wins over the one chosen in the control center ("" = use the setting)
$_FN['home_section_force'] = "";

// MySQL configuration:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "database_name";
$_FN['xmetadb_mysqlusername'] = "username";
$_FN['xmetadb_mysqlpassword'] = "password";
```

The settings of the site (site name, email address, languages, theme, home section, mod_rewrite, registration, cache, ...) are not set in this file: they are chosen in the installation wizard and in the control center, and stored in the `fn_settings` table (`src/config.php` holds only their defaults).

### Using File System as Database (default)
If you don't have access to a MySQL database, FINIS can work using PHP/XML files in the `misc/fndatabase/` folder:

```php
<?php
global $_FN;
$_FN['display_errors'] = "off";
$_FN['default_auth_method'] = "local";
$_FN['default_database_driver'] = "xmlphp"; // Use PHP/XML files
```

### Login Security
These keys can be set in `config.vars.local.php`; without them the defaults below apply:

| Key | Default | Meaning |
|---|---|---|
| `remember_login_days` | 30 | lifetime of the login cookies when the user ticks "remember me" (setting `remember_login` enabled) |
| `recovery_link_minutes` | 60 | validity of a password recovery link |
| `login_max_attempts` | 5 | failed login attempts allowed per username and IP address |
| `login_lockout_minutes` | 15 | time window of the attempts, and longest lockout |
| `trusted_proxies` | "" | reverse proxies whose `X-Forwarded-For` header is trusted: IP addresses, comma separated (no ranges) |
| `username_case_sensitive` | not set | when set, usernames are saved and looked up as typed; otherwise they are lowercase |

The login cookie is signed with the site secret `misc/secret.key.php` (see below) and is sent as HttpOnly, SameSite=Lax, and Secure on HTTPS. Passwords are stored as bcrypt hashes.

### MCP Server and API Keys
The site answers AI clients (Claude Desktop, claude.ai, Claude Code, ...) at `index.php?fnapp=mcp`, authenticated with a personal API key or an OAuth login (`index.php?fnapp=oauthserver`). `$_FN['apikey_groups']` limits the users allowed to use an API key ("" = every user) and `$_FN['mcp_write_group']` (default `mcp_write`) names the group allowed to use the write tools besides the administrators. See the [MCP guide](en_mcp_guide.md).

## First Access
1. After installation, access your site URL
2. Complete the guided configuration:
   - Language of the installer
   - Check of the data folder permissions
   - Settings: languages, theme, site name, title and subtitle, email address, home section
   - Administrator username, email and password
3. Access the control panel at URL: `http://yoursite.com/?fnapp=controlcenter`

## File and Folder Structure
- `src/`: Contains framework source files
- `sections/`: Contains site pages (one folder per section)
- `themes/`: Contains graphic themes
- `modules/`: Contains the various section types available
- `controlcenter/`: Contains the control center sections and themes
- `misc/`: Contains data, including database files
- `languages/`: Contains translations

### The Data Folder
`misc/` (`$_FN['datadir']`) holds all the data of the site:

| Path | Content |
|---|---|
| `misc/fndatabase/` | table descriptors and, with the file drivers, the records and their uploaded files |
| `misc/secret.key.php` | site secret, created automatically (mode 0600): it signs the login cookies and the OAuth tokens and encrypts the copies of the API keys |
| `misc/_sessions/` | server sessions, one file per browser, removed one hour after the last write |
| `misc/_login_attempts/` | counters of the failed login attempts |
| `misc/_cache/` | cache; it can be emptied at any time |
| `misc/_THUMBS/` | image thumbnails, sent to the browsers |
| `misc/_oauth/` | markers of the OAuth codes and refresh tokens already used |
| `misc/log/` | `cron.log` and the monthly log files of the older versions, shown as an archive by the control center (the event log is now the `fn_log` table) |
| `misc/firstinstall` | present until the installation wizard is completed |

Back up the whole data folder, `secret.key.php` included, and never commit `secret.key.php` to a repository. Without it every user is logged out, the OAuth clients must log in again and the administrators can no longer show the existing API keys (the keys keep working).

## Web Server

### Apache and .htaccess
When mod_rewrite is enabled (setting `enable_mod_rewrite`, enabled by default) and FINIS detects it, `index.php` writes the block between `# BEGIN Finis` and `# END Finis` in the `.htaccess` of the site folder, and rewrites it when it changes; the rest of the file is kept. The block:

- passes the `Authorization` header to PHP (API keys and OAuth tokens of the MCP clients):
  `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`
- answers `/.well-known/oauth-authorization-server` and `/.well-known/oauth-protected-resource/...` with the OAuth server (login of Claude Desktop and claude.ai)
- rewrites the friendly URLs (`<section>.html` with the links mode `html`, `<lang>/<section>/` with `folders`)

FINIS detects mod_rewrite with `apache_get_modules()` (PHP as an Apache module) or the environment variable `HTTP_MOD_REWRITE=On`. With PHP-FPM or CGI set `enable_mod_rewrite` to 2 (force) or write the rules by hand. If `.htaccess` cannot be written, the friendly URLs are disabled. For a site in a subfolder, the `/.well-known` rules must also be in the `.htaccess` of the domain root: see the [MCP guide](en_mcp_guide.md).

### Other Web Servers
The `index.php?...` addresses work without rewriting. To use friendly URLs or the OAuth login of the MCP clients, translate the rules of the `# BEGIN Finis` block and make sure that the `Authorization` header reaches PHP.

### Protecting the Data Folder
The data folder is inside the site folder: its `.php` files start with `<?php exit(0); ?>` and must be run by PHP, never sent as text. `misc/_sessions/`, `misc/_login_attempts/`, `misc/_cache/` and `misc/_oauth/` are never used through a URL: disable the directory listing and deny them, for example with Apache, outside the Finis block of `.htaccess`:

```apache
Options -Indexes
RedirectMatch 403 /misc/(_sessions|_login_attempts|_cache|_oauth)/
```

The uploaded files in `misc/fndatabase/` and the thumbnails in `misc/_THUMBS/` must stay reachable.

## Troubleshooting
- **Blank screen or error 500**: `display_errors` is "off" by default; look at the PHP error log, or set `$_FN['display_errors'] = "on";` in `config.vars.local.php` while you look for the problem
- **Permission errors** (`permissions error: misc`, `permissions error: ./sections/`): make the data folder `misc/` (with its subfolders) and `sections/` writable by the web server; the installer shows the command to run
- **Database not accessible**: Check the connection parameters in the configuration file
- **"Service temporarily unavailable" (HTTP 503) with MySQL**: the database server refused the connection because it is busy (too many connections, `max_user_connections`) or not reachable. FINIS retries for about 5 seconds, then answers 503 with a page that reloads itself after 10 seconds; the users stay logged in. The PHP error log has a line `xmetadb: MySQL connection failed`. Check the connection limits of the MySQL account and of the server
- **A user cannot log in with the right password**: after `login_max_attempts` failed attempts from the same IP address the username is locked for up to `login_lockout_minutes`. Wait, or delete the folder `misc/_login_attempts/` (clearing the cache does not unlock it). Behind a reverse proxy set `trusted_proxies`: otherwise all the visitors share the address of the proxy and anyone can lock out a username for everybody
- **Everybody has been logged out**: `misc/secret.key.php` was deleted or replaced (for example by restoring the data without it): a new secret is created and everybody logs in again
- **Users are logged out at every page**: the site secret cannot be saved; make the data folder writable
- **Friendly URLs or the OAuth login of the MCP clients do not work**: the `.htaccess` was not written; check mod_rewrite, the `enable_mod_rewrite` setting and the permissions of `.htaccess` (see Web Server)

## Additional Resources
- Documentation: See files in `doc/`
- Usage examples: See files in `examples/`
- MCP server and API keys: [MCP guide](en_mcp_guide.md)

## Development and Customization
After installation, you can:
1. Create new sections in the `sections/` folder
2. Customize the appearance by modifying files in the `themes/` folder
3. Extend functionality with new modules in the `modules/` folder
