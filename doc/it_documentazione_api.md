# Documentazione API di FINIS Framework

## Introduzione
Questa documentazione fornisce una panoramica completa dell'API (Application Programming Interface) disponibile nel framework FINIS. Questa API permette agli sviluppatori di estendere le funzionalità del framework e di interagire con esso in modo programmatico.

## Concetti Base

### Architettura dell'API
FINIS utilizza un approccio modulare con diverse API che coprono vari aspetti del sistema:

1. **API Core**: Funzioni e classi principali del framework
2. **API Database**: Interfaccia per l'accesso ai dati
3. **API Template**: Sistema di template e rendering
4. **API Moduli**: Funzionalità per estendere il sistema
5. **API REST**: Endpoint per interazioni esterne

### Convenzioni di Naming
- Le funzioni core del framework sono prefissate con `FN_` (es. `FN_GetParam()`)
- Le funzioni del pannello di controllo sono prefissate con `FNCC_` (es. `FNCC_GetSectionPath()`)
- Le funzioni del motore di template sono prefissate con `TPL_` o `FN_TPL_` (es. `TPL_ApplyTplString()`)
- Le classi iniziano con lettere maiuscole (es. `XMETATable`)
- Le costanti sono in maiuscolo con underscore (es. `_FNEXEC`)
- Le variabili globali sono all'interno dell'array `$_FN`

## API Core

### Classe FINIS
La classe principale che inizializza e gestisce il framework (`src/FINIS.php`).

#### Costruttore
```php
/**
 * Inizializza il framework FINIS
 * Copia ogni chiave di $config in $_FN, poi carica src/include/finis.php
 * (configurazione, file di include, database, login, lingua e tema)
 * @param array $config Configurazioni opzionali
 */
function __construct($config = array())
```

Esempio:
```php
require_once "path/to/FINIS.php";
$FINIS = new FINIS(array(
    "src_application" => "."
));
$FINIS->finis();
```

Le opzioni che `src/include/config.vars.php` imposta (come `display_errors`) vengono sovrascritte da quel file: vanno impostate in `config.vars.local.php`. Anche le impostazioni salvate dal pannello di controllo (`fn_settings`: `sitename`, `theme`, `siteurl`, ...) sostituiscono i valori passati al costruttore; solo `theme` e `maintenance` passati al costruttore prevalgono su `fn_settings`. Poiché il bootstrap viene eseguito dentro il costruttore, il login della richiesta (`FN_ManageLogin()`) è già avvenuto quando il costruttore termina.

#### Metodi Principali

```php
/**
 * Esegue l'applicazione richiesta con ?fnapp= (default "website")
 * tramite il dispatch di __call
 */
function finis()

/**
 * Esegue una sezione specifica, se l'utente corrente può vederla
 * @param string $section ID della sezione (default: $_FN['mod'])
 */
function runSection($section = "")

/**
 * Esegue una cartella come se fosse una sezione (section.php, oppure il
 * contenuto statico section.<lingua>.html se manca section.php)
 * @param string $folder Percorso della cartella
 */
function runFolder($folder)

/**
 * Imposta una variabile di configurazione ($_FN[$id] = $value)
 * @param string $id Nome della variabile
 * @param mixed $value Valore
 */
function setVar($id, $value)

/**
 * Imposta i parametri di una tabella ($_FN['tables'][$tablename] = $params).
 * Vengono uniti ai parametri di FN_XMDBTable(), FN_XMDBForm() e
 * FN_XMETATableEditor() per quella tabella, e passati a FN_XMETADBQuery()
 * @param string $tablename Nome della tabella
 * @param array $params Parametri della tabella (es. impostazioni del driver)
 */
function setTable($tablename, $params)

/**
 * Verifica se l'applicazione è in esecuzione in modalità console
 * ($_FN['consolemode'], vero quando $_SERVER['SHELL'] è impostato)
 * @return bool
 */
function isConsole()

/**
 * Registra un'estensione: una cartella con moduli, sezioni, sezioni del
 * pannello di controllo e tabelle aggiuntive, usata senza copiarla nel sito.
 * Il percorso viene aggiunto a $_FN['path_extensions']; lo script si ferma
 * con "<path> not exists" se la cartella non esiste
 * @param string $path_src Percorso della cartella dell'estensione
 */
function addExtension($path_src)
```

`setVar()`, `setTable()` e `addExtension()` agiscono dopo il bootstrap: vanno chiamati prima di `finis()`.

#### Metodi Applicazione (`fnapp`)
Ogni metodo non definito nella classe è gestito da `__call($name, $arguments)`, che include un file PHP con il nome del metodo. `finis()` lo chiama con il valore di `?fnapp=` (o `website`), e uno script può chiamare direttamente un metodo, es. `$FINIS->website()`.

Il nome può contenere solo lettere, cifre e `_` (altrimenti viene lanciata una `Exception` "Method does not exist"). Il file viene cercato, in questo ordine, in:

1. `{src_application}/include/applications/{name}.php`
2. `{src_finis}/include/applications/{name}.php`
3. `{src_application}/include/methods/{name}.php`
4. `{src_finis}/include/methods/{name}.php`

Viene incluso (`include_once`) il primo file trovato; se non ne esiste nessuno viene lanciata una `Exception` "Method {name} does not exist". Un'applicazione può quindi aggiungere i propri metodi o sostituire quelli di FINIS.

Metodi forniti da FINIS (`src/include/methods/`):

