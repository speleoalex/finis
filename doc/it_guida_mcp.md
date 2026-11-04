# Server MCP e chiavi API di FINIS

FINIS espone un server [MCP](https://modelcontextprotocol.io) (Model Context Protocol)
che permette a un client LLM (Claude Code, Claude Desktop, Cursor, agenti personalizzati)
di operare sul CMS e sul pannello di controllo: leggere e scrivere pagine, gestire la
mappa del sito, i blocchi, le impostazioni e gli utenti.

Ogni chiamata è autenticata con una **chiave API personale** o con un **accesso OAuth**
(utente e password, vedi sotto) ed è eseguita con l'identità dell'utente: valgono gli stessi permessi del pannello di
controllo e ogni modifica finisce nel log del sito con il nome dell'utente.

## Chiavi API

* Ogni utente ha una chiave API, salvata nel campo `apikey` di `fn_users`. Non compare
  mai negli elenchi utenti e non è esportata dalle API.
* La chiave viene generata automaticamente la prima volta che l'utente apre la pagina
  del profilo ed è visibile solo al proprietario:
  * pannello di controllo: **Utenti e gruppi > Profilo** (`index.php?fnapp=controlcenter&opt=users_and_groups/profile`)
  * front end: **Modifica profilo** del modulo login (`index.php?mod=login&op=editreg`)
* Il pulsante **Rigenera chiave API** crea una nuova chiave; la vecchia smette subito di funzionare.
* Gli amministratori non vedono le chiavi degli altri utenti.

La chiave si invia nell'header HTTP di ogni richiesta:

```
Authorization: Bearer <apikey>
```

oppure, se il web server rimuove l'header `Authorization`:

```
X-Api-Key: <apikey>
```

Per i client che si configurano solo con un URL (senza header personalizzati), l'endpoint MCP
accetta la chiave anche come parametro:

```
https://example.org/index.php?fnapp=mcp&apikey=<apikey>
```

Se ci sono sia l'header sia il parametro vale l'header. Il parametro funziona solo con
`fnapp=mcp`: le altre pagine lo ignorano. Una chiave nell'URL finisce nei log del web server e
nella cronologia del client: meglio l'header quando il client lo permette, e se l'URL circola va
rigenerata la chiave.

L'header è accettato da tutto il sito, non solo dall'endpoint MCP: `FN_ManageLogin()`
autentica l'utente dalla chiave quando non esiste una sessione con cookie.

Nelle installazioni esistenti il campo `apikey` viene aggiunto automaticamente alla
definizione della tabella `fn_users` (`FN_EnsureUserApiKeyField()`); con il driver MySQL
la colonna viene creata alla prima scrittura.

### Limitare le chiavi API ad alcuni utenti

Di default ogni utente può usare la propria chiave. Per consentirla solo ad alcuni utenti
si indicano i gruppi in `config.php` (o in un file `extra/*.inc.php` dell'applicazione):

```php
$_FN['apikey_groups'] = "mcp_users";   // gruppi separati da virgola, "" = tutti gli utenti
```

Da quel momento solo gli amministratori (livello 10) e i membri di quei gruppi possono
usare una chiave API: le chiavi degli altri utenti sono rifiutate ovunque (endpoint MCP,
accesso al sito con l'header) e la pagina del profilo non mostra né genera la chiave. Il
controllo è `FN_UserCanUseApiKey($user)`. Per abilitare un utente basta aggiungerlo a uno
dei gruppi.

## Endpoint

```
POST {siteurl}/index.php?fnapp=mcp
Content-Type: application/json
Authorization: Bearer <apikey>
```

* Trasporto: MCP *Streamable HTTP*, solo POST. `GET` risponde 405 (nessuno stream SSE),
  `DELETE` risponde 200, `OPTIONS` risponde 204 con gli header CORS.
* Versioni di protocollo: `2025-06-18`, `2025-03-26`, `2024-11-05`.
* Senza chiave o token validi la risposta è `401` con
  `WWW-Authenticate: Bearer resource_metadata="..."`. I cookie del browser vengono
  ignorati di proposito: l'endpoint accetta solo client con chiave API o OAuth.
* In modalità manutenzione l'endpoint è utilizzabile solo dagli amministratori.
* Il server è senza stato: nessun id di sessione, ogni richiesta è completa. Sono
  supportati i batch JSON-RPC; le notifiche ricevono `202 Accepted`.

### Configurazione del client

Claude Code:

```sh
claude mcp add --transport http finis "https://www.example.com/index.php?fnapp=mcp" \
    --header "Authorization: Bearer 39bc8f0d0185bbad495d40c5f866b763ec9fdbc2e8d221bd"
```

Configurazione JSON generica (Claude Desktop, Cursor, ...):

```json
{
  "mcpServers": {
    "finis": {
      "type": "http",
      "url": "https://www.example.com/index.php?fnapp=mcp",
      "headers": { "Authorization": "Bearer 39bc8f0d0185bbad495d40c5f866b763ec9fdbc2e8d221bd" }
    }
  }
}
```

## Accesso con OAuth (Claude Desktop, claude.ai)

Ai client con OAuth 2.1 (connettori personalizzati di Claude Desktop e claude.ai, Claude
Code, MCP Inspector, ...) basta l'URL dell'endpoint. Alla prima chiamata il client apre il
browser, l'utente accede con utente e password e autorizza il client: nessuna chiave da
copiare.

* Possono accedere gli utenti abilitati alla chiave API (`FN_UserCanUseApiKey()`, vedi
  `$_FN['apikey_groups']`), con gli stessi permessi.
* L'accesso è quello di FINIS (`FN_ManageLogin()`: captcha, blocco dopo
  `login_max_attempts` tentativi falliti). Segue una pagina di consenso con il nome del
  client e l'host a cui torna il browser.
* Il token di accesso dura un'ora, il refresh token 30 giorni e viene sostituito a ogni
  rinnovo; il client li rinnova da solo.
* Generare una nuova chiave API, revocarla o cambiare la password scollega tutti i client
  OAuth dell'utente. Disattivare l'utente o toglierlo da `apikey_groups` ha effetto dalla
  richiesta successiva.

Endpoint (`src/include/methods/oauthserver.php`, funzioni in
`src/include/functions_oauth_server.inc.php`):

| URL | Scopo |
|---|---|
| `index.php?fnapp=oauthserver&op=metadata` | metadati dell'authorization server (RFC 8414) |
| `index.php?fnapp=oauthserver&op=prm&resource=<url>` | metadati della risorsa protetta (RFC 9728) |
| `index.php?fnapp=oauthserver&op=register` | registrazione dinamica dei client (RFC 7591), solo client pubblici |
| `index.php?fnapp=oauthserver&op=authorize` | accesso e consenso, PKCE `S256` obbligatorio |
| `index.php?fnapp=oauthserver&op=token` | grant `authorization_code` e `refresh_token` |

Il 401 dell'endpoint MCP contiene `WWW-Authenticate: Bearer resource_metadata="<url>"` e
da lì il client trova il resto. Client e token non sono salvati: sono firmati con il
segreto del sito (`misc/secret.key.php`). Cancellare quel file invalida tutti i token e
tutti i cookie di accesso.

Un'applicazione protegge un proprio endpoint allo stesso modo: `FN_ManageLogin()` accetta
già il token (`$_FN['auth_method'] = "oauth"`) e la risposta 401 chiama
`FN_OAuthServerChallenge(<url dell'endpoint>)`.

### Requisiti

* HTTPS: i client rifiutano indirizzi `http://` fuori da localhost. Dietro un proxy che
  nasconde l'HTTPS, impostare `siteurl` nelle impostazioni.
* L'header `Authorization` deve arrivare a PHP. Il `.htaccess` scritto da FINIS con
  mod_rewrite attivo contiene la regola; altrimenti aggiungerla:

  ```apache
  RewriteEngine On
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
  ```

* I client cercano i metadati nella radice dell'host
  (`/.well-known/oauth-authorization-server/<percorso del sito>`). Un sito nella radice del
  dominio è coperto dal `.htaccess` di FINIS. Un sito in una cartella richiede queste regole
  nel `.htaccess` della radice del dominio (esempio per un sito in `/catasto/`):

  ```apache
  RewriteEngine On
  RewriteRule ^\.well-known/oauth-authorization-server/catasto/?$ /catasto/index.php?fnapp=oauthserver&op=metadata [L]
  RewriteRule ^\.well-known/oauth-protected-resource/(catasto/.*)$ /catasto/index.php?fnapp=oauthserver&op=prm&path=$1 [L]
  ```

  Su Plesk/nginx verificare che `/.well-known/` arrivi ad Apache e non sia servito solo
  dalla location di Let's Encrypt.

## Tool

Le risposte sono compatte: testo semplice (mappa del sito, blocchi, log, guida) oppure
JSON senza indentazione e senza campi vuoti, inviati una sola volta in `content[0].text`
(niente copia in `structuredContent`). Gli errori hanno `isError: true` e un messaggio
`error`. "admin" significa livello utente 10 o superiore.

### Guida del sito (`site_guide`)

`site_guide` è il primo tool dell'elenco e le istruzioni di `initialize` chiedono al
modello di chiamarlo prima degli altri. Con una chiamata (circa 1.000 token per un sito di
20 pagine) restituisce, per l'utente collegato:

* sito, utente (amministratore o editor) e lingue;
* come sono fatte e come si modificano le pagine: un `section.<lang>.html` per lingua;
  l'html è un template, quindi `{nome}`, `<!-- if {x} -->` e `<!-- foreach {x} -->` vanno
  mantenuti; il flag `php` (`section.php` prepara i dati o stampa parti dinamiche che non
  sono nell'html); le sezioni modulo; i link; i backup;
* i tipi di modulo usati, con cosa contengono le loro pagine;
* le posizioni dei blocchi stampate dal tema, lette da `themes/<tema>/template*.tp.html`
  e `theme.php`: se non ce ne sono, la guida dice che i blocchi non compaiono mai;
* la mappa del sito, una riga per sezione, figli rientrati: `id [tipo] "titolo" flag`
  (senza `[tipo]` = pagina di testo), flag `hidden`, `off`, `private`, `php`, `edit` (solo
  per i non amministratori), `langs:<file presenti>`, `nohtml`;
* le note dell'applicazione.

Un'applicazione descrive i propri tipi di modulo e aggiunge note nella configurazione:

```php
$_FN['mcp_type_info']['caveview'] = "cave register records: use the OpenKIS MCP server";
$_FN['mcp_guide']['shop'] = "Products are records: use the shop tools.";
```

| Tool | Chi | Descrizione |
|------|-----|-------------|
| `site_guide` | tutti | Guida e mappa del sito, vedi sopra |
| `whoami` | tutti | Nome utente, admin, livello, gruppi, email |
| `site_info` | tutti | Titolo, indirizzo, lingue, tema, home, versione |
| `list_sections` | tutti | Mappa del sito nel formato di `site_guide`; `parent` = solo quel ramo; i non admin vedono solo le sezioni visibili |
| `get_section` | chi può vedere | Campi di una sezione, titoli e descrizioni per lingua, file (senza backup), indirizzo |
| `list_section_types` | tutti | Nomi dei moduli |
| `create_section` | admin | `title`, `id`, `parent`, `type`, `content`, `lang`; gli altri campi (`titles`, `descriptions`, `status`, `hidden`, `group_view`, ...) in `fields` |
| `update_section` | editor | `id` + `fields`: gli editor titolo/i, descrizione/i, keywords, status, hidden, date; gli admin tutti i campi. Sono accettati anche i campi al primo livello |
| `delete_section` | admin | Elimina record e cartella; rifiuta la home; `force=true` elimina anche i figli |
| `get_section_content` | chi può vedere | `section.<lang>.html`, con ripiego sulla lingua predefinita e su `section.html` segnalato da una nota; `php: true` se la sezione ha un `section.php` |
| `edit_section_content` | editor | Sostituisce `old` con `new` in `section.<lang>.html`: `old` deve comparire esattamente una volta (`all=true`: tutte). Una piccola modifica costa solo il testo cambiato |
| `set_section_content` | editor | Scrive tutto `section.<lang>.html` |
| `list_section_files` | editor | `nome dimensione data` per file; i backup (`*.bak~`) solo contati, elencati con `backups=true` |
| `read_section_file` | editor | Testo di un file della sezione (php solo admin) |
| `write_section_file` | editor | Scrive un file della sezione (php solo admin) |
| `list_blocks` | tutti | `id [tipo] posizione "titolo"`, con `off`, `not shown by the theme`, `only in: <sezioni>` |
| `get_block_content` / `set_block_content` | vedere / editor | `blocks/<id>/section.<lang>.html` |
| `list_settings` | admin | `{nome: valore}` di `fn_settings` (password e chiavi nascoste) |
| `update_setting` | admin | Modifica un'impostazione |
| `list_users` / `get_user` | admin (get_user: anche se stesso) | Campi principali (username, email, level, group, active, name; `full=true`: tutti), mai password e chiavi |
| `create_user` / `update_user` | admin | Campi in `fields` (email, level, group, active, ...); la chiave API non si imposta da qui |
| `list_groups` | tutti | Gruppi |
| `get_log` | admin | Una riga per evento, dal più recente: `data utente contesto: evento` (`limit`, `context`) |
| `log_event` | tutti | Scrive un evento personalizzato con il nome utente |
| `clear_cache` | editor | Svuota la cache del sito |

Ogni scrittura di un file di contenuto (`edit_section_content`, `set_section_content`,
`write_section_file`, `set_block_content`) conserva la versione precedente come
`<file>.<mtime>.<data>.<utente>.bak~` (`FN_BackupFile()`, come l'editor online) e svuota
la cache; riscrivere lo stesso contenuto non fa nulla.

### Link nei contenuti

L'HTML scritto con `set_section_content`, `set_block_content` e con l'argomento
`content` di `create_section` viene reso dal motore di template, che riscrive i link
al momento della visualizzazione. Valgono le stesse regole dell'editor:

* il link a un'altra pagina si scrive `index.php?mod=<id sezione>` (il motore lo
  trasforma in `<id>.html` / `<id>.<lang>.html` se mod_rewrite è attivo, altrimenti
  nell'URL `index.php?mod=` corretto);
* le altre risorse del sito (pannello di controllo, file, immagini) si linkano con un
  percorso relativo alla cartella della sezione, es. `../../index.php?fnapp=controlcenter`
  o `../../misc/images/logo.png`;
* gli URL assoluti del sito (`https://tuosito/percorso/...`) sono accettati: al
  salvataggio vengono convertiti in link locali relativi con
  `FN_RewriteLinksAbsoluteToLocal()`, esattamente come fa l'editor, così il contenuto
  continua a funzionare se il sito cambia indirizzo.

Non scrivere mai `/index.php?mod=...` (con la barra iniziale): il motore lo farebbe
precedere dal percorso della sezione.

I percorsi sono confinati alla cartella della sezione o del blocco: `..` e percorsi
assoluti vengono rifiutati. Gli id accettano solo lettere, cifre, `_`, `-` e `.`.

## Log

Ogni modifica chiama `FN_LogEvent($messaggio, "mcp")`: le colonne `user`, `ip` e `date`
di `fn_log` indicano chi ha fatto cosa. La generazione delle chiavi è loggata con
contesto `apikey`. Il log si legge dal pannello di controllo o con il tool `get_log`.

## Aggiungere tool da un'applicazione

Creare `{src_application}/include/mcp_tools.php`, incluso dopo i tool di base:

```php
<?php
defined('_FNEXEC') or die('Restricted access');

FN_MCP_AddTool(
    "list_products",
    "Elenca i prodotti del negozio",
    array(
        "properties" => array(
            "category" => array("type" => "string", "description" => "Id categoria"),
        ),
    ),
    function ($args)
    {
        global $_FN;
        if (!FN_UserInGroup($_FN['user'], "shop"))
            FN_MCP_Fail("permission denied");
        $table = FN_XMDBTable("products");
        $restr = empty($args['category']) ? false : array("category" => $args['category']);
        return array("products" => $table->GetRecords($restr));
    }
);
```

* La callback riceve l'array degli argomenti e restituisce un array (inviato come JSON
  compatto) o una stringa (inviata così com'è: costa meno per gli elenchi).
* `FN_MCP_Fail($messaggio)` restituisce un errore del tool al client.
* Helper: `FN_MCP_Arg($args, $key, $default, $required)`, `FN_MCP_RequireAdmin()`,
  `FN_MCP_SafeId($id)`, `FN_MCP_Lang($lang)`, `FN_MCP_SafePath($base, $relative)`.
* Nei tool che modificano dati usare `FN_LogEvent($msg, "mcp")`.

## Note di sicurezza

* La chiave va trattata come una password: usare HTTPS, preferire l'header a `&apikey=`
  nell'URL (gli URL finiscono nei log), rigenerarla se compromessa.
* L'endpoint ignora i cookie: una pagina malevola non può pilotare l'endpoint MCP
  sfruttando un browser già autenticato.
* I non amministratori possono scrivere solo file html, e solo nelle sezioni e nei
  blocchi il cui `group_edit` contiene uno dei loro gruppi.
