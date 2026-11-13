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
      ├── config.php         # Opzioni del tema (modificabili dal pannello di controllo)
      ├── theme.php          # Codice PHP opzionale, incluso a ogni pagina
      ├── modules/           # Template che sostituiscono quelli dei moduli
      │   ├── login/login.tp.html
      │   └── dbview/list.tp.html
      ├── css/               # Fogli di stile
      │   ├── style.css      # Stile principale
      │   └── ...            # Altri CSS
      ├── js/                # JavaScript
      │   └── ...
      └── img/               # Immagini del tema
          ├── logo.png
          └── ...
```

### Dove vengono cercati i temi
Il tema in uso è quello indicato da `$_FN['theme']`. FINIS cerca la sua cartella in due posti:

1. la cartella `themes/` del sito (l'applicazione), per esempio `themes/miotema/`;
2. la cartella `themes/` di FINIS (`src/themes/`: `base`, `default`, `bootstrap5`).

Un tema del sito funziona esattamente come un tema di FINIS: compare nell'elenco del pannello di
controllo, si può vedere in anteprima e applicare (vedi [Attivazione del Tema](#4-attivazione-del-tema)).
Se un file esiste in entrambe le cartelle vince la copia del sito. Se il tema non esiste in
nessuna delle due, FINIS usa il tema predefinito e poi `base`. Le estensioni
(`$FINIS->addExtension()`) aggiungono moduli e sezioni, non temi.

Un tema di FINIS viene copiato nel sito la prima volta che si usa: `template.tp.html` e i file
che collega con un percorso relativo (`href`, `src`, `background`), poi `form.tp.html`,
`grid.tp.html`, `view.tp.html` e i file di `img/` quando si mostra un form o una griglia,
finiscono in `themes/<nome>/` del sito, e da quel momento viene usata la copia del sito. Ne
seguono due cose:

- dopo aver modificato uno di questi file in `src/themes/<nome>/` bisogna aggiornare (o
  cancellare) la copia nel sito;
- i file richiamati solo da un CSS non vengono copiati: collegali anche dal template, oppure
  tieni il tema nella cartella `themes/` del sito.

Alcuni file vengono letti solo dalla copia del tema nel sito: `template.<sezione>.tp.html`
(template principale di una sola sezione, per esempio `template.home.tp.html`) e
`template.type.<modulo>.tp.html` (template principale delle sezioni di un modulo, per esempio
`template.type.dbview.tp.html`). Se presenti sostituiscono `template.tp.html`.

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
    
    <!-- I percorsi relativi partono dalla cartella del tema -->
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

Alcune note sul template principale:

- La sezione (il contenuto della pagina) va dove si trova
  `<!-- include section --><!-- end include section -->`: questo segnaposto va mantenuto, serve
  anche alle copie offline (vedi [Copie offline e frammenti ajax](#copie-offline-e-frammenti-ajax)).
- FINIS aggiunge la propria testata prima di `</head>`: il CSS del modulo e della sezione, gli
  script di `include/javascripts/`, i meta tag e l'output dei file `header.php`. Al tema basta il
  tag `</head>`. Il `<title>` viene sempre sostituito con il titolo della pagina
  (`$_FN['site_title']`, che un modulo può cambiare, per esempio con il titolo di un record).
- `href`, `src` e `background` con un percorso relativo (senza `:` e senza `{`) partono dalla
  cartella del template: `css/style.css` diventa `themes/mytheme/css/style.css`.

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
Il file `grid.tp.html` definisce come vengono visualizzate le tabelle di dati (elenco dei record con
filtri, pagine e azioni). Il punto di partenza più comodo è il `grid.tp.html` del tema
`bootstrap5`; la parte centrale è questa:

```html
<table class="table table-hover">
    <thead>
        <tr>
            <!-- foreach {headers} -->
            <th><a href="{link}">{title}{arrow}</a></th>
            <!-- end foreach {headers} -->
            <th colspan="{numactions}"></th>
        </tr>
    </thead>
    <tbody>
        <!-- foreach {rows} -->
        <tr>
            <!-- foreach {cols} -->
            <td data-title="{fieldtitle}">{fieldvalue}</td>
            <!-- end foreach {cols} -->
            <!-- if {action_view} --><td><a href="{link}">{text}</a></td><!-- end if {action_view} -->
            <!-- if {action_edit} --><td><a href="{link}">{text}</a></td><!-- end if {action_edit} -->
            <!-- if {action_delete} --><td><a href="{link}" onclick="return confirm('{i18n:are you sure you want to delete this content?}')">{text}</a></td><!-- end if {action_delete} -->
        </tr>
        <!-- end foreach {rows} -->
    </tbody>
</table>
```

`{action_view}`, `{action_edit}` e `{action_delete}` sono array: dentro il loro blocco `if`
valgono le loro chiavi (`{link}`, `{text}`, `{html}`). Le pagine sono in `{nav_page_prev}`,
`{nav_pages}` (`{link}`, `{title}`, `{current}`) e `{nav_page_next}`, i filtri in `{filters}`.

### Template per Visualizzazione
Il file `view.tp.html` definisce come vengono visualizzati i dettagli di un singolo record. Come
`form.tp.html`, è diviso in parti marcate da commenti HTML: `<!-- item -->` si ripete per ogni
campo, `<!-- group -->` apre un gruppo di campi.

```html
<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <tbody>
                <!-- contents -->
                <!-- group -->
                <tr><td colspan="2">{groupname}</td></tr>
                <!-- end_group -->
                <!-- item -->
                <tr>
                    <th scope="row">{title}</th>
                    <td>{input}</td>
                </tr>
                <!-- end_item -->
                <!-- endgroup -->
                <!-- end_endgroup -->
                <!-- end_contents -->
            </tbody>
        </table>
        <!-- if {textviewlist} -->
        <a class="btn btn-primary" href="{link_view_list}">{textviewlist}</a>
        <!-- end if {textviewlist} -->
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

### Temi del Pannello di Controllo

I temi del pannello di controllo stanno in `controlcenter/themes/<nome>/` (`classic`, `sidebar`,
`adminkit`) e si scelgono con l'impostazione `controlcenter_theme`. Oltre a `controlcenter.tp.html`,
`form.tp.html`, `grid.tp.html` e `view.tp.html`, un tema può fornire:

- `editconf.tp.html`: l'editor dei file `config.php` (impostazioni del sito, opzioni dei moduli e
  dei temi). Il template cicla su `{items}` (`{id}`, `{title}`, `{htmlinput}`, `{value}`,
  `{defaultvalue}`) e può mostrare `{message_saved}` dopo un salvataggio. Gli input li genera
  FINIS; gli attributi che ricevono si dichiarano nel template come commenti HTML:

```html
<!-- inputattributes:class="form-control" -->              input di testo, password e colore
<!-- inputattributes select:class="form-select" -->
<!-- inputattributes checkbox:class="form-check-input" -->
<!-- inputattributes button:class="btn btn-light btn-sm" --> i link sposta su/giù degli elenchi
```

- `controlcenter/sections/<gruppo>/<sezione>/*.tp.html`: i template di una singola sezione
  (per esempio `users_and_groups/profile/form.tp.html`), trovati con `FNCC_FromTheme()`. Una
  copia personalizzata in `controlcenter/themes/<nome>/` del sito vince su quella di FINIS.

Per `editconf.tp.html` valgono anche queste regole: il template viene usato solo se contiene un
`<form>` (FINIS aggiunge i suoi script e i campi nascosti prima di `</form>`), ha a disposizione
anche `{formaction}` e `{linkcancel}`, e viene letto dalla cartella del tema in FINIS
(`src/controlcenter/themes/<nome>/`).

Dal tema `fncc` in poi vale anche questa convenzione:

- `{cc_assets}` nel `<head>` del template: FINIS ci mette il foglio di stile e lo script comuni
  del pannello (`controlcenter/css/fncc-ui.css` e `controlcenter/js/fncc-ui.js`) e, subito dopo,
  `css/theme.css` e `js/theme.js` del tema, se esistono. I file vengono copiati nella cartella del
  sito e ricopiati quando il sorgente in FINIS e' piu' recente, cosi' un aggiornamento del
  framework arriva anche ai siti gia' installati. Un tema che non usa `{cc_assets}` riceve
  comunque i file comuni, aggiunti prima di `</head>`.
