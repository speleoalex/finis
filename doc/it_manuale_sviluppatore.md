# Manuale dello Sviluppatore FINIS Framework

## Introduzione
Questo manuale è pensato per gli sviluppatori che desiderano estendere le funzionalità del framework FINIS, creando moduli personalizzati, integrando nuove funzionalità o modificando quelle esistenti.

## Architettura del Framework

### Componenti Principali
- **Core (FINIS.php)**: Classe principale che inizializza e gestisce il framework
- **XMETATable**: Sistema di gestione database con supporto per diversi driver (vedi [Guida Database](it_guida_database.md))
- **Theme Engine**: Sistema per la gestione e il rendering dei temi (vedi [Guida Temi](it_guida_temi.md))
- **Section Manager**: Gestisce le sezioni del sito e il loro routing
- **Module System**: Sistema per estendere le funzionalità con moduli
- **Estensioni**: Cartelle con moduli, sezioni, pagine del pannello di controllo e tabelle aggiuntive, registrate con `addExtension()` (vedi [Estensioni](#estensioni))

### Cartelle del Framework e dell'Applicazione
FINIS tiene separato il framework dal sito che lo usa:

- `$_FN['src_finis']`: la cartella `src/` di FINIS (funzioni del core, moduli, temi, pannello di controllo)
- `$_FN['src_application']`: la cartella del sito, quella del suo `index.php` (`"."` per default)
- `$_FN['datadir']`: la cartella dei dati (`misc` per default)

La cartella dell'applicazione può contenere:

| Percorso | Contenuto |
|---|---|
| `index.php` | punto di ingresso che crea l'oggetto `FINIS` |
| `config.vars.local.php` | configurazione di questa installazione (database, `display_errors`, ...) |
| `sections/<id>/` | le pagine del sito |
| `modules/<nome>/` | moduli dell'applicazione (trovati solo se la cartella contiene `section.php`) |
| `themes/<nome>/` | temi dell'applicazione |
| `controlcenter/sections/<gruppo>/<pagina>/` | pagine del pannello di controllo |
| `include/methods/<nome>.php`, `include/applications/<nome>.php` | metodi `fnapp` |
| `include/autoexec.d/*.php` | script eseguiti prima di ogni pagina |
| `include/mcp_tools.php` | tool MCP dell'applicazione |
| `include/auth/<metodo>.php` | sostituisce il metodo di autenticazione di FINIS (`local`) |
| `extra/*.inc.php` | file PHP caricati all'avvio da ogni metodo, prima delle funzioni di FINIS |
| `languages/<lingua>/lang.csv` | traduzioni |
| `plugins/<nome>/` | pagine di impostazioni mostrate nel pannello di controllo |

Quando FINIS cerca un modulo, una pagina del pannello di controllo o un file di configurazione,
guarda prima nell'applicazione, poi in FINIS, poi nelle estensioni registrate. I file di FINIS
che servono al browser (immagini, CSS, JavaScript) vengono copiati nella cartella
dell'applicazione al primo utilizzo (`FN_PathSite()`): `src/` può quindi stare fuori dalla
radice web.

### La Classe FINIS
L'`index.php` di un sito:

```php
<?php
require_once "../finis/src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->finis();
```

- `new FINIS($config = array())`: copia ogni voce di `$config` in `$_FN` ed esegue l'avvio
  (`src/include/finis.php`): configurazione, database, login, lingua, sezioni, tema, traduzioni.
  `src/include/config.vars.php` e il `config.vars.local.php` del sito vengono caricati dopo i
  valori del costruttore e li sostituiscono: `display_errors`, `datadir`,
  `default_database_driver` e i parametri del database vanno in `config.vars.local.php`. Un
  `theme` passato al costruttore prevale sul tema scelto nel pannello di controllo.
- `finis()`: esegue il metodo indicato da `fnapp` nella query string, `website` se manca.
- `addExtension($path)`: registra una cartella di estensione (vedi [Estensioni](#estensioni));
  va chiamato dopo il costruttore e prima di `finis()`. Se la cartella non esiste si ferma con
  un errore.
- `setVar($name, $value)`: imposta `$_FN[$name]`.
- `setTable($tablename, $params)`: parametri di una tabella, uniti a quelli che `FN_XMDBTable()`,
  `FN_XMDBForm()` e `FN_XMETATableEditor()` usano per quella tabella (per esempio
  `default_database_driver` per una tabella il cui descrittore non indica un `driver`).
- `runSection($section = "")`: stampa una sezione senza il tema (per default quella corrente,
  `$_FN['mod']`), solo se l'utente può vederla.
- `runFolder($folder)`: esegue il `section.php` di una cartella o ne stampa il contenuto statico
  (`section.<lingua>.html`). Non controlla i permessi.
- `isConsole()`: vero quando lo script è eseguito da riga di comando.
- Qualsiasi altro nome di metodo viene smistato come `fnapp`: `$FINIS->controlcenter()` apre il
  pannello di controllo.

### Metodi (`fnapp`)
`index.php?fnapp=<nome>` (oppure `$FINIS-><nome>()`) include il primo file trovato fra:

1. `{src_application}/include/applications/<nome>.php`
2. `{src_finis}/include/applications/<nome>.php`
3. `{src_application}/include/methods/<nome>.php`
4. `{src_finis}/include/methods/<nome>.php`

Il nome può contenere solo lettere, cifre e `_`: con qualsiasi altro nome, o se il file non
esiste, viene sollevata un'eccezione. FINIS fornisce `website` (il default), `controlcenter`,
`install`, `api`, `print`, `thumb`, `captcha`, `cron`, `filemanager`, `notifications`, `oAuth`,
`mcp` e `oauthserver`; un file dell'applicazione con lo stesso nome sostituisce quello di FINIS.
Il file è incluso dentro un metodo della classe, quindi deve dichiarare `global $_FN;`. Solo
`website` e `print` eseguono gli script di `include/autoexec.d/`.

Esempio, `include/methods/export_csv.php` (`index.php?fnapp=export_csv`):

```php
<?php
global $_FN;
// only the administrators may download the list
if (!FN_IsAdmin())
{
    http_response_code(403);
    die();
}
$table = FN_XMDBTable("my_table");
$rows = $table->GetRecords();
header("Content-Type: text/csv; charset=UTF-8");
if (is_array($rows))
{
    foreach ($rows as $row)
    {
        echo $row['id'] . ";" . str_replace(";", ",", $row['name']) . "\n";
    }
}
```

### Flusso di Esecuzione
1. L'utente richiede una pagina (`index.php`), che crea l'oggetto `FINIS`
2. L'avvio carica `config.vars.php`, `config.vars.local.php`, gli `extra/*.inc.php` dell'applicazione, le funzioni del core, il livello database, l'autenticazione, le impostazioni salvate in `fn_settings`, il login (`FN_ManageLogin()`), la lingua, il tema e le sezioni
3. `finis()` esegue il metodo di `fnapp` (per default `website`)
4. `website` esegue gli script di `include/autoexec.d/` (prima quelli di FINIS, poi quelli dell'applicazione)
5. Il parametro `mod` indica la sezione richiesta; senza `mod` si usa la sezione iniziale (l'impostazione del pannello di controllo, oppure `$_FN['home_section_force']` in `config.vars.local.php`). Una sezione iniziale che esiste solo nella cartella `sections/` dell'applicazione viene usata solo da `index.php`
6. Il template del tema viene renderizzato; `<!-- include section -->` esegue la sezione: il modulo del suo tipo (trovato con `FN_GetModulePath()`), altrimenti la cartella della sezione (`section.php`, oppure `section.<lingua>.html`)

### Modalità di Output del Metodo Website
Oltre alla pagina completa, `website` ha due output parziali, usati dalla navigazione ajax e dai
service worker che conservano copie offline delle pagine:

- `fnajax=1`: solo l'output della sezione, senza il tema:
  `<!DOCTYPE html><html><head><meta charset="..."><title>...</title></head><body>...</body></html>`.
  Gli hook dell'intestazione vengono eseguiti prima (`FN_HtmlHeader()`), quindi il titolo è
  quello della pagina completa (per esempio l'`header.php` di dbview aggiunge il titolo del
  record). La navigazione ajax di dbview (`call_ajax()`) carica i suoi link con `fnajax=1`.
- `fnshell=1`: la pagina con il tema e il marcatore `<!--fnsection-->` al posto della sezione,
  che non viene eseguita. Viene ignorato se è presente anche `fnajax`, e funziona con i template
  che usano `<!-- include section -->`.

Ogni risposta di `website` contiene queste intestazioni:

| Intestazione | Valore |
|---|---|
| `X-Finis-Visitor` | `public` per una GET di un visitatore anonimo (la pagina è uguale per tutti gli anonimi e può essere conservata), altrimenti `private` |
| `X-Finis-Section` | la sezione (`$_FN['mod']`) |
| `X-Finis-Lang` | la lingua (`$_FN['lang']`) |

## Estensioni
Un'estensione è una cartella che aggiunge moduli, sezioni, pagine del pannello di controllo e
tabelle a un sito senza copiarne il codice nell'applicazione. La cartella `fncommerce/` del
repository (il negozio usato da `examples/ecommerce`) è l'esempio di riferimento:

```php
<?php
// examples/ecommerce/index.php
require_once "../../src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("../../fncommerce/");
$FINIS->finis();
```

`addExtension()` aggiunge il percorso a `$_FN['path_extensions']`; più estensioni vengono
cercate nell'ordine in cui sono state aggiunte, dopo l'applicazione e FINIS.

```
miaestensione/
  ├── modules/<nome>/                           # moduli, usati dalla cartella dell'estensione
  ├── sections/<id>/                            # sezioni, copiate nel sito dall'installer
  ├── controlcenter/sections/<gruppo>/<pagina>/ # pagine del pannello di controllo, usate dalla cartella dell'estensione
  └── misc/fndatabase/                          # descrittori delle tabelle e cartelle dei dati, copiati dall'installer
```

- **Moduli**: `FN_GetModulePath($name)` restituisce la cartella di un modulo:
  `{src_application}/modules/<nome>` se contiene `section.php`, altrimenti
  `{src_finis}/modules/<nome>`, altrimenti `<estensione>/modules/<nome>`; restituisce false se
  il modulo non esiste. `FN_GetAllModulePaths()` elenca i moduli dei tre posti. La usano
  l'esecuzione delle sezioni, la ricerca e le traduzioni, e `FN_InitSections()` registra i
  moduli delle estensioni come tipi di sezione.
- **Pannello di controllo**: `FNCC_GetSectionPath("<gruppo>/<pagina>")` cerca
  nell'applicazione, in FINIS e nelle estensioni, in quest'ordine; il menu unisce i gruppi e le
  pagine dei tre posti, e `FNCC_GetSectionTitle()` prende il primo `title.*.fn` trovato.
- **Installer**: durante la prima installazione (`fnapp=install`, finché esiste
  `{datadir}/firstinstall`) le cartelle in `sections/` e i file e le cartelle in
  `misc/fndatabase/` di ogni estensione vengono copiati nell'applicazione se mancano.
  `FN_Install($path)` prende il file da un'estensione quando FINIS non lo ha.
- **Altri file**: anche `FN_LoadConfig()`, `FN_HtmlEditConfFile()` e `FN_FromTheme()` trovano i
  file delle estensioni. Una pagina del pannello di controllo di fncommerce modifica così le
  impostazioni del modulo:

```php
<?php
defined('_FNEXEC') or die('Restricted access');
global $_FN;
// settings of the section "fncommerce": the defaults are in the config.php of the module
$_FN['mod'] = "fncommerce";
echo FNCC_HtmlEditConfFile("modules/fncommerce/config.php");
```

Limiti: le estensioni sono note solo dopo `addExtension()`, cioè dopo l'avvio eseguito dal
costruttore. `header.php` e `style.css` di un modulo vengono letti solo per i moduli di FINIS
(`src/modules/`): un'estensione li fornisce nella cartella della sezione
(`sections/<id>/header.php`, `sections/<id>/style.css`), che l'installer copia.

## Creazione di Moduli

### Struttura Base di un Modulo
Un modulo è un tipo di sezione: una sezione di tipo `mymodule` esegue
`modules/mymodule/section.php`. I moduli stanno nella cartella `modules/` dell'applicazione, di
FINIS (`src/modules/`) o di un'estensione; un modulo dell'applicazione viene trovato solo se la
sua cartella contiene `section.php`.

```
modules/
  └── nomemodulo/
      ├── section.php       # Logica principale del modulo
      ├── config.php        # Configurazione predefinita di ogni sezione
      ├── default.xml       # Titolo e descrizione del tipo di modulo
      ├── search.php        # Facoltativo: risultati per il modulo di ricerca
      ├── languages/        # Traduzioni
      │   ├── en/
      │   │   └── lang.csv
      │   └── it/
      │       └── lang.csv
      ├── css/              # Fogli di stile
      └── js/               # Script JavaScript
```

### Esempio di Modulo Base

Creare una nuova cartella in `modules/` (es. `modules/mymodule/`) con questi file:

**section.php**:
```php
<?php
/**
 * Example module
 * @author DeveloperName
 */
global $_FN;

// Create the table of the module at the first use
if (!file_exists("{$_FN['datadir']}/fndatabase/my_custom_table.php")) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <name>id</name>
        <type>int</type>
        <extra>autoincrement</extra>
        <primarykey>1</primarykey>
    </field>
    <field>
        <name>title</name>
        <type>string</type>
        <size>255</size>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>content</name>
        <type>text</type>
    </field>
    <field>
        <name>created_date</name>
        <type>datetime</type>
    </field>
    <filename>my_custom_table</filename>
</tables>';
    FN_Write($xml, "{$_FN['datadir']}/fndatabase/my_custom_table.php");
}

// Module main output
function MyModule_Main() {
    global $_FN;

    // Example of reading GET/POST parameters
    $action = FN_GetParam("action", $_GET, "string");

    // Configuration of the current section (config.php of the module + saved values)
    $config = FN_LoadConfig();

    // Conditional logic based on action
    $output = "";
    switch ($action) {
        case "view":
            $output = MyModule_ViewItem();
            break;
        case "list":
        default:
            $output = MyModule_ListItems();
            break;
    }

    return $output;
}

// Example function to list items
function MyModule_ListItems() {
    global $_FN;
    $html = "<h2>" . FN_i18n("list_items") . "</h2>";

    // Example of database access
    $table = FN_XMDBTable("my_custom_table");
    $items = $table->GetRecords();

    if (is_array($items) && count($items) > 0) {
        $html .= "<ul>";
        foreach ($items as $item) {
            // values from the database are escaped before they go into the page
            $html .= "<li><a href=\"?mod={$_FN['mod']}&action=view&id=" . urlencode($item['id']) . "\">" . htmlspecialchars($item['title']) . "</a></li>";
        }
        $html .= "</ul>";
    } else {
        $html .= "<p>" . FN_i18n("no_items_found") . "</p>";
    }

    return $html;
}

// Example function to view an item
function MyModule_ViewItem() {
    global $_FN;
    $id = FN_GetParam("id", $_GET, "int");

    $table = FN_XMDBTable("my_custom_table");
    $item = $table->GetRecordByPrimaryKey($id);

    if (isset($item['id'])) {
        $html = "<h2>" . htmlspecialchars($item['title']) . "</h2>";
        $html .= "<div>{$item['content']}</div>";
        $html .= "<p><a href=\"?mod={$_FN['mod']}\">" . FN_i18n("back_to_list") . "</a></p>";
    } else {
        $html = "<p>" . FN_i18n("item_not_found") . "</p>";
        $html .= "<p><a href=\"?mod={$_FN['mod']}\">" . FN_i18n("back_to_list") . "</a></p>";
    }

    return $html;
}

// Main entry point
$output = MyModule_Main();
echo $output;
```

**config.php**:
```php
<?php
//[i18n]items per page
$config['items_per_page'] = 10;
//[i18n]enable comments {1=yes,0=no}
$config['enable_comments'] = 1;
//[i18n]default order
$config['default_sort'] = "title";
```

`FN_LoadConfig()` chiamata senza argomenti dal modulo restituisce la configurazione della
sezione corrente: i valori del `config.php` del modulo, sostituiti da quelli salvati per quella
sezione (tabella `fncf_<id sezione>`). I commenti `//[i18n]` sono le etichette dell'editor di
configurazione, `{1=yes,0=no}` le sue opzioni.

### Integrazione con la Ricerca
Il modulo `search` cerca nelle sezioni che l'utente può vedere, comprese quelle nascoste. Per
ogni sezione include un `search.php`:

- per una sezione con un tipo: `<cartella del modulo>/search.php` (trovata con
  `FN_GetModulePath()`), che definisce `FNSEARCH_module_<tipo>()`;
- per una sezione senza tipo: `{src_application}/sections/<id>/search.php`, che definisce
  `FNSEARCH_section_<id>()`.

Il file viene incluso una sola volta (`include_once`) e la funzione viene chiamata per ogni
sezione di quel tipo:

```php
<?php
// modules/mymodule/search.php
/**
 * @param array $tosearch_array the words to find
 * @param string $method "AND" or "OR"
 * @param array $sectionvalues the section searched
 * @param int $maxres maximum number of results to return
 * @return array results: title, link, text
 */
function FNSEARCH_module_mymodule($tosearch_array, $method, $sectionvalues, $maxres)
{
    $results = array();
    $table = FN_XMDBTable("my_custom_table");
    $rows = $table->GetRecords();
    if (!is_array($rows))
        return $results;
    foreach ($rows as $row)
    {
        $found = ($method == "AND");
        foreach ($tosearch_array as $word)
        {
            $match = (stripos($row['title'] . " " . $row['content'], $word) !== false);
            $found = ($method == "AND") ? ($found && $match) : ($found || $match);
        }
        // the module applies its own permissions here: the search checks only the section
        if ($found)
        {
            $results[] = array(
                "title" => $row['title'],
                "link" => FN_RewriteLink("index.php?mod={$sectionvalues['id']}&action=view&id={$row['id']}"),
                "text" => strip_tags($row['content']),
            );
        }
        if (count($results) >= $maxres)
            break;
    }
    return $results;
}
```

I risultati sono raggruppati per sezione: al massimo 30 per ogni sezione (alla funzione ne viene
chiesto uno in più per sapere se ce ne sono altri) e 500 in tutto. Ogni gruppo ha le variabili
`count`, `more`, `anchor` e, per le sezioni dbview, `url_list` (l'elenco della sezione filtrato
con lo stesso testo). Vengono cercati anche il titolo e la descrizione di ogni sezione. Una
sezione nascosta viene cercata solo se il suo modulo ha un `search.php`. Una sezione senza tipo e
senza `search.php` viene cercata anche nel suo `section.<lingua>.html` (la lingua corrente; le
altre solo se manca). Una casella di ricerca senza il campo `method` cerca tutte le parole.

### Visibilità nel Menu
`FN_GetMenuTree()` elenca le sezioni che l'utente può vedere (`FN_GetSections()`) e poi chiede
a `FN_MenuSectionIsVisible($section)`, che nasconde le voci il cui modulo rifiuterebbe il
contenuto: una sezione dbview è mostrata solo agli utenti dei suoi gruppi `groupadmin`,
`groupview` o `groupinsert` (a tutti se `groupview` è vuoto), una sezione `table_editor` solo
agli amministratori. Un contenitore `block_menu` con tutte le voci nascoste viene nascosto a sua
volta. Gli amministratori vedono tutto. La funzione conosce solo questi due moduli: un modulo con
permessi propri deve impostare i gruppi della sezione (`group_view`), così menu e modulo sono
d'accordo. Un tema o un'applicazione che definisce `FN_GetSectionProprieties($section_id)`
aggiunge a ogni voce del menu i valori che questa restituisce.

### Integrazione con il Pannello di Controllo

Una pagina del pannello di controllo è una cartella `controlcenter/sections/<gruppo>/<pagina>/`
dell'applicazione, di FINIS o di un'estensione. I gruppi di FINIS sono `contents`, `settings`,
`users_and_groups` e `utilities`; una nuova cartella di gruppo diventa un nuovo gruppo del menu.

```
controlcenter/
  └── sections/
      └── settings/
          └── mymodule/
              ├── section.php
              ├── icon.png
              ├── title.it.fn
              └── title.en.fn
```

La pagina si apre con `?fnapp=controlcenter&opt=settings/mymodule`. Il pannello di controllo
ammette gli amministratori e gli utenti abilitati a quella pagina (Utilità > Utenti pannello di
controllo).

**section.php**:
```php
<?php
/**
 * Admin panel for mymodule
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;

// Check if user is administrator
if (!FN_IsAdmin()) {
    echo FN_i18n("access_denied");
    return;
}

// Settings of the section "mymodule": the form is built from the comments of config.php,
// the values are saved in the table fncf_mymodule
$_FN['mod'] = "mymodule";
echo FNCC_HtmlEditConfFile("modules/mymodule/config.php");
```

`FNCC_HtmlEditConfFile()` corrisponde a `FN_HtmlEditConfFile($file, $formaction, $exit, $allow, $write_to_file, $mod, $block, $htmltemplate)`
con il template del tema del pannello di controllo.

## Lavorare con il Database

### Creazione Tabelle
FINIS utilizza un sistema di descrizione di tabelle basato su XML. Ecco come definire una tabella:

```php
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <name>id</name>
        <type>int</type>
        <extra>autoincrement</extra>
        <primarykey>1</primarykey>
    </field>
    <field>
        <name>nome</name>
        <type>string</type>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>descrizione</name>
        <type>text</type>
    </field>
    <filename>mia_tabella</filename>
</tables>';
FN_Write($xml, "{$_FN['datadir']}/fndatabase/mia_tabella.php");
```

### Operazioni CRUD
FINIS offre una serie di funzioni per manipolare i dati:

```php
// Ottenere un'istanza della tabella
$table = FN_XMDBTable("mia_tabella");

// Inserire un record (restituisce il record salvato)
$newRecord = array(
    'nome' => 'Nuovo elemento',
    'descrizione' => 'Descrizione dell\'elemento'
);
$record = $table->InsertRecord($newRecord);
$id = $record['id'];

// Leggere un record
$record = $table->GetRecordByPrimaryKey($id);

// Aggiornare un record: i valori contengono la chiave primaria
$updatedRecord = array(
    'id' => $id,
    'nome' => 'Nome aggiornato',
    'descrizione' => 'Descrizione aggiornata'
);
$table->UpdateRecord($updatedRecord);

// Eliminare un record
$table->DelRecord($id);

// Ottenere tutti i record
$records = $table->GetRecords();

// Ottenere record filtrati
$filteredRecords = $table->GetRecords(array('nome' => 'Filtro'));
```

`UpdateRecord($values, $pkvalue)` con il secondo argomento aggiorna il record con quella chiave
(serve per cambiare la chiave primaria). Con i driver SQL un filtro di `GetRecords()` è un
pattern `LIKE`: quando un valore preso dalla richiesta deve corrispondere a un solo record,
confrontare di nuovo il campo sul risultato. I dettagli dei driver sono nella
[Guida Database](it_guida_database.md).

## Localizzazione e Internazionalizzazione

### File di Traduzione
I file di traduzione sono in formato CSV e si trovano in `languages/[lang]/lang.csv` e `modules/[module]/languages/[lang]/lang.csv`.

Esempio di file `lang.csv`:
```
"original_text","translated_text"
"list_items","Lista elementi"
"no_items_found","Nessun elemento trovato"
"back_to_list","Torna alla lista"
"item_not_found","Elemento non trovato"
```

### Uso delle Traduzioni
```php
echo FN_i18n("my_translation_key");
```

## Hooks del Sistema
FINIS supporta un sistema di hook per estendere le funzionalità senza modificare il core.

### Autoexec e On Site Change
- I file in `include/autoexec.d/` di FINIS, e poi quelli dell'applicazione, vengono eseguiti in ordine naturale prima di ogni pagina dei metodi `website` e `print` (non dal pannello di controllo, da `api`, da `mcp` e dagli altri metodi)
- I file in `src/include/on_site_change.d/` (solo quelli di FINIS; i nomi che iniziano con `none_` vengono saltati) vengono eseguiti da `FN_OnSitemapChange()` quando sezioni o blocchi vengono creati, spostati o eliminati
- I file `extra/*.inc.php` dell'applicazione vengono caricati all'avvio da ogni metodo, prima delle funzioni di FINIS: servono a definire funzioni e impostare chiavi di `$_FN`, non a chiamare funzioni `FN_` durante il caricamento

### Esempio di Hook
```php
<?php
// Save as include/autoexec.d/99_mymodule.php in the application
global $_FN;

// Add the script only to some pages
if ($_FN['mod'] == 'home') {
    $_FN['section_header_footer'] .= '<script src="modules/mymodule/js/script.js"></script>';
}
```

`FN_HtmlHeader()` compone l'intestazione della pagina con `$_FN['section_header']`, l'output
dell'`header.php` del modulo (solo moduli di FINIS) e della sezione (`sections/<id>/header.php`),
i CSS e i JavaScript della pagina e, per ultimo, `$_FN['section_header_footer']`.

### Funzioni di Callback
Il core chiama queste funzioni quando l'applicazione le definisce (per esempio in `extra/*.inc.php`):

| Funzione | Chiamata |
|---|---|
| `FN_OnAddUser($values, $password)` | dopo che `FN_AddUser()` ha salvato un nuovo utente |
| `FN_OnUpdateUser($values, $password)` | dopo che `FN_UpdateUser()` ha salvato un utente |
| `FN_OnDeleteUser($uservalues)` | dopo che `FN_DeleteUser()` ha eliminato un utente |
| `FN_OnConfirmUser($uservalues)` | alla conferma di una registrazione (modulo login) |
| `FN_SendMailWelcome($uservalues)` | al posto della mail di benvenuto predefinita |
| `FN_PasswordVerifyConstraints_overwrite($password)` | regole delle password: restituisce un messaggio di errore, `""` se la password è accettata |
| `FN_GetSectionProprieties($section_id)` | valori aggiuntivi (array) per ogni voce del menu |

## Il Modulo dbview
`src/modules/dbview` mostra i record di una tabella come elenco con ricerca, filtri e pagine, una
scheda per record e i form di inserimento e modifica. La tabella e le opzioni stanno nella
configurazione della sezione (`tables`, `groupview`, `groupinsert`, `groupadmin`,
`search_fields`, `search_orders`, `navigate_groups`, ...: vedi `src/modules/dbview/config.php`).
La classe è `FNDBVIEW` (`FNDBVIEW.php`), con i trait in `traits/` (CRUD, esportazione, permessi,
utilità, cache delle schede).

### File della Sezione
Questi file della cartella della sezione (`{src_application}/sections/<id>/`) vengono usati se
presenti:

| File | Uso |
|---|---|
| `custom_functions.php` | incluso all'inizio di ogni richiesta della sezione |
| `section.php` o `section.<lingua>.html` | contenuto mostrato sopra l'elenco |
| `top.php` | incluso quando viene costruito l'elenco |
| `grid_header.php` | output messo in `{html_header}` del template dell'elenco |
| `bottom.php` | incluso dopo l'elenco |
| `viewheader.php`, `viewfooter.php` | output messo in `{header}` e `{footer}` della scheda |
| `bottom_edit.php` | incluso dopo il form di modifica (`op=edit`, e `op=updaterecord` dopo il salvataggio) |
| `exports.csv` | link di esportazione aggiuntivi dell'elenco (colonna `script` più i valori per il template, es. `title`), con `enable_export` attivo |
| `default.png` | immagine dei record senza foto |
| `header.php`, `style.css` | intestazione della pagina e CSS (qualsiasi sezione) |

`index.php?mod=<id>&file=<nome>` include il file `<nome>` della cartella della sezione al posto
del modulo, così una sezione può avere script propri (un'esportazione, una pagina di stampa).
`<nome>` deve essere un semplice nome di file (lettere, cifre, `_`, `-`, `.`, nessun percorso) e
il visitatore deve poter vedere i record della sezione (`CanViewRecords()`, altrimenti "you are
not authorized to view the data"). I file della tabella sopra che il modulo include da sé, e
`header.php`, `footer.php`, `section.php`, `search.php`, `pdf.php`, `default.xml.php`,
`config.php`, non vengono mai chiamati così: il parametro è ignorato e parte il modulo. Uno
script chiamato con `file=` controlla gli altri permessi di cui ha bisogno (per esempio
`$dbview->CanViewRecord($id, $tablename)` per un record).

### Template
I template vengono cercati prima nella cartella della sezione, poi nel tema
(`themes/<tema>/modules/dbview/`, dell'applicazione e poi di FINIS, tramite `FN_FromTheme()`),
poi nel modulo: `list.tp.html` (elenco), `detail.tp.html` (scheda), `detail_inner.tp.html`
(record collegati; letto dalla cartella della sezione solo se questa ha anche `detail.tp.html`),
`formedit.tp.html` (form di modifica), `forminner.tp.html` (editor delle tabelle collegate),
`form.tp.html` (nuovo record), `history.tp.html` (versioni), `users.tp.html` (utenti abilitati su
un record), `toolbar.tp.html` (barra della scheda). `form_offline.html` viene cercato solo nel
tema e nel modulo.

Per il template dell'elenco l'ordine è: `themes/<tema>/sections/<id>/list.tp.html`
dell'applicazione, poi `sections/<id>/list.tp.html`, poi il template dell'elenco del tema, poi
`$_FN['dbview_list_template']` (percorso di un file, per tutte le sezioni senza un template
proprio), poi quello del modulo.

Ogni elemento di `{items}` in `list.tp.html` ha `{item_title}`, `{item_urlview}`,
`{item_urledit}`, `{item_urldelete}` (false se l'utente non può eliminare o modificare il
record), `{item_urlimage}` (miniatura), `{item_urlimage_fullsize}`, `{item_hasimage}` (vero se il
record ha un file foto), `{url_offlineform}`, `{itemvalues}` (titolo/valore dei campi con
`frm_showinlist`), `{viewvalue_<campo>}` e `{title_<campo>}` per gli stessi campi, e
`{value_<campo>}`: il valore grezzo di ogni campo scalare del record. `{value_<campo>}` non è
escapato: per il testo scritto dagli utenti usare `{value_<campo>.addHtmlEntities()}`. Il
template del modulo mostra i record come schede (classi `dbview-cards`, `dbview-card`,
`dbview-card-media`, `dbview-card-values`, ...).

I link delle pagine, i filtri per categoria e il form di ricerca dell'elenco ricaricano solo i
contenitori `pageresults` e `collapseFilters`: `call_ajax(url, "pageresults,collapseFilters")` e
`<form onsubmit="return call_ajax_form(this, 'pageresults,collapseFilters');" method="get">`.
L'URL viene caricato con `fnajax=1`, i contenitori vengono sostituiti con quelli con lo stesso id
e l'URL del browser segue con la history API (avanti e indietro funzionano, l'URL è un link alla
pagina e ai filtri correnti).

La barra della scheda è `{toolbar_html}` in `detail.tp.html`, `formedit.tp.html`,
`history.tp.html` e `users.tp.html`, costruita da `toolbar.tp.html` con la variabile `{items}`
(`key`, `link`, `title`, `label`, `icon`, `active`). Le chiavi sono `list`, `prev`, `next`,
`view`, `history`, `edit` e `users`; le icone sono caratteri UTF, modificabili con
`$_FN['dbview_toolbar_icons']`:

```php
$_FN['dbview_toolbar_icons'] = array("edit" => "✏", "list" => "≡");
```

### Chiavi dell'Applicazione
L'applicazione imposta queste chiavi in `config.vars.local.php` oppure, per le funzioni, in
`extra/*.inc.php`:

| Chiave | Valore |
|---|---|
| `$_FN['dbview_list_template']` | percorso di un `list.tp.html` per le sezioni che non ne hanno uno proprio |
| `$_FN['dbview_toolbar_icons']` | array chiave => carattere, sostituisce le icone della barra |
| `$_FN['dbview_excluded_values']` | funzione `($tablename)` che restituisce `array(campo => array(valori))`: i record con uno di questi valori vengono esclusi da elenchi e ricerche e rifiutati dalla scheda; non si applica agli amministratori del sito |
| `$_FN['dbview_view_cache']` | cache delle schede per i visitatori anonimi: `array(sezione => secondi)`, oppure un numero per tutte le sezioni (0 = spenta) |
| `$_FN['dbview_view_cache_tables']` | `array(sezione => array(tabelle))`: tabelle le cui modifiche rendono vecchie le schede in cache (per default la tabella della sezione) |
| `$_FN['dbview_view_cache_files']` | array di pattern glob aggiuntivi dei file che generano la scheda (la cache è vecchia quando cambiano) |
| `$_FN['dbview_view_cache_keep']` | giorni dopo i quali le schede in cache non usate vengono eliminate (default 30) |
| `$_FN['xmetadb_file_url']` | funzione `($table, $recordvalues, $field, $thumb)` che restituisce l'indirizzo web di un file di un record (`$table` è la `XMETATable`), false se il file non c'è |
| `$_FN['xmetadb_file_access']` | funzione `($tablename, $id, $field)` che restituisce true se l'utente può leggere quel file |

```php
<?php
// extra/restricted.inc.php
global $_FN;
// records of the table "caves" with status "restricted" only for the group "speleo"
$_FN['dbview_excluded_values'] = function ($tablename)
{
    global $_FN;
    if ($tablename != "caves" || FN_UserInGroup($_FN['user'], "speleo"))
        return array();
    return array("status" => array("restricted"));
};
```

La cache delle schede viene usata solo per una GET senza parametri diversi da `mod`, `op=view`,
`id`, `lang` e `fnajax`, da un visitatore anonimo che può vedere il record. File:
`{datadir}/_cache/pages/<sezione>/<tema>/<lingua>/<id div 1000>/<id>.html`. Un file è valido per
i secondi configurati, finché è più recente delle tabelle della scheda e dei file che la generano
(cartella della sezione, dbview, form, template del tema, `dbview_view_cache_files`). Salvare o
eliminare un record da dbview ne cancella i file; `FN_ClearCache()` li cancella tutti.

### File dei Record Protetti
Per default il file di un record viene servito dalla cartella dei dati. Per proteggere i file,
un'applicazione imposta `$_FN['xmetadb_file_url']` in modo che `getFileUrl()` restituisca
l'indirizzo di un proprio script (per esempio un metodo `fnapp` che controlla l'utente), e
`$_FN['xmetadb_file_access']` per l'endpoint delle miniature. Con `xmetadb_file_access`
impostata, `fnapp=thumb` serve una miniatura chiesta per record (`t`, `i`, `c`: tabella, id,
campo; `d` solo il database del sito) solo se la funzione restituisce true, rifiuta un percorso
`f` dentro la cartella dei dati, e dbview chiede per record le miniature dell'elenco.

### Ordinamento Numerico
Un campo di testo che contiene numeri viene ordinato per valore quando è il campo di ordinamento
dell'elenco: `<frm_sort>numeric</frm_sort>` (oppure `abs`, per valore assoluto) nella definizione
del campo, oppure una classe di campo con un metodo `SortNumeric()` (che restituisce true,
`"numeric"` o `"abs"`). I valori vuoti vanno in fondo e la virgola è accettata come separatore
decimale. Vale con i driver SQL, quando l'elenco è ordinato per un solo campo.

### Form Offline
`op=offlineform` (con `id` per un aggiornamento) scarica un file HTML con il form, da compilare
senza accesso al sito; la scheda ne mostra il link quando `enable_offlineform` è attivo. Il
template è `form_offline.html`, con `{form}`, `{formtitle}`, `{doctitle}`, `{mailsubject}`,
`{mode}` (`insert` o `update`), `{version}`, `{adminemail}` e `{titlefield}`. I campi file e
immagine vengono esclusi; le opzioni della sezione `offlineform_hidden_fields` (campi non
mostrati) e `offlineform_text_fields` (chiavi esterne e liste mostrate come semplici campi di
testo) accettano nomi di campi separati da virgola.

### Callback al Salvataggio
`$_FN['modparams'][<sezione>]['editorparams']['table']['function_on_insert']` è il nome di una
funzione chiamata con il record inserito; `function_on_update` viene chiamata con i valori
inviati dopo ogni salvataggio del form di modifica (anche quando i valori sono stati rifiutati).
Si impostano nel `custom_functions.php` della sezione:

```php
<?php
// sections/caves/custom_functions.php
global $_FN;
function caves_on_insert($record)
{
    FN_LogEvent("new cave {$record['id']}", "caves");
}
$_FN['modparams']['caves']['editorparams']['table']['function_on_insert'] = "caves_on_insert";
```

### Permessi
- `CanViewRecords()`: l'elenco; amministratori, `groupadmin`, `groupview`, `groupinsert`, tutti se `groupview` è vuoto.
- `CanViewRecord($id, $tablename)`: un record; in più `viewonlycreator`, i gruppi di visualizzazione per record (`enable_permissions_each_records`, campo `groupview`) e `$_FN['dbview_excluded_values']`.
- `CanEditRecords()`: richiesto da ogni operazione di scrittura (`edit`, `new`, `del`, `updaterecord`, `insertrecord`, `users`, `admingroups`); falso per i visitatori anonimi, così i crawler vedono solo i record. Amministratori, `groupadmin`, `groupinsert`, qualsiasi utente collegato se `groupinsert` è vuoto.
- `CanUpdateRecord($row)`: salvare un record esistente (form di modifica e salvataggio): gli utenti accettati da `IsAdminRecord($row)` (amministratore del sito, proprietario in `username` o `user`, `groupadmin` e, con `enable_permissions_edit_each_records`, i gruppi nel campo `groupinsert` del record, o ogni utente collegato se è vuoto) e gli utenti abilitati sul record; con i gruppi di modifica per record attivi nessun altro, altrimenti anche `CanAddRecord()`.
- Eliminazione: `enable_delete`, `IsAdminRecord($row)` e una POST con il token della pagina di conferma (una GET mostra la conferma).
- `UsersForm()` (utenti abilitati su un record): `IsAdminRecord($row)`.
- Storico delle versioni (`op=history`): `IsAdmin()`, cioè amministratori del sito e `groupadmin`.

### Paginazione SQL
Con i driver SQL (`mysql`, `sqlite`, `sqlite3`, `sqlserver`), oppure con
`search_query_native_mysql`, la query dell'elenco viene eseguita dal database: `COUNT(*)` per il
totale, `LIMIT` per la pagina, `GROUP BY` per i conteggi delle categorie, nomi delle colonne fra
backquote, a parità di valore ordine per chiave primaria. Con gli altri driver la stessa query
viene eseguita dal motore di query di xmetadb. Il parametro `order` viene accettato solo se è in
`search_orders`, in `defaultorder` o è la chiave primaria; il parametro `rpp` (record per pagina)
è limitato a 200.

## Tool MCP da un'Applicazione
Il server MCP (`index.php?fnapp=mcp`) include `{src_application}/include/mcp_tools.php` dopo i
propri tool. Un tool si registra con
`FN_MCP_AddTool($name, $description, $inputSchema, $callback, $write = false)`; la callback
riceve l'array degli argomenti, restituisce un array o una stringa e chiama
`FN_MCP_Fail($message)` per restituire un errore. I tool di scrittura (`$write = true`) vengono
elencati e permessi solo agli amministratori e al gruppo `$_FN['mcp_write_group']`. I tool
vengono eseguiti come l'utente della chiave API o del token OAuth: i permessi si controllano con
le solite funzioni. Vedi la [Guida MCP](it_guida_mcp.md).

## Debugging
`$_FN['display_errors']` vale `"off"` per default (`src/include/config.vars.php`): durante lo
sviluppo impostarlo a `"on"` nel `config.vars.local.php` del sito (un valore passato al
costruttore di `FINIS` viene sovrascritto). Con `"off"`, `website` scarta anche ciò che viene
stampato fuori dal template del tema, per esempio un `echo` in uno script autoexec. Il pannello
di controllo mostra sempre gli errori.

Il framework offre diverse funzioni per il debugging:

```php
// Visualizzare un array strutturato
dprint_r($myArray);

// Logging (tabella fn_log, contesto "cms")
FN_Log("Messaggio di debug");

// Logging con un contesto
FN_LogEvent("Record importato", "mymodule");

// Tempo di esecuzione
echo FN_GetExecuteTimer(); // tempo dall'inizio in secondi

// Timer parziale
echo FN_GetPartialTimer(); // tempo dall'ultima chiamata
```

Gli eventi stanno nella tabella `fn_log` (contesto, utente, IP, data), consultabile nel pannello
di controllo in Utilità > Registro eventi, con filtri per contesto, utente, evento e date.

## Best Practices

### Sicurezza
- Sanitizzare sempre gli input utente con `FN_GetParam()`; convertire i numeri (`"int"`, `"float"`, `intval()`) prima di salvarli o di metterli in una query
- `FN_XMETADBQuery()` riceve una stringa SQL semplice, senza parametri associati: preferire i metodi delle tabelle (`GetRecords($restr)`, `InsertRecord()`, `UpdateRecord()`), che fanno l'escape dei valori, e non mettere mai stringhe della richiesta in una query
- Verificare l'autorizzazione dell'utente con `FN_UserInGroup()` o `FN_UserCanViewSection()`
- Un valore della richiesta entra nell'HTML o in un template solo dopo l'escape: `FN_GetParam("q", $_GET, "html")`, `htmlspecialchars()`, oppure `{var.addHtmlEntities()}` nel template; `FN_RewriteLink()` e `FN_TPL_ApplyTplString()` non fanno escape
- Prima di un redirect verso un URL preso dalla richiesta, controllare `FN_IsInternalUrl($url)` (vero per gli URL sotto `$_FN['siteurl']` e per gli URL relativi senza schema)
- Usare `FN_GetClientIp()` per l'indirizzo del client (legge `X-Forwarded-For` solo dai proxy elencati in `$_FN['trusted_proxies']`)
- Token, codici e password generate vengono da `random_bytes()` o `random_int()`, mai da `rand()`, `mt_rand()` o `uniqid()`
- Impostare i cookie con `FN_SetCookie($name, $value, $options)` invece di `setcookie()`, per la compatibilità con PHP 7.1
- Le operazioni di scrittura (salvataggio, eliminazione) solo in POST, con un token verificato con `hash_equals()`
- Non passare mai a `FN_UpdateUser()` l'hash della password salvato: la funzione rifà l'hash di ogni `passwd` non vuoto

```php
// anti CSRF token bound to the user, the action and the record
$token = hash_hmac("sha256", "archive|{$_FN['user']}|$id", FN_GetSiteSecret());
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!hash_equals($token, FN_GetParam("fn_csrf", $_POST))) {
        die(FN_Translate("you may not do that"));
    }
    // ... change the data
}
echo "<form method=\"post\"><input type=\"hidden\" name=\"fn_csrf\" value=\"$token\" />...</form>";

// cookie with the options on every PHP version
FN_SetCookie("mycookie", $value, array(
    'expires' => time() + 86400,
    'path' => $_FN['urlcookie'],
    'secure' => FN_IsHttps(),
    'httponly' => true,
    'samesite' => 'Lax',
));

// change the email of a user without touching the password
$values = FN_GetUser($user, false);
unset($values['passwd']);
$values['email'] = $email;
FN_UpdateUser($user, $values);
```

Il cookie di sessione, il segreto del sito (`{datadir}/secret.key.php`) e i limiti del login sono
descritti nella sezione "Login security" della [guida per LLM](Finis_Framework_LLM_Guide.md) (in
inglese).

### Compatibilità PHP
FINIS deve funzionare sia con PHP 7.1 sia con PHP 8.x. Non usare funzioni di PHP 8
(`str_starts_with()`, `str_contains()`), controllare le costanti aggiunte dopo
(`defined("JSON_INVALID_UTF8_SUBSTITUTE")`) e usare `FN_SetCookie()` (l'array di opzioni di
`setcookie()` esiste da PHP 7.3).

### Struttura del Codice
- Mantenere la separazione tra logica e presentazione
- Usare i file di configurazione per le impostazioni
- Documentare le funzioni con commenti PHPDoc
- Utilizzare le funzioni del framework anziché reimplementare le stesse funzionalità

### Performance
- `FN_GetGlobalVarValue($name, $maxtime = false)` e `FN_SetGlobalVarValue($name, $value, $ttl = 0)` conservano valori fra una richiesta e l'altra: in APCu se disponibile (con un tempo di vita facoltativo in secondi), altrimenti in `{datadir}/_cache` (con lock sui file). `$maxtime` è un orario Unix: un valore salvato prima viene scartato. Le chiavi APCu non sono separate per sito: mettere nel nome qualcosa del sito quando il valore dipende da esso. `FN_ClearCache()` svuota solo `{datadir}/_cache`
- Con i driver SQL dbview legge solo i record della pagina corrente; per i visitatori anonimi attivare la cache delle schede (`$_FN['dbview_view_cache']`)
- Considerare l'uso di query ottimizzate per database grandi
- Caricare script JS in modo asincrono

## Esempi Avanzati

### Creazione di un Plugin
Un plugin è una cartella `plugins/<nome>/` dell'applicazione che aggiunge impostazioni al
pannello di controllo. Niente al suo interno viene eseguito da solo: il codice da eseguire su
ogni pagina va in `include/autoexec.d/`.

**Struttura del plugin**:
```
plugins/
  └── myplugin/
      ├── title.it.fn          # Titolo (oppure title.i18n.fn, tradotto)
      ├── config.php           # Facoltativo: impostazioni, modificabili in Impostazioni
      └── controlcenter/
          ├── settings.php     # Pagina nel gruppo "Plugins" del pannello di controllo
          └── icon.png
```

`config.php` usa gli stessi commenti della configurazione di un modulo e si legge con
`FN_LoadConfig("plugins/myplugin/config.php")`.

**include/autoexec.d/50_myplugin.php**:
```php
<?php
global $_FN;
$config = FN_LoadConfig("plugins/myplugin/config.php");
if (!empty($config['enabled'])) {
    $_FN['section_header_footer'] .= '<script src="plugins/myplugin/js/script.js"></script>';
}
```

Per reagire agli eventi sugli utenti, definire le [funzioni di callback](#funzioni-di-callback)
in `extra/*.inc.php`:

```php
<?php
// extra/myplugin.inc.php
function FN_OnAddUser($values, $password)
{
    FN_LogEvent("new user {$values['username']}", "myplugin");
}
```

### Estensione del Pannello di Controllo
Per aggiungere funzionalità al pannello di amministrazione:

```php
<?php
// In controlcenter/sections/utilities/myplugin/section.php
global $_FN;

// Verifica permessi
if (!FN_IsAdmin()) {
    echo FN_i18n("access_denied");
    return;
}

// Interfaccia di amministrazione
echo "<h2>" . FN_i18n("my_plugin_admin") . "</h2>";

// Resto del codice per gestire le impostazioni del plugin
```

## Risoluzione Problemi Comuni

### Debugging degli Errori
- Attivare la visualizzazione degli errori in `config.vars.local.php`:
  ```php
  $_FN['display_errors'] = "on";
  ```
- Controllare i log PHP e il registro eventi (pannello di controllo, Utilità > Registro eventi; i file mensili in `misc/log/` scritti dalle versioni precedenti vi compaiono come archivio)

### Problemi con il Database
- Verificare i permessi di scrittura nelle cartelle `misc/fndatabase/`
- Controllare la connessione al database per i driver esterni (MySQL, ecc.)
- Utilizzare `$table->GetError()` per ottenere messaggi di errore dettagliati

### Problemi con i Moduli
- Assicurarsi che la struttura delle cartelle sia corretta (un modulo dell'applicazione richiede `section.php`)
- Verificare che i file di configurazione siano accessibili
- Controllare eventuali conflitti di nomi tra moduli: un modulo dell'applicazione nasconde quello di FINIS o di un'estensione con lo stesso nome
