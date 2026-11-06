# Step 6: aggancia applicazione mobile e App PWA

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step6.html](http://newfoobot-restart.barbaralerici.net/en/step6.html)

---

    > [!NOTE]
> **Summary & Goal of Step 6**
> L'applicazione mobile di NewFoobot &egrave; una **PWA (Progressive Web App)** autonoma e completa. Per poter funzionare e comunicare con il tuo server personale, i file del **Deposito 4 dell'App (repository Git)** devono risiedere nella cartella `/app/` del tuo sito cruscotto (ad esempio `https://tuodominio.com/app/`). In questo modo l'App accede direttamente alle API protette del database e pu&ograve; essere aperta e installata con un tocco sia su smartphone (Android e iOS) sia su personal computer.

    

#### Come procurarsi il pacchetto dell'App PWA (Modulo 4)

Hai a disposizione due modalit&agrave; per ottenere i file dell'applicazione:

        

          Opzione A • Modulo 4 da GitHub

Visualizza e preleva i sorgenti della PWA direttamente dal repository Git di progetto:

          Visualizza su GitHub →

        

          Opzione B • Archivio Completo NewFoobot

Scarica l'intero pacchetto ZIP dalla homepage con tutti i file del progetto:

          Scarica ZIP Completo (.zip)

      

**
            Collocazione obbligatoria dei file sul Server: cartella `/app/`
          **

            Tutti i file e le cartelle contenuti all'interno del **Modulo 4** (es. `index.html`, `manifest.json`, `sw.js`, `languages/`, ecc.) **devono essere caricati sul tuo hosting all'interno della cartella `/app/` del cruscotto web**.

- **Percorso file sul server (FTP / cPanel):** `public_html/app/` (oppure `radice-cruscotto/app/`).
- **Indirizzo web finale dell'App:** sarà raggiungibile direttamente su `https://tuodominio.com/app/`.
- *Se hai già caricato l'intero archivio completo nello Step 1, la cartella `/app/` è già presente nella posizione corretta e subito pronta per l'uso!*

## 1. Cos'è l'App PWA e perché è stata scelta

L'applicazione mobile di NewFoobot è una **PWA (Progressive Web App)** pronta e funzionante. Questo approccio moderno offre due grandi vantaggi:

- **Compatibilità universale immediata**: funziona e si installa in modo identico sia su qualsiasi smartphone (Android e iPhone/iOS) sia su PC (Windows, Mac, Linux) direttamente dal browser, senza dover scaricare file pesanti o passare attraverso gli store di Google o Apple.
- **Leggerezza ed ergonomia**: una volta installata sulla schermata home o sul desktop del computer, si apre a schermo intero come una normale applicazione indipendente, riproducendo la grafica e l'esperienza dell'applicazione originale Foobot.

## 2. Come funziona e requisiti di rete (Connessione a Internet)

L'App serve per **visionare i dati ambientali comodamente in mobilità**. Tutti i valori visualizzati (temperatura, umidità, CO₂, VOC e particolato fine PM2.5) non risiedono nel telefono, ma vengono **richiamati via internet dal database del tuo sito cruscotto personale** (configurato nello Step 1) tramite le apposite API REST integrate.

      Nota importante sulla connessione: Per sua natura tecnica, l'App non funziona offline. È necessario un collegamento a Internet (rete dati 4G/5G o Wi-Fi) per permettere all'applicazione di contattare il server web e aggiornare le letture in tempo reale e i grafici storici.

## 3. Come abilitare, associare e installare l'App PWA

L'App è già inclusa nel pacchetto del cruscotto web (cartella `app/`): caricando l'intero sito nello Step 1, l'App è già online all'indirizzo `https://tuo-dominio.it/app/`.

### 3.1 Dove andare nel cruscotto web

1. Accedi al **sito del tuo cruscotto NewFoobot** con le tue credenziali.
2. Nel **menu principale in alto**, accanto alla voce *Guida*, trovi l'**icona a forma di smartphone** (visibile agli utenti autenticati).
3. Cliccandola si apre la pagina **App Mobile & Desktop** (`mobile_app.php`). Da questa schermata dedicata puoi:          - **Aprire subito l'App e installarla**: aprendo l'App direttamente dal sito e installandola sul computer (tramite browser) o sullo smartphone, questa verrà **automaticamente registrata** nella lista dei dispositivi connessi per il tuo account utente, comparendo all'istante nella tabella dei dispositivi autorizzati. - **Generare il codice seriale di pairing** per associare un secondo telefono o un dispositivo esterno su cui non sei ancora loggato al sito. - **Gestire, rinominare o disconnettere i dispositivi**: sempre all'interno di questa pagina del cruscotto puoi assegnare un nome personalizzato a ciascun telefono o computer (es. *"Smartphone Marco"*, *"Portatile Salotto"*) oppure revocare l'accesso a un dispositivo singolo o a tutti i dispositivi insieme con un clic.

### 3.2 Numero identificativo univoco (ID Database) e associazione

Ogni installazione dell'App aperta su un dispositivo fisico o browser riceve un **numero identificativo progressivo di registrazione (ID #)** assegnato dal database del cruscotto.

- **Nel cruscotto web**: nella tabella *"Dispositivi autorizzati"* della pagina `mobile_app.php`, accanto al nome di ogni dispositivo compare il badge con il relativo numero (es. `ID #1`, `ID #2`). Questo ti consente di individuare con certezza quale telefono o computer corrisponde a quale sessione.
- **Nell'App**: lo stesso identico numero ID viene mostrato all'interno dell'applicazione aprendo il menu laterale/impostazioni (sotto la voce *"ID Dispositivo App"*, es. `#1`), permettendoti di verificare subito la corrispondenza con la lista del sito.

Per associare un dispositivo hai a disposizione due modalità:

- **Registrazione automatica (consigliata)**: aprendo l'App dallo stesso browser e dispositivo in cui hai già effettuato l'accesso al cruscotto web, il sistema la collega e la registra in automatico nel DB senza richiedere codici.
- **Abbinamento con codice seriale**: per collegare un secondo telefono o un dispositivo non ancora autenticato, premi *Associa nuovo dispositivo* nella pagina *App Mobile & Desktop* per generare un codice seriale monouso valido per **10 minuti** (es. `NF-A1B2C3`). Aprendo l'App sul nuovo dispositivo, basta inserire tale seriale assieme all'URL del tuo sito cruscotto nella schermata di benvenuto e cliccare su *Autorizza e Connetti*.

Dopo ripetuti tentativi errati di inserimento codice, l'indirizzo IP viene bloccato temporaneamente per prevenire tentativi di intrusione brute-force.

### 3.3 Come installare l'App (Computer e Smartphone)

Le **PWA (Progressive Web App)** non richiedono il download di file eseguibili pesanti (come gli `.exe` per PC o gli `.apk` per Android) né passaggi attraverso gli store. Si installano direttamente tramite il browser web e funzionano senza alcun problema sia su **computer (Windows, Mac, Linux)** sia su **smartphone (Android e iPhone/iOS)**.

Per installarla è sufficiente aprire l'App dal link della pagina cruscotto (o visitando `https://tuo-dominio.it/app/`) e utilizzare l'apposito pulsante o barra del browser:

- **Su Computer (Chrome, Edge, Brave)**: nella barra degli indirizzi in alto a destra compare l'icona con il pulsante *"Installa NewFoobot"* (oppure dal menu opzioni del browser seleziona *"Installa applicazione"*). L'App creerà un'icona sul desktop e si aprirà in una finestra autonoma e pulita, esattamente come un programma nativo.
- **Su Smartphone Android (Chrome)**: all'apertura compare in basso la barra con il pulsante *"Aggiungi NewFoobot a schermata Home"* o *"Installa app"* (disponibile anche toccando i tre puntini del menu di Chrome).
- **Su iPhone / iPad (Safari)**: tocca l'icona centrale *Condividi* (il quadrato con la freccia verso l'alto) e seleziona *"Aggiungi alla schermata Home"*.

