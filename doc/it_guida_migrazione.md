# Guida alla Migrazione per FINIS Framework

## Introduzione
Questa guida fornisce istruzioni dettagliate per migrare verso FINIS da sistemi preesistenti o aggiornare tra diverse versioni di FINIS. La migrazione e l'aggiornamento richiedono un'attenta pianificazione per garantire la continuità del servizio e preservare tutti i dati importanti.

## Pianificazione della Migrazione

### Valutazione Preliminare
Prima di iniziare la migrazione, è fondamentale:

1. **Analizzare il sistema attuale**:
   - Inventario dei contenuti esistenti
   - Struttura del database
   - Funzionalità personalizzate
   - Temi e layout
   - Plugin e moduli
   - Utenti e permessi

2. **Valutare la compatibilità**:
   - Verificare i requisiti tecnici di FINIS
   - Identificare potenziali incompatibilità
   - Esaminare formati di dati non supportati

3. **Definire gli obiettivi**:
   - Funzionalità da preservare
   - Nuove funzionalità da implementare
   - Cronologia della migrazione
   - Strategia di test e rollback

### Pianificazione del Tempo
Una migrazione completa richiede tempo. Pianifica adeguatamente:

1. **Sviluppo e test**: 1-4 settimane (dipende dalla complessità)
2. **Migrazione dei dati**: 1-3 giorni
3. **Verifica e correzione**: 1-2 settimane
4. **Formazione utenti**: 1-2 giorni
5. **Messa online**: 1 giorno

### Creazione dell'Ambiente di Test
È sempre consigliabile eseguire la migrazione in un ambiente di test:

1. Configura un server di sviluppo
2. Installa FINIS nell'ambiente di test
3. Crea una copia del database esistente
4. Copia i file necessari nel nuovo ambiente

## Migrazione da Altri CMS a FINIS

### Da WordPress a FINIS

#### Migrazione del Database
1. **Esportazione dei contenuti da WordPress**:
   ```sql
   SELECT ID, post_title, post_content, post_date, post_name, post_type
   FROM wp_posts
   WHERE post_status = 'publish'
   AND post_type IN ('post', 'page')
   ```

2. **Preparazione del file di importazione**:
   Converti i dati in un formato compatibile (CSV o XML)

3. **Script di importazione**:
   ```php
   <?php
   require_once "path/to/FINIS.php";
   $FINIS = new FINIS();
   
   // Carica il file CSV/XML
   $data = load_data_from_file("exported_data.csv");
   
   foreach ($data as $item) {
       $sectionId = sanitize_section_id($item['post_name']);
       
       // Crea una nuova sezione per ogni pagina
       if ($item['post_type'] == 'page') {
           create_finis_section($sectionId, $item['post_title'], $item['post_content']);
       }
       
       // Crea un articolo news per ogni post
       if ($item['post_type'] == 'post') {
           create_finis_news($item['post_title'], $item['post_content'], $item['post_date']);
       }
   }
   
   function create_finis_section($id, $title, $content) {
       global $_FN;
       
       // Logica per creare sezione
       // ...
   }
   
   function create_finis_news($title, $content, $date) {
       global $_FN;
       
       // Logica per creare news
       // ...
   }
   ```

#### Migrazione Utenti
1. **Esportazione utenti da WordPress**:
   ```sql
   SELECT ID, user_login, user_email, user_registered, 
   display_name, user_nicename
   FROM wp_users
   ```

2. **Importazione in FINIS**:
   ```php
   <?php
   // Script per importare utenti
   $users = load_users_from_file("exported_users.csv");
   
   foreach ($users as $user) {
       $newUser = array(
           'username' => $user['user_login'],
           'email' => $user['user_email'],
           'name' => $user['display_name'],
           'group' => 'users',  // gruppo predefinito
           'active' => 1
       );
       
       // Password temporanea casuale: FN_AddUser() ne salva l'hash bcrypt
       $password = bin2hex(random_bytes(8));
       
       // Inserisci utente (username in minuscolo, registrationdate impostata ad ora)
       if (!FN_AddUser($newUser, $password)) {
           echo "Utente non importato: {$user['user_login']}<br>";
       }
   }
   ```

   Gli hash delle password di WordPress non sono riutilizzabili: gli utenti scelgono una nuova password con il recupero password della pagina di login.