- Il tema non deve quindi richiamare il proprio CSS con un `<link href="css/...">`: i file
  referenziati dentro un template vengono copiati nel sito una volta sola e la copia resterebbe
  indietro.

Il foglio `fncc-ui.css` definisce i colori come variabili CSS (`--fncc-surface`, `--fncc-text`,
`--fncc-primary`, ...) con la versione scura sotto `@media (prefers-color-scheme: dark)` e sotto
`:root[data-fncc-theme="dark"]`. Per cambiare l'aspetto del pannello basta ridefinire quelle
variabili in `css/theme.css`: i componenti delle sezioni (pulsanti, schede, tabelle, albero della
mappa del sito) seguono senza modifiche al PHP.

Il file manager disegna le sue icone con caratteri Unicode (`FN_GetIconCharByFilename()`): un tema
non deve fornire immagini per le icone dei file.

## Variabili di Template
FINIS utilizza un sistema di template che permette di inserire variabili e logica condizionale nei template HTML.

### Variabili Principali
Ogni stringa o numero di `$_FN` è una variabile del template (`{sitename}`, `{siteurl}`, `{lang}`,
`{user}`...); i campi dell'utente collegato sono `{user_<campo>}` e quelli della sezione corrente
`{section_<campo>}`. Le più usate:

- `{sitename}`: Nome del sito
- `{siteurl}`: URL base del sito
- `{site_title}`: Titolo della pagina
- `{site_subtitle}`: Sottotitolo del sito
- `{section_title}`, `{section_description}`: Titolo e descrizione della sezione corrente
- `{lang}`: Codice lingua corrente
- `{user}`: Nome utente corrente (vuoto se non loggato)
- `{url_avatar}`: URL dell'avatar utente
- `{urllogin}`: URL per il login
- `{urllogout}`: URL per il logout
- `{urlprofile}`: URL della pagina profilo
- `{urlregister}`: URL per la registrazione
- `{is_home}`: Vero nella sezione iniziale
- `{url_logo}`: `themes/<tema>/images/logo.png` (o `.svg`) del sito, vuoto se manca
- `{credits}`, `{navbar}` (percorso in HTML), `{hmenu}` (menu in HTML), `{languages}` (bandiere in HTML)
- `{nav}`: Percorso (breadcrumb) come elenco di sezioni (`{title}`, `{link}`, `{active}` sulla corrente)
- `{notifications}`, `{notifications_count}`: Notifiche non ancora mostrate all'utente
- le opzioni del `config.php` del tema (vedi [Configurazione del Tema](#configurazione-del-tema))

Una variabile che non esiste resta nella pagina così come è scritta.

### Variabili per i Blocchi
- `{blocks_top}`: Blocchi posizionati in alto
- `{blocks_left}`: Blocchi posizionati a sinistra
- `{blocks_right}`: Blocchi posizionati a destra

Ogni blocco ha i campi del suo record:
- `{title}`: Titolo del blocco
- `{hidetitle}`: Vero quando il titolo non va mostrato
- `{html}`: Contenuto HTML del blocco

I blocchi si possono stampare anche con un include, che funziona per tutte le posizioni (`top`,
`left`, `right`, `bottom`):

```html
<!-- include blocks_bottom -->
<!-- blockitem -->
<section>
    <!-- blockheader --><h3><!-- blocktitle -->titolo<!-- end blocktitle --></h3><!-- end blockheader -->
    <div><!-- blockcontents -->contenuto<!-- end blockcontents --></div>
</section>
<!-- end blockitem -->
<!-- end include blocks_bottom -->
```

### Variabili Menu
- `{menuitems}`: Voci del menu principale
  - `{title}`: Titolo voce menu
  - `{link}`: URL della voce menu
  - `{id}`: ID della voce menu
  - `{active}`: Se la voce è attiva
  - `{opened}`: Se la sezione corrente sta dentro questa voce
  - `{accesskey}`: Accesskey della voce
  - `{havechilds}`: Numero di sottovoci
  - `{childs}`: Array di sottovoci

Il menu elenca solo le sezioni il cui contenuto l'utente può vedere: per esempio una sezione
dbview riservata ad alcuni gruppi, o l'editor delle tabelle, non compaiono agli altri utenti.

### Variabili Multilingua
- `{is_multilanguage}`: Se il sito è multilingua
- `{current_language}`: La lingua corrente (stessi campi dell'elenco)
- `{sitelanguages}`: Lista delle lingue disponibili
  - `{langname}`: Codice lingua
  - `{langtitle}`: Nome lingua
  - `{langflag}`: Bandiera della lingua (Unicode)
  - `{langimg}`: URL dell'immagine della bandiera

### Traduzioni
`{i18n:testo}` stampa la traduzione di `testo` nella lingua corrente; il testo si scrive in inglese.
La prima lettera segue quella scritta: `{i18n:Login}` dà "Accedi", `{i18n:login}` "accedi".

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
            <!-- if not {hidetitle} -->
            <h3>{title}</h3>
            <!-- end if not {hidetitle} -->
            <div>{html}</div>
        </section>
        <!-- end foreach {blocks_left} -->
    </aside>
<!-- end if {blocks_left} -->
```

La condizione è sempre una sola variabile: `<!-- if {var} -->` oppure `<!-- if not {var} -->`,
chiusi da `<!-- end if {var} -->` o `<!-- end if not {var} -->`. Le espressioni (`&&`, `||`, `!`)
non sono supportate. Una stringa vuota, `"0"`, `false` e un array vuoto valgono falso. Quando la
variabile di un `if` è un array, dentro il blocco le sue chiavi diventano variabili:

```html
<!-- if {navigationbar} -->
    {toolbar_html}
<!-- end if {navigationbar} -->
```

Qui `{toolbar_html}` è una chiave dell'array `{navigationbar}`.

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

I blocchi `if` e `foreach` funzionano in template di qualunque dimensione, purché un singolo blocco
(con i suoi cicli già espansi) resti sotto l'impostazione PHP `pcre.backtrack_limit` (1.000.000 di
caratteri come valore predefinito): per blocchi più grandi, per esempio un elenco di migliaia di
record dentro un solo `if`, va alzata.

### Escape dei valori
Il motore dei template stampa i valori così come sono: non fa nessun escape, e nemmeno
`FN_RewriteLink()`. Un valore che arriva dalla richiesta (`$_GET`, `$_POST`, la query string) va
messo in sicurezza prima di finire in un template, con `FN_GetParam($nome, $_GET, "html")` nel
PHP oppure con un modificatore nel template:

```html
<input name="q" value="{q.addHtmlEntities()}">
```

`{var.addHtmlEntities()}` applica `htmlentities()` al valore (`{var.addSlashes()}` applica
`addslashes()`, che non è un escape HTML). Il `{formaction}` che FINIS passa ai form di login e
logout è già sottoposto a escape.

## Personalizzazione dei Temi

### Sovrascrittura dei File di un Modulo
È possibile personalizzare i template di un modulo creando la stessa struttura di file all'interno della cartella del tema:

```
themes/mytheme/modules/login/login.tp.html
```

Questo file sovrascriverà il file originale `modules/login/login.tp.html`. Un template di modulo
viene cercato (`FN_FromTheme()`) in quest'ordine:

1. `themes/<tema>/modules/<modulo>/<file>` del sito;
2. `src/themes/<tema>/modules/<modulo>/<file>` di FINIS;
3. il file del modulo.

Alcuni moduli danno alla cartella della sezione (`sections/<sezione>/`) la precedenza sul tema:
vedi dbview qui sotto.

### Template del modulo dbview
Il modulo dbview (tabelle del database mostrate come elenchi e schede) ha diversi template. La
barra della scheda (`toolbar.tp.html`) e gli altri template della scheda (`detail.tp.html`,
`formedit.tp.html`, `history.tp.html`, `users.tp.html`) vengono cercati in quest'ordine:

1. `sections/<sezione>/` del sito (vale per una sola sezione);
2. `themes/<tema>/modules/dbview/` (sito, poi FINIS);
3. `src/modules/dbview/`.

Per l'elenco (`list.tp.html`) ci sono due posti in più:

1. `themes/<tema>/sections/<sezione>/list.tp.html` nel sito (vince su tutto);
2. `sections/<sezione>/list.tp.html`;
3. `themes/<tema>/modules/dbview/list.tp.html` (sito, poi FINIS);
4. il file indicato da `$_FN['dbview_list_template']` (un solo template di elenco per tutte le
   sezioni dell'applicazione, usato solo se il tema non ha un suo template di elenco);
5. `src/modules/dbview/list.tp.html`.

**Barra della scheda.** I template della scheda stampano la barra con `{toolbar_html}` (dentro
`<!-- if {navigationbar} -->`). `toolbar.tp.html` cicla su `{items}`, ognuno con `{key}` (`list`,
`prev`, `next`, `view`, `history`, `edit`, `users`), `{link}`, `{title}` (testo completo, per il
tooltip), `{label}` (testo visibile), `{icon}` e `{active}`:

```html
<ul class="nav nav-tabs dbview-toolbar">
    <!-- foreach {items} -->
    <li class="nav-item dbview-toolbar-{key}">
        <a class="nav-link" href="{link}" title="{title}"><span class="dbview-toolbar-icon" aria-hidden="true">{icon}</span><span class="dbview-toolbar-text">{label}</span></a>
    </li>
    <!-- end foreach {items} -->
</ul>
```

Le icone sono caratteri Unicode (`☰ ← → ⓘ ↺ ✎ 👥`) disegnati nel colore del testo. Un tema le può
cambiare senza riscrivere il template, per esempio nel suo `theme.php`:

```php
$_FN['dbview_toolbar_icons'] = array("edit" => "✏", "history" => "🕘");
```

Lo `style.css` del modulo rende la barra compatta sugli schermi piccoli: sotto i 768 px le
schede restano su una riga che scorre di lato e solo la scheda attiva mostra il testo; sotto i
576 px restano solo le icone.

**Schede dei record.** Il `list.tp.html` predefinito mostra i record come card in una griglia che
mette in una riga quante card ci stanno (almeno 240 px ciascuna). Ogni record di `{items}` ha:

- `{item_title}`, `{item_urlview}`, `{item_urledit}` e `{item_urldelete}` (gli ultimi due valgono
  falso quando l'utente non può modificare o cancellare il record);
- `{item_urlimage}` (miniatura), `{item_urlimage_fullsize}` e `{item_hasimage}` (vero quando il
  record ha una foto: il template predefinito mostra la foto solo in quel caso);
- `{itemvalues}`: i campi mostrati nell'elenco (`{title}`, `{value}`, `{fieldname}`);
- `{viewvalue_<campo>}` e `{title_<campo>}`: gli stessi valori per nome del campo;
- `{value_<campo>}`: il valore grezzo di ogni campo del record, senza escape (usa
  `{value_<campo>.addHtmlEntities()}` per i testi scritti dagli utenti).

Le card hanno lo stile dello `style.css` del modulo, con le classi `.dbview-cards` (la griglia),
`.dbview-card`, `.dbview-card-media` (foto con proporzioni fisse), `.dbview-card-values` (una riga
per campo), `.dbview-card-view` (il pulsante di visualizzazione) e `.dbview-noresult`; il CSS ha
anche `.dbview-card-badges`, `.dbview-card-code`, `.dbview-card-nophoto` e `.dbview-card-noimage`
per i template che le usano. Questo CSS viene messo nella testata della pagina dopo i fogli di
stile del tema, quindi un tema che vuole cambiare le card deve usare selettori più specifici:

```css
.dbview-cards .dbview-card {
    border-radius: 0;
}
```

**Navigazione ajax.** Le pagine, i filtri per categoria e il modulo di ricerca dell'elenco si
caricano con `call_ajax()` / `call_ajax_form()`: la pagina viene chiesta con `fnajax=1` e i
contenitori indicati nella chiamata sostituiscono i `div`, `span`, `td` o `tr` con lo stesso `id`;
la barra degli indirizzi segue la navigazione. Un template di elenco che vuole mantenere questo
comportamento conserva i contenitori `<div id="pageresults">` e `<div id="collapseFilters">` e le
chiamate:

```html
<a href="{link}" onclick="call_ajax(this.href, 'pageresults,collapseFilters');return false">2</a>
<form onsubmit="return call_ajax_form(this, 'pageresults,collapseFilters');" method="get" action="{formaction}">
```

### Template di login e registrazione
I template del modulo login (`login.tp.html`, `register.tp.html`, `profile.tp.html`,
`editreg.tp.html`, quelli del recupero password e delle email) si sostituiscono in
`themes/<tema>/modules/login/`. I temi `default` e `bootstrap5` di FINIS sono esempi completi.

`login.tp.html` usa `{formaction}` (già sottoposto a escape) e delle parti marcate da commenti
HTML, alcune delle quali FINIS toglie quando non servono:

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

- `<!-- captcha -->` viene tolto quando il captcha è disattivato, `<!-- rememberlogin -->` quando
  `remember_login` è spento, `<!-- register -->` quando la registrazione è disattivata,
  `<!-- loginerror -->` quando non c'è un errore.
- `{oauth_providers}` elenca i provider di login esterni abilitati: stampa solo `{name}`, `{url}`
  e `{urlimage}` (ogni voce è il record del provider).
- Un tema può mettere il form di login anche in `template.tp.html`, tra
  `<!-- include FN_HtmlLoginForm -->` e `<!-- end include FN_HtmlLoginForm -->` (vedi il tema
  `bootstrap5`): valgono le stesse variabili e le stesse parti.

`register.tp.html` è un layout di form (gli input li genera il motore dei form) con una parte per
il captcha e una per le condizioni. Conviene un layout senza tabelle, che si adatta agli schermi
piccoli:

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

- In `register.tp.html` i marcatori di chiusura sono `<!-- endcaptcha -->` e
  `<!-- endconditions -->` (senza spazio).
- `<!-- inputattributes:... -->` dà gli attributi a tutti gli input;
  `<!-- inputattributes <tipo>:... -->` solo ai campi di quel tipo di form (per esempio `radio` o
  `check`).
- Al posto di `{htmlconditions}` il template può ciclare su `{conditions}` (`{title}`, `{text}`,
  `{name}`, `{checked}`, `{required}`, `{error}`, `{title_accept}`).

I valori di un template li fornisce il codice che lo usa. Né `FN_RewriteLink()` né il motore dei
template fanno escape: quando un template del tema mostra un valore preso dalla richiesta, va messo
in sicurezza (vedi [Escape dei valori](#escape-dei-valori)).

### Configurazione del Tema
Il file `config.php` nella cartella del tema dichiara le opzioni del tema. Ogni opzione è una riga
`$config` con il suo valore predefinito, preceduta da un commento con l'etichetta; le opzioni tra
graffe diventano una select:

```php
<?php
//[i18n]show search form {1=YES,0=NO}
$config['show_search_form'] = "1";
//[i18n]footer text
$config['footer_text'] = "";
```

Le opzioni si modificano dal pannello di controllo (Impostazioni generali > Aspetto); i valori
salvati lì stanno nel database, il file contiene solo i valori predefiniti. Ogni opzione è una
variabile di `template.tp.html`:

```html
<!-- if {show_search_form} -->
<form method="post" action="{siteurl}index.php?mod=search">...</form>
<!-- end if {show_search_form} -->
```

Il codice PHP va in `theme.php`, che FINIS include a ogni pagina, prima di costruirla. Può
impostare valori di `$_FN` (ogni stringa di `$_FN` è una variabile del template) e ridefinire le
funzioni HTML che FINIS definisce solo se mancano: `FN_HtmlMenu()`, `FN_HtmlNavbar()`,
`FN_HtmlLanguages()`, `FN_HtmlCredits()`, `FN_HtmlModalWindow()`, `FN_HtmlMainteanceMode()`,
`FN_HtmlHeader()`.

```php
<?php
global $_FN;
// {html_dir} nel template.tp.html: <html lang="{lang}" dir="{html_dir}">
$_FN['html_dir'] = in_array($_FN['lang'], array("ar", "he")) ? "rtl" : "ltr";
```

## Copie offline e frammenti ajax
Due parametri di `index.php` restituiscono parti di una pagina, per la navigazione ajax e per il
service worker di un'applicazione che conserva le pagine per l'uso offline:

- `fnajax=1`: solo la sezione, senza tema, in una pagina HTML minima il cui `<title>` è quello
  della pagina completa. Gli elenchi dbview lo usano per la navigazione ajax.
- `fnshell=1`: la pagina con il tema e il commento `<!--fnsection-->` al posto della sezione, che
  non viene generata. Un worker inserisce un frammento (`fnajax=1`) dentro questo guscio.

Ogni pagina ha anche le intestazioni `X-Finis-Visitor` (`public` quando la pagina è uguale per
tutti i visitatori anonimi, `private` per un utente collegato o una POST), `X-Finis-Section` e
`X-Finis-Lang`.

Perché il guscio funzioni, un tema deve:

- stampare la sezione solo con `<!-- include section --><!-- end include section -->`: il
  marcatore `<!--fnsection-->` viene messo lì;
- tenere il contenuto della pagina dentro la sezione: un worker riusa il guscio di una sezione (e
  di una lingua) per tutte le sue pagine, quindi quello che il tema stampa intorno alla sezione
  deve dipendere solo da queste;
- usare `{formaction}` nei form di login e logout: FINIS ne toglie `fnshell` e `fnajax`, così il
  form invia alla pagina vera.

## Framework CSS Integrati
FINIS supporta vari framework CSS popolari. Ecco come integrare Bootstrap:

### Tema con Bootstrap 5
FINIS contiene già due temi Bootstrap 5 in `src/themes/`: `default` (file di Bootstrap nella
cartella del tema, menu laterale) e `bootstrap5` (Bootstrap da CDN, menu in alto, form di login
nella barra di navigazione). Sono il punto di partenza più rapido: copiane uno nella cartella
`themes/` del sito con un nuovo nome e modificalo. Per costruirne uno da zero:

1. Crea una nuova cartella: `themes/miobootstrap/`
2. Scarica i file di Bootstrap e posizionali in `themes/miobootstrap/css/` e `themes/miobootstrap/js/`
3. Crea il template principale utilizzando le classi Bootstrap, con i percorsi relativi alla
   cartella del tema:

```html
<!DOCTYPE html>
<html lang="{lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{site_title}</title>
    
    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <!-- CSS personalizzati -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="{siteurl}">
                    <img src="img/logo.png" alt="{sitename}" height="30">
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
                            <!-- if not {hidetitle} -->
                            <div class="card-header">
                                <h3 class="card-title">{title}</h3>
                            </div>
                            <!-- end if not {hidetitle} -->
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
                            <!-- if not {hidetitle} -->
                            <div class="card-header">
                                <h3 class="card-title">{title}</h3>
                            </div>
                            <!-- end if not {hidetitle} -->
                            <div class="card-body">
                                {html}
                            </div>
                        </div>
                        <!-- end foreach {blocks_left} -->
                    </div>
                <!-- end if {blocks_left} -->
                
                <div class="col-lg">
                    <div class="card">
                        <!-- if not {is_home} -->
                        <div class="card-header">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-1">
                                    <!-- foreach {nav} -->
                                    <!-- if {active} -->
                                    <li class="breadcrumb-item active">{title}</li>
                                    <!-- end if {active} -->
                                    <!-- if not {active} -->
                                    <li class="breadcrumb-item"><a href="{link}">{title}</a></li>
                                    <!-- end if not {active} -->
                                    <!-- end foreach {nav} -->
                                </ol>
                            </nav>
                            <h1 class="h3 mb-0">{section_title}</h1>
                        </div>
                        <!-- end if not {is_home} -->
                        <div class="card-body">
                            <!-- include section -->
                            
                            <!-- end include section -->
                        </div>
                    </div>
                </div>
                
                <!-- if {blocks_right} -->
                    <div class="col-lg-3 mt-4 mt-lg-0">
                        <!-- foreach {blocks_right} -->
                        <div class="card mb-3">
                            <!-- if not {hidetitle} -->
                            <div class="card-header">
                                <h3 class="card-title">{title}</h3>
                            </div>
                            <!-- end if not {hidetitle} -->
                            <div class="card-body">
                                {html}
                            </div>
                        </div>
                        <!-- end foreach {blocks_right} -->
                    </div>
                <!-- end if {blocks_right} -->
            </div>
        </div>
    </main>
    
    <footer class="footer mt-auto py-3 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>{sitename}</p>
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
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

La colonna centrale usa `col-lg`, che prende lo spazio lasciato dalle colonne laterali: il motore
dei template non valuta espressioni, quindi la larghezza non si calcola con un `if` su più
variabili.

4. Se il tema ha delle opzioni, dichiarale nel suo `config.php` (vedi
   [Configurazione del Tema](#configurazione-del-tema)), come fa il tema `bootstrap5` di FINIS:

```php
<?php
//[i18n]show search form {1=YES,0=NO}
$config['show_search_form'] = "1";
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
1. Minimizza CSS e JavaScript e collega le versioni minimizzate dal template principale:
```html
<link rel="stylesheet" href="css/style.min.css">
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
2. Imposta la direzione in `theme.php` (ogni stringa di `$_FN` diventa una variabile del template):
```php
<?php
// themes/mytheme/theme.php
global $_FN;
$_FN['html_dir'] = in_array($_FN['lang'], array('ar', 'he')) ? 'rtl' : 'ltr';
$_FN['html_rtl'] = ($_FN['html_dir'] == 'rtl') ? '1' : '';
```

3. Nel template principale usa le variabili e carica il foglio RTL solo quando serve:
```html
<html lang="{lang}" dir="{html_dir}">
<head>
    ...
    <!-- if {html_rtl} --><link rel="stylesheet" href="css/rtl.css"><!-- end if {html_rtl} -->
</head>
```

Il percorso del foglio va scritto senza variabili: solo così parte dalla cartella del tema.

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
1. **Il tema non viene caricato**: Verifica che la cartella sia nominata correttamente e corrisponda al valore di `$_FN['theme']`, nella cartella `themes/` del sito o in `src/themes/`; altrimenti FINIS usa il tema predefinito e poi `base`
2. **Le variabili di template non sono sostituite**: Controlla la sintassi, deve essere esattamente `{variable_name}`; una variabile che non esiste resta nella pagina così come è scritta
3. **I file CSS/JS non sono caricati**: Controlla i percorsi e assicurati che i file esistano; i percorsi relativi partono dalla cartella del tema
4. **Le modifiche a un tema di `src/themes/` non si vedono**: il sito usa la propria copia in `themes/<nome>/` (vedi [Dove vengono cercati i temi](#dove-vengono-cercati-i-temi))

### Debug del Tema
Metti temporaneamente `{listvars}` in un template: stampa le variabili usate da quel template con
i loro valori.

## Esempio Pratico: Creazione di un Tema Personalizzato

### 1. Creazione del File di Configurazione
Le opzioni modificabili dal pannello di controllo vanno in `config.php`:

```php
<?php
// themes/custom/config.php
//[i18n]footer text
$config['footer_text'] = "";
```

Il codice PHP, se serve, va in `theme.php`; i valori messi in `$_FN` diventano variabili del
template:

```php
<?php
// themes/custom/theme.php
global $_FN;
$_FN['current_year'] = date('Y');
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
    <link rel="stylesheet" href="css/custom.css">
    <script src="js/custom.js" defer></script>
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
            &copy; {current_year} {sitename} - {footer_text}
        </footer>
    </div>
</body>
</html>
```

### 3. Personalizzazione per Moduli Specifici
Le parti e le variabili del form di login sono descritte in
[Template di login e registrazione](#template-di-login-e-registrazione).

```html
<!-- themes/custom/modules/login/login.tp.html -->
<div class="custom-login-form">
    <!-- Form di login personalizzato -->
</div>
```

### 4. Attivazione del Tema
Per attivare il tema, modifica il file di configurazione o usa il pannello di amministrazione
(Impostazioni generali > Aspetto) per impostare:
```php
$_FN['theme'] = 'custom';
```

Il pannello di controllo elenca i temi di entrambe le cartelle (sito e FINIS), ne mostra
un'anteprima e applica quello scelto. L'anteprima è il sito aperto con
`index.php?themepreview=<nome>`: cambia il tema solo per quella richiesta, per gli amministratori
(per tutti quando `switchtheme` è attivo). Con `switchtheme` attivo un visitatore può anche
scegliere un tema con `index.php?change_theme=1&theme=<nome>`, che resta in un cookie; con
`switchtheme` spento il parametro è ignorato. `theme` e `themepreview` accettano solo il nome di
una cartella di `themes/` (lettere, cifre, `_`, `-`, `.`): un percorso o un nome sconosciuto danno
il tema predefinito.