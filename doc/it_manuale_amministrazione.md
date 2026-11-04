# Manuale di Amministrazione FINIS Framework

## Introduzione
Questo manuale è rivolto agli amministratori di siti web basati su FINIS. Fornisce istruzioni dettagliate sull'utilizzo del pannello di controllo, la gestione di utenti, contenuti, e la configurazione del sistema.

## Accesso al Pannello di Controllo

### Login Amministratore
1. Accedi all'URL del tuo sito
2. Clicca sul link "Login" o accedi direttamente a `http://tuosito.com/?mod=login`
3. Inserisci le credenziali di amministratore configurate durante l'installazione
4. Dopo il login, accedi al pannello di controllo tramite `http://tuosito.com/index.php?fnapp=controlcenter` (oppure `controlcenter.php`, se il sito ha una copia di `controlcenter.php.example`)

Se apri il pannello di controllo senza aver fatto il login come amministratore, il pannello mostra un proprio form di login. Gli amministratori sono gli utenti di livello 10.

### Interfaccia del Pannello di Controllo
Il pannello di controllo è organizzato in gruppi di pagine, elencati nel menu laterale:

- **Contenuti**: **Pagine e struttura sito** (l'albero delle pagine), **Blocchi**, **File**, più una voce per ogni pagina il cui modulo ha una pagina di gestione (ad esempio **Notizie in "..."**)
- **Impostazioni generali**: **Configurazione generale**, **Aspetto**, più le impostazioni delle pagine e dei blocchi il cui modulo ha delle opzioni (ad esempio **Pagina Notizie: ...**, **Pagina dbview: ...**)
- **Utenti e gruppi**: **Utenti**, **Gruppi**, **Campi utente**, **Modifica profilo**, **Condizioni per la registrazione**, **Provider OAuth**, **Server MCP**
- **Utilità**: **Utenti pannello di controllo**, **Operazioni pianificate**, **Registro eventi**, **Invio mail**, **Informazioni server**, **Database Finis**
- **Plugins**: compare solo se il sito ha dei plugin con una pagina di impostazioni

La pagina iniziale (dashboard) mostra i riepiloghi dei moduli che ne forniscono uno, ad esempio il numero di notizie pubblicate e non pubblicate.

Le applicazioni e le estensioni possono aggiungere pagine proprie a questi gruppi: compaiono nel menu insieme alle altre.

L'aspetto del pannello si sceglie con **Tema preferito pannello di controllo** in **Impostazioni generali → Configurazione generale** (`classic`, `adminkit`, `sidebar`). Il tema `adminkit` ha nella barra in alto un'icona di aiuto che apre la documentazione di FINIS su GitHub.

## Gestione Utenti e Gruppi

### Gestione Utenti
Accedi a **Utenti e gruppi → Utenti** per:

1. **Visualizzare utenti**: Lista di tutti gli utenti registrati (nome utente, email, livello, gruppi, data di registrazione). I link in alto mostrano tutti gli utenti, solo gli abilitati, solo i disabilitati o solo gli amministratori
2. **Creare un nuovo utente**:
   - Clicca su "Aggiungi un utente"
   - Compila i campi richiesti (username, password, email)
   - Seleziona i gruppi di appartenenza
   - Imposta il livello (10 = amministratore) e lo stato dell'account (attivo/disattivato)
   - Salva le modifiche
3. **Modificare un utente esistente**:
   - Clicca sull'icona di modifica accanto all'utente
   - Aggiorna i dati necessari; lascia vuota la password per mantenere quella attuale
   - Salva le modifiche
4. **Abilitare o disabilitare un utente**:
   - Usa il pulsante "Abilita" / "Disabilita" nella lista
   - Quando abiliti un utente, spunta "send welcome message" per inviargli l'email di benvenuto
   - Un utente disabilitato viene scollegato alla richiesta successiva, anche se aveva già fatto il login
   - Non puoi disabilitare il tuo account
5. **Eliminare un utente**:
   - Clicca sull'icona di eliminazione
   - Conferma l'operazione

Da sapere:
- Le password sono salvate come hash: nessuno, nemmeno l'amministratore, può leggerle. Per dare una nuova password a un utente scrivila nel form dell'utente. La nuova password scollega l'utente dagli altri browser in cui era collegato e revoca la sua chiave API.
- I nomi utente sono salvati e cercati in minuscolo (login, registrazione, recupero password): `Mario` e `mario` sono lo stesso utente. Per conservare maiuscole e minuscole come sono state scritte, imposta `$_FN['username_case_sensitive'] = 1;` in `config.vars.local.php`.

### Gestione Gruppi
Accedi a **Utenti e gruppi → Gruppi** per:

1. **Visualizzare gruppi**: Lista di tutti i gruppi esistenti
2. **Creare un nuovo gruppo**:
   - Clicca su "Nuovo"
   - Inserisci il nome del gruppo
   - Descrivi lo scopo del gruppo
   - Salva le modifiche
3. **Modificare un gruppo**:
   - Clicca sull'icona di modifica
   - Aggiorna le informazioni
   - Salva le modifiche
4. **Eliminare un gruppo**:
   - Clicca sull'icona di eliminazione
   - Conferma l'operazione (attenzione: gli utenti appartenenti al gruppo non verranno eliminati)

Gli utenti che si registrano dal sito vengono inseriti nel gruppo `users`.

### Campi Utente
**Utenti e gruppi → Campi utente** definisce i campi della scheda utente, mostrati nei form di registrazione e di modifica del profilo: nome, etichetta (con una traduzione per ogni lingua), tipo di campo, dimensione e opzioni.

### Il Tuo Profilo e la Chiave API
**Utenti e gruppi → Modifica profilo** modifica l'account con cui hai fatto il login. Nella stessa pagina c'è il riquadro della **chiave API**: "Genera chiave API" crea la tua chiave personale e la mostra una sola volta (copiala subito), "Revoca chiave API" la elimina. Con la chiave i client MCP e API lavorano sul sito con i tuoi permessi; vedi [Server MCP e chiavi API](#server-mcp-e-chiavi-api).

Gli utenti del sito trovano lo stesso riquadro nella pagina del profilo del modulo login (`index.php?mod=login&op=editreg`).

### Sistema di Permessi
I permessi in FINIS si basano sul livello degli utenti e sui gruppi:

1. **Permessi per Pagine**:
   - Accedi a **Contenuti → Pagine e struttura sito**
   - Modifica una pagina
   - Nella parte "Permessi" del form, definisci:
     - Livello utente per visualizzazione (visibile a tutti, solo utenti registrati, utenti con livello almeno 1-9, solo amministratori)
     - Gruppi abilitati alla visione
     - Gruppi abilitati alla modifica
2. **Permessi per Moduli**:
   - Ogni modulo può definire i propri permessi
   - Generalmente configurabili nelle impostazioni della pagina (**Impostazioni generali → Pagina ...**), ad esempio i gruppi di una pagina dbview
3. **Accesso al pannello per chi non è amministratore**:
   - Accedi a **Utilità → Utenti pannello di controllo**
   - Aggiungi l'utente, scegli le pagine del pannello che può usare e la pagina iniziale
   - L'utente entra nel pannello e vede solo quelle pagine

**Visibilità nel menu**: il menu mostra una pagina solo se il visitatore può vederne il contenuto. Le pagine con un livello o con dei gruppi di visualizzazione sono nascoste agli altri visitatori; una pagina dbview con i record riservati a un gruppo compare solo ai membri dei suoi gruppi (visualizzazione, inserimento, amministratori); una pagina dell'editor di tabelle solo agli amministratori. Gli amministratori vedono tutte le voci.

### Impostazioni di Registrazione
Per configurare il processo di registrazione:

1. Accedi a **Impostazioni generali → Configurazione generale** e configura:
   - **Abilita registrazione utenti**
   - **Registrazione tramite email**: il nuovo utente riceve un'email con il link di conferma; senza questa opzione l'account è attivo subito
   - **Utilizza l'indirizzo email come nome utente**
   - **permetti agli utenti di restare collegati**: mostra l'opzione "ricordami" nel form di login
   - **abilita captcha nel form di login**
2. Accedi a **Utenti e gruppi → Condizioni per la registrazione** per gestire le condizioni che l'utente accetta quando si registra: titolo, testo (uno per lingua), abilitata, facoltativa. La lista mostra titolo, testo, abilitata e facoltativa; gli altri campi sono nel form. Le condizioni abilitate compaiono nel form di registrazione; quelle non facoltative vanno accettate per forza.
3. Le opzioni della pagina di login (captcha nel form di registrazione, messaggio di benvenuto a registrazione avvenuta, cambio della password online durante il recupero) sono in **Impostazioni generali → Pagina login: ...**
4. I testi delle email inviate agli utenti sono i template del modulo login (`mailconfirm.tp.html`, `mailwelcome.tp.html`, `mailrecovery_*.tp.html`); il tema può fornirne una propria copia

I codici di conferma:
- Il link di conferma si usa una sola volta e resta valido finché l'account non è confermato. L'utente può richiederlo di nuovo dalla pagina di login; viene reinviato solo a una registrazione ancora in attesa di conferma.
- Le registrazioni fatte con una versione vecchia di FINIS (codici di 5 caratteri) non ricevono una nuova email: abilita questi utenti da **Utenti e gruppi → Utenti**.
- I reinvii rifiutati sono scritti nel **Registro eventi**.

### Login con Servizi Esterni (OAuth)
**Utenti e gruppi → Provider OAuth** elenca i servizi esterni (ad esempio Google) con cui gli utenti possono fare il login. Per ogni provider imposta:
- nome e immagine (mostrati nel form di login)
- `client_id` e `client_secret`, ottenuti registrando il sito presso il provider
- `auth_url`, `token_url`, `userinfo_url` e `scope` del provider
- abilitato

L'indirizzo da registrare presso il provider come redirect URI è `{siteurl}index.php?fnloginprovider=<id>`, dove `<id>` è il numero del record del provider. Viene collegato l'utente che ha l'indirizzo email fornito dal provider; se il provider dichiara l'indirizzo non verificato, il login è rifiutato. Se nessun utente ha quell'indirizzo e le registrazioni sono abilitate, viene creato un nuovo utente con l'indirizzo come nome utente.

### Sicurezza del Login
FINIS protegge il login senza bisogno di configurazione. L'amministratore deve sapere come funziona:

- **Blocco dopo troppi tentativi**: dopo `login_max_attempts` tentativi (5 di default) con lo stesso nome utente dallo stesso indirizzo entro `login_lockout_minutes` minuti (15 di default), i tentativi successivi vengono rifiutati finché il tempo non è passato. L'utente vede il solito messaggio generico di errore; un login riuscito azzera il conteggio. Ogni blocco è scritto una volta nel **Registro eventi** ("User ... locked out ...", con l'indirizzo del client). Per sbloccare subito un utente cancella la cartella `misc/_login_attempts` (nella cartella dati del sito): svuotare la cache non la azzera.
- **Ricordami**: con **permetti agli utenti di restare collegati** attivo, il form di login offre "ricordami"; il login dura allora `remember_login_days` giorni (30 di default).
- **Recupero password**: il link di recupero inviato per email vale `recovery_link_minutes` minuti (60 di default) e si usa una sola volta. La pagina di recupero risponde allo stesso modo per utenti esistenti e sconosciuti.
- **Segreto del sito**: il file `misc/secret.key.php` viene creato automaticamente e firma i cookie di login, i token OAuth dei client MCP e le copie salvate delle chiavi API. Cancellarlo o sostituirlo scollega tutti gli utenti. Salvalo nei backup insieme ai dati, non pubblicarlo e non metterlo mai in un repository.
- **Reverse proxy**: dietro un reverse proxy o un bilanciatore tutte le richieste arrivano dall'indirizzo del proxy, quindi un blocco fermerebbe un nome utente per tutti. Elenca gli indirizzi del proxy in `trusted_proxies`, così l'indirizzo del client viene letto da `X-Forwarded-For`.

Queste chiavi vanno in `config.vars.local.php` (i valori qui sotto sono quelli di default):

```php
$_FN['login_max_attempts'] = 5;       // tentativi di login per nome utente e indirizzo
$_FN['login_lockout_minutes'] = 15;   // finestra dei tentativi e durata massima del blocco
$_FN['remember_login_days'] = 30;     // durata del login con "ricordami"
$_FN['recovery_link_minutes'] = 60;   // validità del link di recupero password
$_FN['trusted_proxies'] = "";         // indirizzi IP dei reverse proxy, separati da virgola (niente intervalli)
```

### Server MCP e Chiavi API
FINIS include un server MCP (`index.php?fnapp=mcp`) che permette agli assistenti AI (Claude, ChatGPT, ...) di leggere e modificare il sito con i permessi di un utente. Il client si collega con la chiave API personale dell'utente oppure con un login OAuth (nome utente e password).

**Utenti e gruppi → Server MCP** (solo amministratori):
- elenca gli endpoint MCP del sito e l'indirizzo OAuth per Claude Desktop e claude.ai
- elenca gli utenti che hanno una chiave o possono scrivere; cerca un utente, o clicca "Tutti gli utenti", per agire su chiunque
- **Mostra chiave**: mostra la chiave di un utente (ogni chiave mostrata viene scritta nel Registro eventi); le chiavi create da versioni vecchie non si possono mostrare, va generata una nuova chiave
- **Genera chiave** / **Revoca**: crea una nuova chiave per l'utente (la precedente smette di funzionare) oppure la elimina
- **Consenti gli strumenti di scrittura** / **Imposta sola lettura**: la lettura è aperta a ogni utente attivo; gli strumenti che modificano il sito sono consentiti solo agli amministratori e ai membri del gruppo `mcp_write` (`$_FN['mcp_write_group']`, creato automaticamente)

`$_FN['apikey_groups']` in `config.vars.local.php` limita le chiavi API ai membri di alcuni gruppi (gli amministratori sempre). Una nuova password revoca la chiave dell'utente.

Configurazione dei client, elenco dei tool e note di sicurezza: vedi la [guida al server MCP e alle chiavi API](it_guida_mcp.md).

## Gestione dei Contenuti

### Struttura del Sito
Il sito è organizzato in **sezioni** (pagine). Ogni sezione può essere:
- Una pagina di contenuto
- Un modulo specifico (news, login, ecc.)
- Una sottosezione di un'altra sezione

Il menu del sito è costruito a partire da questo albero.

### Gestione Sezioni
Accedi a **Contenuti → Pagine e struttura sito** per:

1. **Visualizzare la struttura del sito**: Rappresentata come un albero gerarchico, con tipo, livello di visualizzazione, gruppi di visione e di modifica e date di pubblicazione di ogni pagina
2. **Aggiungere una nuova sezione**:
   - Clicca su "Crea una nuova pagina"
   - Compila il form:
     - **ID**: Identificativo unico (usato nell'URL)
     - **Titolo**: Nome visualizzato nel menu e nei titoli
     - **Tipo di pagina**: Scegli il modulo della pagina (vuoto per una pagina di contenuto, notizie, login, ecc.)
     - **Posizione**: Posizione nell'albero delle sezioni
     - **Permessi**: Livello di visualizzazione e gruppi con accesso alla sezione
     - **Stato pubblicazione**: Pubblicata o non pubblicata, con date di inizio e fine pubblicazione facoltative
     - **Pagina nascosta nei menu**: la pagina esiste ma non ha una voce nel menu
   - Salva la sezione
3. **Modificare una sezione esistente**:
   - Clicca sull'icona di modifica
   - Aggiorna i parametri necessari
   - Salva le modifiche
4. **Riordinare le sezioni**:
   - Usa le frecce di ogni pagina per spostarla in alto o in basso, o per cambiarne il livello (sinistra/destra)
   - Clicca la lampadina per nascondere o mostrare la pagina nel menu
   - Salva l'ordine
5. **Eliminare una sezione**:
   - Clicca sull'icona di eliminazione
   - Conferma l'operazione

### Modifica dei Contenuti
Per modificare il contenuto di una sezione:

1. Apri il contenuto della pagina da **Contenuti → Pagine e struttura sito**
2. Se è una sezione di tipo standard:
   - Usa l'editor WYSIWYG per modificare il contenuto
   - Inserisci testo, immagini e formattazione
   - Ogni lingua ha il suo testo: una traduzione mancante si può creare vuota o copiare dalla lingua predefinita
   - Sotto l'editor sono elencate le vecchie versioni, con l'utente che le ha sovrascritte: "Restore" ne ricarica una
3. Se è un tipo di sezione specifico (news, modulo personalizzato, ecc.):
   - L'interfaccia di modifica cambierà in base al tipo
   - Segui le istruzioni specifiche per quel tipo di contenuto
4. Salva le modifiche

### Gestione Blocchi
I blocchi sono contenuti secondari visualizzati in posizioni specifiche (barre laterali, intestazione, ecc.). Accedi a **Contenuti → Blocchi** per:

1. **Visualizzare blocchi esistenti**: Lista di tutti i blocchi configurati
2. **Creare un nuovo blocco**:
   - Clicca su "Crea un nuovo blocco"
   - Compila il form:
     - **Titolo**: Nome visualizzato nell'intestazione del blocco
     - **Tipo di blocco**: HTML o un modulo
     - **Posizione**: Sinistra, destra, alto, basso
     - **Pagine**: visualizzato ovunque, solo nelle pagine selezionate, oppure ovunque tranne le pagine selezionate
     - **Permessi**: Livello di visualizzazione e gruppi che possono vedere o modificare il blocco
     - **Stato**: Pubblicato o non pubblicato
   - Salva il blocco
3. **Modificare un blocco**:
   - Clicca sull'icona di modifica
   - Aggiorna i parametri
   - Salva le modifiche
4. **Riordinare i blocchi**:
   - Usa le frecce per spostare i blocchi
   - Salva l'ordine
5. **Eliminare un blocco**:
   - Clicca sull'icona di eliminazione
   - Conferma l'operazione

### Gestione File e Media
Accedi a **Contenuti → File** per gestire i file con il gestore file:

1. **Esplorare file e cartelle**: Naviga attraverso la struttura delle cartelle, come elenco o come icone
2. **Caricare nuovi file**:
   - Seleziona la cartella di destinazione
   - Clicca su "Carica un file"
   - Seleziona i file dal tuo computer
   - Conferma l'upload
3. **Creare nuove cartelle e nuovi file**:
   - Clicca su "Nuova cartella" o "Nuovo file"
   - Inserisci il nome
   - Conferma la creazione
4. **Gestire i file esistenti**:
   - Rinominare: Clicca sull'icona della matita
   - Eliminare: Clicca sull'icona di eliminazione
   - Scaricare: Clicca su "Download"
   - Aprire: mostra percorso, URL, dimensione e un'anteprima; i file che puoi modificare si possono anche editare
5. **Utilizzare i file nei contenuti**:
   - Copia l'URL del file
   - Nell'editor di contenuti, usa il pulsante "Inserisci immagine" o "Inserisci link"
   - Seleziona il file dalla struttura o incolla l'URL

Puoi caricare, rinominare ed eliminare solo i file che hai il permesso di modificare.

### Ricerca nel Sito
La pagina di ricerca (una sezione di tipo search) cerca le parole digitate in:
- titoli e descrizioni delle pagine
- testo delle pagine di contenuto, nella lingua corrente (nelle altre lingue solo se la pagina non ha testo nella lingua corrente)
- record delle pagine il cui modulo prevede una ricerca (dbview, news)

Le pagine nascoste sono cercate solo attraverso il loro modulo. I risultati sono raggruppati per sezione, al massimo 30 per sezione; il template di ricerca del tema può mostrare il numero di risultati di ogni sezione e, per le sezioni dbview, un link all'elenco della sezione filtrato con lo stesso testo. Una casella di ricerca senza la scelta "almeno una parola / tutte le parole / frase esatta" (ad esempio quella nel menu) cerca tutte le parole.

## Configurazione del Sistema

### Impostazioni Generali
Accedi a **Impostazioni generali → Configurazione generale** per configurare:

1. **Informazioni sito**:
   - Nome, titolo, sottotitolo e parole chiave del sito
   - URL del sito (vuoto = rilevato automaticamente)
   - Indirizzo email del sito (mittente delle email) e indirizzo email per le notifiche
2. **Pagine e link**:
   - Pagina predefinita (home page)
   - mod_rewrite di Apache, metodo dei link, metodo dei percorsi
   - Accesskey accanto ai link
3. **Utenti**:
   - Registrazione, conferma via email, email come nome utente, "ricordami", captcha nel login (vedi [Impostazioni di Registrazione](#impostazioni-di-registrazione))
4. **Funzionalità**:
   - Editor HTML, amministrazione online, crediti, fuso orario
   - Sito in manutenzione: i visitatori vedono una pagina di manutenzione, gli amministratori e il pannello di controllo continuano a funzionare
   - Cache, compressione delle pagine con gzip
   - Invia email di log all'amministratore: gli eventi di sistema scritti nel registro vengono inviati anche all'indirizzo email per le notifiche

Dopo il salvataggio la pagina mostra "I dati sono stati aggiornati correttamente". Ogni campo di testo mostra il valore predefinito come segnaposto, e il suggerimento del campo riporta il nome della variabile e il valore predefinito. Le altre pagine di impostazioni del pannello (temi, impostazioni di pagine e blocchi) funzionano allo stesso modo.

Le impostazioni che non sono nel pannello vanno in `config.vars.local.php`, nella cartella del sito:
- `$_FN['display_errors']`: "off" di default; impostalo a "on" solo mentre cerchi un errore
- `$_FN['home_section_force']`: una home page che prevale sulla pagina predefinita salvata dal pannello
- la connessione al database, le chiavi di sicurezza del login e le opzioni delle chiavi API descritte in questo manuale

### Impostazioni di Lingua
In **Impostazioni generali → Configurazione generale**:

1. **Lingue disponibili** (**lista lingue del sito**):
   - Spunta le lingue del sito
   - Ordinale con le frecce: la prima lingua della lista è quella predefinita
2. **Traduzioni**:
   - Le stringhe dell'interfaccia sono nei file `languages/<lingua>/lang.csv` di FINIS e del sito
   - I testi delle pagine si traducono pagina per pagina (vedi [Modifica dei Contenuti](#modifica-dei-contenuti))

### Gestione del Tema
Accedi a **Impostazioni generali → Aspetto** per:

1. **Selezionare il tema**:
   - Scegli tra i temi disponibili: quelli di FINIS e quelli nella cartella `themes/` del sito
   - L'anteprima sotto mostra il tema scelto, con pulsanti per diverse dimensioni dello schermo
   - Clicca "Applica questo tema" per usarlo
2. **Configurare il tema**:
   - Opzioni specifiche del tema (le impostazioni del tema)
   - Modifica dei suoi file CSS
   - Sostituzione delle sue immagini (con immagini dello stesso tipo)
3. **Creare un nuovo tema**:
   - In "Crea un nuovo tema" scegli il tema di partenza e il nuovo nome
   - Conferma: il tema viene copiato e si può modificare

Qualsiasi pagina si può vedere in anteprima con un altro tema con `index.php?themepreview=<tema>`: sempre per gli amministratori, per gli altri visitatori solo se è attivo **abilita il visitatore a cambiare il tema**. Le opzioni **tema predefinito** e **abilita il visitatore a cambiare il tema** sono in **Impostazioni generali → Configurazione generale**.

I menu sono costruiti dall'albero delle pagine: per cambiarli modifica le pagine in **Contenuti → Pagine e struttura sito** (posizione, livello, pagina nascosta nei menu).

### Cache e Performance
In **Impostazioni generali → Configurazione generale**:

1. **Gestire la cache**:
   - **abilita cache** attiva la cache
   - La cache è nella cartella `misc/_cache` e viene svuotata automaticamente quando cambiano pagine o blocchi
   - Per svuotarla a mano cancella il contenuto di `misc/_cache`
2. **Ottimizzazione**:
   - **Comprimi pagina con gzip**
   - Le pagine dbview possono tenere una copia in cache delle schede dei record per i visitatori anonimi (vedi [Pagine database](#pagine-database-modulo-dbview))

## Gestione Moduli

### Installazione dei Moduli
I moduli estendono le funzionalità di FINIS: ogni modulo è un tipo di pagina. Per installarli:

1. Ottieni il modulo (download o sviluppo personalizzato)
2. Carica la cartella del modulo nella cartella `modules/` del sito tramite FTP o il gestore file
3. Alla richiesta successiva il modulo viene registrato come nuovo tipo di pagina
4. Crea una pagina in **Contenuti → Pagine e struttura sito** e scegli il modulo come tipo di pagina

### Configurazione dei Moduli
Ogni modulo ha le proprie opzioni di configurazione:

1. Accedi a **Impostazioni generali → Pagina [modulo]: [titolo della pagina]** (o **Blocco [modulo] in "..."** per un blocco)
2. Configura le opzioni specifiche del modulo
3. Salva le modifiche

I moduli che hanno una pagina di gestione hanno anche una voce in **Contenuti** (ad esempio **Notizie in "..."**).

### Moduli Comuni e loro Configurazione

#### Modulo News
Per gestire notizie e blog:

1. Accedi a **Impostazioni generali → Pagina Notizie: [titolo della pagina]**
2. Configura:
   - News per pagina
   - Commenti (abilitati, anche da utenti non registrati, notifiche)
   - Segnalazioni, notizia precedente e successiva, notizie dello stesso argomento, immagine della notizia
   - Gruppo di utenti abilitato a pubblicare le notizie
   - Bottoni social network, icona RSS, tag, Google sitemap, cache
3. Per gestire le notizie:
   - Vai a **Contenuti → Notizie in "[titolo della pagina]"**
   - Aggiungi, modifica e pubblica le notizie
   - "Modifica gli argomenti" gestisce gli argomenti (categorie) delle notizie

#### Pagine Database (modulo dbview)
Una pagina dbview mostra i record di una tabella come elenco e come scheda, con form di ricerca, inserimento e modifica. Le sue opzioni sono in **Impostazioni generali → Pagina dbview: [titolo della pagina]**:

1. **Gruppi**:
   - **Limita la visualizzazione dei contenuti al gruppo** (`groupview`): solo questi utenti vedono i record (e la voce di menu); vuoto = tutti
   - **Limita inserimento al gruppo** (`groupinsert`): chi può aggiungere record; vuoto = ogni utente che ha fatto il login
   - **Gruppo amministratori** (`groupadmin`): chi gestisce tutti i record della pagina
2. **Per scrivere serve il login**: aggiungere, modificare ed eliminare record richiede sempre un utente collegato e con il permesso, qualunque siano i gruppi; i visitatori anonimi (e i crawler) possono solo leggere.
3. **Storico delle versioni** (opzione `enable_history`): conserva le versioni precedenti di ogni record; lo storico è visibile solo agli amministratori del sito e al gruppo amministratori della pagina.
4. **Scheda offline** (opzione `enable_offlineform`): la scheda del record offre un modulo da scaricare, compilare senza connessione e inviare per email. Le opzioni `offlineform_hidden_fields` (campi non mostrati) e `offlineform_text_fields` (campi mostrati come testo semplice) accettano nomi di campo separati da virgola; i campi file non sono mai nel modulo.
5. **Schede in cache**: per le pagine visitate da molti utenti anonimi, le schede dei record possono essere tenute in cache. Si imposta in `config.vars.local.php`:

   ```php
   $_FN['dbview_view_cache'] = array("miapagina" => 3600); // id pagina => secondi di validità (un intero = tutte le pagine dbview)
   $_FN['dbview_view_cache_keep'] = 30;                     // giorni dopo i quali i file non usati vengono rimossi
   ```

   Solo i visitatori anonimi ricevono la copia in cache. Una copia viene sostituita quando la tabella cambia ed eliminata quando il suo record viene salvato o cancellato; i file sono in `misc/_cache/pages/` e vengono rimossi insieme alla cache.

## Manutenzione e Backup

### Backup del Sistema
Il pannello di controllo non ha uno strumento di backup: copia regolarmente i file del sito.

1. Salva:
   - la cartella dati `misc/`, compresa `misc/fndatabase/` (il database con il driver su file, quello di default) e il segreto del sito `misc/secret.key.php`
   - la cartella `sections/` (contenuti delle pagine) e i temi, i moduli e i file che hai aggiunto
   - `config.vars.local.php`
   - il database MySQL, se il sito usa il driver `mysql`
2. Le cartelle `misc/_cache`, `misc/_sessions` e `misc/_login_attempts` non vanno salvate
3. Conserva multiple versioni in luoghi sicuri: il backup contiene il segreto del sito, proteggilo come una password

### Ripristino da Backup
Per ripristinare il sistema da un backup:

1. Ricopia i file nella cartella del sito
2. Ripristina il database MySQL, se usato
3. Ripristina anche `misc/secret.key.php`: senza, tutti gli utenti vengono scollegati, i client OAuth devono rifare il login e le chiavi API salvate non si possono più mostrare (le chiavi continuano a funzionare)

### Manutenzione Database
Accedi a **Utilità → Database Finis** per:

1. Vedere le tabelle del sito e il driver di ciascuna
2. Modificare la struttura di una tabella, il suo contenuto o la sua definizione XML
3. Creare una nuova tabella

Con il driver `mysql`, per ottimizzazione e riparazione usa gli strumenti del server di database.

### Aggiornamenti
Per mantenere il sistema sicuro e aggiornato:

1. Verifica la disponibilità di aggiornamenti
2. Effettua un backup completo prima di aggiornare
3. Se vuoi, attiva **Sito in manutenzione** in **Impostazioni generali → Configurazione generale**
4. Sostituisci i sorgenti di FINIS con la nuova versione, poi segui la [guida alla migrazione](it_guida_migrazione.md) per le modifiche che richiedono un intervento

## Strumenti Avanzati

### Editor di Temi
Per personalizzare l'aspetto del sito:

1. Accedi a **Impostazioni generali → Aspetto**
2. Seleziona il tema e il file da modificare (i file CSS; struttura e template del tema per utenti esperti)
3. Apporta le modifiche al codice
4. Salva i cambiamenti
5. Verifica che le modifiche non causino problemi

### Log di Sistema
Per monitorare eventi e diagnosticare problemi:

1. Accedi a **Utilità → Registro eventi**
2. Visualizza gli eventi, dal più recente: data, contesto, utente, indirizzo IP e testo. Tra gli altri:
   - Errori e avvisi di sistema scritti da FINIS
   - Blocchi del login e richieste rifiutate del codice di registrazione
   - Modifiche alla configurazione
   - Chiavi API mostrate dagli amministratori e modifiche fatte tramite il server MCP (contesti `apikey` e `mcp`)
3. Filtra per contesto, utente, testo dell'evento o intervallo di date
4. Gli eventi scritti dalle versioni precedenti di FINIS (file mensili in `misc/log/`) sono elencati sotto "Archivio", con un pulsante per mese

### Cron Jobs
Per gestire attività automatizzate accedi a **Utilità → Operazioni pianificate**:

1. **Operazioni**: ogni operazione ha:
   - **operation**: il nome di una funzione PHP, un URL completo, o un URL locale che inizia con `?` (ad esempio `?fnapp=...`)
   - **cron**: una pianificazione per riga, nella forma `anno-mese-giorno ora:minuto:secondo giorno_della_settimana`, dove ogni parte è un numero, `*` (qualsiasi) o `*/n` (ogni n); giorno della settimana 0 = domenica. Il testo dopo `#` è un commento. La lista mostra la pianificazione a parole
   - **last_execution**: data dell'ultima esecuzione
2. Esempi:
   - `*-*-* 04:00:00 1`: ogni lunedì alle 4:00
   - `*-*-* *:*/5:00 *`: ogni 5 minuti
   - `*-*-1 00:00:00 *`: il primo giorno di ogni mese a mezzanotte
3. **Servizio cron**: la pagina avvia il servizio `index.php?fnapp=cron` in un riquadro; il servizio resta attivo riavviando da solo la propria richiesta, e ne gira una sola istanza alla volta. Su un server, richiama periodicamente quell'indirizzo (ad esempio con `wget` o `curl` dal crontab di sistema) per essere sicuro che sia attivo
4. **Log**: sotto la lista sono mostrate le ultime 100 righe di `misc/log/cron.log`, con un link per scaricare il file

### Altre Utilità
- **Utilità → Utenti pannello di controllo**: dà agli utenti che non sono amministratori l'accesso ad alcune pagine del pannello (vedi [Sistema di Permessi](#sistema-di-permessi))
- **Utilità → Invio mail**: invia un'email di prova (mittente, destinatario, oggetto, testo), per verificare la configurazione della posta del server
- **Utilità → Informazioni server**: la configurazione PHP del server (`phpinfo`)

## Integrazione con Servizi Esterni

### Plugin
Il gruppo **Plugins** del pannello elenca i plugin del sito (cartella `plugins/`) che hanno una pagina di impostazioni. Tra i plugin inclusi in FINIS:
- **Header append**: codice HTML aggiunto nell'`<head>` di ogni pagina. Incolla qui il codice di tracking di Google Analytics o di un altro servizio di statistiche
- **Google sitemap**: crea il file `sitemap.xml` del sito; viene aggiornato anche quando cambiano le pagine

### Social Media
Il modulo news ha le sue opzioni per i bottoni dei social network (vedi [Modulo News](#modulo-news)).

## Risoluzione Problemi

### Problemi Comuni e Soluzioni

#### Errori di Login
- **Problema**: Non riesci ad accedere con le credenziali corrette
- **Soluzione**:
  1. Verifica CAPS LOCK
  2. Aspetta `login_lockout_minutes` minuti (15 di default): dopo troppi tentativi il login è rifiutato anche con la password giusta. Cerca "locked out" in **Utilità → Registro eventi**; per sbloccare subito cancella la cartella `misc/_login_attempts`
  3. Controlla in **Utenti e gruppi → Utenti** che l'account sia abilitato
  4. Richiedi una nuova password, oppure impostane una nuova nel form dell'utente
  5. Dietro un reverse proxy, imposta `trusted_proxies` (vedi [Sicurezza del Login](#sicurezza-del-login))

#### Tutti gli Utenti Scollegati
- **Problema**: tutti gli utenti devono rifare il login, i client MCP collegati con OAuth devono autenticarsi di nuovo
- **Soluzione**:
  1. Controlla che `misc/secret.key.php` esista, sia leggibile dal web server e non sia stato sostituito (ad esempio da un ripristino o da una nuova installazione della cartella dati)
  2. Se è andato perso, ripristinalo da un backup; altrimenti il nuovo segreto creato automaticamente vale da ora in poi e agli utenti basta rifare il login

#### "Servizio momentaneamente non disponibile" (503)
- **Problema**: il sito mostra "Servizio momentaneamente non disponibile: il database è occupato" e ricarica la pagina ogni 10 secondi
- **Soluzione**:
  1. Il server MySQL ha rifiutato la connessione per circa 5 secondi (troppe connessioni, ad esempio il limite `max_user_connections` di un hosting condiviso) oppure non è raggiungibile. Gli utenti non vengono scollegati: la pagina torna a funzionare appena il server accetta connessioni
  2. Cerca "xmetadb: MySQL connection failed" nel log degli errori di PHP
  3. Se succede spesso, aumenta i limiti di connessione del server di database o riduci il traffico (cache, schede dbview in cache)

#### Pagina Bianca o Errore 500
- **Problema**: Il sito mostra una pagina bianca o errore del server
- **Soluzione**:
  1. Imposta `$_FN['display_errors'] = "on";` in `config.vars.local.php` mentre cerchi l'errore, e rimettilo a "off" dopo (il default: gli errori non vengono mostrati ai visitatori)
  2. Controlla i log PHP sul server e **Utilità → Registro eventi**
  3. Verifica permessi dei file (la cartella `misc/` deve essere scrivibile)
  4. Disattiva i moduli o i plugin installati di recente

#### Problemi con Upload File
- **Problema**: Non riesci a caricare file
- **Soluzione**:
  1. Verifica i permessi della cartella di destinazione
  2. Controlla i limiti di dimensione file nel php.ini
  3. Verifica che il tipo di file sia consentito (i file eseguibili come `.php` sono sempre rifiutati)
  4. Prova a caricare file più piccoli

### Contattare il Supporto
Se non riesci a risolvere un problema:

1. Raccogli informazioni dettagliate:
   - Versione di FINIS
   - Descrizione precisa del problema
   - Screenshot se applicabile
   - Eventi del Registro eventi e log degli errori di PHP
   - Azioni che hanno preceduto il problema
2. Contatta il supporto attraverso i canali ufficiali

## Best Practices

### Sicurezza
- Mantieni FINIS e tutti i moduli aggiornati
- Usa password complesse e cambiale regolarmente
- Limita l'accesso amministrativo a IP conosciuti se possibile
- Effettua backup regolari, compreso `misc/secret.key.php`, e custodiscili come una password
- Controlla regolarmente il Registro eventi per attività sospette (blocchi del login, chiavi mostrate)
- Consenti gli strumenti di scrittura MCP solo agli utenti che ne hanno bisogno
- Su un sito pubblico tieni `display_errors` a "off"

### Performance
- Attiva la cache
- Ottimizza le immagini prima di caricarle
- Limita il numero di moduli attivi
- Pulisci regolarmente database e file temporanei
- Considera l'utilizzo di un CDN per risorse statiche

### Gestione Contenuti
- Organizza i contenuti con una struttura logica
- Usa tag e categorie per facilitare la navigazione
- Mantieni i nomi dei file descrittivi e senza spazi
- Crea un calendario editoriale per aggiornamenti regolari
- Rivedi regolarmente i contenuti vecchi per verificarne l'attualità
