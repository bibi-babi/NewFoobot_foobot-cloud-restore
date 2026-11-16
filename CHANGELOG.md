# Registro delle Modifiche (Changelog) — NewFoobot Revival Ecosystem

Tutte le modifiche degne di nota apportate al progetto **NewFoobot** sono documentate in questo file.
Il formato è basato sulle linee guida di [Keep a Changelog](https://keepachangelog.com/it/1.0.0/) e aderisce al [Semantic Versioning](https://semver.org/lang/it/).

---

## [1.0.1] - 2026-11-16

### 🛠️ Correzioni e Ottimizzazioni di Stabilità (Stability & Bugfix Release)

Questa versione introduce correzioni mirate alla robustezza delle riconnessioni hardware dopo assenza di corrente e all'affidabilità delle notifiche e dei cron di sistema.

#### 1. Configuratore Hardware Foobot (`1_foobot_device_config`)
- **File aggiornati**:
  - `Windows codice/foobot_manager.ps1`
  - `Mac codice/foobot_manager.py`
  - `linux codice/foobot_manager.py`
- **Modifica apportata**:
  - Riprogettata integralmente la procedura di **Opzione 3** (*Riattivazione Foobot dopo sospensione o caduta di corrente con lo stesso IP registrato*).
- **Motivo del problema**:
  - In precedenza l'Opzione 3 impostava temporaneamente il modulo in modalità DHCP (`AT+WANN=DHCP`) ed effettuava un riavvio forzato (`AT+Z` per 16 secondi). Al riavvio, il modulo negoziava i parametri con il router di casa acquisendo il server DNS predefinito del provider internet; quest'ultimo risolveva l'host originario del produttore (`broker-gw-nc.foobot.io`) verso il vecchio server AWS dismesso (`3.223.61.228`). Il modulo memorizzava tale IP in cache interna, impedendo la riconnessione al Bridge custom (lasciando `TCPLKB=+ok=off`), pur mostrando un falso esito positivo nello script.
- **Soluzione applicata**:
  - La procedura ora legge direttamente il server DNS custom già salvato nella memoria del Foobot via comandi AT (`AT+WSDNS`), senza effettuare alcun riavvio o passaggio in DHCP.
  - Fissa immediatamente l'IP statico e reinizializza il canale Socket B (TCP porta 1883) ripristinando la connessione cloud (`AT+TCPLKB=on`).
  - Aggiunta una verifica finale vincolante che attesta lo stato effettivo `TCPLKB=+ok=on` prima di confermare il successo all'utente.
  - Testi dei menu allineati e aggiornati sia in lingua italiana che inglese.

#### 2. Standalone Broker MQTT & Bridge Server (`2_mqtt_broker_bridge_server`)
- **File aggiornato**:
  - `2_mqtt_broker_bridge_server/bridge.py`
- **Modifica apportata**:
  - Aggiornata la funzione `trigger_cpanel_offline_alert()` per l'inoltro delle richieste di notifica disconnessione verso l'endpoint `cron_events.php` del sito web.
- **Motivo del problema**:
  - L'invio dell'API Key tramite semplice concatenazione di stringa nell'URL (`f"{cron_url}?key={API_KEY}"`) provocava il troncamento della query string nei casi in cui la Passkey segreta contenesse caratteri speciali come `#` (interpretato dai server web e dai browser come indicatore di frammento URL), determinando un errore HTTP 403 (Accesso Negato) e il mancato invio dell'email di blackout.
- **Soluzione applicata**:
  - Utilizzo del dizionario strutturato di parametri `params={"key": API_KEY}` della libreria `requests`, che applica automaticamente la corretta codifica URL conforme allo standard RFC 3986.
  - Aggiunta contestuale dell'header di sicurezza HTTP `X-Api-Key` per consentire l'autenticazione sia via header che via query parameter.
  - Aggiunti log diagnostici dettagliati per intercettare e registrare eventuali codici di stato HTTP imprevisti da cPanel.

#### 3. Piattaforma Web & Endpoint Cron (`3_web_dashboard_and_database`)
- **File aggiornato**:
  - `3_web_dashboard_and_database/cron_events.php`
- **Modifica apportata**:
  - Potenziamento della verifica di autenticazione per l'esecuzione dei cron job e delle allerte di disconnessione / blackout.
- **Motivo del problema**:
  - Alcuni ambienti di hosting condiviso (es. cPanel con interpreti FastCGI / `cgi-fcgi`) non riconoscono l'esecuzione CLI convenzionale (`php_sapi_name()` diverso da `'cli'`), bloccando l'esecuzione con HTTP 403 qualora non vi sia una sessione amministrativa aperta o la chiave venga trasmessa via header HTTP.
- **Soluzione applicata**:
  - Supporto per la lettura dell'header HTTP `X-Api-Key` (tramite `getallheaders()` o variabili server `HTTP_X_API_KEY`).
  - Supporto di fallback su molteplici parametri di autenticazione (`$_GET['key']`, `$_GET['api_key']`, `$_POST['key']`, `$_POST['api_key']`).
  - Estensione del rilevamento automatico della modalità CLI per contesti FastCGI cPanel privi di `REMOTE_ADDR` e `HTTP_HOST`.

---

## [1.0.0] - 2026-11-06

### 🚀 Release Ufficiale Stabile 1.0.0 (Gold Master)

Rilascio iniziale pubblico dell'ecosistema completo NewFoobot per il ripristino autonomo e open source dei sensori Airboxlab Foobot:

#### 1. Configuratore Hardware Multipiattaforma (`1_foobot_device_config`)
- Script nativo PowerShell per ambiente Windows (`foobot_manager.ps1`) e script Python per macOS e Linux (`foobot_manager.py`).
- Riconfigurazione locale via Wi-Fi SoftAP senza apertura fisica o modifiche hardware al sensore.
- Menu interattivo bilingue (Italiano / Inglese) per lettura registri firmware, impostazione IP statico/DNS e sblocco parametri di fabbrica.

#### 2. Standalone Broker MQTT & Bridge Server v3.0 (`2_mqtt_broker_bridge_server`)
- Implementazione nativa in Python di un server Broker MQTT v3.1/v3.1.1 su porta TCP 1883 in grado di accettare i pacchetti legacy `MQIsdp` del Foobot.
- Applicazione delle formule di calibrazione ufficiali Airboxlab per conversione di temperatura, umidità, particolato PM2.5, VOC ed eCO2.
- Inoltro sicuro delle letture telemetriche al cruscotto web tramite HTTP/HTTPS POST a `ingest.php`.
- Watchdog Zero-Ping integrato: segnalazione immediata dei blackout dopo 10 minuti di inattività e trigger per email di allerta dopo 30 minuti.
- Servizio di sistema Linux `systemd` pronto all'uso per VPS (Oracle Cloud, AWS, DigitalOcean o server locale).

#### 3. Cruscotto Web & Database MariaDB/MySQL (`3_web_dashboard_and_database`)
- Architettura responsive realizzata in CSS puro indipendente da framework esterni (nessun caricamento Tailwind/CDN), per massima velocità e rispetto privacy.
- Supporto nativo per Tema Chiaro (*Light Mode*) e Tema Scuro (*Dark Mode*).
- Grafico interattivo multi-metrica basato su Chart.js con librerie incluse localmente, scale ad auto-adattamento dinamico per i picchi acuti e persistenza della legenda.
- Sistema di autenticazione multi-utente con protezione dell'amministratore primario, gestione ruoli, CSRF token e rate limiting.
- Esportazione programmata e manuale dei backup telemetrici e log di connessione in formato CSV con rotazione automatica dei file.
- Supporto multilingua completo (i18n) a 5 lingue: Italiano, Inglese, Francese, Spagnolo e Tedesco.

#### 4. Applicazione Universale Mobile & Desktop PWA (`4_mobile_app`)
- Progressive Web App autonoma distribuibile nella cartella `/app/` del cruscotto web.
- Grafica fedele con anelli di concentrazione Foobot, visualizzazione istantanea metriche e storico grafico.
- Service Worker per funzionamento offline e installabilità come app nativa su dispositivi Android, iOS, Windows, macOS e Linux.
- Accoppiamento dispositivi sicuro tramite token temporanei e gestione centralizzata delle autorizzazioni.

---

**Sviluppatore:** Barbara Lerici  
**Repository GitHub:** [NewFoobot_foobot-cloud-restore](https://github.com/bibi-babi/NewFoobot_foobot-cloud-restore)  
**Portale del Progetto:** [newfoobot-restart.barbaralerici.net](http://newfoobot-restart.barbaralerici.net/)
