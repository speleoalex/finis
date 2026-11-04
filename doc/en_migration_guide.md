# FINIS Framework Migration Guide

## Introduction
This guide provides detailed instructions for migrating to FINIS from existing systems or upgrading between different FINIS versions. Migration and upgrading require careful planning to ensure service continuity and preserve all important data.

## Migration Planning

### Preliminary Assessment
Before starting migration, it's essential to:

1. **Analyze the current system**:
   - Inventory of existing content
   - Database structure
   - Custom functionality
   - Themes and layouts
   - Plugins and modules
   - Users and permissions

2. **Assess compatibility**:
   - Verify FINIS technical requirements
   - Identify potential incompatibilities
   - Examine unsupported data formats

3. **Define objectives**:
   - Features to preserve
   - New features to implement
   - Migration timeline
   - Testing and rollback strategy

### Creating a Test Environment
It's always advisable to perform migration in a test environment:

1. Set up a development server
2. Install FINIS in the test environment
3. Create a copy of the existing database
4. Copy necessary files to the new environment

## Migration from Other CMS to FINIS

### From WordPress to FINIS

#### Database Migration
1. **Export content from WordPress**:
   ```sql
   SELECT ID, post_title, post_content, post_date, post_name, post_type
   FROM wp_posts
   WHERE post_status = 'publish'
   AND post_type IN ('post', 'page')
   ```

2. **Prepare import file**:
   Convert data to a compatible format (CSV or XML)

3. **Import script**:
   ```php
   <?php
   require_once "path/to/FINIS.php";
   $FINIS = new FINIS();

   // Load CSV/XML file
   $data = load_data_from_file("exported_data.csv");

   foreach ($data as $item) {
       $sectionId = sanitize_section_id($item['post_name']);

       // Create a new section for each page
       if ($item['post_type'] == 'page') {
           create_finis_section($sectionId, $item['post_title'], $item['post_content']);
       }

       // Create a news article for each post
       if ($item['post_type'] == 'post') {
           create_finis_news($item['post_title'], $item['post_content'], $item['post_date']);
       }
   }
   ```

#### User Migration
1. **Export users from WordPress**:
   ```sql
   SELECT ID, user_login, user_email, user_registered,
   display_name, user_nicename
   FROM wp_users
   ```

2. **Import to FINIS**:
   ```php
   <?php
   // Script to import users
   $users = load_users_from_file("exported_users.csv");

   foreach ($users as $user) {
       $newUser = array(
           'username' => $user['user_login'],
           'email' => $user['user_email'],
           'name' => $user['display_name'],
           'group' => 'users',  // default group
           'active' => 1
       );

       // Random temporary password: FN_AddUser() stores its bcrypt hash
       $password = bin2hex(random_bytes(8));

       // Insert user (username in lowercase, registrationdate set to now)
       if (!FN_AddUser($newUser, $password)) {
           echo "User not imported: {$user['user_login']}<br>";
       }
   }
   ```

   The WordPress password hashes cannot be reused: the users choose a new password with the password recovery of the login page.

#### Media Migration
1. **Copy media files**:
   ```bash
   # Example bash script to copy images
   mkdir -p /path/to/finis/misc/uploads/images
   cp -R /path/to/wordpress/wp-content/uploads/* /path/to/finis/misc/uploads/
   ```

2. **Update references**:
   ```php
   <?php
   // Script to update image references in sections:
   // the content of a page is in sections/<id>/section.<lang>.html
   $files = glob("sections/*/section.*.html");

   foreach ($files as $file) {
       // Replace image paths
       $content = file_get_contents($file);
       $content = str_replace(
           'wp-content/uploads/',
           'misc/uploads/',
           $content
       );

       // Save the page
       FN_Write($content, $file);
   }
   ```

### From Static HTML to FINIS

#### Content Migration
1. **Analyze structure**:
   - Identify main pages
   - Map navigation
   - Catalog assets (images, CSS, JS)

