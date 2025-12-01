# Manuale di Amministrazione FINIS Framework

## Introduzione
Questo manuale è rivolto agli amministratori di siti web basati su FINIS. Fornisce istruzioni dettagliate sull'utilizzo del pannello di controllo, la gestione di utenti, contenuti, e la configurazione del sistema.

## Accesso al Pannello di Controllo

### Login Amministratore
1. Accedi all'URL del tuo sito
2. Clicca sul link "Login" o accedi direttamente a `http://tuosito.com/?mod=login`
3. Inserisci le credenziali di amministratore configurate durante l'installazione
4. Dopo il login, accedi al pannello di controllo tramite `http://tuosito.com/?mod=controlcenter`

### Interfaccia del Pannello di Controllo
Il pannello di controllo è organizzato in diverse sezioni:

- **Dashboard**: Panoramica generale del sito
- **Contenuti**: Gestione di sezioni, blocchi e file
- **Utenti e Gruppi**: Gestione utenti, gruppi e permessi
- **Impostazioni**: Configurazione generale del sito
- **Strumenti**: Utility di sistema e manutenzione
- **Moduli**: Configurazione dei moduli installati

## Gestione Utenti e Gruppi

### Gestione Utenti
Accedi a **Utenti e Gruppi → Utenti** per:

1. **Visualizzare utenti**: Lista di tutti gli utenti registrati
2. **Creare un nuovo utente**:
   - Clicca su "Aggiungi nuovo"
   - Compila i campi richiesti (username, password, email)
   - Seleziona i gruppi di appartenenza
   - Imposta lo stato dell'account (attivo/disattivato)
   - Salva le modifiche
3. **Modificare un utente esistente**:
   - Clicca sull'icona di modifica accanto all'utente
   - Aggiorna i dati necessari
   - Salva le modifiche
4. **Eliminare un utente**:
   - Clicca sull'icona di eliminazione
   - Conferma l'operazione

### Gestione Gruppi
Accedi a **Utenti e Gruppi → Gruppi** per:

1. **Visualizzare gruppi**: Lista di tutti i gruppi esistenti
2. **Creare un nuovo gruppo**:
   - Clicca su "Aggiungi nuovo"
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

### Sistema di Permessi
I permessi in FINIS sono basati sui gruppi:

1. **Permessi per Sezioni**:
   - Accedi a **Contenuti → Sezioni**
   - Seleziona una sezione
   - Nella scheda "Permessi", definisci:
     - Gruppi con permesso di visualizzazione
     - Gruppi con permesso di modifica
2. **Permessi per Moduli**:
   - Ogni modulo può definire i propri permessi
   - Generalmente configurabili nelle impostazioni del modulo

### Impostazioni di Registrazione
Per configurare il processo di registrazione:

1. Accedi a **Impostazioni → Generale**
2. Configura le opzioni:
   - Abilitare registrazioni
   - Richiedere conferma email
   - Gruppo predefinito per nuovi utenti
   - Termini e condizioni
3. Personalizza le email inviate agli utenti in **Impostazioni → Email**

## Gestione dei Contenuti

### Struttura del Sito
Il sito è organizzato in **sezioni**. Ogni sezione può essere:
- Una pagina di contenuto
- Un modulo specifico (news, login, ecc.)
- Una sottosezione di un'altra sezione

### Gestione Sezioni
Accedi a **Contenuti → Sezioni** per:

