# Step 6: Connect Mobile & PWA Application

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step6.html](http://newfoobot-restart.barbaralerici.net/en/step6.html)

---

    > [!NOTE]
> **Summary & Goal of Step 6**
> The NewFoobot mobile application is a standalone, full-featured **PWA (Progressive Web App)**. To operate properly and connect with your personal server, the files from **Module 4 (Git repository)** must reside in the `/app/` directory of your web dashboard (e.g. `https://yourdomain.com/app/`). This enables direct, authenticated access to database REST APIs and lets you launch or install the app in one tap across iOS, Android, macOS, Windows, and Linux.

    

#### How to Obtain the PWA App Package (Module 4)

You have two convenient options to get the application files:

        

          Option A • Module 4 from GitHub

Browse and download PWA source files directly from the official project repository:

          View on GitHub →

        

          Option B • Full NewFoobot Archive

Download the complete project ZIP archive containing all modules from the homepage:

          Download Full ZIP (.zip)

      

**
            Mandatory File Location on Server: `/app/` directory
          **

            All files and folders contained in **Module 4** (e.g. `index.html`, `manifest.json`, `sw.js`, `languages/`, etc.) **must be uploaded to your web host inside the `/app/` subdirectory of your web dashboard**.

- **Server directory path (FTP / cPanel):** `public_html/app/` (or `dashboard-root/app/`).
- **Final Web App URL:** reachable directly at `https://yourdomain.com/app/`.
- *If you previously extracted and uploaded the entire ZIP archive in Step 1, the `/app/` folder is already in place and ready to go!*

## 1. What is the PWA App and Why Was It Chosen?

The NewFoobot mobile application is a production-ready **PWA (Progressive Web App)**. This modern architecture provides two major benefits:

- **Universal cross-platform compatibility**: installs identically on Android smartphones, iPhones (iOS), iPads, and PCs (Windows, Mac, Linux) directly through any standard browser, without downloading bloated installation binaries or going through proprietary app stores.
- **Lightweight ergonomics**: once added to your home screen or desktop, it launches in standalone fullscreen mode like native software, faithfully reproducing the original Foobot design and interface.

## 2. How It Works and Network Requirements (Internet Connectivity)

The App enables **convenient mobile air quality monitoring**. None of the measured readings (temperature, humidity, CO₂, VOC, and PM2.5 particulate matter) are stored locally on the phone; instead, they are **queried securely over the Internet from your private web dashboard database** (configured in Step 1) through integrated REST APIs.

      Important note on connectivity: By its technical nature, the App does not operate offline. An active Internet connection (4G/5G mobile data or Wi-Fi) is required so the application can reach your server and refresh live gauges and historical charts.

## 3. How to Enable, Pair, and Install the PWA App

The App is bundled directly in the web dashboard package (folder `app/`): when uploading the dashboard files in Step 1, the App is automatically accessible online at `https://your-domain.com/app/`.

### 3.1 Finding the App in the Web Dashboard

1. Sign in to your **personal NewFoobot web dashboard** with your credentials.
2. In the **top navigation bar**, next to the *Guide* link, click the **smartphone icon** (visible to authenticated users).
3. This opens the dedicated **Mobile & Desktop App** manager (`mobile_app.php`). From here you can:          - **Launch and install the App instantly**: opening the App directly from this section registers your current phone or PC **automatically** in your authorized sessions table. - **Generate a pairing code** to authorize a second phone or remote device where you have not signed in to the web panel. - **Manage, rename, or revoke devices**: assign friendly labels to connected devices (e.g. *"John's Pixel"*, *"Living Room Laptop"*) or revoke session tokens with a single click.

### 3.2 Unique Device ID and Pairing Modes

Every registered device or browser profile receives an incremental **Device ID number (#)** assigned by the server database.

- **On the Web Dashboard**: the *"Authorized Devices"* table displays the ID badge (e.g. `ID #1`, `ID #2`) alongside device metadata, making identification effortless.
- **Inside the App**: the exact same ID is visible in the Settings sidebar under *"App Device ID"* (e.g. `#1`), verifying device-to-database association.

Two pairing workflows are supported:

- **Automatic registration (recommended)**: opening the App from the same browser where you are logged into the web dashboard links and authorizes the device automatically without typing codes.
- **Pairing code authorization**: to connect another smartphone, click *Pair New Device* in `mobile_app.php` to generate a one-time 6-character code valid for **10 minutes** (e.g. `NF-A1B2C3`). Open the App on the new phone, enter your dashboard URL and code, then tap *Authorize & Connect*.

Repeated incorrect code attempts trigger a temporary IP ban to guard against brute-force attacks.

### 3.3 Installing the App (Desktop and Mobile)

PWAs eliminate heavy APK or EXE installers and work natively across **computers (Windows, Mac, Linux)** and **smartphones (Android, iOS)**.

Open the App URL (`https://your-domain.com/app/`) and follow your browser's prompt:

- **On Desktop (Chrome, Edge, Brave)**: click the install icon in the right side of the address bar or select *"Install NewFoobot"* from the browser menu. A standalone desktop shortcut will be generated.
- **On Android (Chrome)**: tap the bottom prompt *"Add NewFoobot to Home screen"* or select *"Install app"* from Chrome's three-dot menu.
- **On iPhone / iPad (Safari)**: tap the central *Share* button (square with an arrow pointing upward) and pick *"Add to Home Screen"*.

Once added, the NewFoobot icon will appear alongside your regular apps, ready for distraction-free fullscreen launch.

## 4. Application User Guide

The interface is structured into three main views:

### 4.1 Home Screen (Trilobed Gauge)

        Home screen: central GPI core (10% Great), VOC lobe (125 ppb), PM2.5 lobe (9.4 µg/m³), eCO2 lobe (450 ppm), plus lower temperature and humidity dials.

- **Global Pollution Index (GPI)**: the large circular core reports overall air quality (0–100%) with an immediate status verdict (*GREAT*, *GOOD*, *POOR*). The ring illuminates in **cyan/blue** when air is clean and shifts to **vibrant amber/orange** when ventilation is advised.
- **The Three Pollutant Lobes**:                    **PM2.5** (Fine particulate matter in µg/m³).
- **VOC** (Volatile Organic Compounds in ppb).
- **eCO2** (Equivalent carbon dioxide in ppm).

      Bottom Dials (Temperature & Humidity): display ambient temperature (°C or °F) and relative humidity (%).
      Automatic & Manual Refresh: telemetry refreshes automatically every 30 seconds with an exact timestamp. On touchscreens, drag down the screen (pull-to-refresh) to trigger an immediate update.

### 4.2 Sensor Details and Historical Charts

          Historical timeline chart (7-day filter) with color severity zones and value cursor.

          Quick switcher menu to navigate instantly between VOC, PM2.5, eCO2, GPI, TMP, and HUM.

Tapping any sensor opens its dedicated analytics view with time-series historical data:

- **Time Range Filters**: toggle between *Minutes* (high-resolution recent hours), *Hours (24h)*, *Days (7d)*, and *Weeks (30d)* to inspect acute spikes or long-term baseline trends.
- **Interactive Timeline Graph**: smooth curves with intuitive color coding (blue for healthy air, orange/red for elevated thresholds) to review when room ventilation changed.

### 4.3 Multi-Foobot Support and Settings

        Settings drawer: language preference, temperature units (°C/°F), device switcher, and App Device ID (#19) matching the server database.

- **Device Switcher**: if you operate multiple Foobot monitors on one installation, tap the top device picker to switch rooms instantly (e.g. Living Room / Bedroom).
- **Settings Drawer (Gear Icon)**: toggle temperature units (°C or °F), select UI language (English or Italian), verify database link, and disconnect when required.

    

      ← Return to Step 5: Router Setup
      Explore Dashboard Features →

---

## Guide Navigation

- ⬅️ **Previous:** [Step 5](step5.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
