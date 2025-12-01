# Guida al Layer di Astrazione Database di FINIS

## Introduzione

Il framework FINIS offre un potente layer di astrazione del database chiamato XMETATable che consente di interagire con diversi sistemi di archiviazione dati in modo unificato. Questa guida illustra come utilizzare XMETATable per gestire i dati dell'applicazione indipendentemente dal database sottostante.

## Concetti Fondamentali

### Architettura di XMETATable

XMETATable è una classe che implementa un'architettura a driver multipli per l'accesso ai dati. Le caratteristiche principali sono:

1. **Driver Multipli**: Supporta vari sistemi di archiviazione (MySQL, SQLite, XML/PHP, CSV, etc.)
2. **Interfaccia Unificata**: Stesse funzioni per tutti i driver
3. **Migrazione Semplificata**: Possibilità di passare da un sistema all'altro senza modificare il codice dell'applicazione
4. **Gestione File Incorporata**: Funzionalità per gestire file e immagini collegati ai record

### Driver Disponibili

FINIS supporta i seguenti driver di database:

- `xmlphp`: Archiviazione in file XML/PHP (opzione predefinita)
- `mysql`: MySQL/MariaDB
- `sqlite`: SQLite
- `sqlite3`: SQLite versione 3
- `sqlserver`: Microsoft SQL Server
- `csv`: File CSV
- `serialize`: File PHP serializzati

## Utilizzo Base

### Ottenere un'Istanza di XMETATable

Per ottenere un'istanza di una tabella, utilizzare la funzione `FN_XMDBTable()`:

```php
// Ottieni un riferimento alla tabella degli utenti
$usersTable = FN_XMDBTable("fn_users");
```

### Definizione di Tabelle

Le tabelle sono definite attraverso file XML con estensione `.php`. Esempio di file descrittore:

```xml
<?php exit(0);?>
<tables>
  <field>
    <n>id</n>
    <type>int</type>
    <primarykey>1</primarykey>
    <extra>autoincrement</extra>
  </field>
  <field>
    <n>username</n>
    <type>string</type>
    <size>50</size>
  </field>
  <field>
    <n>email</n>
    <type>string</type>
    <size>100</size>
  </field>
  <field>
    <n>password</n>
    <type>string</type>
    <size>32</size>
  </field>
  <field>
    <n>regdate</n>
    <type>datetime</type>
  </field>
  <driver>xmlphp</driver>
</tables>
```

### Operazioni CRUD di Base

#### Inserimento Record

```php
// Crea un nuovo utente
$userData = array(
    'username' => 'nuovoutente',
    'email' => 'utente@example.com',
    'password' => md5('password123'),
    'regdate' => FN_Now()
);

// Inserisci il record e ottieni il record completo con ID generato
$newUser = $usersTable->InsertRecord($userData);
```

#### Lettura Record

```php
// Ottieni tutti i record
$allUsers = $usersTable->GetRecords();

// Ottieni record con filtro
$adminUsers = $usersTable->GetRecords(array('group' => 'admin'));

// Ottieni record per chiave primaria
$user = $usersTable->GetRecordByPrimaryKey(5);

// Ottieni un singolo record con filtro
$user = $usersTable->GetRecord(array('username' => 'admin'));
```

#### Aggiornamento Record

```php
// Aggiorna un record per chiave primaria
$usersTable->UpdateRecordBypk(
    array('email' => 'nuovo@esempio.com'),
    'id',
    5
);

// Aggiorna un record fornendo i dati completi
$userData = array(
    'id' => 5,
    'email' => 'nuovo@esempio.com',
    'lastlogin' => FN_Now()
);
$usersTable->UpdateRecord($userData);
```

#### Eliminazione Record

```php
// Elimina un record per ID
$usersTable->DelRecord(5);
```

## Funzionalità Avanzate

### Paginazione e Ordinamento

```php
// Ottieni record paginati (dal 10° al 20° record)
$users = $usersTable->GetRecords(false, 10, 10);

// Ottieni record ordinati per nome (ascendente)
$users = $usersTable->GetRecords(false, false, false, 'username');

// Ottieni record ordinati per data (discendente)
$users = $usersTable->GetRecords(false, false, false, 'regdate', true);

// Ordinamento multiplo
$users = $usersTable->GetRecords(false, false, false, 'group,username');
```

### Filtri Complessi

```php
// Filtraggio semplice con array
$activeUsers = $usersTable->GetRecords(array(
    'active' => 1,
    'group' => 'users'
));

// Filtraggio avanzato con stringa SQL (solo con driver SQL)
$recentUsers = $usersTable->GetRecords("regdate > '2023-01-01' AND active = 1");
```

