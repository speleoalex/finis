# FINIS Framework Theme Guide

## Introduction
This guide provides the information needed to create, customize, and manage graphic themes for the FINIS framework. The theme system allows you to completely change the site's appearance without altering the content structure.

## Theme Structure

### Location and Organization
Themes are contained in the `themes/` folder and each theme has its own subfolder. A typical theme contains:

```
themes/
  └── yourthemename/
      ├── template.tp.html   # Main template
      ├── form.tp.html       # Form template
      ├── grid.tp.html       # Grid template
      ├── view.tp.html       # View template
      ├── config.php         # Theme configurations
      ├── css/               # Stylesheets
      │   ├── style.css      # Main style
      │   └── ...            # Other CSS
      ├── js/                # JavaScript
      │   └── ...
      └── img/               # Theme images
          ├── logo.png
          └── ...
```

### Main Template
The fundamental file of every theme is `template.tp.html`, which defines the basic HTML structure of the site.

## Creating a Basic Theme

### Creating the Structure
1. Create a new folder in `themes/` (e.g., `themes/mytheme/`)
2. Create the necessary subfolders: `css/`, `js/`, `img/`
3. Create the main template files

### Main Template
The `template.tp.html` file defines the general page structure. Here's a basic example:

```html
<!DOCTYPE html>
<html lang="{lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{site_title}</title>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="{siteurl}themes/mytheme/css/style.css">

    <!-- System CSS -->
    {css}

    <!-- System JavaScript -->
    {javascript}

    <!-- Custom head -->
    {head}
    {header_append}
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <a href="{siteurl}">
                    <img src="{siteurl}themes/mytheme/img/logo.png" alt="{sitename}">
                    <!-- if {isadmin} -->
                     Admin
                    <!-- end if {isadmin} -->
                </a>
            </div>

            <nav class="main-menu">
                <!-- foreach {menuitems} -->
                <a href="{link}" class="<!-- if {active} -->active<!-- end if {active} -->">
                    {title}
                </a>
                <!-- end foreach {menuitems} -->
            </nav>

            <div class="user-menu">
                <!-- if {user} -->
                    <a href="{urlprofile}">{user}</a> |
                    <a href="{urllogout}">{i18n:Logout}</a>
                <!-- end if {user} -->
                <!-- if not {user} -->
                    <a href="{urllogin}">{i18n:Login}</a> |
                    <a href="{urlregister}">{i18n:Register}</a>
                <!-- end if not {user} -->
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <!-- if {blocks_top} -->
                <div class="blocks-top">
                    <!-- foreach {blocks_top} -->
                    <section>
                        <!-- if {blocktitle} --><h3>{blocktitle}</h3><!-- end if {blocktitle} -->
                        <div>{html}</div>
                    </section>
                    <!-- end foreach {blocks_top} -->
                </div>
            <!-- end if {blocks_top} -->

            <div class="content-wrapper">
                <!-- if {blocks_left} -->
                    <aside class="sidebar-left">
                        <!-- foreach {blocks_left} -->
                        <section>
                            <!-- if {blocktitle} --><h3>{blocktitle}</h3><!-- end if {blocktitle} -->
                            <div>{html}</div>
                        </section>
                        <!-- end foreach {blocks_left} -->
                    </aside>
                <!-- end if {blocks_left} -->

                <div class="main-content">
                    <h1>{title}</h1>

                    <!-- if {path} -->
                        <div class="breadcrumbs">
                            {path}
                        </div>
                    <!-- end if {path} -->

                    <!-- include section -->

                    <!-- end include section -->
                </div>

                <!-- if {blocks_right} -->
                    <aside class="sidebar-right">
                        <!-- foreach {blocks_right} -->
                        <section>
                            <!-- if {blocktitle} --><h3>{blocktitle}</h3><!-- end if {blocktitle} -->
                            <div>{html}</div>
                        </section>
                        <!-- end foreach {blocks_right} -->
                    </aside>
                <!-- end if {blocks_right} -->
            </div>

            <!-- if {blocks_bottom} -->
                <div class="blocks-bottom">
                    <!-- foreach {blocks_bottom} -->
                    <section>
                        <!-- if {blocktitle} --><h3>{blocktitle}</h3><!-- end if {blocktitle} -->
                        <div>{html}</div>
                    </section>
                    <!-- end foreach {blocks_bottom} -->
                </div>
            <!-- end if {blocks_bottom} -->
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="copyright">
                &copy; {year} {sitename}
            </div>
            <div class="footer-menu">
                {menu_footer}
            </div>
        </div>
    </footer>

    <!-- Additional JavaScript -->
    <script src="{siteurl}themes/mytheme/js/script.js"></script>
    {footer_append}
</body>
</html>
```