1. **Visualizzare la struttura del sito**: Rappresentata come un albero gerarchico
2. **Aggiungere una nuova sezione**:
   - Clicca su "Aggiungi nuovo"
   - Compila il form:
     - **ID**: Identificativo unico (usato nell'URL)
     - **Titolo**: Nome visualizzato nel menu e nei titoli
     - **Tipo**: Scegli il tipo di sezione (html, notizie, login, ecc.)
     - **Posizione**: Posizione nell'albero delle sezioni
     - **Permessi**: Gruppi con accesso alla sezione
     - **Stato**: Pubblicato, nascosto o bozza
   - Salva la sezione
3. **Modificare una sezione esistente**:
   - Clicca sull'icona di modifica
   - Aggiorna i parametri necessari
   - Salva le modifiche
4. **Riordinare le sezioni**:
   - Trascina le sezioni nella posizione desiderata usando drag-and-drop
   - Salva l'ordine
5. **Eliminare una sezione**:
   - Clicca sull'icona di eliminazione
   - Conferma l'operazione

### Modifica dei Contenuti
Per modificare il contenuto di una sezione:

1. Dopo aver creato o selezionato una sezione, vai alla tab "Contenuti"
2. Se è una sezione di tipo standard:
   - Usa l'editor WYSIWYG per modificare il contenuto
   - Inserisci testo, immagini e formattazione
   - Crea versioni in diverse lingue selezionando la lingua dalla dropdown
3. Se è un tipo di sezione specifico (news, modulo personalizzato, ecc.):
   - L'interfaccia di modifica cambierà in base al tipo
   - Segui le istruzioni specifiche per quel tipo di contenuto
4. Salva le modifiche

### Gestione Blocchi
I blocchi sono contenuti secondari visualizzati in posizioni specifiche (barre laterali, intestazione, ecc.). Accedi a **Contenuti → Blocchi** per:

1. **Visualizzare blocchi esistenti**: Lista di tutti i blocchi configurati
2. **Creare un nuovo blocco**:
   - Clicca su "Aggiungi nuovo"
   - Compila il form:
     - **Titolo**: Nome visualizzato nell'intestazione del blocco
     - **Tipo**: HTML, menu, login, ecc.
     - **Posizione**: Sinistra, destra, alto, basso
     - **Visibilità**: Sezioni in cui il blocco sarà visibile
     - **Permessi**: Gruppi che possono vedere il blocco
     - **Stato**: Pubblicato o disabilitato
   - Salva il blocco
3. **Modificare un blocco**:
   - Clicca sull'icona di modifica
   - Aggiorna i parametri
   - Salva le modifiche
4. **Riordinare i blocchi**:
   - Trascina i blocchi nella posizione desiderata
   - Salva l'ordine
5. **Eliminare un blocco**:
   - Clicca sull'icona di eliminazione
   - Conferma l'operazione

### Gestione File e Media
Accedi a **Contenuti → File** per gestire i file multimediali:

1. **Esplorare file e cartelle**: Naviga attraverso la struttura delle cartelle
2. **Caricare nuovi file**:
   - Seleziona la cartella di destinazione
   - Clicca su "Carica file"
   - Seleziona i file dal tuo computer
   - Conferma l'upload
3. **Creare nuove cartelle**:
   - Clicca su "Nuova cartella"
   - Inserisci il nome
   - Conferma la creazione
4. **Gestire i file esistenti**:
   - Rinominare: Clicca sull'icona di modifica
   - Eliminare: Clicca sull'icona di eliminazione
   - Spostare: Trascina i file nelle cartelle
5. **Utilizzare i file nei contenuti**:
   - Copia l'URL del file
   - Nell'editor di contenuti, usa il pulsante "Inserisci immagine" o "Inserisci link"
   - Seleziona il file dalla struttura o incolla l'URL

## Configurazione del Sistema

### Impostazioni Generali
Accedi a **Impostazioni → Generale** per configurare:

1. **Informazioni sito**:
   - Nome del sito
   - Descrizione
   - Email di amministrazione
   - Logo del sito
2. **Impostazioni SEO**:
   - Meta tag predefiniti
   - Robots.txt
   - Sitemap XML
3. **Funzionalità**:
   - Abilitare/disabilitare funzioni specifiche
   - Impostare timeouts e limiti
4. **Debug e Log**:
   - Livello di log
   - Visualizzazione errori

### Impostazioni di Lingua
Accedi a **Impostazioni → Lingue** per configurare:

1. **Lingue disponibili**:
   - Attivare/disattivare lingue
   - Impostare lingua predefinita
2. **Traduzioni**:
   - Modificare stringhe di traduzione
   - Importare/esportare file di lingua

### Gestione del Tema
Accedi a **Impostazioni → Aspetto** per:

1. **Selezionare il tema**:
   - Scegli tra i temi disponibili
   - Visualizza anteprima
2. **Configurare il tema attivo**:
   - Opzioni specifiche del tema
   - Colori, font e layout
3. **Gestire menu**:
   - Creare/modificare voci di menu
   - Impostare ordine e gerarchia

### Cache e Performance
Accedi a **Strumenti → Cache** per:

1. **Gestire la cache**:
   - Visualizzare stato cache
   - Svuotare cache
   - Configurare impostazioni cache
2. **Ottimizzazione**:
   - Compressione output
   - Minificazione CSS/JS
   - Caching browser

## Gestione Moduli

### Installazione dei Moduli
I moduli estendono le funzionalità di FINIS. Per installarli:

1. Ottieni il modulo (download o sviluppo personalizzato)
2. Carica i file nella cartella `modules/` tramite FTP o il gestore file
3. Accedi a **Moduli → Gestione**
4. Il nuovo modulo dovrebbe apparire nella lista
5. Clicca su "Attiva" per renderlo disponibile

### Configurazione dei Moduli
Ogni modulo ha le proprie opzioni di configurazione:

1. Accedi a **Moduli → [Nome Modulo]**
2. Configura le opzioni specifiche del modulo
3. Salva le modifiche

### Moduli Comuni e loro Configurazione

#### Modulo News
Per gestire notizie e blog:

1. Accedi a **Moduli → News**
2. Configura:
   - Numero di notizie per pagina
   - Formato data
   - Opzioni commenti
   - Categorie di notizie
3. Per aggiungere una nuova notizia:
   - Vai a **Contenuti → Notizie**
   - Clicca su "Aggiungi nuovo"
   - Compila titolo, testo, categorie e data
   - Salva la notizia

#### Modulo Form
Per creare moduli di contatto personalizzati:

1. Accedi a **Moduli → Moduli**
2. Per creare un nuovo form:
   - Clicca su "Aggiungi nuovo"
   - Aggiungi campi (testo, email, checkbox, ecc.)
   - Configura email di notifica
   - Salva il form
3. Per visualizzare le risposte:
   - Seleziona il form
   - Vai alla tab "Risposte"
   - Esporta dati se necessario

## Manutenzione e Backup

### Backup del Sistema
È essenziale effettuare backup regolari:

1. Accedi a **Strumenti → Backup**
2. Opzioni di backup:
   - Backup completo (file + database)
   - Solo database
   - Solo file
3. Scarica il file di backup sul tuo computer
4. Conserva multiple versioni in luoghi sicuri

### Ripristino da Backup
Per ripristinare il sistema da un backup:

1. Accedi a **Strumenti → Backup**
2. Seleziona "Ripristina backup"
3. Carica il file di backup
4. Segui le istruzioni per completare il ripristino

### Manutenzione Database
Per ottimizzare il database:

1. Accedi a **Strumenti → Database**
2. Funzioni disponibili:
   - Ottimizzazione tabelle
   - Riparazione tabelle
   - Verifica integrità

### Aggiornamenti
Per mantenere il sistema sicuro e aggiornato:

1. Verifica la disponibilità di aggiornamenti
2. Effettua un backup completo prima di aggiornare
3. Accedi a **Strumenti → Aggiornamenti**
4. Segui le istruzioni per aggiornare il sistema

## Strumenti Avanzati

### Editor di Temi
Per personalizzare l'aspetto del sito:

1. Accedi a **Strumenti → Editor Temi**
2. Seleziona il file da modificare
3. Apporta le modifiche al codice
4. Salva i cambiamenti
5. Verifica che le modifiche non causino problemi

### Log di Sistema
Per monitorare eventi e diagnosticare problemi:

1. Accedi a **Strumenti → Log**
2. Visualizza:
   - Accessi utenti
   - Errori di sistema
   - Azioni amministrative
3. Filtra per tipo, data o utente
4. Esporta log per analisi esterne

### Cron Jobs
Per gestire attività automatizzate:

1. Accedi a **Strumenti → Cron**
2. Funzioni disponibili:
   - Visualizzare job programmati
   - Aggiungere nuovi job
   - Modificare frequenza di esecuzione
   - Eseguire job manualmente
   - Visualizzare log di esecuzione

## Integrazione con Servizi Esterni

### Google Analytics
Per tracciare le visite al sito:

1. Accedi a **Impostazioni → Integrazioni**
2. Nella sezione Google Analytics:
   - Inserisci il codice di tracking
   - Configura opzioni di privacy
   - Seleziona pagine da escludere dal tracking

### Social Media
Per integrare i social media:

1. Accedi a **Impostazioni → Integrazioni → Social Media**
2. Configura:
   - Pulsanti di condivisione
   - Profili social del sito
   - Feed social da visualizzare

### Newsletter
Per gestire iscrizioni a newsletter:

1. Accedi a **Moduli → Newsletter**
2. Configura:
   - Servizio di newsletter (interno o esterno)
   - Template email
   - Liste di distribuzione
   - Form di iscrizione

## Risoluzione Problemi

### Problemi Comuni e Soluzioni

#### Errori di Login
- **Problema**: Non riesci ad accedere con le credenziali corrette
- **Soluzione**: 
  1. Verifica CAPS LOCK
  2. Richiedi una nuova password
  3. Controlla nelle impostazioni che il tuo account non sia bloccato
  4. Se niente funziona, accedi al database e resetta la password manualmente

#### Pagina Bianca o Errore 500
- **Problema**: Il sito mostra una pagina bianca o errore del server
- **Soluzione**:
  1. Attiva la visualizzazione degli errori nel file `config.vars.local.php`
  2. Controlla i log PHP sul server
  3. Verifica permessi dei file
  4. Disattiva i plugin recentemente installati

#### Problemi con Upload File
- **Problema**: Non riesci a caricare file
- **Soluzione**:
  1. Verifica i permessi della cartella di destinazione
  2. Controlla i limiti di dimensione file nel php.ini
  3. Verifica che il tipo di file sia consentito
  4. Prova a caricare file più piccoli

### Contattare il Supporto
Se non riesci a risolvere un problema:

1. Raccogli informazioni dettagliate:
   - Versione di FINIS
   - Descrizione precisa del problema
   - Screenshot se applicabile
   - Log di sistema
   - Azioni che hanno preceduto il problema
2. Contatta il supporto attraverso i canali ufficiali

## Best Practices

### Sicurezza
- Mantieni FINIS e tutti i moduli aggiornati
- Usa password complesse e cambiale regolarmente
- Limita l'accesso amministrativo a IP conosciuti se possibile
- Effettua backup regolari
- Controlla regolarmente i log per attività sospette

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