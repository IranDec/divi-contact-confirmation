# Divi Contact Form — Confirmation Email

**Version:** 2.0.0  
**Author:** [Mohammad Babaei](https://adschi.com)  
**License:** GPL-2.0-or-later  
**Requires WordPress:** 6.0+  
**Requires PHP:** 7.4+  
**Compatible with:** Divi 4, Divi 5

---

> ## ⚠️ Security advisory — update to 2.0.0
>
> **Versions 1.0 – 1.5.3 can be abused to send spam / scam emails from your site.**
> The confirmation email repeated what the visitor typed (`{name}`, `{email}`, any `{field}`) and
> was sent to whatever address was entered. Bots used this in June 2026: they entered victims'
> addresses and put text such as `Join TRONYX >>> https://…` into the name field, so the site sent
> the scam with its own (SPF/DKIM-valid) reputation. The 1.x reCAPTCHA did not stop it (a missing
> token was let through) and the IP limit was useless against ~80 different IPs.
>
> **2.0.0 fixes this by design:** the confirmation never contains anything the visitor typed, and
> new limits and spam checks apply. Existing templates are cleaned automatically on update
> (`Hello {name},` becomes `Hello,`).

---

## Description

Automatically sends a confirmation email ("we received your message") to visitors after they
submit a Divi contact form. No changes to your Divi modules are required.

## Features

- Works with **Divi 4** (`et_pb_contact_form_submit`) and **Divi 5** (`divi_contact_form_submitted`) —
  only after Divi accepted the form (nonce, captcha, spam protection)
- Customisable **subject** and **body** with safe placeholders only (`{site_name}`, `{site_url}`, `{date}`, `{time}`)
- **Abuse protection:**
  - nothing the visitor typed is ever put into the email
  - no confirmation when a field contains links or spam markers (`http`, `www.`, `>>>`, …), when the name is
    suspicious, when the address is tagged with your site name (`victim+yoursite@…`) or belongs to your own domain
  - **one confirmation per address per 24 h** (gmail dots and `+tags` count as the same address)
  - per-IP limit, plus **total limits per hour and per day** — when reached, confirmations pause and
    the admin gets one warning per day (the form messages still arrive as usual)
  - blocked domains, blocked keywords, optional MX check
  - `Auto-Submitted: auto-replied` header (no auto-reply loops)
- **Logs tab** — every sent / failed / blocked email with the reason (the visitor's name is not stored)
- **Diagnostics tab** — send a test email and check the setup
- Translations: English, German (de_DE), Persian (fa_IR)

---

## Installation

1. Upload the `divi-contact-confirmation` folder to `/wp-content/plugins/` (or upload a ZIP of it under
   **Plugins → Add New → Upload Plugin**) and activate it.
2. **Settings → Divi Confirmation**: set subject, body and sender.
3. In every Divi contact form module enable **Spam Protection** (reCAPTCHA via Divi's spam protection
   service) and the **captcha**. Divi rejects bots before this plugin runs.

**Updating from 1.x:** just replace the files. On the next page load the plugin removes `{name}`,
`{email}` and field placeholders from your saved subject/body, adds the new limit options and deletes
the old reCAPTCHA options.

---

## Configuration

### Email Settings tab

| Field | Description |
|---|---|
| Email Subject | Subject line sent to the visitor |
| Email Body | Plain-text body (safe placeholders only) |
| From Name / From Email | Sender (must be allowed by your mail server / SPF) |

| Placeholder | Replaced with |
|---|---|
| `{site_name}` | Your site name |
| `{site_url}` | Your site URL |
| `{date}` | Submission date |
| `{time}` | Submission time |

`{name}`, `{email}` and form-field placeholders were removed on purpose (see the advisory above).

### Security tab

| Option | Default | Description |
|---|---|---|
| Enable confirmation emails | on | Master switch |
| Rate limit (per IP / hour) | 3 | Confirmations per IP and hour (0 = off) |
| Total limit per hour | 10 | All visitors together; then pause + admin warning |
| Total limit per day | 40 | All visitors together in 24 h |
| Blocked email domains | — | Comma-separated, subdomains included |
| Blocked keywords | — | Comma-separated, checked in all fields |
| Require valid MX record | off | DNS check of the recipient domain |
| Log blocked attempts | on | Write a "Blocked" log row with the reason |
| Auto-delete blocked logs after (hours) | 24 | Sent entries are kept |

Always on (no setting): link / spam-text check, suspicious name, tagged address, own domain,
one confirmation per address per 24 h.

---

## Developer hooks

Since 2.0.0 the filters receive the recipient address only — no form fields, so custom code cannot
put visitor text back into the email by accident.

```php
add_filter( 'dcc_confirmation_subject', function ( $subject, $email ) { return $subject; }, 10, 2 );
add_filter( 'dcc_confirmation_body',    function ( $body, $email ) { return $body; }, 10, 2 );
add_filter( 'dcc_confirmation_headers', function ( $headers, $email ) { return $headers; }, 10, 2 );

// Veto: return a reason string to block, true to allow.
add_filter( 'dcc_should_send', function ( $result, $email ) { return $result; }, 10, 2 );
```

---

## FAQ

**The email is not sending — what should I check?**  
Diagnostics → *Send test email* (limits do not apply to the test). If it fails, WordPress cannot send
mail — use WP Mail SMTP. If the test works but forms are not logged: the form needs an email field, and
Divi must accept the form (captcha / spam protection passed).

**A real visitor did not get a confirmation.**  
Check the Logs tab for the reason — e.g. the message contained a link (`www.…`), or the address already
got a confirmation in the last 24 hours. The form message itself always reaches you.

**Where is data stored?**  
Options in `wp_options` (prefix `dcc_`), logs in `{prefix}dcc_log`. Everything is removed on uninstall.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Credits

Developed by [Mohammad Babaei](https://adschi.com).  
Licensed under [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
