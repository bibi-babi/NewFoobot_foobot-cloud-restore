# Step 4: configurazione Foobot via Wi-Fi

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step4.html](http://newfoobot-restart.barbaralerici.net/en/step4.html)

---

    > [!NOTE]
> **Summary & Goal of Step 4**
> In questo passaggio operiamo direttamente sul modulo Wi-Fi del Foobot (chip HF-LPB100). Tramite lo strumento software multipiattaforma fornito dal progetto, sblocchiamo la modalit&agrave; comandi AT senza dover smontare o saldare il dispositivo, comunicandogli il nuovo indirizzo IP del server DNS configurato nello Step 3 (es. NextDNS o DNS locale). In questo modo il sensore contatter&agrave; istantaneamente il tuo Server Bridge personale e ricomincer&agrave; a trasmettere i dati al cruscotto web in tempo reale.

## 1. Preparazione preliminare

Assicurati che il Foobot sia acceso e collegato alla tua rete Wi-Fi di casa, e che il tuo computer (PC fisso o portatile) sia connesso alla **stessa rete Wi-Fi locale**.

## 2. Come scaricare e avviare lo strumento sul tuo computer

Lo strumento di configurazione &egrave; universale, gratuito e completamente aperto: non installa driver, non modifica il tuo sistema operativo e funziona in modo nativo su **Windows**, **macOS** e **Linux**.

    

#### Come procurarsi lo strumento: Foobot Management & Configuration Tool (Gestione Foobot)

Hai a disposizione due modalit&agrave; per scaricare il programma:

        

          Opzione A • Singolo Modulo da GitHub

Puoi visualizzare e scaricare direttamente la cartella del configuratore dal repository ufficiale:

          Visualizza cartella su GitHub →

            Contiene le sottocartelle dedicate pronte per Windows, macOS e Linux.

        

          Opzione B • Archivio Completo NewFoobot

Se hai scaricato il pacchetto completo del progetto dalla home page, estrai l'archivio ZIP e troverai tutto gi&agrave; pronto nella cartella:

          Scarica pacchetto ZIP completo &darr;

            Percorso: 1_foobot_device_config/

      

#### Istruzioni di avvio per il tuo sistema operativo:

        

**Windows (10 / 11)**

            Entra nella cartella `windows/` e fai doppio clic su:

          START_FOOBOT_MANAGER.bat

            Avvia automaticamente lo script PowerShell integrato in Windows (foobot_manager.ps1) senza richiedere l'installazione di Python.

        

**macOS (Apple Mac)**

            Entra nella cartella `macos/` e fai doppio clic su:

          START_FOOBOT_MANAGER.command

            Si apre direttamente il Terminale di macOS con l'applicazione (oppure da terminale: python3 foobot_manager.py).

        

**Linux (Ubuntu / Debian / Pi)**

            Entra nella cartella `linux/` ed esegui nel terminale:

          bash START_FOOBOT_MANAGER.sh

            Richiede Python 3 preinstallato (di serie su tutte le distribuzioni Linux recenti).

## 3. Menu operazioni e lettura diagnostica

All'apertura, lo strumento rileva automaticamente la lingua del sistema operativo (supportando **Italiano** e **Inglese**) e chiede conferma prima di procedere. Subito dopo invia un segnale di rilevamento broadcast sulla rete locale (porta UDP 48899): se il Foobot &egrave; acceso e collegato al Wi-Fi, viene agganciato istantaneamente mostrando l'indirizzo IP locale, l'indirizzo MAC e il codice seriale hardware (MID).

    

        Schermata del Foobot Management & Configuration Tool (Gestione Foobot): rilevamento del sensore sulla rete LAN e menu principale delle 6 opzioni operative. Fai clic sull'immagine per ingrandirla a tutto schermo.

    

#### A cosa servono le opzioni del menu:

        

          1. Leggi la configurazione attuale del Foobot (sola lettura &mdash; 100% sicuro)

Interroga lo stato interno del chip Wi-Fi senza alterare alcun registro. Mostra la versione del firmware interno, il codice seriale univoco (MID), la qualit&agrave; del segnale Wi-Fi ricevuto, l'indirizzo IP, il server DNS attivo, il canale MQTT e lo stato della connessione Cloud (`TCPLKB`). Al termine esegue anche un test diretto di raggiungibilit&agrave; verso la porta 1883 del Bridge. &Egrave; consigliabile eseguirla come prima verifica diagnostica.

        

          2. Configura server DNS personalizzato (NextDNS, Pi-hole o IP dedicato)

&Egrave; l'opzione fondamentale per la prima configurazione: richiede l'indirizzo IPv4 del tuo server DNS (quello predisposto allo Step 3), fissa automaticamente la configurazione di rete in modalit&agrave; statica (cos&igrave; il DHCP del router non pu&ograve; sovrascrivere o cancellare il DNS) ed esegue a caldo la sequenza di sgancio e riaggancio del canale Socket B verso il tuo Server Bridge con timeout di sicurezza a 5 secondi.

        

            3. Riattivazione dopo sospensione corrente con lo stesso IP registrato in precedenza
            NUOVO • POST-BLACKOUT

Risolve il classico blocco che si verifica dopo un'interruzione di corrente (blackout). Quando torna la luce, il Foobot si avvia in circa 3 secondi mentre il router di casa impiega diversi minuti; all'avvio, il router cancella la tabella dei client autorizzati e blocca l'uscita su Internet del Foobot rimasto su IP statico, lasciando la connessione cloud `TCPLKB` su `off`. Questa procedura automatizza il ripristino in 3 fasi con un solo tasto:

- Legge in automatico l'IP DNS gi&agrave; configurato in precedenza nella memoria del Foobot;
- Attiva temporaneamente il DHCP e riavvia il modulo (`AT+Z` con attesa di 16 secondi) per costringere il router di casa a registrare e autorizzare nuovamente il Foobot;
- Rileva via broadcast UDP l'IP assegnato dal router, lo blocca come IP statico, ripristina il DNS salvato e riapre il socket Cloud portando `TCPLKB` su `+ok=on`.

        

          4. Configura nuova rete Wi-Fi di casa (pairing SSID e password)

Permette di collegare il Foobot a una nuova rete Wi-Fi se hai sostituito il modem di casa o cambiato la password di rete, sostituendo la vecchia app ufficiale Airboxlab non pi&ugrave; funzionante. Imposta il nome della rete (SSID a 2.4 GHz) e la password WPA2-PSK con successivo riavvio controllato.

        

          5. Ripristina le impostazioni originali di fabbrica (10.10.100.254)

Funzione di ripristino per annullare qualsiasi personalizzazione: riporta l'acquisizione dell'IP in modalit&agrave; dinamica dal router (DHCP) e reimposta il server DNS originario di fabbrica (`10.10.100.254`).

        

          6. Riavvia il modulo Wi-Fi del Foobot (solo comando AT+Z)

Invia esclusivamente il comando hardware di riavvio (`AT+Z`) al modulo Wi-Fi senza toccare registri o parametri memorizzati. I LED del sensore pulsano per circa 15 secondi fino al ricollegamento alla rete locale.

        

          7. Esci dal programma

Chiude il canale UDP con il dispositivo e termina l'esecuzione dello strumento.

## 4. Modifica del server DNS del Foobot (Opzione 2)

Dal menu principale seleziona l'**Opzione 2** (digita `2` e premi Invio):

    

#### Quale indirizzo IP inserire quando richiesto dal programma?

Il programma ti chieder&agrave; di digitare l'**indirizzo IPv4 del tuo server DNS** (quello predisposto nello **Step 3** per la regola di riscrittura). A seconda della soluzione che hai scelto per il DNS, inserisci il valore corrispondente:

        

          Se hai usato NextDNS (modello collaudato):

            Inserisci uno dei due indirizzi DNS IPv4 primari mostrati nella schermata Setup di NextDNS (ad esempio 45.90.28.xxx o 45.90.30.xxx).

        

          Se hai configurato il Router di casa (FRITZ!Box / OpenWrt):

            Inserisci l'indirizzo IP locale del tuo router (solitamente 192.168.1.1 o 192.168.178.1), che fa da server DNS interno per la tua rete domestica.

        

          Se hai usato Pi-hole o AdGuard Home (Raspberry / NAS):

            Inserisci l'indirizzo IP privato LAN del dispositivo su cui &egrave; in esecuzione il servizio DNS (es. 192.168.1.150).

    

1. **Lettura e fissaggio dell'IP statico:** il programma legge l'indirizzo IP attualmente assegnato al Foobot e fissa la modalit&agrave; statica. In questo modo il router non potr&agrave; sovrascrivere o resettare il DNS quando scade il lease DHCP.
2. **Inserimento dell'indirizzo IP del DNS:** digita l'IP corretto seguendo le casistiche indicate nel riquadro sopra (es. `45.90.28.xxx` oppure `192.168.1.1`).
3. **Conferma di sicurezza:** verifica i dati visualizzati a schermo e conferma digitando S e premendo Invio.
4. **Esecuzione a caldo e aggancio al Bridge:** il programma invia in tempo reale la sequenza di istruzioni al chip Wi-Fi (forzatura della cache DNS, ricreazione del Socket B verso il dominio del Foobot con timeout a 5 secondi e pause di transizione estese a 2.5s). Il sensore risolver&agrave; istantaneamente il dominio puntando verso il tuo Server Bridge!

    

**Cosa fare se salta la corrente o si riavvia il modem (Blackout)**

        Se dopo una sospensione elettrica noti che il Foobot non trasmette pi&ugrave; e nella diagnostica dell'Opzione 1 vedi `Connessione TCP Cloud (TCPLKB) : +ok=off`, **non serve rifare l'Opzione 2**:

Scegli direttamente l'**Opzione 3 ("Riattivazione dopo sospensione corrente")**: il programma legger&agrave; in automatico il DNS gi&agrave; salvato, far&agrave; rinnovare il lease DHCP al router per riabilitare il sensore sulla rete e riaprir&agrave; la connessione cloud con un solo clic.

## 5. Verifica del funzionamento

Apri la pagina `logs.php` del tuo cruscotto web: entro pochi secondi vedrai comparire la nuova connessione TCP dal Foobot e il messaggio verde:

    Autenticazione Foobot MQTT CONNECT riuscita: clientID=2402XXXXXXXXXXXX
Ricevuto pacchetto telemetria sensori
Dati calibrati -> Inoltro a cPanel completato [HTTP 200]

    

      ← Torna allo Step 3: Imposta DNS
      Vai allo Step 5: modifica Router →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 3](step3.md)
- ➡️ **Next:** [Step 5](step5.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
