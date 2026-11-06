# Step 5: modifica del router Wi-Fi (persistenza blackout)

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step5.html](http://newfoobot-restart.barbaralerici.net/en/step5.html)

---

### Cosa succede al Foobot quando manca la corrente (e quando questo Step può essere ignorato)

Anche se nello **Step 4** il Foobot ha salvato in memoria l'indirizzo IP e il DNS personalizzato, alla **perdita di corrente** (o se scolleghi l'alimentatore) il firmware interno ha un comportamento cablato di fabbrica: non appena si riaccende, **interroga comunque per primo il router di casa (via DHCP)** chiedendo quale server DNS usare, ignorando inizialmente l'IP di NextDNS memorizzato. L'unico modo definitivo per bloccare questo comportamento e renderlo immune ai blackout è impostare il DNS direttamente sul router.

        Hai già configurato la regola DNS direttamente nel tuo Router nello Step 3?
        Se nello Step 3 hai usato un router avanzato (es. FRITZ!Box, OpenWrt, MikroTik) inserendo lì la regola di reindirizzamento per broker-gw-nc.foobot.io, oppure se avevi già impostato NextDNS/Pi-hole nelle impostazioni DHCP del router, questo Step 5 non serve e può essere ignorato (passa direttamente allo Step 6), poiché il router fornirà già in automatico la risposta corretta a ogni riaccensione del Foobot.

## 1. Perché modificare il router?

Durante l'analisi del comportamento hardware, abbiamo verificato che **quando il Foobot si spegne per mancanza di corrente**, al riavvio il microcontrollore interno resetta la RAM e chiede un nuovo indirizzo IP e DNS al router tramite DHCP.

      Vantaggio della modifica al Router: Se inserisci l'IP di NextDNS direttamente nel router, ogni volta che torna la corrente il Foobot riceve subito il DNS corretto e riprende a trasmettere al tuo server in piena autonomia, senza che tu debba fare nulla dal computer!

## 2. Procedura passo-passo nel router

1. Accedi al pannello di amministrazione del tuo router da browser (solitamente digitando `192.168.1.1` o `192.168.0.1`).
2. Inserisci nome utente e password del router (stampati sull'etichetta dell'apparecchio).
3. Cerca la scheda **Rete Locale / LAN** oppure **Server DHCP / Impostazioni DNS**.
4. Alla voce **DNS Primario (Primary DNS)**, inserisci l'indirizzo IPv4 di NextDNS ricavato nello Step 2 (es. `45.90.28.xxx`).
5. Salva le impostazioni e riavvia il router.

## 3. Compromesso alternativo: usare il Foobot senza toccare il router

Se non hai accesso al router di casa, se è fornito bloccato dal tuo operatore telefonico o se preferisci non modificare le impostazioni di rete dell'intera abitazione, **puoi saltare tranquillamente questo passaggio**.

### Come gestire il Foobot con lo strumento dello Step 4

Senza la modifica al router, il Foobot funzioner&agrave; regolarmente trasmettendo i dati al tuo server Bridge. Tuttavia, il dispositivo potrebbe reimpostare il DNS ai valori di fabbrica o perdere il reindirizzamento in situazioni quali:

- **Interruzioni di corrente (blackout) o sbalzi di tensione** che riavviano il modulo Wi-Fi interno.
- **Rinnovo o cambio del lease DHCP da parte del router**, che pu&ograve; riassegnare un nuovo IP locale o sovrascrivere i parametri DNS della scheda di rete.

#### Cosa fare quando accade (senza toccare il router):

Non serve accedere al modem o modificare le impostazioni di casa. Ti baster&agrave; avviare dal computer il programma di gestione visto nello [Step 4](step4-device-setup.html) (il file `START_FOOBOT_MANAGER.bat` su Windows o lo script su Mac/Linux) e scegliere:

- **Opzione 3 • Riattivazione dopo sospensione corrente con lo stesso IP registrato:** &egrave; la funzione specifica per i blackout. Legge in automatico l'IP DNS gi&agrave; configurato in precedenza, rinnova il lease DHCP con il router riavviando il Foobot per 16 secondi, lo riblocca come IP statico e riapre il canale Cloud verso il Bridge (`TCPLKB = on`) con un solo tasto, senza dover reinserire nulla.
- **Opzione 2 • Configura server DNS personalizzato:** da utilizzare se desideri cambiare manualmente l'indirizzo IP del DNS o assegnarne uno nuovo.

        Monitoraggio automatico sul cruscotto web:
        Sul tuo sito web &egrave; gi&agrave; attivo il watchdog intelligente. Se il Foobot smette di inviare dati per oltre 30 minuti (a causa di un salto di corrente, di un'interruzione Wi-Fi o del riavvio del router), il cruscotto evidenzia lo stato di disconnessione e ti invia un avviso e-mail: saprai cos&igrave; con precisione quando &egrave; il momento di avviare il file .bat dello Step 4 e selezionare l'Opzione 3 per riattivare il flusso in 16 secondi.

    

      ← Torna allo Step 4: modifica Foobot
      Vai allo Step 6: Aggancia App →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 4](step4.md)
- ➡️ **Next:** [Step 6](step6.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
