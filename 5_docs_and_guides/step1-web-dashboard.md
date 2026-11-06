# Step 1: Install Web Dashboard & Database (MySQL / MariaDB)

> **Part of the Official NewFoobot Setup Guide**  
> Interactive Version: [http://newfoobot-restart.barbaralerici.net/en/step1.html](http://newfoobot-restart.barbaralerici.net/en/step1.html)

---

    > [!NOTE]
> **Summary & Goal of Step 1**
> In this initial phase, you need a web host or private server (free options included, supporting PHP and MySQL/MariaDB) to host the telemetry database and analytics interface. Installing the web dashboard first is crucial: it lets you identify your server IP address and generate your secure API passkey, both of which are required in subsequent steps. NewFoobot provides a ready-to-use package featuring an automated web installer.

    

## 1. Do You Already Have Personal Web Hosting?

Before starting, determine whether you already own a web hosting space (shared hosting, VPS, or home server) and your familiarity with its control panel:

#### ✓ Already have personal hosting?

If you already have an active domain (e.g. SiteGround, Hetzner, OVH, Hostinger, cPanel/Plesk), create a subfolder or subdomain with a dedicated MySQL/MariaDB database, then jump directly to [file upload](#caricamento-file).

#### &plus; No web hosting yet?

You do not need to buy a paid plan immediately. Several providers offer **free hosting tiers** with PHP support and relational databases. While they have bandwidth or resource quotas compared to paid plans, Foobot's lightweight telemetry footprint fits comfortably within them.

    

**Recommended Free Web Hosting Providers (PHP & MySQL / MariaDB)**

            If you do not run a self-hosted server, here are reliable free solutions internationally suitable for hosting the PHP dashboard and relational database:

Scroll horizontally to view entire table

| Hosting Provider | Scope | Free Tier Specs & Resources | Link |
| --- | --- | --- | --- |
| **InfinityFree** | International | One of the most popular globally: 5 GB disk quota, unlimited bandwidth, PHP 8.2/8.3, MySQL DB, free Let's Encrypt SSL, and no forced ads. | [infinityfree.com →](https://www.infinityfree.com) |
| **ByetHost** | International | Reliable infrastructure with 5 GB storage, unlimited MySQL databases, PHP support, and VistaPanel / cPanel control panel. | [byet.host →](https://byet.host) |
| **GoogieHost** | International | Free hosting backed by LiteSpeed web servers, 1 GB NVMe SSD storage, MySQL database, PHP support, and free SSL certificate. | [googiehost.com →](https://googiehost.com) |
| **Netsons (Free)** | Europe / Italy | Free web space with professional cPanel, modern PHP support, 1 MySQL/MariaDB database, and third-level subdomain. | [netsons.com →](https://www.netsons.com) |

## 2. Web Space Technical Requirements

      Whichever host you choose (free or commercial), ensure it satisfies these prerequisites:

- **PHP**: versions 7.4, 8.0, 8.1, 8.2 or newer with extensions `pdo_mysql`, `json`, `mbstring`, and `openssl` enabled.
- **Relational Database**: 1 database running **MySQL** (v5.7+) or **MariaDB** (v10.3+) with full user grants.

      How to verify PHP extensions in cPanel:

1. Log into **cPanel** and find **Select PHP Version** (or *MultiPHP Manager / PHP Extensions*) under *Software*.
2. Click the **Extensions** tab: check that `pdo_mysql`, `json`, `mbstring`, and `openssl` are checked.
3. *Quick alternative:* upload a temporary `info.php` with `<?php phpinfo(); ?>`, inspect via browser, and delete it immediately afterwards.

    

## 3. Hosting Preparation and Database Creation

Before transferring application files, set up your web address and create the database:

      

#### Phase 1: Configure Domain or Subdomain

Use either the free subdomain provided by your hosting provider (e.g. `yourname.infinityfreeapp.com`) or configure a custom subdomain (e.g. `foobot.yourdomain.com`) pointing to your target folder.

      

#### Phase 2: Create Database (MySQL / MariaDB) and Privileged User

In your hosting control panel, open the database manager (**MySQL Databases** or *Database Wizard*):

1. Create a new database (e.g. `foobot_db`).
2. Create a user with a strong password (e.g. `foobot_user`).
3. Assign the user to the database with **ALL PRIVILEGES**.

          Record and preserve these 4 parameters for the web installer:
          Host: localhost (or host IP provided by host) | DB Name | Username | Password

    

## 4. File Upload (FTP or File Manager)

With hosting and database ready, retrieve the dashboard package and upload it to the server.

    

#### How to Obtain the Web Dashboard Package

Two options are available:

        

          Option A • Single Module from GitHub

Browse and download folder `3_web_dashboard_and_database` directly from GitHub:

          View on GitHub →

        

          Option B • Full NewFoobot Archive

Download the complete repository ZIP archive and extract `3_web_dashboard_and_database/`:

          Download Full ZIP (.zip)

Extract the contents of `3_web_dashboard_and_database` and upload them via **FTP (e.g. FileZilla)** or cPanel **File Manager**:

- If intended as primary root: upload directly to root directory (typically `public_html/` or `htdocs/`).
- If using a dedicated subfolder: upload to configured path (e.g. `public_html/foobot/`, reachable at `https://yourdomain.com/foobot/`).

      Security Note: The included .htaccess and robots.txt files automatically restrict direct access to backup dumps and prevent search engines from indexing private telemetry.

    

## 5. Running the Interactive Installer (install.php)

Open your browser and navigate to `/install.php` on your domain:

    https://your-domain.com/install.php

The guided setup proceeds in two simple steps:

1. **Database Connection**: enter the database parameters saved earlier (Host typically `localhost`, DB Name, User, and Password). The installer verifies connectivity and generates relational tables automatically.
2. **Admin Account, Language & API Passkey**:                  The dashboard requires authenticated, password-protected access. Configure your primary Administrator user, select default UI language among the 5 options (*English &mdash; default*, *Italiano*, *Deutsch*, *Español*, *Français*), and define your **secret API Passkey** (e.g. `MySecretFoobotPass2026!`).                    **Save your admin credentials and API Passkey:** Your credentials log you into the web dashboard, while the API Passkey must be supplied to the Bridge Server in Step 2 to authenticate telemetry forwarding.

    

## 6. Accessing the Control Panel and Verification

Upon setup completion, the system removes the installation lock and redirects to the login screen. Browsing to your domain (e.g. `https://your-domain.com/`) reveals the complete telemetry dashboard.

To check table health and configuration consistency at any time, run the built-in diagnostic script:

    https://your-domain.com/check_db.php

    

## 7. Multilingual Customization (i18n Module)

The web dashboard ships with 5 official languages (English, Italian, German, Spanish, French). Thanks to the dynamic internationalization engine (`i18n.php`), you can add any language or refine existing text strings without editing PHP code.

#### Translation Workflow:

1. **Template and Path:**           access the `languages/` folder via FTP or File Manager. You will find `template.json` with all available system keys, or duplicate `en.json` as your starting baseline.
2. **Naming Convention (ISO 639-1):**           save the new file with the standard lowercase 2-letter language code. For instance:            - `pt.json` for Portuguese - `nl.json` for Dutch - `pl.json` for Polish - `el.json` for Greek
3. **JSON Translation Guidelines:**           edit string values on the right of the colons inside quotes. Keep intact:            - Key names on the left of colons (e.g. `"nav_dashboard": "..."`). - Dynamic variable placeholders enclosed in curly braces (e.g. `{user}`, `{time}`, `{count}`). - HTML formatting tags embedded in strings (e.g. ``, ``).
4. **Dynamic Detection and Safety Fallback:**           `i18n.php` dynamically indexes the `languages/` folder. As soon as a valid `.json` file is uploaded:            - The new language appears **instantly** in the navigation language selector and user profile preferences. - Any untranslated or missing keys automatically fall back to English, preventing blank labels.

#### PWA Mobile App Language Translation & Offline Caching:

The **PWA Mobile App** also supports custom languages with dynamic scanning and local offline caching:

- **Automatic PWA Folder Detection:**           the server endpoint `api/i18n.php` prioritizes scanning `/app/languages/`. Adding a new file (e.g. `app/languages/pt.json`) advertises it to the app with its localized label (e.g. *Português (PT)*).
- **Direct Download and Local Device Caching:**           the PWA downloads `./languages/{code}.json` and persists it in `localStorage` under `nf_pwa_cached_lang_{code}`. Even without an internet connection, the chosen language remains stored on your phone.
- **Service Worker Offline Pre-Caching:**           `./languages/*.json` files are registered in `STATIC_ASSETS` inside `sw.js`, ensuring browser Cache Storage keeps physical translations readily accessible offline.

    

      ← Return to Guide Index
      Go to Step 2: Bridge Server →

---

## Guide Navigation

- ➡️ **Next:** [Step 2](step2.md)
- 🏠 **Project Overview:** [README.md](../README.md)
- 🌐 **Official Interactive Guide:** [http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)
