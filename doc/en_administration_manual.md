# FINIS Framework Administration Manual

## Introduction
This manual is aimed at administrators of websites based on FINIS. It provides detailed instructions on using the control panel, managing users, content, and system configuration.

## Control Panel Access

### Administrator Login
1. Access your site URL
2. Click on the "Login" link or access directly `http://yoursite.com/?mod=login`
3. Enter the administrator credentials configured during installation
4. After login, access the control panel via `http://yoursite.com/index.php?fnapp=controlcenter` (or `controlcenter.php`, when the site has a copy of `controlcenter.php.example`)

If you open the control panel without being logged in as an administrator, it shows its own login form. Administrators are the users with level 10.

### Control Panel Interface
The control panel is organized into groups of pages, listed in the side menu:

- **Contents**: **Pages and structure** (the page tree), **Blocks**, **Files**, plus one entry for each page whose module has a management page (e.g. **News in "..."**)
- **Global settings**: **Website setup**, **Look and feel**, plus the settings of the pages and blocks whose module has options (e.g. **Page News: ...**, **Page dbview: ...**)
- **Users and groups**: **Users**, **Groups**, **Users fields**, **Edit profile**, **Conditions for registration**, **OAuth providers**, **MCP server**
- **Utilities**: **Control center users**, **Planned operations**, **Event log**, **Send email**, **Server information**, **Finis database**
- **Plugins**: shown only when the site has plugins with a settings page

The start page (dashboard) shows the summaries of the modules that provide one, for example the number of published and unpublished news.

Applications and extensions can add their own pages to these groups: they appear in the menu with the others.

The look of the control panel is chosen with **default control center theme** in **Global settings > Website setup** (`classic`, `adminkit`, `sidebar`). The `adminkit` theme has a help icon in the top bar that opens the FINIS documentation on GitHub.

## User and Group Management

### User Management
Access **Users and groups > Users** to:

1. **View users**: List of all registered users (username, email, level, groups, registration date). The links at the top show all the users, only the enabled ones, only the disabled ones or only the administrators
2. **Create a new user**:
   - Click on "Add a user"
   - Fill in the required fields (username, password, email)
   - Select group memberships
   - Set the level (10 = administrator) and the account status (active/disabled)
   - Save changes
3. **Edit an existing user**:
   - Click on the edit icon next to the user
   - Update the necessary data; leave the password empty to keep the current one
   - Save changes
4. **Enable or disable a user**:
   - Use the "Enable" / "Disable" button in the list
   - When enabling, tick "send welcome message" to send the welcome email
   - A disabled user is logged out at the next request, also when already logged in
   - You cannot disable your own account
5. **Delete a user**:
   - Click on the delete icon
   - Confirm the operation

Notes:
- Passwords are stored as hashes: nobody, administrators included, can read them. To give a user a new password, type it in the user form. A new password logs the user out of the other browsers where the user was logged in, and revokes the user's API key.
- Usernames are saved and looked up in lowercase (login, registration, password recovery), so `Mario` and `mario` are the same user. To keep the case as typed, set `$_FN['username_case_sensitive'] = 1;` in `config.vars.local.php`.

### Group Management
Access **Users and groups > Groups** to:

1. **View groups**: List of all existing groups
2. **Create a new group**:
   - Click on "New"
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

Users who register on the site are put in the `users` group.

### User Fields
**Users and groups > Users fields** defines the fields of the user record, shown in the registration and profile forms: name, label (with a translation for each language), field type, size and options.

