# FINIS Framework and CMS

F.I.N.I.S.: Flatnux Is Now Infinitely Scalable

FINIS is a PHP framework and CMS. A site is a folder of sections (pages), modules (section
types) and themes; data is kept in tables described by XML descriptors and stored, by default,
in plain PHP/XML files, so a site runs without a database server. The same code can run as a
complete CMS with its control center, or as a library that an application calls to run its own
sections in PHP, Python or Node.js.

## Features

- **Database layer (xmetadb)**: one API for the `xmlphp` (files, default), `mysql`/MariaDB,
  `sqlite3`, `sqlserver`, `csv` and `serialize` drivers; tables and forms generated from the
  XML descriptors in `misc/fndatabase/`; SQL-like queries with `FN_XMETADBQuery()`.
- **Sections and modules**: one folder per page; section types for static pages, database views
  with search, cards and record sheets (`dbview`), news, login and registration, search, file
  manager, table editor, site map, and PHP, Python 3 and Node.js scripts.
- **Themes and templates**: site and control center themes, `{variables}`, `<!-- if -->` and
  `<!-- foreach -->` blocks, module templates that a theme can override.
- **Multilingual** content and interface (English, Italian, German, French, Spanish).
- **Control center** (`index.php?fnapp=controlcenter`): contents, blocks, settings, themes,
  users and groups, API keys, event log and other utilities.
- **Users and security**: groups and permissions per section, registration with confirmation,
  password recovery, login with external OAuth providers, signed login cookies, bcrypt
  passwords, login attempt limiter, captcha.
- **AI clients**: an MCP server (`index.php?fnapp=mcp`) that lets an AI client (Claude Desktop,
  claude.ai, Claude Code, ...) read and, when allowed, edit the site as a FINIS user, with
  personal API keys or an OAuth 2.1 login.
- **Extensions**: a folder of modules, sections and tables loaded with
  `$FINIS->addExtension()` without copying it into the site (for example the `fncommerce`
  e-commerce extension).

## Requirements

- PHP 7.1 or higher (PHP 8.x supported)
- A web server (Apache with mod_rewrite for friendly URLs; any server for `index.php?...` URLs)
- Optional: MySQL/MariaDB, SQLite 3 or SQL Server; APCu for a faster cache; the `openssl`
  extension to let the administrators show the users' API keys

## Installation (CMS)

1. Upload the content of `src/` to your website folder
2. Rename `index.php.example` to `index.php`
3. To use MySQL instead of the files, copy `config.vars.local.php.mysql.example` to
   `config.vars.local.php` and set the connection data
4. Make the data folder `misc/` writable by the web server
5. Go to `http://[your website]/` and run the wizard
6. The control center is at `http://[your website]/index.php?fnapp=controlcenter`

`config.vars.local.php` holds the settings of the installation (database, `display_errors`,
`trusted_proxies`, `home_section_force`, ...); the defaults are in
`src/include/config.vars.php`. The data folder also gets the site secret
`misc/secret.key.php`: keep it with the backups and never commit it.

See the [Installation Manual](doc/en_installation_manual.md) for the details.

## Examples

The `examples/` folder holds ready-made sites that use the sources in `src/`:

- `examples/website` - a site with the default sections
- `examples/fullcms` - the complete CMS with control center
- `examples/ecommerce` - a shop built on the `fncommerce` extension

## Documentation

FINIS includes comprehensive documentation in the `/doc` folder:

- [Manual](doc/en_manual.md) - General documentation
- [Installation Manual](doc/en_installation_manual.md) - Detailed installation instructions
- [Administration Manual](doc/en_administration_manual.md) - Managing a FINIS website
- [Developer Guide](doc/en_developer_guide.md) - Guide for developing with FINIS
- [Theme Guide](doc/en_theme_guide.md) - How to create and customize themes
- [Database Guide](doc/en_database_guide.md) - Working with the database abstraction layer
- [API Documentation](doc/en_api_documentation.md) - Complete API reference
- [MCP Guide](doc/en_mcp_guide.md) - MCP server, API keys and OAuth login for AI clients
- [Migration Guide](doc/en_migration_guide.md) - Migrating and upgrading
- [Migration from Flatnux](doc/en_migrate_from_flatnux.md) - Guide to migrate from Flatnux
- [LLM Guide](doc/Finis_Framework_LLM_Guide.md) - Reference for AI coding assistants

