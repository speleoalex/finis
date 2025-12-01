# Manuale dello Sviluppatore FINIS Framework

## Introduzione
Questo manuale è pensato per gli sviluppatori che desiderano estendere le funzionalità del framework FINIS, creando moduli personalizzati, integrando nuove funzionalità o modificando quelle esistenti.

## Architettura del Framework

### Componenti Principali
- **Core (FINIS.php)**: Classe principale che inizializza e gestisce il framework
- **XMETATable**: Sistema di gestione database con supporto per diversi driver (vedi [Guida Database](guida_database.md))
- **Theme Engine**: Sistema per la gestione e il rendering dei temi (vedi [Guida Temi](guida_temi.md))
- **Section Manager**: Gestisce le sezioni del sito e il loro routing
- **Module System**: Sistema per estendere le funzionalità con moduli (vedi [Guida Moduli](guida_moduli.md))

### Flusso di Esecuzione
1. L'utente richiede una pagina (`index.php`)
2. Il framework viene inizializzato (`FINIS.php`)
3. Il parametro `mod` viene analizzato per determinare la sezione richiesta
4. La sezione viene caricata ed eseguita
5. Il risultato viene renderizzato attraverso il sistema di template

## Creazione di Moduli

### Struttura Base di un Modulo
I moduli sono contenuti nella cartella `modules/` e ogni modulo ha la propria sottocartella.

```
modules/
  └── nomemodulo/
      ├── section.php       # Logica principale del modulo
      ├── config.php        # Configurazioni del modulo
      ├── languages/        # Traduzioni
      │   ├── en/
      │   │   └── lang.csv
      │   └── it/
      │       └── lang.csv
      ├── css/              # Fogli di stile
      └── js/               # Script JavaScript
```

### Esempio di Module Base

Creare una nuova cartella in `modules/` (es. `modules/mymodule/`) con questi file:

**section.php**:
```php
<?php
/**
 * Modulo di esempio
 * @author NomeSviluppatore
 */
global $_FN;

// Output principale del modulo
function MyModule_Main() {
    global $_FN;
    
    // Esempio di lettura parametri GET/POST
    $action = FN_GetParam("action", $_GET, "string");
    
    // Esempio di lettura configurazione
    $config = FN_LoadConfig("modules/mymodule/config.php");
    
    // Logica condizionale in base all'azione
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

// Esempio di funzione per listare elementi
function MyModule_ListItems() {
    global $_FN;
    $html = "<h2>" . FN_i18n("list_items") . "</h2>";
    
    // Esempio di accesso al database
    $table = FN_XMDBTable("my_custom_table");
    $items = $table->GetRecords();
    
    if (is_array($items) && count($items) > 0) {
        $html .= "<ul>";
        foreach ($items as $item) {
            $html .= "<li><a href=\"?mod={$_FN['mod']}&action=view&id={$item['id']}\">{$item['title']}</a></li>";
        }
        $html .= "</ul>";
    } else {
        $html .= "<p>" . FN_i18n("no_items_found") . "</p>";
    }
    
    return $html;
}

// Esempio di funzione per visualizzare un elemento
function MyModule_ViewItem() {
    $id = FN_GetParam("id", $_GET, "int");
    
    $table = FN_XMDBTable("my_custom_table");
    $item = $table->GetRecordByPrimaryKey($id);
    
    if (isset($item['id'])) {
        $html = "<h2>{$item['title']}</h2>";
        $html .= "<div>{$item['content']}</div>";
        $html .= "<p><a href=\"?mod={$_FN['mod']}\">" . FN_i18n("back_to_list") . "</a></p>";
    } else {
        $html = "<p>" . FN_i18n("item_not_found") . "</p>";
        $html .= "<p><a href=\"?mod={$_FN['mod']}\">" . FN_i18n("back_to_list") . "</a></p>";
    }
    
    return $html;
}

// Punto di ingresso principale
$output = MyModule_Main();
echo $output;
```