### Form Template
The `form.tp.html` file defines how forms are rendered in the system:

```html
<div class="card">
    <div class="card-body bg-light">
        <!-- if {text_on_update_ok} -->
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {text_on_update_ok}
        </div>
        <!-- end if {text_on_update_ok} -->

        <!-- if {text_on_update_fail} -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {text_on_update_fail}
        </div>
        <!-- end if {text_on_update_fail} -->

        <form onsubmit="formChanged = false;" id="editform" method="post" action="{action}" enctype="multipart/form-data">
            <!-- contents -->
            <div class="form-group row">
                <!-- group -->
                <fieldset>
                    <legend>{groupname}</legend>
                    <!-- end_group -->

                    <!-- item -->
                    <div class="form-group row">
                        <label class="col-sm-2 col-form-label" for="{fieldname}">
                            {title}:
                            <!-- error -->
                            <em style="color:red"><br />{error}</em>
                            <!-- end_error -->
                        </label>
                        <div class="col-sm-10">
                            <!-- inputattributes:class="form-control" -->
                            {input}
                            <!-- if {help} -->
                            <small class="form-text text-muted">{help}</small>
                            <!-- end if {help} -->
                        </div>
                    </div>
                    <hr />
                    <!-- end_item -->

                    <!-- endgroup -->
                </fieldset>
                <!-- end_endgroup -->
            </div>
            <!-- end_contents -->

            <button class="btn btn-primary" type="submit">{textsave}</button>
            <!-- if {textcancel} -->
            <a class="btn btn-primary" href="{url_cancel}">{textcancel}</a>
            <!-- end if {textcancel} -->
        </form>
    </div>
</div>
```

## Template Variables
FINIS uses a template system that allows inserting variables and conditional logic in HTML templates.

### Main Variables
- `{sitename}`: Site name
- `{siteurl}`: Site base URL
- `{title}`: Current page title
- `{site_title}`: Complete site title (often sitename + page title)
- `{lang}`: Current language code
- `{css}`: System CSS
- `{javascript}`: System JavaScript
- `{head}`: Additional header content
- `{header_append}`: Scripts or styles added by modules
- `{footer_append}`: Scripts added by modules at page bottom
- `{user}`: Current username (empty if not logged in)
- `{year}`: Current year
- `{url_avatar}`: User avatar URL
- `{urllogin}`: Login URL
- `{urllogout}`: Logout URL
- `{urlprofile}`: Profile page URL
- `{urlregister}`: Registration URL

### Block Variables
- `{blocks_top}`: Blocks positioned at top
- `{blocks_left}`: Blocks positioned on left
- `{blocks_right}`: Blocks positioned on right
- `{blocks_bottom}`: Blocks positioned at bottom

For each block:
- `{blocktitle}`: Block title
- `{html}`: Block HTML content

### Menu Variables
- `{menuitems}`: Main menu items
  - `{title}`: Menu item title
  - `{link}`: Menu item URL
  - `{id}`: Menu item ID
  - `{active}`: If the item is active
  - `{accesskey}`: Item accesskey
  - `{havechilds}`: If the item has sub-items
  - `{childs}`: Array of sub-items

### Multilingual Variables
- `{is_multilanguage}`: If the site is multilingual
- `{sitelanguages}`: List of available languages
  - `{langname}`: Language code
  - `{langtitle}`: Language name
  - `{langflag}`: Language flag

### Conditional Logic
```html
<!-- if {user} -->
    <!-- Content for logged-in users -->
<!-- end if {user} -->

<!-- if not {user} -->
    <!-- Content for non-logged-in users -->
<!-- end if not {user} -->

<!-- if {blocks_left} -->
    <aside class="sidebar-left">
        <!-- foreach {blocks_left} -->
        <section>
            <!-- if {blocktitle} -->
            <h3>{blocktitle}</h3>
            <!-- end if {blocktitle} -->
            <div>{html}</div>
        </section>
        <!-- end foreach {blocks_left} -->
    </aside>
<!-- end if {blocks_left} -->
```

### Loops
```html
<!-- foreach {menuitems} -->
<li class="nav-item">
    <a href="{link}" class="nav-link <!-- if {active} -->active<!-- end if {active} -->">
        {title}
    </a>
</li>
<!-- end foreach {menuitems} -->
```

