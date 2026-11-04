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

### Struttura della Libreria

| File | Contenuto |
|---|---|
| `src/include/xmetadb/xmetadb_core.php` | funzioni di supporto (lettura dei descrittori, `xmetadb_frm_type()`, `xmetadb_sql_column_type()`, `xmetadb_escape_like()`, `addxmltablefield()`, miniature, ...); carica `XMETATable.php` |
| `src/include/xmetadb/XMETATable.php` | classe `XMETATable`: descrittore, driver, file dei record |
| `src/include/xmetadb/XMETAField.php` | classe `XMETAField`: un campo del descrittore |
| `src/include/xmetadb/XMETADatabase.php` | classe `XMETADatabase`: il motore delle query in stile SQL |
| `src/include/xmetadb/XMETATable_<driver>.php` | i driver, caricati quando una tabella li usa |
| `src/include/xmetadb.php`, `src/include/xmetadb_query.php` | loader mantenuti per compatibilità: includono `xmetadb_core.php` e `XMETADatabase.php` |
| `src/include/xmetadb_frm.php`, `xmetadb_frm_search.php`, `xmetadb_editor.php`, `xmetadbfrm_fields/` | form, form di ricerca, editor delle tabelle e widget dei form |
| `src/include/functions_xmetadb.inc.php` | le funzioni FINIS `FN_XMDBTable()`, `FN_XMDBForm()`, `FN_XMETADBQuery()`, `FN_setCommonParams()` |

Le classi sono nel namespace `Xmetadb` (`Xmetadb\XMETATable`, `Xmetadb\XMETAField`,
`Xmetadb\XMETADatabase`, `Xmetadb\XMETATable_mysql`, ...) e ognuna ha un `class_alias()` con il
vecchio nome globale, quindi `new XMETATable(...)` e `XMETATable::createMetadbTable()`
continuano a funzionare. Le funzioni di `xmetadb_core.php` sono globali; quelle definite in un
file di driver sono nel namespace (`\Xmetadb\xml_to_sql()`).

La libreria funziona anche fuori da FINIS: basta includere `src/include/xmetadb.php` (definisce
`FN_GetParam()`, `FN_SaveFile()` e `FN_Copy()` se mancano).

### Driver Disponibili

FINIS supporta i seguenti driver di database:

- `xmlphp`: Archiviazione in file XML/PHP (opzione predefinita)
- `mysql`: MySQL/MariaDB
- `sqlite3`: SQLite versione 3 (estensione PHP `SQLite3`)
- `sqlserver`: Microsoft SQL Server (estensione PHP `sqlsrv`)
- `csv`: File CSV
- `serialize`: File PHP serializzati
- `sqlite`: obsoleto, richiede la classe `SQLiteDatabase` della vecchia estensione SQLite di
  PHP 5 e non funziona con le versioni attuali di PHP: usare `sqlite3`

## Utilizzo Base

### Ottenere un'Istanza di XMETATable

Per ottenere un'istanza di una tabella, utilizzare la funzione `FN_XMDBTable()`:

```php
// Get a reference to the table app_users of the site database
$usersTable = FN_XMDBTable("app_users");
```

