# Guida ai Temi di FINIS Framework

## Introduzione
Questa guida fornisce le informazioni necessarie per creare, personalizzare e gestire temi grafici per il framework FINIS. Il sistema di temi permette di modificare completamente l'aspetto del sito senza alterare la struttura dei contenuti.

## Struttura dei Temi

### Posizione e Organizzazione
I temi sono contenuti nella cartella `themes/` e ogni tema ha la propria sottocartella. Un tema tipico contiene:

```
themes/
  └── nomedeltuotema/
      ├── template.tp.html   # Template principale
      ├── form.tp.html       # Template per i form
      ├── grid.tp.html       # Template per le griglie
      ├── view.tp.html       # Template per la visualizzazione
      ├── config.php         # Configurazioni del tema
      ├── css/               # Fogli di stile
      │   ├── style.css      # Stile principale
      │   └── ...            # Altri CSS
      ├── js/                # JavaScript
      │   └── ...
      └── img/               # Immagini del tema
          ├── logo.png
          └── ...
```

### Tema Principale
Il file fondamentale di ogni tema è `template.tp.html`, che definisce la struttura HTML di base del sito.

## Creazione di un Tema Base

### Creazione della Struttura
1. Crea una nuova cartella in `themes/` (es. `themes/mytheme/`)
2. Crea le sottocartelle necessarie: `css/`, `js/`, `img/`
3. Crea i file template principali

### Template Principale
Il file `template.tp.html` definisce la struttura generale della pagina. Ecco un esempio base:

```html
<!DOCTYPE html>
<html lang="{lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{site_title}</title>
    
    <!-- CSS personalizzati -->
    <link rel="stylesheet" href="{siteurl}themes/mytheme/css/style.css">
    
    <!-- CSS di sistema -->
    {css}
    
    <!-- JavaScript di sistema -->
    {javascript}
    
    <!-- Testa personalizzata -->
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
    
    <!-- JavaScript aggiuntivi -->
    <script src="{siteurl}themes/mytheme/js/script.js"></script>
    {footer_append}
</body>
</html>
```

### Template per Form
Il file `form.tp.html` definisce come vengono renderizzati i form nel sistema:

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

### Template per Griglia
Il file `grid.tp.html` definisce come vengono visualizzate le tabelle e le griglie di dati:

```html
<div class="table-responsive">
    <!-- if {title} -->
    <h2 class="grid-title">{title}</h2>
    <!-- end if {title} -->
    
    <!-- if {actions} -->
    <div class="grid-actions mb-3">
        <!-- foreach {actions} -->
        <a href="{url}" class="btn {class}" title="{title}">{label}</a>
        <!-- end foreach {actions} -->
    </div>
    <!-- end if {actions} -->
    
    <!-- if {filters} -->
    <div class="grid-filters mb-3">
        <form action="{filter_action}" method="get">
            <!-- foreach {hidden_params} -->
            <input type="hidden" name="{name}" value="{value}">
            <!-- end foreach {hidden_params} -->
            
            <div class="row">
                <!-- foreach {filters} -->
                <div class="col-md-3 mb-2">
                    <label>{label}</label>
                    {html}
                </div>
                <!-- end foreach {filters} -->
                
                <div class="col-md-3 mb-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">{i18n:Filter}</button>
                    <a href="{reset_url}" class="btn btn-secondary ms-2">{i18n:Reset}</a>
                </div>
            </div>
        </form>
    </div>
    <!-- end if {filters} -->
    
    <!-- if {records} -->
    <table class="table table-hover">
        <thead>
            <tr>
                <!-- foreach {headers} -->
                <th class="{class}">
                    <!-- if {sortable} -->
                    <a href="{sort_url}" class="sort-link {sort_class}">
                        {label}
                    </a>
                    <!-- end if {sortable} -->
                    <!-- if not {sortable} -->
                    {label}
                    <!-- end if not {sortable} -->
                </th>
                <!-- end foreach {headers} -->
            </tr>
        </thead>
        <tbody>
            <!-- foreach {records} -->
            <tr class="{row_class}">
                <!-- foreach {cells} -->
                <td class="{class}">{content}</td>
                <!-- end foreach {cells} -->
            </tr>
            <!-- end foreach {records} -->
        </tbody>
    </table>
    
    <!-- if {pagination} -->
    <div class="grid-pagination">
        <div class="pagination-info">
            {pagination.info}
        </div>
        <div class="pagination-links">
            {pagination.html}
        </div>
    </div>
    <!-- end if {pagination} -->
    <!-- end if {records} -->
    
    <!-- if not {records} -->
    <div class="alert alert-info">
        {i18n:No records found}
    </div>
    <!-- end if not {records} -->
</div>
```

