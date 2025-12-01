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
       $table = FN_XMDBTable("fn_users");
       
       $newUser = array(
           'username' => $user['user_login'],
           'email' => $user['user_email'],
           'name' => $user['display_name'],
           'regdate' => $user['user_registered'],
           'group' => 'users',  // gruppo predefinito
           'active' => 1
       );
       
       // Genera password temporanea
       $newUser['password'] = FN_GenerateRandomPassword();
       
       // Inserisci utente
       $table->InsertRecord($newUser);
       
       // Opzionale: invia email con password temporanea
       FN_SendPasswordEmail($user['user_email'], $newUser['password']);
   }
   ```

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
   // Script per aggiornare i riferimenti alle immagini nelle sezioni
   $table = FN_XMDBTable("fn_sections");
   $sections = $table->GetRecords();
   
   foreach ($sections as $section) {
       if (isset($section['content'])) {
           // Sostituisci i percorsi delle immagini
           $content = $section['content'];
           $content = str_replace(
               'wp-content/uploads/', 
               'misc/uploads/', 
               $content
           );
           
           // Aggiorna il record
           $table->UpdateRecord(
               array('content' => $content),
               'id',
               $section['id']
           );
       }
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
       $table = FN_XMDBTable("fn_users");
       
       $newUser = array(
           'username' => $user['username'],
           'email' => $user['email'],
           'name' => $user['name'],
           'regdate' => date('Y-m-d H:i:s', strtotime($user['registerDate'])),
           'group' => 'users',  // gruppo predefinito
           'active' => 1
       );
       
       // Genera password temporanea
       $newUser['password'] = FN_GenerateRandomPassword();
       
       // Inserisci utente
       $table->InsertRecord($newUser);
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
       $table = FN_XMDBTable("fn_users");
       
       $newUser = array(
           'username' => $user['name'],
           'email' => $user['mail'],
           'regdate' => date('Y-m-d H:i:s', $user['created']),
           'group' => 'users',  // gruppo predefinito
           'active' => 1
       );
       
       // Genera password temporanea
       $newUser['password'] = FN_GenerateRandomPassword();
       
       // Inserisci utente
       $table->InsertRecord($newUser);
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
   - Controlla che il server soddisfi i requisiti della nuova versione
   - Verifica la compatibilità dei moduli personalizzati
   - Leggi le note di rilascio per cambiamenti significativi

#### Procedura di Aggiornamento
1. **Download dei nuovi file**:
   - Scarica l'ultima versione di FINIS
   - Decomprimila in una cartella temporanea

2. **Sostituzione dei file**:
   ```bash
   # Rimuovi i file del core, mantenendo configurazioni e contenuti
   rm -rf /path/to/finis/src/*.php
   rm -rf /path/to/finis/src/include
   rm -rf /path/to/finis/src/modules
   rm -rf /path/to/finis/src/controlcenter
   
   # Copia i nuovi file
   cp -R /path/to/new_version/src/* /path/to/finis/src/
   ```

3. **Esecuzione degli script di aggiornamento**:
   - Accedi all'URL: `http://tuosito.com/?mod=install&op=update`
   - Segui le istruzioni per completare l'aggiornamento

4. **Verifica post-aggiornamento**:
   - Controlla che tutte le pagine funzionino correttamente
   - Verifica la correttezza dei contenuti
   - Controlla il log degli errori

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

// Svuota directory cache
$cacheDir = $_FN['datadir'] . "/_cache";
$files = glob($cacheDir . '/*');

foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    } elseif (is_dir($file)) {
        // Rimuovi directory ricorsivamente
        $dirFiles = glob($file . '/*');
        foreach ($dirFiles as $dirFile) {
            if (is_file($dirFile)) {
                unlink($dirFile);
            }
        }
        rmdir($file);
    }
}

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