**config.php**:
```php
<?php
global $_FN;

// Configurazioni predefinite del modulo
$config = array(
    'items_per_page' => 10,
    'enable_comments' => 1,
    'default_sort' => 'title'
);

// Definizione del database custom del modulo
if (!file_exists("{$_FN['datadir']}/fndatabase/my_custom_table.php")) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <n>id</n>
        <type>integer</type>
        <autoincrement>1</autoincrement>
        <primarykey>1</primarykey>
    </field>
    <field>
        <n>title</n>
        <type>string</type>
        <size>255</size>
        <frm_required>1</frm_required>
    </field>
    <field>
        <n>content</n>
        <type>text</type>
    </field>
    <field>
        <n>created_date</n>
        <type>datetime</type>
    </field>
    <filename>my_custom_table</filename>
</tables>';
    FN_Write($xml, "{$_FN['datadir']}/fndatabase/my_custom_table.php");
}
```

### Integrazione con il Pannello di Controllo

Per aggiungere il tuo modulo al pannello di controllo, crea una cartella in `controlcenter/sections/`:

```
controlcenter/
  └── sections/
      └── mymodule/
          ├── section.php
          ├── title.it.fn
          └── title.en.fn
```

**section.php**:
```php
<?php
/**
 * Admin panel for mymodule
 */
global $_FN;

// Verifico se l'utente è amministratore
if (!FN_IsAdmin()) {
    echo FN_i18n("access_denied");
    return;
}

// Gestisco le operazioni di amministrazione
$op = FN_GetParam("op", $_GET);
switch ($op) {
    case "save":
        // Salva le configurazioni
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $config = FN_LoadConfig("modules/mymodule/config.php");
            $config['items_per_page'] = FN_GetParam("items_per_page", $_POST, "int");
            $config['enable_comments'] = FN_GetParam("enable_comments", $_POST, "int");
            $config['default_sort'] = FN_GetParam("default_sort", $_POST);
            
            // Salva le configurazioni
            $table = FN_XMDBTable("fncf_mymodule");
            foreach ($config as $key => $value) {
                $table->UpdateRecord(array("varname" => $key, "varvalue" => $value), "varname", $key);
            }
            
            echo "<div class='alert alert-success'>" . FN_i18n("settings_saved") . "</div>";
        }
        break;
}

// Carico le configurazioni attuali
$config = FN_LoadConfig("modules/mymodule/config.php");

// Form di configurazione
echo "<h2>" . FN_i18n("mymodule_settings") . "</h2>";
echo "<form method='post' action='?mod=" . $_FN['mod'] . "&op=save'>";
echo "<div class='form-group'>";
echo "<label>" . FN_i18n("items_per_page") . "</label>";
echo "<input type='number' name='items_per_page' value='" . $config['items_per_page'] . "' class='form-control'>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>" . FN_i18n("enable_comments") . "</label>";
echo "<select name='enable_comments' class='form-control'>";
echo "<option value='1' " . ($config['enable_comments'] ? "selected" : "") . ">" . FN_i18n("yes") . "</option>";
echo "<option value='0' " . (!$config['enable_comments'] ? "selected" : "") . ">" . FN_i18n("no") . "</option>";
echo "</select>";
echo "</div>";

echo "<div class='form-group'>";
echo "<label>" . FN_i18n("default_sort") . "</label>";
echo "<select name='default_sort' class='form-control'>";
echo "<option value='title' " . ($config['default_sort'] == 'title' ? "selected" : "") . ">" . FN_i18n("title") . "</option>";
echo "<option value='created_date' " . ($config['default_sort'] == 'created_date' ? "selected" : "") . ">" . FN_i18n("date") . "</option>";
echo "</select>";
echo "</div>";

echo "<button type='submit' class='btn btn-primary'>" . FN_i18n("save") . "</button>";
echo "</form>";
```

## Lavorare con il Database

### Creazione Tabelle
FINIS utilizza un sistema di descrizione di tabelle basato su XML. Ecco come definire una tabella:

