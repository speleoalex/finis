# FINIS Framework Installation Manual

## Introduction
FINIS (Flatnux Is Now Infinitely Scalable) is a PHP framework and CMS designed to be flexible and adaptable. This manual will guide you through the installation process and initial system configuration.

## Prerequisites
- Web server (Apache, Nginx, etc.)
- PHP 7.0 or higher
- Support for one of the databases:
  - File system (default)
  - MySQL
  - SQLite
  - SQL Server
  - Other databases supported through specific drivers

## Download
1. Download the latest version from the official repository
2. Extract the archive to a temporary folder

## Installation Methods

### Standard Installation (Complete CMS)
1. Upload all files to your website's root folder
2. Copy the file `src/config.vars.local.php.mysql.example` to `src/config.vars.local.php`
3. Modify the configuration file with your database access credentials
4. Access your site URL (e.g., `http://yoursite.com/`)
5. Follow the guided installation procedure that will appear automatically

### Installation with Separate Sources
FINIS allows separating the framework source files from the application files:

1. Create two folders: `finis_src/` for framework sources and `website/` for the site
2. Copy all framework files to the `finis_src/` folder
3. Create an `index.php` file in the `website/` folder with the following content:
   ```php
   <?php
   require_once "../finis_src/FINIS.php";
   $FINIS = new FINIS(array("src_application"=> "."));
   $FINIS->finis();
   ```
4. Configure the web server to use `website/` as the root folder

## Configuration

### Main Configuration File
The `config.vars.local.php` file contains the main settings:

```php
<?php
global $_FN;
// Error display
$_FN['display_errors'] = "on"; // set to "off" in production

// Authentication method (in include/auth/)
$_FN['default_auth_method'] = "local";

// MySQL configuration:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "database_name";
$_FN['xmetadb_mysqlusername'] = "username";
$_FN['xmetadb_mysqlpassword'] = "password";

// Other general settings
$_FN['sitename'] = "My FINIS Site";
$_FN['site_email_address'] = "admin@mysite.com";
```

### Using File System as Database (default)
If you don't have access to a MySQL database, FINIS can work using PHP/XML files in the `misc/fndatabase/` folder:

```php
<?php
global $_FN;
$_FN['display_errors'] = "on";
$_FN['default_auth_method'] = "local";
$_FN['default_database_driver'] = "xmlphp"; // Use PHP/XML files
```

## First Access
1. After installation, access your site URL
2. Complete the guided configuration by setting:
   - Site name
   - Administrator email
   - Administrator username and password
   - Default language
3. Access the control panel at URL: `http://yoursite.com/?fnapp=controlcenter`

## File and Folder Structure
- `src/`: Contains framework source files
- `sections/`: Contains site pages (one folder per section)
- `themes/`: Contains graphic themes
- `modules/`: Contains the various section types available
- `misc/`: Contains data, including database files
- `languages/`: Contains translations

## Troubleshooting
- **Blank screen**: Verify that the `config.vars.local.php` file is configured correctly
- **Permission errors**: Ensure that the `misc/` and `misc/fndatabase/` folders have write permissions (CHMOD 755 or 777)
- **Database not accessible**: Check the connection parameters in the configuration file

## Additional Resources
- Documentation: See files in `doc/`
- Usage examples: See files in `examples/`

## Development and Customization
After installation, you can:
1. Create new sections in the `sections/` folder
2. Customize the appearance by modifying files in the `themes/` folder
3. Extend functionality with new modules in the `modules/` folder
