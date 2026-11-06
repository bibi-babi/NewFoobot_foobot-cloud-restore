# NewFoobot — Open Source Air Quality Monitor Revival System

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![Python: 3.8+](https://img.shields.io/badge/Python-3.8+-brightgreen.svg)](https://www.python.org/)
[![PHP: 7.4 | 8.x](https://img.shields.io/badge/PHP-7.4%20|%208.x-777bb4.svg)](https://www.php.net/)
[![MQTT: v3.1 / v3.1.1](https://img.shields.io/badge/MQTT-v3.1%20|%20v3.1.1-orange.svg)](https://mqtt.org/)
[![PWA: Ready](https://img.shields.io/badge/PWA-Ready-purple.svg)](4_mobile_app/)

*Read this in other languages: [English](README.md) | [Italiano](README_it.md)*

> **Official Interactive Documentation & Setup Guide:**  
> 🌐 **[http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)**

---

## Overview

**NewFoobot** is a complete, self-hosted, open-source revival ecosystem for **Airboxlab Foobot** indoor air quality monitors. Following the official cloud service shutdown by the original manufacturer, functional Foobot devices became inoperative due to hardcoded cloud broker dependencies.

This project delivers an end-to-end replacement stack that brings Foobot hardware back to life without relying on proprietary third-party servers:
- **Zero hardware modifications**: Works with original, unmodified Foobot firmware.
- **Local Wi-Fi re-provisioning**: Built-in cross-platform configuration tool (Windows, macOS, Linux).
- **Standalone MQTT Bridge**: Custom broker accepting legacy MQTT v3.1 (`MQIsdp`) packets and forwarding calibrated telemetry via HTTP/HTTPS POST.
- **Zero-Ping Watchdog**: Real-time power outage and connection-loss detection without wasteful background polling.
- **Responsive Web Dashboard**: Real-time telemetry, historical analytics, air quality index calculation, email alerts, multi-device management, and multi-language support (IT, EN, DE, FR, ES).
- **Universal Mobile & Desktop PWA**: Progressive Web App with offline caching and native app experience.

---

## System Architecture

```text
+-----------------------+
|  Foobot Device (HW)   |
| (Unmodified Firmware) |
+-----------+-----------+
            |
            | 1. Local USB/Wi-Fi Setup via CLI
            |    (Windows / macOS / Linux)
            v
+-------------------------------------------------------+
| 1_foobot_device_config (foobot_manager.ps1 / .py)     |
| - Connects to Foobot SoftAP                           |
| - Redirects broker IP to Cloud VPS / Bridge Server    |
+-------------------------------------------------------+
            |
            | 2. Raw MQTT v3.1 (TCP Port 1883)
            v
+-------------------------------------------------------+
| 2_mqtt_broker_bridge_server (bridge.py)               |
| - Standalone MQTT Broker & Protocol Adapter           |
| - Sensor Calibration & Official Allpollu Calculation   |
| - Zero-Ping Inactivity Watchdog (10m / 30m outage)    |
+-------------------------------------------------------+
            |
            | 3. Authenticated HTTPS POST (/api/ingest.php)
            v
+-------------------------------------------------------+
| 3_web_dashboard_and_database (PHP 7.4/8.x + MySQL)    |
| - Database Storage & Telemetry Ingestion API          |
| - Responsive Admin Console & Diagnostic Logs          |
| - SMTP Email Alert Dispatcher (Power loss / PM spikes)|
+-------------------------------------------------------+
            |
            | 4. REST API Endpoints (/api/get_datapoints.php)
            v
+-------------------------------------------------------+
| 4_mobile_app (Progressive Web App - PWA)              |
| - Mobile / Tablet / Desktop Interface                 |
| - Real-time Air Quality Gauges & Interactive Charts   |
| - Multi-Language Support (IT, EN, DE, FR, ES)        |
+-------------------------------------------------------+
```

---

## Repository Structure

| Directory | Description |
| :--- | :--- |
| **`1_foobot_device_config/`** | Cross-platform configuration scripts for Windows (`.ps1`, `.bat`), Linux (`.py`, `.sh`), and macOS (`.py`, `.command`) to provision the Foobot device over Wi-Fi. |
| **`2_mqtt_broker_bridge_server/`** | Python 3 standalone MQTT broker and bridge script (`bridge.py`) with `systemd` service template for Oracle Cloud Free Tier or any Linux VPS. |
| **`3_web_dashboard_and_database/`** | Web dashboard, ingestion API (`/api/`), MySQL schema (`schema.sql`), automated installation wizard (`install.php`), user management, and SMTP alerts. |
| **`4_mobile_app/`** | Standalone Progressive Web App (PWA) client designed to be deployed into the `/app/` subfolder of the web dashboard. |
| **`5_docs_and_guides/`** | Original sensor hardware datasheets (e.g. ScioSense IAQ-Core C) and technical reference materials. |

---

## Key Features

### 1. Legacy MQTT v3.1 Protocol Support
Original Foobot firmware uses the legacy `MQIsdp` header format from MQTT v3.1, which causes connection drops on modern brokers (such as Mosquitto v2.x with default strict validation). The standalone `bridge.py` includes a custom, permissive protocol handler that parses:
- `CONNECT` and authentication handshakes;
- `SUBSCRIBE` / `UNSUBSCRIBE` with legacy bit flags;
- `PUBLISH` telemetry payloads on `device/{uuid}/sensor/push`;
- `PINGREQ` keepalive heartbeats every 10 seconds.

### 2. Sensor Calibration & Official Allpollu Formula
Raw telemetry transmitted by the internal sensors is automatically calibrated using official firmware conversion factors:
- **Temperature:** `Raw / 1000.0` (°C)
- **Relative Humidity:** `Raw / 1000.0` (%)
- **Particulate Matter (PM2.5):** `Raw / 94.2` (µg/m³)
- **Volatile Organic Compounds (VOC):** ppb (isobutylene equivalent)
- **Global Allpollu Index (0–100):** Quadratic regression combining normalized particulate and VOC concentrations.

### 3. Zero-Ping Watchdog
The bridge avoids periodic network polling to save bandwidth and server resources:
- **Normal operation:** Data is pushed every 5 minutes by the Foobot;
- **10 minutes of silence:** Bridge detects a power outage or connection loss, notifying the database;
- **30 minutes of silence:** A single trigger is dispatched to `cron_events.php` to notify the administrator via email.

---

## Quick Start Guide

### Step 1: Deploy the Web Dashboard & Database
1. Upload the contents of `3_web_dashboard_and_database/` to your web server (e.g., cPanel hosting or VPS).
2. Copy `4_mobile_app/` inside the `/app/` directory of your web dashboard.
3. Access `https://your-domain.com/install.php` in your browser and complete the automated setup.
4. Save your database credentials and note your **API Passkey**.

### Step 2: Set Up the MQTT Broker Bridge Server
1. Provision a Linux virtual machine (e.g., Oracle Cloud Always Free VM).
2. Open incoming TCP port **1883** in your cloud network security list and local firewall (`iptables`).
3. Deploy `2_mqtt_broker_bridge_server/bridge.py` and customize `INGEST_URL` and `API_KEY`.
4. Install the provided `systemd` service (`foobot-bridge.service`) for automatic 24/7 background operation.

### Step 3: Configure Your Foobot Hardware
1. Power on your Foobot and place it in pairing mode.
2. Run the configuration tool matching your operating system inside `1_foobot_device_config/`:
   - **Windows:** Run `START_FOOBOT_MANAGER.bat`
   - **Linux:** Run `./START_FOOBOT_MANAGER.sh`
   - **macOS:** Run `START_FOOBOT_MANAGER.command`
3. Enter your Wi-Fi credentials and the IP address of your Bridge Server.

### Step 4: Monitor & Enjoy
- Open your web dashboard: `https://your-domain.com/`
- Open the mobile PWA: `https://your-domain.com/app/`
- Real-time air quality metrics will begin streaming every 5 minutes.

---

## Official Documentation & Tutorials

For detailed, step-by-step visual guides covering each stage of the process, visit the official project website:

👉 **[http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)**

---

## Contributing

Contributions, bug reports, and pull requests are warmly welcomed! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on code style, multi-language localization, and submission guidelines.

---

## Related Projects & Acknowledgments

- **[foobot-local](https://github.com/kerosix/foobot-local)** by [@kerosix](https://github.com/kerosix): A lightweight local Python solution to intercept and read Foobot data directly on your LAN. It served as an invaluable source of inspiration and technical reference for exploring the internal Wi-Fi module, UDP port 48899, and AT command set.

---

## Security

To report security vulnerabilities or sensitive issues, please refer to [SECURITY.md](SECURITY.md).

---

## License

This project is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
See the [LICENSE](LICENSE) file for complete terms and copyright details.

Copyright (C) 2026 Barbara Lerici.
