# Step 3: Setup DNS Rewrite Rules (NextDNS or Pi-hole)

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step3.html](http://newfoobot-restart.barbaralerici.net/en/step3.html)

---

    > [!NOTE]
> **Summary & Goal of Step 3**
> In this step, we configure a DNS rewrite rule to intercept Foobot's network queries. Because the OEM domain name is hardcoded in device firmware, we instruct our custom DNS resolver to return the IP of our Bridge Server (launched in Step 2): Foobot will connect to its expected trusted host, while all telemetry packets are safely captured by our private infrastructure.

## 1. The Principle of DNS Redirection

Foobot's built-in Wi-Fi module contains a fixed, immutable firmware setting: it constantly and exclusively queries the domain name `broker-gw-nc.foobot.io`.

There is no AT command or software setting to replace that hardcoded hostname in the device. The only network setting Foobot accepts to modify is the **IP address of the DNS Server** it queries to resolve that domain name.

We leverage this mechanism to our advantage:

- We configure Foobot to query **our dedicated DNS Server** (e.g. NextDNS, Pi-hole, or home router).
- When Foobot asks where `broker-gw-nc.foobot.io` is located, our resolver returns the **IP address of our Bridge Server** (configured in Step 2) instead of the dead Airboxlab cloud.

      Transparent Redirect: Foobot believes it is connecting to its original cloud provider, while in reality delivering all air quality readings directly to your independent setup.

    

## 2. Overview of DNS Services Supporting Rewrites (Free & Premium)

To apply this rewrite rule (controlled DNS rewrite or spoofing), multiple platforms are available:

    

Scroll horizontally to view entire table

| DNS Platform | Type | Features & Notes | Link |
| --- | --- | --- | --- |
| **NextDNS** | Free (300k queries) | Cloud resolver with intuitive web dashboard. Supports DNS rewrites, dedicated IPv4 endpoints, and query logs. Ideal free plan for NewFoobot continuous streaming. | [nextdns.io →](https://nextdns.io/) |
| **Pi-hole (Self-hosted)** | Open Source Free | Local DNS server for Raspberry Pi, mini PC, or Docker. Allows Local DNS Records to route the hostname directly inside your home network. | [pi-hole.net →](https://pi-hole.net/) |
| **AdGuard Home / DNS** | Free / Premium | Available as self-hosted software or managed cloud service. Supports DNS rewrites and custom filtering via clean GUI. | [adguard-dns.io →](https://adguard-dns.io/) |
| **Cloudflare Zero Trust** | Free Tier (Up to 50 users) | Enterprise-grade cloud DNS with policy routing, custom resolvers, and security features. Slightly more complex initial setup. | [cloudflare.com →](https://one.dash.cloudflare.com/) |
| **Control D** | Paid / Trial | Modern high-performance DNS service with custom profiles, built-in proxying, and comprehensive domain/IP rewrite capabilities. | [controld.com →](https://controld.com/) |

#### Have an advanced home router? You can configure everything directly on it!

        If your home router provides advanced networking features or open firmware (such as **AVM FRITZ!Box**, **OpenWrt**, **pfSense / OPNsense**, **MikroTik RouterOS**, **GL.iNet**, or **Asuswrt-Merlin**), you don't need external cloud services:

- **Static Hosts / Local DNS / Dnsmasq**: create a static DNS host mapping directly in the router pairing `broker-gw-nc.foobot.io` to your Bridge Server IP.
- **DNS Server IP for Foobot**: the DNS address given to Foobot will simply be your router's LAN IP (e.g. `192.168.1.1`).

*Note:* Standard ISP-provided modem routers often lack local DNS rewrites; this is why we showcase **NextDNS** as the universal solution for everyone.

        Jump to Router & Local Server Solutions &darr;

    

## 3. The Two Essential Parameters for Any DNS Service

Regardless of the platform selected (NextDNS, Pi-hole, AdGuard, router DNS, or custom server), NewFoobot requires only **two key settings**:

      

#### Rewrite Rule (Rewrite / Local DNS)

- **Domain name to intercept**: `broker-gw-nc.foobot.io`
- **Answer IP address**: IP address of the machine running Bridge Server (Step 2). Can be local LAN IP (e.g. `192.168.1.50`) or public VPS IP (e.g. Oracle Cloud).

Any resolver supporting static A records or rewrites handles this transparently.

      

#### DNS Resolver IP to Assign to Foobot

Note down the **IPv4 address** of your DNS resolver (e.g. primary NextDNS IP `45.90.28.xxx` or local Pi-hole IP `192.168.1.2`).

This is the sole setting injected into Foobot's microcontroller in Step 4.

    

## 4. Step-by-Step Procedure: NextDNS Configuration (Standard Guide Model)

Field-Tested Procedure

      To enable anyone to finish the project without setting up local home servers, we use **NextDNS** as our free reference model:

1. Create a free account on [nextdns.io →](https://nextdns.io/) and log in.
2. Create a dedicated profile (e.g. named `Foobot`) from the top-left dropdown menu so normal household browsing remains untouched.                    **Tip:** If you return to NextDNS later, the panel might revert to *Default* profile. Select your *Foobot* profile from the top dropdown to view your rules.
3. In the **Setup** tab:          - Locate the **Linked IP** box and click **«Link IP»** (authorizes your home internet line for this profile). - Find the **DNS Servers (IPv4)** line and **note the first numerical IP** (e.g. `45.90.28.xxx`): this will be assigned to Foobot in Step 4.                                            NextDNS *Setup* tab: *Linked IP* box and primary numerical *DNS Servers*.
4. Go to the **Settings** tab, scroll down to **Rewrites**, and click **«New rewrite»**:          - **Domain**: `broker-gw-nc.foobot.io` (or `*.broker-gw-nc.foobot.io`) - **Answer**: enter your Step 2 Bridge Server IP (public Oracle VPS IP or local PC IP).                                            NextDNS *Settings → Rewrites*: *New rewrite* button and active rule pointing to Bridge IP.
5. Save the rewrite rule.

    

**Detailed NextDNS guide: Security, Privacy, Logs, and Dynamic IP settings**

            NextDNS offers dozens of filters. To ensure it does not inadvertently drop IoT telemetry or JSON payloads, use this verified configuration:

          

#### 1. Security Tab

**Keep strictly DISABLED (to prevent blocking Foobot):**

- **DNS Rebinding Protection**: OFF (essential if Bridge runs on a local private IP).
- **Data dump services / Webhooks**: OFF.
- **Newly active domains**: OFF.
- **Residential hosting**: OFF.
- **Tunneling endpoints & Free hosting domains**: OFF.

**Standard Protections to keep ACTIVE:**

Threat Intelligence feeds, AI-driven threat detection, Google Safe Browsing, Cryptomining protection, DGA algorithms, Typosquatting protection.

          

#### 2. Privacy Tab

- **Blocklists**: standard NextDNS list or none. Foobot does not browse websites.
- **Native tracking protection**: OFF.
- **Block disguised third-party trackers (CNAME)**: OFF.

          

#### 3. Parental Control and Allowlist / Denylist

Keep all parental filters **disabled** and leave both *Allowlist* and *Denylist* empty.

          

#### 4. Logs Tab (Real-Time Diagnostics)

The **Logs** tab is your best diagnostic tool: when Foobot connects, you will see queries for `broker-gw-nc.foobot.io` tagged with a green **Rewritten** badge, confirming successful redirection.

          

#### 5. Handling Dynamic Public IPs

If your home ISP provides dynamic public IPs, router restarts change your IP address. To re-link NextDNS instantly:

- Under **Setup** → *Linked IP*, click **Advanced**.
- Bookmark the secret URL: `https://link-ip.nextdns.io/

/`.
- Opening that link updates your home IP in NextDNS in one second, or add it to your router's DDNS curl script.

### NextDNS Operational Checklist

    Summary of actions in NextDNS and values to retain:

    

Scroll horizontally to view entire table

| Step | Action | Panel Location | Parameter & Purpose |
| --- | --- | --- | --- |
| **1. Sign Up** | Create free account and log in | Top-right corner | Persists your configuration in the cloud. |
| **2. Profile** | Create profile (e.g. *Foobot*) | Top-left profile menu → *Create profile* | Isolates sensor rules from household traffic. |
| **3. Link IP** | Authorize home internet connection | *Setup* tab → *Linked IP* | Click **«Link IP»** (and copy secret URL under Advanced). |
| **4. DNS IP** | **Record 1st DNS IPv4 address** | *Setup* tab → *DNS Servers* | Numeric IP (e.g. `45.90.28.xxx`) to configure on Foobot in Step 4. |
| **5. Rewrite** | Redirect OEM domain to Bridge | *Settings* → *Rewrites* → *New rewrite* | Domain: `broker-gw-nc.foobot.io`Answer: `` |

## 5. Alternative Solutions for Advanced Users (Router / Pi-hole / AdGuard)

      If you prefer local name resolution on your own network:

- **Pi-hole (Self-hosted)**: Dashboard → *Local DNS* → *DNS Records* → Add `broker-gw-nc.foobot.io` pointing to your Bridge Server IP.
- **AdGuard Home**: Dashboard → *Filters* → *DNS Rewrites* → Add domain and Bridge IP.
- **Home Router with Custom Firmware**: create a static DNS host mapping directly in your router firmware.

    

## 6. Home Router Configuration as Alternative to NextDNS

Alternative Custom Procedure

      Router options differ significantly depending on brand and firmware. Here are conceptual guidelines for capable devices:

    

#### Minimum Router Requirements

The router must offer at least one of these two capabilities:

- **DNS Host Mapping / Local DNS (DNSmasq / Unbound)**: ability to associate a specific hostname (`broker-gw-nc.foobot.io`) with a custom IP.
- **Custom DHCP DNS Server Broadcast**: ability to advertise its own local IP (e.g. `192.168.1.1`) as the primary DNS resolver to Wi-Fi clients.

#### Compatible Models and Brands:

- **AVM FRITZ!Box**: *Home Network → Network → DNS Rebind Protection Exceptions*.
- **OpenWrt / DD-WRT**: native *Dnsmasq* directive `address=/broker-gw-nc.foobot.io/IP`.
- **GL.iNet**: integrated AdGuard Home or Dnsmasq interface.

- **MikroTik RouterOS**: *IP → DNS → Static* FQDN entries.
- **pfSense / OPNsense**: *Unbound DNS → Host Override*.
- **Asus (Asuswrt-Merlin)**: custom `hosts.add` directives.

      

#### Universal Guide for Compatible Routers

1. **Log In**: browse to your router gateway (e.g. `192.168.1.1`) and authenticate.
2. **Locate DNS / LAN Section**: search for *Local Network (LAN)*, *DHCP Server*, *Static DNS*, or *Dnsmasq*.
3. **Add Rewrite Entry (DNS Host)**:            - **Host / Domain FQDN**: `broker-gw-nc.foobot.io` - **Answer IP**: Step 2 Bridge Server IP (public VPS IP or local PC IP).
4. **DNS Rebind Exception (Crucial for LAN Bridges)**: if your Bridge Server is on a private LAN IP (e.g. `192.168.x.x`), add an exception in *DNS Rebind Protection* for `foobot.io`.
5. **Apply & Save**: save changes. During Step 4 on Foobot, the DNS IP you enter will simply be your router's LAN IP (e.g. `192.168.1.1`).

    

      ← Return to Step 2: Bridge Server
      Go to Step 4: Configure Foobot →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 2](step2.md)
- ➡️ **Next:** [Step 4](step4.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
