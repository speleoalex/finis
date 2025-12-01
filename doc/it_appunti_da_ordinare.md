# Installazione
scaricare i sorgenti metterli sul provider
creare eventuale config.vars.local.php
andare all'url dell' installzzione ad esempio ...
seguire la procedura di installazione



## Struttura delle cartelle:




i sorgenti dell'applicazione contengono le seguenti cartelle:

## files framework
config.php                           
FINIS.php
modules/   
themes/
controlcenter/                        
extra/              
images/    
languages/  
plugins/
include/

## files website:
sections/
themes/
config.vars.local.php
index.php


# files contenente i dati:
misc/


Il database è descritto all'interno di files xml dentro misc/fndatabase
Ogni file descrive una tabella, ogni tabella può avere un suo driver in modo che i dati possano essere messi su mysql, files csv, mssql, xmlphp ecc.


esempio descrittore tabella fn_sections:
```
<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <name>id</name>
        <frm_required>1</frm_required>
        <primarykey>1</primarykey>
        <size>128</size>
        <frm_show>0</frm_show>
        <frm_i18n>unique name</frm_i18n>
        <frm_help_i18n>unique name of the page, used to identify the page in a unique way</frm_help_i18n>
    </field>
    <field>
        <name>type</name>
        <frm_i18n>page type</frm_i18n>
        <frm_type>select</frm_type>
        <frm_show>1</frm_show>
        <foreignkey>fn_sectionstypes</foreignkey>
        <fk_link_field>name</fk_link_field>
        <fk_show_field>title</fk_show_field>
        <frm_help_i18n>causes the page to load one of the installed modules, for example, a login page will load the functionality to log in users</frm_help_i18n>
    </field>
    <field>
        <name>parent</name>
        <foreignkey>fn_sections</foreignkey>
        <fk_link_field>id</fk_link_field>
        <fk_show_field>id</fk_show_field>
        <frm_help_i18n>parent page in the site map</frm_help_i18n>
    </field>
    <field>
        <name>position</name>
    </field>
    <field>
        <name>title</name>
        <frm_i18n>title</frm_i18n>
        <type>varchar</type>
        <frm_multilanguages>auto</frm_multilanguages>
    </field>
    <field>
        <name>description</name>
        <frm_i18n>description</frm_i18n>
        <type>varchar</type>
        <frm_multilanguages>auto</frm_multilanguages>
    </field>
    <field>
        <name>startdate</name>
        <frm_i18n>publication start date</frm_i18n>
        <frm_en>Publication start date</frm_en>
        <frm_it>Data inizio pubblicazione</frm_it>
        <frm_type>datetime</frm_type>
        <frm_dateformat>y-mm-dd</frm_dateformat>
    </field>
    <field>
        <name>enddate</name>
        <frm_i18n>publication end date</frm_i18n>
        <frm_type>datetime</frm_type>
        <frm_dateformat>y-mm-dd</frm_dateformat>
    </field>
    <field>
        <name>status</name>
        <frm_i18n>publication status</frm_i18n>
        <frm_type>radio</frm_type>
        <frm_options>1,0</frm_options>
        <frm_options_i18n>published,not published</frm_options_i18n>
    </field>
    <field>
        <name>hidden</name>
        <frm_type>check</frm_type>
        <frm_i18n>page is hidden in menus</frm_i18n>
        <frm_help_i18n>if selected the page does not appear in the menus but will still be accessible via direct link</frm_help_i18n>
    </field>
    <field>
        <name>accesskey</name>
        <frm_i18n>accesskey</frm_i18n>
        <type>varchar</type>
        <size>1</size>
        <frm_size>2</frm_size>
    </field>
    <field>
        <name>keywords</name>
        <frm_i18n>keywords</frm_i18n>
        <type>varchar</type>
        <frm_help_i18n>comma-separated page keywords to optimize indexing in search engines</frm_help_i18n>
    </field>
    <field>
        <name>sectionpath</name>
        <frm_type>varchar</frm_type>
        <frm_show>0</frm_show>
    </field>
    <field>
        <name>level</name>
        <frm_type>select</frm_type>
        <frm_i18n>user level for viewing</frm_i18n>
        <frm_group>permissions</frm_group>
        <frm_group_i18n>permissions</frm_group_i18n>
        <frm_options>,0,1,2,3,4,5,6,7,8,9,10</frm_options>
        <frm_options_i18n>visible to everyone,only registered users,users with at least level 1,users with at least level 2,users with at least level 3,users with at least level 4,users with at least level 5,users with at least level 6,users with at least level 7,users with at least level 8,users with at least level 9,visible only by administrators</frm_options_i18n>                
    </field>
    <field>
        <name>group_view</name>
        <foreignkey>fn_groups</foreignkey>
        <fk_link_field>groupname</fk_link_field>
        <fk_show_field>groupname</fk_show_field>
        <frm_type>multicheck</frm_type>
        <frm_i18n>allow viewing only to these user groups</frm_i18n>
        <frm_help_i18n>if no group is selected, the content will be visible to everyone</frm_help_i18n>
    </field>
    <field>
        <name>group_edit</name>
        <foreignkey>fn_groups</foreignkey>
        <fk_link_field>groupname</fk_link_field>
        <fk_show_field>groupname</fk_show_field>
        <frm_type>multicheck</frm_type>
        <frm_i18n>allow modify to the following user groups</frm_i18n>
        <frm_help_i18n>if no group is selected, then only administrators can edit content</frm_help_i18n>
        <frm_endgroup>permissions</frm_endgroup>
    </field>
    <field>
        <name>blocksmode</name>
        <frm_group>blocks</frm_group>
        <frm_group_i18n>blocks</frm_group_i18n>
        <frm_i18n>view blocks</frm_i18n>
        <frm_type>radio</frm_type>
        <frm_options>,show,hide</frm_options>
        <frm_options_i18n>view all,displays only selected,hide selected</frm_options_i18n>
    </field>
    <field>
        <name>blocks</name>
        <frm_i18n>selected blocks</frm_i18n>
        <foreignkey>fn_blocks</foreignkey>
        <fk_link_field>id</fk_link_field>
        <fk_show_field>title</fk_show_field>
        <frm_type>multicheck</frm_type>
        <frm_endgroup></frm_endgroup>
    </field>    
    <filename>sections</filename>
    <driver>xmlphp</driver>
</tables>



```



