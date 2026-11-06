# NewFoobot — Implementation Guides & Hardware Datasheets

> **Component:** 5_docs_and_guides  
> **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)

---

## Complete Step-by-Step Implementation Guides

This directory contains the full documentation extracted from the official project website, available in Markdown format for offline consultation and version control:

| Step | Document | Goal & Description |
| :---: | :--- | :--- |
| **1** | [step1-web-dashboard.md](step1-web-dashboard.md) | **Install Web Dashboard & Database (MySQL / MariaDB):** Prepare PHP web hosting, run the interactive installer, create the database, and define the API passkey. |
| **2** | [step2-mqtt-bridge.md](step2-mqtt-bridge.md) | **MQTT Bridge Server Setup:** Set up a cloud VPS (Oracle Cloud Always Free) or local server (Raspberry Pi/NAS) to run `bridge.py` 24/7 with systemd. |
| **3** | [step3-dns-rewriting.md](step3-dns-rewriting.md) | **Setup DNS Rewrite Rules (NextDNS / Pi-hole):** Intercept Foobot DNS queries for `api.foobot.io` and redirect traffic to your Bridge Server IP. |
| **4** | [step4-device-setup.md](step4-device-setup.md) | **Configure Foobot via Local Wi-Fi:** Run the cross-platform setup utility on your computer to connect Foobot to your home Wi-Fi and set DNS rules. |
| **5** | [step5-router-setup.md](step5-router-setup.md) | **Wi-Fi Router Setup (Blackout Persistence):** Configure your home router's DNS settings so the Foobot reconnects automatically after a power outage. |
| **6** | [step6-mobile-app.md](step6-mobile-app.md) | **Connect Mobile & PWA Application:** Pair the Progressive Web App client with your device ID and install it on smartphones and desktop computers. |

---

## Directory Organization

- **`step1-web-dashboard.md` … `step6-mobile-app.md`**: Individual step-by-step implementation guides in English.
- **`en/`**: English documentation, step guides ([English README](en/step1-web-dashboard.md)), and sensor datasheets (e.g. ScioSense IAQ-Core C).
- **`it/`**: Italian documentation, step guides ([Guida in italiano](it/README.md)), and sensor datasheets.

For interactive guides with screenshots, visit the official website:  
👉 **[http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)**

---

## License

All project code and documentation are licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
Datasheets remain the property of their respective manufacturers.