#### Migrazione Media
1. **Copia dei file media**:
   ```bash
   # Esempio di script bash per copiare immagini
   mkdir -p /path/to/finis/misc/uploads/images
   cp -R /path/to/wordpress/wp-content/uploads/* /path/to/finis/misc/uploads/
   ```

2. **Aggiornamento dei riferimenti**:
   ```php
   <?php
   // Script per aggiornare i riferimenti alle immagini nelle sezioni:
   // il contenuto di una pagina è in sections/<id>/section.<lingua>.html
   $files = glob("sections/*/section.*.html");
   
   foreach ($files as $file) {
       // Sostituisci i percorsi delle immagini
       $content = file_get_contents($file);
       $content = str_replace(
           'wp-content/uploads/', 
           'misc/uploads/', 
           $content
       );
       
       // Salva la pagina
       FN_Write($content, $file);
   }
   ```

### Da Joomla a FINIS

#### Migrazione del Database
1. **Esportazione dei contenuti da Joomla**:
   ```sql
   SELECT id, title, alias, introtext, `fulltext`, state, 
   created, publish_up
   FROM #__content
   WHERE state = 1
   ```

2. **Importazione in FINIS**:
   ```php
   <?php
   // Script per importare articoli Joomla
   $articles = load_data_from_file("joomla_articles.csv");
   
   foreach ($articles as $article) {
       // Unisci testo introduttivo e completo
       $content = $article['introtext'] . $article['fulltext'];
       
       // Crea un ID di sezione valido
       $sectionId = sanitize_section_id($article['alias']);
       
       if ($article['is_page']) {
           // Crea come sezione statica
           create_finis_section($sectionId, $article['title'], $content);
       } else {
           // Crea come articolo news
           create_finis_news($article['title'], $content, $article['created']);
       }
   }
   ```

#### Migrazione Utenti
1. **Esportazione utenti da Joomla**:
   ```sql
   SELECT id, username, email, name, registerDate
   FROM #__users
   WHERE block = 0
   ```

2. **Importazione in FINIS**:
   ```php
   <?php
   // Script per importare utenti Joomla
   $users = load_users_from_file("joomla_users.csv");
   
   foreach ($users as $user) {
       $newUser = array(
           'username' => $user['username'],
           'email' => $user['email'],
           'name' => $user['name'],
           'group' => 'users',  // gruppo predefinito
           'active' => 1
       );
       
       // Password temporanea casuale, salvata come hash bcrypt
       $password = bin2hex(random_bytes(8));
       
       // Inserisci utente
       FN_AddUser($newUser, $password);
   }
   ```

### Da Drupal a FINIS

#### Migrazione del Database
1. **Esportazione dei contenuti da Drupal**:
   ```sql
   SELECT n.nid, n.title, b.body_value, 
   FROM_UNIXTIME(n.created) AS created_date, n.type
   FROM node n
   JOIN node_revision r ON n.vid = r.vid
   JOIN field_data_body b ON r.vid = b.revision_id
   WHERE n.status = 1
   ```

2. **Importazione in FINIS**:
   ```php
   <?php
   // Script per importare nodi Drupal
   $nodes = load_data_from_file("drupal_nodes.csv");
   
   foreach ($nodes as $node) {
       // Determina il tipo di contenuto
       switch ($node['type']) {
           case 'page':
               // Crea come sezione statica
               $sectionId = 'page_' . $node['nid'];
               create_finis_section($sectionId, $node['title'], $node['body_value']);
               break;
               
           case 'article':
               // Crea come articolo news
               create_finis_news($node['title'], $node['body_value'], $node['created_date']);
               break;
               
           // Altri tipi di contenuto...
       }
   }
   ```

#### Migrazione Utenti
1. **Esportazione utenti da Drupal**:
   ```sql
   SELECT uid, name, mail, created, status
   FROM users
   WHERE uid > 0 AND status = 1
   ```

