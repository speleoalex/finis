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
      ├── config.php         # Theme options (edited in the control center)
      ├── theme.php          # Optional PHP code, included on every page
      ├── modules/           # Templates that replace those of the modules
      │   ├── login/login.tp.html
      │   └── dbview/list.tp.html
      ├── css/               # Stylesheets
      │   ├── style.css      # Main style
      │   └── ...            # Other CSS
      ├── js/                # JavaScript
      │   └── ...
      └── img/               # Theme images
          ├── logo.png
          └── ...
```

### Where Themes Are Looked Up
The theme in use is the one named by `$_FN['theme']`. FINIS looks for its folder in two places:

1. the `themes/` folder of the site (the application), for example `themes/mytheme/`;
2. the `themes/` folder of FINIS (`src/themes/`: `base`, `default`, `bootstrap5`).

A theme of the site works exactly like a theme of FINIS: it is listed, previewed and applied in the
control center (see [Activating the Theme](#activating-the-theme)). When a file exists in both
folders, the copy of the site wins. If the theme exists in neither folder, FINIS uses the default
theme, then `base`. Extensions (`$FINIS->addExtension()`) add modules and sections, not themes.

A theme of FINIS is copied into the site the first time it is used: `template.tp.html` and the
files that it links with a relative path (`href`, `src`, `background`), then `form.tp.html`,
`grid.tp.html`, `view.tp.html` and the files of `img/` when a form or a grid is shown, are copied
to `themes/<name>/` of the site, and from then on the site's copy is the one rendered. Two
consequences:

- after changing one of these files in `src/themes/<name>/`, update (or delete) the copy in the site;
- files referenced only from a CSS file are not copied: link them from the template too, or keep
  the theme in the `themes/` folder of the site.

Some files are read only from the site's copy of the theme: `template.<section>.tp.html` (main
template of a single section, for example `template.home.tp.html`) and
`template.type.<module>.tp.html` (main template of the sections of a module, for example
`template.type.dbview.tp.html`). When present they replace `template.tp.html`.

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

    <!-- Relative paths start from the theme folder -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <a href="{siteurl}">
                    <img src="img/logo.png" alt="{sitename}">
                </a>
            </div>

            <nav class="main-menu">
                <!-- foreach {menuitems} -->
                <a href="{link}" accesskey="{accesskey}" class="<!-- if {active} -->active<!-- end if {active} -->">
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
                        <!-- if not {hidetitle} --><h3>{title}</h3><!-- end if not {hidetitle} -->
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
                            <!-- if not {hidetitle} --><h3>{title}</h3><!-- end if not {hidetitle} -->
                            <div>{html}</div>
                        </section>
                        <!-- end foreach {blocks_left} -->
                    </aside>
                <!-- end if {blocks_left} -->

                <div class="main-content">
                    <!-- if not {is_home} -->
                    <div class="breadcrumbs">
                        <!-- foreach {nav} --><a href="{link}">{title}</a> &raquo; <!-- end foreach {nav} -->
                    </div>
                    <h1>{section_title}</h1>
                    <!-- end if not {is_home} -->

                    <!-- include section -->

                    <!-- end include section -->
                </div>

                <!-- if {blocks_right} -->
                    <aside class="sidebar-right">
                        <!-- foreach {blocks_right} -->
                        <section>
                            <!-- if not {hidetitle} --><h3>{title}</h3><!-- end if not {hidetitle} -->
                            <div>{html}</div>
                        </section>
                        <!-- end foreach {blocks_right} -->
                    </aside>
                <!-- end if {blocks_right} -->
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="copyright">
                {sitename} - {site_subtitle}
            </div>
            <div class="footer-menu">
                {credits}
            </div>
        </div>
    </footer>

    <script src="js/script.js" defer></script>
</body>
</html>
```

Notes on the main template:

- The section (the page content) goes where `<!-- include section --><!-- end include section -->`
  is: keep this placeholder, the offline copies need it (see
  [Offline Copies and Ajax Fragments](#offline-copies-and-ajax-fragments)).
- FINIS adds its own head before `</head>`: the CSS of the module and of the section, the
  scripts of `include/javascripts/`, the meta tags and the output of the `header.php` files. The
  theme only needs a `</head>` tag. The `<title>` is always replaced with the title of the page
  (`$_FN['site_title']`, which a module can change, for example with the title of a record).
- `href`, `src` and `background` with a relative path (no `:` and no `{`) start from the folder
  of the template, so `css/style.css` is `themes/mytheme/css/style.css`.

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

### Control Center Themes

The themes of the control center live in `controlcenter/themes/<name>/` (`classic`, `sidebar`,
`adminkit`) and are chosen with the setting `controlcenter_theme`. Besides `controlcenter.tp.html`,
`form.tp.html`, `grid.tp.html` and `view.tp.html`, a theme can provide:

- `editconf.tp.html`: the editor of the `config.php` files (site settings, module and theme
  options). The template loops over `{items}` (`{id}`, `{title}`, `{htmlinput}`, `{value}`,
  `{defaultvalue}`) and may show `{message_saved}` after a save. The inputs are generated by
  FINIS; the attributes they get are declared in the template as HTML comments:

```html
<!-- inputattributes:class="form-control" -->              text, password and color inputs
<!-- inputattributes select:class="form-select" -->
<!-- inputattributes checkbox:class="form-check-input" -->
<!-- inputattributes button:class="btn btn-light btn-sm" --> the move up/down links of the lists
```

  The template is used only when it contains a `<form>`: FINIS adds its scripts and hidden
  fields before `</form>`. `{formaction}` and `{linkcancel}` are also available.
  `editconf.tp.html` is read from the theme folder of FINIS (`src/controlcenter/themes/<name>/`).

- `controlcenter/sections/<group>/<section>/*.tp.html`: the templates of a single section
  (for example `users_and_groups/profile/form.tp.html`), found with `FNCC_FromTheme()`. A
  customized copy in `controlcenter/themes/<name>/` of the site wins over the one of FINIS.

From the `fncc` theme on, this convention also applies:

- `{cc_assets}` in the `<head>` of the template: FINIS puts there the shared stylesheet and
  script of the panel (`controlcenter/css/fncc-ui.css` and `controlcenter/js/fncc-ui.js`) and,
  right after them, the `css/theme.css` and `js/theme.js` of the theme, when they exist. The
  files are copied into the site folder, and copied again when the source in FINIS is newer, so
  an upgrade of the framework also reaches the sites already installed. A theme without
  `{cc_assets}` still gets the shared files, added before `</head>`.
- A theme should therefore not link its own CSS with `<link href="css/...">`: a file referenced
  inside a template is copied into the site only once, and the copy would stay behind.

`fncc-ui.css` defines the colours as CSS variables (`--fncc-surface`, `--fncc-text`,
`--fncc-primary`, ...), with the dark set under `@media (prefers-color-scheme: dark)` and under
`:root[data-fncc-theme="dark"]`. Restyling the panel is a matter of redefining those variables in
`css/theme.css`: the components of the sections (buttons, cards, tables, the sitemap tree) follow
with no change to the PHP.

The file manager draws its icons with Unicode characters (`FN_GetIconCharByFilename()`), so a
theme does not need to provide icon images for it.

## Template Variables
FINIS uses a template system that allows inserting variables and conditional logic in HTML templates.

### Main Variables
Every string or number of `$_FN` is a variable of the template (`{sitename}`, `{siteurl}`,
`{lang}`, `{user}`...), the fields of the logged user are `{user_<field>}` and those of the
current section `{section_<field>}`. The most used:

- `{sitename}`: Site name
- `{siteurl}`: Site base URL
- `{site_title}`: Title of the page
- `{site_subtitle}`: Site subtitle
- `{section_title}`, `{section_description}`: Title and description of the current section
- `{lang}`: Current language code
- `{user}`: Current username (empty if not logged in)
- `{url_avatar}`: User avatar URL
- `{urllogin}`: Login URL
- `{urllogout}`: Logout URL
- `{urlprofile}`: Profile page URL
- `{urlregister}`: Registration URL
- `{is_home}`: True on the home section
- `{url_logo}`: `themes/<theme>/images/logo.png` (or `.svg`) of the site, empty if missing
- `{credits}`, `{navbar}` (breadcrumb as HTML), `{hmenu}` (menu as HTML), `{languages}` (flags as HTML)
- `{nav}`: Breadcrumb as a list of sections (`{title}`, `{link}`, `{active}` on the current one)
- `{notifications}`, `{notifications_count}`: Notifications not yet displayed to the user
- the options of the theme's `config.php` (see [Theme Configuration](#theme-configuration))

A variable that does not exist is left in the page as it is written.

### Block Variables
- `{blocks_top}`: Blocks positioned at top
- `{blocks_left}`: Blocks positioned on left
- `{blocks_right}`: Blocks positioned on right

Each block has the fields of its record:
- `{title}`: Block title
- `{hidetitle}`: True when the title must not be shown
- `{html}`: Block HTML content

Blocks can also be printed with an include, which works for every position (`top`, `left`,
`right`, `bottom`):

```html
<!-- include blocks_bottom -->
<!-- blockitem -->
<section>
    <!-- blockheader --><h3><!-- blocktitle -->title<!-- end blocktitle --></h3><!-- end blockheader -->
    <div><!-- blockcontents -->contents<!-- end blockcontents --></div>
</section>
<!-- end blockitem -->
<!-- end include blocks_bottom -->
```

### Menu Variables
- `{menuitems}`: Main menu items
  - `{title}`: Menu item title
  - `{link}`: Menu item URL
  - `{id}`: Menu item ID
  - `{active}`: If the item is active
  - `{opened}`: If the current section is inside this item
  - `{accesskey}`: Item accesskey
  - `{havechilds}`: Number of sub-items
  - `{childs}`: Array of sub-items

The menu lists only the sections whose content the user may see: for example a dbview section
restricted to some groups, or the table editor, are not shown to the other users.

### Multilingual Variables
- `{is_multilanguage}`: If the site is multilingual
- `{current_language}`: The current language (same fields as below)
- `{sitelanguages}`: List of available languages
  - `{langname}`: Language code
  - `{langtitle}`: Language name
  - `{langflag}`: Language flag (Unicode)
  - `{langimg}`: URL of the flag image

### Translations
`{i18n:text}` prints the translation of `text` in the current language. The case of the first
letter is kept: `{i18n:Login}` gives "Login", `{i18n:login}` "login".

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
            <!-- if not {hidetitle} -->
            <h3>{title}</h3>
            <!-- end if not {hidetitle} -->
            <div>{html}</div>
        </section>
        <!-- end foreach {blocks_left} -->
    </aside>
<!-- end if {blocks_left} -->
```

The condition is always a single variable: `<!-- if {var} -->` or `<!-- if not {var} -->`, closed
by `<!-- end if {var} -->` or `<!-- end if not {var} -->`. Expressions (`&&`, `||`, `!`) are not
supported. An empty string, `"0"`, `false` and an empty array are false. When the variable of an
`if` is an array, its keys become variables inside the block:

```html
<!-- if {navigationbar} -->
    {toolbar_html}
<!-- end if {navigationbar} -->
```

Here `{toolbar_html}` is a key of the array `{navigationbar}`.

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

`if` and `foreach` blocks work in templates of any size, as long as a single block (with its
loops already expanded) stays under the PHP setting `pcre.backtrack_limit` (1,000,000 characters
by default): raise it for larger blocks, for example a list of thousands of records inside one
`if`.

### Escaping
The template engine prints the values as they are: it escapes nothing, and neither does
`FN_RewriteLink()`. A value that comes from the request (`$_GET`, `$_POST`, the query string)
must be escaped before it reaches a template, with `FN_GetParam($name, $_GET, "html")` in PHP or
with a modifier in the template:

```html
<input name="q" value="{q.addHtmlEntities()}">
```

`{var.addHtmlEntities()}` applies `htmlentities()` to the value (`{var.addSlashes()}` applies
`addslashes()`, which is not an HTML escape). The `{formaction}` that FINIS gives to the login and
logout forms is already escaped.

## Theme Customization

### Overriding Module Files
You can customize a module's templates by creating the same file structure within the theme folder:

```
themes/mytheme/modules/login/login.tp.html
```

This file will override the original `modules/login/login.tp.html`. A module template is looked
up (`FN_FromTheme()`) in this order:

1. `themes/<theme>/modules/<module>/<file>` in the site;
2. `src/themes/<theme>/modules/<module>/<file>` in FINIS;
3. the file of the module.

Some modules give the section folder (`sections/<section>/`) priority over the theme: see dbview below.

### dbview Templates
The dbview module (database tables shown as lists and record sheets) has several templates. The
toolbar of the record (`toolbar.tp.html`) and the other record templates (`detail.tp.html`,
`formedit.tp.html`, `history.tp.html`, `users.tp.html`) are looked up in this order:

1. `sections/<section>/` of the site (one section only);
2. `themes/<theme>/modules/dbview/` (site, then FINIS);
3. `src/modules/dbview/`.

The list (`list.tp.html`) has two more places:

1. `themes/<theme>/sections/<section>/list.tp.html` in the site (wins over everything);
2. `sections/<section>/list.tp.html`;
3. `themes/<theme>/modules/dbview/list.tp.html` (site, then FINIS);
4. the file named by `$_FN['dbview_list_template']` (one list template for all the sections of
   the application, used only when the theme has no list template of its own);
5. `src/modules/dbview/list.tp.html`.

**Toolbar.** The record templates print the toolbar with `{toolbar_html}` (inside
`<!-- if {navigationbar} -->`). `toolbar.tp.html` loops over `{items}`, each with `{key}`
(`list`, `prev`, `next`, `view`, `history`, `edit`, `users`), `{link}`, `{title}` (full text,
for the tooltip), `{label}` (visible text), `{icon}` and `{active}`:

```html
<ul class="nav nav-tabs dbview-toolbar">
    <!-- foreach {items} -->
    <li class="nav-item dbview-toolbar-{key}">
        <a class="nav-link" href="{link}" title="{title}"><span class="dbview-toolbar-icon" aria-hidden="true">{icon}</span><span class="dbview-toolbar-text">{label}</span></a>
    </li>
    <!-- end foreach {items} -->
</ul>
```

The icons are Unicode characters (`☰ ← → ⓘ ↺ ✎ 👥`) drawn in the colour of the text. A theme can
change them without overriding the template, for example in its `theme.php`:

```php
$_FN['dbview_toolbar_icons'] = array("edit" => "✏", "history" => "🕘");
```

The module's `style.css` makes the toolbar compact on small screens: under 768 px the tabs stay
on one row that scrolls sideways and only the active tab shows its text; under 576 px only the
icons are shown.

**Record cards.** The default `list.tp.html` shows the records as cards in a grid that takes as
many cards per row as fit (at least 240 px each). Each record of `{items}` has:

- `{item_title}`, `{item_urlview}`, `{item_urledit}` and `{item_urldelete}` (the last two are
  false when the user may not edit or delete the record);
- `{item_urlimage}` (thumbnail), `{item_urlimage_fullsize}` and `{item_hasimage}` (true when the
  record has a photo, the default template shows the photo only then);
- `{itemvalues}`: the fields shown in the list (`{title}`, `{value}`, `{fieldname}`);
- `{viewvalue_<field>}` and `{title_<field>}`: the same values by field name;
- `{value_<field>}`: the raw value of every field of the record, not escaped (use
  `{value_<field>.addHtmlEntities()}` for text typed by the users).

The cards are styled by the module's `style.css` through the classes `.dbview-cards` (the grid),
`.dbview-card`, `.dbview-card-media` (photo with a fixed ratio), `.dbview-card-values` (one row
per field), `.dbview-card-view` (the view button) and `.dbview-noresult`; the CSS also has
`.dbview-card-badges`, `.dbview-card-code`, `.dbview-card-nophoto` and `.dbview-card-noimage` for
templates that use them. This CSS is placed in the head of the page after the theme's
stylesheets, so a theme that restyles the cards needs more specific selectors, for example:

```css
.dbview-cards .dbview-card {
    border-radius: 0;
}
```

**Ajax navigation.** The pages, the category filters and the search form of the list are loaded
with `call_ajax()` / `call_ajax_form()`: the page is requested with `fnajax=1` and the containers
named in the call replace the `div`, `span`, `td` or `tr` with the same `id`; the address bar
follows the navigation. A
list template that keeps this behaviour keeps the containers `<div id="pageresults">` and
`<div id="collapseFilters">` and the calls:

```html
<a href="{link}" onclick="call_ajax(this.href, 'pageresults,collapseFilters');return false">2</a>
<form onsubmit="return call_ajax_form(this, 'pageresults,collapseFilters');" method="get" action="{formaction}">
```

### Login and Registration Templates
The login module templates (`login.tp.html`, `register.tp.html`, `profile.tp.html`,
`editreg.tp.html`, the password recovery and mail templates) are overridden in
`themes/<theme>/modules/login/`. The themes `default` and `bootstrap5` of FINIS are complete
references.

`login.tp.html` uses `{formaction}` (already escaped) and parts marked by HTML comments, some of
which FINIS removes when not needed:

```html
<form action="{formaction}" method="post" name="login">
    <input name="username" type="text" autocomplete="username">
    <input name="password" type="password" autocomplete="current-password">
    <!-- captcha -->{htmlcaptcha}<!-- end captcha -->
    <!-- rememberlogin -->
    <label><input type="checkbox" name="rememberlogin" value="1"> {i18n:Stay connected}</label>
    <!-- endrememberlogin -->
    <button type="submit">{i18n:Login}</button>
    <!-- register --><a href="{urlregister}">{i18n:Register}</a><!-- end register -->
    <!-- passwordrecovery --><a href="{urlpasswordrecovery}">{i18n:Password recovery}</a><!-- end passwordrecovery -->
    <!-- loginerror --><div class="alert">{login_error}</div><!-- end loginerror -->
</form>
<!-- foreach {oauth_providers} -->
<a href="{url}"><!-- if {urlimage} --><img src="{urlimage}" alt=""> <!-- end if {urlimage} -->{i18n:Continue with}: {name}</a>
<!-- end foreach {oauth_providers} -->
```

- `<!-- captcha -->` is removed when the captcha is disabled, `<!-- rememberlogin -->` when
  `remember_login` is off, `<!-- register -->` when the registration is disabled,
  `<!-- loginerror -->` when there is no error.
- `{oauth_providers}` lists the enabled external login providers: print only `{name}`, `{url}`
  and `{urlimage}` (each entry is the provider's record).
- A theme can also put the login form in `template.tp.html`, between
  `<!-- include FN_HtmlLoginForm -->` and `<!-- end include FN_HtmlLoginForm -->` (see the
  `bootstrap5` theme): the same variables and parts apply.

`register.tp.html` is a form layout (the inputs are generated by the form engine) with a
captcha part and a conditions part. Use a layout without tables, so that the form fits small
screens:

```html
<form enctype="multipart/form-data" action="{formaction}" method="post" name="register">
    <!-- contents -->
    <!-- group --><div class="group">{groupname}</div><!-- end_group -->
    <!-- item -->
    <div class="field">
        <label for="{fieldname}">{title}<!-- error --><span class="error">{error}</span><!-- end_error --></label>
        <!-- inputattributes:class="form-control" -->{input}
    </div>
    <!-- end_item -->
    <!-- endgroup --><!-- end_endgroup -->
    <!-- end_contents -->
    <!-- conditions --><div class="field">{htmlconditions}</div><!-- endconditions -->
    <!-- captcha -->
    <div class="field">{htmlcaptcha} <span class="error">{txt_error_security_code}</span></div>
    <!-- endcaptcha -->
    <input name="updateuser" value="1" type="hidden">
    <button type="submit">{i18n:Save}</button>
    <button type="button" onclick="window.location = '{urlcancel}'">{i18n:Cancel}</button>
</form>
```

- In `register.tp.html` the closing markers are `<!-- endcaptcha -->` and `<!-- endconditions -->`
  (without space).
- `<!-- inputattributes:... -->` gives attributes to every input; `<!-- inputattributes <type>:... -->`
  only to the fields of that form type (for example `radio` or `check`).
- Instead of `{htmlconditions}` the template can loop over `{conditions}` (`{title}`, `{text}`,
  `{name}`, `{checked}`, `{required}`, `{error}`, `{title_accept}`).

The values of a template come from the code that renders it. Neither `FN_RewriteLink()` nor the
template engine escape anything: when a template of the theme shows a value taken from the
request, escape it (see [Escaping](#escaping)).

### Theme Configuration
The `config.php` file in the theme folder declares the options of the theme. Each option is a
`$config` line with its default value, preceded by a comment with its label; the options in braces
become a select:

```php
<?php
//[i18n]show search form {1=YES,0=NO}
$config['show_search_form'] = "1";
//[i18n]footer text
$config['footer_text'] = "";
```

The options are edited in the control center (Global settings > Look and feel); the values saved
there are kept in the database, the file holds only the defaults. Every option is a variable of
`template.tp.html`:

```html
<!-- if {show_search_form} -->
<form method="post" action="{siteurl}index.php?mod=search">...</form>
<!-- end if {show_search_form} -->
```

PHP code goes in `theme.php`, which FINIS includes on every page, before the page is built. It
can set `$_FN` values (every string of `$_FN` is a template variable) and redefine the HTML
functions that FINIS defines only when missing: `FN_HtmlMenu()`, `FN_HtmlNavbar()`,
`FN_HtmlLanguages()`, `FN_HtmlCredits()`, `FN_HtmlModalWindow()`, `FN_HtmlMainteanceMode()`,
`FN_HtmlHeader()`.

```php
<?php
global $_FN;
// {html_dir} in template.tp.html: <html lang="{lang}" dir="{html_dir}">
$_FN['html_dir'] = in_array($_FN['lang'], array("ar", "he")) ? "rtl" : "ltr";
```

## Offline Copies and Ajax Fragments
Two parameters of `index.php` give parts of a page, for the ajax navigation and for the service
worker of an application that keeps pages for offline use:

- `fnajax=1`: only the section, without the theme, in a minimal HTML page whose `<title>` is the
  one of the full page. The dbview lists use it for their ajax navigation.
- `fnshell=1`: the page with the theme, and the comment `<!--fnsection-->` in place of the
  section, which is not generated. A worker puts a fragment (`fnajax=1`) inside this shell.

Every page also has the headers `X-Finis-Visitor` (`public` when the page is the same for every
anonymous visitor, `private` for a logged user or a POST), `X-Finis-Section` and `X-Finis-Lang`.

For the shell to work, a theme must:

- print the section only through `<!-- include section --><!-- end include section -->`: the
  marker `<!--fnsection-->` is placed there;
- keep the content of the page inside the section: a worker reuses the shell of a section (and
  language) for all its pages, so what the theme prints around the section should depend only on
  them;
- use `{formaction}` in its login and logout forms: FINIS removes `fnshell` and `fnajax` from it,
  so the form posts to the real page.

## Integrated CSS Frameworks
FINIS supports various popular CSS frameworks. Here's how to integrate Bootstrap:

### Theme with Bootstrap 5
FINIS ships two Bootstrap 5 themes in `src/themes/`: `default` (Bootstrap files in the theme
folder, side menu) and `bootstrap5` (Bootstrap from a CDN, top menu, login form in the navbar).
They are the quickest start: copy one of them into the `themes/` folder of the site under a new
name and change it. To build one from scratch:

1. Create a new folder: `themes/mybootstrap/`
2. Download Bootstrap files and place them in `themes/mybootstrap/css/` and `themes/mybootstrap/js/`
3. Create the main template using Bootstrap classes, linking the files with relative paths
   (`css/bootstrap.min.css`)

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
3. Use the `dir="rtl"` attribute on the html tag, for example with a `$_FN['html_dir']` set in
   `theme.php` (see [Theme Configuration](#theme-configuration))

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
1. **Theme not loading**: Verify the folder is named correctly and matches the value of `$_FN['theme']`, in the `themes/` folder of the site or in `src/themes/`; otherwise FINIS uses the default theme, then `base`
2. **Template variables not replaced**: Check the syntax, must be exactly `{variable_name}`; a variable that does not exist stays in the page as written
3. **CSS/JS files not loading**: Check paths and ensure files exist; relative paths start from the theme folder
4. **Changes to a theme of `src/themes/` not visible**: the site uses its own copy in `themes/<name>/` (see [Where Themes Are Looked Up](#where-themes-are-looked-up))

### Theme Debug
Put `{listvars}` in a template, temporarily: it prints the variables used by that template with
their values.

## Activating the Theme
To activate the theme, modify the configuration file or use the administration panel
(Global settings > Look and feel) to set:
```php
$_FN['theme'] = 'mytheme';
```

The control center lists the themes of both folders (site and FINIS), shows a preview and applies
the chosen one. The preview is the site rendered with `index.php?themepreview=<name>`: it changes
the theme of that request only, for the administrators (for everybody when `switchtheme` is on).
With `switchtheme` on, a visitor can also choose a theme with
`index.php?change_theme=1&theme=<name>`, kept in a cookie; with `switchtheme` off the parameter
is ignored. `theme` and `themepreview` accept only the name of a folder of `themes/` (letters,
digits, `_`, `-`, `.`): a path or an unknown name gives the default theme.
