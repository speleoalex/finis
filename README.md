# FINIS Framework and CMS

F.I.N.I.S.: Flatnux Is Now Infinitely Scalable

## Installation instructions (C.M.S.):

1. Copy `include/config.vars.local.php.sample` to `config.vars.local.php`
2. Upload all files to your website folder
3. Go to `http://[your website]/`
4. Run the wizard

## Documentation

FINIS includes comprehensive documentation in the `/doc` folder:

- [Installation Manual](doc/manuale_installazione.md) - Detailed installation instructions
- [Developer Guide](doc/manuale_sviluppatore.md) - Guide for developing with FINIS
- [Theme Guide](doc/guida_temi.md) - How to create and customize themes
- [Administration Manual](doc/manuale_amministrazione.md) - Managing a FINIS website
- [Module Guide](doc/guida_moduli.md) - Creating custom modules
- [Database Guide](doc/guida_database.md) - Working with the database abstraction layer
- [Migration Guide](doc/guida_migrazione.md) - Migrating between versions
- [API Documentation](doc/documentazione_api.md) - Complete API reference

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
// Funzione per ottenere la data e l'ora correnti
function getCurrentDateTime() {
    const now = new Date();
    const date = now.toLocaleDateString();
    const time = now.toLocaleTimeString();
    return {date, time};
}

// Funzione per creare il contenuto HTML
function createHtmlContent() {
    const {date, time} = getCurrentDateTime();
    const message = "Benvenuto nel nostro sito!";
    return `
        <h1>${message}</h1>
        <p>Data: ${date}</p>
        <p>Ora: ${time}</p>
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

## Create a complete, configurable website, complete with backend, administration interface and user management
 
```php
<?php
require_once "path_src_finis/FINIS.php";
$FINIS = new FINIS(array("src_application"=> "."));

$FINIS->finis();
```
