# Manuale di Installazione FINIS Framework

## Introduzione
FINIS (Flatnux Is Now Infinitely Scalable) è un framework PHP e CMS progettato per essere flessibile e adattabile. Questo manuale guiderà l'utente attraverso il processo di installazione e la configurazione iniziale del sistema.

## Prerequisiti
- Server web (Apache, Nginx, ecc.)
- PHP 7.0 o superiore
- Supporto per uno dei database: 
  - File system (predefinito)
  - MySQL
  - SQLite
  - SQL Server
  - Altri database supportati tramite driver specifici

## Download
1. Scaricare l'ultima versione dal repository ufficiale
2. Decomprimere l'archivio in una cartella temporanea

## Metodi di Installazione

### Installazione Standard (CMS completo)
1. Caricare tutti i file nella cartella principale del sito web
2. Copiare il file `src/config.vars.local.php.mysql.example` in `src/config.vars.local.php` 
3. Modificare il file di configurazione con i dati di accesso al database
4. Accedere all'URL del sito (es. `http://tuosito.com/`)
5. Seguire la procedura guidata di installazione che apparirà automaticamente

### Installazione con Sorgenti Separati
FINIS permette di separare i file sorgenti del framework dai file dell'applicazione:

1. Creare due cartelle: `finis_src/` per i sorgenti del framework e `website/` per il sito
2. Copiare tutti i file del framework nella cartella `finis_src/`
3. Creare un file `index.php` nella cartella `website/` con il seguente contenuto:
   ```php
   <?php
   require_once "../finis_src/FINIS.php";
   $FINIS = new FINIS(array("src_application"=> "."));
   $FINIS->finis();
   ```
4. Configurare il server web per utilizzare `website/` come cartella principale

## Configurazione

### File di Configurazione Principale
Il file `config.vars.local.php` contiene le impostazioni principali:

```php
<?php
global $_FN;
// Visualizzazione errori
$_FN['display_errors'] = "on"; // impostare su "off" in produzione

// Metodo di autenticazione (in include/auth/)
$_FN['default_auth_method'] = "local";

// Configurazione per MySQL:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "nome_database";
$_FN['xmetadb_mysqlusername'] = "username";
$_FN['xmetadb_mysqlpassword'] = "password";

// Altre impostazioni generali
$_FN['sitename'] = "Il mio sito FINIS";
$_FN['site_email_address'] = "admin@miosito.com";
```

### Utilizzo di File System come Database (predefinito)
Se non si ha accesso a un database MySQL, FINIS può funzionare utilizzando file PHP/XML nella cartella `misc/fndatabase/`:

```php
<?php
global $_FN;
$_FN['display_errors'] = "on";
$_FN['default_auth_method'] = "local";
$_FN['default_database_driver'] = "xmlphp"; // Utilizza file PHP/XML
```

## Primo Accesso
1. Dopo l'installazione, accedere all'URL del sito
2. Completare la configurazione guidata, impostando:
   - Nome del sito
   - Email amministratore
   - Username e password amministratore
   - Lingua predefinita
3. Accedere al pannello di amministrazione all'URL: `http://tuosito.com/?fnapp=controlcenter`

## Struttura dei File e Cartelle
- `src/`: Contiene i file sorgenti del framework
- `sections/`: Contiene le pagine del sito (una cartella per ogni sezione)
- `themes/`: Contiene i temi grafici
- `modules/`: Contiene i vari tipi di sezioni disponibili
- `misc/`: Contiene i dati, inclusi i file del database
- `languages/`: Contiene le traduzioni

## Risoluzione Problemi
- **Schermata bianca**: Verificare che il file `config.vars.local.php` sia configurato correttamente
- **Errori di permessi**: Assicurarsi che le cartelle `misc/` e `misc/fndatabase/` abbiano permessi di scrittura (CHMOD 755 o 777)
- **Database non accessibile**: Controllare i parametri di connessione nel file di configurazione

## Risorse Aggiuntive
- Documentazione: Consultare i file in `doc/`
- Esempi di utilizzo: Vedere i file in `examples/`

## Sviluppo e Personalizzazione
Dopo l'installazione, potrai:
1. Creare nuove sezioni nella cartella `sections/`
2. Personalizzare l'aspetto modificando i file nella cartella `themes/`
3. Estendere le funzionalità con nuovi moduli nella cartella `modules/`