### Template per Visualizzazione
Il file `view.tp.html` definisce come vengono visualizzati i dettagli di un singolo elemento:

```html
<div class="card">
    <div class="card-header">
        <!-- if {title} -->
        <h2 class="view-title">{title}</h2>
        <!-- end if {title} -->
        
        <!-- if {actions} -->
        <div class="view-actions">
            <!-- foreach {actions} -->
            <a href="{url}" class="btn {class}" title="{title}">{label}</a>
            <!-- end foreach {actions} -->
        </div>
        <!-- end if {actions} -->
    </div>
    
    <div class="card-body">
        <!-- foreach {fields} -->
        <div class="view-field {class}">
            <strong>{label}:</strong>
            <div class="field-value">{value}</div>
        </div>
        <!-- end foreach {fields} -->
        
        <!-- if {related_data} -->
        <div class="related-data mt-4">
            <!-- foreach {related_data} -->
            <div class="related-section">
                <h3 class="related-title">{title}</h3>
                {content}
            </div>
            <!-- end foreach {related_data} -->
        </div>
        <!-- end if {related_data} -->
    </div>
</div>
```

### Foglio di Stile Base
Crea un file `css/style.css` con le regole di stile base:

```css
/* Reset e Base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 16px;
    line-height: 1.5;
    color: #333;
    background-color: #f8f9fa;
}

.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

/* Header */
header {
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 15px 0;
}

header .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo img {
    max-height: 50px;
}

.main-menu {
    display: flex;
    gap: 20px;
}

.main-menu a {
    text-decoration: none;
    color: #333;
    font-weight: 500;
}

.main-menu a:hover,
.main-menu a.active {
    color: #007bff;
}

.user-menu {
    font-size: 14px;
}

.user-menu a {
    color: #555;
    text-decoration: none;
}

.user-menu a:hover {
    color: #007bff;
}

/* Main Content */
main {
    padding: 30px 0;
}

.content-wrapper {
    display: flex;
    margin: 20px 0;
}

.main-content {
    flex: 1;
    background-color: #fff;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sidebar-left,
.sidebar-right {
    width: 250px;
    padding: 0 20px;
}

.sidebar-left {
    padding-right: 20px;
}

.sidebar-right {
    padding-left: 20px;
}

/* Typography */
h1 {
    font-size: 2em;
    margin-bottom: 20px;
    color: #333;
}

h2 {
    font-size: 1.5em;
    margin: 15px 0;
    color: #444;
}

h3 {
    font-size: 1.2em;
    margin: 10px 0;
    color: #555;
}

p {
    margin-bottom: 15px;
}

/* Links */
a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

/* Form Elements */
.form-group {
    margin-bottom: 15px;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.btn {
    display: inline-block;
    padding: 8px 16px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    text-decoration: none;
}

.btn:hover {
    background-color: #0069d9;
    text-decoration: none;
}

.btn-secondary {
    background-color: #6c757d;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

/* Tables */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.table tr:hover {
    background-color: #f5f5f5;
}

/* Footer */
footer {
    background-color: #343a40;
    color: #fff;
    padding: 30px 0;
    margin-top: 30px;
}

footer .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.footer-menu {
    display: flex;
    gap: 20px;
}

.footer-menu a {
    color: #adb5bd;
    text-decoration: none;
}

.footer-menu a:hover {
    color: #fff;
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 991px) {
    .content-wrapper {
        flex-direction: column;
    }
    
    .sidebar-left,
    .sidebar-right {
        width: 100%;
        padding: 0 0 20px 0;
    }
}

@media (max-width: 767px) {
    header .container {
        flex-direction: column;
    }
    
    .logo {
        margin-bottom: 15px;
    }
    
    .main-menu {
        margin-bottom: 15px;
    }
    
    footer .container {
        flex-direction: column;
    }
    
    .copyright {
        margin-bottom: 15px;
    }
}
```

## Variabili di Template
FINIS utilizza un sistema di template che permette di inserire variabili e logica condizionale nei template HTML.