### Italian Documentation

- [Manuale Generale](doc/it_manuale.md) - Manuale generale
- [Manuale di Installazione](doc/it_manuale_installazione.md) - Istruzioni di installazione
- [Manuale Amministrazione](doc/it_manuale_amministrazione.md) - Gestione di un sito FINIS
- [Manuale Sviluppatore](doc/it_manuale_sviluppatore.md) - Guida per lo sviluppatore
- [Guida Temi](doc/it_guida_temi.md) - Creazione e personalizzazione dei temi
- [Guida Database](doc/it_guida_database.md) - Livello di astrazione del database
- [Documentazione API](doc/it_documentazione_api.md) - Riferimento delle API
- [Guida MCP](doc/it_guida_mcp.md) - Server MCP, chiavi API e login OAuth per i client AI
- [Guida Migrazione](doc/it_guida_migrazione.md) - Migrazione e aggiornamento

## Create a complete, configurable website, with backend, administration interface and user management

```php
<?php
require_once "path_src_finis/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->finis();
```

The application folder (`src_application`) holds its own `sections/`, `themes/`, `modules/`,
`misc/` and `config.vars.local.php`; what it does not have is taken from the FINIS sources.
`$FINIS->finis()` serves the site and the other entry points selected with `?fnapp=`
(`controlcenter`, `api`, `mcp`, `oauthserver`, `thumb`, ...).

### Loading an extension

```php
<?php
require_once "path_src_finis/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("path/to/fncommerce/");
$FINIS->finis();
```

Modules, their configuration files and the control center sections are looked up in the
application, then in FINIS, then in the extensions; at the first install the sections and the
table descriptors of the extensions are copied into the site.

## Creating PHP applications in framework mode

`./sections/home/section.en.html`

```html
<h1>FINIS !!!</h1>
```

`./sections/home/section.php`

```php
<?php
global $_FN;
echo "<h1>FINIS !!!</h1>";
echo $_FN['siteurl'];
```

`example.php`

```php
<?php
require_once "../finis/FINIS.php";
$FINIS = new FINIS();
$FINIS->runSection("home");
```

## Creating Python application in framework mode

`./sections/home/section.py`

```python
import json
import sys

json_data = sys.argv[1]
# Parse the JSON data
FN = json.loads(json_data)
print(f"""
Site url: {FN['siteurl']}
""")
```

`./example.php`

```php
<?php
require_once "../finis/FINIS.php";
$FINIS = new FINIS();
$FINIS->runSection("home");
```

## Creating NodeJS application in framework mode

`./sections/home/section.js`

```js
// Current date and time
function getCurrentDateTime() {
    const now = new Date();
    const date = now.toLocaleDateString();
    const time = now.toLocaleTimeString();
    return {date, time};
}

// HTML content of the page
function createHtmlContent() {
    const {date, time} = getCurrentDateTime();
    const message = "Welcome to our site!";
    return `
        <h1>${message}</h1>
        <p>Date: ${date}</p>
        <p>Time: ${time}</p>
    `;
}
console.log(createHtmlContent());

try {
    const FN = JSON.parse(process.argv[2]);
    console.log("<pre>");
    console.log("Site url:" + FN['siteurl']);
    console.log("User:" + FN['user']);
    console.log("</pre>");
} catch (error) {
    const FN = {};
}
```

## AI clients (MCP)

Every user allowed by the administrators can connect an AI client to the site:

- **API key**: generate it in the profile page, then point the client at
  `https://[your website]/index.php?fnapp=mcp` with the header `Authorization: Bearer <key>`
- **OAuth login**: Claude Desktop and claude.ai connect with the username and password of the
  user, no key to copy

Reading is open to the users with a key; the write tools (edit pages, blocks, users, ...) are
reserved to the administrators and to the group `mcp_write`. Details in the
[MCP Guide](doc/en_mcp_guide.md).
