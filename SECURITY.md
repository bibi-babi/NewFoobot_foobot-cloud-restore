# Security Policy

The security of the **NewFoobot** project and its users' personal IoT environments is a top priority.

---

## Supported Versions

Security updates and critical bug fixes are applied to the latest release:

| Component | Supported Version |
| :--- | :--- |
| `1_foobot_device_config` | Latest |
| `2_mqtt_broker_bridge_server` (Bridge v3.0) | Latest |
| `3_web_dashboard_and_database` | Latest |
| `4_mobile_app` (PWA) | Latest |

---

## Reporting a Vulnerability

If you discover a security vulnerability, please **do not open a public issue**.  
Instead, report it responsibly via the contact channels provided on the official project website:

🌐 **[http://newfoobot-restart.barbaralerici.net/](http://newfoobot-restart.barbaralerici.net/)**

Please include in your report:
- A detailed description of the vulnerability and its potential impact;
- Steps or a proof-of-concept demonstrating how to reproduce the issue;
- Affected components and software versions.

We appreciate your cooperation in responsible disclosure and will work to address verified issues promptly.

---

## Deployment Security Best Practices

When deploying your self-hosted NewFoobot instance:
1. **Never commit sensitive credentials:** Keep `config.php`, API keys, private SSH keys, and database passwords out of version control (see `.gitignore`).
2. **Use HTTPS / TLS:** Secure all communication between the Bridge and your Web Dashboard using valid SSL certificates.
3. **Firewall Rules:** Restrict TCP port 1883 access on your Bridge server to known IP subnets if possible, or monitor traffic for unauthorized connections.
4. **Change Default Passwords:** Choose a strong administrator password and a unique API passkey during installation (`install.php`).
