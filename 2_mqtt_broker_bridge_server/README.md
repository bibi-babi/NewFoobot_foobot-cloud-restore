# NewFoobot — MQTT Broker & Bridge Server

> **Component:** 2_mqtt_broker_bridge_server  
> **Version:** 3.0 (Standalone Native Broker & HTTPS Forwarder)  
> **Official Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)

---

## Overview

The **`bridge.py`** script is a lightweight, standalone Python 3 server designed to run on a cloud VPS (such as Oracle Cloud Always Free, AWS EC2, DigitalOcean, or a local server):
1. **Listens directly on TCP port 1883** without requiring external broker daemons (like Mosquitto).
2. **Accepts legacy MQTT v3.1 (`MQIsdp`) protocol handshakes** and bitmask packets transmitted by original Foobot firmware.
3. **Calibrates raw sensor metrics** using official formulas (Temperature, Humidity, PM2.5, VOC, and Allpollu index).
4. **Tracks connection status and power outages** with a Zero-Ping watchdog (notifies power cuts after 10 minutes; triggers an offline alert email after 30 minutes).
5. **Forwards telemetry via HTTP/HTTPS POST** to the web dashboard (`/api/ingest.php`).

---

## Configuration

Open `bridge.py` and customize lines 28–31:

```python
# ==========================================
# USER CONFIGURATION (Customize these 2 fields)
# ==========================================
# Enter the exact URL of ingest.php on your website
INGEST_URL = "https://your-domain.com/api/ingest.php"

# Enter the same API Passkey configured during website installation
API_KEY = "YOUR_SECRET_API_PASSKEY"
```

---

## Installation & Deployment (Ubuntu / Debian)

### 1. Install Dependencies
```bash
sudo apt update && sudo apt install -y python3 python3-requests
```

### 2. Configure Firewall (Port 1883)
Ensure TCP port 1883 is open in both your cloud provider security group and local `iptables`:
```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 1883 -j ACCEPT
sudo netfilter-persistent save
```

### 3. Deploy Script
```bash
mkdir -p ~/foobot-bridge
cp bridge.py ~/foobot-bridge/
```

### 4. Enable Background Service (`systemd`)
Copy `foobot-bridge.service` into systemd:
```bash
sudo cp foobot-bridge.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable foobot-bridge.service
sudo systemctl start foobot-bridge.service
```

### 5. Monitor Live Telemetry Logs
```bash
sudo journalctl -u foobot-bridge.service -f
```

---

## License

Licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
Copyright (C) 2026 Barbara Lerici.
