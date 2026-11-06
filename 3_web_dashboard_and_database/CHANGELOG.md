# Registro Modifiche (Changelog) — NewFoobot Web Platform

Tutte le modifiche degne di nota apportate al progetto NewFoobot sono documentate in questo file.
Il formato e basato sulle linee guida ufficiali di *Keep a Changelog* e rispetta il Versionamento Semantico (*Semantic Versioning*).

---

## [1.0.0] - 2026-11-05

### 🚀 Release Ufficiale Stabile 1.0.0 (Gold Master)

#### 📱 Web App PWA Universale & Gestione Dispositivi (`/app/`)
- **Applicazione Client PWA**:
  - Rilascio dell'applicazione Progressive Web App completa per Android, iOS, Windows, macOS e Linux da caricare nella sottocartella `/app/` del cruscotto.
  - Grafica fedele con anelli di concentrazione Foobot, visualizzazione istantanea metriche (VOC, PM2.5, CO2, Temp, Hum) e storico grafico interattivo.
  - Supporto Service Worker (`sw.js`) per caching e funzionamento offline.
- **Accoppiamento & Gestione Dispositivi (`mobile_app.php`)**:
  - Collegamento trasparente automatico senza codici tramite sessione browser attiva.
  - Generatore di seriali di accoppiamento (ticket crittografici monouso con scadenza a 10 minuti) per autorizzazione dispositivi secondari.
  - Gestione e revoca granulare o massiva dei dispositivi connessi per singoli utenti o per l'intero sistema (pannello amministratore).

#### 🎨 Interfaccia Utente, Grafica e CSS Puro
- **Architettura CSS Puro Indipendente**:
  - Rimozione totale di qualsiasi dipendenza esterna da framework CSS (Tailwind CDN / JavaScript runtime esterni) per garantire conformita GDPR, massima velocita di caricamento ed esecuzione autonoma offline.
  - Implementazione di un motore di fogli di stile completo in `assets/css/style.css` con supporto bidirezionale nativo per Tema Chiaro (*Light Mode*) e Tema Scuro (*Dark Mode*) basato su variabili CSS (`var(--...)`).
  - Ottimizzazione responsive per smartphone, tablet, desktop e schermi ultra-wide.
- **Raffinamento Componenti Grafici della Dashboard**:
  - Schede e indicatori di soglia con sfondi pastello a contrasto cromatico ottimizzato (`Super`, `Buono`, `Medio`, `Cattivo`) e rimozione di bordi superflui per un design moderno e leggibile.
  - Barre di livello dinamiche con colorazione fedele per tutti gli inquinanti: ripristinata e garantita la visualizzazione a tinta unita della barra blu per eCO2 e VOC.
  - Pulsanti delle risoluzioni temporali con reset visivo delle ombre interne e degli sfondi di default, con evidenziazione netta della selezione attiva.
  - Frecce di navigazione cronologica del grafico (`#btn-chart-prev`, `#btn-chart-next`) con icone vettoriali SVG ad alta nitidezza, visibili e contrastate in entrambe le modalita cromatiche.
  - Box di inserimento giorni personalizzati ridisegnato con raggio di curvatura coerente, assenza di ombre interne e pulsante compatto di invio.
  - Modale informativo ufficiale Foobot con spiegazione dettagliata delle soglie di benessere, icona nitida geometrica vettoriale e pulsante di conferma "Ho capito" privo di bordi o ombre invadenti.

#### 📊 Grafico Interattivo e Logica Client-Side (`dashboard.js`)
- **Librerie JavaScript Autonome Locali**:
  - Integrazione locale in `assets/js/external/` di Chart.js v4.4.1 e del plugin di annotazione, azzerando qualsiasi chiamata di rete a CDN terze.
- **Scale Dinamiche ad Auto-Adattamento**:
  - Gestione intelligente dei picchi acuti di VOC (fino a 2000+ ppb) e particolato PM2.5 con espansione fluida dell'asse Y e visualizzazione corretta dei valori standard senza appiattimento delle curve.
