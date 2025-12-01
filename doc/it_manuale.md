# Finis CMS

## Installazione

1. Scaricare i sorgenti e caricarli sul provider.
2. Creare il file `config.vars.local.php` se necessario.
3. Accedere all'URL dell'installazione, ad esempio `http://[tuo-sito]/`.
4. Seguire la procedura guidata di installazione.

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

### Files del Sito Web

- `sections/`
- `themes/`
- `config.vars.local.php`
- `index.php`

### Files contenenti i dati

- `misc/`

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
//display error, http://php.net/manual/it/errorfunc.configuration.php#ini.display-errors
$_FN['display_errors'] = "on";
//authentication method (include/auth/)
$_FN['default_auth_method'] = "local";
//specific options for the mysql driver:
$_FN['default_database_driver'] = "mysql";
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "finis";
$_FN['xmetadb_mysqlusername'] = "root";
$_FN['xmetadb_mysqlpassword'] = "";
```

## Applicazione e Sorgenti

I file possono convivere nella stessa cartella, ma è possibile separare i sorgenti del framework da quelli dei siti web, ad esempio in `finis_src/` e `website/`.

La `index.php` nella cartella del sito web include `../finis_src/FINIS.php`.

