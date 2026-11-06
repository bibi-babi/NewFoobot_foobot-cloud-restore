# Step 5: Wi-Fi Router Setup (Blackout Persistence)

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step5.html](http://newfoobot-restart.barbaralerici.net/en/step5.html)

---

### What happens to Foobot during power loss (and when this Step can be skipped)

Even though in **Step 4** Foobot saved your custom IP and DNS in memory, upon **complete power loss** (or if you unplug its power cable) the internal firmware exhibits a factory-wired behavior: as soon as it boots back up, **it queries the home router via DHCP first**, asking for DNS settings and initially disregarding the stored NextDNS IP. The only definitive way to bypass this behavior and make the device power-outage-proof is to configure DNS directly on your router.

        Already configured the DNS rule in your router during Step 3?
        If in Step 3 you utilized an advanced router (e.g. FRITZ!Box, OpenWrt, MikroTik) with a custom rewrite entry for broker-gw-nc.foobot.io, or if you already entered NextDNS/Pi-hole in your router's DHCP pool settings, this Step 5 is unnecessary and can be skipped (proceed directly to Step 6), because your router will automatically supply the correct DNS answer on every Foobot boot.

## 1. Why modify the router?

During hardware protocol reverse engineering, we verified that **whenever Foobot turns off due to power disconnection**, upon restart the internal microcontroller clears RAM and requests a fresh IP and DNS lease from the router via DHCP.

      Benefit of Router Configuration: If you enter the NextDNS IP directly inside your router's DNS settings, whenever electricity returns Foobot immediately receives the correct DNS and resumes telemetry delivery to your server fully autonomously, without requiring any action from your PC!

## 2. Step-by-Step Procedure in the Router

1. Log in to your router's web administration panel via browser (typically by browsing to `192.168.1.1` or `192.168.0.1`).
2. Enter username and password (found on the device sticker or in your personal records).
3. Navigate to **Local Network / LAN** or **DHCP Server / DNS Settings**.
4. In the **Primary DNS** field, enter the NextDNS IPv4 address obtained in Step 2 (e.g. `45.90.28.xxx`).
5. Save settings and reboot the router.

## 3. Alternative Compromise: Using Foobot Without Touching the Router

If you cannot access your router, if your ISP has locked its settings, or if you simply prefer not to alter your entire home network configuration, **you can safely skip this step**.

### Managing Foobot via the Step 4 Utility

Without router reconfiguration, Foobot operates normally and streams air quality telemetry to your Bridge server. However, the device might revert DNS to factory defaults or lose redirection under circumstances such as:

- **Power cuts or voltage drops** that force the internal Wi-Fi chip to restart.
- **Router DHCP lease expirations or renewals** that reassign local IPs or overwrite adapter DNS settings.

#### What to do when this happens (without modifying your router):

You do not need to touch the modem. Simply launch the management utility from your PC introduced in [Step 4](step4-device-setup.html) (`START_FOOBOT_MANAGER.bat` on Windows or the script on Mac/Linux) and pick:

- **Option 3 • Reactivate after power cut with the same recorded IP:** built specifically for power outages. It automatically reads the previously configured DNS IP, triggers a 16-second DHCP lease refresh with the router, locks the static IP, and reopens the cloud channel toward the Bridge (`TCPLKB = on`) in one keystroke without re-entering values.
- **Option 2 • Configure custom DNS server:** use this if you want to manually specify a new DNS IP.

        Automatic Web Dashboard Monitoring:
        Your dashboard includes an intelligent watchdog service. If Foobot stops transmitting data for more than 30 minutes (due to power loss, Wi-Fi glitches, or router reboot), the web interface marks the device as offline and sends an email notification: you will know exactly when to run the Step 4 batch file and select Option 3 to restore communication in 16 seconds.

    

      ← Return to Step 4: Configure Foobot
      Go to Step 6: Mobile App →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 4](step4.md)
- ➡️ **Next:** [Step 6](step6.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
