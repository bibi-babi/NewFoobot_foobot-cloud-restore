# Step 4: Configure Foobot via Local Wi-Fi

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step4.html](http://newfoobot-restart.barbaralerici.net/en/step4.html)

---

    > [!NOTE]
> **Summary & Goal of Step 4**
> In this step, we communicate directly with Foobot's Wi-Fi chip (HF-LPB100). Using the open-source cross-platform management utility provided by this project, we unlock the AT-command mode on the fly without disassembling or soldering the hardware, setting the new DNS server IP configured in Step 3 (e.g. NextDNS or local DNS). The sensor will immediately contact your personal Bridge Server and resume live telemetry streaming to your web dashboard.

## 1. Preliminary Preparation

Ensure that your Foobot is powered on and connected to your home Wi-Fi network, and that your computer (desktop or laptop) is connected to the **same local Wi-Fi network**.

## 2. Download and Launch the Tool on Your Computer

The configuration utility is completely free, open-source, and driverless: it requires no low-level system alterations and runs natively on **Windows**, **macOS**, and **Linux**.

    

#### How to Obtain the Tool: Foobot Management & Configuration Tool

You have two options to download the utility:

        

          Option A • Single Module from GitHub

Browse and download the configuration folder directly from the official project repository:

          View Folder on GitHub →

            Contains ready-to-run subfolders for Windows, macOS, and Linux.

        

          Option B • Full NewFoobot Archive

If you downloaded the full ZIP archive from the home page, extract it on your PC and locate:

          Download Full ZIP (.zip) &darr;

            Directory path: 1_foobot_device_config/

      

#### Launch Instructions for Your Operating System:

        

**Windows (10 / 11)**

            Open the `windows/` folder and double-click:

          START_FOOBOT_MANAGER.bat

            Runs the built-in Windows PowerShell script (foobot_manager.ps1) automatically with zero dependencies (Python not required).

        

**macOS (Apple Mac)**

            Open the `macos/` folder and double-click:

          START_FOOBOT_MANAGER.command

            Launches the Terminal with the Python application (or execute in Terminal: python3 foobot_manager.py).

        

**Linux (Ubuntu / Debian / Pi)**

            Open the `linux/` folder and execute in a terminal:

          bash START_FOOBOT_MANAGER.sh

            Requires Python 3 (installed by default across modern Linux distributions).

## 3. Operations Menu and Diagnostic Reading

Upon launch, the program automatically detects your operating system language (bilingual **English** and **Italian**) and requests confirmation before proceeding. It then broadcasts a UDP probe across your local network (port 48899): if Foobot is on and connected to Wi-Fi, it is discovered immediately, revealing its local IP, MAC address, and hardware module ID (MID).

    

        Foobot Management & Configuration Tool: LAN sensor detection and main menu displaying all 6 operational choices. Click to enlarge fullscreen.

    

#### Menu Options Breakdown:

        

          1. Read current Foobot configuration (Read-only &mdash; 100% safe)

Queries Wi-Fi module status without altering any registers. Displays internal firmware version, unique module ID (MID), Wi-Fi RSSI signal quality, IP address, active DNS server, MQTT channel, and cloud link status (`TCPLKB`). Concludes with a direct TCP socket ping to Bridge port 1883. Recommended as your first diagnostic verification.

        

          2. Configure custom DNS server (NextDNS, Pi-hole, or dedicated IP)

Essential for initial setup: prompts for your DNS IPv4 address (from Step 3), locks network parameters to static mode (preventing router DHCP renewals from wiping DNS entries), and executes an on-the-fly disconnect and reconnect of Socket B toward your Bridge server with 5-second safety timeouts.

        

            3. Reactivate after power cut with the same recorded IP
            FEATURED • POST-BLACKOUT

Solves the connection lockout caused by power outages. Following power restoration, Foobot boots in 3 seconds while home routers take minutes; upon starting, routers clear client tables and block Internet egress for devices lingering on static IPs, leaving `TCPLKB` on `off`. This automated routine resolves the issue in 3 steps with a single keypress:

- Reads the previously stored DNS IP from Foobot memory;
- Enables DHCP temporarily and triggers a warm reboot (`AT+Z` with a 16-second delay) forcing the router to authorize the client;
- Discovers the newly assigned IP via UDP broadcast, locks it as static, restores custom DNS, and restarts the cloud socket, driving `TCPLKB` to `+ok=on`.

        

          4. Configure new home Wi-Fi network (SSID and password pairing)

Enables pairing Foobot to a new router or updated Wi-Fi password without relying on the discontinued Airboxlab mobile app. Configures 2.4 GHz SSID and WPA2-PSK key followed by a controlled soft restart.

        

          5. Restore original factory settings (10.10.100.254)

Factory reset procedure to revert all custom settings: switches IP acquisition back to automatic DHCP and re-establishes default factory DNS (`10.10.100.254`).

        

          6. Reboot Foobot Wi-Fi module (AT+Z command only)

Sends hardware restart command (`AT+Z`) to the Wi-Fi module without modifying registers or parameters. LED pulses for ~15 seconds until network link is re-established.

        

          7. Exit program

Closes the UDP communication socket and exits the utility.

## 4. Modifying Foobot's DNS Server (Option 2)

From the main menu, select **Option 2** (press `2` and hit Enter):

    

#### Which IP Address Should You Enter?

The tool will prompt for the **IPv4 address of your DNS resolver** (prepared in **Step 3** with the rewrite rule). Enter the value matching your setup:

        

          If using NextDNS (Proven standard):

            Enter one of the two primary DNS IPv4 addresses shown in your NextDNS Setup tab (e.g. 45.90.28.xxx or 45.90.30.xxx).

        

          If using Home Router DNS (FRITZ!Box / OpenWrt):

            Enter your router's local gateway IP (usually 192.168.1.1 or 192.168.178.1), acting as internal LAN DNS resolver.

        

          If using Pi-hole or AdGuard Home (Raspberry Pi / NAS):

            Enter the LAN private IP of the device hosting the DNS service (e.g. 192.168.1.150).

    

1. **Read and lock static IP:** the tool reads Foobot's currently leased IP and locks network mode to static, ensuring router DHCP lease renewal cannot reset DNS.
2. **Enter DNS IP:** type the address matching your architecture (e.g. `45.90.28.xxx` or `192.168.1.1`).
3. **Safety confirmation:** verify entered parameters on screen, type Y (or S) and hit Enter.
4. **Execution and Bridge handshake:** the sequence executes on the fly (DNS cache flushing, Socket B recreation toward Foobot cloud host with 5-second timeout and 2.5-second settling pauses). Foobot will resolve the domain to your Bridge Server immediately!

    

**What to Do After Power Outages or Modem Reboots**

        If after a blackout Foobot stops streaming and Option 1 diagnostics show `TCP Cloud link (TCPLKB) : +ok=off`, **do not re-run Option 2**:

Select **Option 3 ("Reactivate after power cut")** directly: the script will retrieve saved DNS, refresh DHCP with the router, and re-establish cloud connection in one click.

## 5. Operational Verification

Open `logs.php` on your personal web dashboard: within seconds, you should observe incoming TCP connections and the confirmation log:

    Foobot MQTT CONNECT authentication succeeded: clientID=2402XXXXXXXXXXXX
Telemetry sensor packet received
Calibrated readings -> Forwarded to cPanel successfully [HTTP 200]

    

      ← Return to Step 3: Setup DNS
      Go to Step 5: Router Setup →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 3](step3.md)
- ➡️ **Next:** [Step 5](step5.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
