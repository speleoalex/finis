# Server MCP e chiavi API di FINIS

FINIS espone un server [MCP](https://modelcontextprotocol.io) (Model Context Protocol)
che permette a un client LLM (Claude Code, Claude Desktop, Cursor, agenti personalizzati)
di operare sul CMS e sul pannello di controllo: leggere e scrivere pagine, gestire la
mappa del sito, i blocchi, le impostazioni e gli utenti.

Ogni chiamata è autenticata con una **chiave API personale** ed è eseguita con
l'identità del proprietario della chiave: valgono gli stessi permessi del pannello di
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

L'header è accettato da tutto il sito, non solo dall'endpoint MCP: `FN_ManageLogin()`
autentica l'utente dalla chiave quando non esiste una sessione con cookie.

Nelle installazioni esistenti il campo `apikey` viene aggiunto automaticamente alla
definizione della tabella `fn_users` (`FN_EnsureUserApiKeyField()`); con il driver MySQL
la colonna viene creata alla prima scrittura.

## Endpoint

```
POST {siteurl}/index.php?fnapp=mcp
Content-Type: application/json
Authorization: Bearer <apikey>
```

* Trasporto: MCP *Streamable HTTP*, solo POST. `GET` risponde 405 (nessuno stream SSE),
  `DELETE` risponde 200, `OPTIONS` risponde 204 con gli header CORS.
* Versioni di protocollo: `2025-06-18`, `2025-03-26`, `2024-11-05`.
* Senza chiave valida la risposta è `401` con `WWW-Authenticate: Bearer`. I cookie del
  browser vengono ignorati di proposito: l'endpoint accetta solo client con chiave API.
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

## Tool

I risultati contengono i dati sia come testo JSON in `content[0].text` sia in
`structuredContent`. Gli errori hanno `isError: true` e un messaggio `error`.
"admin" significa livello utente 10 o superiore.

| Tool | Chi | Descrizione |
|------|-----|-------------|
| `whoami` | tutti | Utente della chiave, livello, gruppi |
| `site_info` | tutti | Titolo, lingue, tema, home, versione, tipi di sezione |
| `list_sections` | tutti | Mappa del sito (`parent`, `recursive`, `include_disabled`); i non admin vedono solo le sezioni visibili |
| `get_section` | chi può vedere | Tutti i campi di una sezione, titoli per lingua, file di contenuto |
| `list_section_types` | tutti | Tipi di sezione (moduli) |
| `create_section` | admin | Nuova pagina: `title`, `id`, `type`, `parent`, `titles`, `descriptions`, `group_view`, `group_edit`, `content` opzionale |
| `update_section` | editor | Gli editor (group_edit) cambiano titolo, descrizione, keywords, status, hidden, date; gli admin tutti i campi |
| `delete_section` | admin | Elimina record e cartella; rifiuta la home; `force=true` elimina anche i figli |
| `get_section_content` | chi può vedere | `sections/<id>/section.<lang>.html` |
| `set_section_content` | editor | Scrive `section.<lang>.html` e svuota la cache; gli URL assoluti del sito vengono convertiti in link locali |
| `list_section_files` | editor | File nella cartella della sezione |
| `read_section_file` | editor | Legge un file della sezione (php solo admin) |
| `write_section_file` | editor | Scrive un file della sezione (php solo admin) |
| `list_blocks` | tutti | Blocchi visibili all'utente |
| `get_block_content` / `set_block_content` | vedere / editor | `blocks/<id>/section.<lang>.html` |
| `list_settings` | admin | `fn_settings` (password e chiavi nascoste) |
| `update_setting` | admin | Modifica un'impostazione |
| `list_users` / `get_user` | admin (get_user: anche se stesso) | Utenti senza password e chiavi |
| `create_user` / `update_user` | admin | Utenti; la chiave API di un utente non si imposta da qui |
| `list_groups` | tutti | Gruppi |
| `get_log` | admin | Ultime righe di `fn_log` (`limit`, `context`) |
| `log_event` | tutti | Scrive un evento personalizzato con il nome utente |
| `clear_cache` | editor | Svuota la cache del sito |

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

* La callback riceve l'array degli argomenti e restituisce un array (risultato JSON).
* `FN_MCP_Fail($messaggio)` restituisce un errore del tool al client.
* Helper: `FN_MCP_Arg($args, $key, $default, $required)`, `FN_MCP_RequireAdmin()`,
  `FN_MCP_SafeId($id)`, `FN_MCP_Lang($lang)`, `FN_MCP_SafePath($base, $relative)`.
* Nei tool che modificano dati usare `FN_LogEvent($msg, "mcp")`.

## Note di sicurezza

* La chiave va trattata come una password: usare HTTPS, non metterla negli URL,
  rigenerarla se compromessa.
* L'endpoint ignora i cookie: una pagina malevola non può pilotare l'endpoint MCP
  sfruttando un browser già autenticato.
* I non amministratori possono scrivere solo file html, e solo nelle sezioni e nei
  blocchi il cui `group_edit` contiene uno dei loro gruppi.