`FN_XMDBTable($tablename, $params = array())` apre la tabella in `$_FN['database']` (cartella
`$_FN['datadir']`) con i parametri comuni (`FN_setCommonParams()`: ogni chiave stringa di `$_FN`
che contiene `xmetadb`, l'url del sito, il charset e il driver predefinito) e restituisce
un'istanza condivisa: la stessa tabella con gli stessi parametri viene costruita una volta per
richiesta (`XMETATable::xmetadbTable($databasename, $tablename, $path, $params)`).

Gli utenti del sito (`fn_users`, chiave primaria `username`, hash bcrypt in `passwd`) non si
scrivono direttamente: usare `FN_AddUser()`, `FN_UpdateUser()` e `FN_GetUser()`.

### Definizione di Tabelle

Le tabelle sono definite attraverso file XML con estensione `.php`, in
`{datadir}/{database}/{tabella}.php` (`misc/fndatabase/app_users.php`); il driver `xmlphp`
tiene i record nella cartella `misc/fndatabase/app_users/`. Esempio di file descrittore:

```xml
<?php exit(0);?>
<tables>
  <field>
    <name>id</name>
    <type>int</type>
    <primarykey>1</primarykey>
    <extra>autoincrement</extra>
  </field>
  <field>
    <name>username</name>
    <type>varchar</type>
    <size>50</size>
  </field>
  <field>
    <name>email</name>
    <type>varchar</type>
    <size>100</size>
  </field>
  <field>
    <name>password</name>
    <type>varchar</type>
    <size>255</size>
  </field>
  <field>
    <name>regdate</name>
    <type>datetime</type>
  </field>
  <driver>xmlphp</driver>
</tables>
```

Un descrittore si può creare anche da codice. `createMetadbTable()` restituisce false quando
crea la tabella e non sovrascrive mai un descrittore esistente (restituisce
`"xml table exists"` e ricrea solo la cartella dei dati se manca); `addxmltablefield()`
aggiunge un campo o aggiorna le proprietà di uno esistente. I valori vengono codificati per
l'XML.

```php
$fields = array(
    array('name' => 'id', 'type' => 'int', 'primarykey' => '1', 'extra' => 'autoincrement'),
    array('name' => 'title', 'type' => 'varchar', 'size' => '100'),
);
XMETATable::createMetadbTable($_FN['database'], 'app_notes', $fields, $_FN['datadir']);
addxmltablefield($_FN['database'], 'app_notes', array('name' => 'body', 'type' => 'text'), $_FN['datadir']);
```

### Operazioni CRUD di Base

#### Inserimento Record

```php
// Create a new user
$userData = array(
    'username' => 'nuovoutente',
    'email' => 'utente@example.com',
    'password' => password_hash('password123', PASSWORD_DEFAULT),
    'regdate' => FN_Now()
);

// Insert the record and get the complete record with the generated ID
$newUser = $usersTable->InsertRecord($userData);
if (!is_array($newUser)) {
    // false (SQL drivers) or an "error:..." string (xmlphp: duplicate or missing key)
}
```

#### Lettura Record

```php
// Get all records
$allUsers = $usersTable->GetRecords();

// Get records with a filter
$adminUsers = $usersTable->GetRecords(array('group' => 'admin'));

// Only some columns
$names = $usersTable->GetRecords(false, false, false, false, false, array('id', 'username'));

// Get a record by primary key
$user = $usersTable->GetRecordByPrimaryKey(5);

// Get a single record with a filter
$user = $usersTable->GetRecord(array('username' => 'admin'));
```

`GetRecord()` restituisce null quando nessun record corrisponde. `GetRecordByPrimaryKey()`
(alias di `GetRecordByPk()`) restituisce un valore vuoto quando la chiave non esiste: null con
`mysql` e `sqlite3`, false con `xmlphp` e `sqlserver`. Con `mysql`, false significa che la
query è fallita.

#### Aggiornamento Record

```php
// Update a record by primary key
$usersTable->UpdateRecordBypk(
    array('email' => 'nuovo@esempio.com'),
    'id',
    5
);

// Update a record giving the complete data
$userData = array(
    'id' => 5,
    'email' => 'nuovo@esempio.com',
    'lastlogin' => FN_Now()
);
$usersTable->UpdateRecord($userData);
```

Entrambi restituiscono il record dopo l'aggiornamento, false quando non esiste un record con
quella chiave, oppure una stringa di errore se l'aggiornamento fallisce: controllare il
risultato con `is_array()`.

#### Eliminazione Record

```php
// Delete a record by ID
$usersTable->DelRecord(5);
```

`DelRecord()` elimina anche la cartella con i file del record.

## Funzionalità Avanzate

### Paginazione e Ordinamento

```php
// Records sorted by name (ascending)
$users = $usersTable->GetRecords(false, false, false, 'username');

// Records sorted by date (descending)
$users = $usersTable->GetRecords(false, false, false, 'regdate', true);

// Several fields, direction per field (SQL drivers only)
$users = $usersTable->GetRecords(false, false, false, 'group,username:DESC');

// 10 records starting from the 11th
$users = $usersTable->GetRecords(false, 10, 10); // mysql, sqlite3, sqlserver
$users = $usersTable->GetRecords(false, 11, 10); // xmlphp, csv, serialize
```

- I driver SQL usano `$min` come offset di `LIMIT $min,$length` (da 0). I driver su file lo
  usano come posizione contata da 1 e applicano il limite solo se `$min` e `$length` sono
  entrambi impostati: `GetRecords(false, 0, 10)` restituisce tutti i record. Per codice che
  deve funzionare con ogni driver, leggere i record e usare `array_slice()`, oppure controllare
  `$usersTable->driver`.
- I driver SQL accettano un elenco di campi separati da virgola, ognuno con `:ASC` o `:DESC`
  facoltativo. `xmlphp` ordina solo per un singolo campo esistente e ignora ogni altro valore.

### Filtri Complessi

```php
// Simple filtering with an array
$activeUsers = $usersTable->GetRecords(array(
    'active' => 1,
    'group' => 'users'
));

// Advanced filtering with an SQL string (SQL drivers only)
$recentUsers = $usersTable->GetRecords("regdate > '2023-01-01' AND active = 1");
```

Una stringa SQL viene passata al database così com'è: non costruirla mai con valori della
richiesta. `xmlphp` non la supporta (warning PHP, array vuoto).

### Regole di Corrispondenza

- **Chiave primaria**: `GetRecordByPrimaryKey()`, `DelRecord()` e `UpdateRecordBypk()` dei
  driver `mysql`, `sqlite3` e `sqlserver` agiscono solo sul record con esattamente quella
  chiave: il valore è tra apici ed escapato, e `%` e `_` sono escapati
  (`xmetadb_escape_like()`). Se le maiuscole contano dipende dal database: le tabelle create da
  FINIS su MySQL (`utf8_bin`) distinguono le maiuscole, le collation predefinite di SQL Server
  no, SQLite ignora le maiuscole delle lettere ASCII in `GetRecordByPrimaryKey()` e
  `DelRecord()` ma non in `UpdateRecordBypk()`.
- **Filtri**: nei driver SQL ogni valore dell'array passato a `GetRecords()` o
  `GetNumRecords()` è un pattern `LIKE` (`%` e `_` sono caratteri jolly). `xmlphp` confronta il
  valore esattamente (distinguendo le maiuscole); un `%` iniziale e/o finale lo trasforma in una
  ricerca "finisce con", "inizia con" o "contiene" che ignora le maiuscole.
- **Campi `int`**: `mysql` e `sqlserver` scrivono un valore senza apici solo se è un intero
  (`-?[0-9]+`), ogni altro valore va tra apici e lo gestisce il database; `sqlite3` applica
  `intval()`. I form non controllano il tipo: convertire i valori della richiesta con `intval()`.

Quando un valore della richiesta deve trovare esattamente un record, confrontare di nuovo il
campo:

```php
$user = $usersTable->GetRecord(array('username' => $username));
if (!$user || $user['username'] !== $username) {
    $user = null; // on SQL drivers "a_b" also matches "axb"
}
```

### Conteggio Record

```php
// Count all records
$totalUsers = $usersTable->GetNumRecords();

// Count with a filter
$totalActiveUsers = $usersTable->GetNumRecords(array('active' => 1));
```

### Query in Stile SQL

`FN_XMETADBQuery($query, $params = array())` esegue una query sulle tabelle del sito con il
motore `XMETADatabase`. Il motore legge i record tramite XMETATable e li filtra e ordina in PHP:
la stessa query funziona con ogni driver, ma legge sempre l'intera tabella. Per usare la classe
direttamente, costruirla come fa `FN_XMETADBQuery()`, altrimenti le tabelle MySQL non ricevono
i parametri di connessione:

```php
$DB = new XMETADatabase($_FN['database'], $_FN['datadir'], FN_setCommonParams());
$rows = $DB->Query("SELECT name, city FROM app_people WHERE age > 17 ORDER BY name");
```

| Istruzione | Note |
|---|---|
| `SELECT campi FROM tabella` | `*`, un elenco di campi, `campo AS alias`, `DISTINCT`; `COUNT(*)` restituisce una riga con la chiave `COUNT(*)`, `COUNT(*) AS n` con la chiave `n` |
| `WHERE` | vedi sotto |
| `ORDER BY campo [DESC]` | un campo; con più campi l'ultimo è la chiave principale |
| `LIMIT inizio,quanti` | `quanti` record dalla posizione `inizio`, contata da 1 (`LIMIT 1,10` = i primi dieci); `LIMIT n` e `LIMIT 0,n` non limitano il risultato |
| `DESCRIBE tabella` | una riga per campo: `Field`, `Type`, `Null`, `Key`, `Extra` |
| `SHOW TABLES` | una riga per tabella, chiave `Tables_in_<database>` |
| `INSERT INTO t (f1, f2) VALUES ('a', 'b')` | restituisce il record inserito; i valori tra apici possono contenere virgole, `''` è un apice |
| `UPDATE t SET f1 = 'a', f2 = 'b' WHERE ...` | valori senza virgole né `=`; restituisce null |
| `DELETE FROM t WHERE ...` | restituisce `""` |

Condizioni WHERE:

- `campo operatore valore`, con l'operatore tra spazi: `=`, `<>`, `<`, `<=`, `>`, `>=`,
  `LIKE`; le condizioni si combinano con `AND`, `OR` e parentesi. `!=`, `NOT`, `IN`,
  `IS NULL` e `BETWEEN` non sono supportati, e `name='x'` (senza spazi) viene rifiutato.
- `=` e `LIKE` senza `%` trovano il valore esatto (distinguendo le maiuscole). `LIKE 'abc%'`,
  `'%abc'` e `'%abc%'` trovano l'inizio, la fine o una parte qualsiasi del valore ignorando le
  maiuscole; `<> '%abc%'` trova i valori che non contengono `abc`.
- Gli interi senza apici vengono confrontati come numeri (`age > 9`); un numero con uno zero
  iniziale va messo tra apici.

Una tabella sconosciuta restituisce una stringa che inizia con `xmetadb:`; un campo sconosciuto
o una condizione rifiutata danno un warning PHP e false.

**Sicurezza**: la WHERE viene tradotta in un'espressione PHP. I caratteri `` ` ``, `$`, `{` e
`}` vengono rimossi, e l'espressione viene valutata solo se contiene esclusivamente campi,
operatori di confronto e logici, numeri e stringhe tra apici; tutto il resto viene rifiutato.
Un apice in un valore cambia comunque le condizioni: filtrare i valori della richiesta con i
metodi di XMETATable.

### Gestione Files e Immagini

I campi con widget `file` o `image` tengono nel record il nome del file. XMETATable salva il
file in `{datadir}/{database}/{tabella}/{chiave primaria}/{campo}/{nome file}` quando un record
viene inserito o aggiornato con un upload che ha il nome del campo (`$_FILES['avatar']`) o con
un file passato con `SetFile()`. Mettere la chiave primaria nei valori: il driver `mysql`
gestisce i file solo se è presente.

```php
// In an HTML form:
<form method="post" enctype="multipart/form-data">
    <input type="file" name="avatar">
    <!-- other fields -->
    <button type="submit">Save</button>
</form>

// In the PHP code that saves:
if (!empty($_FILES['avatar']['tmp_name'])) {
    $userData = array('id' => $userId, 'avatar' => $_FILES['avatar']['name']);
    $usersTable->UpdateRecordBypk($userData, 'id', $userId);
    // The upload is handled automatically by XMETATable
}

// A file already on the server
$usersTable->SetFile('avatar', '/tmp/photo.jpg', 'photo.jpg');
$usersTable->UpdateRecordBypk(array('id' => $userId, 'avatar' => 'photo.jpg'), 'id', $userId);

// Path of the image:
$user = $usersTable->GetRecordByPrimaryKey($userId);
$avatarPath = $usersTable->getFilePath($user, 'avatar');

// URL of the file and of its thumbnail:
$fileUrl = $usersTable->getFileUrl($user, 'avatar');
$thumbUrl = $usersTable->get_thumb($user, 'avatar');
```

- `getFilePath($record, $campo)`: percorso del file nella cartella dei dati, false se il campo
  è vuoto. Usarlo invece di costruire il percorso: quando una cartella contiene 10000 record i
  successivi vanno in `{tabella}.1`, `{tabella}.2`, ...
- `getThumbPath($record, $campo)`: la miniatura, oppure il file stesso se la miniatura non c'è.
- `getFileUrl($record, $campo, $thumb = false)`: indirizzo web del file (`$_FN['siteurl']`
  seguito dal percorso), false se il file non esiste. `get_file()` e `get_thumb()` sono
  scorciatoie per il file e la miniatura.
- La miniatura `thumbs/{nome file}.jpg` viene creata all'upload quando il `type` del campo è
  `image`, con la dimensione `thumbsize` (default 22, minimo 16) oppure `thumbsize_w` /
  `thumbsize_h`.
- Gli upload vengono rifiutati quando il nome del file contiene un'estensione eseguibile seguita
  da un punto o dalla fine del nome: `.php`, `.php0` ... `.php9`, `.phtml`, `.phar`, `.shtml`,
  `.pl`, `.cgi`, `.sh`, `.py` (quindi anche `foto.php.jpg`). Il file non viene salvato. `/` e `\`
  vengono tolti dai nomi.
- I nomi di record e campi che arrivano al file system vengono controllati: `DelRecord()`
  elimina una cartella solo se il suo percorso reale è esattamente `{tabella}/{chiave primaria}`.

#### Miniature

`index.php?fnapp=thumb` restituisce un PNG (o un JPEG con `format=jpg`) di esattamente `w` x `h`
pixel (default 32 x 32, un solo valore dà un quadrato) con l'immagine ridimensionata e
centrata:

```text
index.php?fnapp=thumb&t=app_users&i=5&c=avatar&w=200&h=200
index.php?fnapp=thumb&f=images/logo.png&w=64
```

- Per record: `t` tabella, `i` chiave primaria, `c` campo, `d` cartella del database (default
  `fndatabase`). Le tabelle del database del sito vengono lette con `FN_XMDBTable()`, quindi con
  il loro driver; viene ridimensionato il file originale, non la copia in `thumbs/`.
- Per percorso: `f` percorso di un file immagine.
- Un file mancante o che non è un'immagine dà l'icona generica `images/mime/image.png` (la copia
  del sito o quella di FINIS). L'orientamento EXIF viene applicato ai file JPEG e TIFF.

#### File Protetti

Un'applicazione che deve controllare chi legge i file dei propri record imposta due hook, per
esempio in un file caricato a ogni richiesta (`extra/protected_files.inc.php`
dell'applicazione):

```php
global $_FN;
// who may read a file: checked by fnapp=thumb for the thumbnails asked by record
$_FN['xmetadb_file_access'] = function ($tablename, $id, $field) {
    return MyApp_CanReadRecord($tablename, $id); // true: the thumbnail is served
};
// web address of a file: returned by getFileUrl(), get_file() and get_thumb()
$_FN['xmetadb_file_url'] = function ($table, $record, $field, $thumb) {
    global $_FN;
    return $_FN['siteurl'] . "getfile.php?t=" . rawurlencode($table->tablename)
        . "&id=" . rawurlencode($record[$table->primarykey])
        . "&f=" . rawurlencode($field) . ($thumb ? "&thumb=1" : "");
};
```

- `$_FN['xmetadb_file_access']`: callable `($tablename, $id, $field)` che restituisce true se il
  visitatore corrente può leggere il file. Quando è impostato, `fnapp=thumb` serve una miniatura
  per record solo se il callback lo permette, solo dal database del sito e per nomi di tabella e
  di campo fatti di lettere, cifre e `_` (altrimenti 403), e rifiuta un percorso `f` dentro la
  cartella dei dati. dbview allora chiede per record le miniature delle sue liste.
- `$_FN['xmetadb_file_url']`: callable `($table, $record, $field, $thumb)` (`$table` è l'oggetto
  XMETATable) che restituisce l'indirizzo da usare al posto del percorso nella cartella dei dati,
  per esempio uno script dell'applicazione che controlla l'accesso e invia il file. La cartella
  dei dati può allora essere chiusa alle richieste dirette.

## Configurazione Database

### Configurazione MySQL

Per configurare una connessione MySQL, modifica il file `config.vars.local.php`:

```php
// MySQL driver configuration
$_FN['default_database_driver'] = 'mysql';
$_FN['xmetadb_mysqlhost'] = 'localhost';
$_FN['xmetadb_mysqldatabase'] = 'finis';
$_FN['xmetadb_mysqlusername'] = 'username';
$_FN['xmetadb_mysqlpassword'] = 'password';
$_FN['xmetadb_timezone'] = '+00:00'; // optional: time_zone of the connection
```

Come funziona il driver `mysql`:

- Una connessione per richiesta, condivisa da tutte le tabelle MySQL. Una volta per
  connessione esegue `SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'` e, se
  `$_FN['xmetadb_timezone']` è impostato, `SET time_zone`.
- Una tabella mancante viene creata dal descrittore con `DEFAULT CHARACTER SET utf8 COLLATE
  utf8_bin` (i confronti di testo distinguono le maiuscole), e i record della sua cartella dati
  `xmlphp`, se ci sono, vengono copiati. Un campo aggiunto al descrittore diventa una nuova
  colonna; le colonne esistenti non vengono mai modificate. L'elenco delle tabelle e il
  `DESCRIBE` di ogni tabella vengono letti una volta per richiesta.
- I database creati da versioni precedenti possono avere colonne `NOT NULL` senza default: un
  inserimento che le tralascia scrive il valore che MySQL non strict salvava (0, la data zero,
  il primo valore di un ENUM, `''`), e un valore vuoto in una di queste colonne viene scritto
  allo stesso modo. Lo schema non viene modificato.
- Un server che rifiuta la connessione perché occupato (errori 1040, 1203, o 1226 per
  `max_user_connections`) viene interrogato di nuovo per circa 5 secondi. Se la connessione
  fallisce ancora, o il server non è raggiungibile (1226 per altri limiti, 2002, 2003, 2006,
  2013), una richiesta web termina con una pagina HTTP 503 (`Retry-After: 10`) che si ricarica
  da sola dopo 10 secondi per le richieste GET; da riga di comando il driver restituisce false.
  L'errore viene registrato con `error_log()` e non viene ritentato nella stessa richiesta.
- `GetRecordByPk()` restituisce false quando la query fallisce o non c'è connessione, null
  quando nessun record ha quella chiave.

### Configurazione SQLite

Per utilizzare SQLite come database:

```php
// SQLite driver configuration
$_FN['default_database_driver'] = 'sqlite3';
```

Il file del database è `{datadir}/{database}.sqlite3` (`misc/fndatabase.sqlite3`); una tabella
può usare un altro file con `<sqlite3filename>` nel suo descrittore.

### Configurazione a Livello di Tabella

Il descrittore può scegliere il driver di una singola tabella e il nome della sua tabella SQL:

```xml
<tables>
  <!-- field definitions -->
  <driver>mysql</driver>
  <sqltable>table_name</sqltable>
</tables>
```

Il driver `mysql` legge anche `<host>`, `<user>`, `<password>`, `<database>` e `<port>`, ma in
FINIS le impostazioni `$_FN['xmetadb_mysql*']` li sostituiscono quando host, database e
username sono impostati (`config.vars.php` li imposta per default), e ogni tabella MySQL usa la
connessione già aperta nella richiesta.

## Migrazione Tra Database

### Da XML/PHP a MySQL

Per migrare una tabella da XML/PHP a MySQL:

```php
require_once $_FN['src_finis'] . "/include/xmetadb/XMETATable_mysql.php";

$connection = array(
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'username',
    'password' => 'password',
    'database' => 'my_database',
    'sqltable' => 'app_users'
);

// xml_to_sql() is in the Xmetadb namespace
\Xmetadb\xml_to_sql($_FN['database'], 'app_users', $_FN['datadir'], $connection);
```

`xml_to_sql()` aggiunge al descrittore `<driver>mysql</driver>` e i dati di connessione
(password compresa) e apre la tabella: la tabella MySQL viene creata e i record vengono
copiati. Con `$_FN['default_database_driver'] = 'mysql'` succede lo stesso a ogni tabella
senza `<driver>` la prima volta che viene aperta.

## Schemi e Definizioni Campi

### Tipi dei campi: `type` e `frm_type`

- `type` è il **tipo della colonna SQL**, usato quando si crea la tabella o si aggiunge una colonna.
- `frm_type` è il **widget del form**. Se manca, il widget si ricava da `type`.

Tipi SQL (`type`):

| `type` | colonna | valore vuoto |
|---|---|---|
| `varchar` | `VARCHAR(size)`, size di default 255 | `''` (NOT NULL) |
| `char` | `CHAR(size)`, size di default 1 | `''` (NOT NULL) |
| `text`, `html`, `mediumtext`, `longtext` | `TEXT`, `TEXT`, `MEDIUMTEXT`, `LONGTEXT` | `NULL` |
| `int`, `number`, `tinyint`, `smallint`, `bigint` | tipi interi (`number` = `INT`) | `NULL` |
| `float`, `double` | `FLOAT`, `DOUBLE` | `NULL` |
| `decimal` | `DECIMAL(size)`, size `"precisione,scala"`, default `10,2` | `NULL` |
| `date`, `datetime`, `time` | `DATE`, `DATETIME`, `TIME` | `NULL` |
| `json` | `JSON` | |
| qualunque altro valore (`string`, `check`, `image`, ... nomi di widget storici) | `VARCHAR(255)` | `''` (NOT NULL) |

Le colonne numeriche e le date ammettono `NULL`: un campo vuoto del form viene salvato come
`NULL` e riletto come `""`, mai come `0`. finis non modifica mai una colonna esistente:
cambiare `type` in un descrittore vale solo per le tabelle e le colonne nuove.

Widget (`frm_type`), ognuno una classe `xmetadbfrm_field_<nome>` in
`src/include/xmetadbfrm_fields/`: `varchar`, `varchar_uppercase`, `varchar_lowercase`, `text`,
`html`, `bbcode`, `check`, `radio`, `select`, `multiselect`, `multicheck`, `stringselect`, `int`,
`number`, `float`, `datetime`, `password`, `cryptpasswd`, `file`, `image`, `base64file`,
`localfile`, `json`, `fn_cron_text`, `separator` (`md5passwd` e `md5password` sono storici).
Un nome sconosciuto ricade su `varchar`; un campo con `foreignkey` e senza widget (o con
`varchar`) usa `select`. I tipi SQL senza un widget proprio usano il più vicino
(`mediumtext` → `text`, `double` → `varchar`, `date` → `varchar`, ...).

```xml
<field>
  <name>elevation</name>
  <type>double</type>          <!-- tipo SQL -->
  <frm_type>varchar</frm_type> <!-- widget del form -->
</field>
<field>
  <name>photo</name>
  <type>varchar</type>
  <frm_type>image</frm_type>
</field>
```

### Widget dei Form

- `int`: casella di testo che accetta solo cifre e `-`.
- `number`: `<input type="number">` con le proprietà del campo `min` e `max`.
- `float`: `<input type="number">` con `min`, `max` e `step` (default `0.1`); la vista del
  record mostra il valore con `decimals` decimali (default 2).
- `json`: area di testo (`frm_rows`, default 4; `frm_cols`); la vista del record mostra il JSON
  decodificato come elenco annidato, oppure il testo così com'è se non è JSON valido.
- `fn_cron_text`: editor di pianificazioni, una per riga, `A-M-G h:m:s giornosettimana` con `*`
  e `*/n` (`*-*-* 13:30:00 5`, giorno 0 = domenica) e un `# commento` facoltativo; la vista del
  record le descrive a parole. Usato dal campo `cron_lines` di `fn_cron`.
- `datetime`: calendario. `frm_dateformat` (default `y-mm-dd 00:00:00`: `y` anno, `mm` mese,
  `dd` giorno, `00:00:00` o `00:00` ora) è il formato del form, `view_dateformat` quello della
  vista del record; il valore viene salvato come `Y-m-d H:i:s`. Al salvataggio, una data che non
  segue il formato o che non esiste (`2020-02-31`) viene rifiutata con "la data non è valida,
  usa il formato YYYY-MM-DD hh:mm:ss" (l'ora si può omettere). Viene accettato anche un valore
  già nel formato di archiviazione (`Y-m-d`, eventualmente con `H:i` o `H:i:s`, come lo scrivono
  i client API), e la data zero viene salvata come valore vuoto. Un valore salvato che non è una
  data completa (un vecchio `1993`) viene mostrato così com'è, quindi salvando il form viene
  chiesta una data valida.

### Proprietà dei Campi

```xml
<field>
  <name>campo_nome</name>          <!-- nome del campo -->
  <type>varchar</type>             <!-- tipo della colonna SQL -->
  <size>100</size>                 <!-- dimensione (per varchar) -->
  <primarykey>1</primarykey>       <!-- indica chiave primaria -->
  <extra>autoincrement</extra>     <!-- campo auto-incrementante -->
  <thumbsize>100</thumbsize>       <!-- dimensione miniatura per immagini -->
  <defaultvalue>0</defaultvalue>   <!-- valore predefinito letterale (driver su file) -->
  <frm_default>0</frm_default>     <!-- valore proposto dal form per un nuovo record -->
  <mysql_default>NULL</mysql_default> <!-- valore predefinito (MySQL) -->
  <mysql_on_update>CURRENT_TIMESTAMP</mysql_on_update> <!-- aggiornamento automatico -->
</field>
```

- `defaultvalue` è un valore letterale: le espressioni PHP non vengono valutate (`date('Y')`
  viene salvato come testo). I driver `xmlphp`, `csv` e `serialize` lo scrivono quando un record
  viene inserito senza il campo e lo restituiscono per i record salvati che non hanno il campo.
  Su MySQL il default della colonna viene da `mysql_default` (un'espressione SQL usata in
  `CREATE TABLE` / `ALTER TABLE`).
- `frm_sort`: `numeric` o `abs` fa ordinare a dbview la lista per valore numerico (o valore
  assoluto) di una colonna di testo: valori vuoti in fondo, virgola accettata come separatore
  decimale. Vale per le tabelle con un driver SQL.
- `frm_functionview`: nome di una funzione PHP chiamata come `function($values, $fieldname)`
  nella vista del record; l'HTML che restituisce viene mostrato con il titolo del campo, e
  omesso se è vuoto o se il campo è nascosto.

Chiave esterna:

```xml
<field>
  <name>category_id</name>
  <foreignkey>app_categories</foreignkey>   <!-- tabella collegata -->
  <fk_link_field>id</fk_link_field>         <!-- valore salvato nel campo -->
  <fk_show_field>code,name</fk_show_field>  <!-- campi mostrati nelle opzioni -->
  <fk_filter_field>active='1'</fk_filter_field>
</field>
```

- `fk_show_field`: campi separati da virgola, uniti da uno spazio; si usa `{campo}_{lingua}`
  quando non è vuoto.
- `fk_filter_field`: `campo='valore'` (una costante) o `campo=altrocampo` (il valore di un altro
  campo del record), separati da virgola.
- `frm_show_image`: campo immagine della tabella collegata mostrato accanto alle opzioni.
- `fk_databasename`, `fk_databasepath`: la tabella collegata è in un'altra cartella di database.

Le opzioni leggono solo le colonne con cui vengono costruite (chiave primaria, campo di
collegamento, campi mostrati con le loro varianti di lingua, campo immagine).

## Ottimizzazione delle Prestazioni

### Uso della Cache

I driver `xmlphp`, `csv` e `serialize` possono tenere in file i risultati di `GetRecords()`
(con `xmlphp` anche di `GetRecordByPrimaryKey()`) (`{datadir}/{database}/cache/`):

```xml
<tables>
  <!-- field definitions -->
  <usecachefile>1</usecachefile>
</tables>
```

### Operazioni Rapide

Il driver `mysql` ha un inserimento e un aggiornamento senza gestione dei file, raggiungibili
tramite l'oggetto del driver:

```php
// Fast insert (without file management), returns true or false
$usersTable->driverclass->InsertRecordFast($userData);

// Fast update by the primary key in $userData (without file management)
$usersTable->driverclass->UpdateRecordFast($userData);
```

## Estensione del Sistema

### Creazione di un Driver Personalizzato

Per creare un nuovo driver per XMETATable:

1. Crea la classe `XMETATable_miodriver` (nome globale) in `src/include/xmetadb/XMETATable_miodriver.php`, oppure definiscila nell'applicazione prima che la tabella venga aperta
2. Implementa i metodi che XMETATable delega al driver: `GetRecords`, `GetRecord`, `GetRecordByPrimaryKey`, `GetRecordByPk`, `GetNumRecords`, `GetAutoincrement`, `InsertRecord`, `UpdateRecordBypk`, `DelRecord`, `GetFileRecord`, `Truncate`
3. Registra il driver nel descrittore XML della tabella (`<driver>miodriver</driver>`)

Esempio di struttura di base:

```php
<?php
class XMETATable_miodriver extends stdClass
{
    function __construct(&$xmltable, $params = false)
    {
        // Initialization
    }

    function GetRecords($restr = false, $min = false, $length = false, $order = false, $reverse = false, $fields = array())
    {
        // Implementation
    }

    function InsertRecord($values)
    {
        // Implementation
    }

    // Other required methods
}
```

### Widget Personalizzati

Un widget è la classe `xmetadbfrm_field_<frm_type>`. I form usano la classe se è già definita
(per esempio in un file `extra/*.inc.php` dell'applicazione) oppure la caricano da
`src/include/xmetadbfrm_fields/`. Il costruttore riceve `array('fieldvalues' => <campo>)`; i
metodi ricevono le proprietà del campo con `name` e `value`.

```php
class xmetadbfrm_field_percent
{
    function __construct($field)
    {
    }

    // input of the form
    function show($params)
    {
        return "<input type=\"number\" min=\"0\" max=\"100\" name=\"{$params['name']}\" value=\""
            . htmlspecialchars($params['value']) . "\" /> %";
    }

    // value in the record view
    function view($params)
    {
        return htmlspecialchars($params['value']) . " %";
    }

    // optional: error shown by the form, "" when the value is valid
    function checkformvalue($value, $params)
    {
        return ($value >= 0 && $value <= 100) ? "" : "0 - 100";
    }
}
```

Altri metodi facoltativi: `formtovalue($value, $params)` (valore inviato → valore salvato),
`htmlfilter($params)` (input del filtro nell'editor delle tabelle), `SortNumeric()` (true,
`"numeric"` o `"abs"`: come `frm_sort`).

## Best Practices

1. **Chiavi Primarie**: Definisci sempre una chiave primaria per ogni tabella
2. **Corrispondenze Esatte**: Nei driver SQL i filtri sono pattern `LIKE`: confronta di nuovo il campo quando un valore deve trovare un solo record
3. **Campi Null**: Gestisci correttamente i valori null per ogni driver
4. **Sicurezza**: Usa sempre `FN_GetParam()` per ottenere i dati utente prima di inserirli, e `intval()` per gli interi
5. **Backup**: Crea backup regolari dei dati, specialmente quando usi il driver xmlphp

## Risoluzione Problemi

### Problemi Comuni

- **Errore "Table not exists"**: Verifica che il file descrittore della tabella esista nel percorso corretto.
- **Errore "File not writable"**: Controlla i permessi di scrittura della directory.
- **Errore "Database not writable"**: Verifica che l'utente del database abbia i permessi corretti.
- **Errore di connessione MySQL**: Controlla le credenziali e che il server sia in esecuzione.
- **HTTP 503 "Servizio momentaneamente non disponibile"**: il server MySQL era occupato o non
  raggiungibile per circa 5 secondi (vedi Configurazione MySQL).
- **File caricato mancante**: il nome del file contiene un'estensione eseguibile.

### Debug

Per registrare le operazioni di scrittura sulle tabelle:

```php
define("XMETADB_DEBUG_FILE_LOG", "/path/to/logfile.log");
```

Ogni `InsertRecord()`, `UpdateRecordBypk()` e `DelRecord()` scrive l'ora e il nome della
tabella (e i valori per `fn_settings`). Gli errori MySQL vengono segnalati come warning PHP
(`MySQL INSERT failed: ...`), quelli di connessione con `error_log()`.

## Esempi Pratici

### Esempio 1: Sistema di Gestione Utenti

```php
// Get the users table
$usersTable = FN_XMDBTable("app_users");

// User registration
function registerUser($username, $email, $password) {
    global $usersTable;

    // Check whether the user already exists
    $existingUser = $usersTable->GetRecord(array('username' => $username));
    if ($existingUser && $existingUser['username'] === $username) {
        return array('error' => 'Username già in uso');
    }

    // Register the new user
    $userData = array(
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'regdate' => FN_Now(),
        'active' => 1,
        'group' => 'users'
    );

    $newUser = $usersTable->InsertRecord($userData);
    return is_array($newUser) ? array('success' => true, 'user' => $newUser) : array('error' => 'Errore di registrazione');
}

// User login
function loginUser($username, $password) {
    global $usersTable;

    $user = $usersTable->GetRecord(array(
        'username' => $username,
        'active' => 1
    ));

    if (!$user || $user['username'] !== $username || !password_verify($password, $user['password'])) {
        return array('error' => 'Credenziali non valide');
    }

    // Update the last access
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
// Get the posts table
$postsTable = FN_XMDBTable("app_posts");

// Create a new post
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

// Get the recent posts
function getRecentPosts($limit = 10) {
    global $postsTable;

    $posts = $postsTable->GetRecords(
        array('status' => 'published'),
        false,
        false,
        'date_created',
        true
    );
    // the same on every driver (the meaning of $min differs, see Pagination)
    return is_array($posts) ? array_slice($posts, 0, $limit) : array();
}

// Get the posts of a category
function getPostsByCategory($categoryId) {
    global $postsTable;

    return $postsTable->GetRecords(array(
        'category_id' => $categoryId,
        'status' => 'published'
    ));
}
```

## Riferimento API Completo

Per un elenco completo delle funzioni e dei metodi disponibili, consultare la documentazione API nella sezione "Classe XMETATable" della [Documentazione API di FINIS Framework](it_documentazione_api.md#classe-xmetatable).