### Variabili Principali
- `{sitename}`: Nome del sito
- `{siteurl}`: URL base del sito
- `{title}`: Titolo della pagina corrente
- `{site_title}`: Titolo completo del sito (spesso sitename + titolo pagina)
- `{lang}`: Codice lingua corrente
- `{css}`: CSS di sistema
- `{javascript}`: JavaScript di sistema
- `{head}`: Contenuto aggiuntivo per l'header
- `{header_append}`: Script o stili aggiunti dai moduli
- `{footer_append}`: Script aggiunti dai moduli in fondo alla pagina
- `{user}`: Nome utente corrente (vuoto se non loggato)
- `{year}`: Anno corrente
- `{url_avatar}`: URL dell'avatar utente
- `{urllogin}`: URL per il login
- `{urllogout}`: URL per il logout
- `{urlprofile}`: URL della pagina profilo
- `{urlregister}`: URL per la registrazione

### Variabili per i Blocchi
- `{blocks_top}`: Blocchi posizionati in alto
- `{blocks_left}`: Blocchi posizionati a sinistra
- `{blocks_right}`: Blocchi posizionati a destra
- `{blocks_bottom}`: Blocchi posizionati in basso

Per ogni blocco sono disponibili:
- `{blocktitle}`: Titolo del blocco
- `{html}`: Contenuto HTML del blocco

### Variabili Menu
- `{menuitems}`: Voci del menu principale
  - `{title}`: Titolo voce menu
  - `{link}`: URL della voce menu
  - `{id}`: ID della voce menu
  - `{active}`: Se la voce è attiva
  - `{accesskey}`: Accesskey della voce
  - `{havechilds}`: Se la voce ha sottovoci
  - `{childs}`: Array di sottovoci

### Variabili Multilingua
- `{is_multilanguage}`: Se il sito è multilingua
- `{sitelanguages}`: Lista delle lingue disponibili
  - `{langname}`: Codice lingua
  - `{langtitle}`: Nome lingua
  - `{langflag}`: Bandiera della lingua

### Logica Condizionale
```html
<!-- if {user} -->
    <!-- Contenuto per utenti loggati -->
<!-- end if {user} -->

<!-- if not {user} -->
    <!-- Contenuto per utenti non loggati -->
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

### Cicli
```html
<!-- foreach {menuitems} -->
<li class="nav-item">
    <a href="{link}" class="nav-link <!-- if {active} -->active<!-- end if {active} -->">
        {title}
    </a>
</li>
<!-- end foreach {menuitems} -->
```

## Personalizzazione dei Temi

### Sovrascrittura dei File di un Modulo
È possibile personalizzare i template di un modulo creando la stessa struttura di file all'interno della cartella del tema:

```
themes/mytheme/modules/login/login.tp.html
```

Questo file sovrascriverà il file originale `modules/login/login.tp.html`.

### Configurazione del Tema
Il file `config.php` nella cartella del tema permette di definire opzioni specifiche:

```php
<?php
global $_FN;

// Configurazioni del tema
$theme_config = array(
    'name' => 'My Theme',
    'author' => 'Your Name',
    'version' => '1.0',
    'description' => 'A beautiful responsive theme for FINIS',
    'support_responsive' => true,
    'support_blocks_positions' => array('top', 'left', 'right', 'bottom'),
    'default_blocks_position' => 'right'
);

// Impostazioni di tema personalizzate
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

// Funzioni specifiche del tema
function theme_get_menu() {
    global $_FN;
    // Logica personalizzata per generare il menu
    return $menu_html;
}

