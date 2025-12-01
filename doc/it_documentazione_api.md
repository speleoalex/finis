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
- Le classi iniziano con lettere maiuscole (es. `XMETATable`)
- Le costanti sono in maiuscolo con underscore (es. `_FNEXEC`)
- Le variabili globali sono all'interno dell'array `$_FN`

## API Core

### Classe FINIS
La classe principale che inizializza e gestisce il framework.

#### Costruttore
```php
/**
 * Inizializza il framework FINIS
 * @param array $config Configurazioni opzionali
 */
function __construct($config = array())
```

Esempio:
```php
require_once "path/to/FINIS.php";
$FINIS = new FINIS(array(
    "src_application" => ".",
    "display_errors" => "on"
));
```

#### Metodi Principali

```php
/**
 * Esegue una sezione specifica
 * @param string $section ID della sezione
 */
function runSection($section = "")

/**
 * Esegue una cartella come se fosse una sezione
 * @param string $folder Percorso della cartella
 */
function runFolder($folder)

/**
 * Esegue l'applicazione principale
 */
function finis()

/**
 * Imposta una variabile di configurazione
 * @param string $id Nome della variabile
 * @param mixed $value Valore
 */
function setVar($id, $value)

/**
 * Verifica se l'applicazione è in esecuzione in modalità console
 * @return bool
 */
function isConsole()
```

### Funzioni Generali

#### Gestione Parametri
```php
/**
 * Ottiene un parametro da una variabile
 * @param string $key Nome del parametro
 * @param array $var Array da cui estrarre il parametro
 * @param string $type Tipo di dato (html, int, float)
 * @return mixed Valore del parametro
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
 * Ottiene il path per un file con considerazione del tema
 * @param string $file File da cercare
 * @param bool $absolute Se restituire un path assoluto
 * @return string Path al file
 */
function FN_FromTheme($file, $absolute = true)

/**
 * Converte un path locale in assoluto
 * @param string $filepath Path del file
 * @param bool $urlAbsolute Se restituire URL assoluto
 * @return string Path completo
 */
function FN_PathSite($filepath, $urlAbsolute = false)

/**
 * Restituisce l'icona appropriata per un tipo di file
 * @param string $filename Nome del file
 * @return string URL dell'icona
 */
function FN_GetIconByFilename($filename)
```

#### Localizzazione e Traduzione
```php
/**
 * Traduce una stringa nella lingua corrente
 * @param string $text Testo da tradurre
 * @param string $context Contesto opzionale
 * @return string Testo tradotto
 */
function FN_i18n($text, $context = "")

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
 * Registra un evento nel log di sistema
 * @param string $event Messaggio dell'evento
 * @param string $context Contesto dell'evento
 */
function FN_LogEvent($event, $context = "cms")

/**
 * Scrive un messaggio nel log
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

> **Nota**: Per una documentazione più completa sul sistema di database, consultare la [Guida al Layer di Astrazione Database](guida_database.md).

### Classe XMETATable
Classe principale per interagire con il database.

#### Creazione e Inizializzazione
```php
/**
 * Ottiene un'istanza della tabella
 * @param string $tablename Nome della tabella
 * @return object Istanza di XMETATable
 */
function FN_XMDBTable($tablename)
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
 * @param array $record Dati del record
 * @return mixed ID del record inserito o false
 */
function InsertRecord($record)

/**
 * Aggiorna un record esistente
 * @param array $record Nuovi dati
 * @param string $fieldname Campo chiave
 * @param mixed $fieldvalue Valore chiave
 * @return bool Successo
 */
function UpdateRecord($record, $fieldname, $fieldvalue)

/**
 * Aggiorna un record tramite chiave primaria
 * @param array $record Nuovi dati
 * @param string $pkfield Nome campo chiave primaria
 * @param mixed $pkvalue Valore chiave primaria
 * @return bool Successo
 */
function UpdateRecordBypk($record, $pkfield, $pkvalue)