i files possono convivere nella stessa cartella, tuttavia è possibile separare i sorgenti del framework da quelli dei siti web
per esempio possiamo avere una cartella finis_src/ e una cartella website/

la index della cartella website avrà una index che include ../finis_src/FINIS.php

index.php:
```
<?php
require_once "FINIS.php";
$FINIS = new FINIS(array("src_application"=> "."));
$FINIS->website();

```



applicazione e sorgenti finis possono o meno corrispondere.


## descrizione cartelle


### sections/ 

contiene la lista delle sezioni, una cartella per sezione.
Per esempio sections/home sarà l'homepage
in modalità website ogni sezione corrisponde ad una pagina web
ogni sezione può essere di tipo diverso, i tipi di sezione sono definiti nella cartella modules/ caricando il file /modules/nome modulo/section.php


### modules/
contiene i tipi di sezione, uno per ogni sottocartella, per esempio 
/modules/login/section.php definisce i sorgenti delle pagine di tipo login

sezioni di tipo finis (default)

se il tipo di sezione non è specificato la sezione sarà di tipo finis.
Ecco un esempio del contenuto di una sezione:

sections/home/section.it.html : template html 
sections/home/section.php : logiche e dizionario da passare al template html

### themes;


esempio di tema per finis website

themes/mytheme/template.tp.html:
```
<!DOCTYPE html>
<html lang="{lang}">
    <head>
        <meta charset="utf-8">
        <title>{site_title}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            /* Modern CSS Reset */
            *, *::before, *::after {
                box-sizing: border-box;
            }
            body, h1, h2, h3, h4, p, figure, blockquote, dl, dd {
                margin: 0;
            }
        </style>
    </head>
    <body>
        <header>
            <div class="container">
                <nav>
                    <img src="logo.png" alt="Logo" class="logo">
                    <button class="menu-toggle" aria-label="Toggle menu">☰</button>
                    <!-- if {menuitems} -->
                    <ul class="menu">
                        <!-- foreach {menuitems} -->
                        <!-- menuitem -->
                        <!-- if not {active} -->
                        <li><a href="{link}" accesskey="{accesskey}" >{title}</a></li>
                        <!-- end if not {active} -->                        
                        <!-- if {active} -->
                        <li class="active"><a href="{link}" accesskey="{accesskey}" class="active">{title}</a></li>
                        <!-- end if {active} -->                        
                        <!-- end foreach {menuitems} -->
                    </ul>
                    <!-- end if {menuitems} -->
                    <div class="languages">
                        <!-- foreach {sitelanguages} -->
                        <!-- langitem -->
                        <a href='{siteurl}?lang={langname}'><img src="{siteurl}/images/flags/{langname}.png" alt="{langtitle}" title="{langtitle}"/></a>
                        <!-- endlangitem -->
                        <!-- end foreach {sitelanguages} -->
                    </div>
                </nav>
            </div>
            <!-- if {blocks_top} -->
            {blocks_top}
            <!-- end if {blocks_top} -->
        </header>
        <main class="container">
            <div class="content">
                <!-- include section -->
                <!-- end include section -->
            </div>
            <aside>
                <!-- foreach {blocks_left} -->
                <section>
                    <h3><!-- if {blocktitle} -->{blocktitle}<!-- end if {blocktitle} --></h3>
                    <div>
                        {blockcontents}
                    </div>
                </section>
                <!-- end foreach {blocks_left} -->
            </aside>
        </main>

        <footer>
            <div class="container">
                <!-- if {blocks_bottom} -->
                {blocks_bottom}
                <!-- end if {blocks_bottom} -->
                {credits}
            </div>
        </footer>

        <script>
            // Toggle menu functionality
            document.addEventListener('DOMContentLoaded', function () {
                const menuToggle = document.querySelector('.menu-toggle');
                const menu = document.querySelector('.menu');

                menuToggle.addEventListener('click', function () {
                    menu.classList.toggle('expanded');
                });
            });
        </script>
    </body>
</html>
```
themes/mytheme/template.tp.html:






# FINIS Framework and CMS

F.I.N.I.S.: Flatnux Is Now Infinitely Scalable

## Installation instructions (C.M.S.):

1. Copy `include/config.vars.local.php.sample` to `config.vars.local.php`
2. Upload all files to your website folder
3. Go to `http://[your website]/`
4. Run the wizard

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


esempio config.vars.local.php:

```
<?php
global $_FN;
//display error, http://php.net/manual/it/errorfunc.configuration.php#ini.display-errors
$_FN['display_errors'] = "on";
//authentication method (include/auth/)
$_FN['default_auth_method'] = "local";
//specific options for the mysql driver:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "finis";
$_FN['xmetadb_mysqlusername'] = "root";
$_FN['xmetadb_mysqlpassword'] = "";


```