2. **Importazione in FINIS**:
   ```php
   <?php
   // Script per importare utenti Drupal
   $users = load_users_from_file("drupal_users.csv");
   
   foreach ($users as $user) {
       $newUser = array(
           'username' => $user['name'],
           'email' => $user['mail'],
           'group' => 'users',  // gruppo predefinito
           'active' => 1
       );
       
       // Password temporanea casuale, salvata come hash bcrypt
       $password = bin2hex(random_bytes(8));
       
       // Inserisci utente
       FN_AddUser($newUser, $password);
   }
   ```

### Da HTML Statico a FINIS

#### Migrazione dei Contenuti
1. **Analisi della struttura**:
   - Identifica le pagine principali
   - Mappa la navigazione
   - Cataloga gli asset (immagini, CSS, JS)

2. **Conversione in Sezioni FINIS**:
   ```php
   <?php
   // Script per importare pagine HTML statiche
   $directory = "path/to/html_site/";
   $files = glob($directory . "*.html");
   
   foreach ($files as $file) {
       $content = file_get_contents($file);
       $filename = basename($file, ".html");
       
       // Estrai titolo dalla pagina HTML
       preg_match("/<title>(.*?)<\/title>/i", $content, $matches);
       $title = isset($matches[1]) ? $matches[1] : $filename;
       
       // Estrai contenuto dal body
       preg_match("/<body>(.*?)<\/body>/is", $content, $matches);
       $bodyContent = isset($matches[1]) ? $matches[1] : $content;
       
       // Pulisci il contenuto
       $cleanContent = clean_html_content($bodyContent);
       
       // Crea una sezione per ogni pagina HTML
       create_finis_section($filename, $title, $cleanContent);
   }
   
   function clean_html_content($html) {
       // Rimuovi elementi non necessari (menu, footer, ecc.)
       // Aggiusta i percorsi relativi
       // Sanitizza l'HTML
       
       return $cleaned_html;
   }
   ```

3. **Migrazione degli Asset**:
   ```bash
   # Copia immagini e altri asset
   mkdir -p /path/to/finis/misc/uploads/images
   cp -R /path/to/html_site/images/* /path/to/finis/misc/uploads/images/
   ```

4. **Adattamento dei Template**:
   - Analizza il layout originale
   - Crea un tema FINIS che riproduca l'aspetto del sito statico
   - Modifica i percorsi dei file CSS e JS

## Aggiornamenti di FINIS

### Aggiornamento da una Versione Precedente

#### Preparazione
1. **Backup completo**:
   ```bash
   # Backup file
   tar -czf finis_backup_files.tar.gz /path/to/finis
   
   # Backup database (esempio per MySQL)
   mysqldump -u username -p dbname > finis_backup_db.sql
   ```

2. **Verifica dei requisiti**:
   - Controlla che il server soddisfi i requisiti della nuova versione (PHP 7.1 o superiore)
   - Verifica la compatibilità dei moduli personalizzati
   - Leggi le note di rilascio per cambiamenti significativi

#### Procedura di Aggiornamento
1. **Download dei nuovi file**:
   - Scarica l'ultima versione di FINIS
   - Decomprimila in una cartella temporanea

2. **Sostituzione dei file**:
   - Installazione con sorgenti separati (`src_application`): sostituisci per intero la cartella del framework; la cartella dell'applicazione (sezioni, temi, dati, configurazione) non viene toccata:
     ```bash
     mv /path/to/finis_src /path/to/finis_src.old
     cp -R /path/to/new_version/src /path/to/finis_src
     ```
   - Installazione standard (la cartella del sito contiene il contenuto di `src/`): il pacchetto non contiene `config.vars.local.php`, `index.php`, `.htaccess` né `misc/`, che quindi restano; `sections/` contiene le pagine del sito e va esclusa:
     ```bash
     # Cartelle del core sostituite per intero: i file eliminati da FINIS non devono restare
     # (ricopia dopo i tuoi moduli, le tue sezioni del pannello di controllo e i tuoi file in include/)
     rm -rf /path/to/finis/include
     rm -rf /path/to/finis/modules
     rm -rf /path/to/finis/controlcenter
     
     # Copia i nuovi file
     rsync -a --exclude='sections/' /path/to/new_version/src/ /path/to/finis/
     ```