- **Persistenza della Selezione Curve nella Legenda**:
  - Memorizzazione client-side di sessione (`userHiddenDatasets`) per mantenere lo stato attivo/disattivo delle singole metriche durante la navigazione tra le diverse risoluzioni temporali (minuti, ore, giorni).
- **Navigazione Cronologica Flessibile**:
  - Scorrimento temporale avanti e indietro con etichetta temporale dinamica, disabilitazione del tasto "Avanti" al raggiungimento del presente e pulsante "Oggi" per il ritorno immediato al tempo reale.

#### 👥 Gestione Utenti e Amministrazione (`settings.php`)
- **Protezione dell'Amministratore Primario**:
  - Riconoscimento automatico del primo account amministratore creato nel sistema (ID primario).
  - Blindatura contro cancellazioni accidentali e divieto di degradazione del ruolo, con badge identificativo dedicato ("Primario" / "Protetto").
- **Modale Interattivo di Modifica Utente**:
  - Pulsante di modifica dedicato (`.btn-edit`) per tutti gli account secondari (altri amministratori e visualizzatori).
  - Finestra modale per l'aggiornamento di Username (con controllo di univocita), Email, Ruolo di accesso e Nuova Password opzionale (con indicatore mostra/nascondi).
- **Cancellazione Utenti Sicura**:
  - Pulsante cestino (`.btn-trash.btn-trash-sm`) con richiesta di conferma JavaScript prima dell'invio e verifica token CSRF.
  - Divieto di eliminazione del proprio account attualmente in uso.

#### 💾 Sistema di Backup e Manutenzione
- **Doppio Canale di Backup (Sensori & Connessioni)**:
  - Esportazione programmata e manuale sia delle letture telemetriche dei sensori (`foobot_backup_*.csv`) sia del registro degli eventi e blackout del router/bridge (`foobot_connection_logs_*.csv`).
- **Gestione Completa dei File di Backup sull'Hosting**:
  - Elenco unificato dei backup disponibili ordinati cronologicamente con indicazione del tipo, data e peso in KB.
  - Pulsante "Scarica" per il download diretto protetto.
  - **Pulsante Cestino per la Cancellazione Singola**: icona vettoriale SVG con conferma utente, sanificazione del percorso contro attacchi di *Directory Traversal* e rimozione sicura dal filesystem.
  - Rotazione automatica con conservazione dei 30 file storici piu recenti.

#### 🔒 Sicurezza e Protezione di Grado Industriale
- **Anti-Brute-Force & Rate Limiting**: blocco progressivo temporaneo degli indirizzi IP dopo tentativi falliti di autenticazione.
- **Whitelist IP & Geo-Filtro**: accesso amministrativo ristretto ad IP autorizzati o al paese di residenza con protezione contro accessi da aree geografiche non autorizzate.
- **Separazione Ingestione Telemetria & Accesso Utenti**: endpoint `api/ingest.php` verificato con chiave segreta e vincolato all'IP del server bridge/Oracle, consentendo l'accesso utente indipendente da qualsiasi IP autorizzato.
- **Flusso Login Standard Semplificato**: accesso tramite credenziali (username/email e password) senza vincolo da 2FA passkey obbligatoria non configurata.
- **Endpoint Diagnostici Riservati**: accesso alle metriche live di `api/device_status.php` consentito esclusivamente agli amministratori autenticati o con chiave segreta.

#### 🌐 Internazionalizzazione Completa (i18n a 5 Lingue)
- Supporto multilingua integrale a 5 lingue: **Italiano (IT)**, **Inglese (EN)**, **Francese (FR)**, **Spagnolo (ES)** e **Tedesco (DE)** con commutatore immediato in testata e persistenza in sessione.
- File di dizionario JSON strutturati (`languages/*.json`) con copertura totale al 100% delle chiavi.

---

**Sviluppatore:** Barbara Lerici  
**Sito Web:** [barbaralerici.net](https://barbaralerici.net)  
**Portale del Progetto:** [newfoobot-restart.barbaralerici.net](https://newfoobot-restart.barbaralerici.net)