// Aggiungi CSS e JS personalizzati
$_FN['header_append'] .= '
<link rel="stylesheet" href="' . $_FN['siteurl'] . 'themes/' . $_FN['theme'] . '/css/custom.css">
<script src="' . $_FN['siteurl'] . 'themes/' . $_FN['theme'] . '/js/custom.js" defer></script>
';
```

## Framework CSS Integrati
FINIS supporta vari framework CSS popolari. Ecco come integrare Bootstrap:

### Tema con Bootstrap 5
1. Crea una nuova cartella: `themes/bootstrap5/`
2. Scarica i file di Bootstrap e posizionali in `themes/bootstrap5/css/` e `themes/bootstrap5/js/`
3. Crea il template principale utilizzando le classi Bootstrap:

```html
<!DOCTYPE html>
<html lang="{lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{site_title}</title>
    
    <!-- Bootstrap CSS -->
    <link href="{siteurl}themes/bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- CSS personalizzati -->
    <link rel="stylesheet" href="{siteurl}themes/bootstrap5/css/style.css">
    
    <!-- CSS di sistema -->
    {css}
    
    <!-- JavaScript di sistema -->
    {javascript}
    
    <!-- Testa personalizzata -->
    {head}
    {header_append}
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="{siteurl}">
                    <img src="{siteurl}themes/bootstrap5/img/logo.png" alt="{sitename}" height="30">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <!-- foreach {menuitems} -->
                        <!-- if {havechilds} -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbar{id}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {title}
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbar{id}">
                                <!-- foreach {childs} -->
                                <li><a class="dropdown-item" href="{link}">{title}</a></li>
                                <!-- end foreach {childs} -->
                            </ul>
                        </li>
                        <!-- end if {havechilds} -->
                        <!-- if not {havechilds} -->
                        <li class="nav-item">
                            <a class="nav-link <!-- if {active} -->active<!-- end if {active} -->" href="{link}" accesskey="{accesskey}">
                                {title}
                            </a>
                        </li>
                        <!-- end if not {havechilds} -->
                        <!-- end foreach {menuitems} -->
                    </ul>
                    <div class="d-flex">
                        <!-- if {user} -->
                            <a href="{urlprofile}" class="btn btn-outline-light me-2">{user}</a>
                            <a href="{urllogout}" class="btn btn-outline-light">{i18n:Logout}</a>
                        <!-- end if {user} -->
                        <!-- if not {user} -->
                            <a href="{urllogin}" class="btn btn-outline-light me-2">{i18n:Login}</a>
                            <a href="{urlregister}" class="btn btn-outline-light">{i18n:Register}</a>
                        <!-- end if not {user} -->
                    </div>
                </div>
            </div>
        </nav>
    </header>
    
    <main class="py-4">
        <div class="container">
            <!-- if {blocks_top} -->
                <div class="row mb-4">
                    <div class="col-12">
                        <!-- foreach {blocks_top} -->
                        <div class="card mb-3">
                            <!-- if {blocktitle} -->
                            <div class="card-header">
                                <h3 class="card-title">{blocktitle}</h3>
                            </div>
                            <!-- end if {blocktitle} -->
                            <div class="card-body">
                                {html}
                            </div>
                        </div>
                        <!-- end foreach {blocks_top} -->
                    </div>
                </div>
            <!-- end if {blocks_top} -->
            
            <div class="row">
                <!-- if {blocks_left} -->
                    <div class="col-lg-3 mb-4 mb-lg-0">
                        <!-- foreach {blocks_left} -->
                        <div class="card mb-3">
                            <!-- if {blocktitle} -->
                            <div class="card-header">
                                <h3 class="card-title">{blocktitle}</h3>
                            </div>
                            <!-- end if {blocktitle} -->
                            <div class="card-body">
                                {html}
                            </div>
                        </div>
                        <!-- end foreach {blocks_left} -->
                    </div>
                <!-- end if {blocks_left} -->
                
                <div class="col-lg-<!-- if {blocks_left} && {blocks_right} -->6<!-- end if {blocks_left} && {blocks_right} --><!-- if ({blocks_left} && !{blocks_right}) || (!{blocks_left} && {blocks_right}) -->9<!-- end if ({blocks_left} && !{blocks_right}) || (!{blocks_left} && {blocks_right}) --><!-- if !{blocks_left} && !{blocks_right} -->12<!-- end if !{blocks_left} && !{blocks_right} -->">
                    <div class="card">
                        <div class="card-header">
                            <h1 class="h3 mb-0">{title}</h1>
                        </div>
                        <div class="card-body">
                            <!-- if {path} -->
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <!-- foreach {path_items} -->
                                        <li class="breadcrumb-item <!-- if {active} -->active<!-- end if {active} -->">
                                            <!-- if not {active} -->
                                            <a href="{link}">{title}</a>
                                            <!-- end if not {active} -->
                                            <!-- if {active} -->
                                            {title}
                                            <!-- end if {active} -->
                                        </li>
                                        <!-- end foreach {path_items} -->
                                    </ol>
                                </nav>
                            <!-- end if {path} -->
                            
                            <!-- include section -->
                            
                            <!-- end include section -->
                        </div>
                    </div>
                </div>
                
                <!-- if {blocks_right} -->
                    <div class="col-lg-3 mt-4 mt-lg-0">
                        <!-- foreach {blocks_right} -->
                        <div class="card mb-3">
                            <!-- if {blocktitle} -->
                            <div class="card-header">
                                <h3 class="card-title">{blocktitle}</h3>
                            </div>
                            <!-- end if {blocktitle} -->
                            <div class="card-body">
                                {html}
                            </div>
                        </div>
                        <!-- end foreach {blocks_right} -->
                    </div>
                <!-- end if {blocks_right} -->
            </div>
            
            <!-- if {blocks_bottom} -->
                <div class="row mt-4">
                    <div class="col-12">
                        <!-- foreach {blocks_bottom} -->
                        <div class="card mb-3">
                            <!-- if {blocktitle} -->
                            <div class="card-header">
                                <h3 class="card-title">{blocktitle}</h3>
                            </div>
                            <!-- end if {blocktitle} -->
                            <div class="card-body">
                                {html}
                            </div>
                        </div>
                        <!-- end foreach {blocks_bottom} -->
                    </div>
                </div>
            <!-- end if {blocks_bottom} -->
        </div>
    </main>
    
    <footer class="footer mt-auto py-3 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; {year} {sitename}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-menu">
                        <!-- foreach {menuitems} -->
                        <a href="{link}" class="text-white me-3">{title}</a>
                        <!-- end foreach {menuitems} -->
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="{siteurl}themes/bootstrap5/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript aggiuntivi -->
    <script src="{siteurl}themes/bootstrap5/js/script.js"></script>
    {footer_append}
