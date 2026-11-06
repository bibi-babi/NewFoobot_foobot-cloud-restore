# Contributing to NewFoobot

Thank you for your interest in contributing to the **NewFoobot** project!  
This guide outlines the development standards, contribution process, and coding conventions across our components.

---

## Code of Conduct

We are committed to providing a friendly, safe, and welcoming environment for everyone, regardless of experience level, background, or identity. Please communicate with respect, clarity, and professionalism.

---

## How Can You Contribute?

1. **Reporting Bugs:**
   - Search the existing issues to ensure the bug has not already been reported.
   - Provide a clear and detailed description, including steps to reproduce, logs, device OS, and browser/firmware versions.

2. **Suggesting Enhancements:**
   - Clearly explain the intended use case and why the feature would benefit the community.
   - Discuss architectural implications for the bridge or dashboard before submitting large changes.

3. **Submitting Pull Requests:**
   - Create a feature branch from `main`: `git checkout -b feature/your-feature-name`.
   - Keep commits focused and atomic with meaningful commit messages.
   - Test your changes locally before submitting the pull request.

4. **Localization (i18n):**
   - NewFoobot supports English (`en`), Italian (`it`), German (`de`), French (`fr`), and Spanish (`es`).
   - If you add or edit translations, update the corresponding JSON files in both:
     - `3_web_dashboard_and_database/languages/`
     - `4_mobile_app/languages/`

---

## Development Standards

- **Language Policy:**
  - All code symbols (function names, variables, class names, constants) must be written in **English**.
  - Commit messages, pull requests, and code comments must be in **English**.

- **Python (`bridge.py`, `foobot_manager.py`):**
  - Follow [PEP 8](https://peps.python.org/pep-0008/) style guidelines.
  - Maintain compatibility with Python 3.8+.
  - Do not introduce heavy external dependencies; keep the standalone bridge lightweight.

- **PHP & MySQL (`3_web_dashboard_and_database/`):**
  - Maintain compatibility with PHP 7.4 through PHP 8.3+.
  - Follow secure SQL practices: always use prepared statements with PDO for database queries.
  - Validate and sanitize input; escape output contextually (`htmlspecialchars`).
  - Do not hardcode database credentials or private keys.

- **Frontend & PWA (`4_mobile_app/`):**
  - Use semantic, responsive HTML5, modern CSS3 (Flexbox/Grid), and vanilla JavaScript.
  - Test service worker cache strategies (`sw.js`) to ensure smooth offline functionality.
  - Avoid inline CSS styles in application views where external stylesheets apply.

---

## Licensing

By contributing to NewFoobot, you agree that your contributions will be licensed under the **GNU General Public License v3.0 (GPL-3.0)** as defined in the root [LICENSE](LICENSE) file.