Una volta installata, l'icona NewFoobot comparirà tra le normali applicazioni del tuo dispositivo, pronta per essere avviata a pieno schermo con un solo tocco o clic.

## 4. Guida all'uso dell'App

L'applicazione offre un'esperienza d'uso suddivisa nelle schermate principali di consultazione:

### 4.1 Schermata Home (Quadrante Trilobato)

        Schermata Home: nucleo centrale GPI (10% Great), lobi VOC (125 ppb), PM2.5 (9.4 µg/m³), eCO2 (450 ppm) e cerchi inferiori di temperatura e umidità.

- **Indice Globale (GPI) al centro**: il grande nucleo centrale riporta l'indice complessivo di qualità dell'aria (GPI) da 0 a 100% con un verdetto testuale immediato (*OTTIMO*, *BUONO*, *SCARSO*). L'anello si colora di **azzurro/blu** quando l'aria è salubre e vira all'**arancione vivo** quando i parametri superano la soglia di guardia, segnalando la necessità di ventilare i locali.
- **I tre lobi inquinanti**: disposti a corona attorno al centro, mostrano i valori correnti di:                    **PM2.5** (Particolato fine espresso in µg/m³).
- **VOC** (Composti Organici Volatili espressi in ppb).
- **eCO2** (Anidride carbonica equivalente espressa in ppm).

      Cerchi inferiori (Temperatura e Umidità): riportano la temperatura ambiente (°C o °F) con l'icona termometro e l'umidità relativa (%) con l'icona goccia.
      Aggiornamento automatico e manuale: i valori si aggiornano in background ogni 30 secondi; in basso è indicato l'orario esatto dell'ultima misurazione ricevuta. Su smartphone puoi inoltre forzare l'aggiornamento immediato in qualsiasi momento strisciando la schermata verso il basso (gesto pull-to-refresh).