</body>
</html>
```

4. Crea il file `config.php` per personalizzare le funzionalità del tema:

```php
<?php
global $_FN;

// Configurazioni del tema
$theme_config = array(
    'name' => 'Bootstrap 5 Theme',
    'author' => 'Your Name',
    'version' => '1.0',
    'description' => 'A responsive theme using Bootstrap 5',
    'support_responsive' => true
);

// Aggiungi metadati Bootstrap
$_FN['header_append'] .= '
<meta name="description" content="' . $_FN['sitename'] . ' - ' . $_FN['title'] . '">
<meta name="author" content="' . $theme_config['author'] . '">
';

// Imposta variabili per template
if (!isset($_FN['tp']['path_items']) && isset($_FN['path'])) {
    // Converti il percorso in formato comprensibile per Bootstrap
    $_FN['tp']['path_items'] = array();
    // Logica per convertire il percorso
}
```

## Responsive Design
Per assicurarsi che il tema sia responsive:

1. Usa sempre viewport meta tag:
```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```

2. Utilizza unità relative (%, em, rem) invece di pixel quando possibile
3. Implementa media query per adattare il layout a diverse dimensioni dello schermo:
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
    
    .sidebar-left,
    .sidebar-right {
        width: 200px;
    }
}

/* Mobile */
@media (max-width: 767px) {
    .container {
        width: 100%;
        padding: 0 10px;
    }
    
    .content-wrapper {
        flex-direction: column;
    }
    
    .sidebar-left,
    .sidebar-right {
        width: 100%;
        padding: 0;
        margin-bottom: 20px;
    }
}
```

4. Usa Flexbox o Grid CSS per layout responsive:
```css
.content-wrapper {
    display: flex;
    flex-wrap: wrap;
}

.main-content {
    flex: 1 1 0;
    min-width: 0;
}

@media (max-width: 767px) {
    .content-wrapper {
        flex-direction: column;
    }
}
```

## Ottimizzazione delle Performance

### Minimizzazione delle Risorse
1. Minimizza CSS e JavaScript:
```php
// In config.php
$_FN['header_append'] .= '<link rel="stylesheet" href="' . $_FN['siteurl'] . 'themes/' . $_FN['theme'] . '/css/style.min.css">';
```

2. Ottimizza le immagini prima di includerle nel tema
3. Usa l'attributo defer per JavaScript non critico:
```html
<script src="{siteurl}themes/mytheme/js/script.js" defer></script>
```

### Caricamento Asincrono
Per risorse non critiche, usa il caricamento asincrono:
```html
<script src="{siteurl}themes/mytheme/js/analytics.js" async></script>
```

## Supporto RTL (Right-to-Left)
Per supportare lingue RTL come l'arabo o l'ebraico:

1. Crea un foglio di stile dedicato: `css/rtl.css`
2. Aggiungi la logica per caricarlo quando necessario:
```php
// In config.php
$rtl_languages = array('ar', 'he');
if (in_array($_FN['lang'], $rtl_languages)) {
    $_FN['header_append'] .= '<link rel="stylesheet" href="' . $_FN['siteurl'] . 'themes/' . $_FN['theme'] . '/css/rtl.css">';
    // Aggiungi l'attributo dir="rtl" al tag html
    $_FN['html_dir'] = 'rtl';
}
```

3. Nel template principale:
```html
<html lang="{lang}" dir="{html_dir|default:'ltr'}">
```

4. Esempio di regole RTL:
```css
/* rtl.css */
body {
    direction: rtl;
    text-align: right;
}

.sidebar-left {
    float: right;
    margin-right: 0;
    margin-left: 20px;
}

.sidebar-right {
    float: left;
    margin-left: 0;
    margin-right: 20px;
}
```

## Best Practices

### Organizzazione del Codice
- Mantieni CSS, JS e immagini in cartelle separate
- Usa un metodo di denominazione coerente per file e classi
- Commenta il codice per migliorare la manutenibilità
- Separa la struttura (HTML), la presentazione (CSS) e il comportamento (JS)

### Compatibilità
- Testa su diversi browser (Chrome, Firefox, Safari, Edge)
- Testa su diverse dimensioni di schermo
- Verifica che il sito funzioni senza JavaScript abilitato

### Accessibilità
- Usa tag semantici HTML5 (`header`, `nav`, `main`, `footer`, ecc.)
- Aggiungi attributi ARIA quando necessario
- Assicurati che il contrasto dei colori sia sufficiente
- Fornisci testi alternativi per le immagini

### Sicurezza
- Non utilizzare direttamente JavaScript inline nel template
- Evita di esporre dati sensibili nei template
- Sanifica tutti gli output per prevenire attacchi XSS

## Risoluzione dei Problemi

### Errori Comuni
1. **Il tema non viene caricato**: Verifica che la cartella sia nominata correttamente e corrisponda al valore di `$_FN['theme']`
2. **Le variabili di template non sono sostituite**: Controlla la sintassi, deve essere esattamente `{variable_name}`
3. **I file CSS/JS non sono caricati**: Controlla i percorsi e assicurati che i file esistano

### Debug del Tema
```php
// In config.php, aggiungi temporaneamente:
echo "<!-- Theme Debug: " . $_FN['theme'] . " loaded -->";
print_r($_FN['tp']); // Mostra tutte le variabili di template
```

## Esempio Pratico: Creazione di un Tema Personalizzato

### 1. Creazione del File di Configurazione
```php
<?php
// themes/custom/config.php
global $_FN;

// Configurazioni
$theme_config = array(
    'name' => 'Custom Theme',
    'author' => 'Your Name',
    'version' => '1.0',
    'support_responsive' => true
);

// Carica CSS e JS personalizzati
$_FN['header_append'] .= '
<link rel="stylesheet" href="' . $_FN['siteurl'] . 'themes/custom/css/custom.css">
<script src="' . $_FN['siteurl'] . 'themes/custom/js/custom.js" defer></script>
';

// Funzione per formattare il menu
function custom_format_menu() {
    // Implementazione del menu personalizzato
    return $html;
}

// Imposta variabili personalizzate
$_FN['tp']['custom_menu'] = custom_format_menu();
$_FN['tp']['current_year'] = date('Y');
```

### 2. Creazione del Template Principale
```html
<!-- themes/custom/template.tp.html -->
<!DOCTYPE html>
<html lang="{lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{site_title}</title>
    <link rel="stylesheet" href="{siteurl}themes/custom/css/custom.css">
    {css}
    {javascript}
    {head}
    {header_append}
</head>
<body>
    <div class="site-wrapper">
        <header>
            <!-- Header personalizzato -->
        </header>
        
        <main>
            <!-- Contenuto principale -->
            <!-- include section -->
            
            <!-- end include section -->
        </main>
        
        <footer>
            <!-- Footer personalizzato -->
        </footer>
    </div>
    
    {footer_append}
</body>
</html>
```

### 3. Personalizzazione per Moduli Specifici
```html
<!-- themes/custom/modules/login/login.tp.html -->
<div class="custom-login-form">
    <!-- Form di login personalizzato -->
</div>
```

### 4. Attivazione del Tema
Per attivare il tema, modifica il file di configurazione o usa il pannello di amministrazione per impostare:
```php
$_FN['theme'] = 'custom';
```