```php
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0);?>
<tables>
    <field>
        <name>id</name>
        <type>integer</type>
        <autoincrement>1</autoincrement>
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

// Inserire un record
$newRecord = array(
    'nome' => 'Nuovo elemento',
    'descrizione' => 'Descrizione dell\'elemento'
);
$id = $table->InsertRecord($newRecord);

// Leggere un record
$record = $table->GetRecordByPrimaryKey($id);

// Aggiornare un record
$updatedRecord = array(
    'nome' => 'Nome aggiornato',
    'descrizione' => 'Descrizione aggiornata'
);
$table->UpdateRecord($updatedRecord, "id", $id);

// Eliminare un record
$table->DelRecord($id);

// Ottenere tutti i record
$records = $table->GetRecords();

// Ottenere record filtrati
$filteredRecords = $table->GetRecords(array('nome' => 'Filtro'));
```

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
- I file in `include/autoexec.d/` vengono eseguiti all'avvio
- I file in `include/on_site_change.d/` vengono eseguiti quando il sito cambia

### Esempio di Hook
```php
<?php
// Salvare come include/autoexec.d/99_mymodule.php
global $_FN;

// Aggiungi lo script solo per certe pagine
if ($_FN['mod'] == 'home') {
    $_FN['header_append'] .= '<script src="modules/mymodule/js/script.js"></script>';
}
```

## Debugging
Il framework offre diverse funzioni per il debugging:

```php
// Visualizzare un array strutturato
dprint_r($myArray);

// Logging
FN_Log("Messaggio di debug");

// Tempo di esecuzione
echo FN_GetExecuteTimer(); // tempo dall'inizio in secondi

// Timer parziale
echo FN_GetPartialTimer(); // tempo dall'ultima chiamata
```

## Best Practices

### Sicurezza
- Sanitizzare sempre gli input utente con `FN_GetParam()`
- Usare prepared statements per le query SQL
- Verificare l'autorizzazione dell'utente con `FN_UserInGroup()` o `FN_UserCanViewSection()`

### Struttura del Codice
- Mantenere la separazione tra logica e presentazione
- Usare i file di configurazione per le impostazioni
- Documentare le funzioni con commenti PHPDoc
- Utilizzare le funzioni del framework anziché reimplementare le stesse funzionalità

### Performance
- Utilizzare la cache quando possibile
- Considerare l'uso di query ottimizzate per database grandi
- Caricare script JS in modo asincrono

## Esempi Avanzati

### Creazione di un Plugin
I plugin sono componenti che estendono le funzionalità del core senza creare nuove sezioni.

**Struttura del plugin**:
```
plugins/
  └── myplugin/
      ├── plugin.php        # Codice principale del plugin
      ├── controlcenter/    # Interfaccia di amministrazione
      │   └── settings.php
      └── functions.php     # Funzioni helper
```

**plugin.php**:
```php
<?php
/**
 * Esempio di plugin
 */
global $_FN;

// Aggiungi script o funzionalità globali
$_FN['header_append'] .= '<script src="plugins/myplugin/js/script.js"></script>';

// Aggiungi hook per intercettare eventi
function myplugin_on_user_login($username) {
    FN_Log("Utente $username ha effettuato l'accesso");
}

// Registra la funzione nell'hook
$_FN['hooks']['user_login'][] = 'myplugin_on_user_login';
```

### Estensione del Pannello di Controllo
Per aggiungere funzionalità al pannello di amministrazione:

```php
<?php
// In controlcenter/sections/myplugin/section.php
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
- Controllare i log PHP e i log del framework in `misc/log/`

### Problemi con il Database
- Verificare i permessi di scrittura nelle cartelle `misc/fndatabase/`
- Controllare la connessione al database per i driver esterni (MySQL, ecc.)
- Utilizzare `$table->GetError()` per ottenere messaggi di errore dettagliati

### Problemi con i Moduli
- Assicurarsi che la struttura delle cartelle sia corretta
- Verificare che i file di configurazione siano accessibili
- Controllare eventuali conflitti di nomi tra moduli