### Conteggio Record

```php
// Conteggio di tutti i record
$totalUsers = $usersTable->GetNumRecords();

// Conteggio con filtro
$totalActiveUsers = $usersTable->GetNumRecords(array('active' => 1));
```

### Gestione Files e Immagini

XMETATable gestisce automaticamente l'upload, l'archiviazione e il recupero di file e immagini associate ai record:

```php
// In un form HTML:
<form method="post" enctype="multipart/form-data">
    <input type="file" name="avatar">
    <!-- altri campi -->
    <button type="submit">Salva</button>
</form>

// Nel codice PHP per salvare:
if ($_FILES['avatar']['tmp_name']) {
    $userData['avatar'] = $_FILES['avatar']['name'];
    $usersTable->UpdateRecordBypk($userData, 'id', $userId);
    // L'upload del file viene gestito automaticamente da XMETATable
}

// Per recuperare il percorso dell'immagine:
$user = $usersTable->GetRecordByPrimaryKey($userId);
$avatarPath = $usersTable->getFilePath($user, 'avatar');

// Per recuperare l'URL dell'anteprima (per immagini):
$thumbUrl = $usersTable->get_thumb($user, 'avatar');
```

## Configurazione Database

### Configurazione MySQL

Per configurare una connessione MySQL, modifica il file `config.vars.local.php`:

```php
// Configurazione per driver MySQL
$_FN['default_database_driver'] = 'mysql';
$_FN['xmetadb_mysqlhost'] = 'localhost';
$_FN['xmetadb_mysqldatabase'] = 'finis';
$_FN['xmetadb_mysqlusername'] = 'username';
$_FN['xmetadb_mysqlpassword'] = 'password';
```

### Configurazione SQLite

Per utilizzare SQLite come database:

```php
// Configurazione per driver SQLite
$_FN['default_database_driver'] = 'sqlite';
$_FN['xmetadb_sqlitepath'] = $path_to_db_file;
```

### Configurazione a Livello di Tabella

È possibile sovrascrivere la configurazione globale a livello di tabella nel file descrittore XML:

```xml
<tables>
  <!-- definizione campi -->
  <driver>mysql</driver>
  <host>db.esempio.com</host>
  <user>username</user>
  <password>password</password>
  <database>my_database</database>
  <sqltable>table_name</sqltable>
</tables>
```

## Migrazione Tra Database

### Da XML/PHP a MySQL

Per migrare una tabella da XML/PHP a MySQL:

```php
require_once "path/to/include/xmetadb/XMETATable_mysql.php";

$connection = array(
    'host' => 'localhost',
    'user' => 'username',
    'password' => 'password',
    'database' => 'my_database',
    'sqltable' => 'users'
);

xml_to_sql('database_name', 'fn_users', 'misc', $connection);
```

## Schemi e Definizioni Campi

### Tipi di Campi Supportati

- `string` / `varchar`: Stringhe di testo (lunghezza limitata)
- `text`: Testo lungo
- `html`: Testo HTML
- `int`: Numeri interi
- `datetime`: Date e timestamp
- `file`: Campo per l'upload di file
- `image`: Campo per l'upload di immagini (con generazione automatica di miniature)
- `base64file`: File archiviati come base64 nel database

### Proprietà dei Campi

```xml
<field>
  <n>campo_nome</n>         <!-- nome del campo -->
  <type>string</type>       <!-- tipo di campo -->
  <size>100</size>          <!-- dimensione (per varchar) -->
  <primarykey>1</primarykey> <!-- indica chiave primaria -->
  <extra>autoincrement</extra> <!-- campo auto-incrementante -->
  <thumbsize>100</thumbsize>   <!-- dimensione miniatura per immagini -->
  <mysql_default>NULL</mysql_default> <!-- valore predefinito (MySQL) -->
  <mysql_on_update>CURRENT_TIMESTAMP</mysql_on_update> <!-- aggiornamento automatico -->
</field>
```

## Ottimizzazione delle Prestazioni

### Uso della Cache

XMETATable può utilizzare una cache per migliorare le prestazioni:

```xml
<tables>
  <!-- definizione campi -->
  <usecachefile>1</usecachefile>
</tables>
```

### Operazioni Rapide

Per inserimenti o aggiornamenti rapidi senza gestione file:

```php
// Inserimento veloce (senza gestione file)
$usersTable->InsertRecordFast($userData);

// Aggiornamento veloce (senza gestione file)
$usersTable->UpdateRecordFast($userData);
```

