# NewFoobot — Universal Mobile & Desktop Web App (PWA)

> **Versione:** 1.0.0 (Release Ufficiale Stabile)  
> **Sviluppatore:** Barbara Lerici  
> **Licenza:** GNU General Public License v3.0 (GPL-3.0)

Questa cartella contiene i sorgenti completi dell'applicazione universale **NewFoobot Mobile & Desktop**, realizzata come Progressive Web App (PWA) ad alte prestazioni, autonoma e indipendente da store di terze parti.

---

## ISTRUZIONE FONDAMENTALE DI CARICAMENTO SUL SERVER (DEPLOYMENT)

> [!IMPORTANT]
> **DOVE CARICARE QUESTA APPLICAZIONE SUL SERVER WEB:**
> Tutti i file e le cartelle presenti in questa directory (`4_mobile_app`) **DEVONO ESSERE CARICATI SUL SERVER WEB ALL'INTERNO DELLA SOTTOCARTELLA `/app/` DEL SITO CRUSCOTTO** (`3_web_dashboard_and_database`).

### Struttura cartelle sul server di produzione (Hosting / cPanel / VPS):

```text
/public_html/ (o root del dominio)
│
├── .htaccess
├── index.php                          <- Cruscotto Web Principale
├── mobile_app.php                     <- Pagina di Gestione Dispositivi & Accoppiamento
├── auth.php, settings.php, ...
├── api/                               <- Endpoint REST per telemetria e autenticazione
│   ├── device_status.php
│   ├── get_datapoints.php
│   └── device_auth.php
│
└── app/                               <- CARICARE QUI TUTTO IL CONTENUTO DI 4_mobile_app:
    ├── index.html                     <- Schermata principale PWA
    ├── manifest.json                  <- Manifest di installazione PWA
    ├── sw.js                          <- Service Worker per cache e offline
    ├── assets/
    │   ├── css/app.css
    │   ├── js/app.js, vendor/...
    │   ├── icons/                     <- Icone PWA (192px, 512px, maskable, SVG)
    │   └── img/                       <- Grafiche e icone fedeli all'App originale
    └── languages/                     <- Dizionari linguistici JSON (it, en, fr, es, de)
```

In questo modo l'applicazione sarà accessibile all'indirizzo web:
```text
https://tuodominio.it/app/
```

---

## Caratteristiche Principali

1. **Compatibilità Universale Cross-Platform**:
   - **Android**: Installabile nativamente da Google Chrome / Samsung Internet con prompt *"Installa applicazione"*.
   - **iPhone & iPad (iOS / iPadOS)**: Installabile da Safari toccando il tasto *Condividi* &rarr; *"Aggiungi alla schermata Home"*.
   - **PC Desktop (Windows, macOS, Linux)**: Installabile come programma desktop autonomo a finestra dedicata tramite Chrome, Microsoft Edge, Brave e Opera.
2. **Accoppiamento Trasparente e Sicuro (Zero Configuration)**:
   - **Collegamento Diretto da Cruscotto**: Dalla pagina `mobile_app.php` del sito cruscotto, cliccando su *"Apri App nel Browser"* o *"Apri App su Smartphone"*, l'applicazione si avvia associando automaticamente il dispositivo all'utente in 1 secondo (senza digitare codici o credenziali).
   - **Seriale Monouso (Pairing Ticket)**: Per collegare un telefono da computer, genera un seriale crittografico monouso (validità 10 minuti) da `mobile_app.php` e digitalo nell'App.
   - **Login Diretto con Credenziali**: Supporto al login tradizionale con Username/Email e Password del cruscotto.
3. **Grafica e Telemetria Fedele all'App Originale Foobot**:
   - Animazione dinamica ad anelli concentrici (cerchi Foobot) proporzionale all'indice globale di inquinamento.
   - Visualizzazione in tempo reale di VOC, PM2.5, CO2 equivalente, Temperatura e Umidità.
   - Grafico interattivo dell'andamento storico (ultime 24 ore, 7 giorni, 30 giorni) integrato con Chart.js.
4. **Multilingua Nativo (i18n)**:
   - Supporta 5 lingue: Italiano, Inglese, Francese, Spagnolo e Tedesco.
   - Rilevamento automatico della lingua del dispositivo con possibilità di cambio rapido manuale.
5. **Leggerezza e Affidabilità Offline**:
   - Peso totale inferiore a 2 MB.
   - Service Worker (`sw.js`) per caricamento istantaneo e funzionamento della cache anche in assenza temporanea di rete.

---

## English Summary

### Deployment Instruction

> [!IMPORTANT]
> All files in `4_mobile_app` (`index.html`, `manifest.json`, `sw.js`, `assets/`, `languages/`) **must be uploaded into the `/app/` subfolder of your web dashboard installation** (`3_web_dashboard_and_database`).
>
> Target URL: `https://your-domain.com/app/`

---

## Informazioni e Licenza

- **Sviluppatore**: [Barbara Lerici](https://barbaralerici.net/)
- **Sito Ufficiale Progetto**: [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
- **Licenza**: GNU General Public License v3.0 (GPL-3.0)
