# FINIS Framework Administration Manual

## Introduction
This manual is aimed at administrators of websites based on FINIS. It provides detailed instructions on using the control panel, managing users, content, and system configuration.

## Control Panel Access

### Administrator Login
1. Access your site URL
2. Click on the "Login" link or access directly `http://yoursite.com/?mod=login`
3. Enter the administrator credentials configured during installation
4. After login, access the control panel via `http://yoursite.com/?mod=controlcenter`

### Control Panel Interface
The control panel is organized into several sections:

- **Dashboard**: General site overview
- **Content**: Management of sections, blocks, and files
- **Users and Groups**: User, group, and permission management
- **Settings**: General site configuration
- **Tools**: System utilities and maintenance
- **Modules**: Installed module configuration

## User and Group Management

### User Management
Access **Users and Groups > Users** to:

1. **View users**: List of all registered users
2. **Create a new user**:
   - Click on "Add new"
   - Fill in the required fields (username, password, email)
   - Select group memberships
   - Set account status (active/disabled)
   - Save changes
3. **Edit an existing user**:
   - Click on the edit icon next to the user
   - Update the necessary data
   - Save changes
4. **Delete a user**:
   - Click on the delete icon
   - Confirm the operation

### Group Management
Access **Users and Groups > Groups** to:

1. **View groups**: List of all existing groups
2. **Create a new group**:
   - Click on "Add new"
   - Enter the group name
   - Describe the group's purpose
   - Save changes
3. **Edit a group**:
   - Click on the edit icon
   - Update the information
   - Save changes
4. **Delete a group**:
   - Click on the delete icon
   - Confirm the operation (note: users belonging to the group will not be deleted)

### Permission System
Permissions in FINIS are group-based:

1. **Section Permissions**:
   - Access **Content > Sections**
   - Select a section
   - In the "Permissions" tab, define:
     - Groups with view permission
     - Groups with edit permission
2. **Module Permissions**:
   - Each module can define its own permissions
   - Generally configurable in module settings

### Registration Settings
To configure the registration process:

1. Access **Settings > General**
2. Configure options:
   - Enable registrations
   - Require email confirmation
   - Default group for new users
   - Terms and conditions
3. Customize emails sent to users in **Settings > Email**

### Login and Password Security
These rules apply to every user, administrators included.

**Passwords**
- Passwords are stored as one-way hashes (`password_hash`). Old accounts imported from earlier versions may still have an MD5 hash. Those users log in with their usual password, and the hash is replaced with a modern one at that login. Nothing changes for the user, and no reset is needed. An MD5 hash stays in the database until its owner logs in again.
- The stored hash is never accepted as a password ("pass-the-hash" is refused).
- The only built-in rule is that a password cannot contain spaces. A site can add its own rules by defining `FN_PasswordVerifyConstraints_overwrite($password)`. It returns an error message, or an empty string when the password is accepted.
- Saving a profile without typing a new password keeps the current password.

**Failed logins and lockout**
- After 5 failed attempts for the same username from the same IP address within 15 minutes, further attempts are refused, even with the right password, until the 15 minutes have passed. A successful login clears the counter.
- Each lockout is written once to the system log ("User ... locked out for 15 minutes ...").
- There is nothing to unlock by hand: the lock expires on its own. To clear it at once, delete the files in `misc/_login_attempts/`. Clearing the cache doesn't reset the counters.
- Settings (in `config.vars.local.php`): `$_FN['login_max_attempts']` (default 5) and `$_FN['login_lockout_minutes']` (default 15).
- **Behind a reverse proxy** (Caddy, nginx, Traefik), set `$_FN['trusted_proxies']` to the proxy's IP addresses. If it isn't set, every visitor appears with the proxy's address. A username is then locked for everyone after 5 wrong attempts from anywhere.

**Sessions and "remember me"**
- The session cookie is signed with a secret key specific to the site, `misc/secret.key.php`, which is created automatically. Include this file in backups. If it is lost or changes, every user is logged out once and has to log in again. The same happens once when a site is updated to this version.
- "Remember me" keeps the user logged in for 30 days (`$_FN['remember_login_days']`). Setting `$_FN['remember_login'] = 0` turns the option off.
- A user who is deactivated is logged out at their next request.

**Password recovery and registration**
- "Forgot password" emails a single-use link that is valid for 60 minutes (`$_FN['recovery_link_minutes']`). Links sent before an update to this version no longer work, so the user has to request a new one.
- The recovery and "resend code" pages give the same answer whether or not the username exists, so they can't be used to find out which accounts exist.
- Registration confirmation codes are random and single use. Captcha codes are single use too.
- Usernames are saved in lowercase unless `$_FN['username_case_sensitive']` is set. Usernames made only of dots are refused.

**API keys**
- When `$_FN['apikey_groups']` is set, only administrators and the members of those groups can use or see an API key.

## Content Management

### Site Structure
The site is organized into **sections**. Each section can be:
- A content page
- A specific module (news, login, etc.)
- A subsection of another section

