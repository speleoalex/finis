# Manuale di Installazione FINIS Framework

## Introduzione
FINIS (Flatnux Is Now Infinitely Scalable) è un framework PHP e CMS progettato per essere flessibile e adattabile. Questo manuale guiderà l'utente attraverso il processo di installazione e la configurazione iniziale del sistema.

## Prerequisiti
- Server web (Apache con mod_rewrite per gli URL leggibili e per il login OAuth dei client MCP; qualsiasi server per gli indirizzi `index.php?...`)
- PHP 7.1 o superiore (PHP 8.x è supportato)
- Supporto per uno dei database: 
  - File system (predefinito, non serve un server di database)
  - MySQL / MariaDB
  - SQLite 3
  - SQL Server
  - Altri database supportati tramite driver specifici (`csv`, `serialize`)
- Permessi di scrittura per il server web sulla cartella dei dati (`misc/`) e sulla cartella `sections/` del sito

### Estensioni PHP
FINIS funziona con le estensioni di una normale installazione di PHP. Le seguenti servono solo ad alcune funzioni:

| Estensione | Serve per |
|---|---|
| `mysqli` | driver di database `mysql` |
| `sqlite3` | driver di database `sqlite3` |
| `sqlsrv` | driver di database `sqlserver` |
| `gd` (con FreeType) | miniature, campi immagine, anteprime del file manager, immagine del captcha |
| `mbstring` | risultati della ricerca nelle viste di database, registrazione dei client del server OAuth |
| `curl` | login con provider OAuth esterni |
| `openssl` (AES-256-GCM) | copia cifrata delle chiavi API, che gli amministratori possono così mostrare; senza, la chiave è salvata solo come hash e si vede una volta sola |
| `exif` | orientamento delle foto JPEG nelle miniature |
| `zlib` | compressione gzip delle pagine (impostazione `enable_compress_gzip`) |
| `apcu` | cache più veloce; senza, la cache resta in file in `misc/_cache/` |

## Download
1. Scaricare l'ultima versione dal repository ufficiale
2. Decomprimere l'archivio in una cartella temporanea

## Metodi di Installazione

### Installazione Standard (CMS completo)
1. Caricare il contenuto della cartella `src/` nella cartella principale del sito web
2. Rinominare `index.php.example` in `index.php`
3. Per usare MySQL invece dei file, copiare `config.vars.local.php.mysql.example` in `config.vars.local.php` e inserire i dati di accesso al database
4. Rendere scrivibile dal server web la cartella del sito (FINIS vi crea la cartella dei dati `misc/`) e la cartella `sections/`
5. Accedere all'URL del sito (es. `http://tuosito.com/`)
6. Seguire la procedura guidata di installazione (`index.php?fnapp=install`), che apparirà automaticamente: parte quando la cartella dei dati non esiste ancora (se si crea `misc/` a mano, creare anche un file vuoto `misc/firstinstall`)

### Installazione con Sorgenti Separati
FINIS permette di separare i file sorgenti del framework dai file dell'applicazione:

1. Creare due cartelle: `finis_src/` per i sorgenti del framework e `website/` per il sito
2. Copiare il contenuto di `src/` nella cartella `finis_src/`
3. Creare un file `index.php` nella cartella `website/` con il seguente contenuto:
   ```php
   <?php
   require_once "../finis_src/FINIS.php";
   $FINIS = new FINIS(array("src_application" => "."));
   $FINIS->finis();
   ```
4. Mettere `config.vars.local.php` (quando serve) nella cartella `website/`
5. Configurare il server web per utilizzare `website/` come cartella principale

La cartella dell'applicazione contiene i propri `sections/`, `themes/`, `modules/`, `misc/` (dati) e `config.vars.local.php`; quello che manca viene preso dai sorgenti del framework. Alla prima visita l'installatore copia le sezioni predefinite di FINIS in `website/sections/`. `$FINIS->finis()` serve il sito e gli altri punti di ingresso scelti con `?fnapp=` (`controlcenter`, `api`, `mcp`, `oauthserver`, `thumb`, ...). Più siti possono usare la stessa copia dei sorgenti del framework.

### Estensioni
Un'estensione è una cartella con moduli, sezioni, sezioni del pannello di controllo e tabelle aggiuntive, caricata senza copiarla nel sito:

```php
<?php
require_once "../finis_src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("../fncommerce/");
$FINIS->finis();
```

La cartella di un'estensione può contenere `modules/`, `sections/`, `controlcenter/sections/` e `misc/fndatabase/`. I moduli (tipi di sezione) e le sezioni del pannello di controllo vengono cercati prima nell'applicazione, poi nei sorgenti di FINIS, poi nelle estensioni (nell'ordine delle chiamate ad `addExtension()`). I moduli e le sezioni del pannello di controllo restano nella cartella dell'estensione; l'installatore copia nel sito le `sections/` e i descrittori di tabella di `misc/fndatabase/` delle estensioni, solo quelli che il sito non ha già. Se si aggiunge un'estensione a un sito già installato, vanno copiati a mano. Un percorso inesistente ferma la richiesta con il messaggio `<percorso> not exists`.

### Esempi
La cartella `examples/` contiene siti pronti che usano i sorgenti in `../../src/` e girano dall'interno del repository:

- `examples/website`: un sito con le sezioni predefinite; l'installatore parte alla prima visita
- `examples/fullcms`: il CMS completo con il pannello di controllo
- `examples/ecommerce`: un negozio (carrello, categorie, prodotti) basato sull'estensione `fncommerce`, caricata con `$FINIS->addExtension("../../fncommerce/")`; i dati del catalogo sono già nella sua cartella `misc/`

## Configurazione

### File di Configurazione Principale
Il file `config.vars.local.php` del sito contiene le impostazioni dell'installazione. I valori predefiniti sono in `src/include/config.vars.php`: una chiave impostata lì (come `display_errors`) va cambiata in `config.vars.local.php`, un valore passato a `new FINIS(array(...))` verrebbe sovrascritto.

```php
<?php
global $_FN;
// Visualizzazione errori
$_FN['display_errors'] = "off"; // "on" solo durante lo sviluppo

// Metodo di autenticazione (in include/auth/)
$_FN['default_auth_method'] = "local";

// Dietro un reverse proxy: i suoi indirizzi IP, separati da virgola, perché il limite
// dei tentativi di login legga l'indirizzo del client da X-Forwarded-For ("" = nessun proxy)
$_FN['trusted_proxies'] = "";

// Sezione iniziale che prevale su quella scelta nel pannello di controllo ("" = usa l'impostazione)
$_FN['home_section_force'] = "";

// Configurazione per MySQL:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "nome_database";
$_FN['xmetadb_mysqlusername'] = "username";
$_FN['xmetadb_mysqlpassword'] = "password";
```

Le impostazioni del sito (nome, indirizzo email, lingue, tema, sezione iniziale, mod_rewrite, registrazione, cache, ...) non si mettono in questo file: si scelgono nella procedura di installazione e nel pannello di controllo e sono salvate nella tabella `fn_settings` (`src/config.php` contiene solo i valori predefiniti).

### Utilizzo di File System come Database (predefinito)
Se non si ha accesso a un database MySQL, FINIS può funzionare utilizzando file PHP/XML nella cartella `misc/fndatabase/`:

```php
<?php
global $_FN;
$_FN['display_errors'] = "off";
$_FN['default_auth_method'] = "local";
$_FN['default_database_driver'] = "xmlphp"; // Utilizza file PHP/XML
```

### Sicurezza del Login
Queste chiavi si possono impostare in `config.vars.local.php`; se mancano valgono i valori predefiniti:

| Chiave | Predefinito | Significato |
|---|---|---|
| `remember_login_days` | 30 | durata dei cookie di login quando l'utente sceglie "ricordami" (impostazione `remember_login` attiva) |
| `recovery_link_minutes` | 60 | validità di un link di recupero password |
| `login_max_attempts` | 5 | tentativi di login falliti ammessi per nome utente e indirizzo IP |
| `login_lockout_minutes` | 15 | finestra di tempo dei tentativi e durata massima del blocco |
| `trusted_proxies` | "" | reverse proxy di cui si accetta l'intestazione `X-Forwarded-For`: indirizzi IP separati da virgola (niente intervalli) |
| `username_case_sensitive` | non impostata | se impostata, i nomi utente sono salvati e cercati come scritti; altrimenti sono in minuscolo |

Il cookie di login è firmato con il segreto del sito `misc/secret.key.php` (vedi sotto) ed è inviato come HttpOnly, SameSite=Lax e Secure su HTTPS. Le password sono salvate come hash bcrypt.

### Server MCP e Chiavi API
Il sito risponde ai client AI (Claude Desktop, claude.ai, Claude Code, ...) all'indirizzo `index.php?fnapp=mcp`, con l'autenticazione tramite chiave API personale o login OAuth (`index.php?fnapp=oauthserver`). `$_FN['apikey_groups']` limita gli utenti che possono usare una chiave API ("" = tutti) e `$_FN['mcp_write_group']` (predefinito `mcp_write`) indica il gruppo che, oltre agli amministratori, può usare gli strumenti di scrittura. Vedere la [guida MCP](it_guida_mcp.md).

## Primo Accesso
1. Dopo l'installazione, accedere all'URL del sito
2. Completare la configurazione guidata:
   - Lingua dell'installatore
   - Controllo dei permessi della cartella dei dati
   - Impostazioni: lingue, tema, nome del sito, titolo e sottotitolo, indirizzo email, sezione iniziale
   - Username, email e password dell'amministratore
3. Accedere al pannello di amministrazione all'URL: `http://tuosito.com/?fnapp=controlcenter`

## Struttura dei File e Cartelle
- `src/`: Contiene i file sorgenti del framework
- `sections/`: Contiene le pagine del sito (una cartella per ogni sezione)
- `themes/`: Contiene i temi grafici
- `modules/`: Contiene i vari tipi di sezioni disponibili
- `controlcenter/`: Contiene le sezioni e i temi del pannello di controllo
- `misc/`: Contiene i dati, inclusi i file del database
- `languages/`: Contiene le traduzioni

### La Cartella dei Dati
`misc/` (`$_FN['datadir']`) contiene tutti i dati del sito:

| Percorso | Contenuto |
|---|---|
| `misc/fndatabase/` | descrittori delle tabelle e, con i driver a file, i record e i loro file caricati |
| `misc/secret.key.php` | segreto del sito, creato automaticamente (permessi 0600): firma i cookie di login e i token OAuth e cifra le copie delle chiavi API |
| `misc/_sessions/` | sessioni lato server, un file per browser, eliminato un'ora dopo l'ultima scrittura |
| `misc/_login_attempts/` | contatori dei tentativi di login falliti |
| `misc/_cache/` | cache; si può svuotare in qualsiasi momento |
| `misc/_THUMBS/` | miniature delle immagini, inviate ai browser |
| `misc/_oauth/` | marcatori dei codici OAuth e dei refresh token già usati |
| `misc/log/` | `cron.log` e i file di log mensili delle versioni precedenti, mostrati come archivio dal pannello di controllo (il log degli eventi ora è la tabella `fn_log`) |
| `misc/firstinstall` | presente finché la procedura di installazione non è conclusa |

Il backup deve comprendere tutta la cartella dei dati, `secret.key.php` incluso; `secret.key.php` non va mai messo in un repository. Senza di esso tutti gli utenti vengono disconnessi, i client OAuth devono rifare il login e gli amministratori non possono più mostrare le chiavi API esistenti (le chiavi continuano a funzionare).

## Server Web

### Apache e .htaccess
Quando mod_rewrite è attivo (impostazione `enable_mod_rewrite`, attiva per default) e FINIS lo rileva, `index.php` scrive nel `.htaccess` della cartella del sito il blocco tra `# BEGIN Finis` e `# END Finis`, e lo riscrive quando cambia; il resto del file viene mantenuto. Il blocco:

- passa a PHP l'intestazione `Authorization` (chiavi API e token OAuth dei client MCP):
  `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`
- risponde a `/.well-known/oauth-authorization-server` e `/.well-known/oauth-protected-resource/...` con il server OAuth (login di Claude Desktop e claude.ai)
- riscrive gli URL leggibili (`<sezione>.html` con la modalità link `html`, `<lingua>/<sezione>/` con `folders`)

FINIS rileva mod_rewrite con `apache_get_modules()` (PHP come modulo di Apache) o con la variabile d'ambiente `HTTP_MOD_REWRITE=On`. Con PHP-FPM o CGI impostare `enable_mod_rewrite` a 2 (forza) oppure scrivere le regole a mano. Se `.htaccess` non è scrivibile, gli URL leggibili vengono disattivati. Per un sito in una sottocartella le regole `/.well-known` vanno anche nel `.htaccess` della radice del dominio: vedere la [guida MCP](it_guida_mcp.md).

### Altri Server Web
Gli indirizzi `index.php?...` funzionano senza riscritture. Per gli URL leggibili o il login OAuth dei client MCP, tradurre le regole del blocco `# BEGIN Finis` e controllare che l'intestazione `Authorization` arrivi a PHP.

### Protezione della Cartella dei Dati
La cartella dei dati è dentro la cartella del sito: i suoi file `.php` iniziano con `<?php exit(0); ?>` e devono essere eseguiti da PHP, mai inviati come testo. `misc/_sessions/`, `misc/_login_attempts/`, `misc/_cache/` e `misc/_oauth/` non vengono mai usate tramite URL: disattivare l'elenco delle cartelle e negarne l'accesso, ad esempio con Apache, fuori dal blocco Finis del `.htaccess`:

```apache
Options -Indexes
RedirectMatch 403 /misc/(_sessions|_login_attempts|_cache|_oauth)/
```

I file caricati in `misc/fndatabase/` e le miniature in `misc/_THUMBS/` devono restare raggiungibili.

## Risoluzione Problemi
- **Schermata bianca o errore 500**: `display_errors` è "off" per default; controllare il log degli errori di PHP, oppure impostare `$_FN['display_errors'] = "on";` in `config.vars.local.php` mentre si cerca il problema
- **Errori di permessi** (`permissions error: misc`, `permissions error: ./sections/`): rendere scrivibili dal server web la cartella dei dati `misc/` (con le sue sottocartelle) e `sections/`; l'installatore mostra il comando da eseguire
- **Database non accessibile**: Controllare i parametri di connessione nel file di configurazione
- **"Servizio momentaneamente non disponibile" (HTTP 503) con MySQL**: il server di database ha rifiutato la connessione perché occupato (troppe connessioni, `max_user_connections`) o non raggiungibile. FINIS riprova per circa 5 secondi, poi risponde 503 con una pagina che si ricarica da sola dopo 10 secondi; gli utenti restano collegati. Nel log degli errori di PHP compare una riga `xmetadb: MySQL connection failed`. Controllare i limiti di connessione dell'utente MySQL e del server
- **Un utente non riesce a entrare con la password giusta**: dopo `login_max_attempts` tentativi falliti dallo stesso indirizzo IP il nome utente resta bloccato al massimo per `login_lockout_minutes`. Attendere, oppure eliminare la cartella `misc/_login_attempts/` (svuotare la cache non lo sblocca). Dietro un reverse proxy impostare `trusted_proxies`: altrimenti tutti i visitatori hanno l'indirizzo del proxy e chiunque può bloccare un nome utente per tutti
- **Tutti gli utenti sono stati disconnessi**: `misc/secret.key.php` è stato eliminato o sostituito (ad esempio ripristinando i dati senza di esso): viene creato un nuovo segreto e tutti devono rifare il login
- **Gli utenti vengono disconnessi a ogni pagina**: il segreto del sito non può essere salvato; rendere scrivibile la cartella dei dati
- **Gli URL leggibili o il login OAuth dei client MCP non funzionano**: il `.htaccess` non è stato scritto; controllare mod_rewrite, l'impostazione `enable_mod_rewrite` e i permessi di `.htaccess` (vedere Server Web)

## Risorse Aggiuntive
- Documentazione: Consultare i file in `doc/`
- Esempi di utilizzo: Vedere i file in `examples/`
- Server MCP e chiavi API: [guida MCP](it_guida_mcp.md)

## Sviluppo e Personalizzazione
Dopo l'installazione, potrai:
1. Creare nuove sezioni nella cartella `sections/`
2. Personalizzare l'aspetto modificando i file nella cartella `themes/`
3. Estendere le funzionalità con nuovi moduli nella cartella `modules/`
