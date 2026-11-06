# Step 3: Imposta regola DNS (NextDNS o Pi-hole)

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step3.html](http://newfoobot-restart.barbaralerici.net/en/step3.html)

---

    > [!NOTE]
> **Summary & Goal of Step 3**
> In questa fase predisponiamo una regola di riscrittura DNS per intercettare il traffico del Foobot. Poich&eacute; il nome a dominio ufficiale &egrave; impresso indelebilmente nel firmware del sensore, facciamo puntare il DNS personalizzato verso il nostro Server Bridge (avviato allo Step 2): in questo modo il dispositivo contatter&agrave; il suo consueto indirizzo fidato, ma le risposte e la ricezione dei dati saranno gestite interamente dalla nostra infrastruttura locale o remota.

## 1. Il principio del reindirizzamento DNS

Il modulo Wi-Fi integrato nel Foobot custodisce all'interno del proprio firmware una configurazione rigida e non modificabile: cerca costantemente e unicamente il nome a dominio `broker-gw-nc.foobot.io`.

Non esiste alcuna procedura, comando o impostazione per sostituire tale indirizzo URL nel chip del dispositivo. L'unico parametro di rete che il Foobot accetta di configurare &egrave; l'**indirizzo IP del Server DNS** che deve consultare per tradurre quel nome di dominio.

Siamo pertanto obbligati a sfruttare questo meccanismo:

- Istruiamo il Foobot ad interrogare il **nostro Server DNS dedicato** (es. NextDNS o Pi-hole).
- Quando il sensore chiede a questo DNS dove si trovi `broker-gw-nc.foobot.io`, il nostro resolver non contatter&agrave; i vecchi server dismessi di Airboxlab, ma risponder&agrave; fornendo l'**indirizzo IP del nostro Server Bridge** (predisposto nello Step 2).

      L'inganno trasparente: In questo modo il Foobot creder&agrave; di comunicare con il suo originale fornitore cloud di fiducia, trovando l'esatto URL di cui ha bisogno, mentre in realt&agrave; recapiter&agrave; tutte le letture in tempo reale direttamente al nostro sistema autonomo.

    

## 2. Panoramica dei servizi DNS con supporto riscritture (free e premium)

Per applicare questa regola di riscrittura (DNS Rewrite o DNS Spoofing controllato), &egrave; possibile ricorrere a diverse piattaforme nel web, sia gratuite che professionali a pagamento, oppure a server locali:

    

Scorri orizzontalmente per visualizzare l'intera tabella

| Piattaforma DNS | Tipologia | Funzionalit&agrave; & Note | Collegamento |
| --- | --- | --- | --- |
| **NextDNS** | Gratuito (300k query) | Cloud resolver avanzato con pannello web intuitivo. Include riscritture DNS (Rewrites), IP IPv4 personalizzati dedicati e blocco telemetrie. Piano gratuito ideale per l'uso continuo di NewFoobot. | [nextdns.io →](https://nextdns.io/) |
| **Pi-hole (Self-hosted)** | Open Source Free | DNS server locale installabile su Raspberry Pi, mini PC o Docker. Consente record DNS locali (Local DNS Records) per reindirizzare il dominio direttamente all'interno della rete domestica. | [pi-hole.net →](https://pi-hole.net/) |
| **AdGuard Home / DNS** | Free / Premium | Disponibile sia come software self-hosted sia come servizio cloud gestito. Supporta riscritture DNS e regole personalizzate tramite interfaccia grafica pulita. | [adguard-dns.io →](https://adguard-dns.io/) |
| **Cloudflare Zero Trust** | Free Tier (Fino a 50 utenti) | Soluzione cloud professionale enterprise con policy DNS avanzate, risoluzione personalizzata e protezione elevata. Configurazione leggermente pi&ugrave; complessa per utenti alle prime armi. | [cloudflare.com →](https://one.dash.cloudflare.com/) |
| **Control D** | A Pagamento / Trial | Servizio DNS moderno ad alte prestazioni con profili personalizzati, proxy integrato e supporto completo alla riscrittura di domini e IP. | [controld.com →](https://controld.com/) |

#### Hai un router di casa avanzato? Puoi fare tutto direttamente da lì!

        Se il tuo router domestico integra funzionalit&agrave; avanzate di gestione di rete o un firmware aperto (come **AVM FRITZ!Box**, **OpenWrt**, **pfSense / OPNsense**, **MikroTik RouterOS**, **GL.iNet** o **Asuswrt-Merlin**), non hai bisogno di alcun servizio cloud esterno:

- **Host Statici / Local DNS / DNS Masq**: puoi creare direttamente nel router un record statico che associa `broker-gw-nc.foobot.io` all'indirizzo IP del tuo Server Bridge.
- **Server DNS da comunicare al Foobot**: in questo caso l'indirizzo DNS da assegnare al sensore sar&agrave; semplicemente l'IP locale del tuo router (es. `192.168.1.1`).

*Nota:* I comuni modem/router base forniti dagli operatori telefonici (TIM, Vodafone, Fastweb, WindTre, Iliad) solitamente bloccano o non offrono la riscrittura DNS locale; per questo motivo, nella guida illustriamo **NextDNS** come soluzione universale accessibile a chiunque.

        Vai alle Soluzioni per Router & Server Locali &darr;

    

## 3. L'essenziale da configurare in qualunque servizio DNS

Indipendentemente dalla piattaforma scelta (NextDNS, Pi-hole, AdGuard, DNS del router o server dedicato), per far funzionare NewFoobot sono necessari esclusivamente **due parametri chiave**:

      

#### Regola di riscrittura (Rewrite / Local DNS)

- **Nome Dominio da intercettare**: `broker-gw-nc.foobot.io`
- **Indirizzo IP di Risposta**: l'indirizzo IP della macchina su cui girer&agrave; il Server Bridge (Step 2). Pu&ograve; essere l'IP locale (es. `192.168.1.50`) o l'IP pubblico di una VPS (es. Oracle Cloud).

Qualunque DNS supporti record DNS A statici o riscritture eseguir&agrave; questo compito in modo trasparente.

      

#### Indirizzo IP del server DNS da assegnare al Foobot

Annota l'**indirizzo IPv4** del tuo server DNS (es. il primo IP di NextDNS `45.90.28.xxx` o l'IP locale del Pi-hole `192.168.1.2`).

Questo sar&agrave; l'unico valore che inietteremo nel microcontrollore del Foobot durante lo Step 4 per fargli interrogare il server giusto.

    

## 4. Procedura operativa: configurazione su NextDNS (modello della guida)

Procedura testata sul campo

      Per consentire a chiunque di completare il progetto senza installare macchine locali o configurare server complessi, in questa guida adottiamo **NextDNS** come modello di riferimento gratuito:

1. Registra un account gratuito su [nextdns.io →](https://nextdns.io/) (fondamentale: senza registrazione le impostazioni andranno perse alla chiusura del browser).
2. Crea un nuovo profilo dedicato cliccando sul nome in alto a sinistra (es. **«Crea profilo»** → chiamalo *Foobot*).                    **Attenzione al profilo attivo:** Se ricarichi la pagina o effettui un nuovo accesso a NextDNS in un secondo momento, l'interfaccia potrebbe reimpostarsi automaticamente sul profilo predefinito (*Default*) mostrando una configurazione apparentemente vuota e priva delle tue regole. Non allarmarti: ti baster&agrave; cliccare sul menu a tendina in alto e riselezionare il tuo profilo *Foobot*.
3. Nella scheda **Setup (Guida all'installazione / Installazione)**:          - Individua il box **IP associato** e clicca su **«Associa IP»** (serve per autorizzare la tua linea di casa verso questo profilo). - Individua la riga **Server DNS (IPv4)** e **annotati il primo indirizzo IP numerico** (indicato dalla freccia verde nello screenshot, es. `45.90.28.xxx`): sar&agrave; il DNS da assegnare al Foobot nello Step 4.                                                     Scheda *Installazione* su NextDNS: riquadro *IP associato* e gli indirizzi numerici *Server DNS* evidenziati dalla freccia.
4. Accedi alla scheda **Settings (Impostazioni)**, scorri fino alla sezione **Rewrites (Riscritture)** e clicca sul pulsante blu **«Nuova riscrittura»** (Add a rewrite):          - **Domain (Dominio)**: `broker-gw-nc.foobot.io` (oppure `*.broker-gw-nc.foobot.io`) - **Answer (Risposta)**: inserisci l'indirizzo IP del tuo Server Bridge dello Step 2 (l'IP pubblico della tua VPS Oracle Cloud oppure l'IP locale del tuo PC).                                                     Scheda *Impostazioni → Riscritture* su NextDNS: pulsante *Nuova riscrittura* e regola attiva verso l'IP del Bridge.
5. Salva la riscrittura.

    

**Guida dettagliata pannello NextDNS: schede sicurezza, privacy, log e IP dinamico**

            NextDNS offre un controllo capillare su decine di filtri. Per evitare che blocchi involontariamente le comunicazioni del Foobot o i payload JSON, ecco la configurazione collaudata scheda per scheda:

          

#### 1. Scheda sicurezza (Security)

**Voci da mantenere tassativamente DISATTIVATE (per non bloccare il Foobot):**

- **Indirizzi di rete locale (DNS rebinding)**: SPENTO (indispensabile se il Bridge &egrave; su IP locale privato).
- **Servizi di deposito dati (Data dump services / Webhooks)**: SPENTO (evita il blocco di webhook o invii dati).
- **Domini attivi da poco (Newly active domains)**: SPENTO.
- **Hosting residenziale (Residential hosting)**: SPENTO.
- **Endpoint di tunneling** e **Domini di hosting gratuito**: SPENTO.

**Protezioni standard da mantenere ATTIVE:**

Feed di intelligence sulle minacce, Rilevamento minacce con AI, Navigazione sicura di Google, Protezione cryptomining, Algoritmi DGA, Typosquatting.

          

#### 2. Scheda privacy

- **Liste di blocco (Blocklists)**: puoi lasciare la lista predefinita NextDNS oppure rimuoverla. Il Foobot non naviga e contatta unicamente il proprio broker.
- **Protezione dal tracciamento nativo**: DISATTIVATA (il firmware IoT ha uno stack leggero).
- **Blocca i tracker mascherati (CNAME)**: DISATTIVATA.

          

#### 3. Scheda controllo parentale e liste consentiti/rifiutati

**Disattivare tutto** nel Controllo Parentale e lasciare sia la *Allowlist* che la *Denylist* completamente vuote. Filtri su restrizioni di navigazione o VPN/Tor potrebbero creare falsi positivi sulle porte aperte del dispositivo.

          

#### 4. Scheda log (registri in tempo reale)

La scheda **Log** &egrave; il miglior strumento diagnostico: quando il Foobot &egrave; operativo, vedrai comparire la query per `broker-gw-nc.foobot.io` contrassegnata con l'etichetta verde **Rewritten**, confermando che il Foobot sta interrogando regolarmente il servizio e riceve l'IP corretto.

          

#### 5. Gestione per connessioni domestiche con IP dinamico

Se la tua connessione internet di casa non possiede un IP statico, ogni riavvio del modem cambier&agrave; il tuo IP pubblico. Per evitare che NextDNS smetta di applicare il profilo *Foobot*:

- Nella scheda **Setup** → *IP associato* clicca su **Dati avanzati**.
- NextDNS fornisce un URL personale segreto: `https://link-ip.nextdns.io//`.
- Salvalo nei preferiti: basta aprirlo dal browser da casa per riallineare l'IP in un secondo se il Foobot dovesse perdere la connessione, oppure integrarlo nel DDNS del router.

    

### Tabella di riepilogo operativo NextDNS

Ecco la sintesi ordinata di tutte le azioni da compiere su NextDNS e i valori chiave da estrarre:

    

Scorri orizzontalmente per visualizzare l'intera tabella

| Passo Operativo | Azione da Compiere | Dove Trovarlo nel Pannello | Parametro & Scopo |
| --- | --- | --- | --- |
| **1. Accesso** | Registrazione account gratuito e login | In alto a destra (*Accedi* / *Sign in*) | Conserva e rende permanenti le tue impostazioni nel cloud. |
| **2. Profilo** | Creazione profilo dedicato (es. *Foobot*) | Menu profilo in alto a sinistra → *Crea profilo* | Isola le regole del sensore senza alterare la normale navigazione di casa. |
| **3. Associazione IP** | Collega la tua linea internet di casa | Scheda *Setup* → Sezione *IP associato* | Clic su **«Associa IP»** (e link in *Dati avanzati* per connessioni con IP dinamico). |
| **4. Server DNS** | **Annota il 1&deg; indirizzo DNS IPv4** | Scheda *Setup* → Riga *Server DNS* | Valore numerico (es. `45.90.28.xxx`) da iniettare nel Foobot allo Step 4. |
| **5. Riscrittura** | Reindirizza il dominio ufficiale verso il Bridge | Scheda *Settings* → *Rewrites* → *Add a rewrite* | Dominio: `broker-gw-nc.foobot.io`Risposta: `` |

## 5. Soluzioni alternative per utenti avanzati (router di casa / Pi-hole / AdGuard)

      Se non desideri affidarti a NextDNS e preferisci gestire la risoluzione dei nomi internamente alla tua rete, puoi ottenere lo stesso identico risultato impostando la regola di riscrittura locale su:

- **Pi-hole (Self-hosted)**: accedi alla dashboard → Menu *Local DNS* → *DNS Records* → Aggiungi il dominio `broker-gw-nc.foobot.io` associandolo all'indirizzo IP del tuo Server Bridge.
- **AdGuard Home**: accedi al pannello → Menu *Filtri* → *Riscritture DNS* → Aggiungi il dominio e l'IP del Bridge.
- **Router di Casa con Firmware Dedicato**: configurando un host statico o una direttiva DNS locale direttamente nel modem/router di casa.

    

## 6. Configurazione del router di casa al posto del modello NextDNS

Procedura alternativa non collaudata direttamente

      A differenza del modello ufficiale NextDNS, le istruzioni per il router variano notevolmente a seconda del produttore e del firmware installato. Rappresentano una linea guida concettuale per router che offrono opzioni avanzate:

    

#### Requisiti minimi del router di casa

Il router deve possedere almeno una di queste due funzionalit&agrave; software nel proprio pannello amministrativo:

- **DNS Host Mapping / Riscrittura DNS locale (Local DNS / DNSmasq)**: la capacit&agrave; di associare un nome a dominio arbitrario (`broker-gw-nc.foobot.io`) a uno specifico indirizzo IP senza interrogare i server DNS pubblici di Internet.
- **Server DNS personalizzabile via DHCP**: la possibilit&agrave; di comunicare ai dispositivi collegati in Wi-Fi l'indirizzo IP del proprio server DNS interno (es. `192.168.1.1` o l'IP di un Pi-hole locale).

#### Modelli e marche certamente compatibili:

- **AVM FRITZ!Box**: menu *Rete Locale → Nomi host DNS* (con eccezione DNS Rebind).
- **OpenWrt / DD-WRT**: gestione nativa *Dnsmasq* con direttiva `address=/.../IP`.
- **GL.iNet**: interfaccia AdGuard Home o Dnsmasq integrata di serie.

- **MikroTik RouterOS**: menu *IP → DNS → Static* per record FQDN.
- **pfSense / OPNsense**: modulo resolver *Unbound DNS → Host Override*.
- **Asus (Asuswrt-Merlin)**: supporto direttive personalizzate `hosts.add`.

      

#### Procedura guida universale per qualunque router compatibile

1. **Accesso al Router**: apri il browser web da un computer o smartphone connesso alla rete Wi-Fi di casa e digita l'indirizzo gateway del tuo router (solitamente `192.168.1.1` o `192.168.0.1`), inserendo le credenziali di amministratore.
2. **Individua la sezione DNS o Rete Locale**: cerca nel menu impostazioni avanzate voci quali *Rete Locale (LAN)*, *DHCP Server*, *DNS Statico*, *Host Nomi* o *DNSmasq*.
3. **Inserisci la Regola di Riscrittura (DNS Host)**:            - **Nome Host / Dominio FQDN**: `broker-gw-nc.foobot.io` - **Indirizzo IP di Risposta**: inserisci l'indirizzo IP del tuo Server Bridge dello Step 2 (es. l'IP pubblico della tua VPS oppure l'IP locale `192.168.1.50` se il Bridge &egrave; sul tuo PC di casa).
4. **Eccezione DNS Rebinding (Fondamentale se il Bridge &egrave; in LAN)**: se il tuo Server Bridge si trova su un indirizzo IP privato di casa (es. `192.168.x.x`), molti router bloccano la risposta per sicurezza contro attacchi web. Cerca la sezione *Protezione DNS Rebind* e aggiungi un'eccezione esplicita per il dominio `foobot.io` o `broker-gw-nc.foobot.io`.
5. **Applica e Salva**: salva la configurazione e riavvia il router se richiesto. Da questo momento, quando nello Step 4 configurerai il Foobot, come IP DNS indicherai semplicemente l'indirizzo IP del tuo router (es. `192.168.1.1`).

    

      ← Torna allo Step 2: server Bridge
      Vai allo Step 4: Configura Foobot →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 2](step2.md)
- ➡️ **Next:** [Step 4](step4.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
