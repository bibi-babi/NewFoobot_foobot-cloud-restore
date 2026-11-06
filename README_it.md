# NewFoobot — Sistema Open Source per il Recupero dei Monitor di Qualità dell'Aria

[![License: GPL v3](https://img.shields.io/badge/Licenza-GPLv3-blue.svg)](LICENSE)
[![Python: 3.8+](https://img.shields.io/badge/Python-3.8+-brightgreen.svg)](https://www.python.org/)
[![PHP: 7.4 | 8.x](https://img.shields.io/badge/PHP-7.4%20|%208.x-777bb4.svg)](https://www.php.net/)
[![MQTT: v3.1 / v3.1.1](https://img.shields.io/badge/MQTT-v3.1%20|%20v3.1.1-orange.svg)](https://mqtt.org/)
[![PWA: Pronta](https://img.shields.io/badge/PWA-Pronta-purple.svg)](4_mobile_app/)

*Read this in other languages: [English](README.md) | [Italiano](README_it.md)*

> **Guida Ufficiale Interattiva e Documentazione Passo-Passo:**  
> 🌐 **[http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)**

---

## Panoramica del Progetto

**NewFoobot** è un ecosistema completo, self-hosted e open-source progettato per riportare in vita i monitor di qualità dell'aria per interni **Airboxlab Foobot**. In seguito alla chiusura definitiva dei server cloud da parte del produttore originale, tutti i dispositivi Foobot funzionanti sono diventati inutilizzabili a causa della dipendenza dal broker proprietario spento.

Questo progetto fornisce l'intero stack sostitutivo per ripristinare il corretto funzionamento dell'hardware Foobot senza dipendere da alcun servizio terzo proprietario:
- **Nessuna modifica hardware o saldatura**: Funziona con il firmware di fabbrica originale Foobot non modificato.
- **Riconfigurazione Wi-Fi locale**: Utility multipiattaforma dedicata con interfaccia guidata (Windows, macOS, Linux).
- **Bridge e Broker MQTT Nativo Standalone**: Gestore integrato che accetta i pacchetti legacy MQTT v3.1 (`MQIsdp`) del firmware e inoltra le telemetrie calibrate via HTTP/HTTPS POST.
- **Watchdog Zero-Ping**: Monitoraggio in tempo reale di blackout, cadute di linea e assenza segnale senza ping a vuoto verso il server web.
- **Dashboard Web Responsive**: Telemetria live, archivio storico, calcolo indici ambientali, avvisi email SMTP, gestione multi-dispositivo e supporto multilingua (IT, EN, DE, FR, ES).
- **PWA Universale Mobile & Desktop**: Progressive Web App installabile con cache offline e layout responsive.

---

## Architettura del Sistema

```text
+-----------------------+
|  Dispositivo Foobot   |
| (Firmware Originale)  |
+-----------+-----------+
            |
            | 1. Configurazione locale Wi-Fi da PC
            |    (Windows / macOS / Linux)
            v
+-------------------------------------------------------+
| 1_foobot_device_config (foobot_manager.ps1 / .py)     |
| - Connessione al SoftAP temporaneo Foobot             |
| - Reindirizzamento IP broker verso la VPS Cloud       |
+-------------------------------------------------------+
            |
            | 2. Protocollo MQTT v3.1 nativo (Porta TCP 1883)
            v
+-------------------------------------------------------+
| 2_mqtt_broker_bridge_server (bridge.py)               |
| - Broker MQTT standalone e adattatore di protocollo   |
| - Calibrazione sensori e formula ufficiale Allpollu   |
| - Watchdog Zero-Ping (Blackout a 10m / Allerta a 30m) |
+-------------------------------------------------------+
            |
            | 3. Chiamata autenticata HTTPS POST (/api/ingest.php)
            v
+-------------------------------------------------------+
| 3_web_dashboard_and_database (PHP 7.4/8.x + MySQL)    |
| - Archiviazione MySQL e API ricezione dati            |
| - Cruscotto web responsive e log diagnostici live     |
| - Notifiche email via SMTP (Blackout e picchi PM)     |
+-------------------------------------------------------+
            |
            | 4. Endpoint REST API (/api/get_datapoints.php)
            v
+-------------------------------------------------------+
| 4_mobile_app (Progressive Web App - PWA)              |
| - Interfaccia Smartphone, Tablet e Computer Desktop   |
| - Indicatori live, grafici storici interattivi        |
| - Supporto multilingua (IT, EN, DE, FR, ES)           |
+-------------------------------------------------------+
```

---

## Struttura della Repository

| Cartella | Descrizione |
| :--- | :--- |
| **`1_foobot_device_config/`** | Script di configurazione guidata per Windows (`.ps1`, `.bat`), Linux (`.py`, `.sh`) e macOS (`.py`, `.command`) per associare il Foobot al Wi-Fi e al server bridge. |
| **`2_mqtt_broker_bridge_server/`** | Script Python 3 del broker MQTT standalone (`bridge.py`) con template del servizio `systemd` per Oracle Cloud Free Tier o qualsiasi VPS Linux. |
| **`3_web_dashboard_and_database/`** | Piattaforma web di gestione in PHP, API di ingestione (`/api/`), schema del database (`schema.sql`), installazione guidata (`install.php`), cruscotto e avvisi SMTP. |
| **`4_mobile_app/`** | Progressive Web App (PWA) autonoma da posizionare all'interno della cartella `/app/` del cruscotto web. |
| **`5_docs_and_guides/`** | Datasheet tecnici dei sensori interni (es. ScioSense IAQ-Core C) e documentazione di riferimento. |

---

## Funzionalità Principali

### 1. Supporto Completo MQTT v3.1 Legacy
Il firmware Foobot originale usa la variante legacy `MQIsdp` con header di protocollo MQTT v3.1, che provoca disconnessioni immediate sui broker moderni (come Mosquitto v2.x con controlli di conformità rigidi). Il bridge `bridge.py` include un gestore di protocollo nativo e permissivo che gestisce:
- Handshake `CONNECT` e autenticazione;
- Sottoscrizioni `SUBSCRIBE` e `UNSUBSCRIBE` con flag legacy;
- Ricezione pacchetti `PUBLISH` su topic `device/{uuid}/sensor/push`;
- Battito cardiaco `PINGREQ` inviato dal dispositivo ogni 10 secondi.

### 2. Calibrazione dei Sensori e Indice Ufficiale Allpollu
I valori grezzi trasmessi dai sensori interni vengono convertiti in tempo reale applicando i fattori ufficiali del firmware:
- **Temperatura:** `Valore / 1000.0` (°C)
- **Umidità Relativa:** `Valore / 1000.0` (%)
- **Polveri Sottili (PM2.5):** `Valore / 94.2` (µg/m³)
- **Composti Organici Volatili (VOC):** ppb (equivalente isobutilene)
- **Indice Globale Allpollu (0–100):** Regressione quadratica ufficiale che combina le concentrazioni normalizzate di particolato e composti volatili.

### 3. Watchdog Intelligente Zero-Ping
Il bridge evita il polling continuo verso l'hosting web, risparmiando banda e risorse:
- **Funzionamento regolare:** Nessun ping a vuoto, il Foobot invia le telemetrie ogni 5 minuti;
- **Dopo 10 minuti di inattività:** Segnala tempestivamente al database l'evento di blackout o caduta linea;
- **Dopo 30 minuti totali di assenza:** Esegue una singola chiamata mirata a `cron_events.php` per inoltrare l'email di allerta all'amministratore.

---

## Guida Rapida di Installazione

### Passo 1: Installare Cruscotto Web e Database
1. Carica i file di `3_web_dashboard_and_database/` sul tuo spazio web (es. cPanel hosting o VPS).
2. Carica i file di `4_mobile_app/` all'interno della cartella `/app/` del cruscotto web.
3. Apri nel browser l'indirizzo `https://tuo-dominio.it/install.php` e completa l'installazione guidata.
4. Annota le credenziali del database e la **Passkey API**.

### Passo 2: Configurare il Server Bridge MQTT
1. Attiva una macchina virtuale Linux (es. Oracle Cloud Always Free VM).
2. Apri la porta TCP **1883** nella Security List del cloud e nelle regole `iptables` locali.
3. Posiziona `bridge.py` nella cartella `~/foobot-bridge/` e configura `INGEST_URL` e `API_KEY`.
4. Attiva il servizio in background con `foobot-bridge.service` tramite `systemd`.

### Passo 3: Configurare il Dispositivo Foobot
1. Accendi il Foobot e avvia la sequenza di associazione (capovolgendo il dispositivo o premendo il pulsante dedicato).
2. Esegui il programma corrispondente al tuo sistema operativo dentro `1_foobot_device_config/`:
   - **Windows:** Esegui `START_FOOBOT_MANAGER.bat`
   - **Linux:** Esegui `./START_FOOBOT_MANAGER.sh`
   - **macOS:** Esegui `START_FOOBOT_MANAGER.command`
3. Inserisci i dati della tua rete Wi-Fi e l'indirizzo IP del tuo Server Bridge.

### Passo 4: Monitoraggio e Utilizzo
- Accedi alla dashboard web: `https://tuo-dominio.it/`
- Accedi all'applicazione mobile PWA: `https://tuo-dominio.it/app/`
- Le letture ambientali verranno visualizzate e registrate automaticamente a intervalli di 5 minuti.

---

## Documentazione Ufficiale e Guide Illustrate

Per guide visive passo-passo con screenshot e procedure dettagliate per ogni fase dell'installazione, visita il portale ufficiale del progetto:

👉 **[http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)**

---

## Come Contribuire

Contributi, segnalazioni di bug e suggerimenti di miglioramento sono benvenuti. Consulta il file [CONTRIBUTING.md](CONTRIBUTING.md) per informazioni sugli standard di codice, localizzazione multilingua e invio di pull request.

---

## Sicurezza

Per segnalare anomalie o vulnerabilità di sicurezza, fai riferimento alle istruzioni contenute in [SECURITY.md](SECURITY.md).

---

## Licenza

Questo progetto è rilasciato sotto i termini della licenza **GNU General Public License v3.0 (GPL-3.0)**.  
Consulta il file [LICENSE](LICENSE) per il testo integrale e i dettagli sul copyright.

Copyright (C) 2026 Barbara Lerici.