3. **Aggiornamento del database**:
   - Non c'è uno script di aggiornamento: apri il sito. A ogni richiesta FINIS crea in `misc/fndatabase/` i descrittori delle tabelle di sistema che mancano; con MySQL le colonne dei campi aggiunti a un descrittore vengono create automaticamente
   - I descrittori esistenti delle tabelle di sistema non vengono mai sostituiti: leggi [Aggiornamento alla Versione Attuale](#aggiornamento-alla-versione-attuale) per le modifiche da fare a mano

4. **Verifica post-aggiornamento**:
   - Controlla che tutte le pagine funzionino correttamente
   - Verifica la correttezza dei contenuti
   - Controlla il log degli errori

### Aggiornamento alla Versione Attuale

Note per un sito installato con una versione precedente di FINIS. Leggile prima di sostituire i file.

#### Segreto del Sito
Alla prima richiesta FINIS crea il segreto del sito `misc/secret.key.php` (permessi 0600), che firma i cookie di login e i token OAuth dei client MCP e cifra le copie delle chiavi API. Da questo momento fa parte dei dati:

- salvalo nei backup insieme a `misc/` e conservalo quando sposti il sito su un altro server
- non metterlo mai in un repository: se il sito è sotto git, aggiungi `misc/secret.key.php` a `.gitignore`
- se va perso ne viene creato uno nuovo: tutti gli utenti vengono disconnessi, i client OAuth devono rifare il login e gli amministratori non possono più mostrare le chiavi API esistenti (le chiavi continuano a funzionare)

#### Login e Password
- Il cookie di login `secid` ora è un HMAC-SHA256 firmato con il segreto del sito: i cookie della versione precedente vengono rifiutati, quindi dopo l'aggiornamento ogni utente deve rifare il login una volta. "Ricordami" dura `remember_login_days` giorni (predefinito 30).
- Le password salvate come md5, hash di `crypt()` o in chiaro vengono ancora accettate e sostituite da un hash bcrypt al primo login riuscito di ciascun utente: non c'è niente da fare.
- I codici di registrazione ora sono di 32 caratteri esadecimali: i codici di 5 caratteri inviati dalla versione precedente vengono rifiutati e non vengono reinviati. Attiva dal pannello di controllo (Utenti e gruppi > Utenti) gli utenti ancora in attesa di conferma.
- I link di recupero password inviati prima dell'aggiornamento non sono più validi: gli utenti ne chiedono uno nuovo.
- Dietro un reverse proxy imposta `$_FN['trusted_proxies']` in `config.vars.local.php`, perché il limite dei tentativi di login veda il vero indirizzo del client (vedi il [Manuale di Installazione](it_manuale_installazione.md)).

#### Chiavi API
Le chiavi API salvate in chiaro dalle versioni precedenti continuano a funzionare e vengono sostituite dal loro hash al primo uso. I campi `apikey`, `apikey_date` e `apikey_enc` vengono aggiunti a `misc/fndatabase/fn_users.php` la prima volta che una chiave viene usata o generata (il file deve essere scrivibile). Una chiave creata quando veniva salvato solo l'hash non si può più mostrare: generane una nuova. Con l'estensione `openssl` gli amministratori possono mostrare le chiavi create da ora in poi. Vedi la [guida MCP](it_guida_mcp.md).

#### Configurazione
- `display_errors` è "off" per default: imposta `$_FN['display_errors'] = "on";` in `config.vars.local.php` sulle copie di sviluppo. Con "off", quello che uno script stampa fuori dal template del tema (ad esempio un `echo` in uno script di autoexec) viene scartato.
- Un punto di ingresso `index.php?fnapp=<nome>` deve avere un nome fatto di lettere, cifre e `_`.

#### Descrittori delle Tabelle
- `defaultvalue` in un descrittore ora viene salvato così come è scritto: il driver `xmlphp` non espande più le variabili PHP che contiene e il driver `serialize` non lo valuta più come espressione PHP. Sostituisci questi valori con valori semplici e imposta quelli dinamici nel codice prima di salvare il record.
- `misc/fndatabase/fn_oauth_providers.php` dei siti installati con i campi `name`, `client_id`, `client_secret`, `redirect_uri`, `enabled` non ha i campi usati dal login con provider esterni (`avatar`, `auth_url`, `token_url`, `userinfo_url`, `scope`) e non viene sostituito. Copia sopra di esso `src/include/install/misc/fndatabase/fn_oauth_providers.php` (oppure aggiungi i campi), poi inserisci gli indirizzi di ogni provider nel pannello di controllo (Utenti e gruppi > Provider OAuth).
- Il modulo news usa la chiave primaria `id` della sua tabella delle notizie. Una tabella news creata da una vecchia versione con la chiave primaria `unirecid` va convertita: rinomina il campo nel descrittore e nei record salvati (con MySQL `ALTER TABLE ... CHANGE`), prima su una copia.

#### Viste di Database (dbview) ed Editor delle Tabelle
- Le operazioni di scrittura (nuovo, modifica, eliminazione) richiedono un utente collegato con i permessi della sezione: le sezioni in cui i visitatori anonimi aggiungevano record devono ora chiedere il login. `FN_XMETATableEditor()` non consente scritture quando nessuno è collegato.
- L'eliminazione di un record richiede un POST con un token anti-CSRF: un link di eliminazione apre un modulo di conferma.
- La cronologia delle versioni dei record è visibile solo agli amministratori.

#### Server Web
Il blocco del `.htaccess` scritto da FINIS (`# BEGIN Finis` ... `# END Finis`) ora passa a PHP l'intestazione `Authorization` e risponde a `/.well-known/oauth-authorization-server` e `/.well-known/oauth-protected-resource/...` per il login OAuth dei client MCP. Se mod_rewrite viene rilevato e `.htaccess` è scrivibile, il blocco viene riscritto dalla prima richiesta a `index.php`; altrimenti aggiungi le regole a mano (vedi il [Manuale di Installazione](it_manuale_installazione.md) e, per un sito in una sottocartella, la [guida MCP](it_guida_mcp.md)).

#### Codice Personalizzato
La libreria del database è in `src/include/xmetadb/` e le sue classi sono nel namespace `Xmetadb`, con alias che mantengono i vecchi nomi (`XMETATable`, `XMETADatabase`, ...). Uno script autonomo che carica la libreria include `include/xmetadb.php` e `include/xmetadb_query.php`, rimasti come caricatori, e non i file dentro la cartella.

### Aggiornamento del Database

#### Migrazione tra Driver di Database
Per cambiare il driver di database (es. da XML a MySQL):

1. **Configurazione del nuovo database**:
   ```php
   // Aggiorna config.vars.local.php
   $_FN['default_database_driver'] = "mysql";
   $_FN['xmetadb_mysqlhost'] = "localhost";
   $_FN['xmetadb_mysqldatabase'] = "finis_db";
   $_FN['xmetadb_mysqlusername'] = "username";
   $_FN['xmetadb_mysqlpassword'] = "password";
   ```

2. **Esportazione dal database corrente**:
   ```php
   <?php
   // Script per esportare dati da un driver all'altro
   require_once "path/to/FINIS.php";
   $FINIS = new FINIS();
   
   // Lista delle tabelle da migrare
   $tables = array(
       "fn_sections", "fn_users", "fn_groups", "fn_settings",
       "fn_blocks", "fn_blockslocation", "fn_conditions",
       // altre tabelle...
   );
   
   // Configura driver di origine (originale)
   $_FN['default_database_driver'] = "xmlphp";  // Ad esempio
   
   foreach ($tables as $tableName) {
       // Ottieni i dati dalla tabella origine
       $sourceTable = FN_XMDBTable($tableName);
       $records = $sourceTable->GetRecords();
       
       // Salva in formato intermedio
       file_put_contents(
           "export_{$tableName}.json", 
           json_encode($records)
       );
   }
   ```

3. **Importazione nel nuovo database**:
   ```php
   <?php
   // Script per importare i dati nel nuovo driver
   require_once "path/to/FINIS.php";
   $FINIS = new FINIS();
   
   // Configura driver di destinazione
   $_FN['default_database_driver'] = "mysql";  // Ad esempio
   
   $tables = array(
       "fn_sections", "fn_users", "fn_groups", "fn_settings",
       "fn_blocks", "fn_blockslocation", "fn_conditions",
       // altre tabelle...
   );
   
   foreach ($tables as $tableName) {
       // Carica i dati dal formato intermedio
       $records = json_decode(
           file_get_contents("export_{$tableName}.json"),
           true
       );
       
       if (!$records) continue;
       
       // Importa nel nuovo database
       $destTable = FN_XMDBTable($tableName);
       
       foreach ($records as $record) {
           $destTable->InsertRecord($record);
       }
       
       echo "Migrati " . count($records) . " record per $tableName<br>";
   }
   ```

#### Aggiornamento Schema Database
Per aggiornare lo schema del database dopo un aggiornamento di versione:

1. **Backup delle tabelle esistenti**
2. **Esecuzione dello script di aggiornamento**:
   ```php
   <?php
   // Script per aggiornare lo schema
   require_once "path/to/FINIS.php";
   $FINIS = new FINIS();
   
   // Forza la reinstallazione delle tabelle di sistema
   FN_InitTables(true);
   
   echo "Schema database aggiornato con successo";
   ```

   Attenzione: `FN_InitTables(true)` sovrascrive i descrittori delle tabelle di sistema in `misc/fndatabase/` con quelli di installazione, perdendo i campi aggiunti a mano (ad esempio i campi personalizzati di `fn_users.php`). Fai prima una copia di `misc/fndatabase/`. Senza parametro, `FN_InitTables()` crea solo i descrittori mancanti e viene già eseguita a ogni richiesta del sito.

### Migrazione dei Temi

#### Adattamento di Temi Personalizzati
Per adattare un tema personalizzato alla nuova versione:

1. **Analisi dei cambiamenti**:
   - Controlla le modifiche nei template di base
   - Identifica nuove variabili o funzionalità

2. **Aggiornamento dei Template**:
   ```php
   <?php
   // Script per verificare compatibilità template
   $themeDir = "/path/to/finis/themes/mytheme/";
   $baseTemplateFile = "/path/to/finis/themes/default/template.tp.html";
   $customTemplateFile = $themeDir . "template.tp.html";
   
   $baseTemplate = file_get_contents($baseTemplateFile);
   $customTemplate = file_get_contents($customTemplateFile);
   
   // Estrai variabili dal template base
   preg_match_all('/{\$(.*?)}/', $baseTemplate, $baseVars);
   
   // Estrai variabili dal template personalizzato
   preg_match_all('/{\$(.*?)}/', $customTemplate, $customVars);
   
   // Trova variabili mancanti
   $missingVars = array_diff($baseVars[1], $customVars[1]);
   
   echo "Variabili mancanti nel template personalizzato:<br>";
   foreach ($missingVars as $var) {
       echo "{\$$var}<br>";
   }
   ```

3. **Aggiornamento dei CSS**:
   - Verifica che i selettori CSS corrispondano alla nuova struttura HTML
   - Aggiungi stili per nuovi elementi
   - Rimuovi stili obsoleti

## Migrazione dei Dati

### Importazione/Esportazione CSV

#### Esportazione in CSV
```php
<?php
// Script per esportare dati in CSV
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

$tableName = "fn_users";  // Esempio
$table = FN_XMDBTable($tableName);
$records = $table->GetRecords();

// Apri file per scrittura
$fp = fopen("{$tableName}_export.csv", 'w');

// Scrivi intestazioni
if (count($records) > 0) {
    fputcsv($fp, array_keys($records[0]));
}

// Scrivi dati
foreach ($records as $record) {
    fputcsv($fp, $record);
}

fclose($fp);
echo "Esportazione completata: {$tableName}_export.csv";
```

#### Importazione da CSV
```php
<?php
// Script per importare dati da CSV
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

$tableName = "fn_users";  // Esempio
$table = FN_XMDBTable($tableName);

// Apri file CSV
$fp = fopen("{$tableName}_import.csv", 'r');

// Leggi intestazioni
$headers = fgetcsv($fp);

// Leggi e importa dati
while (($data = fgetcsv($fp)) !== false) {
    $record = array_combine($headers, $data);
    $table->InsertRecord($record);
}

fclose($fp);
echo "Importazione completata";
```

### Importazione/Esportazione XML

#### Esportazione in XML
```php
<?php
// Script per esportare dati in XML
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

$tableName = "fn_sections";  // Esempio
$table = FN_XMDBTable($tableName);
$records = $table->GetRecords();

// Crea documento XML
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><records></records>');

// Aggiungi record
foreach ($records as $record) {
    $xmlRecord = $xml->addChild('record');
    
    foreach ($record as $field => $value) {
        $xmlRecord->addChild($field, htmlspecialchars($value));
    }
}

// Salva file XML
$xml->asXML("{$tableName}_export.xml");
echo "Esportazione XML completata: {$tableName}_export.xml";
```

#### Importazione da XML
```php
<?php
// Script per importare dati da XML
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

$tableName = "fn_sections";  // Esempio
$table = FN_XMDBTable($tableName);

// Carica file XML
$xml = simplexml_load_file("{$tableName}_import.xml");

// Importa record
foreach ($xml->record as $xmlRecord) {
    $record = array();
    
    foreach ($xmlRecord->children() as $field) {
        $record[$field->getName()] = (string)$field;
    }
    
    $table->InsertRecord($record);
}

echo "Importazione XML completata";
```

### Migrazione dei File

#### Trasferimento della Struttura di File
```bash
# Script per trasferire file tra installazioni
rsync -av --exclude='config.vars.local.php' \
          --exclude='misc/*' \
          --exclude='.git' \
          /path/to/old_finis/ /path/to/new_finis/
```

#### Migrazione Selettiva dei File
```php
<?php
// Script per migrare selettivamente file di upload
$sourceDir = "/path/to/old_finis/misc/uploads/";
$destDir = "/path/to/new_finis/misc/uploads/";

// Crea directory di destinazione se non esiste
if (!file_exists($destDir)) {
    mkdir($destDir, 0755, true);
}

// Copia file più recenti di una certa data
$cutoffDate = strtotime("2023-01-01");  // Esempio

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir)
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $filePath = $file->getPathname();
        $relativePath = str_replace($sourceDir, '', $filePath);
        $destPath = $destDir . $relativePath;
        
        // Controlla data del file
        if ($file->getMTime() > $cutoffDate) {
            // Crea directory di destinazione se necessario
            $destDirPath = dirname($destPath);
            if (!file_exists($destDirPath)) {
                mkdir($destDirPath, 0755, true);
            }
            
            // Copia il file
            copy($filePath, $destPath);
            echo "Copiato: $relativePath<br>";
        }
    }
}

echo "Migrazione file completata";
```

## Ottimizzazione Post-Migrazione

### Cache e Performance

#### Pulizia della Cache
```php
<?php
// Script per pulire la cache
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

// Elimina e ricrea la cartella della cache {datadir}/_cache
// (i contatori dei tentativi di login in _login_attempts restano)
FN_ClearCache();

echo "Cache svuotata con successo";
```

#### Ottimizzazione Database
```php
<?php
// Script per ottimizzare tabelle MySQL
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

// Solo per driver MySQL
if ($_FN['default_database_driver'] == "mysql") {
    $tables = array(
        "fn_sections", "fn_users", "fn_groups",
        // altre tabelle...
    );
    
    // Connessione diretta al database
    $conn = mysqli_connect(
        $_FN['xmetadb_mysqlhost'],
        $_FN['xmetadb_mysqlusername'],
        $_FN['xmetadb_mysqlpassword'],
        $_FN['xmetadb_mysqldatabase']
    );
    
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "OPTIMIZE TABLE $table");
        if ($result) {
            echo "Tabella $table ottimizzata<br>";
        } else {
            echo "Errore ottimizzando $table: " . mysqli_error($conn) . "<br>";
        }
    }
    
    mysqli_close($conn);
    
    echo "Ottimizzazione database completata";
}
```

### Verifica dell'Integrità dei Dati

#### Controllo delle Relazioni
```php
<?php
// Script per verificare l'integrità delle relazioni
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

// Esempio: verifica relazioni sezioni -> blocchi
$sectionsTable = FN_XMDBTable("fn_sections");
$blocksTable = FN_XMDBTable("fn_blocks");

$sections = $sectionsTable->GetRecords();
$blocks = $blocksTable->GetRecords();

$sectionIds = array();
foreach ($sections as $section) {
    $sectionIds[] = $section['id'];
}

$errors = 0;
foreach ($blocks as $block) {
    if (isset($block['section']) && $block['section'] != "*") {
        $blockSections = explode(",", $block['section']);
        
        foreach ($blockSections as $blockSection) {
            if (!in_array($blockSection, $sectionIds) && $blockSection != "*") {
                echo "Errore: Blocco ID {$block['id']} fa riferimento a una sezione inesistente: $blockSection<br>";
                $errors++;
            }
        }
    }
}

echo "Verifica completata: $errors errori trovati";
```

#### Controllo Immagini Orfane
```php
<?php
// Script per trovare immagini orfane
require_once "path/to/FINIS.php";
$FINIS = new FINIS();

// Directory delle immagini
$imageDir = $_FN['datadir'] . "/uploads/images/";
$images = glob($imageDir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);

// Raccogli tutti i contenuti
$allContent = "";

// Sezioni
$sectionsTable = FN_XMDBTable("fn_sections");
$sections = $sectionsTable->GetRecords();
foreach ($sections as $section) {
    if (isset($section['content'])) {
        $allContent .= $section['content'];
    }
}

// News
$newsTable = FN_XMDBTable("fn_news");
if ($newsTable) {
    $news = $newsTable->GetRecords();
    foreach ($news as $article) {
        if (isset($article['content'])) {
            $allContent .= $article['content'];
        }
    }
}

// Controlla ogni immagine
$orphanedImages = array();
foreach ($images as $image) {
    $imageName = basename($image);
    if (strpos($allContent, $imageName) === false) {
        $orphanedImages[] = $imageName;
    }
}

echo "Immagini orfane trovate: " . count($orphanedImages) . "<br>";
foreach ($orphanedImages as $image) {
    echo "$image<br>";
}
```

## Rollback di Emergenza

### Piano di Rollback
In caso di problemi durante la migrazione, è importante avere un piano di rollback:

1. **Backup pre-migrazione**:
   - Database completo
   - File del sito
   - Configurazioni

2. **Piano di ripristino**:
   - Documento con passaggi dettagliati
   - Tempo stimato per il rollback
   - Personale necessario

### Procedura di Rollback

#### Ripristino dei File
```bash
# Ripristina i file dal backup
rm -rf /path/to/finis
tar -xzf finis_backup_files.tar.gz -C /
```

#### Ripristino del Database
```bash
# Per MySQL
mysql -u username -p dbname < finis_backup_db.sql

# Per SQLite
cp finis_backup.sqlite3 /path/to/finis/misc/database.sqlite3
```

#### Verifica Post-Rollback
Dopo il ripristino, verifica:

1. Accesso al sito
2. Funzionalità principali
3. Integrità dei contenuti
4. Log di errori

## Best Practices

### Prima della Migrazione
- Effettua sempre un backup completo
- Testa la migrazione in un ambiente di sviluppo
- Informa gli utenti del periodo di manutenzione
- Pianifica la migrazione in orari di basso traffico
- Verifica i requisiti di sistema

### Durante la Migrazione
- Monitora il processo
- Mantieni un log dettagliato
- Segui la procedura passo-passo
- Verifica ogni fase prima di procedere
- Mantieni i backup accessibili

### Dopo la Migrazione
- Verifica tutte le funzionalità principali
- Controlla i log per errori
- Monitora le performance
- Raccogli feedback dagli utenti
- Documenta eventuali problemi e soluzioni

## Risorse Aggiuntive
- [Link alla documentazione ufficiale]
- [Link agli script di migrazione]
- [Link al forum di supporto]