2. **Convert to FINIS Sections**:
   ```php
   <?php
   // Script to import static HTML pages
   $directory = "path/to/html_site/";
   $files = glob($directory . "*.html");

   foreach ($files as $file) {
       $content = file_get_contents($file);
       $filename = basename($file, ".html");

       // Extract title from HTML page
       preg_match("/<title>(.*?)<\/title>/i", $content, $matches);
       $title = isset($matches[1]) ? $matches[1] : $filename;

       // Extract content from body
       preg_match("/<body>(.*?)<\/body>/is", $content, $matches);
       $bodyContent = isset($matches[1]) ? $matches[1] : $content;

       // Clean content
       $cleanContent = clean_html_content($bodyContent);

       // Create a section for each HTML page
       create_finis_section($filename, $title, $cleanContent);
   }
   ```

3. **Asset Migration**:
   ```bash
   # Copy images and other assets
   mkdir -p /path/to/finis/misc/uploads/images
   cp -R /path/to/html_site/images/* /path/to/finis/misc/uploads/images/
   ```

## FINIS Updates

### Updating from a Previous Version

#### Preparation
1. **Complete backup**:
   ```bash
   # File backup
   tar -czf finis_backup_files.tar.gz /path/to/finis

   # Database backup (MySQL example)
   mysqldump -u username -p dbname > finis_backup_db.sql
   ```

2. **Verify requirements**:
   - Check server meets new version requirements (PHP 7.1 or higher)
   - Verify custom module compatibility
   - Read release notes for significant changes

#### Update Procedure
1. **Download new files**:
   - Download the latest version of FINIS
   - Extract to a temporary folder

2. **Replace files**:
   - Installation with separate sources (`src_application`): replace the framework folder as a whole; the application folder (sections, themes, data, configuration) is not touched:
     ```bash
     mv /path/to/finis_src /path/to/finis_src.old
     cp -R /path/to/new_version/src /path/to/finis_src
     ```
   - Standard installation (the site folder holds the content of `src/`): the package has no `config.vars.local.php`, `index.php`, `.htaccess` or `misc/`, so they are kept; `sections/` holds the pages of the site and is left out:
     ```bash
     # Core folders replaced as a whole: files removed from FINIS must not stay behind
     # (copy back afterwards your own modules, control center sections and files in include/)
     rm -rf /path/to/finis/include
     rm -rf /path/to/finis/modules
     rm -rf /path/to/finis/controlcenter

     # Copy new files
     rsync -a --exclude='sections/' /path/to/new_version/src/ /path/to/finis/
     ```

