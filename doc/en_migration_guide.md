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
       $table = FN_XMDBTable("fn_users");

       $newUser = array(
           'username' => $user['user_login'],
           'email' => $user['user_email'],
           'name' => $user['display_name'],
           'regdate' => $user['user_registered'],
           'group' => 'users',  // default group
           'active' => 1
       );

       // Generate temporary password
       $newUser['password'] = FN_GenerateRandomPassword();

       // Insert user
       $table->InsertRecord($newUser);

       // Optional: send email with temporary password
       FN_SendPasswordEmail($user['user_email'], $newUser['password']);
   }
   ```

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
   // Script to update image references in sections
   $table = FN_XMDBTable("fn_sections");
   $sections = $table->GetRecords();

   foreach ($sections as $section) {
       if (isset($section['content'])) {
           // Replace image paths
           $content = $section['content'];
           $content = str_replace(
               'wp-content/uploads/',
               'misc/uploads/',
               $content
           );

           // Update the record
           $table->UpdateRecord(
               array('content' => $content),
               'id',
               $section['id']
           );
       }
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
   - Check server meets new version requirements
   - Verify custom module compatibility
   - Read release notes for significant changes

#### Update Procedure
1. **Download new files**:
   - Download the latest version of FINIS
   - Extract to a temporary folder

2. **Replace files**:
   ```bash
   # Remove core files, keeping configurations and content
   rm -rf /path/to/finis/src/*.php
   rm -rf /path/to/finis/src/include
   rm -rf /path/to/finis/src/modules
   rm -rf /path/to/finis/src/controlcenter

   # Copy new files
   cp -R /path/to/new_version/src/* /path/to/finis/src/
   ```

3. **Run update scripts**:
   - Access URL: `http://yoursite.com/?mod=install&op=update`
   - Follow instructions to complete the update

4. **Post-update verification**:
   - Check all pages work correctly
   - Verify content correctness
   - Check error log

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

// Empty cache directory
$cacheDir = $_FN['datadir'] . "/_cache";
$files = glob($cacheDir . '/*');

foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

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