### Your Profile and API Key
**Users and groups > Edit profile** edits the account you are logged in with. The same page holds the **API key** block: "Generate API key" creates your personal key and shows it once (copy it at once), "Revoke API key" deletes it. The key lets MCP and API clients act on the site with your permissions; see [MCP Server and API Keys](#mcp-server-and-api-keys).

The users of the site find the same block in the profile page of the login module (`index.php?mod=login&op=editreg`).

### Permission System
Permissions in FINIS are based on user levels and groups:

1. **Page Permissions**:
   - Access **Contents > Pages and structure**
   - Edit a page
   - In the "Permissions" part of the form, define:
     - User level for viewing (visible to everyone, only registered users, users with at least level 1-9, only administrators)
     - Groups for viewing
     - Groups for editing
2. **Module Permissions**:
   - Each module can define its own permissions
   - Generally configurable in the page settings (**Global settings > Page ...**), e.g. the groups of a dbview page
3. **Control panel access for non-administrators**:
   - Access **Utilities > Control center users**
   - Add the user, select the control panel pages the user may use and the start page
   - The user then logs into the control panel and sees only those pages

**Menu visibility**: the menu shows a page only when the visitor may see its content. Pages with a viewing level or viewing groups are hidden from the other visitors; a dbview page whose records are limited to a group is shown only to the members of its groups (view, insert, administrators); a table editor page only to administrators. Administrators see every entry.

### Registration Settings
To configure the registration process:

1. Access **Global settings > Website setup** and configure:
   - **enable user registration**
   - **registration by mail confirmation**: the new user receives an email with a confirmation link; without it the account is active at once
   - **use email address as username**
   - **allow users to stay connected**: shows the "remember me" option in the login form
   - **enable captcha in login form**
2. Access **Users and groups > Conditions for registration** to manage the terms the user accepts when registering: title, text (one per language), enabled, optional. The list shows title, text, enabled and optional; the other fields are in the form. Enabled conditions appear in the registration form; the ones that are not optional must be accepted.
3. The options of the login page (captcha in the registration form, welcome message after the registration, password change online during the recovery) are in **Global settings > Page login: ...**
4. The texts of the emails sent to the users are the templates of the login module (`mailconfirm.tp.html`, `mailwelcome.tp.html`, `mailrecovery_*.tp.html`); the theme can provide its own copies

About the confirmation codes:
- The confirmation link is single use and stays valid until the account is confirmed. The user can ask for it again from the login page; it is resent only to a registration still waiting for confirmation.
- Registrations made with an old version of FINIS (5 character codes) do not get a new email: enable these users from **Users and groups > Users**.
- Refused resends are written in the **Event log**.

### Login with External Providers (OAuth)
**Users and groups > OAuth providers** lists the external services (e.g. Google) the users can log in with. For each provider set:
- name and image (shown in the login form)
- `client_id` and `client_secret`, obtained by registering the site with the provider
- `auth_url`, `token_url`, `userinfo_url` and `scope` of the provider
- enabled

The address to register with the provider as redirect URI is `{siteurl}index.php?fnloginprovider=<id>`, where `<id>` is the number of the provider record. The user logged in is the one with the email address given by the provider; when the provider says that the address is not verified, the login is refused. When no user has that address and registration is enabled, a new user is created with the address as username.

### Login Security
FINIS protects the login without any configuration. The administrator should know how it works:

- **Lockout**: after `login_max_attempts` attempts (default 5) with the same username from the same address within `login_lockout_minutes` (default 15), further attempts are refused until the time has passed. The user sees the usual generic login error; a successful login resets the counter. Each lockout is written once in the **Event log** ("User ... locked out ...", with the client address). To unlock a user at once, delete the folder `misc/_login_attempts` (in the data folder of the site): clearing the cache does not reset it.
- **Remember me**: with **allow users to stay connected** enabled, the login form offers "remember me"; the login then lasts `remember_login_days` (default 30) days.
- **Password recovery**: the recovery link sent by email is valid for `recovery_link_minutes` (default 60) minutes and can be used once. The recovery page answers the same way for existing and unknown users.
- **Site secret**: the file `misc/secret.key.php` is created automatically and signs the login cookies, the OAuth tokens of the MCP clients and the stored copies of the API keys. Deleting or replacing it logs out every user. Back it up with the data, never publish it and never commit it to a repository.
- **Reverse proxy**: behind a reverse proxy or load balancer every request comes from the proxy address, so a lockout would block a username for everybody. List the proxy addresses in `trusted_proxies` so that the client address is read from `X-Forwarded-For`.

These keys go in `config.vars.local.php` (the values below are the defaults):

```php
$_FN['login_max_attempts'] = 5;       // login attempts per username and address
$_FN['login_lockout_minutes'] = 15;   // time window of the attempts, and longest lockout
$_FN['remember_login_days'] = 30;     // duration of the login with "remember me"
$_FN['recovery_link_minutes'] = 60;   // validity of a password recovery link
$_FN['trusted_proxies'] = "";         // reverse proxy IP addresses, comma separated (no ranges)
```

### MCP Server and API Keys
FINIS includes an MCP server (`index.php?fnapp=mcp`) that lets AI assistants (Claude, ChatGPT, ...) read and edit the site with the permissions of a user. A client connects with the personal API key of the user or with an OAuth login (username and password).

**Users and groups > MCP server** (administrators only):
- lists the MCP endpoints of the site and the OAuth address for Claude Desktop and claude.ai
- lists the users with a key or allowed to write; search a user, or click "All the users", to act on any user
- **Show key**: shows the key of a user (every key shown is written in the Event log); keys created by old versions cannot be shown, generate a new one
- **Generate key** / **Revoke**: creates a new key for the user (the previous one stops working) or deletes it
- **Allow the write tools** / **Set read only**: reading is open to every active user; the tools that change the site are allowed only to administrators and to the members of the group `mcp_write` (`$_FN['mcp_write_group']`, created automatically)

`$_FN['apikey_groups']` in `config.vars.local.php` limits the API keys to the members of some groups (administrators always). A new password revokes the user's key.

Configuration of the clients, list of the tools and security notes: see the [MCP server and API keys guide](en_mcp_guide.md).

## Content Management

### Site Structure
The site is organized into **sections** (pages). Each section can be:
- A content page
- A specific module (news, login, etc.)
- A subsection of another section

The menu of the site is built from this tree.

### Section Management
Access **Contents > Pages and structure** to:

1. **View site structure**: Represented as a hierarchical tree, with type, viewing level, viewing and editing groups and publication dates of each page
2. **Add a new section**:
   - Click on "Create a new page"
   - Fill in the form:
     - **ID**: Unique identifier (used in URL)
     - **Title**: Name displayed in menu and titles
     - **Page type**: Choose the module of the page (empty for a content page, news, login, etc.)
     - **Position**: Position in the section tree
     - **Permissions**: Viewing level and groups with access to the section
     - **Publication status**: Published or not published, with optional publication start and end dates
     - **Page is hidden in menus**: the page exists but has no menu entry
   - Save the section
3. **Edit an existing section**:
   - Click on the edit icon
   - Update necessary parameters
   - Save changes
4. **Reorder sections**:
   - Use the arrows of each page to move it up or down, or to change its level (left/right)
   - Click the light bulb to hide or show the page in the menu
   - Save the order
5. **Delete a section**:
   - Click on the delete icon
   - Confirm the operation

### Content Editing
To edit the content of a section:

1. Open the content of the page from **Contents > Pages and structure**
2. If it's a standard section type:
   - Use the WYSIWYG editor to modify content
   - Insert text, images, and formatting
   - Each language has its own text: a missing translation can be created empty or copied from the default language
   - The previous versions are listed under the editor, with the user who overwrote them: "Restore" loads one of them
3. If it's a specific section type (news, custom module, etc.):
   - The editing interface will change based on the type
   - Follow specific instructions for that content type
4. Save changes

### Block Management
Blocks are secondary content displayed in specific positions (sidebars, header, etc.). Access **Contents > Blocks** to:

1. **View existing blocks**: List of all configured blocks
2. **Create a new block**:
   - Click on "Create a new block"
   - Fill in the form:
     - **Title**: Name displayed in block header
     - **Block type**: HTML or a module
     - **Position**: Left, right, top, bottom
     - **Pages**: displays anywhere, only on the selected pages, or everywhere except the selected pages
     - **Permissions**: Viewing level and groups that can see or edit the block
     - **Status**: Published or not published
   - Save the block
3. **Edit a block**:
   - Click on the edit icon
   - Update parameters
   - Save changes
4. **Reorder blocks**:
   - Use the arrows to move the blocks
   - Save the order
5. **Delete a block**:
   - Click on the delete icon
   - Confirm the operation

### File and Media Management
Access **Contents > Files** to manage files with the file manager:

1. **Browse files and folders**: Navigate through the folder structure, as a list or as icons
2. **Upload new files**:
   - Select the destination folder
   - Click on "Upload file"
   - Select files from your computer
   - Confirm the upload
3. **Create new folders and files**:
   - Click on "New folder" or "New file"
   - Enter the name
   - Confirm creation
4. **Manage existing files**:
   - Rename: Click on the pencil icon
   - Delete: Click on the delete icon
   - Download: Click on "Download"
   - Open: shows the path, the URL, the size and a preview; the files you may modify can also be edited
5. **Use files in content**:
   - Copy the file URL
   - In the content editor, use "Insert image" or "Insert link" button
   - Select the file from the structure or paste the URL

You can upload, rename and delete only the files you are allowed to modify.

### Site Search
The search page (a section of type search) looks for the typed words in:
- the titles and descriptions of the pages
- the text of the content pages, in the current language (in the other languages only when the page has no text in the current one)
- the records of the pages whose module provides a search (dbview, news)

Hidden pages are searched only through their module. The results are grouped per section, at most 30 for each section; the theme's search template can show the number of results of each section and, for dbview sections, a link to the section list filtered by the same text. A search box without the "any word / all words / exact phrase" selector (e.g. in the menu) looks for all the words.

## System Configuration

### General Settings
Access **Global settings > Website setup** to configure:

1. **Site information**:
   - Site name, title, subtitle and keywords
   - Site url (empty = autodetect)
   - Site email address (sender of the emails) and logs email address
2. **Pages and links**:
   - Default section (home page)
   - Apache mod_rewrite, links mode, path method
   - Show access key
3. **Users**:
   - Registration, email confirmation, email as username, "remember me", login captcha (see [Registration Settings](#registration-settings))
4. **Features**:
   - HTML editor, online administration, credits, timezone
   - Site in maintenance: visitors see a maintenance page; administrators and the control panel keep working
   - Enable cache, compress pages with gzip
   - Enable LOG email to administrator: the system events written in the log are also mailed to the logs email address

After saving, the page shows "The data were successfully updated". Each text field shows its default value as placeholder, and the field tooltip shows the variable name and the default value. The other settings pages of the control panel (themes, page and block settings) work the same way.

Settings that are not in the control panel go in `config.vars.local.php`, in the folder of the site:
- `$_FN['display_errors']`: "off" by default; set it to "on" only while looking for an error
- `$_FN['home_section_force']`: a home section that overrides the default section saved in the control panel
- the database connection, the login security keys and the API key options described in this manual

### Language Settings
In **Global settings > Website setup**:

1. **Available languages** (**Contents languages**):
   - Tick the languages of the site
   - Order them with the arrows: the first language of the list is the default one
2. **Translations**:
   - The interface strings are in the `languages/<language>/lang.csv` files of FINIS and of the site
   - The texts of the pages are translated page by page (see [Content Editing](#content-editing))

### Theme Management
Access **Global settings > Look and feel** to:

1. **Select theme**:
   - Choose from available themes: the themes of FINIS and the ones in the `themes/` folder of the site
   - The preview below shows the chosen theme, with buttons for different screen sizes
   - Click "Apply this theme" to use it
2. **Configure the theme**:
   - Theme-specific options (the settings of the theme)
   - Edit its CSS files
   - Replace its images (same type)
3. **Create a new theme**:
   - Under "Create new theme" choose the starting theme and the new name
   - Click "Make theme": the theme is copied and can be modified

Any page can be previewed with another theme with `index.php?themepreview=<theme>`: always for administrators, for the other visitors only when **enable theme switch** is set. The **Default theme** and **enable theme switch** options are in **Global settings > Website setup**.

The menus are built from the page tree: to change them, edit the pages in **Contents > Pages and structure** (position, level, hidden in menus).

### Cache and Performance
In **Global settings > Website setup**:

1. **Manage cache**:
   - **enable cache** turns the cache on
   - The cache is kept in the `misc/_cache` folder and is cleared automatically when pages or blocks change
   - To clear it by hand, delete the content of `misc/_cache`
2. **Optimization**:
   - **compress pages with gzip**
   - dbview pages can keep a cached copy of the record pages for anonymous visitors (see [Database Pages](#database-pages-dbview-module))

## Module Management

### Module Installation
Modules extend FINIS functionality: each module is a page type. To install them:

1. Obtain the module (download or custom development)
2. Upload the module folder to the `modules/` folder of the site via FTP or file manager
3. At the next request the module is registered as a new page type
4. Create a page in **Contents > Pages and structure** and choose the module as page type

### Module Configuration
Each module has its own configuration options:

1. Access **Global settings > Page [module]: [page title]** (or **Block [module] in "..."** for a block)
2. Configure module-specific options
3. Save changes

Modules with a management page also have an entry in **Contents** (e.g. **News in "..."**).

### Common Modules and Their Configuration

#### News Module
To manage news and blog:

1. Access **Global settings > Page News: [page title]**
2. Configure:
   - News per page
   - Comments (enabled, by anonymous users, notifications)
   - Reports, previous/next news, news of the same argument, news image
   - Group of users allowed to publish the news
   - Social network buttons, RSS icon, tags, Google sitemap, cache
3. To manage the news:
   - Go to **Contents > News in "[page title]"**
   - Add, edit and publish the news
   - "Edit arguments" manages the arguments (categories) of the news

#### Database Pages (dbview module)
A dbview page shows the records of a table as a list and a record page, with search, insert and edit forms. Its options are in **Global settings > Page dbview: [page title]**:

1. **Groups**:
   - **limit the display of content to the group** (`groupview`): only these users see the records (and the menu entry); empty = everybody
   - **limit the insert of content to the group** (`groupinsert`): who can add records; empty = every logged in user
   - **group administrators** (`groupadmin`): who manages all the records of the page
2. **Writing needs a login**: adding, editing and deleting records always requires a logged in user with the permission, whatever the groups; anonymous visitors (and web crawlers) can only read.
3. **Version history** (**enables data history**): keeps the previous versions of each record; the history is shown only to the site administrators and to the group administrators of the page.
4. **Offline form** (**enable offline form**): the record page offers a form to download, fill in without a connection and send by email. **fields not shown in the offline form** and **fields shown as plain text in the offline form** take comma separated field names; file fields are never in the form.
5. **Cached record pages**: for pages visited by many anonymous visitors, the record pages can be cached. Set it in `config.vars.local.php`:

   ```php
   $_FN['dbview_view_cache'] = array("mypage" => 3600); // page id => seconds of validity (an int = every dbview page)
   $_FN['dbview_view_cache_keep'] = 30;                  // days after which unused files are removed
   ```

   Only anonymous visitors get the cached copy. A copy is replaced when the table changes and is removed when its record is saved or deleted; the files are in `misc/_cache/pages/` and are removed with the cache.

## Maintenance and Backup

### System Backup
The control panel has no backup tool: copy the files of the site regularly.

1. Back up:
   - the data folder `misc/`, including `misc/fndatabase/` (the database with the default file driver) and the site secret `misc/secret.key.php`
   - the `sections/` folder (pages content) and the themes, modules and files you added
   - `config.vars.local.php`
   - the MySQL database, when the site uses the `mysql` driver
2. The folders `misc/_cache`, `misc/_sessions` and `misc/_login_attempts` do not need a backup
3. Keep multiple versions in safe locations: the backup contains the site secret, so protect it like a password

### Restore from Backup
To restore the system from a backup:

1. Copy the files back to the site folder
2. Restore the MySQL database, if used
3. Restore `misc/secret.key.php` too: without it every user is logged out, the OAuth clients must log in again and the stored API keys can no longer be shown (the keys themselves keep working)

### Database Maintenance
Access **Utilities > Finis database** to:

1. View the tables of the site and the driver of each one
2. Edit the structure of a table, its contents, or its XML definition
3. Create a new table

With the `mysql` driver, use the tools of the database server for optimization and repair.

### Updates
To keep the system secure and updated:

1. Check for available updates
2. Perform a complete backup before updating
3. Optionally enable **Site in maintenance** in **Global settings > Website setup**
4. Replace the FINIS source files with the new version, then follow the [migration guide](en_migration_guide.md) for the changes that need an action

## Advanced Tools

### Theme Editor
To customize the site's appearance:

1. Access **Global settings > Look and feel**
2. Select the theme and the file to modify (CSS files; the theme structure and template for advanced users)
3. Make changes to the code
4. Save changes
5. Verify changes don't cause issues

### System Log
To monitor events and diagnose problems:

1. Access **Utilities > Event log**
2. View the events, newest first: date, context, user, IP address and text. They include:
   - System errors and warnings written by FINIS
   - Login lockouts and refused registration code requests
   - Changes to the configuration
   - API keys shown by administrators and changes made through the MCP server (contexts `apikey` and `mcp`)
3. Filter by context, user, text of the event, or date range
4. The events written by older versions of FINIS (monthly files in `misc/log/`) are listed under "Archive", one button per month

### Cron Jobs
To manage automated tasks, access **Utilities > Planned operations**:

1. **Operations**: each operation has:
   - **operation**: a PHP function name, a full URL, or a local URL starting with `?` (e.g. `?fnapp=...`)
   - **cron**: one schedule per line, in the form `year-month-day hour:minute:second weekday`, where each part is a number, `*` (any) or `*/n` (every n); weekday 0 = Sunday. Text after `#` is a comment. The list shows the schedule in words
   - **last execution**: date of the last run
2. Examples:
   - `*-*-* 04:00:00 1`: every Monday at 4:00
   - `*-*-* *:*/5:00 *`: every 5 minutes
   - `*-*-1 00:00:00 *`: the first day of every month at midnight
3. **Cron service**: the page starts the service `index.php?fnapp=cron` in a frame; the service keeps running by restarting its own request, and only one instance runs at a time. On a server, call that address periodically (e.g. with `wget` or `curl` from the system crontab) to be sure it is running
4. **Logs**: the last 100 lines of `misc/log/cron.log` are shown under the list, with a link to download the file

### Other Utilities
- **Utilities > Control center users**: gives users who are not administrators access to some pages of the control panel (see [Permission System](#permission-system))
- **Utilities > Send email**: sends a test email (from, to, subject, text), to check the mail configuration of the server
- **Utilities > Server information**: the PHP configuration of the server (`phpinfo`)

## External Service Integration

### Plugins
The **Plugins** group of the control panel lists the plugins of the site (`plugins/` folder) that have a settings page. Among the plugins included with FINIS:
- **Header append**: HTML added to the `<head>` of every page. Paste here the tracking code of Google Analytics or of another statistics service
- **Google sitemap**: creates the `sitemap.xml` file of the site; it is also updated when the pages change

### Social Media
The news module has its own options for social network buttons (see [News Module](#news-module)).

## Troubleshooting

### Common Problems and Solutions

#### Login Errors
- **Problem**: Can't log in with correct credentials
- **Solution**:
  1. Check CAPS LOCK
  2. Wait `login_lockout_minutes` (default 15): after too many attempts the login is refused even with the right password. Look for "locked out" in **Utilities > Event log**; to unlock at once delete the folder `misc/_login_attempts`
  3. Check in **Users and groups > Users** that the account is enabled
  4. Request a new password, or set a new one in the user form
  5. Behind a reverse proxy, set `trusted_proxies` (see [Login Security](#login-security))

#### Everybody Is Logged Out
- **Problem**: all the users have to log in again, MCP clients connected with OAuth must log in again
- **Solution**:
  1. Check that `misc/secret.key.php` exists, is readable by the web server and has not been replaced (e.g. by a restore or a new deployment of the data folder)
  2. If it was lost, restore it from a backup; otherwise the new secret created automatically is valid from now on and the users only need to log in again

#### "Service temporarily unavailable" (503)
- **Problem**: the site shows "Service temporarily unavailable: the database is busy" and reloads the page every 10 seconds
- **Solution**:
  1. The MySQL server refused the connection for about 5 seconds (too many connections, e.g. the `max_user_connections` limit of a shared hosting) or cannot be reached. Users are not logged out: the page works again as soon as the server accepts connections
  2. Look for "xmetadb: MySQL connection failed" in the PHP error log
  3. If it happens often, raise the connection limits of the database server or reduce the traffic (cache, cached dbview pages)

#### Blank Page or 500 Error
- **Problem**: Site shows a blank page or server error
- **Solution**:
  1. Set `$_FN['display_errors'] = "on";` in `config.vars.local.php` while looking for the error, and back to "off" afterwards (the default: errors are not shown to visitors)
  2. Check PHP logs on the server and **Utilities > Event log**
  3. Verify file permissions (the `misc/` folder must be writable)
  4. Disable recently installed modules or plugins

#### File Upload Problems
- **Problem**: Can't upload files
- **Solution**:
  1. Check destination folder permissions
  2. Check file size limits in php.ini
  3. Verify file type is allowed (executable files such as `.php` are always refused)
  4. Try uploading smaller files

### Contacting Support
If you can't resolve an issue:

1. Gather detailed information:
   - FINIS version
   - Precise problem description
   - Screenshot if applicable
   - Event log entries and PHP error log
   - Actions that preceded the problem
2. Contact support through official channels

## Best Practices

### Security
- Keep FINIS and all modules updated
- Use complex passwords and change them regularly
- Limit administrative access to known IPs if possible
- Perform regular backups, including `misc/secret.key.php`, and keep them as safe as a password
- Regularly check the Event log for suspicious activity (lockouts, keys shown)
- Allow the MCP write tools only to the users who need them
- Keep `display_errors` "off" on a public site

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