3. **Update the database**:
   - There is no update script: open the site. At each request FINIS creates the descriptors of the system tables that are missing in `misc/fndatabase/`; with MySQL the columns of the fields added to a descriptor are created automatically
   - The existing descriptors of the system tables are never replaced: read [Upgrading to the Current Version](#upgrading-to-the-current-version) for the changes to make by hand

4. **Post-update verification**:
   - Check all pages work correctly
   - Verify content correctness
   - Check error log

### Upgrading to the Current Version

Notes for a site installed with an earlier version of FINIS. Read them before replacing the files.

#### Site Secret
At the first request FINIS creates the site secret `misc/secret.key.php` (mode 0600). It signs the login cookies and the OAuth tokens of the MCP clients, and encrypts the copies of the API keys. From now on it is part of the data:

- back it up with `misc/` and keep it when the site is moved to another server
- never commit it: when the site is in a repository, add `misc/secret.key.php` to `.gitignore`
- if it is lost, a new one is created: every user is logged out, the OAuth clients must log in again and the administrators can no longer show the existing API keys (the keys keep working)

#### Login and Passwords
- The `secid` login cookie is now an HMAC-SHA256 signed with the site secret: the cookies of the previous version are refused, so every user logs in once again after the upgrade. "Remember me" lasts `remember_login_days` days (default 30).
- Passwords stored as md5, `crypt()` hashes or plain text are still accepted and are replaced by a bcrypt hash at the first successful login of each user: nothing to do.
- Registration codes are now 32 hexadecimal characters: the 5 character codes sent by the previous version are refused and not resent. Activate the users still waiting for the confirmation in the control center (Users and groups > Users).
- Password recovery links sent before the upgrade are no longer valid: the users ask for a new one.
- Behind a reverse proxy, set `$_FN['trusted_proxies']` in `config.vars.local.php`, so that the login attempt limiter sees the real client address (see the [Installation Manual](en_installation_manual.md)).

#### API Keys
API keys stored in clear by older versions keep working and are replaced by their hash at the first use. The fields `apikey`, `apikey_date` and `apikey_enc` are added to `misc/fndatabase/fn_users.php` the first time a key is used or generated (the file must be writable). A key created when only its hash was stored cannot be shown again: generate a new one. With the `openssl` extension the administrators can show the keys created from now on. See the [MCP guide](en_mcp_guide.md).

#### Configuration
- `display_errors` is "off" by default: set `$_FN['display_errors'] = "on";` in `config.vars.local.php` on development copies. With "off", what a script prints outside the theme template (e.g. an `echo` in an autoexec script) is discarded.
- An entry point `index.php?fnapp=<name>` must have a name made of letters, digits and `_`.

#### Table Descriptors
- `defaultvalue` in a descriptor is now saved as written: the `xmlphp` driver no longer expands the PHP variables written in it and the `serialize` driver no longer evaluates it as a PHP expression. Replace such values with plain values and set the dynamic ones in the code before saving the record.
- `misc/fndatabase/fn_oauth_providers.php` of the sites installed with the fields `name`, `client_id`, `client_secret`, `redirect_uri`, `enabled` lacks the fields used by the login with external providers (`avatar`, `auth_url`, `token_url`, `userinfo_url`, `scope`) and is not replaced. Copy `src/include/install/misc/fndatabase/fn_oauth_providers.php` over it (or add the fields), then fill in the addresses of each provider in the control center (Users and groups > OAuth providers).
- The news module works with the primary key `id` of its news table. A news table created by an old version with the primary key `unirecid` must be converted: rename the field in the descriptor and in the stored records (with MySQL, `ALTER TABLE ... CHANGE`), on a copy first.

#### Database Views (dbview) and Table Editor
- The write operations (new, edit, delete) need a logged-in user with the rights of the section: sections that let anonymous visitors add records must now ask them to log in. `FN_XMETATableEditor()` allows no write operation when nobody is logged in.
- Deleting a record needs a POST with an anti-CSRF token: a delete link opens a confirmation form.
- The version history of the records is shown only to the administrators.

#### Web Server
The `.htaccess` block written by FINIS (`# BEGIN Finis` ... `# END Finis`) now passes the `Authorization` header to PHP and answers `/.well-known/oauth-authorization-server` and `/.well-known/oauth-protected-resource/...` for the OAuth login of the MCP clients. When mod_rewrite is detected and `.htaccess` is writable, the block is rewritten by the first request to `index.php`; otherwise add the rules by hand (see the [Installation Manual](en_installation_manual.md) and, for a site in a subfolder, the [MCP guide](en_mcp_guide.md)).

#### Custom Code
The database library is in `src/include/xmetadb/` and its classes are in the `Xmetadb` namespace, with aliases that keep the old names (`XMETATable`, `XMETADatabase`, ...). A standalone script that loads the library includes `include/xmetadb.php` and `include/xmetadb_query.php`, which remain as loaders, and not the files inside the folder.

### Database Update

#### Migration Between Database Drivers
To change database driver (e.g., from XML to MySQL):

1. **Configure new database**:
   ```php
   // Update config.vars.local.php
   $_FN['default_database_driver'] = "mysql";
   $_FN['xmetadb_mysqlhost'] = "localhost";
   $_FN['xmetadb_mysqldatabase'] = "finis_db";
   $_FN['xmetadb_mysqlusername'] = "username";
   $_FN['xmetadb_mysqlpassword'] = "password";
   ```

2. **Export from current database**:
   ```php
   <?php
   // Script to export data from one driver to another
   require_once "path/to/FINIS.php";
   $FINIS = new FINIS();

   // List of tables to migrate
   $tables = array(
       "fn_sections", "fn_users", "fn_groups", "fn_settings",
       "fn_blocks", "fn_blockslocation", "fn_conditions",
       // other tables...
   );

   // Configure source driver (original)
   $_FN['default_database_driver'] = "xmlphp";  // Example

   foreach ($tables as $tableName) {
       // Get data from source table
       $sourceTable = FN_XMDBTable($tableName);
       $records = $sourceTable->GetRecords();

       // Save in intermediate format
       file_put_contents(
           "export_{$tableName}.json",
           json_encode($records)
       );
   }
   ```

3. **Import to new database**:
   ```php
   <?php
   // Script to import data to new driver
   require_once "path/to/FINIS.php";
   $FINIS = new FINIS();

   // Configure destination driver
   $_FN['default_database_driver'] = "mysql";  // Example

   $tables = array(
       "fn_sections", "fn_users", "fn_groups", "fn_settings",
       "fn_blocks", "fn_blockslocation", "fn_conditions",
       // other tables...
   );

   foreach ($tables as $tableName) {
       // Load data from intermediate format
       $records = json_decode(
           file_get_contents("export_{$tableName}.json"),
           true
       );

       if (!$records) continue;

       // Import to new database
       $destTable = FN_XMDBTable($tableName);

       foreach ($records as $record) {
           $destTable->InsertRecord($record);
       }

       echo "Migrated " . count($records) . " records for $tableName<br>";
   }
   ```

## Data Migration

### CSV Import/Export

#### Export to CSV
```php
<?php
// Script to export data to CSV
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

$tableName = "fn_users";  // Example
$table = FN_XMDBTable($tableName);
$records = $table->GetRecords();

// Open file for writing
$fp = fopen("{$tableName}_export.csv", 'w');

// Write headers
if (count($records) > 0) {
    fputcsv($fp, array_keys($records[0]));
}

// Write data
foreach ($records as $record) {
    fputcsv($fp, $record);
}

fclose($fp);
echo "Export completed: {$tableName}_export.csv";
```

#### Import from CSV
```php
<?php
// Script to import data from CSV
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

$tableName = "fn_users";  // Example
$table = FN_XMDBTable($tableName);

// Open CSV file
$fp = fopen("{$tableName}_import.csv", 'r');

// Read headers
$headers = fgetcsv($fp);

// Read and import data
while (($data = fgetcsv($fp)) !== false) {
    $record = array_combine($headers, $data);
    $table->InsertRecord($record);
}

fclose($fp);
echo "Import completed";
```

### File Migration

#### File Structure Transfer
```bash
# Script to transfer files between installations
rsync -av --exclude='config.vars.local.php' \
          --exclude='misc/*' \
          --exclude='.git' \
          /path/to/old_finis/ /path/to/new_finis/
```

## Post-Migration Optimization

### Cache and Performance

#### Clear Cache
```php
<?php
// Script to clear cache
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

// Remove and recreate the cache folder {datadir}/_cache
// (the login attempt counters in _login_attempts are kept)
FN_ClearCache();

echo "Cache cleared successfully";
```

#### Database Optimization
```php
<?php
// Script to optimize MySQL tables
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

// Only for MySQL driver
if ($_FN['default_database_driver'] == "mysql") {
    $tables = array(
        "fn_sections", "fn_users", "fn_groups",
        // other tables...
    );

    // Direct database connection
    $conn = mysqli_connect(
        $_FN['xmetadb_mysqlhost'],
        $_FN['xmetadb_mysqlusername'],
        $_FN['xmetadb_mysqlpassword'],
        $_FN['xmetadb_mysqldatabase']
    );

    foreach ($tables as $table) {
        $result = mysqli_query($conn, "OPTIMIZE TABLE $table");
        if ($result) {
            echo "Table $table optimized<br>";
        }
    }

    mysqli_close($conn);
    echo "Database optimization completed";
}
```

## Emergency Rollback

### Rollback Plan
In case of problems during migration, it's important to have a rollback plan:

1. **Pre-migration backup**:
   - Complete database
   - Site files
   - Configurations

2. **Recovery plan**:
   - Document with detailed steps
   - Estimated time for rollback
   - Required personnel

### Rollback Procedure

#### File Restore
```bash
# Restore files from backup
rm -rf /path/to/finis
tar -xzf finis_backup_files.tar.gz -C /
```

#### Database Restore
```bash
# For MySQL
mysql -u username -p dbname < finis_backup_db.sql

# For SQLite
cp finis_backup.sqlite3 /path/to/finis/misc/database.sqlite3
```

#### Post-Rollback Verification
After restore, verify:

1. Site access
2. Main functionality
3. Content integrity
4. Error logs

## Best Practices

### Before Migration
- Always perform a complete backup
- Test migration in a development environment
- Inform users of maintenance period
- Plan migration during low traffic hours
- Verify system requirements

### During Migration
- Monitor the process
- Maintain a detailed log
- Follow procedure step-by-step
- Verify each phase before proceeding
- Keep backups accessible

### After Migration
- Verify all main functionality
- Check logs for errors
- Monitor performance
- Gather user feedback
- Document any issues and solutions