### Section Management
Access **Content > Sections** to:

1. **View site structure**: Represented as a hierarchical tree
2. **Add a new section**:
   - Click on "Add new"
   - Fill in the form:
     - **ID**: Unique identifier (used in URL)
     - **Title**: Name displayed in menu and titles
     - **Type**: Choose section type (html, news, login, etc.)
     - **Position**: Position in the section tree
     - **Permissions**: Groups with access to the section
     - **Status**: Published, hidden, or draft
   - Save the section
3. **Edit an existing section**:
   - Click on the edit icon
   - Update necessary parameters
   - Save changes
4. **Reorder sections**:
   - Drag sections to the desired position using drag-and-drop
   - Save the order
5. **Delete a section**:
   - Click on the delete icon
   - Confirm the operation

### Content Editing
To edit the content of a section:

1. After creating or selecting a section, go to the "Content" tab
2. If it's a standard section type:
   - Use the WYSIWYG editor to modify content
   - Insert text, images, and formatting
   - Create versions in different languages by selecting the language from the dropdown
3. If it's a specific section type (news, custom module, etc.):
   - The editing interface will change based on the type
   - Follow specific instructions for that content type
4. Save changes

### Block Management
Blocks are secondary content displayed in specific positions (sidebars, header, etc.). Access **Content > Blocks** to:

1. **View existing blocks**: List of all configured blocks
2. **Create a new block**:
   - Click on "Add new"
   - Fill in the form:
     - **Title**: Name displayed in block header
     - **Type**: HTML, menu, login, etc.
     - **Position**: Left, right, top, bottom
     - **Visibility**: Sections where the block will be visible
     - **Permissions**: Groups that can see the block
     - **Status**: Published or disabled
   - Save the block
3. **Edit a block**:
   - Click on the edit icon
   - Update parameters
   - Save changes
4. **Reorder blocks**:
   - Drag blocks to the desired position
   - Save the order
5. **Delete a block**:
   - Click on the delete icon
   - Confirm the operation

### File and Media Management
Access **Content > Files** to manage multimedia files:

1. **Browse files and folders**: Navigate through the folder structure
2. **Upload new files**:
   - Select the destination folder
   - Click on "Upload file"
   - Select files from your computer
   - Confirm the upload
3. **Create new folders**:
   - Click on "New folder"
   - Enter the name
   - Confirm creation
4. **Manage existing files**:
   - Rename: Click on the edit icon
   - Delete: Click on the delete icon
   - Move: Drag files into folders
5. **Use files in content**:
   - Copy the file URL
   - In the content editor, use "Insert image" or "Insert link" button
   - Select the file from the structure or paste the URL

## System Configuration

### General Settings
Access **Settings > General** to configure:

1. **Site information**:
   - Site name
   - Description
   - Administration email
   - Site logo
2. **SEO settings**:
   - Default meta tags
   - Robots.txt
   - XML sitemap
3. **Features**:
   - Enable/disable specific functions
   - Set timeouts and limits
4. **Debug and Log**:
   - Log level
   - Error display

### Language Settings
Access **Settings > Languages** to configure:

1. **Available languages**:
   - Enable/disable languages
   - Set default language
2. **Translations**:
   - Edit translation strings
   - Import/export language files

### Theme Management
Access **Settings > Appearance** to:

1. **Select theme**:
   - Choose from available themes
   - Preview
2. **Configure active theme**:
   - Theme-specific options
   - Colors, fonts, and layout
3. **Manage menus**:
   - Create/edit menu items
   - Set order and hierarchy

### Cache and Performance
Access **Tools > Cache** to:

1. **Manage cache**:
   - View cache status
   - Clear cache
   - Configure cache settings
2. **Optimization**:
   - Output compression
   - CSS/JS minification
   - Browser caching

## Module Management

### Module Installation
Modules extend FINIS functionality. To install them:

1. Obtain the module (download or custom development)
2. Upload files to the `modules/` folder via FTP or file manager
3. Access **Modules > Management**
4. The new module should appear in the list
5. Click "Activate" to make it available

### Module Configuration
Each module has its own configuration options:

1. Access **Modules > [Module Name]**
2. Configure module-specific options
3. Save changes

### Common Modules and Their Configuration

#### News Module
To manage news and blog:

1. Access **Modules > News**
2. Configure:
   - Number of news per page
   - Date format
   - Comment options
   - News categories
3. To add new news:
   - Go to **Content > News**
   - Click "Add new"
   - Fill in title, text, categories, and date
   - Save the news

#### Form Module
To create custom contact forms:

1. Access **Modules > Forms**
2. To create a new form:
   - Click "Add new"
   - Add fields (text, email, checkbox, etc.)
   - Configure notification email
   - Save the form
3. To view responses:
   - Select the form
   - Go to the "Responses" tab
   - Export data if needed

#### Site Search
The **search** section looks for the words in every section the visitor may open:

- results are grouped by section, with at most 30 results for each section and 500 in all; the theme template receives, for each section, the number of results shown (`{count}`), a flag when the section has more (`{more}`), an anchor (`{anchor}`) and, for database sections, a link to the section list filtered by the same text (`{url_list}`). These appear only in themes whose `modules/search/searchform.tp.html` uses them;
- a search box without the "method" field (for example the one in the menu) looks for pages that contain all the words;
- the text under each result is the first 160 characters of the record description, and is left empty when the record has no description;
- records the visitor may not see are left out after the database has returned the first 31 matches of a section, so a section can show fewer results than it really has for that visitor. Use more specific words, or the search of the section itself, to find the others.

#### Database Views (dbview): Sorting and List Templates
- A field holding numbers stored as text (lengths, depths) is sorted by its value when the table definition sets `frm_sort` to `numeric` (or `abs`, on the absolute value), or when its field class asks for it. Empty values go last and the comma is accepted as the decimal separator. With such an order the "previous/next" links of a record sheet use the slower generic path.
- List templates can show any field of the record as `{value_<field>}`. The value is the raw one stored in the database: it is neither translated nor escaped, and the field's visibility rules are not applied. Use it only for fields every visitor of the list may see and that hold no HTML typed by users.

## Maintenance and Backup

### System Backup
It's essential to perform regular backups:

1. Access **Tools > Backup**
2. Backup options:
   - Complete backup (files + database)
   - Database only
   - Files only
3. Download the backup file to your computer
4. Keep multiple versions in safe locations

### Restore from Backup
To restore the system from a backup:

1. Access **Tools > Backup**
2. Select "Restore backup"
3. Upload the backup file
4. Follow instructions to complete the restore

### Database Maintenance
To optimize the database:

1. Access **Tools > Database**
2. Available functions:
   - Table optimization
   - Table repair
   - Integrity check

### Updates
To keep the system secure and updated:

1. Check for available updates
2. Perform a complete backup before updating
3. Access **Tools > Updates**
4. Follow instructions to update the system

## Advanced Tools

### Theme Editor
To customize the site's appearance:

1. Access **Tools > Theme Editor**
2. Select the file to modify
3. Make changes to the code
4. Save changes
5. Verify changes don't cause issues

### System Log
To monitor events and diagnose problems:

1. Access **Tools > Log**
2. View:
   - User accesses
   - System errors
   - Administrative actions
3. Filter by type, date, or user
4. Export logs for external analysis

### Cron Jobs
To manage automated tasks:

1. Access **Tools > Cron**
2. Available functions:
   - View scheduled jobs
   - Add new jobs
   - Modify execution frequency
   - Run jobs manually
   - View execution logs

## External Service Integration

### Google Analytics
To track site visits:

1. Access **Settings > Integrations**
2. In the Google Analytics section:
   - Enter the tracking code
   - Configure privacy options
   - Select pages to exclude from tracking

### Social Media
To integrate social media:

1. Access **Settings > Integrations > Social Media**
2. Configure:
   - Share buttons
   - Site social profiles
   - Social feeds to display

### Newsletter
To manage newsletter subscriptions:

1. Access **Modules > Newsletter**
2. Configure:
   - Newsletter service (internal or external)
   - Email templates
   - Distribution lists
   - Subscription forms

## Troubleshooting

### Common Problems and Solutions

#### Login Errors
- **Problem**: Can't log in with correct credentials
- **Solution**:
  1. Check CAPS LOCK
  2. Request a new password
  3. After 5 wrong passwords the login is locked for 15 minutes, even with the right password. Wait, or delete the files in `misc/_login_attempts/` (see Login and Password Security)
  4. If nothing works, access the database and manually reset the password

#### Blank Page or 500 Error
- **Problem**: Site shows a blank page or server error
- **Solution**:
  1. Enable error display in `config.vars.local.php`
  2. Check PHP logs on the server
  3. Verify file permissions
  4. Disable recently installed plugins

#### File Upload Problems
- **Problem**: Can't upload files
- **Solution**:
  1. Check destination folder permissions
  2. Check file size limits in php.ini
  3. Verify file type is allowed
  4. Try uploading smaller files

### Contacting Support
If you can't resolve an issue:

1. Gather detailed information:
   - FINIS version
   - Precise problem description
   - Screenshot if applicable
   - System logs
   - Actions that preceded the problem
2. Contact support through official channels

## Best Practices

### Security
- Keep FINIS and all modules updated
- Use complex passwords and change them regularly
- Limit administrative access to known IPs if possible
- Perform regular backups, including `misc/secret.key.php`
- Regularly check logs for suspicious activity

### Performance
- Enable cache
- Optimize images before uploading
- Limit the number of active modules
- Regularly clean database and temporary files
- Consider using a CDN for static resources

### Content Management
- Organize content with a logical structure
- Use tags and categories to facilitate navigation
- Keep file names descriptive and without spaces
- Create an editorial calendar for regular updates
- Regularly review old content to verify its relevance
