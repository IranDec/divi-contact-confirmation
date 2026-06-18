# Changelog

All notable changes to this project are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project uses [Semantic Versioning](https://semver.org/).

---

## [1.3.0] — 2026-06-18

### Added
- Full internationalisation (i18n) support
- **Persian (fa_IR)** translation — 59 strings, full RTL-ready
- **German (de_DE)** translation — 59 strings
- `languages/divi-contact-confirmation.pot` master translation template
- `bin/compile-mo.py` — Python 3 script to compile `.po` → `.mo` without requiring external tools
- `load_plugin_textdomain()` call in `dcc_init()` so WordPress auto-selects the correct language based on **Settings → General → Site Language**

### Changed
- Version bump to 1.3.0

---

## [1.2.0] — 2026-06-18

### Added
- **Security tab** in the admin UI with the following options:
  - Master enable/disable toggle (disable without deactivating the plugin)
  - Per-IP rate limiting (configurable max emails per hour)
  - Blocked email domains list (comma-separated)
  - Blocked keyword list (suppresses email if any submitted field matches)
  - Optional MX record check before sending
  - Toggle to log blocked/suppressed attempts
- `dcc_should_send` filter — lets developers veto a send programmatically
- `DCC_Security` class handling all security checks in one place
- Security defaults set on activation

### Changed
- Version bump to 1.2.0 in plugin header and `DCC_VERSION` constant
- Uninstall hook now also removes security options

---

## [1.1.0] — 2026-06-17

### Added
- **Logs tab** in the admin UI — paginated table of every sent/failed email
- `DCC_Logger` class writing to a custom `wp_dcc_log` database table
- Log table created via `dbDelta()` on activation (safe to re-run on upgrade)
- "Clear all logs" button with confirmation dialog
- Error message column capturing PHPMailer error info on failure
- Log table and all options dropped cleanly on plugin uninstall
- Author info (Mohammad Babaei / adschi.com) in all file headers
- Admin page footer showing plugin version and author link

### Changed
- `DCC_Mailer::send()` now calls `DCC_Logger::write()` after every send attempt
- Version bump to 1.1.0

---

## [1.0.0] — 2026-06-17

### Added
- Initial release
- Hooks into Divi 4 (`et_pb_contact_form_submit`) and Divi 5 (`divi_contact_form_submitted`)
- Auto-detects submitter email and name from form fields
- Confirmation email sent via `wp_mail()` with configurable subject, body, from name, from email
- Dynamic placeholders: `{name}`, `{email}`, `{site_name}`, `{site_url}`, `{date}`, `{time}`, plus any form field ID
- Admin settings page under **Settings → Divi Confirmation**
- Developer filters: `dcc_confirmation_subject`, `dcc_confirmation_body`, `dcc_confirmation_headers`
- Activation hook sets sensible default options
