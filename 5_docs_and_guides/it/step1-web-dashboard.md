# Step 1: installazione cruscotto web e database (MySQL / MariaDB)

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step1.html](http://newfoobot-restart.barbaralerici.net/en/step1.html)

---

    > [!NOTE]
> **Summary & Goal of Step 1**
> In questa prima fase &egrave; necessario predisporre un proprio hosting o server web (anche gratuito con supporto PHP e database relazionale MySQL o MariaDB) per ospitare la piattaforma di raccolta dati. &Egrave; fondamentale installare il cruscotto web prima di procedere con gli altri componenti: solo cos&igrave; potrai identificare l'indirizzo IP del server e generare la tua chiave di sicurezza API, indispensabili per le fasi successive. Per semplificare l'operazione, il progetto NewFoobot mette a disposizione un pacchetto pronto all'uso con procedura guidata d'installazione.

    

## 1. Disponi già di un hosting o server web personale?

Prima di iniziare, occorre verificare se possiedi già uno spazio web (hosting condiviso, VPS o server casalingo) e se hai un minimo di familiarità con la sua gestione:

#### ✓ Hai già un hosting personale?

Se hai già un dominio o spazio web attivo (es. SiteGround, Aruba, ServerPlan, Hetzner, cPanel/Plesk), ti basterà creare una cartella o sottodominio e un database dedicato (MySQL o MariaDB), per poi passare subito al [caricamento file](#caricamento-file).

#### &plus; Non hai un hosting?

Non è necessario acquistare subito un piano a pagamento. Esistono diversi provider che offrono **hosting gratuiti di prova** con supporto PHP e database relazionale (MySQL o MariaDB). Sebbene presentino limitazioni di banda o risorse rispetto ai server commerciali, per il volume dati leggero del Foobot risultano ampiamente sufficienti.

    

**I migliori servizi di hosting gratuiti (PHP e MySQL / MariaDB)**

            Se non disponi di un server proprietario, di seguito elenchiamo le soluzioni gratuite più affidabili a livello internazionale e in Italia adatte per installare il cruscotto PHP e il database relazionale (MySQL o MariaDB):

          

Scorri orizzontalmente per visualizzare l'intera tabella

| Servizio Hosting | Ambito | Caratteristiche & Risorse Gratuite | Collegamento |
| --- | --- | --- | --- |
| **Netsons (Free)** | Italia | Spazio web gratuito con pannello professionale cPanel, supporto PHP moderno, 1 database MySQL/MariaDB e sottodominio di terzo livello. Ottimo per test in ambiente italiano pulito. | [netsons.com →](https://www.netsons.com) |
| **Altervista** | Italia | Piattaforma italiana storica con hosting classico (gestione file via FTP o pannello), supporto completo a PHP e database MySQL senza limiti rigidi di traffico. | [altervista.org →](https://it.altervista.org) |
| **InfinityFree** | Internazionale | Tra i più utilizzati al mondo: 5 GB di spazio disco, traffico illimitato, supporto a PHP 8.2/8.3, database MySQL, certificato SSL gratuito Let's Encrypt e nessuna pubblicità forzata sul sito. | [infinityfree.com →](https://www.infinityfree.com) |
| **ByetHost** | Internazionale | Infrastruttura solida con 5 GB di storage, database MySQL illimitati, supporto PHP e pannello di controllo cPanel/VistaPanel con installatore script. | [byet.host →](https://byet.host) |
| **GoogieHost** | Internazionale | Hosting gratuito basato su web server veloci LiteSpeed, 1 GB di storage SSD NVMe, database MySQL, supporto PHP e certificato SSL gratuito incluso. | [googiehost.com →](https://googiehost.com) |

## 2. Requisiti tecnici dello spazio web

      Qualunque sia il provider scelto (a pagamento o gratuito), assicurati che rispetti questi requisiti minimi:

- **PHP**: versione 7.4, 8.0, 8.1, 8.2 o successive con estensioni `pdo_mysql`, `json`, `mbstring` e `openssl` attive.
- **Database Relazionale**: 1 database basato su motore **MySQL** (versione 5.7 o superiore) oppure **MariaDB** (versione 10.3 o superiore), con un utente associato dotato di permessi completi.

      Come verificare le estensioni PHP nel pannello cPanel:

1. Accedi al tuo **cPanel** e cerca la voce **Select PHP Version** (oppure *Gestione MultiPHP / Estensioni PHP*) nella sezione *Software*.
2. Clicca sulla scheda **Extensions**: troverai l'elenco alfabetico di tutti i moduli disponibili con una casella di spunta accanto.
3. Verifica che `pdo_mysql`, `json`, `mbstring` e `openssl` abbiano la spunta attiva. Se manca una spunta, selezionala per abilitarla all'istante senza riavvii.
4. *In alternativa rapida:* puoi caricare temporaneamente nella cartella del sito un file `info.php` contenente `<?php phpinfo(); ?>`, aprirlo dal browser e usare `Ctrl + F` per verificare i moduli (ricordati di eliminarlo subito dopo per sicurezza).

    

## 3. Fasi di predisposizione hosting e creazione database

Prima di trasferire i file applicativi, occorre predisporre l'indirizzo web e creare la base dati sul server:

      

#### Fase 1: configurare dominio o sottodominio

Puoi utilizzare il **dominio/sottodominio gratuito** assegnato dal provider (es. `tuonome.netsons.org` oppure `tuonome.infinityfreeapp.com`) o configurare un dominio/sottodominio dedicato di tua propriet&agrave; (es. `foobot.tuodominio.it`) puntandolo alla cartella di destinazione.

      

#### Fase 2: creare il database (MySQL o MariaDB) e utente con permessi completi

Accedi al pannello di controllo dell'hosting (cPanel, Plesk o gestione autonoma del provider) e apri la sezione di gestione database (solitamente denominata **Database MySQL**, **MariaDB** o *Creazione guidata database*):

1. Crea un nuovo database (es. `foobot_db`).
2. Crea un nuovo utente database associando una password robusta (es. `foobot_user`).
3. Associa l'utente al database creato e assegna **Tutti i permessi** (*ALL PRIVILEGES*: CREATE, INSERT, UPDATE, DELETE, SELECT, ALTER, DROP, ecc.).

          Annota e conserva questi 4 parametri per la procedura di installazione:
          Host: localhost (o indirizzo fornito dal provider) | Nome DB | Nome Utente | Password

    

## 4. Caricamento dei file (FTP o file manager)

Ora che lo spazio web e il database sono pronti, occorre prelevare il pacchetto del cruscotto web e trasferirlo sul server.

    

#### Come procurarsi il pacchetto del cruscotto web

Hai a disposizione due modalit&agrave; per ottenere i file di installazione:

        

          Opzione A • Singolo Modulo da GitHub

Puoi visualizzare e prelevare la sola cartella `3_web_dashboard_and_database` direttamente dal repository ufficiale:

          Visualizza su GitHub →

            (Su GitHub: apri la cartella del modulo, oppure usa strumenti come DownGit inserendo l'URL per scaricarla singolarmente in archivio ZIP).

        

          Opzione B • Archivio Completo NewFoobot

Scarica l'intero archivio ZIP di progetto dalla homepage e individua la cartella `3_web_dashboard_and_database/`:

          Scarica ZIP Completo (.zip)

Estrai i file contenuti in `3_web_dashboard_and_database` sul tuo computer e caricali sul server via **FTP (es. con FileZilla)** o tramite il **File Manager** del tuo pannello:

- Se vuoi che sia la pagina principale del dominio: carica tutti i file direttamente nella cartella root (tipicamente `public_html/` o `htdocs/`).
- Se preferisci un sottodominio o una sottocartella dedicata: caricali all'interno del percorso configurato (es. `public_html/foobot/`, accessibile poi da `https://tuodominio.com/foobot/`).

      Nota di Sicurezza: I file .htaccess e robots.txt gi&agrave; inclusi nel pacchetto proteggono automaticamente l'accesso ai file di backup e impediscono l'indicizzazione dei dati privati sui motori di ricerca.

    

## 5. Procedura guidata via browser (URL /install.php)

Una volta concluso il caricamento dei file sul server, apri il browser e naviga all'indirizzo del tuo dominio seguito da `/install.php` (oppure `/cartella/install.php` se hai scelto una sottocartella):

    https://tuo-dominio.com/install.php

La procedura guidata si divide in due passaggi immediati:

1. **Connessione Database**: inserisci i dati del database (MySQL o MariaDB) salvati in precedenza (Nome Host solitamente `localhost`, Nome Database, Nome Utente e Password). L'installer verificher&agrave; la connessione e generer&agrave; in automatico tutte le tabelle relazionali necessarie partendo dallo schema SQL (pienamente compatibile con motori MySQL e MariaDB).
2. **Creazione Account Amministratore, Lingua & Passkey API**:                  Il cruscotto &egrave; strutturato per funzionare esclusivamente con **accesso utente autenticato e riservato**. In questa schermata configuri il primo utente Amministratore (username e password di accesso), selezioni la **lingua predefinita del cruscotto** tra le 5 disponibili (*English &mdash; default*, *Italiano*, *Deutsch*, *Espa&ntilde;ol*, *Fran&ccedil;ais*) e definisci la **Passkey API segreta** (es. `MiaPasskeyFoobot2026!`).                    **Annota le credenziali di accesso e la Passkey:** La username e password ti serviranno per effettuare il login al cruscotto; la Passkey API sar&agrave; richiesta dal Server Bridge (Step 2) per inoltrare i dati in forma autenticata. Successivamente, all'interno del cruscotto potrai modificare la lingua, i dati o creare ulteriori utenti.

    

## 6. Accesso al pannello di controllo e verifica

Completata l'installazione, il sistema rimuove il blocco di setup e reindirizza alla pagina di login. Accedendo con il tuo dominio (es. `https://tuo-dominio.com/`), avrai a disposizione il pannello completo del cruscotto pronto per visualizzare le metriche.

Per verificare lo stato delle tabelle e la consistenza della configurazione, puoi visitare in qualsiasi momento lo script diagnostico:

    https://tuo-dominio.com/check_db.php

    

## 7. Come tradurre il sito nella propria lingua (modulo i18n)

Il sistema web include nativamente 5 lingue ufficiali (Inglese, Italiano, Tedesco, Spagnolo, Francese). Grazie all'architettura dinamica del motore di internazionalizzazione (`i18n.php`), puoi aggiungere qualsiasi altra lingua o personalizzare i testi esistenti senza modificare una sola riga di codice PHP.

#### Procedura operativa di traduzione:

1. **Percorso e file modello:**           accedi via FTP o tramite il *File Manager* di cPanel alla cartella `languages/` del sito. Troverai il file modello `template.json` (contenente tutte le chiavi del sistema predisposte e vuote) oppure puoi duplicare direttamente `en.json` o `it.json` come base di partenza.
2. **Convenzione del nome file (ISO 639-1):**           salva il nuovo file assegnandogli il codice standard a 2 lettere minuscole corrispondente alla lingua desiderata. Ad esempio:            - `pt.json` per il Portoghese - `nl.json` per l'Olandese - `pl.json` per il Polacco - `el.json` per il Greco
3. **Regole di compilazione del file JSON:**           apri il file con un editor di testo (es. Blocco Note, VS Code). Modifica esclusivamente i testi posti a destra dei due punti tra virgolette, lasciando intatti:            - I nomi delle chiavi a sinistra dei due punti (es. `"nav_dashboard": "..."`). - I segnaposto dinamici racchiusi tra parentesi graffe (es. `{user}`, `{time}`, `{count}`), che il sistema valorizza a runtime. - Eventuali tag HTML formattatori presenti nei testi (come ``, ``).
4. **Rilevamento istantaneo e fallback di sicurezza:**           il modulo `i18n.php` effettua una scansione dinamica della cartella `languages/`. Non appena carichi il nuovo file `.json` sul server:            - La nuova lingua compare **all'istante** nel selettore di lingua della barra di navigazione e nelle preferenze profilo di tutti gli utenti. - Se alcune chiavi non sono state tradotte o vengono omesse, il motore applica in automatico il valore di riserva (**fallback**) in Inglese o Italiano, garantendo che nessuna schermata rimanga priva di testo.

#### Traduzione dei file lingua dell'App PWA & Caching Offline:

Oltre al cruscotto web, anche l'**App PWA** supporta l'aggiunta dinamica di lingue personalizzate con un sistema avanzato di rilevamento, download diretto e memorizzazione offline nel dispositivo:

- **Rilevamento automatico nella cartella PWA:**           l'endpoint `api/i18n.php` del server scansiona prioritariamente la cartella `/app/languages/`. Se inserisci un nuovo file lingua (ad esempio `app/languages/pt.json`), l'endpoint lo rileva immediatamente e lo invia all'App con l'etichetta estesa (es. *Português (PT)*).
- **Download diretto e caching locale nel dispositivo:**           la PWA scarica direttamente il file statico `./languages/{code}.json` dalla propria cartella. Il file scaricato viene memorizzato in `localStorage` con la chiave `nf_pwa_cached_lang_{code}`. Da quel momento, anche chiudendo l'app o usandola in mobilità senza connessione a internet, la nuova lingua è sempre disponibile e salvata nello smartphone.
- **Pre-Cache offline nel Service Worker:**           i file JSON della cartella `./languages/*.json` sono registrati nella lista `STATIC_ASSETS` del Service Worker (`sw.js`). In questo modo, anche i file fisici di traduzione vengono salvati preventivamente nella *Cache Storage* del browser, garantendo il cambio lingua offline istantaneo.

    

      ← Torna all'Indice Guida
      Vai allo Step 2: server Bridge →

---

## Guide Navigation

- ➡️ **Next:** [Step 2](step2.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
