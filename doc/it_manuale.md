# Finis CMS

## Installazione

1. Scaricare i sorgenti e caricare sul provider il contenuto di `src/`.
2. Rinominare `index.php.example` in `index.php`.
3. Creare il file `config.vars.local.php` se necessario (ad esempio per MySQL).
4. Accedere all'URL dell'installazione, ad esempio `http://[tuo-sito]/`.
5. Seguire la procedura guidata di installazione.

Requisiti, estensioni PHP, server web e cartella dei dati sono descritti nel [Manuale di Installazione](it_manuale_installazione.md).

## Struttura delle cartelle

### Files del Framework

- `config.php`
- `FINIS.php`
- `modules/`
- `themes/`
- `controlcenter/`
- `extra/`
- `images/`
- `languages/`
- `plugins/`
- `include/`
- `sections/` (sezioni predefinite, copiate nel sito dall'installatore)

### Files del Sito Web

- `sections/`
- `themes/`
- `modules/` (tipi di sezione dell'applicazione)
- `controlcenter/sections/` (pagine del pannello di controllo dell'applicazione)
- `include/` (`autoexec.d/`, `methods/`, `auth/`, `mcp_tools.php`)
- `config.vars.local.php`
- `index.php`

### Files delle Estensioni

Un'estensione (ad esempio `fncommerce/`) è una cartella separata con `modules/`, `sections/`, `controlcenter/sections/` e `misc/fndatabase/`, caricata con `$FINIS->addExtension()`.

### Files contenenti i dati

- `misc/`

Oltre al database, la cartella dei dati contiene il segreto del sito `misc/secret.key.php` (creato automaticamente: va salvato nei backup insieme ai dati e mai messo in un repository), le sessioni lato server (`_sessions/`), i contatori dei tentativi di login (`_login_attempts/`) e la cache (`_cache/`).

Il database è descritto all'interno dei file XML nella cartella `misc/fndatabase`. Ogni file descrive una tabella e ogni tabella può avere il proprio driver per supportare diversi sistemi di archiviazione dati come MySQL, file CSV, MSSQL, XMLPHP, ecc.

### Esempio di descrittore di tabella `fn_sections`

Il file XML descrittivo di una tabella include campi come `id`, `type`, `parent`, `position`, `title`, `description`, `startdate`, `enddate`, `status`, `hidden`, `accesskey`, `keywords`, `sectionpath`, `level`, `group_view`, `group_edit`, `blocksmode`, e `blocks`.

## Descrizione delle Cartelle

### `sections/`

Contiene la lista delle sezioni, con una cartella per ogni sezione. Ad esempio, `sections/home` sarà l'homepage. In modalità website, ogni sezione corrisponde a una pagina web e può essere di tipo diverso, definito nella cartella `modules/`.

### `modules/`

Contiene i tipi di sezione, uno per ogni sottocartella. Ad esempio, `/modules/login/section.php` definisce i sorgenti per le pagine di tipo login. Se il tipo di sezione non è specificato, sarà di tipo Finis.

### `themes/`

Esempio di tema per Finis:

`themes/mytheme/template.tp.html` include un layout HTML base con intestazioni, menu, contenuti principali, e footer.

## Configurazione

Esempio di file `config.vars.local.php`:

```php
<?php
global $_FN;
//display error ("on" only while developing), http://php.net/manual/it/errorfunc.configuration.php#ini.display-errors
$_FN['display_errors'] = "off";
//authentication method (include/auth/)
$_FN['default_auth_method'] = "local";
//specific options for the mysql driver:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "finis";
$_FN['xmetadb_mysqlusername'] = "root";
$_FN['xmetadb_mysqlpassword'] = "";
```

I valori predefiniti di queste chiavi sono in `src/include/config.vars.php`. Altre chiavi di questo file: `trusted_proxies` (reverse proxy), `home_section_force` (sezione iniziale che prevale sull'impostazione del pannello di controllo), le chiavi di sicurezza del login e `apikey_groups` / `mcp_write_group` (vedere il [Manuale di Installazione](it_manuale_installazione.md)). Le altre impostazioni del sito si modificano nel pannello di controllo (`index.php?fnapp=controlcenter`).

## Applicazione e Sorgenti

I file possono convivere nella stessa cartella, ma è possibile separare i sorgenti del framework da quelli dei siti web, ad esempio in `finis_src/` e `website/`.

La `index.php` nella cartella del sito web include `../finis_src/FINIS.php`:

```php
<?php
require_once "../finis_src/FINIS.php";
$FINIS = new FINIS(array("src_application" => "."));
$FINIS->addExtension("../fncommerce/"); // facoltativo, una chiamata per estensione
$FINIS->finis();
```

I moduli (tipi di sezione) e le sezioni del pannello di controllo vengono cercati nella cartella dell'applicazione, poi nei sorgenti di FINIS, poi nelle estensioni. `$FINIS->finis()` serve il sito e i punti di ingresso scelti con `?fnapp=`: `controlcenter`, `api`, `mcp`, `oauthserver`, `thumb`, ... Un'applicazione aggiunge i propri in `include/methods/<nome>.php` (nomi fatti di lettere, cifre e `_`).

Il server MCP `index.php?fnapp=mcp` permette ai client AI di lavorare sul sito come un utente FINIS, con una chiave API personale o un login OAuth: vedere la [guida MCP](it_guida_mcp.md).