## Estensione del Sistema

### Creazione di un Driver Personalizzato

Per creare un nuovo driver per XMETATable:

1. Crea una classe che estende `stdClass` nel file `XMETATable_miodriver.php`
2. Implementa tutti i metodi richiesti (GetRecords, InsertRecord, UpdateRecordBypk, DelRecord, ecc.)
3. Registra il driver nel descrittore XML della tabella

Esempio di struttura di base:

```php
<?php
class XMETATable_miodriver extends stdClass
{
    function __construct(&$xmltable, $params = false)
    {
        // Inizializzazione
    }
    
    function GetRecords($restr = false, $min = false, $length = false, $order = false, $reverse = false, $fields = array())
    {
        // Implementazione
    }
    
    function InsertRecord($values)
    {
        // Implementazione
    }
    
    // Altri metodi richiesti
}
```

## Best Practices

1. **Chiavi Primarie**: Definisci sempre una chiave primaria per ogni tabella
2. **Transazioni**: Quando possibile, usa transazioni per operazioni multiple
3. **Campi Null**: Gestisci correttamente i valori null per ogni driver
4. **Sicurezza**: Usa sempre `FN_GetParam()` per ottenere i dati utente prima di inserirli
5. **Backup**: Crea backup regolari dei dati, specialmente quando usi il driver xmlphp

## Risoluzione Problemi

### Problemi Comuni

- **Errore "Table not exists"**: Verifica che il file descrittore della tabella esista nel percorso corretto.
- **Errore "File not writable"**: Controlla i permessi di scrittura della directory.
- **Errore "Database not writable"**: Verifica che l'utente del database abbia i permessi corretti.
- **Errore di connessione MySQL**: Controlla le credenziali e che il server sia in esecuzione.

### Debug

Per attivare il logging degli errori del database:

```php
define("XMETADB_DEBUG_FILE_LOG", "/path/to/logfile.log");
```

## Esempi Pratici

### Esempio 1: Sistema di Gestione Utenti

```php
// Ottenere istanza della tabella utenti
$usersTable = FN_XMDBTable("fn_users");

// Registrazione utente
function registerUser($username, $email, $password) {
    global $usersTable;
    
    // Verifica se l'utente esiste già
    $existingUser = $usersTable->GetRecord(array('username' => $username));
    if ($existingUser) {
        return array('error' => 'Username già in uso');
    }
    
    // Registra nuovo utente
    $userData = array(
        'username' => $username,
        'email' => $email,
        'password' => md5($password),
        'regdate' => FN_Now(),
        'active' => 1,
        'group' => 'users'
    );
    
    $newUser = $usersTable->InsertRecord($userData);
    return $newUser ? array('success' => true, 'user' => $newUser) : array('error' => 'Errore di registrazione');
}

// Login utente
function loginUser($username, $password) {
    global $usersTable;
    
    $user = $usersTable->GetRecord(array(
        'username' => $username,
        'password' => md5($password),
        'active' => 1
    ));
    
    if (!$user) {
        return array('error' => 'Credenziali non valide');
    }
    
    // Aggiorna ultimo accesso
    $usersTable->UpdateRecordBypk(
        array('lastlogin' => FN_Now()),
        'id',
        $user['id']
    );
    
    return array('success' => true, 'user' => $user);
}
```

### Esempio 2: Gestione di un Blog

```php
// Ottieni tabella post
$postsTable = FN_XMDBTable("fn_posts");

// Crea un nuovo post
function createPost($title, $content, $userId) {
    global $postsTable;
    
    $postData = array(
        'title' => $title,
        'content' => $content,
        'user_id' => $userId,
        'date_created' => FN_Now(),
        'status' => 'published'
    );
    
    return $postsTable->InsertRecord($postData);
}

// Ottieni post recenti
function getRecentPosts($limit = 10) {
    global $postsTable;
    
    return $postsTable->GetRecords(
        array('status' => 'published'),
        0,
        $limit,
        'date_created',
        true
    );
}

// Ottieni post per categoria
function getPostsByCategory($categoryId) {
    global $postsTable;
    
    return $postsTable->GetRecords(array(
        'category_id' => $categoryId,
        'status' => 'published'
    ));
}
```

## Riferimento API Completo

Per un elenco completo delle funzioni e dei metodi disponibili, consultare la documentazione API nella sezione "Classe XMETATable" della [Documentazione API di FINIS Framework](documentazione_api.md#classe-xmetatable).