/**
 * Elimina un record
 * @param mixed $id Valore chiave primaria
 * @return bool Successo
 */
function DelRecord($id)

/**
 * Ottiene tutti i record
 * @param array $filter Filtro opzionale
 * @return array Lista di record
 */
function GetRecords($filter = array())

/**
 * Ottiene un record per chiave primaria
 * @param mixed $id Valore chiave primaria
 * @return array Record o false
 */
function GetRecordByPrimaryKey($id)
```

Esempio:
```php
// Inserisci un nuovo utente
$userData = array(
    'username' => 'nuovoutente',
    'email' => 'utente@esempio.com',
    'password' => md5('password123'),
    'regdate' => FN_Now(),
    'active' => 1
);
$userId = $usersTable->InsertRecord($userData);

// Aggiorna un utente
$usersTable->UpdateRecord(
    array('email' => 'nuovo@esempio.com'),
    'username',
    'nuovoutente'
);

// Ottieni un utente specifico
$user = $usersTable->GetRecordByPrimaryKey(5);

// Ottieni utenti filtrati
$admins = $usersTable->GetRecords(array('group' => 'admin'));

// Elimina un utente
$usersTable->DelRecord(5);
```

#### Query Avanzate
```php
/**
 * Esegue una query SQL personalizzata
 * @param string $query Query SQL
 * @return array Risultati query
 */