## Theme Customization

### Overriding Module Files
You can customize a module's templates by creating the same file structure within the theme folder:

```
themes/mytheme/modules/login/login.tp.html
```

This file will override the original `modules/login/login.tp.html`.

### Theme Configuration
The `config.php` file in the theme folder allows defining specific options:

```php
<?php
global $_FN;

// Theme configurations
$theme_config = array(
    'name' => 'My Theme',
    'author' => 'Your Name',
    'version' => '1.0',
    'description' => 'A beautiful responsive theme for FINIS',
    'support_responsive' => true,
    'support_blocks_positions' => array('top', 'left', 'right', 'bottom'),
    'default_blocks_position' => 'right'
);

// Custom theme settings
$theme_settings = array(
    'primary_color' => '#007bff',
    'secondary_color' => '#6c757d',
    'show_logo' => true,
    'show_site_name' => true,
    'footer_text' => '&copy; ' . date('Y') . ' ' . $_FN['sitename'],
    'enable_animations' => true,
    'sidebar_width' => '250px',
    'max_width' => '1200px'
);

// Add custom CSS and JS
$_FN['header_append'] .= '
<link rel="stylesheet" href="' . $_FN['siteurl'] . 'themes/' . $_FN['theme'] . '/css/custom.css">
<script src="' . $_FN['siteurl'] . 'themes/' . $_FN['theme'] . '/js/custom.js" defer></script>
';
```

## Integrated CSS Frameworks
FINIS supports various popular CSS frameworks. Here's how to integrate Bootstrap:

### Theme with Bootstrap 5
1. Create a new folder: `themes/bootstrap5/`
2. Download Bootstrap files and place them in `themes/bootstrap5/css/` and `themes/bootstrap5/js/`
3. Create the main template using Bootstrap classes

## Responsive Design
To ensure your theme is responsive:

1. Always use viewport meta tag:
```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```

2. Use relative units (%, em, rem) instead of pixels when possible
3. Implement media queries to adapt the layout to different screen sizes:
```css
/* Desktop */
@media (min-width: 992px) {
    .container {
        max-width: 960px;
    }
}

/* Tablet */
@media (max-width: 991px) and (min-width: 768px) {
    .container {
        max-width: 720px;
    }
}

/* Mobile */
@media (max-width: 767px) {
    .container {
        width: 100%;
        padding: 0 10px;
    }
}
```

4. Use Flexbox or CSS Grid for responsive layouts

## Performance Optimization

### Resource Minimization
1. Minimize CSS and JavaScript
2. Optimize images before including them in the theme
3. Use the defer attribute for non-critical JavaScript:
```html
<script src="{siteurl}themes/mytheme/js/script.js" defer></script>
```

### Asynchronous Loading
For non-critical resources, use asynchronous loading:
```html
<script src="{siteurl}themes/mytheme/js/analytics.js" async></script>
```

## RTL (Right-to-Left) Support
To support RTL languages like Arabic or Hebrew:

1. Create a dedicated stylesheet: `css/rtl.css`
2. Add logic to load it when needed
3. Use the `dir="rtl"` attribute on the html tag

## Best Practices

### Code Organization
- Keep CSS, JS, and images in separate folders
- Use a consistent naming method for files and classes
- Comment the code to improve maintainability
- Separate structure (HTML), presentation (CSS), and behavior (JS)

### Compatibility
- Test on different browsers (Chrome, Firefox, Safari, Edge)
- Test on different screen sizes
- Verify that the site works without JavaScript enabled

### Accessibility
- Use semantic HTML5 tags (`header`, `nav`, `main`, `footer`, etc.)
- Add ARIA attributes when necessary
- Ensure color contrast is sufficient
- Provide alternative text for images

### Security
- Don't use inline JavaScript directly in templates
- Avoid exposing sensitive data in templates
- Sanitize all outputs to prevent XSS attacks

## Troubleshooting

### Common Errors
1. **Theme not loading**: Verify the folder is named correctly and matches the value of `$_FN['theme']`
2. **Template variables not replaced**: Check the syntax, must be exactly `{variable_name}`
3. **CSS/JS files not loading**: Check paths and ensure files exist

### Theme Debug
```php
// In config.php, temporarily add:
echo "<!-- Theme Debug: " . $_FN['theme'] . " loaded -->";
print_r($_FN['tp']); // Show all template variables
```

## Activating the Theme
To activate the theme, modify the configuration file or use the administration panel to set:
```php
$_FN['theme'] = 'mytheme';
```
