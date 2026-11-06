# Step 2: MQTT Bridge Server Setup

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step2.html](http://newfoobot-restart.barbaralerici.net/en/step2.html)

---

    > [!NOTE]
> **Summary & Goal of Step 2**
> In this second phase, we prepare an online server (such as an Oracle Cloud Always Free VPS) or an always-on local computer to run a dedicated Python program. This script receives compact JSON/binary packets from the sensor, decodes and calibrates them, and securely forwards the readings to our web dashboard. Once started, we will note down the **IP address** of this machine, which is required in Step 3 for the DNS rewrite rule.

## 1. Why is a Dedicated Bridge Server Required?

The original Foobot firmware implements a legacy version of the MQTT protocol (MQIsdp / MQTT v3.1) that modern public MQTT brokers and shared web hosts reject. The `bridge.py` script solves this issue by opening a native socket server on standard port **TCP 1883**:

- Accepts the Foobot handshake and authentication without requiring expired Airboxlab cloud certificates.
- Decodes binary telemetry packets and calculates calibrated physical readings in real time (PM2.5, VOC in ppb, estimated CO2, and the allpollu air quality index).
- Forwards each reading via a secure HTTP/HTTPS POST request to the `api/ingest.php` endpoint of your web dashboard configured in Step 1.
- Manages an intelligent *Zero Ping* watchdog to alert you only in case of genuine prolonged disconnects or power outages.

## 2. Where to Host the Bridge Server?

You can freely choose between two operational architectures:

      

### Option A: Free Cloud (Always Free)

          Recommended • No hardware kept on at home

Running the Bridge on a Linux virtual machine (VPS, e.g. Ubuntu) with a static public IP address. It runs 24/7 in the cloud independently, without consuming any electricity at home. Reliable providers offering permanent free tiers or generous free trials include:

          

              Oracle Cloud (Always Free) →

                The primary recommended solution for this guide. Includes up to 2 x86 instances or Ampere ARM instances (up to 4 cores and 24 GB RAM) with a permanent, lifetime free IPv4 public IP.

              Recommended

          

            Google Cloud (Free Tier) →

              Offers a free e2-micro virtual machine every month in US regions, well suited for lightweight socket scripts.

          

            Amazon Web Services (AWS) →

              Includes a free Linux t2.micro / t3.micro instance for 12 months with a public IP for new accounts.

          

            Microsoft Azure →

              Includes Linux B1s virtual machines for 12 months with initial trial credits for the first 30 days.

      

### Option B: Local Computer or Mini-PC at Home

          Local LAN • Requires an always-on device

You can run the Python script directly on a home computer, a **Raspberry Pi**, a **NAS (Synology / QNAP)**, or a dedicated mini-PC (such as a Home Assistant server) connected to the same local Wi-Fi/LAN network as the Foobot. In this case, the address to use will be the local private IP of the computer (e.g. `192.168.1.50`). This mode requires that the machine remains powered on to continuously receive sensor telemetry.

          Jump to Local Raspberry Pi & NAS Guide ↓

    

## 3. Server Setup on Oracle Cloud (Recommended Tested Setup)

Field-Tested Procedure • 100% Always Free

      In this guide we adopt **Oracle Cloud Always Free** as our official reference model: it provides a Linux (Ubuntu) cloud virtual machine with a dedicated public IP, operational 24 hours a day without requiring any computer kept on at home. Oracle's Always Free tier is designed for developers, remains free permanently without expiration dates, and provides a full-featured cloud VPS for your own projects, scripts, and services.

    

      

#### Phase 1: Account Registration and Home Region Selection

Visit the official [Oracle Cloud Free Tier →](https://www.oracle.com/cloud/free/) portal and click the free tier signup button. Fill in the requested details carefully:

- **Account information & country**: select your country, enter your real full name and email address. Click *Verify my email* and open the verification link sent to your inbox.
- **Account type**: select **Individual** (Personal / Non-corporate).
- **Cloud Account Name (Tenancy)**: choose a unique name for your cloud workspace (e.g. `yourname-cloud`). You will use this name when signing in.
- **Home Region (permanent and critical)**: choose the geographically closest datacenter (e.g. *Italy North (Turin)*, *Italy Northwest (Milan)*, or your local regional hub).                                           **Warning:** The Home Region cannot be changed after registration, and all free tier resources must be created in this specific datacenter.
- **Identity Verification with Card**: used strictly to verify human identity and prevent abuse. A temporary pre-authorization of ~**$1 / €0.93** is made and automatically released/refunded by the bank. Upon signup you receive promotional credits for 30 days: once 30 days expire, the account smoothly transitions to the **Always Free** tier with zero unexpected charges.
- **Activation & 2FA**: accept terms and click *Start my free trial*. On initial login, set up two-factor authentication (2FA) using an app such as Google Authenticator.

        

Golden Rule to Never Pay Anything (Staying on Always Free)

            To ensure with 100% certainty that the server remains completely free beyond the 30-day promotional window:

- When creating any resource (virtual machines, block storage, IP addresses), always ensure the green **"Always Free Eligible"** label is present.
- Never activate the paid tier upgrade (*Pay As You Go*).
- By using solely Always Free resources, when the initial 30-day promotional credits expire, the balance simply resets to zero and your server continues operating at zero cost forever.

      

#### Phase 2: Pre-Creating the Virtual Cloud Network (VCN with Internet Connectivity)

          Why create the network BEFORE the instance: This preliminary step guarantees that the virtual machine is provisioned from the very first boot into a public subnet with an Internet Gateway (IGW), enabling automatic assignment of a public IPv4 address without console bottlenecks.

From the Oracle Cloud side menu navigate to **Networking → Virtual Cloud Networks** (or go directly to [Virtual Cloud Networks (VCNs) →](https://cloud.oracle.com/networking/vcns)):

        

              Virtual Cloud Networks (VCN) panel with Create VCN button

              VCN configuration form: IPv4 CIDR blocks and DNS resolution

1. Click on the **Actions** button next to *Create VCN* and choose **Start VCN Wizard**.
2. Select the option **Create VCN with Internet Connectivity** (which automatically provisions VCN, public subnet, private subnet, and Internet Gateway) and click *Start VCN Wizard*.
3. In the configuration form:              - **VCN Name**: assign a recognizable name (e.g. `vcn-foobot`). - **VCN IPv4 CIDR Block**: `10.0.0.0/16`. - **Configure Public Subnet**: CIDR block `10.0.0.0/24`. - **Configure Private Subnet**: CIDR block `10.0.1.0/24`. - **IPv6**: disabled / unchecked.
4. Click **Next** and on the review page click **Create** (takes 5-10 seconds). Once complete with green checkmarks, click **View VCN**.

      

#### Phase 3: Virtual Instance Creation (5-Step Wizard)

Now that the VCN network is ready, from the **Compute → Instances** section of the Oracle Cloud console, click the **Create instance** button to start configuring your virtual machine.

        

            Compute Instances list: the virtual machine is active (Running) with its dedicated public IP visible in the table. Click Create instance here to get started.

The Oracle console guides you through a 5-step sequence (clicking **Next** at the end of each screen):

        

          [ Step 1: Basics & OS ] → [ Step 2: Security ] → [ Step 3: Networking & SSH ] → [ Step 4: Storage ] → [ Step 5: Review & Create ]

1. **Step 1: Basic Information and Operating System**              - **Instance Name**: enter any friendly name (e.g. `server-foobot`). - **Placement and Availability Domain**: leave the default root compartment and availability domain assigned by the system (e.g. `AD-1`). - **Capacity type**: select *On-demand capacity*. - **Image (Operating System)**: click **Change image**, select the **Canonical Ubuntu** tab, choose **`Canonical Ubuntu 24.04 Minimal`** (or `26.04 Minimal`), then confirm by clicking *Select image*. - **Shape (Hardware)**: keep the default shape assigned. If you wish to change it, click *Change shape*, select *Virtual machine*, choose **Ampere** (or AMD) and ensure the green *Always Free Eligible* badge is shown. - **Advanced options**: leave the menu collapsed without changing options and click **Next** at the bottom right.                                                                         Schermata dello Step 1 su Oracle Cloud: posizionamento, cambio immagine Ubuntu e selezione della forma hardware.
2. **Step 2: Sicurezza**              - **Istanza schermata (Shielded instance)**: lascia la casella **DISATTIVATA** (senza spunta, come da default). Per un server Ubuntu Linux standard &egrave; consigliabile mantenerla disattivata per massimizzare la compatibilit&agrave; del sistema operativo ed evitare blocchi firmware. - **Opzioni avanzate (Attributi di sicurezza / ZPR)**: lascia vuoto e non aggiungere attributi. - Fai clic su **Avanti**.
3. **Step 3: Networking & SSH Keys (Instant Link)**                            Having created the VCN in Phase 2, association is instant:                           - **Virtual Cloud Network (VCN)**: select *Select existing virtual cloud network* and pick the VCN created in Phase 2 (e.g. `vcn-foobot`). - **Subnet**: select *Select existing subnet* and choose the **public subnet** (e.g. `public-subnet-...`). - **Private IPv4 Address**: choose *Automatically assign private IPv4 address*. - **Public IPv4 Address**: ensure **Automatically assign public IPv4 address** is checked. - **IPv6 Address**: keep unchecked / **DISABLED** (pure IPv4 is optimal for the MQTT broker and Foobot firmware). - **Add SSH Keys (Critical)**:                                                                            **Warning: Download your private key right now!**                     Select **Generate a key pair for me** and immediately click **Save private key**. Save this file (e.g. `ssh-key.key` or `.pem`) in a secure folder on your computer: it is the essential key to connecting via SSH without opening the Oracle web console. If you proceed without saving it, the private key cannot be retrieved! - Click **Next**.
4. **Step 4: Storage (Boot Volume & SSD)**              - **Custom Boot Volume Size**: keep **DISABLED** (unchecked). The default size of **46.6 GB** is fully within the 200 GB Always Free tier allowance and more than sufficient for Linux, Python, and the Bridge. - **In-transit encryption**: keep **ENABLED** (checked, default). - **Customer-managed encryption key**: keep **DISABLED** (Oracle handles encryption transparently and free of charge). - **Additional Block Volumes**: do not add secondary disks; click **Next**.
5. **Step 5: Final Review & Creation**              - Check the resource summary and click the blue **Create** button at the bottom right. - The page will show the orange *PROVISIONING* status. Within 1-2 minutes the icon turns green with the status **RUNNING**. - Under *Instance access* (and in the summary table) your dedicated numerical **Public IPv4 Address** will appear: this is your official server IP!                            **Tip if "Out of Capacity" appears on Create:** return to Step 1, select the latest LTS image (e.g. *Canonical Ubuntu 24.04 Minimal*), pick the *Ampere* processor shape, and click *Create* again.

      

#### Phase 4: Opening TCP Port 1883 in the Cloud Firewall (Security List)

This step is **mandatory**: the Foobot is hardcoded in its firmware to broadcast telemetry packets exclusively on standard **TCP port 1883** (standard MQTT protocol). To allow incoming packets to reach your virtual machine:

1. In the Oracle Cloud side menu, navigate to **Networking** → **Virtual Cloud Networks (VCNs)** and click on your VCN name.
2. In the navigation menu, select **Subnets** and click on the name of your **Public Subnet**.
3. Select the **Security Lists** tab and click on your active security list name (e.g. `Default Security List for VCN-...`).
4. In the left menu select **Ingress Rules** and click the blue **Add Ingress Rules** button.
5. Configure the ingress rule with these values:              - **Source Type**: `CIDR` - **Source CIDR**: `0.0.0.0/0` (global inbound access: guarantees continuous data reception even when your home router reboots and its external dynamic IP changes). - **IP Protocol**: `TCP` - **Source Port Range**: leave blank (all ports). - **Destination Port Range**: enter exactly `1883`. - **Description**: `NewFoobot Global MQTT Port`.
6. Click **Add Ingress Rules** to make the rule active immediately.

      

**Key Information to Keep Ready**

          Before opening your terminal to connect via SSH, make sure you have these two items ready from the previous steps:

            1. Your SSH private key file (.key or .pem)

              Where it is: the file saved to your PC during Step 3 (Networking & SSH Keys) via "Save private key" (e.g. ssh-key.key). Without this file, server access will be denied.

            2. Server Public IPv4 Address

              Where to find it: on your instance page in Oracle Cloud (under Instance details → Instance access or the Public IP address column, e.g. 198.51.100.123).

      

#### Phase 5: Connecting to the Server via SSH from Your Computer (Windows, macOS or Linux)

Commands are sent to the server using the standard **SSH (Secure Shell)** protocol. Opening the terminal directly **inside the directory where your key file is located** is the easiest approach: it avoids typing long absolute paths and eliminates typos.

        

          

            Windows (PowerShell)

              Open the folder containing your key file (.key) in File Explorer, click on the address bar at the top, type powershell and press Enter. The terminal will open directly in that folder.

          

            macOS (Terminale)

              In Finder, right-click the folder containing your key file (.key) and select "New Terminal at Folder" (or open Terminal, type cd  and drag the folder into the window).

          

            Linux (Terminale)

              In your graphical file manager, right-click in an empty space in the key folder and choose "Open in Terminal".

- **Key file permissions (.key) (macOS and Linux only)**: before connecting, set the restricted file permissions required by SSH by running `chmod 400 private-key.key` (on Windows this step is not needed).
- **SSH connection command**: run the command below, replacing the key filename (`.key`) and your server's **public IP address**:             ssh -i "private-key.key" ubuntu@198.51.100.XXX             *Note: `ubuntu` is the default username created by Oracle for Canonical Ubuntu images and should not be changed.*
- **Host fingerprint verification**: on your first connection, the prompt *"Are you sure you want to continue connecting (yes/no/[fingerprint])?"* will appear. Type exactly **`yes`** and press Enter.
- When you see the green prompt `ubuntu@instance-name:~$` (where *instance-name* is the name given during creation, e.g. `server-foobot`), you are logged into the remote virtual machine.

      

**Practical tip for pasting commands into the terminal:**

          If standard keyboard paste Ctrl + V does not work in PowerShell or CMD, simply **right-click once** inside the terminal window: copied text will be pasted instantly.

#### Phase 6: Python Environment Installation and Internal Firewall Unlock (iptables)

          Now that you are connected to the server, complete the instance setup with two simple commands in the terminal:

1. **Install base environment (Python 3 and network libraries)**                            Install Python 3, the `pip` package manager, the `requests` library (needed by the bridge to forward readings to your website via HTTP/HTTPS), and the `nano` text editor:                          sudo apt update && sudo apt install -y python3 python3-pip python3-requests iptables-persistent nano
2. **Mandatory unlock of port 1883 in the internal Linux firewall (`iptables`)**                            In addition to the Security List on Oracle Cloud's dashboard, official Ubuntu Server images include an internal software firewall (`iptables`) that blocks all incoming ports except SSH. Run these two commands to authorize MQTT traffic on port 1883 and make the rule persistent across server reboots:

```bash
sudo iptables -I INPUT 1 -p tcp --dport 1883 -j ACCEPT
sudo netfilter-persistent save
```

*Note:* The `netfilter-persistent save` command permanently saves newly created rules so they are not lost if the server reboots.

    

## 4. Configuring and Uploading the bridge.py Script

Ora che il server &egrave; attivo e accessibile, predisponiamo il programma `bridge.py` che ricever&agrave; i dati del sensore e li inoltrer&agrave; al tuo cruscotto web.

    

#### How to Obtain the bridge.py Script

You have two convenient ways to download the Bridge Server Python script:

        

          Option A • Direct GitHub Module

You can inspect and download the Bridge Server files directly from the official NewFoobot GitHub repository:

          View Module on GitHub →

            Inside you will find the source file bridge.py ready to be configured.

        

          Option B • Full NewFoobot Archive

If you downloaded the full project ZIP bundle from the homepage, open the project folder on your PC and extract:

            2_mqtt_broker_bridge_server/bridge.py

          Scarica ZIP Completo (.zip)

Nelle prime righe del file troverai il blocco `CONFIGURAZIONE UTENTE`: occorre sostituire i **due parametri fondamentali** con i dati reali del tuo cruscotto web ricavati nello Step 1:

    

      # Come appare originariamente all'inizio di bridge.py (righe 28-39):
      # ==========================================
      # CONFIGURAZIONE UTENTE
      # ==========================================
      # Inserire l'URL esatto della cartella del proprio sito web
      INGEST_URL = "https://your-domain.com/api/ingest.php"

      # Inserire la stessa Passkey API impostata durante l'installazione del sito
      API_KEY = "YOUR_SECURE_API_SECRET_KEY"

      # Parametri Server MQTT Integrato (ascolto diretto sulla porta 1883)
      MQTT_BIND_HOST = "0.0.0.0"
      MQTT_BIND_PORT = 1883

    

      

#### 1. Data Destination Endpoint (INGEST_URL)

The URL of the PHP ingest endpoint on your web host (installed in Step 1) which accepts calibrated readings from the bridge and saves them to the database. It corresponds to your web dashboard URL followed by `/api/ingest.php`.

          Default value to replace: "https://your-domain.com/api/ingest.php"
          Real example with your website: "https://tuonome.netsons.org/api/ingest.php" (or /foobot/api/ingest.php if installed in a subfolder)

      

#### 2. Security Authentication Key (API_KEY)

This is the exact same secret API Passkey you chose and entered into the website settings during the guided installation with `install.php` in Step 1 (which can also be retrieved in the website's `config.php` file). It ensures that only your Bridge Server can communicate with the PHP dashboard, blocking any unauthorized requests.

          Default placeholder to replace: "YOUR_SECURE_API_SECRET_KEY"
          Live example with your site API key: "MySecretPasskey2026!"

    

#### How to Transfer bridge.py from PC to Server (without nano)

You don't need terminal text editors on the server. Easily edit `bridge.py` on your PC with any text editor (setting the two lines for `INGEST_URL` and `API_KEY`) and transfer it directly to the server folder via Secure Copy Protocol (SCP):

1. **In the server terminal (`ubuntu@instance-name:~$`):** create the workspace folder by running:           mkdir -p ~/foobot-bridge
2. **On your PC:** verify that both your saved `bridge.py` and the SSH private key file (e.g. `ssh-key-foobot.key`) reside in the same local folder.
3. **Transfer the file via a second PowerShell window (or Mac/Linux Terminal):** do not close your existing SSH terminal session. Open a **new terminal window on your PC** inside the local folder holding both your key and `bridge.py`. Run the SCP command below, inserting your **actual key file name** and your **server public IP**:           scp -i "ssh-key-foobot.key" bridge.py ubuntu@TUO_IP_PUBBLICO:~/foobot-bridge/           The file will upload within seconds to `~/foobot-bridge/bridge.py` on the server without interrupting your session. You can now return to the first terminal to run tests.

    

## 5. Manual Test and Continuous 24/7 Autostart (systemd Service)

All subsequent commands must be run **inside your server terminal**: if you closed the window, reconnect via SSH as described in **Phase 5** until you see the green `ubuntu@instance-name:~$` prompt.

    

#### Step 1: Immediate Manual Test

Before setting up background autostart, run the script manually to confirm there are no syntax errors or incorrect parameters:

    python3 ~/foobot-bridge/bridge.py

If properly configured, you will see the startup banner output:

      NewFoobot Standalone MQTT Broker & Bridge v3.0 started!
      Directly listening on: 0.0.0.0:1883

This confirms the broker socket is active and listening. Press Ctrl + C to stop the temporary test and return to the prompt.

    

#### Step 2: Configure as a Permanent System Service (systemd)

If you left the script running interactively, it would terminate as soon as you closed SSH. We create a **Linux system service (systemd)**: an automated daemon that keeps the Bridge running in the background 24/7 and restarts it automatically if the server reboots:

    sudo nano /etc/systemd/system/foobot-bridge.service

Paste this standard service unit configuration into the editor:

    [Unit]
Description=NewFoobot Standalone MQTT Broker to cPanel Bridge
After=network.target

[Service]
Type=simple
User=ubuntu
WorkingDirectory=/home/ubuntu/foobot-bridge
ExecStart=/usr/bin/python3 -u /home/ubuntu/foobot-bridge/bridge.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

Save the file (with `Ctrl + O`, then `Enter`, exit with `Ctrl + X`), then enable and start the service:

    sudo systemctl daemon-reload
sudo systemctl enable foobot-bridge.service
sudo systemctl restart foobot-bridge.service

You can inspect status and live logs at any time with:

    sudo systemctl status foobot-bridge.service
sudo journalctl -u foobot-bridge.service -f

    

## 6. Alternative: Local Server Setup (Raspberry Pi or NAS)

Alternative Procedure • Community Supported

      If you already keep an always-on device in your home network (such as a **Raspberry Pi**, Linux mini-PC, or a **Synology, QNAP or Asustor NAS**), you can run `bridge.py` locally instead of on Oracle Cloud.

    

        Mandatory Preliminary Step: Prepare bridge.py on Your PC First

Even when using a home server or NAS, the Python script requires the exact same configuration shown above. Before proceeding, ensure you have downloaded `bridge.py` and filled in the two lines for `INGEST_URL` and `API_KEY` as detailed in **Section 4**:

        &uarr; Back to Section 4: Configuring and downloading bridge.py

    

#### Key Home Network Rules

- **Fixed Local IP (DHCP Reservation):** your Raspberry Pi or NAS must always keep the same internal IP address (e.g. `192.168.1.150`). Access your home router administration panel and configure a static DHCP reservation for that device, as this IP will be supplied to the DNS rule in Step 3.
- **No Port Forwarding Required on Router:** because both the Foobot and the Raspberry/NAS share the same local home Wi-Fi/LAN, **you do not need to open or forward port 1883 on your router to the Internet**. MQTT traffic stays strictly within your local network.
- **Outbound HTTPS Access:** the local device needs normal outbound Internet connectivity to forward calibrated readings via HTTPS to your web hosting dashboard.

    

### Option A: Step-by-Step Guide for Raspberry Pi or Linux Mini-PC

**Is the terminal required?** Yes, on Raspberry Pi (or Debian/Ubuntu mini-PC) the terminal is used, with two options: if connected to a monitor with keyboard and mouse, you can operate using the desktop GUI; if running headless without a monitor, simply connect from your PC via PowerShell.

        

          Step 1: Accessing the Terminal and Installing Python

If using the Raspberry with a monitor and mouse, launch the **Terminal** icon. If connecting from your PC, open PowerShell and log in with your Raspberry username and password (replacing `pi` with your username and `192.168.1.150` with the local IP):

          ssh pi@192.168.1.150

Once logged in, install Python 3 support and create your workspace folder:

          sudo apt update && sudo apt install -y python3 python3-requests
mkdir -p ~/foobot-bridge

        

          Step 2: How to Transfer bridge.py to the Raspberry Pi

Choose whichever transfer method suits you best:

- **Via USB Drive (if using desktop GUI):** copy the modified `bridge.py` (configured in [Section 4](#configurazione-bridge-py)) to a USB thumb drive, insert it into the Raspberry, and drag it into the `foobot-bridge` folder in your Home.
- **Over the Network from your PC (headless):** open a PowerShell window on your PC inside the folder where `bridge.py` is saved and copy it over SCP (it will prompt for your Raspberry password):               scp bridge.py pi@192.168.1.150:~/foobot-bridge/

        

          Step 3: Continuous 24/7 Autostart

In your Raspberry Pi terminal, run the same procedure detailed in **Section 5** to create the system service, ensuring you replace `ubuntu` with your Raspberry Pi username (typically `pi`):

          sudo nano /etc/systemd/system/foobot-bridge.service

Incolla la configurazione adattata per l'utente `pi`:

          [Unit]
Description=Foobot Local MQTT Bridge
After=network-online.target

[Service]
Type=simple
User=pi
WorkingDirectory=/home/pi/foobot-bridge
ExecStart=/usr/bin/python3 -u /home/pi/foobot-bridge/bridge.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

Salva con `Ctrl + O`, premi `Invio`, esci con `Ctrl + X` e attiva il servizio:

          sudo systemctl daemon-reload && sudo systemctl enable --now foobot-bridge.service

    

### Option B: Detailed Guide for NAS (Synology, QNAP, Asustor)

**Is the terminal required on a NAS? No!** On modern home NAS devices you can do everything in your web browser with a mouse, dragging `bridge.py` into a shared folder and using official GUI apps from the NAS app center.

      

        Which Apps to Install from the NAS Store?

- **On Synology NAS (DSM OS):** open *Package Center* and install **Python 3.9** (or higher) or **Container Manager** (formerly Docker). We will also use **File Station** and the built-in **Task Scheduler** in Control Panel.
- **On QNAP NAS (QTS OS):** open *App Center* and install **Python3** and **Container Station**. We will use **File Station 5** to upload the file.

        

          Step 1: How to Upload bridge.py to the NAS

1. Ensure you have configured `bridge.py` on your PC with your parameters ([see Section 4](#configurazione-bridge-py)).
2. Log into your NAS web portal, open **File Station**, and create a folder named `foobot-bridge` (e.g. inside the shared folder `docker` or `homes`).
3. Drag `bridge.py` from your PC directly into the **File Station** window (or use *Upload → Overwrite*). Note the full path to the file (e.g. `/volume1/docker/foobot-bridge/bridge.py`).

        

          Step 2 (Easy Synology Method): Autostart via Task Scheduler

On Synology you can launch the script on boot without touching the terminal or Docker:

1. Go to **Control Panel → Task Scheduler**.
2. Click **Create → Triggered Task → User-defined script**.
3. In the *General* tab: enter Task Name `Foobot Bridge`, set **User: root** and **Event: Boot-up**.
4. In the *Task Settings* tab under **Run command**, paste these two lines (verifying your folder path from Step 1):               python3 -m pip install requests nohup python3 -u /volume1/docker/foobot-bridge/bridge.py > /volume1/docker/foobot-bridge/bridge.log 2>&1 &
5. Click **OK** to save, select the new task from the list, and click **Run** at the top to start it immediately. Inspect operational output by opening `bridge.log` in File Station.

        

          Step 2 Alternative (Synology Container Manager / QNAP Container Station): Running in Docker

Se sul tuo NAS (Synology, QNAP o Asustor) preferisci isolare lo script in un container Docker tramite l'interfaccia grafica di **Container Manager** o **Container Station**:

1. Apri **Container Manager / Container Station**, vai nella sezione *Progetto* (o *Applicazione Compose*) e clicca su **Crea**.
2. Select the `foobot-bridge` folder where you uploaded `bridge.py`, choose *Create docker-compose.yml file*, and paste this configuration:

```yaml
version: "3.8"
services:
  foobot-bridge:
    image: python:3.11-slim
    container_name: foobot-bridge
    restart: always
    ports:
      - "1883:1883"
    volumes:
      - ./bridge.py:/app/bridge.py:ro
    working_dir: /app
    command: sh -c "pip install --no-cache-dir requests && python -u bridge.py"
```
3. Click to deploy the project: the NAS will pull the official Python container image, install `requests`, expose port `1883`, and keep the Bridge running 24/7.

    

        Note and Save Your Bridge Server IP Address:

Now that your Bridge Server is ready and listening on port 1883, write down its **IP address**:
        if hosted on Oracle Cloud / VPS, use its *Static Public IP*; if hosted on a home PC or Raspberry, use its *Local LAN Private IP* (e.g. `192.168.1.50`).

        This IP address is the exact value needed in the upcoming **Step 3 (DNS Rewrite Rule)**!

    

      ← Back to Step 1: Web & Database
      Proceed to Step 3: DNS Rules →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 1](step1.md)
- ➡️ **Next:** [Step 3](step3.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
