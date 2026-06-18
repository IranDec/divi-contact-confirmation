# Divi Contact Form — Confirmation Email

**Version:** 1.2.0  
**Author:** [Mohammad Babaei](https://adschi.com)  
**License:** GPL-2.0-or-later  
**Requires WordPress:** 6.0+  
**Tested up to:** 6.7  
**Requires PHP:** 7.4+  
**Compatible with:** Divi 4, Divi 5

---

## Description

Automatically sends a confirmation email to visitors after they submit any Divi contact form on your site. No changes to your Divi modules are required — just install, activate, and configure the email template.

---

## Features

- Works with **Divi 4** and **Divi 5** out of the box
- Fully customisable email **subject** and **body** with dynamic placeholders
- **Security tab** with rate limiting, domain blocking, and honeypot options
- **Log tab** — view every sent/failed email with recipient, subject, status, and error details
- Paginated log with one-click "Clear all" button
- Cleans up all data (DB table + options) on uninstall

---

## Installation

1. Upload the `divi-contact-confirmation` folder to `/wp-content/plugins/`.
2. In your WordPress dashboard go to **Plugins → Installed Plugins** and activate **Divi Contact Form Confirmation Email**.
3. Go to **Settings → Divi Confirmation** to configure the email template and security options.

> **Upgrading from 1.0 / 1.1?**  
> Deactivate and re-activate the plugin. The activation hook runs `dbDelta()` which safely creates or upgrades the log table without touching existing data.

---

## Configuration

### Email Settings tab

| Field | Description |
|---|---|
| Email Subject | Subject line sent to the submitter |
| Email Body | Plain-text body. Supports placeholders (see below). |
| From Name | Display name shown in the recipient's inbox |
| From Email | Sending address (must be authorised on your mail server) |

### Available placeholders

| Placeholder | Replaced with |
|---|---|
| `{name}` | Submitter's name (falls back to email) |
| `{email}` | Submitter's email address |
| `{site_name}` | Your site name |
| `{site_url}` | Your site URL |
| `{date}` | Submission date |
| `{time}` | Submission time |
| `{field_id}` | Any Divi form field — wrap its ID in braces |

### Security tab

| Option | Description |
|---|---|
| Enable plugin | Master on/off switch — disable without deactivating |
| Rate limit | Max confirmation emails per IP address per hour (0 = unlimited) |
| Blocked email domains | Comma-separated list of domains to never send to (e.g. `tempmail.com`) |
| Blocked keywords | Comma-separated words — if found in any field the email is suppressed |
| Require valid MX record | Only send if the recipient domain has a valid DNS MX record |
| Log blocked attempts | Write a log row (status = blocked) when a submission is suppressed |

### Logs tab

- Shows all sent/failed/blocked emails, newest first
- 25 rows per page with pagination
- Click "Clear all logs" to wipe the table (irreversible — a confirmation dialog is shown first)

---

## Developer hooks

```php
// Modify the subject before sending
add_filter( 'dcc_confirmation_subject', function( $subject, $email, $fields ) {
    return 'Custom subject';
}, 10, 3 );

// Modify the body before sending
add_filter( 'dcc_confirmation_body', function( $body, $email, $fields ) {
    return 'Custom body';
}, 10, 3 );

// Modify the mail headers
add_filter( 'dcc_confirmation_headers', function( $headers, $email, $fields ) {
    $headers[] = 'Bcc: archive@example.com';
    return $headers;
}, 10, 3 );

// Veto sending programmatically (return false to block)
add_filter( 'dcc_should_send', function( $should_send, $email, $fields ) {
    return $should_send;
}, 10, 3 );
```

---

## Frequently Asked Questions

**Does this work with all Divi contact forms on the page?**  
Yes. The hook fires for every Divi contact form submission site-wide.

**The email is not sending — what should I check?**  
1. Confirm your WordPress installation can send emails at all (use a plugin like WP Mail SMTP to test).  
2. Check the Logs tab — if the row shows status "Failed" the error column will show the PHPMailer error.  
3. Make sure the "From Email" address is authorised by your mail server / SPF records.

**Can I send an HTML email instead of plain text?**  
Add this snippet to your theme's `functions.php`:
```php
add_filter( 'dcc_confirmation_headers', function( $headers ) {
    // Replace the plain-text content type
    foreach ( $headers as $i => $h ) {
        if ( str_starts_with( $h, 'Content-Type' ) ) {
            $headers[ $i ] = 'Content-Type: text/html; charset=UTF-8';
        }
    }
    return $headers;
} );
```
Then write HTML in the Email Body field.

**Where is data stored?**  
Options in the `wp_options` table (prefixed `dcc_`). Logs in the `wp_dcc_log` custom table. Everything is removed on uninstall.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## Credits

Developed by [Mohammad Babaei](https://adschi.com).  
Licensed under [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