function FN_XMETADBQuery($query)
```

Esempio:
```php
// Query personalizzata
$results = FN_XMETADBQuery("
    SELECT u.username, g.groupname 
    FROM fn_users u 
    JOIN fn_groups g ON u.group = g.groupname 
    WHERE u.active = 1
");
```

### Classe XMETAForm
Classe per la gestione di form collegati a tabelle.

```php
/**
 * Ottiene un'istanza del form per una tabella
 * @param string $tablename Nome della tabella
 * @return object Istanza di XMETAForm
 */
function FN_XMDBForm($tablename)
```

Esempio:
```php
// Ottieni un form per la tabella utenti
$usersForm = FN_XMDBForm("fn_users");

// Genera HTML per il form
$formHtml = $usersForm->GetForm(array(
    'action' => 'add',
    'record' => array(),  // Per edit, inserire record esistente
    'redirect' => '?mod=users'
));
```

## API Template

### Gestione Template
```php
/**
 * Carica una configurazione da un file
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
 * Include CSS dal framework e sezioni
 * @return string Tag HTML per CSS
 */
function FN_IncludeCSS()

/**
 * Include JavaScript dal framework
 * @return string Tag HTML per JavaScript
 */
function FN_IncludeJS()
```

### Manipolazione HTML e URL
```php
/**
 * Converte BBCode in HTML
 * @param string $string Testo con BBCode
 * @return string HTML risultante
 */
function FN_Tag2Html($string)

/**
 * Converte link relativi in assoluti
 * @param string $str Contenuto HTML
 * @param string $folder Directory di base
 * @return string HTML con link assoluti
 */
function FN_RewriteLinksLocalToAbsolute($str, $folder)

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

## API Sezioni

### Gestione Sezioni
```php
/**
 * Verifica se un utente può visualizzare una sezione
 * @param string $section ID sezione
 * @param string $user Username (opzionale)
 * @return bool
 */
function FN_UserCanViewSection($section, $user = "")

/**
 * Verifica se un utente può modificare una sezione
 * @param string $section ID sezione
 * @param string $user Username (opzionale)
 * @return bool
 */
function FN_UserCanEditSection($section, $user = "")

/**
 * Ottiene i valori di una sezione
 * @param string $sectionid ID sezione
 * @return array Dati sezione
 */
function FN_GetSectionValues($sectionid)

/**
 * Esegue una sezione
 * @param string $section ID sezione
 * @param bool $return Se restituire l'output invece di stamparlo
 * @return mixed Output o null
 */
function FN_RunSection($section, $return = false)

/**
 * Esegue una cartella come sezione
 * @param string $folder Path cartella
 * @param bool $return Se restituire l'output
 * @return mixed Output o null
 */
function FN_RunFolder($folder, $return = false)
```

### Navigazione
```php
/**
 * Ottiene le voci di menu
 * @param string $level Livello menu
 * @param string $parent Sezione genitore
 * @return array Voci menu
 */
function FN_GetMenuEntries($level = "top", $parent = "")

/**
 * Ottiene il percorso di navigazione
 * @param string $section ID sezione
 * @return array Percorso
 */
function FN_GetPath($section = "")
```

## API Utenti e Gruppi

### Gestione Utenti
```php
/**
 * Verifica se l'utente corrente è amministratore
 * @return bool
 */
function FN_IsAdmin()

/**
 * Ottiene i dati di un utente
 * @param string $username Username
 * @return array Dati utente o false
 */
function FN_GetUser($username)

/**
 * Verifica se un utente appartiene a un gruppo
 * @param string $user Username
 * @param string $group Nome gruppo o gruppi separati da virgola
 * @return bool
 */
function FN_UserInGroup($user, $group)

/**
 * Crea un gruppo se non esiste
 * @param string $groupname Nome gruppo
 */
function FN_CreateGroupIfNotExists($groupname)
```

### Autenticazione
```php
/**
 * Verifica le credenziali di un utente
 * @param string $username Username
 * @param string $password Password
 * @return bool Successo
 */
function FN_CheckUserPass($username, $password)

/**
 * Inizia una sessione utente
 * @param string $username Username
 * @param bool $remember Cookie persistente
 */
function FN_Login($username, $remember = false)

/**
 * Termina la sessione utente corrente
 */
function FN_Logout()
```

## API File e Directory

### Operazioni su File
```php
/**
 * Scrive contenuto in un file
 * @param string $string Contenuto
 * @param string $path Path file
 * @param string $mode Modalità (default: w)
 * @return bool Successo
 */
function FN_Write($string, $path, $mode = "w")

/**
 * Copia un file
 * @param string $source File origine
 * @param string $dest File destinazione
 * @param bool $overwrite Sovrascrivere se esiste
 * @return bool Successo
 */
function FN_Copy($source, $dest, $overwrite = false)

/**
 * Copia una directory ricorsivamente
 * @param string $source Dir origine
 * @param string $dest Dir destinazione
 * @param bool $overwrite Sovrascrivere file esistenti
 * @return bool Successo
 */
function FN_CopyDir($source, $dest, $overwrite = false)

/**
 * Crea una directory
 * @param string $path Path directory
 * @param int $mode Permessi (default: 0755)
 * @return bool Successo
 */
function FN_MkDir($path, $mode = 0755)

/**
 * Rimuove una directory ricorsivamente
 * @param string $dir Path directory
 * @return bool Successo
 */
function FN_RemoveDir($dir)
```

### Utilità File
```php
/**
 * Ottiene l'estensione di un file
 * @param string $filename Nome file
 * @return string Estensione
 */
function FN_GetFileExtension($filename)

/**
 * Ottiene il MIME type di un file
 * @param string $filename Nome file
 * @return string MIME type
 */
function FN_GetMimeType($filename)

/**
 * Genera un nome file sicuro
 * @param string $filename Nome file originale
 * @return string Nome file sicuro
 */
function FN_CreateSafeFilename($filename)
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
 * Redirect a un'altra pagina
 * @param string $url URL di destinazione
 */
function FN_Redirect($url)

/**
 * Verifica se il referer è esterno
 * @return bool
 */
function FN_IsExternalReferer()

/**
 * Invia un file al browser per il download
 * @param string $filecontents Contenuto del file
 * @param string $filename Nome del file
 * @param string $HeaderContentType Tipo MIME
 */
function FN_SaveFile($filecontents, $filename, $HeaderContentType = "application/force-download")
```

## API REST

### Endpoint Base
FINIS fornisce un sistema di API REST accessibile tramite il parametro `fnapi`:

```
http://tuosito.com/?fnapi=nome_api&action=nome_azione&param1=valore1
```

#### Gestione delle Richieste API
```php
/**
 * Gestisce una richiesta API
 * @param string $apiName Nome dell'API
 * @param string $action Azione richiesta
 * @param array $params Parametri aggiuntivi
 * @return mixed Risultato dell'API
 */
function FN_HandleApiRequest($apiName, $action, $params = array())
```

### Esempio API Utenti
```php
// API Utenti (include/methods/api.php)
function api_users($action, $params) {
    switch ($action) {
        case 'get':
            // Verifica permessi
            if (!FN_IsAdmin()) {
                return array('error' => 'Unauthorized');
            }
            
            $userId = FN_GetParam('id', $params, 'int');
            if ($userId) {
                $user = FN_XMDBTable('fn_users')->GetRecordByPrimaryKey($userId);
                // Rimuovi dati sensibili
                unset($user['password']);
                return $user;
            } else {
                $users = FN_XMDBTable('fn_users')->GetRecords();
                foreach ($users as &$user) {
                    unset($user['password']);
                }
                return $users;
            }
            break;
            
        case 'create':
            // Implementazione creazione utente
            break;
            
        // Altre azioni...
    }
}
```

### Richiesta API Esempio
Accesso all'API:
```
http://tuosito.com/?fnapi=users&action=get&id=5
```

Risposta (in formato JSON):
```json
{
    "id": 5,
    "username": "johndoe",
    "email": "john@example.com",
    "name": "John Doe",
    "regdate": "2023-01-15 10:30:00",
    "group": "users,editors",
    "active": 1
}
```

## Estendibilità

### Hook e Callback
FINIS utilizza un sistema di hook per permettere l'estensione del comportamento:

```php
// Registrare una funzione per un hook
$_FN['hooks']['user_login'][] = 'my_login_callback';

// Funzione di callback
function my_login_callback($username) {
    // Azioni da eseguire al login
    FN_Log("Utente $username ha effettuato l'accesso");
}

// Eseguire un hook
if (!empty($_FN['hooks']['user_login'])) {
    foreach ($_FN['hooks']['user_login'] as $callback) {
        if (function_exists($callback)) {
            call_user_func($callback, $username);
        }
    }
}
```

### Script Autoexec
I file nella cartella `include/autoexec.d/` vengono eseguiti automaticamente all'avvio del framework:

```php
// include/autoexec.d/99_custom.php
global $_FN;

// Aggiungi CSS o JS personalizzato
if ($_FN['mod'] == 'home') {
    $_FN['header_append'] .= '<script src="path/to/script.js"></script>';
}

// Registra hook
$_FN['hooks']['user_login'][] = 'custom_login_handler';
```

### Script on_site_change
I file nella cartella `include/on_site_change.d/` vengono eseguiti quando i contenuti del sito cambiano:

```php
// include/on_site_change.d/sitemap_generator.php
global $_FN;

// Genera sitemap quando i contenuti cambiano
function regenerate_sitemap() {
    // Logica per generare sitemap
}

// Registra funzione per essere chiamata quando il sito cambia
$_FN['on_site_change_callbacks'][] = 'regenerate_sitemap';
```

## Sicurezza

### Sanitizzazione Input
```php
/**
 * Sanitizza input per prevenire XSS
 * @param string $str Stringa da sanitizzare
 * @return string Stringa sanitizzata
 */
function FN_HtmlEncode($str)

/**
 * Verifica se una stringa corrisponde a un pattern regex
 * @param string $pattern Pattern regex
 * @param string $string Stringa da verificare
 * @return bool Match trovato
 */
function FN_erg($pattern, $string)

/**
 * Verifica se un'email è valida
 * @param string $email Indirizzo email
 * @return bool Validità
 */
function FN_CheckMail($email)
```

### Gestione Permessi
```php
/**
 * Verifica se l'utente corrente può accedere a una funzionalità
 * @param string $permission Nome permesso
 * @return bool
 */
function FN_UserCan($permission)

/**
 * Genera un token CSRF
 * @param string $action Nome azione
 * @return string Token
 */
function FN_GetCSRFToken($action)

/**
 * Verifica un token CSRF
 * @param string $token Token da verificare
 * @param string $action Nome azione
 * @return bool Validità
 */
function FN_VerifyCSRFToken($token, $action)
```

## Cache

### Gestione Cache
```php
/**
 * Pulisce la cache
 * @return bool Successo
 */
function FN_ClearCache()

/**
 * Imposta un valore nella cache
 * @param string $key Chiave
 * @param mixed $value Valore
 * @param int $ttl Scadenza in secondi
 * @return bool Successo
 */
function FN_SetCache($key, $value, $ttl = 3600)

/**
 * Ottiene un valore dalla cache
 * @param string $key Chiave
 * @return mixed Valore o false
 */
function FN_GetCache($key)

/**
 * Rimuove un valore dalla cache
 * @param string $key Chiave
 * @return bool Successo
 */
function FN_DeleteCache($key)
```

## Moduli

### Gestione Moduli
```php
/**
 * Verifica se un modulo esiste
 * @param string $moduleName Nome modulo
 * @return bool
 */
function FN_ModuleExists($moduleName)

/**
 * Ottiene il path di un modulo
 * @param string $moduleName Nome modulo
 * @return string Path o false
 */
function FN_ModulePath($moduleName)

/**
 * Carica un modulo
 * @param string $moduleName Nome modulo
 * @return bool Successo
 */
function FN_LoadModule($moduleName)
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
                echo "<h2>{$item['title']}</h2>";
                echo "<div>{$item['content']}</div>";
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
            echo "<li><a href=\"?mod={$_FN['mod']}&action=view&id={$item['id']}\">{$item['title']}</a></li>";
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
        <input type="email" id="email" name="email" value="<?php echo FN_GetParam("email", $_POST); ?>" required>
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
// Esempio di utilizzo dell'API FINIS da JavaScript
function getUserData(userId) {
    return fetch(`?fnapi=users&action=get&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            return data;
        });
}