| Metodo | Scopo |
|---|---|
| `website` | La pagina del sito (tema + sezione); metodo di default |
| `api` | Esegue la sezione e stampa `$_FN['return']` in JSON (vedi [API REST](#api-rest)) |
| `controlcenter` | Pannello di controllo |
| `filemanager` | File manager |
| `install` | Procedura di installazione |
| `captcha` | Immagine del captcha (`&t=<nome>`) |
| `thumb` | Miniature delle immagini e dei file dei record |
| `print` | Versione stampabile di una pagina |
| `notifications` | Notifiche dell'utente |
| `cron` | Operazioni pianificate |
| `oAuth` | Login con provider OAuth esterni |
| `mcp` | Server MCP per i client LLM |
| `oauthserver` | Server di autorizzazione OAuth 2.1 per i client MCP |

#### Estensioni
Un'estensione è una cartella con la stessa struttura dell'applicazione (`modules/`, `sections/`, `controlcenter/sections/`, `misc/fndatabase/`, `include/install/`). Esempio (`examples/ecommerce/index.php`):

```php
require_once "../../src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("../../fncommerce/");
$FINIS->finis();
```

I file vengono cercati nell'applicazione, poi in FINIS, poi nelle estensioni, nell'ordine in cui sono state aggiunte: `FN_GetModulePath()`, `FNCC_GetSectionPath()`, `FNCC_GetSectionTitle()`, `FN_LoadConfig()`, `FN_FromTheme()` e `FN_HtmlEditConfFile()` conoscono le estensioni, e i tipi di modulo delle estensioni vengono registrati in `fn_sectionstypes`. L'installazione copia nel sito le cartelle `sections/` e `misc/fndatabase/` delle estensioni (i file esistenti vengono mantenuti).

### Funzioni Generali

#### Gestione Parametri
```php
/**
 * Ottiene un parametro da una variabile
 * @param string $key Nome del parametro
 * @param array $var Array da cui estrarre il parametro (default: $_REQUEST)
 * @param string $type Tipo di dato: html (htmlentities con ENT_QUOTES), int,
 *                     float, o il nome di una funzione applicata al valore
 * @return mixed Valore del parametro ("" se manca o se il valore è un array)
 */
function FN_GetParam($key, $var = false, $type = "")
```

Esempio:
```php
// Ottieni il parametro 'id' dalla richiesta GET
$id = FN_GetParam("id", $_GET, "int");

// Ottieni il parametro 'name' dalla richiesta POST e sanitizzalo come HTML
$name = FN_GetParam("name", $_POST, "html");
```

#### Gestione File e Path
```php
/**
 * Ottiene il path per un file con considerazione del tema. Un path relativo
 * viene cercato in FINIS, poi nelle estensioni; una copia del file nel tema
 * (themes/<tema>/ dell'applicazione, poi di FINIS) ha la precedenza
 * @param string $file File da cercare
 * @param bool $absolute Se restituire un URL assoluto
 * @return string Path al file
 */
function FN_FromTheme($file, $absolute = true)

/**
 * Path di un file di FINIS nel sito (alias di FN_FinisPathToApplicationPath)
 * @param string $filepath Path del file
 * @param bool $urlAbsolute Se restituire URL assoluto
 * @return string Path completo
 */
function FN_PathSite($filepath, $urlAbsolute = false)

/**
 * Converte un path sotto src_finis nello stesso path sotto src_application,
 * copiandovi il file (o i file della cartella) se mancano
 * @param string $filepath Path del file
 * @param bool $urlAbsolute Se restituire URL assoluto
 * @return string Path relativo all'applicazione, o URL
 */
function FN_FinisPathToApplicationPath($filepath, $urlAbsolute = false)

/**
 * Restituisce l'icona appropriata per un tipo di file
 * @param string $filename Nome del file
 * @return string URL dell'icona (images/mime/ del tema)
 */
function FN_GetIconByFilename($filename)

/**
 * Icona di un file come carattere unicode (entità HTML), in base
 * all'estensione: nessuna immagine da caricare
 * @param string $filename Nome del file
 * @return string Entità HTML, es. "&#x1F4C4;"
 */
function FN_GetIconCharByFilename($filename)
```

#### Localizzazione e Traduzione
```php
/**
 * Traduce una stringa nella lingua corrente (file .csv delle lingue)
 * @param string $constant Testo da tradurre
 * @param string $language Lingua (default: lingua corrente)
 * @param string $uppercasemode Maiuscole opzionali: "Aa", "aa", "AA", "Aa Aa"
 * @return string Testo tradotto
 */
function FN_i18n($constant, $language = "", $uppercasemode = "")

/**
 * Traduce una stringa, con l'iniziale maiuscola per default
 * @param string $english_string Testo da tradurre
 * @param string $uppercasemode "Aa" (default), "aa", "AA", "Aa Aa"
 * @param string $language Lingua (opzionale)
 * @return string Testo tradotto
 */
function FN_Translate($english_string, $uppercasemode = "Aa", $language = "")

/**
 * Ottiene il titolo di una cartella nella lingua corrente
 * @param string $path Percorso della cartella
 * @param string $lang Lingua (opzionale)
 * @return string Titolo localizzato
 */
function FN_GetFolderTitle($path, $lang = "")

/**
 * Imposta il titolo di una cartella
 * @param string $path Percorso della cartella
 * @param string $title Titolo da impostare
 * @param string $lang Lingua (opzionale)
 */
function FN_SetFolderTitle($path, $title, $lang = "")
```

#### Date e Orari
```php
/**
 * Restituisce la data e ora corrente formattata
 * @param string $format Formato data (default: Y-m-d H:i:s)
 * @return string Data formattata
 */
function FN_Now($format = "Y-m-d H:i:s")

/**
 * Formatta una data nel formato localizzato
 * @param string $time Data/ora da formattare
 * @param bool $showtime Mostra anche l'ora
 * @return string Data formattata
 */
function FN_FormatDate($time, $showtime = true)

/**
 * Restituisce un timestamp Unix
 * @return int Timestamp corrente
 */
function FN_Time()
```

#### Logging e Debug
```php
/**
 * Registra un evento nella tabella fn_log (context, event,
 * user = $_FN['user'], ip, date)
 * @param string $event Messaggio dell'evento
 * @param string $context Contesto dell'evento (minuscolo, [a-z0-9_])
 */
function FN_LogEvent($event, $context = "cms")

/**
 * Scrive un messaggio nel log (FN_LogEvent), e lo invia per email a
 * $_FN['log_email_address'] quando $_FN['enable_log_email'] vale 1
 * @param string $txt Messaggio da loggare
 */
function FN_Log($txt)

/**
 * Ottiene il tempo di esecuzione dall'inizio
 * @return string Tempo di esecuzione in secondi
 */
function FN_GetExecuteTimer()

/**
 * Ottiene il tempo parziale dall'ultima chiamata
 * @return string Tempo parziale e totale
 */
function FN_GetPartialTimer()
```

#### Notifiche
```php
/**
 * Aggiunge una notifica per utenti specifici
 * @param mixed $notificationvalues Contenuto notifica o array
 * @param mixed $users Username o array di username
 */
function FN_AddNotification($notificationvalues, $users)

/**
 * Ottiene le notifiche non visualizzate di un utente
 * @param string $user Username
 * @param string $context Contesto opzionale
 * @return array Lista di notifiche
 */
function FN_GetNotificationsUndisplayed($user, $context = "")

/**
 * Segna una notifica come visualizzata
 * @param int $id ID della notifica
 */
function FN_SetNotificationDisplayed($id)
```

## API Database

> **Nota**: Per una documentazione più completa sul sistema di database, consultare la [Guida al Layer di Astrazione Database](it_guida_database.md).

### Classe XMETATable
Classe principale per interagire con il database (`src/include/xmetadb/`, namespace `Xmetadb`; i vecchi nomi delle classi come `XMETATable` e `XMETADatabase` restano disponibili come alias).

#### Creazione e Inizializzazione
```php
/**
 * Ottiene un'istanza della tabella
 * @param string $tablename Nome della tabella
 * @param array $params Parametri opzionali (uniti a $_FN['tables'][$tablename])
 * @return object Istanza di XMETATable
 */
function FN_XMDBTable($tablename, $params = array())
```

Esempio:
```php
// Ottieni un'istanza della tabella utenti
$usersTable = FN_XMDBTable("fn_users");
```

#### Operazioni CRUD

```php
/**
 * Inserisce un nuovo record
 * @param array $values Dati del record
 * @return mixed Il record salvato (array); false o una stringa di errore in caso di errore
 */
function InsertRecord($values)

/**
 * Aggiorna un record esistente
 * @param array $values Nuovi dati, con la chiave primaria
 * @param mixed $pkvalue Valore della chiave primaria (default: preso da $values)
 * @return mixed Il record salvato (array); false o una stringa di errore in caso di errore
 */
function UpdateRecord($values, $pkvalue = false)

/**
 * Aggiorna un record tramite chiave primaria
 * @param array $values Nuovi dati
 * @param string $pkey Nome campo chiave primaria
 * @param mixed $pvalue Valore chiave primaria
 * @return mixed Il record salvato (array); false o una stringa di errore in caso di errore
 */
function UpdateRecordBypk($values, $pkey, $pvalue)

/**
 * Elimina un record
 * @param mixed $pkvalue Valore chiave primaria
 * @return bool Successo
 */
function DelRecord($pkvalue)

/**
 * Ottiene i record
 * @param mixed $restr Filtro: array(campo => valore), o una condizione SQL
 *                     in forma di stringa con i driver SQL
 * @param int $min Primo record
 * @param int $length Numero di record
 * @param string $order Campo/i di ordinamento
 * @param bool $reverse Ordine decrescente
 * @param array $fields Campi da leggere
 * @return array Lista di record
 */
function GetRecords($restr = false, $min = false, $length = false, $order = false, $reverse = false, $fields = false)

/**
 * Ottiene un record per chiave primaria
 * @param mixed $unirecid Valore chiave primaria
 * @return array Record; null se non trovato (il driver mysql restituisce
 *               false se la query fallisce)
 */
function GetRecordByPrimaryKey($unirecid)
```

Esempio:
```php
$productsTable = FN_XMDBTable("products");

// Inserisci un nuovo record
$product = $productsTable->InsertRecord(array(
    'name' => 'Lampada',
    'price' => '12.50'
));

// Aggiornalo
$productsTable->UpdateRecord(array(
    'id' => $product['id'],
    'price' => '11.90'
));

// Ottieni un record specifico
$product = $productsTable->GetRecordByPrimaryKey(5);

// Ottieni record filtrati
$lampade = $productsTable->GetRecords(array('name' => 'Lampada'));

// Elimina un record
$productsTable->DelRecord(5);
```

Per creare o modificare utenti usa `FN_AddUser()` e `FN_UpdateUser()` (vedi [Gestione Utenti](#gestione-utenti)): calcolano l'hash della password.

#### Query Avanzate
```php
/**
 * Esegue una query in stile SQL (XMETADatabase, con ogni driver)
 * @param string $query Query SQL
 * @param array $params Parametri opzionali
 * @return array Risultati query
 */
function FN_XMETADBQuery($query, $params = array())
```

Esempio:
```php
// Query personalizzata
$results = FN_XMETADBQuery("
    SELECT username, email
    FROM fn_users
    WHERE active = 1
    ORDER BY username
");
```

### Form
`FN_XMDBForm()` restituisce l'oggetto form di una tabella (classe `FieldFrm`, `src/include/xmetadb_frm.php`); l'oggetto tabella è nella sua proprietà `xmltable`.

```php
/**
 * Ottiene un'istanza del form per una tabella
 * @param string $tablename Nome della tabella
 * @param array $params Parametri opzionali (uniti a $_FN['tables'][$tablename])
 * @return object Istanza di FieldFrm
 */
function FN_XMDBForm($tablename, $params = array())
```

Metodi principali: `HtmlShowInsertForm($currentleveladmin = false, $values = false, $errors = false)`, `HtmlShowUpdateForm($pk, $currentleveladmin = false, $nvalues = false, $errors = false)`, `HtmlShowView($values, $currentleveladmin = false, $errors = false)`, `GetByPost($oldvalues = array())`, `Verify($newvalues, $update = false, $pk = null)`, `InsertRecord($newvalues)`, `UpdateRecord($newvalues, $pkvalue = false)`. A differenza dei metodi della tabella, i metodi del form convertono i valori dei campi (es. un campo `cryptpasswd` viene trasformato in hash).

Esempio:
```php
$form = FN_XMDBForm("products");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $values = $form->GetByPost();
    $errors = $form->Verify($values);
    if (count($errors) == 0) {
        $form->InsertRecord($values);
    }
}
echo $form->HtmlShowInsertForm();
```

## API Template

### Gestione Template
```php
/**
 * Carica una configurazione da un file (applicazione, FINIS, estensioni)
 * @param string $fileconfig Path del file di configurazione
 * @param string $sectionid ID sezione
 * @param bool $usecache Usa cache
 * @return array Configurazione
 */
function FN_LoadConfig($fileconfig = "", $sectionid = "", $usecache = true)

/**
 * Esegue una sezione e restituisce HTML
 * @param string $folder Path della sezione
 * @param bool $usecache Usa cache
 * @return string HTML generato
 */
function FN_HtmlContent($folder, $usecache = true)

/**
 * Carica il contenuto statico section.<lingua>.html di una cartella
 * @param string $folder Path della sezione
 * @param bool $usecache Usa cache
 * @return string HTML
 */
function FN_HtmlStaticContent($folder, $usecache = false)

/**
 * Include CSS dal framework e sezioni
 * @return string Tag HTML per CSS
 */
function FN_IncludeCSS()

/**
 * Include JavaScript dal framework
 * @return string Tag HTML per JavaScript
 */
function FN_IncludeJS()

/**
 * Applica le variabili a un template ({var}, blocchi if/foreach)
 * @param string $str Template
 * @param array $vars Variabili
 * @param string $basepath Path base del template
 * @param array $config Opzioni
 * @return string HTML
 */
function TPL_ApplyTplString($str, $vars, $basepath = false, $config = array())

/**
 * Come TPL_ApplyTplString() con le variabili di FINIS ($_FN, i18n, link)
 * @param string $str Template
 * @param array $vars Variabili
 * @param string $basepath Path base del template
 * @return string HTML
 */
function FN_TPL_ApplyTplString($str, $vars, $basepath = false)
```

Le funzioni di template non applicano escape ai valori: scrivi `{var.addHtmlEntities()}` nel template, oppure applica l'escape al valore in PHP (vedi [Sicurezza](#sicurezza)).

### Manipolazione HTML e URL
```php
/**
 * Converte BBCode in HTML
 * @param string $string Testo con BBCode
 * @return string HTML risultante
 */
function FN_Tag2Html($string)

/**
 * Riscrive un link secondo le impostazioni mod_rewrite del sito
 * @param string $href Link (es. "index.php?mod=news")
 * @param string $sep Separatore dei parametri ("&" o "&amp;")
 * @param bool $full Restituisce un URL assoluto
 * @return string Link
 */
function FN_RewriteLink($href, $sep = "", $full = false)

/**
 * Converte link relativi in assoluti
 * @param string $str Contenuto HTML
 * @param string $folder Directory di base
 * @return string HTML con link assoluti
 */
function FN_RewriteLinksLocalToAbsolute($str, $folder)

/**
 * Converte gli URL assoluti del sito in link relativi a una cartella
 * (la conversione applicata dagli editor al salvataggio)
 * @param string $content Contenuto HTML
 * @param string $directory Directory di base (es. "sections/<id>")
 * @return string HTML con link locali
 */
function FN_RewriteLinksAbsoluteToLocal($content, $directory)

/**
 * Normalizza tutti i path in un contenuto HTML
 * @param string $content Contenuto HTML
 * @return string Contenuto con path normalizzati
 */
function FN_NormalizeAllPaths($content)

/**
 * Normalizza un singolo path
 * @param string $path Path da normalizzare
 * @return string Path normalizzato
 */
function FN_NormalizePath($path)
```

### Parametri del Metodo Website
Il metodo `website` (`src/include/methods/website.php`) accetta due parametri per la navigazione ajax e le copie offline (service worker):

- `fnajax=1`: solo l'output della sezione, senza il tema, in una pagina HTML minima il cui `<title>` è il titolo della pagina completa. L'intestazione della pagina viene costruita prima (`FN_HtmlHeader()`), quindi i suoi hook vengono eseguiti come per la pagina completa. La navigazione ajax di dbview lo aggiunge agli URL che carica.
- `fnshell=1`: la pagina con il tema e il marcatore `<!--fnsection-->` al posto della sezione, che non viene generata. Ignorato insieme a `fnajax=1`.

Ogni pagina del metodo `website` ha queste intestazioni di risposta:

| Intestazione | Valore |
|---|---|
| `X-Finis-Visitor` | `public` per una GET di un visitatore anonimo (la stessa pagina per ogni visitatore anonimo, può essere conservata offline), `private` altrimenti |
| `X-Finis-Section` | La sezione (`$_FN['mod']`) |
| `X-Finis-Lang` | La lingua (`$_FN['lang']`) |

I form di login e logout rimuovono `fnajax` e `fnshell` dall'URL a cui inviano i dati.

## API Sezioni

### Gestione Sezioni
```php
/**
 * Verifica se un utente può visualizzare una sezione
 * @param string $section ID sezione (default: sezione corrente)
 * @param string $user Username (default: utente corrente)
 * @return bool
 */
function FN_UserCanViewSection($section = "", $user = "")

/**
 * Verifica se un utente può modificare una sezione
 * @param string $section ID sezione (default: sezione corrente)
 * @param string $user Username (default: utente corrente)
 * @return bool
 */
function FN_UserCanEditSection($section = "", $user = "")

/**
 * Ottiene i valori di una sezione
 * @param string $section ID sezione
 * @param bool $usecache Usa cache
 * @return array Dati sezione
 */
function FN_GetSectionValues($section, $usecache = true)

/**
 * Esegue una sezione: section.php del suo modulo (FN_GetModulePath) o di
 * sections/<id>/, oppure il contenuto statico
 * @param string $section ID sezione
 * @param bool $return_html Se restituire l'output invece di stamparlo
 * @return string Output (vuoto se stampato)
 */
function FN_RunSection($section, $return_html)

/**
 * Esegue una cartella come sezione
 * @param string $folder Path cartella
 * @param bool $return_html Se restituire l'output
 * @return string Output (vuoto se stampato)
 */
function FN_RunFolder($folder, $return_html)

/**
 * HTML della sezione con le opzioni di amministrazione, oppure il messaggio
 * "you don't have permission to view this page"
 * @param string $section ID sezione (default: sezione corrente)
 * @return string HTML
 */
function FN_HtmlSection($section = "")

/**
 * Riempie i titoli e le descrizioni per lingua vuoti delle sezioni nel
 * database con i valori di sections/<id>/default.xml.php (usata dalla
 * procedura di installazione dopo la scelta delle lingue)
 * @return int Numero di sezioni aggiornate
 */
function FN_SyncSectionsFromDefaults()
```

### Navigazione
```php
/**
 * Ottiene le sezioni sotto un genitore
 * @param string $section Sezione genitore ("" = radice)
 * @param bool $recursive Include le sottosezioni
 * @param bool $onlyreadable Solo le sezioni visibili all'utente
 * @param bool $hidden Include le sezioni nascoste
 * @param bool $onlyenabled Solo le sezioni abilitate
 * @param bool $nocache Rilegge
 * @return array Sezioni
 */
function FN_GetSections($section = "", $recursive = false, $onlyreadable = true, $hidden = false, $onlyenabled = true, $nocache = false)

/**
 * Voci di menu ad albero (le voci nascoste da FN_MenuSectionIsVisible() sono escluse)
 * @param string $parent Sezione genitore
 * @return array Voci menu
 */
function FN_GetMenuTree($parent = "")

/**
 * Indica se una voce di menu porta a contenuti che l'utente può vedere.
 * Gli amministratori vedono tutto; una sezione dbview è visibile solo ai
 * gruppi della configurazione del modulo (groupadmin, groupview,
 * groupinsert) quando groupview è impostato; una sezione table_editor solo
 * agli amministratori
 * @param array $section Valori della sezione (FN_GetSections())
 * @return bool
 */
function FN_MenuSectionIsVisible($section)

/**
 * Ottiene il percorso di navigazione (briciole di pane): la sezione e i suoi
 * genitori, dalla radice
 * @param string $section ID sezione (default: sezione corrente)
 * @return array Percorso
 */
function FN_GetSectionsTree($section = "")
```

## API Utenti e Gruppi

### Gestione Utenti
```php
/**
 * Verifica se un utente è amministratore (level >= 10)
 * @param string $user Username (default: utente corrente)
 * @return bool
 */
function FN_IsAdmin($user = false)

/**
 * Ottiene i dati di un utente (con l'hash della password in passwd: non
 * restituirlo mai al client)
 * @param string $user Username
 * @param bool $usecache Usa la cache della richiesta
 * @return array Dati utente o null
 */
function FN_GetUser($user, $usecache = array())

/**
 * I valori dell'utente senza passwd, password, apikey, apikey_enc e rnd (e senza il
 * campo password del form, quando ha un altro nome): da usare per un record utente
 * messo in $_FN['return'] o inviato a un client
 * @param array $values Record dell'utente
 * @param string $passwordfield Nome del campo password del form
 * @return array
 */
function FN_UserValuesForOutput($values, $passwordfield = "")

/**
 * Ottiene gli utenti
 * @param array $arrayfilter Filtro opzionale
 * @return array Utenti
 */
function FN_GetUsers($arrayfilter = false)

/**
 * Crea un utente. Lo username viene salvato in minuscolo salvo
 * $_FN['username_case_sensitive']; gli username fatti solo di punti sono
 * rifiutati. La password (terzo argomento o "passwd") viene trasformata in
 * hash con password_hash(). In caso di successo chiama l'hook
 * FN_OnAddUser($newvalues, $password)
 * @param array $newvalues Dati utente
 * @param string $password Password in chiaro
 * @return bool True solo se il record è stato salvato (uno username esistente è rifiutato)
 */
function FN_AddUser($newvalues, $password = "")

/**
 * Aggiorna un utente. Un passwd non vuoto (o $password) viene trasformato in
 * hash: non passare mai l'hash letto da FN_GetUser(), elimina invece passwd.
 * Una nuova password revoca la chiave API. Attivare o disattivare un utente
 * cancella il suo codice di registrazione. Chiama l'hook
 * FN_OnUpdateUser($newvalues, $password)
 * @param string $user Username
 * @param array $newvalues Nuovi dati
 * @param string $password Nuova password in chiaro
 * @return mixed Il record salvato (array); false o una stringa di errore in caso di errore
 */
function FN_UpdateUser($user, $newvalues, $password = "")

/**
 * Elimina un utente e la cartella dei suoi file; chiama l'hook
 * FN_OnDeleteUser($uservalues)
 * @param string $user Username
 */
function FN_DeleteUser($user)

/**
 * Verifica se un utente appartiene a un gruppo
 * @param string $user Username
 * @param string $group Nome gruppo o gruppi separati da virgola
 * @return bool
 */
function FN_UserInGroup($user, $group)

/**
 * Aggiunge o rimuove un utente da un gruppo
 * @param string $user Username
 * @param string $group Nome gruppo
 * @param bool $member true = aggiunge, false = rimuove
 * @return bool Successo
 */
function FN_SetUserGroup($user, $group, $member)

/**
 * Crea un gruppo se non esiste
 * @param string $groupname Nome gruppo
 */
function FN_CreateGroupIfNotExists($groupname)
```

### Autenticazione
Lo stato del login viene dai cookie `fnuser` e `secid`; per i dettagli vedi [Login security](Finis_Framework_LLM_Guide.md#login-security).

```php
/**
 * Gestisce il login della richiesta; chiamata dal bootstrap per ogni
 * richiesta web. Gestisce il form di login (?fnlogin=login con username e
 * password in POST, captcha, limite dei tentativi), ?fnlogin=logout, il
 * controllo dei cookie e, senza una sessione da cookie, un access token
 * OAuth o una chiave API inviati nelle intestazioni. Imposta $_FN['user']
 */
function FN_ManageLogin()

/**
 * Verifica una password. Gli hash moderni solo con password_verify (con
 * nuovo hash quando serve); i vecchi valori md5 e in chiaro vengono
 * trasformati in hash al primo login riuscito.
 * $_FN['FN_VerifyUserPassword'] può indicare una funzione che sostituisce
 * questo controllo
 * @param string $fnuser Username
 * @param string $fnpwd Password
 * @return bool
 */
function FN_VerifyUserPassword($fnuser, $fnpwd)

/**
 * Inizia una sessione utente: imposta i cookie fnuser e secid (HttpOnly,
 * SameSite=Lax, Secure su HTTPS). Non verifica la password. Con
 * $_FN['remember_login'] e $rememberlogin i cookie durano
 * $_FN['remember_login_days'] giorni (default 30), altrimenti la sessione del browser
 * @param string $fnuser Username
 * @param bool $rememberlogin Cookie persistente
 * @return bool false se l'utente non esiste o non è attivo
 */
function FN_Login($fnuser, $rememberlogin = false)

/**
 * Termina la sessione utente corrente (cancella i cookie di login)
 */
function FN_Logout()

/**
 * Verifica il cookie secid di $_FN['user'] (HMAC con il segreto del sito);
 * false per un utente non attivo
 * @return bool
 */
function FN_CheckUser()

/**
 * Registra un tentativo di login di uno username dall'IP del client, prima
 * del controllo della password. Rifiutato dopo $_FN['login_max_attempts']
 * tentativi (default 5) in $_FN['login_lockout_minutes'] minuti (default 15)
 * @param string $user Username
 * @return bool false se lo username è bloccato da questo IP
 */
function FN_LoginAttempt($user)

/**
 * Azzera i tentativi di login di uno username dall'IP del client
 * @param string $user Username
 */
function FN_LoginResetAttempts($user)

/**
 * Valore del cookie secid: HMAC-SHA256 di "user|passwd" con il segreto del sito
 * @param string $user Username
 * @param string $passwd Hash della password salvato
 * @return string
 */
function FN_GetSecid($user, $passwd)

/**
 * Indica se una password in chiaro è quella salvata (qualsiasi formato salvato)
 * @param string $password Password in chiaro
 * @param string $stored Hash salvato
 * @return bool
 */
function FN_SamePassword($password, $stored)
```

### Funzioni per Richiesta e Sito
```php
/**
 * setcookie() con l'array di opzioni (expires, path, domain, secure,
 * httponly, samesite) su ogni versione di PHP: prima di PHP 7.3 SameSite
 * viene aggiunto al path
 * @param string $name Nome del cookie
 * @param string $value Valore
 * @param array $opts Opzioni
 * @return bool
 */
function FN_SetCookie($name, $value, $opts = array())

/**
 * Indirizzo IP del client: REMOTE_ADDR, oppure, quando REMOTE_ADDR è uno di
 * $_FN['trusted_proxies'], l'ultimo indirizzo in X-Forwarded-For non
 * aggiunto da un proxy fidato
 * @return string
 */
function FN_GetClientIp()

/**
 * Vero quando la richiesta usa HTTPS (HTTPS=on o X-Forwarded-Proto: https)
 * @return bool
 */
function FN_IsHttps()

/**
 * Vero quando l'URL punta a questo sito (sotto $_FN['siteurl'], o relativo
 * senza schema). Va verificato prima di un redirect a un URL della richiesta
 * @param string $url
 * @return bool
 */
function FN_IsInternalUrl($url)

/**
 * Segreto del sito, letto da {datadir}/secret.key.php o creato una volta
 * con random_bytes(32) (permessi 0600). Firma i cookie di login e i token
 * OAuth e cifra le copie delle chiavi API: cancellare il file disconnette
 * tutti gli utenti. Non va mai messo nel repository
 * @return string
 */
function FN_GetSiteSecret()

/**
 * Nuovo codice per il captcha $name (fnlogin_code per il form di login,
 * security_code per il form di registrazione), disegnato da
 * ?fnapp=captcha&t=$name. I codici degli altri form vengono mantenuti
 * @param string $name Nome del captcha
 * @return int Il codice (random_int(1000, 9999))
 */
function FN_NewCaptchaCode($name)
```

### Chiavi API, MCP e Server OAuth
Ogni utente può avere una chiave API personale, usata dai client API e MCP; il server OAuth 2.1 rilascia access token ai client MCP. Documentazione completa: [Server MCP e chiavi API](it_guida_mcp.md).

- Chiavi API (`src/include/auth/local.php`): `FN_GetRequestApiKey()`, `FN_LoginByApiKey($apikey)`, `FN_GetUserByApiKey($apikey)`, `FN_GenerateApiKey($user)`, `FN_RevokeApiKey($user)`, `FN_ApiKeyInfo($user)`, `FN_ApiKeyReveal($user)`, `FN_ApiKeyHash($apikey)`, `FN_UserCanUseApiKey($user)`, `FN_UserCanWriteByApiKey($user)`, `FN_McpWriteGroup()`, `FN_ProfileApiKey($user, &$tplvars)`, `FN_ApiKeyFormToken()`, `FN_GetMcpEndpoints()`, `FN_EnsureUserApiKeyField()`; `FN_GetUserApiKey($user, $generate = false)` è deprecata.
- Strumenti MCP (`src/include/methods/mcp.php`, disponibili in `{src_application}/include/mcp_tools.php`): `FN_MCP_AddTool($name, $description, $inputSchema, $callback, $write = false)`, `FN_MCP_Fail($message)`, `FN_MCP_CanWrite()`.
- Server OAuth (`src/include/functions_oauth_server.inc.php`): `FN_OAuthServerLoginByToken($token)`, `FN_OAuthServerChallenge($resource)`.

### Hook degli Utenti
Il framework chiama queste funzioni quando l'applicazione le definisce:

| Funzione | Chiamata da |
|---|---|
| `FN_OnAddUser($newvalues, $password)` | `FN_AddUser()`, dopo il salvataggio del record |
| `FN_OnUpdateUser($newvalues, $password)` | `FN_UpdateUser()`, dopo il salvataggio del record |
| `FN_OnDeleteUser($uservalues)` | `FN_DeleteUser()` |
| `FN_OnConfirmUser($uservalues)` | Il modulo login, quando una registrazione viene confermata |

## API File e Directory

### Operazioni su File
```php
/**
 * Scrive contenuto in un file
 * @param string $string Contenuto
 * @param string $file Path file
 * @param string $mode Modalità (default: w)
 * @return bool Successo
 */
function FN_Write($string, $file, $mode = "w")

/**
 * Copia un file (dentro la cartella se $d è una cartella)
 * @param string $s File origine
 * @param string $d File destinazione
 * @param bool $createdir Crea la cartella di destinazione se manca
 * @return bool Successo
 */
function FN_Copy($s, $d, $createdir = false)

/**
 * Copia una directory ricorsivamente
 * @param string $fromDir Dir origine
 * @param string $toDir Dir destinazione
 * @param bool $verbose Stampa i path copiati
 * @return bool Successo
 */
function FN_CopyDir($fromDir, $toDir, $verbose = false)

/**
 * Crea una directory con le cartelle superiori
 * @param string $pathname Path directory
 * @return bool Successo
 */
function FN_MkDir($pathname)

/**
 * Rimuove una directory ricorsivamente (un path con "../" ferma lo script)
 * @param string $dirtodelete Path directory
 */
function FN_RemoveDir($dirtodelete)

/**
 * Copia un file in <file>.<mtime>.<YmdHis>.<utente>.bak~ prima di
 * modificarlo (solo se il file esiste e non è vuoto)
 * @param string $file Path file
 */
function FN_BackupFile($file)
```

### Utilità File
```php
/**
 * Ottiene l'estensione di un file
 * @param string $filename Nome file
 * @return string Estensione ("" senza punto)
 */
function FN_GetFileExtension($filename)

/**
 * Legge un file CSV con una riga di intestazione
 * @param string $filename Nome file
 * @param string $delimiter Separatore dei campi
 * @param string $enclosure Carattere di delimitazione
 * @param string $escape Carattere di escape
 * @return array Righe come array indicizzati con i nomi delle colonne
 */
function FN_ReadCsvDatabase($filename, $delimiter, $enclosure = '"', $escape= '\\')
```

## API Comunicazione

### Email
```php
/**
 * Invia un'email
 * @param string $to Destinatario
 * @param string $subject Oggetto
 * @param string $body Corpo messaggio
 * @param bool $ishtml Se il contenuto è HTML
 * @param string $from Mittente (opzionale)
 * @return bool Successo
 */
function FN_SendMail($to, $subject, $body, $ishtml = false, $from = "")

/**
 * Corregge i caratteri di nuova riga per email
 * @param string $text Testo da correggere
 * @return string Testo corretto
 */
function FN_FixNewline($text)
```

### HTTP
```php
/**
 * Redirect a un'altra pagina (scarta i buffer di output e ferma lo script).
 * Un URL della richiesta va prima verificato con FN_IsInternalUrl()
 * @param string $url URL di destinazione
 */
function FN_Redirect($url)

/**
 * Verifica se il referer è esterno
 * @return bool
 */
function FN_IsExternalReferer()

/**
 * Invia un file al browser per il download (scarta i buffer di output e
 * ferma lo script)
 * @param string $filecontents Contenuto del file
 * @param string $filename Nome del file
 * @param string $HeaderContentType Tipo MIME
 */
function FN_SaveFile($filecontents, $filename, $HeaderContentType = "application/force-download")
```

## API REST

### Endpoint Base
Il metodo `api` esegue una sezione come il metodo `website`, scarta il suo output HTML e stampa l'array `$_FN['return']` in JSON:

```
http://tuosito.com/index.php?fnapp=api&mod=id_sezione&param1=valore1
```

Una sezione (o un modulo) restituisce dati riempiendo `$_FN['return']`; lo stesso codice serve la pagina e l'API. La risposta ha `Cache-Control: no-cache` e le intestazioni CORS `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Methods: GET, POST, OPTIONS`, `Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With`. Con `&debug=1` il metodo mostra una pagina HTML con i parametri e il valore restituito.

### Autenticazione
Le richieste `fnapp=api` vengono autenticate da `FN_ManageLogin()`, come ogni altra richiesta:

1. **Sessione da cookie**: i cookie `fnuser` e `secid` impostati da un login. Un client può fare login con una POST a `index.php?fnapp=api&mod=login&fnlogin=login` con `username` e `password` (più `fnlogin_code` quando il captcha è attivo), e poi inviare i cookie ricevuti.
2. **Access token OAuth**: senza una sessione da cookie, il valore di `Authorization: Bearer <token>` viene prima verificato come access token rilasciato da `fnapp=oauthserver` (`$_FN['auth_method'] = "oauth"`).
3. **Chiave API**: altrimenti il valore di `Authorization: Bearer <chiave>` o dell'intestazione `X-Api-Key: <chiave>` viene verificato come chiave API di un utente attivo ammesso da `$_FN['apikey_groups']` (`$_FN['auth_method'] = "apikey"`). Una chiave nell'URL (`&apikey=`) è accettata solo dall'endpoint MCP.

Chiavi API e token non creano cookie né sessioni: vanno inviati con ogni richiesta.

La sessione lato server (`FN_GetSessionValue()`, usata per esempio dal captcha) è identificata solo dal cookie `fnsid`, mai dall'URL. Quando la richiesta la usa, l'id è anche in `$_FN['return']['fnsid']`: un client senza gestione dei cookie lo rimanda come `Cookie: fnsid=<id>`.

Dati che non devono mai arrivare al client: il modulo login (`op=profile`, `op=editreg`) e il profilo del pannello di controllo passano il record utente che mettono in `$_FN['return']` a `FN_UserValuesForOutput()`, che toglie `passwd`, `apikey`, `apikey_enc` e `rnd`. Fai lo stesso nelle tue sezioni.

### Esempio di Sezione
```php
<?php
// sections/products/section.php
global $_FN;
$items = FN_XMDBTable("products")->GetRecords();

// restituito in JSON da fnapp=api
$_FN['return']['items'] = $items;

// stampato dal metodo website, scartato da fnapp=api
echo "<ul>";
foreach ($items as $item) {
    echo "<li>" . htmlspecialchars($item['name']) . "</li>";
}
echo "</ul>";
```

### Richiesta API Esempio
Profilo dell'utente proprietario della chiave API:
```
curl -H "Authorization: Bearer <chiave>" "http://tuosito.com/index.php?fnapp=api&mod=login&op=profile"
```

Risposta (in formato JSON, i campi di `fn_users` senza `passwd`, `apikey` e `rnd`), per esempio:
```json
{
    "username": "johndoe",
    "email": "john@example.com",
    "name": "John",
    "surname": "Doe",
    "level": "0",
    "group": "users,editors",
    "active": "1",
    "registrationdate": "2023-01-15 10:30:00"
}
```

## Estendibilità

### Hook e Callback
FINIS chiama funzioni opzionali definite dall'applicazione (vedi [Hook degli Utenti](#hook-degli-utenti)):

```php
// es. in un file incluso all'avvio (extra/*.inc.php dell'applicazione)
function FN_OnAddUser($newvalues, $password) {
    // Azioni da eseguire quando viene creato un utente
    FN_LogEvent("Nuovo utente {$newvalues['username']}", "myapp");
}
```

Altre funzioni sostituibili: `$_FN['FN_VerifyUserPassword']` può indicare una funzione che sostituisce il controllo della password, e `FN_PasswordVerifyConstraints_overwrite($password)` sostituisce le regole delle password (restituisce un messaggio di errore, o "" se la password è valida).

### File di Avvio
- `{src_application}/extra/*.inc.php` vengono inclusi dal bootstrap, prima dei file di include di FINIS.
- `{src_application}/config.vars.local.php` imposta la configurazione (vedi il costruttore).

### Script Autoexec
Il metodo `website` esegue i file `*.php` di `{src_finis}/include/autoexec.d/` e poi di `{src_application}/include/autoexec.d/`, in ordine naturale, prima di costruire la pagina:

```php
// include/autoexec.d/99_custom.php
global $_FN;

// Aggiungi tag personalizzati all'intestazione della pagina
if ($_FN['mod'] == 'home') {
    $_FN['section_header_footer'] .= '<script src="path/to/script.js"></script>';
}
```

### Script on_site_change
`FN_OnSitemapChange()` esegue i file `*.php` di `{src_finis}/include/on_site_change.d/` (i file che iniziano con `none_` vengono saltati) quando sezioni o blocchi cambiano (pannello di controllo, editor, MCP, `FN_SyncSectionsFromDefaults()`). FINIS lo usa per svuotare la cache e rigenerare la sitemap:

```php
// include/on_site_change.d/00_cache.php
FN_ClearCache();
```

## Sicurezza

### Escape
FINIS non applica l'escape ai valori al posto tuo:

- `FN_GetParam($key, $var, "html")` restituisce il valore con `htmlentities(..., ENT_QUOTES)`; senza tipo il valore viene restituito come ricevuto.
- `FN_RewriteLink()`, `TPL_ApplyTplString()` e `FN_TPL_ApplyTplString()` non applicano alcun escape: un valore della richiesta o del database entra in un template solo dopo `FN_GetParam(..., "html")`, `htmlspecialchars()` o `{var.addHtmlEntities()}` nel template.
- Un valore scritto in un attributo HTML va passato a `htmlspecialchars($value, ENT_QUOTES)`.
- Prima di un redirect a un URL della richiesta, verificalo con `FN_IsInternalUrl()`.

```php
/**
 * Verifica se una stringa corrisponde a un pattern regex (preg_match con delimitatori "/")
 * @param string $find Pattern regex
 * @param string $str Stringa da verificare
 * @param array $regs Corrispondenze (opzionale)
 * @return int Match trovato
 */
function FN_erg($find, $str, $regs = null)

/**
 * Verifica se un'email è valida
 * @param string $email Indirizzo email
 * @return bool Validità
 */
function FN_CheckMail($email)
```

### Valori Casuali
Token, codici captcha, id di sessione, chiavi API e password generate usano `random_bytes()` o `random_int()`; non usare `rand()`, `mt_rand()` o `uniqid()` per nulla di segreto. Confronta i segreti con `hash_equals()`.

### Gestione Permessi
Non esiste una funzione generica per i permessi: usa `FN_IsAdmin()`, `FN_UserInGroup()`, `FN_UserCanViewSection()`, `FN_UserCanEditSection()`, `FN_UserCanViewBlock($block, $user = "")`, `FN_UserCanEditBlock($block = "", $user = "")` e `FN_CanModifyFile($user, $file)`.

### Token CSRF
FINIS non ha funzioni CSRF generiche. I suoi form usano questi schemi:

- `FN_ApiKeyFormToken()`: HMAC legato all'utente collegato, per i form che generano o revocano le chiavi API.
- dbview elimina i record solo con una POST che contiene `fn_csrf`, un HMAC dell'operazione con un segreto per utente conservato con `FN_SetUserSessionValue('fn_csrf_secret', ...)`.

Fai lo stesso nei tuoi form: accetta modifiche solo in POST, aggiungi un token derivato da un segreto dell'utente o della sessione (`hash_hmac()` con un valore da `random_bytes()`), e verificalo con `hash_equals()`.

```php
$secret = FN_GetUserSessionValue('myform_secret');
if (empty($secret)) {
    $secret = bin2hex(random_bytes(16));
    FN_SetUserSessionValue('myform_secret', $secret);
}
$token = hash_hmac('sha256', "save|$id", $secret);
$ok = $_SERVER['REQUEST_METHOD'] === 'POST'
    && hash_equals($token, (string) FN_GetParam("token", $_POST));
```

## Sessioni e Cache

### Sessione Lato Server
```php
/**
 * Id di sessione del browser, preso solo dal cookie fnsid (32 caratteri
 * esadecimali); altrimenti viene creato un id casuale e inviato in un
 * cookie di sessione (HttpOnly, SameSite=Lax, Secure su HTTPS). Anche in
 * $_FN['fnsid'] e $_FN['return']['fnsid']
 * @return string
 */
function FN_GetSessionId()

/**
 * Legge un valore della sessione lato server ({datadir}/_sessions/<fnsid>.session)
 * @param string $varname Chiave
 * @return mixed Valore o null
 */
function FN_GetSessionValue($varname)

/**
 * Scrive un valore della sessione lato server; i file di sessione vengono
 * rimossi un'ora dopo l'ultima scrittura
 * @param string $key Chiave
 * @param mixed $value Valore
 */
function FN_SetSessionValue($key, $value)

/**
 * Legge un valore salvato per l'utente collegato (tabella fn_userssessions)
 * @param string $varname Chiave
 * @return mixed Valore o null (anche senza utente collegato)
 */
function FN_GetUserSessionValue($varname)

/**
 * Salva un valore per l'utente collegato (tabella fn_userssessions, creata
 * se manca); non fa nulla senza utente collegato
 * @param string $varname Chiave
 * @param mixed $value Valore
 */
function FN_SetUserSessionValue($varname, $value)
```

### Gestione Cache
```php
/**
 * Pulisce la cache: rimuove e ricrea {datadir}/_cache
 */
function FN_ClearCache()

/**
 * Legge un valore condiviso da tutte le richieste. Usa APCu se disponibile,
 * altrimenti il file {datadir}/_cache/<md5>.cache (letto con un lock condiviso)
 * @param string $varname Chiave
 * @param int $maxtime Timestamp: un valore salvato prima viene scartato
 * @return mixed Valore o null
 */
function FN_GetGlobalVarValue($varname, $maxtime = false)

/**
 * Salva un valore condiviso da tutte le richieste (APCu, o un file scritto
 * con un lock esclusivo)
 * @param string $varname Chiave
 * @param mixed $value Valore
 * @param int $ttl Durata in secondi, solo con APCu (0 = nessuna scadenza)
 * @return bool Successo
 */
function FN_SetGlobalVarValue($varname, $value, $ttl = 0)
```

Con APCu i valori globali non sono in `{datadir}/_cache`: `FN_ClearCache()` non li rimuove (scadono con il loro TTL o al riavvio di PHP). Anche i contatori dei tentativi di login (`{datadir}/_login_attempts`) e le sessioni (`{datadir}/_sessions`) sono fuori dalla cartella della cache.

## Moduli

### Gestione Moduli
Un modulo è una cartella `modules/<nome>/` con un `section.php`; una sezione lo usa tramite il suo `type`.

```php
/**
 * Ottiene il path di un modulo: {src_application}/modules/<nome> (se ha un
 * section.php), poi {src_finis}/modules/<nome>, poi le estensioni
 * @param string $module_name Nome modulo
 * @return string|false Path o false
 */
function FN_GetModulePath($module_name)

/**
 * Ottiene le cartelle di tutti i moduli (applicazione, FINIS, estensioni)
 * @return array Path dei moduli
 */
function FN_GetAllModulePaths()

/**
 * Salva il tipo di un modulo in fn_sectionstypes, se la tabella non lo ha già
 * @param array $values Record (name, title, ...)
 * @return array|false Il record salvato o trovato, false in caso di errore
 */
function FN_InsertSectionType($values)
```

### Sezioni del Pannello di Controllo
Queste funzioni sono definite dal metodo `controlcenter`:

```php
/**
 * Path di una sezione del pannello di controllo: applicazione, FINIS, poi estensioni
 * @param string $section_path es. "users_and_groups/users"
 * @return string|false Path o false
 */
function FNCC_GetSectionPath($section_path)

/**
 * Titolo di una sezione o di un gruppo del pannello di controllo: il primo
 * file di titolo trovato nell'applicazione, in FINIS e nelle estensioni
 * (default: il nome della cartella)
 * @param string $section_path es. "users_and_groups"
 * @return string
 */
function FNCC_GetSectionTitle($section_path)

/**
 * Path di un file nel tema del pannello di controllo
 * ($_FN['controlcenter_theme']): la copia in
 * {src_application}/controlcenter/themes/ prevale su FINIS
 * @param string $file File
 * @param bool $absolute Se restituire un URL assoluto
 * @return string Path al file
 */
function FNCC_FromTheme($file, $absolute = true)
```

## Esempi di Utilizzo

### Creazione di una Pagina Dinamica
```php
<?php
// sections/mypage/section.php
global $_FN;

// Ottieni parametri
$action = FN_GetParam("action", $_GET);
$id = FN_GetParam("id", $_GET, "int");

// Output HTML
echo "<h1>La mia pagina dinamica</h1>";

// Gestione azioni
switch ($action) {
    case "view":
        if ($id) {
            $item = FN_XMDBTable("mytable")->GetRecordByPrimaryKey($id);
            if ($item) {
                echo "<h2>" . htmlspecialchars($item['title']) . "</h2>";
                echo "<div>" . htmlspecialchars($item['content']) . "</div>";
            } else {
                echo "<p>Elemento non trovato</p>";
            }
        }
        break;

    case "list":
    default:
        $items = FN_XMDBTable("mytable")->GetRecords();
        echo "<ul>";
        foreach ($items as $item) {
            echo "<li><a href=\"?mod={$_FN['mod']}&action=view&id=" . intval($item['id']) . "\">" . htmlspecialchars($item['title']) . "</a></li>";
        }
        echo "</ul>";
        break;
}
```

### Creazione di un Form
```php
<?php
// sections/contact/section.php
global $_FN;

// Gestione invio form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni dati form
    $name = FN_GetParam("name", $_POST, "html");
    $email = FN_GetParam("email", $_POST);
    $message = FN_GetParam("message", $_POST, "html");

    // Validazione
    $errors = array();
    if (empty($name)) $errors[] = "Nome richiesto";
    if (empty($email) || !FN_CheckMail($email)) $errors[] = "Email non valida";
    if (empty($message)) $errors[] = "Messaggio richiesto";

    // Se non ci sono errori, procedi
    if (empty($errors)) {
        // Invia email
        $body = "Nome: $name\nEmail: $email\n\nMessaggio:\n$message";
        if (FN_SendMail($_FN['site_email_address'], "Contatto dal sito", $body)) {
            echo "<div class='success'>Messaggio inviato con successo!</div>";
        } else {
            echo "<div class='error'>Errore nell'invio del messaggio.</div>";
        }
    } else {
        // Mostra errori
        echo "<div class='error'>";
        foreach ($errors as $error) {
            echo "<p>$error</p>";
        }
        echo "</div>";
    }
}

// Mostra form
?>
<h1>Contattaci</h1>
<form method="post" action="?mod=<?php echo $_FN['mod']; ?>">
    <div class="form-group">
        <label for="name">Nome:</label>
        <input type="text" id="name" name="name" value="<?php echo FN_GetParam("name", $_POST, "html"); ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo FN_GetParam("email", $_POST, "html"); ?>" required>
    </div>

    <div class="form-group">
        <label for="message">Messaggio:</label>
        <textarea id="message" name="message" rows="5" required><?php echo FN_GetParam("message", $_POST, "html"); ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit">Invia</button>
    </div>
</form>
```

### Utilizzo dell'API in JavaScript
```javascript
// Esempio di utilizzo dell'API FINIS da JavaScript (stesso sito: i cookie di login vengono inviati)
function getProducts() {
    return fetch('index.php?fnapp=api&mod=products', { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            return data.items;
        });
}

// Utilizzo della funzione
getProducts()
    .then(items => {
        items.forEach(item => console.log(item.name));
    })
    .catch(error => {
        console.error('Errore:', error.message);
    });
```

## Codici di Errore Comuni

| Codice | Descrizione | Possibile Soluzione |
|--------|-------------|---------------------|
| 401 | Endpoint MCP senza una chiave API o un token OAuth validi (con `WWW-Authenticate`) | Inviare `Authorization: Bearer <chiave o token>` |
| 403 | Miniatura di un file di record protetto rifiutata (`$_FN['xmetadb_file_access']`) | Verificare i permessi dell'utente sul record |
| 405 | Endpoint MCP chiamato senza POST | Usare POST |
| 503 | MySQL non raggiungibile, oppure occupato (troppe connessioni) dopo circa 5 secondi di tentativi; la pagina ha `Retry-After: 10` e una GET si ricarica da sola | Verificare il server MySQL e i suoi limiti di connessione |
| Exception "Method ... does not exist" | Valore di `fnapp` sconosciuto | Verificare il nome del metodo e le cartelle `include/methods/` |

Una sezione che l'utente non può vedere mostra "you don't have permission to view this page".

## Best Practices

### Ottimizzazione Performance
- Usa la cache quando possibile
- Limita le query al database
- Minimizza CSS e JavaScript
- Usa immagini ottimizzate

### Sicurezza
- Sanitizza sempre gli input utente
- Usa FN_GetParam() per ottenere parametri, applica l'escape a ogni valore scritto in HTML
- Verifica sempre i permessi
- Implementa protezione CSRF per i form (POST più un token)
- Non restituire mai `passwd`, `apikey`, `apikey_enc` o `rnd` di un utente in `$_FN['return']`: usa `FN_UserValuesForOutput()`

### Manutenibilità
- Documenta il codice con commenti PHPDoc
- Segui le convenzioni di naming
- Organizza il codice in funzioni logiche
- Usa costanti per valori ripetuti

## Appendice

### Costanti e Variabili Globali
- `_FNEXEC`: Indica che il framework è in esecuzione
- `$_FN['lang']`: Lingua corrente
- `$_FN['siteurl']`: URL base del sito
- `$_FN['sitepath']`: Path base del sito
- `$_FN['datadir']`: Directory dei dati
- `$_FN['mod']`: Sezione corrente
- `$_FN['theme']`: Tema corrente
- `$_FN['user']`: Utente corrente
- `$_FN['uservalues']`: Record dell'utente corrente
- `$_FN['return']`: Dati restituiti in JSON da `fnapp=api`
- `$_FN['consolemode']`: Vero in modalità console
- `$_FN['src_finis']`, `$_FN['src_application']`: Cartelle di FINIS e dell'applicazione
- `$_FN['path_extensions']`: Cartelle registrate con `addExtension()`
- `$_FN['tables']`: Parametri delle tabelle impostati con `setTable()`
- `$_FN['auth_method']`: `apikey` o `oauth` quando la richiesta è autenticata da una chiave API o da un token OAuth
- `$_FN['fnsid']`: Id della sessione lato server (dopo `FN_GetSessionId()`)
- `$_FN['fnshell']`: Vero quando il metodo `website` costruisce il guscio del tema (`fnshell=1`)

### Chiavi di Configurazione
Da impostare in `config.vars.local.php` (default nel codice o in `src/include/config.vars.php`):

| Chiave | Default | Significato |
|---|---|---|
| `display_errors` | "off" | "on" mostra gli errori PHP (sviluppo) |
| `home_section_force` | "" | Sezione home che prevale su quella salvata dal pannello di controllo |
| `remember_login_days` | 30 | Durata dei cookie di login con "ricordami" |
| `recovery_link_minutes` | 60 | Validità di un link di recupero password |
| `login_max_attempts` | 5 | Tentativi di login ammessi per username e IP |
| `login_lockout_minutes` | 15 | Finestra temporale dei tentativi |
| `trusted_proxies` | "" | Reverse proxy di cui ci si fida per `X-Forwarded-For` |
| `username_case_sensitive` | non impostata | Mantiene maiuscole e minuscole degli username |
| `apikey_groups` | "" | Gruppi ammessi all'uso delle chiavi API ("" = tutti) |
| `mcp_write_group` | "mcp_write" | Gruppo ammesso agli strumenti MCP di scrittura |
| `mcp_endpoints`, `mcp_guide`, `mcp_type_info` | non impostate | Endpoint e note MCP dell'applicazione (vedi [guida MCP](it_guida_mcp.md)) |
| `controlcenter_theme` | | Tema del pannello di controllo |
| `xmetadb_file_url` | non impostata | Callback `($table, $recordvalues, $recordkey, $thumb)` che restituisce l'URL di un file di record |
| `xmetadb_file_access` | non impostata | Callback `($table, $id, $field)` che decide chi può leggere un file di record (endpoint delle miniature) |
| `dbview_list_template` | non impostata | File di template della lista per ogni sezione dbview dell'applicazione |
| `dbview_toolbar_icons` | non impostata | Icone della barra strumenti di dbview |
| `dbview_excluded_values` | non impostata | Callback `($tablename)` che restituisce `array(campo => valori)` dei record nascosti all'utente |
| `dbview_view_cache` | non impostata | Cache delle schede dei record per i visitatori anonimi: `array(sezione => secondi)` o secondi per ogni sezione |
| `dbview_view_cache_tables`, `dbview_view_cache_files`, `dbview_view_cache_keep` | | Tabelle e file che invalidano una scheda in cache; giorni di conservazione dei file (30) |

### Driver Database Supportati
Driver in `src/include/xmetadb/` (`XMETATable_<driver>.php`):
- `xmlphp`: File XML/PHP (default)
- `mysql`: MySQL/MariaDB (mysqli, utf8mb4)
- `sqlite3`: SQLite 3 (estensione SQLite3)
- `sqlite`: Vecchio SQLite (richiede la vecchia classe `SQLiteDatabase`)
- `sqlserver`: Microsoft SQL Server (estensione sqlsrv)
- `csv`: File CSV
- `serialize`: File PHP serializzati

### Requisiti di Sistema
- PHP 7.1 o superiore (PHP 8.x supportato)
- Estensione mysqli, SQLite3 o sqlsrv per i driver SQL
- Libreria GD per manipolazione immagini (miniature)
- Opzionali: APCu per `FN_GetGlobalVarValue()` / `FN_SetGlobalVarValue()`; openssl per permettere agli amministratori di mostrare le chiavi API degli utenti
- Permessi di scrittura per le directory di dati

### Compatibilità browser
- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 16+
- Opera 47+
