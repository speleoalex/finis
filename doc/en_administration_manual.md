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
  3. Check in settings that your account is not locked
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
- Perform regular backups
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
