# NewFoobot — Device Configuration & Wi-Fi Provisioning Tool

> **Component:** 1_foobot_device_config  
> **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)

---

## Overview

This directory provides cross-platform provisioning utilities to connect original Airboxlab Foobot hardware to your local Wi-Fi network and redirect its MQTT broker target to your own Cloud VPS / Bridge server:
- **Zero firmware modifications required.**
- Communicates directly with the Foobot's onboard setup SoftAP.
- Configures local Wi-Fi SSID, WPA2 password, and custom MQTT broker IP/hostname.

---

## Supported Operating Systems

Select the directory matching your computer:

| Platform | Directory | Launch Script | Description |
| :--- | :--- | :--- | :--- |
| **Windows** | `Windows codice/` | `START_FOOBOT_MANAGER.bat` | Automated launcher for PowerShell provisioning script (`foobot_manager.ps1`). |
| **Linux** | `linux codice/` | `START_FOOBOT_MANAGER.sh` | Shell launcher for Python provisioning script (`foobot_manager.py`). |
| **macOS** | `Mac codice/` | `START_FOOBOT_MANAGER.command` | Double-clickable macOS launcher for Python provisioning script (`foobot_manager.py`). |

---

## Quick Usage Instructions

1. **Place Foobot in Pairing Mode:**
   - Turn the Foobot upside down and right side up twice, or hold the pairing button until the breathing pairing light sequence begins.
2. **Run the Launcher:**
   - Double-click the launcher script for your operating system.
3. **Follow the On-Screen Prompts:**
   - The script will scan for the Foobot SoftAP (`Foobot-XXXX`), connect to it, prompt for your home Wi-Fi credentials and your Bridge Server IP (port 1883), and transmit the configuration.

---

## License

Licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
Copyright (C) 2026 Barbara Lerici.
