# Step 2: configurazione Server Bridge MQTT

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step2.html](http://newfoobot-restart.barbaralerici.net/en/step2.html)

---

    > [!NOTE]
> **Summary & Goal of Step 2**
> In questa seconda fase predisponiamo un server online (es. una VPS gratuita come Oracle Cloud) oppure un computer locale sempre acceso su cui eseguire un programma in Python. Questo script ha il compito di ricevere i dati inviati dal sensore sotto forma di pacchetti compatti in formato JSON: li riceve, li decodifica e li inoltra in modo sicuro al nostro sito web. Al termine dell'avvio annoteremo l'**indirizzo IP** di questa macchina, che servir&agrave; nel successivo Step 3 per la regola DNS.

## 1. Perché è necessario un Server Bridge dedicato?

Il firmware originale del Foobot implementa una versione datata del protocollo MQTT (MQIsdp / MQTT v3.1) che i broker pubblici moderni e i comuni hosting web condivisi rifiutano. Lo script `bridge.py` risolve questo problema aprendo un server socket nativo sulla porta standard **TCP 1883**:

- Accetta l'handshake e l'autenticazione del Foobot senza richiedere certificati cloud Airboxlab.
- Decodifica i pacchetti binari di telemetria e calcola in tempo reale i valori fisici calibrati (PM2.5, VOC in ppb, CO2 stimata e indice allpollu).
- Inoltra ogni lettura via chiamata HTTP/HTTPS POST protetta verso l'endpoint `api/ingest.php` del tuo cruscotto web configurato nello Step 1.
- Gestisce il watchdog intelligente *Zero Ping* per avvisarti solo in caso di reale prolungata disconnessione o blackout.

## 2. Dove ospitare il Bridge?

Puoi scegliere liberamente tra due architetture di esecuzione:

      

### Opzione A: cloud gratuito Always Free

          Consigliata • Nessun dispositivo acceso a casa

Consiste nell'eseguire il Bridge su una macchina virtuale (VPS) Linux (es. Ubuntu) con indirizzo IP pubblico statico. Rimane attiva 24 ore su 24 nel cloud in modo indipendente, senza consumare corrente a casa. Tra i provider affidabili che offrono istanze gratuite per sempre o piani di prova gratuiti:

          

              Oracle Cloud (Always Free) →

                Soluzione di riferimento consigliata per la guida. Include fino a 2 istanze x86 o istanze ARM (fino a 4 core e 24 GB RAM) con IP pubblico IPv4 gratuito a vita senza scadenze.

              Consigliato

          

            Google Cloud (Free Tier) →

              Offre un'istanza virtuale e2-micro gratuita ogni mese nelle regioni USA, ottima per script leggeri di ricezione socket.

          

            Amazon Web Services (AWS) →

              Include un'istanza Linux t2.micro / t3.micro gratuita per 12 mesi con IP pubblico per i nuovi account.

          

            Microsoft Azure →

              Include macchine virtuali Linux B1s per 12 mesi e credito di prova iniziale per i primi 30 giorni.

      

### Opzione B: computer o mini-PC locale a casa

          Rete locale LAN • Richiede dispositivo sempre attivo

Puoi avviare il programma Python direttamente su un computer di casa, un **Raspberry Pi**, un **NAS (Synology / QNAP)** o un mini-PC dedicato (es. server Home Assistant) collegato alla medesima rete Wi-Fi/LAN del Foobot. In questo caso l'indirizzo da utilizzare sar&agrave; l'IP privato locale del computer (es. `192.168.1.50`). Questa modalit&agrave; richiede che la macchina rimanga accesa per continuare a ricevere le letture.

          Vai alla Guida per Raspberry Pi & NAS Locale &darr;

    

## 3. Configurazione del server su Oracle Cloud (modello collaudato della guida)

Procedura testata sul campo • 100% Sempre Gratuita

      In questa guida adottiamo **Oracle Cloud Always Free** come modello di riferimento ufficiale: permette di avere una macchina Linux (Ubuntu) nel cloud con IP pubblico dedicato, operativa 24 ore su 24 senza richiedere dispositivi accesi a casa. Il piano Always Free di Oracle &egrave; pensato per sviluppatori, rimane gratuito per sempre senza limiti temporali e mette a disposizione una vera e propria VPS cloud completa utilizzabile per qualsiasi proprio progetto, script o servizio.

    

      

#### Fase 1: registrazione account e scelta dell'Home Region

Collegati al portale ufficiale [Oracle Cloud Free Tier →](https://www.oracle.com/cloud/free/) e clicca sul pulsante di avvio gratuito. Compila i passaggi con attenzione:

- **Dati account e paese**: seleziona *Italia* (o il tuo paese effettivo), inserisci nome, cognome reali e la tua email personale. Clicca su *Verifica la mia email* e apri il link di conferma ricevuto nella tua casella di posta.
- **Tipo account**: seleziona **Individuale** (Personal / Individual).
- **Nome account cloud (Tenancy)**: inserisci un nome a scelta per il tuo spazio di lavoro (es. `tuonome-cloud`). Sar&agrave; il nome univoco con cui accedere.
- **Home Region (permanente e fondamentale)**: seleziona la regione pi&ugrave; vicina (per l'Italia: *Italy North (Turin)* o *Italy Northwest (Milan)*).                                           **Attenzione:** l'Home Region non pu&ograve; essere cambiata dopo la registrazione e tutte le risorse gratuite del piano Always Free andranno create tassativamente in questo specifico datacenter.
- **Verifica identit&agrave; con carta di credito/debito**: serve esclusivamente a convalidare la persona fisica contro gli abusi. Viene effettuata una temporanea pre-autorizzazione di prova di circa **0,93 &euro;** stornata e riaccreditata in automatico dalla banca. All'attivazione ricevi circa 250 &euro; in crediti promozionali per 30 giorni: scaduti i 30 giorni, l'account passa in automatico al profilo **Always Free** senza alcun addebito a sorpresa.
- **Attivazione e 2FA**: accetta le condizioni e clicca su *Avvia la mia prova gratuita*. Al primo accesso configura l'autenticazione a due fattori (2FA) con un'app come Google Authenticator.

        

Regola d'oro per non pagare mai nulla (permanenza su Always Free)

            Per avere la certezza assoluta che il server rimanga gratuito al 100% anche dopo i 30 giorni di prova:

- Durante la creazione di qualsiasi risorsa (macchine virtuali, dischi, indirizzi IP), accertati sempre che sia presente l'etichetta verde **«Idoneo per Always Free»** (o *Always Free Eligible*).
- Non attivare mai l'upgrade a pagamento (*Pay As You Go*).
- Utilizzando solo le risorse Always Free, alla scadenza del 30&deg; giorno i crediti promozionali si azzereranno semplicemente e il server continuer&agrave; a funzionare regolarmente a costo zero per sempre.

      

#### Fase 2: creazione preventiva della rete cloud virtuale (VCN con connettivit&agrave; Internet)

          Perch&eacute; creare la rete PRIMA dell'istanza: Questo passaggio preliminare garantisce che la macchina virtuale disponga fin dal primo avvio di una subnet pubblica con Gateway Internet (IGW) e permette l'assegnazione automatica dell'indirizzo IPv4 pubblico senza blocchi nella console.

Dal menu laterale di Oracle vai in **Networking → Reti cloud virtuali** (oppure collegati direttamente a [Reti cloud virtuali (VCN) →](https://cloud.oracle.com/networking/vcns)):

        

              Pannello Reti cloud virtuali (VCN) con pulsante Crea VCN

              Modulo di configurazione VCN: blocchi CIDR IPv4 e risoluzione DNS

1. Fai clic sul pulsante **Azioni** accanto a *Crea VCN* e seleziona **Avvia creazione guidata VCN**.
2. Seleziona l'opzione **Crea VCN con connettivit&agrave; Internet** (che configura automaticamente VCN, subnet pubblica, subnet privata e Gateway Internet) e clicca su *Avvia procedura guidata VCN*.
3. Nel modulo di configurazione:              - **Nome VCN**: assegna un nome (es. `vcn-foobot`). - **Blocco CIDR IPv4 VCN**: `10.0.0.0/16`. - **Configura subnet pubblica**: blocco CIDR `10.0.0.0/24`. - **Configura subnet privata**: blocco CIDR `10.0.1.0/24`. - **IPv6**: disattivo / senza spunta.
4. Clicca su **Avanti** e nella pagina di riepilogo premi **Crea** (richiede 5-10 secondi). Al termine, con tutte le spunte verdi, clicca su **Visualizza VCN**.

      

#### Fase 3: creazione guidata dell'istanza virtuale (wizard a 5 passaggi)

Ora che la rete VCN &egrave; pronta, dalla sezione **Computazione → Istanze** della console Oracle Cloud clicca sul pulsante nero **Crea istanza** per avviare il processo di configurazione della macchina virtuale.

        

            Elenco Istanze di calcolo: la macchina &egrave; attiva (In esecuzione) con il suo IP pubblico dedicato visibile nella colonna. Da qui si clicca anche su Crea istanza per iniziare.

La console di Oracle ti guider&agrave; attraverso una sequenza a 5 schermate consecutive (cliccando sul pulsante **Avanti** al termine di ciascuna):

        

          [ Step 1: Base & OS ] → [ Step 2: Sicurezza ] → [ Step 3: Networking & SSH ] → [ Step 4: Storage ] → [ Step 5: Revisione & Crea ]

1. **Step 1: Informazioni di base e Sistema Operativo**              - **Nome istanza**: assegna un nome identificativo a piacere (es. `server-foobot`). - **Compartimento e posizionamento**: lascia il compartimento radice predefinito e il dominio di disponibilit&agrave; impostato dal sistema (es. `AD-1`). - **Tipo di capacit&agrave;**: seleziona *Capacit&agrave; su richiesta* (On-demand). - **Immagine (Sistema Operativo)**: fai clic su **Cambia immagine**, seleziona la scheda **Canonical Ubuntu** e scegli la versione **`Canonical Ubuntu 26.04 Minimal`** (oppure `24.04 Minimal`), quindi conferma premendo *Seleziona immagine*. - **Forma hardware (Shape)**: lascia la forma predefinita assegnata dal sistema. Se desideri modificarla, clicca su *Modifica forma*, seleziona *Virtual machine* e clicca sul riquadro **Ampere** (o AMD) verificando la presenza del contrassegno verde *Idoneo per Always Free*. - **Opzioni avanzate**: lascia il menu chiuso senza modificare parametri e fai clic su **Avanti** in basso a destra.                                                                         Schermata dello Step 1 su Oracle Cloud: posizionamento, cambio immagine Ubuntu e selezione della forma hardware.
2. **Step 2: Sicurezza**              - **Istanza schermata (Shielded instance)**: lascia la casella **DISATTIVATA** (senza spunta, come da default). Per un server Ubuntu Linux standard &egrave; consigliabile mantenerla disattivata per massimizzare la compatibilit&agrave; del sistema operativo ed evitare blocchi firmware. - **Opzioni avanzate (Attributi di sicurezza / ZPR)**: lascia vuoto e non aggiungere attributi. - Fai clic su **Avanti**.
3. **Step 3: Networking e chiavi SSH (collegamento immediato)**                            Avendo gi&agrave; creato la VCN nella Fase 2, il collegamento &egrave; immediato:                           - **Rete cloud virtuale (VCN)**: seleziona il pallino *Seleziona rete cloud virtuale esistente* e scegli dal menu la VCN creata prima (es. `vcn-foobot`). - **Subnet**: seleziona il pallino *Seleziona subnet esistente* e scegli la **subnet pubblica** (es. `subnet pubblica-...`). - **Assegnazione IPv4 privato**: scegli *Assegna automaticamente indirizzo IPv4 privato*. - **Assegnazione IPv4 pubblico**: spunta obbligatoriamente la casella **Assegna automaticamente indirizzo IPv4 pubblico**. - **Assegnazione IPv6**: lascia la casella **DISATTIVATA** (la rete opera in IPv4 puro, ideale per il broker MQTT e il firmware del Foobot). - **Aggiungi chiavi SSH (fondamentale)**:                                                                            **Attenzione: scarica subito il file adesso!**                     Seleziona **Genera automaticamente una coppia di chiavi** e fai clic subito sul pulsante **Scarica chiave privata**. Salva il file (es. `chiave.key` o `.pem`) in una cartella protetta del tuo computer: sar&agrave; il passpartout indispensabile per collegarti via SSH dal tuo PC personale senza passare dal pannello online di Oracle. Se prosegui senza scaricarlo, non sar&agrave; pi&ugrave; possibile recuperare la chiave! - Fai clic su **Avanti**.
4. **Step 4: Storage (Volume di avvio e disco SSD)**              - **Dimensione personalizzata**: lascia **DISATTIVATO** (senza spunta). La dimensione predefinita di **46.6 GB** &egrave; interamente compresa nella quota gratuita di 200 GB del piano Always Free e pi&ugrave; che sufficiente per Linux, Python e il Bridge. - **Cifratura in transito**: lascia **ATTIVATO** (con spunta, predefinito). - **Cifratura con chiave utente**: lascia **DISATTIVATO** (Oracle gestisce la protezione in modo trasparente e gratuito). - **Volumi a blocchi aggiuntivi**: non aggiungere dischi secondari e fai clic su **Avanti**.
5. **Step 5: Revisione e creazione finale**              - Controlla il riepilogo generale delle risorse e premi il pulsante blu **Crea** in basso a destra. - La pagina mostrer&agrave; l'icona con lo stato arancione *PROVISIONING*. Nel giro di 1 o 2 minuti l'icona diventer&agrave; verde con la dicitura **IN ESECUZIONE** (Running). - Nel riquadro *Accesso all'istanza* (e nella tabella riepilogativa) comparir&agrave; il tuo **Indirizzo IPv4 pubblico** numerico dedicato: &egrave; l'IP ufficiale del tuo server!                            **Suggerimento se compare l'avviso «Capacit&agrave; esaurita» al momento del Crea:** torna allo Step 1, cambia l'immagine scegliendo l'ultima versione disponibile (es. *Canonical Ubuntu 26.04 Minimal*), imposta il processore su forma *Ampere* e premi nuovamente *Crea*.

      

#### Fase 4: apertura della porta TCP 1883 nel firewall cloud (Security List)

Questo passaggio &egrave; **obbligatorio**: il Foobot &egrave; programmato di fabbrica per trasmettere i suoi pacchetti di telemetria esclusivamente sulla porta **TCP 1883** (protocollo MQTT standard). Per consentire ai pacchetti di raggiungere la macchina virtuale:

1. Nel menu principale di Oracle vai in **Networking** → **Reti cloud virtuali (VCN)** e clicca sul nome della tua VCN.
2. Nel menu orizzontale/laterale entra nella scheda **Subnet** e clicca sul nome della tua **Subnet pubblica**.
3. Entra nella scheda **Sicurezza** (o *Liste di sicurezza*) e fai clic sul nome blu della tua lista (es. `Default Security List for VCN-...`).
4. Nel menu a sinistra seleziona **Regole di entrata** (Ingress Rules) e clicca sul pulsante blu **Aggiungi regole di entrata** (Add Ingress Rules).
5. Compila la regola con i seguenti valori:              - **Tipo di origine**: `CIDR` - **CIDR di origine**: `0.0.0.0/0` (apertura globale consigliata: garantisce la ricezione continua anche quando il modem di casa si riavvia cambiando l'IP dinamico dell'abitazione). - **Protocollo IP**: `TCP` - **Intervallo di porte di origine**: lascia vuoto (qualsiasi porta). - **Intervallo porte di destinazione**: digita esattamente `1883`. - **Descrizione**: `Porta MQTT Foobot Globale`.
6. Clicca su **Aggiungi regole di entrata** per rendere attiva l'autorizzazione.

      

**Dati fondamentali da tenere a portata di mano**

          Prima di aprire il terminale per collegarti al server, accertati di avere pronti questi due elementi ricavati dai passaggi precedenti:

            1. Il file della chiave privata SSH (.key o .pem)

              Dove si trova: &egrave; il file scaricato sul tuo PC al punto Step 3 (Networking e chiavi SSH) premendo «Scarica chiave privata» (es. chiave.key). Senza questo file l'accesso al server sar&agrave; respinto.

            2. L'indirizzo IPv4 pubblico del server

              Dove si legge: nella pagina dell'istanza su Oracle Cloud (sotto Dettagli istanza → riquadro Accesso all'istanza oppure colonna Indirizzo IP pubblico, es. 198.51.100.123).

      

#### Fase 5: accesso al server via SSH dal tuo computer (Windows, macOS o Linux)

I comandi sul server si inviano tramite il protocollo **SSH (Secure Shell)**. Aprire il terminale direttamente **all'interno della cartella in cui si trova il file della chiave** &egrave; la soluzione pi&ugrave; comoda e pratica: evita di dover digitare lunghi percorsi assoluti del file e azzera il rischio di errori di battitura.

        

          

            Windows (PowerShell)

              Apri la cartella del file chiave (.key) in Esplora file, clicca sulla barra dell'indirizzo in alto, digita powershell e premi Invio. Il terminale si aprir&agrave; direttamente l&igrave;.

          

            macOS (Terminale)

              Nel Finder, fai clic destro sulla cartella contenente il file chiave (.key) e seleziona «Nuovo terminale nella cartella» (oppure apri il Terminale, digita cd  e trascina la cartella nella finestra).

          

            Linux (Terminale)

              Nel file manager grafico, clicca con il tasto destro nello spazio vuoto della cartella con il file chiave (.key) e scegli «Apri nel terminale».

- **Permessi del file chiave (.key) (solo su macOS e Linux)**: prima di collegarti, imposta i permessi di lettura restrittivi richiesti da SSH digitando `chmod 400 chiave-privata.key` (su Windows questo passaggio non &egrave; necessario).
- **Comando di collegamento SSH**: digita il comando sostituendo il nome del tuo file chiave (`.key`) e l'**indirizzo IP pubblico del server** precedentemente segnato da parte:             ssh -i "chiave-privata.key" ubuntu@198.51.100.XXX             *Nota: `ubuntu` &egrave; il nome utente predefinito creato da Oracle per le immagini Canonical Ubuntu e non va modificato.*
- **Conferma dell'impronta di sicurezza**: alla prima connessione comparir&agrave; la richiesta *"Are you sure you want to continue connecting (yes/no/[fingerprint])?"*. Digita esattamente **`yes`** e premi Invio.
- Quando vedi comparire la scritta in verde `ubuntu@nome-istanza:~$` (dove *nome-istanza* corrisponde al nome assegnato alla macchina durante lo Step 1 della creazione, es. `server-foobot`), sei collegato ed operativo direttamente all'interno della macchina virtuale.

      

**Consiglio pratico per incollare i comandi nel terminale:**

          Se all'interno della finestra del terminale (PowerShell o Prompt) la combinazione da tastiera standard Ctrl + V non incolla il testo, &egrave; sufficiente fare un **singolo clic con il tasto destro del mouse** nello spazio vuoto del terminale: il testo copiato verr&agrave; incollato istantaneamente.

#### Fase 6: installazione pacchetti Python e sblocco firewall interno (iptables)

          Ora che sei dentro al server, completiamo la preparazione della macchina eseguendo due operazioni fondamentali direttamente dal terminale:

1. **Installazione dell'ambiente base (Python 3 e librerie di rete)**                            Installa l'interprete Python 3, il gestore di pacchetti `pip`, la libreria `requests` (necessaria al bridge per spedire i dati via HTTP/HTTPS al sito web) e l'editor di testo `nano`:                          sudo apt update && sudo apt install -y python3 python3-pip python3-requests iptables-persistent nano
2. **Sblocco obbligatorio della porta 1883 nel firewall interno (`iptables`)**                            Oltre alla Security List sul sito web di Oracle Cloud, le immagini ufficiali di Ubuntu Server hanno un firewall software interno (`iptables`) che blocca qualsiasi porta in ingresso eccetto SSH. Esegui questi due comandi per autorizzare il traffico MQTT sulla porta 1883 e rendere la regola permanente anche in caso di riavvio della macchina:                          sudo iptables -I INPUT 1 -p tcp --dport 1883 -j ACCEPT sudo netfilter-persistent save                            *Nota:* il comando `netfilter-persistent save` salva in modo definitivo le regole appena create, evitando che vadano perse se il server si riavvia.

    

## 4. Configurazione e caricamento del file bridge.py

Ora che il server &egrave; attivo e accessibile, predisponiamo il programma `bridge.py` che ricever&agrave; i dati del sensore e li inoltrer&agrave; al tuo cruscotto web.

    

#### Come procurarsi il file bridge.py

Hai a disposizione due modalit&agrave; per scaricare lo script Python del Server Bridge:

        

          Opzione A • Singolo Modulo da GitHub

Puoi visualizzare e scaricare direttamente la cartella del Server Bridge dal repository ufficiale di NewFoobot:

          Visualizza cartella su GitHub →

            All'interno troverai il file sorgente bridge.py gi&agrave; pronto per essere personalizzato.

        

          Opzione B • Archivio Completo NewFoobot

Se hai scaricato l'intero pacchetto ZIP dalla homepage, apri la cartella del progetto sul tuo PC ed estrai il contenuto di:

            2_mqtt_broker_bridge_server/bridge.py

          Scarica ZIP Completo (.zip)

Nelle prime righe del file troverai il blocco `CONFIGURAZIONE UTENTE`: occorre sostituire i **due parametri fondamentali** con i dati reali del tuo cruscotto web ricavati nello Step 1:

    

      # Come appare originariamente all'inizio di bridge.py (righe 28-39):
      # ==========================================
      # CONFIGURAZIONE UTENTE
      # ==========================================
      # Inserire l'URL esatto della cartella del proprio sito web
      INGEST_URL = "https://your-domain.com/api/ingest.php"

      # Inserire la stessa Passkey API impostata durante l'installazione del sito
      API_KEY = "YOUR_SECURE_API_SECRET_KEY"

      # Parametri Server MQTT Integrato (ascolto diretto sulla porta 1883)
      MQTT_BIND_HOST = "0.0.0.0"
      MQTT_BIND_PORT = 1883

    

      

#### 1. Indirizzo di Destinazione Dati (INGEST_URL)

&Egrave; l'indirizzo web dell'endpoint PHP sul tuo sito hosting (installato nello Step 1) che riceve le letture dal bridge e le salva nel database. Corrisponde esattamente al percorso in cui hai caricato i file PHP del cruscotto seguito da `/api/ingest.php`.

          Valore predefinito da sostituire: "https://your-domain.com/api/ingest.php"
          Esempio reale con il tuo sito web: "https://tuonome.netsons.org/api/ingest.php" (o /foobot/api/ingest.php se hai installato il cruscotto in una sottocartella)

      

#### 2. Chiave di Sicurezza Autenticazione (API_KEY)

&Egrave; la stessa identica Passkey API segreta che hai scelto e inserito nelle impostazioni del sito web durante l'installazione guidata con `install.php` nello Step 1 (e che puoi ritrovare nel file `config.php` del sito). Serve a garantire che solo il tuo Server Bridge possa comunicare con il cruscotto PHP, bloccando qualsiasi richiesta non autorizzata.

          Valore predefinito da sostituire: "YOUR_SECURE_API_SECRET_KEY"
          Esempio reale con la tua chiave del sito: "MiaPasskeyFoobot2026!"

    

#### Come trasferire il file bridge.py dal PC al server (senza nano)

Non serve installare o utilizzare editor di testo da terminale sul server. Modifica comodamente il file `bridge.py` sul tuo PC con il Blocco Note (impostando le due righe con `INGEST_URL` e `API_KEY`) e invialo direttamente nella cartella del server tramite comando di copia sicura (SCP):

1. **Nel terminale del server (`ubuntu@nome-istanza:~$`):** crea la cartella di destinazione eseguendo:           mkdir -p ~/foobot-bridge
2. **Sul tuo PC:** verifica che il file `bridge.py` salvato e il file della chiave SSH (ad esempio `ssh-key-foobot.key`) si trovino nella stessa cartella locale.
3. **Invia il file aprendo una seconda finestra di PowerShell (o Terminale Mac/Linux):** non chiudere il terminale del server già connesso a `ubuntu@nome-istanza:~$`. Apri una **nuova finestra di terminale sul tuo PC** posizionandoti nella cartella locale con la chiave e `bridge.py`. Esegui il comando di trasferimento, inserendo il **nome effettivo della tua chiave** e l'**IP pubblico del tuo server** (gli stessi dati usati per il collegamento SSH iniziale):           scp -i "ssh-key-foobot.key" bridge.py ubuntu@TUO_IP_PUBBLICO:~/foobot-bridge/           Il file verrà caricato in pochi istanti nel percorso `~/foobot-bridge/bridge.py` del server, senza interrompere la sessione remota. Puoi quindi tornare al primo terminale per procedere con il test.

    

## 5. Test manuale e avvio automatico continuo 24/7 (servizio di sistema)

Tutti i comandi seguenti vanno eseguiti **all'interno del terminale del server**: se hai chiuso la finestra, ricollegati via SSH come descritto nella **Fase 5** fino a visualizzare il prompt verde `ubuntu@nome-istanza:~$`.

    

#### Passo 1: prova di avvio immediato (test manuale)

Prima di impostare l'esecuzione automatica, avviamo il programma manualmente per verificare che non ci siano errori di sintassi o parametri errati:

    python3 ~/foobot-bridge/bridge.py

Se il programma &egrave; configurato correttamente, vedrai comparire il banner con la scritta:

      NewFoobot Standalone MQTT Broker & Bridge v3.0 avviato!
      In ascolto diretto su : 0.0.0.0:1883

Questo conferma che il server riceve ed &egrave; in ascolto. Premi la combinazione da tastiera Ctrl + C per interrompere il test temporaneo e tornare al terminale.

    

#### Passo 2: configurazione come servizio permanente di sistema (systemd)

Se lasciassi il programma aperto nel terminale, si chiuderebbe non appena scolleghi il PC. Creiamo quindi un **servizio di sistema di Linux (systemd)**: &egrave; un automatismo che mantiene il Bridge sempre attivo in sottofondo (anche quando chiudi il terminale) e lo riavvia da solo se il server viene riavviato:

    sudo nano /etc/systemd/system/foobot-bridge.service

Incolla all'interno dell'editor questa configurazione predefinita:

    [Unit]
Description=NewFoobot Standalone MQTT Broker to cPanel Bridge
After=network.target

[Service]
Type=simple
User=ubuntu
WorkingDirectory=/home/ubuntu/foobot-bridge
ExecStart=/usr/bin/python3 -u /home/ubuntu/foobot-bridge/bridge.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

Salva il file (con `Ctrl + O`, poi `Invio` ed esci con `Ctrl + X`), quindi abilita e avvia il servizio:

    sudo systemctl daemon-reload
sudo systemctl enable foobot-bridge.service
sudo systemctl restart foobot-bridge.service

Puoi controllare lo stato e i log in tempo reale in qualsiasi momento con:

    sudo systemctl status foobot-bridge.service
sudo journalctl -u foobot-bridge.service -f

    

## 6. Alternativa: installazione su server locale (Raspberry Pi o NAS)

Procedura alternativa non collaudata direttamente

      Se disponi già di un dispositivo acceso continuamente nella tua rete domestica (come un **Raspberry Pi**, un mini-PC Linux, oppure un **NAS Synology, QNAP o Asustor**), puoi eseguire lo script `bridge.py` in locale anziché su Oracle Cloud.

    

        Passaggio preliminare obbligatorio: prepara prima il file bridge.py sul tuo PC

Anche se utilizzi un server domestico o un NAS, lo script Python richiede la stessa identica personalizzazione vista sopra. Prima di procedere oltre, assicurati di aver scaricato `bridge.py` sul tuo PC e di aver compilato le due righe `INGEST_URL` e `API_KEY` seguendo le indicazioni della **Sezione 4**:

        &uarr; Torna al punto 4: Configurazione e scaricamento di bridge.py

    

#### Regole di rete fondamentali per l'ambiente domestico

- **Indirizzo IP locale fisso (Prenotazione DHCP):** il tuo Raspberry Pi o il tuo NAS deve mantenere sempre lo stesso indirizzo IP interno (ad esempio `192.168.1.150`). Entra nel pannello del tuo router di casa e imposta l'assegnazione IP statica per quel dispositivo, perché sarà l'indirizzo che comunicherai al DNS nello Step 3.
- **Nessuna apertura porte sul router:** poiché sia il Foobot sia il Raspberry/NAS sono collegati al medesimo Wi-Fi o cavo LAN di casa, **non devi aprire la porta 1883 sul router verso Internet**. La comunicazione MQTT avviene interamente dentro le mura domestiche.
- **Uscita HTTPS libera:** il dispositivo locale deve poter accedere normalmente a Internet per spedire le letture via HTTPS al tuo spazio web cPanel.

    

### Opzione A: guida dettagliata per Raspberry Pi o Mini-PC Linux

**Serve il terminale?** Sì, su Raspberry Pi (o mini-PC Debian/Ubuntu) si utilizza il terminale, ma hai due possibilità molto comode: se il Raspberry è collegato a uno schermo con tastiera e mouse puoi operare con le finestre grafiche come su un normale computer; se invece è senza schermo (*headless*), ti colleghi dal tuo PC tramite PowerShell.

        

          Passo 1: accesso al terminale e installazione di Python

Se usi il Raspberry con schermo e mouse, apri l'icona del **Terminale** in alto. Se invece operi dal tuo PC Windows/Mac, apri PowerShell e collegati con utente e password del tuo Raspberry (sostituendo `pi` con il tuo nome utente e `192.168.1.150` con l'IP locale del Raspberry):

          ssh pi@192.168.1.150

Una volta dentro, installa il supporto Python 3 e crea la cartella di lavoro:

          sudo apt update && sudo apt install -y python3 python3-requests
mkdir -p ~/foobot-bridge

        

          Passo 2: come caricare il file bridge.py sul Raspberry

Scegli il metodo che preferisci tra questi due:

- **Con chiavetta USB (se hai schermo e mouse collegati):** copia il file `bridge.py` (già modificato al [punto 4](#configurazione-bridge-py)) su una chiavetta USB, inseriscila nel Raspberry e trascina il file con il mouse dentro la cartella `foobot-bridge` nella tua Home.
- **Via rete dal tuo PC (senza chiavetta):** apri una nuova finestra di PowerShell sul tuo PC nella cartella dove hai salvato `bridge.py` e invialo direttamente via rete locale (ti verrà chiesta la password del Raspberry):               scp bridge.py pi@192.168.1.150:~/foobot-bridge/

        

          Passo 3: avvio automatico continuo 24/7

Nel terminale del Raspberry esegui la stessa procedura vista nella **Sezione 5** per creare il servizio di sistema, avendo cura di sostituire `ubuntu` con il nome utente del tuo Raspberry (di solito `pi`):

          sudo nano /etc/systemd/system/foobot-bridge.service

Incolla la configurazione adattata per l'utente `pi`:

          [Unit]
Description=Foobot Local MQTT Bridge
After=network-online.target

[Service]
Type=simple
User=pi
WorkingDirectory=/home/pi/foobot-bridge
ExecStart=/usr/bin/python3 -u /home/pi/foobot-bridge/bridge.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

Salva con `Ctrl + O`, premi `Invio`, esci con `Ctrl + X` e attiva il servizio:

          sudo systemctl daemon-reload && sudo systemctl enable --now foobot-bridge.service

    

### Opzione B: guida dettagliata per NAS (Synology, QNAP, Asustor)

**Serve usare il terminale sul NAS? No!** Sui moderni NAS domestici puoi fare tutto comodamente dal browser web usando il mouse, trascinando il file `bridge.py` a mano dentro una cartella e sfruttando le applicazioni ufficiali già presenti nello store del NAS.

      

        Quali applicazioni installare dallo Store del NAS?

- **Su NAS Synology (sistema DSM):** apri il *Centro Pacchetti* e installa **Python 3.9** (o versione superiore) oppure **Container Manager** (ex Docker). Useremo inoltre **File Station** e l'**Utilità di pianificazione** integrata nel Pannello di Controllo.
- **Su NAS QNAP (sistema QTS):** apri l'*App Center* e installa **Python3** e **Container Station**. Useremo **File Station 5** per caricare il file a mano.

        

          Passo 1: come caricare il file bridge.py a mano sul NAS

1. Assicurati di aver preparato sul tuo PC il file `bridge.py` con i tuoi dati ([vedi Sezione 4](#configurazione-bridge-py)).
2. Entra nel pannello web del tuo NAS, apri l'app **File Station** (il gestore cartelle del NAS) e crea una nuova cartella chiamata `foobot-bridge` (ad esempio dentro la cartella condivisa `docker` o `homes`).
3. Trascina con il mouse il file `bridge.py` dal tuo PC direttamente dentro la finestra di **File Station** (oppure clicca sul pulsante *Carica → Carica - Sovrascrivi*). Annota il percorso completo della cartella (es. `/volume1/docker/foobot-bridge/bridge.py`).

        

          Passo 2 (Metodo semplice Synology): avvio automatico tramite Utilità di pianificazione

Su Synology puoi far partire lo script all'accensione del NAS senza mai toccare il terminale né Docker:

1. Vai in **Pannello di controllo → Utilità di pianificazione** (*Task Scheduler*).
2. Clicca su **Crea → Attività attivata → Script definito dall'utente**.
3. Nella scheda *Generale*: assegna il nome `Foobot Bridge`, imposta **Utente: root** ed **Evento: Avvio** (*Boot-up*).
4. Nella scheda *Impostazione attività*, nel riquadro **Esegui comando**, incolla queste due righe (verificando che il percorso corrisponda alla tua cartella del Passo 1):               python3 -m pip install requests nohup python3 -u /volume1/docker/foobot-bridge/bridge.py > /volume1/docker/foobot-bridge/bridge.log 2>&1 &
5. Premi **OK** per salvare, quindi seleziona la riga appena creata in elenco e clicca sul pulsante **Esegui** in alto per avviarlo subito. Potrai leggere il registro di funzionamento aprendo il file `bridge.log` direttamente da File Station.

        

          Passo 2 Alternativo (Synology Container Manager / QNAP Container Station): avvio in Docker

Se sul tuo NAS (Synology, QNAP o Asustor) preferisci isolare lo script in un container Docker tramite l'interfaccia grafica di **Container Manager** o **Container Station**:

1. Apri **Container Manager / Container Station**, vai nella sezione *Progetto* (o *Applicazione Compose*) e clicca su **Crea**.
2. Seleziona come percorso la cartella `foobot-bridge` in cui hai caricato a mano `bridge.py`, scegli *Crea file docker-compose.yml* e incolla questa configurazione pronta:               version: "3.8" services:   foobot-bridge:     image: python:3.11-slim     container_name: foobot-bridge     restart: always     ports:       - "1883:1883"     volumes:       - ./bridge.py:/app/bridge.py:ro     working_dir: /app     command: sh -c "pip install --no-cache-dir requests && python -u bridge.py"
3. Avvia il progetto con un clic: il NAS scaricherà automaticamente l'immagine ufficiale di Python, installerà la libreria `requests`, aprirà la porta `1883` e manterrà il Bridge sempre attivo 24/7.

    

        Annota e conserva l'Indirizzo IP del Server Bridge:

Ora che il tuo Server Bridge &egrave; pronto e in ascolto sulla porta 1883, segnati il suo **indirizzo IP**:
        se hai creato la macchina su Oracle Cloud / VPS sar&agrave; il suo *IP Pubblico Statico*, mentre se hai scelto il PC o Raspberry locale sar&agrave; il suo *IP Privato LAN* (es. `192.168.1.50`).

        Questo indirizzo IP sar&agrave; il valore da inserire nel prossimo **Step 3 (Regola di Riscrittura DNS)**!

    

      ← Torna allo Step 1: Web & Database
      Vai allo Step 3: Imposta DNS →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 1](step1.md)
- ➡️ **Next:** [Step 3](step3.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