// Utilizzo della funzione
getUserData(5)
    .then(user => {
        console.log(`Nome utente: ${user.username}`);
        console.log(`Email: ${user.email}`);
    })
    .catch(error => {
        console.error('Errore:', error.message);
    });
```

## Codici di Errore Comuni

| Codice | Descrizione | Possibile Soluzione |
|--------|-------------|---------------------|
| 403 | Accesso negato | Verificare i permessi utente |
| 404 | Sezione non trovata | Verificare l'ID sezione |
| 500 | Errore interno del server | Controllare i log per dettagli |
| FN001 | Errore di configurazione DB | Verificare le impostazioni database |
| FN002 | File non trovato | Verificare il path del file |
| FN003 | Errore di permessi file | Verificare CHMOD della directory |

## Best Practices

### Ottimizzazione Performance
- Usa la cache quando possibile
- Limita le query al database
- Minimizza CSS e JavaScript
- Usa immagini ottimizzate

### Sicurezza
- Sanitizza sempre gli input utente
- Usa FN_GetParam() per ottenere parametri
- Verifica sempre i permessi
- Implementa protezione CSRF per i form

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

### Driver Database Supportati
- `xmlphp`: File XML/PHP (default)
- `mysql`: MySQL/MariaDB
- `sqlite`: SQLite
- `sqlserver`: Microsoft SQL Server
- `csv`: File CSV

### Requisiti di Sistema
- PHP 7.0 o superiore
- Estensione PDO per database SQL
- Libreria GD per manipolazione immagini
- Permessi di scrittura per le directory di dati

### Compatibilità browser
- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 16+
- Opera 47+