### 4.2 Schermata Dettaglio Valore Sensore e Storico

          Grafico storico (filtro 7 giorni) con fasce di severità e cursore di data e valore.

          Menu rapido di selezione per passare istantaneamente tra VOC, PM2.5, eCO2, GPI, TMP e HUM.

Selezionando un singolo inquinante o parametro si accede alla schermata di dettaglio, che mostra l'andamento temporale e il grafico storico della misurazione:

- **Filtri temporali**: selezione rapida dell'intervallo temporale tra *Minuti* (ultime ore ad alta risoluzione), *Ore (24h)*, *Giorni (7d)* e *Settimane (30d)* per analizzare i picchi di inquinamento o la media nel lungo periodo.
- **Grafico storico dell'andamento**: visualizzazione a linee continue o a barre con codice cromatico (blu per valori salubri, arancione per livelli critici), che permette di verificare subito quando e come è variata la qualità dell'aria nella stanza.

### 4.3 Gestione Multi-Foobot e Impostazioni

        Pannello Impostazioni: scelta lingua, unità termica (°C/°F), commutazione dispositivi Foobot e corrispondenza ID Dispositivo App (#19) nel database server.

- **Cambio sensore**: se possiedi più Foobot configurati sullo stesso server, toccando il nome in alto nel selettore puoi passare istantaneamente da una stanza all'altra (es. Salotto / Camera).
- **Menu Impostazioni (icona ingranaggio)**: permette di cambiare l'unità di misura della temperatura (°C o °F), impostare la lingua dell'interfaccia (Italiano o Inglese), verificare l'indirizzo del server e inviare una notifica push di prova.

    

      ← Torna allo Step 5: modifica Router
      Visualizza l'Anteprima del Cruscotto →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 5](step5.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
