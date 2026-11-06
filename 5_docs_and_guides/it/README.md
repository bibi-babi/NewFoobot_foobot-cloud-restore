# NewFoobot — Guide di Installazione e Datasheet Hardware

> **Componente:** 5_docs_and_guides/it  
> **Guida Ufficiale Interattiva:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)

---

## Guide Passo-Passo per l'Installazione

Questa cartella raccoglie la documentazione ufficiale in lingua italiana estratta dal sito web del progetto, formattata in Markdown per consultazione offline:

| Step | Documento | Obiettivo e Descrizione |
| :---: | :--- | :--- |
| **1** | [step1-web-dashboard.md](step1-web-dashboard.md) | **Installazione Cruscotto Web e Database (MySQL / MariaDB):** Preparazione hosting PHP, wizard interattivo `install.php`, creazione DB e passkey API. |
| **2** | [step2-mqtt-bridge.md](step2-mqtt-bridge.md) | **Configurazione Server Bridge MQTT:** Setup VPS Linux (Oracle Cloud Always Free o Raspberry Pi/NAS locale) con script `bridge.py` e servizio `systemd`. |
| **3** | [step3-dns-rewriting.md](step3-dns-rewriting.md) | **Regole di Riscrittura DNS (NextDNS / Pi-hole):** Reindirizzamento delle query DNS del Foobot da `api.foobot.io` verso l'IP del Bridge. |
| **4** | [step4-device-setup.md](step4-device-setup.md) | **Configurazione Foobot via Wi-Fi Locale:** Connessione al SoftAP e utilizzo del tool di configurazione multipiattaforma per Windows, macOS e Linux. |
| **5** | [step5-router-setup.md](step5-router-setup.md) | **Configurazione Router Wi-Fi (Persistenza Blackout):** Impostazione dei DNS sul router di casa per garantire la riconnessione automatica dopo interruzioni di corrente. |
| **6** | [step6-mobile-app.md](step6-mobile-app.md) | **Collegamento Applicazione Mobile & PWA:** Associazione dell'app PWA tramite Device UUID, installazione su smartphone e computer desktop. |

---

## Datasheet Hardware

- **`IAQ_CORE_C_Datasheet.pdf`**: Specifiche tecniche del sensore ScioSense IAQ-Core C integrato nel Foobot.

---

## Licenza

Il codice e la documentazione del progetto sono rilasciati sotto licenza **GNU General Public License v3.0 (GPL-3.0)**.  
I datasheet rimangono proprietà dei rispettivi produttori.
