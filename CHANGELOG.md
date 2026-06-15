# Changelog

All notable changes to this project will be documented in this file.

## [1.4.2] - 2026-06-02

### Added
- Enable Logs setting to turn delivery log writing on or off.

### Changed
- Renamed current Email Log labels to Logs.
- Replaced the header logo wrapper with direct icon styling while keeping the email icon visible.

### Removed
- Removed the Reset Test Form button from the Test Email screen.
- Removed unused internal plugin include, PHPMailer version, and legacy admin notice helper code.

## [1.4.1] - 2026-06-01

### Changed
- Added a subtle transparent `a` brand mark behind the plugin header icon and title using external CSS.

## [1.4.0] - 2026-06-01

### Added
- Plain-text to HTML conversion setting that preserves a text alternative.
- Refresh Logs button.
- Per-tab reset actions for SMTP and Sender settings.
- Change Password button for saved SMTP passwords.

### Changed
- SMTP Test Connection now saves the posted SMTP configuration before testing.
- Saved SMTP passwords are shown as a disabled dummy value until Change Password is clicked.
- Admin notices are converted to toast messages only to avoid duplicate header and toast messages.
- SMTP primary action is now labeled Save SMTP Settings.
- The admin wrapper now uses the available WordPress admin width.

### Removed
- Removed open tracking, tracking pixels, the tracking REST route, and tracking log columns.

## [1.3.0] - 2026-06-01

### Added
- SMTP connection test button beside the authentication credentials.
- Saved-password placeholder and helper text so admins know a password exists without exposing it.
- Toast notifications mirrored from admin notices, with header-level notice placement.
- Fallback From address of `asmtp-mailer@hostname` when From Email is empty.
- Optional open tracking pixel support with opened-state logging.
- Tracking status column in the Email Log.

### Changed
- Standardized action button alignment across tabs.
- Improved From Email guidance for SMTP providers that reject unowned sender addresses.

### Security
- SMTP connection testing uses the current posted password only in memory and does not persist plaintext.
- Open tracking route uses an unguessable per-email token and logs only matching token requests.

## [1.2.0] - 2026-06-01

### Added
- Automatic SMTP port updates when the encryption selection changes.
- Log status filters for all, sent, and failed records.
- Email log retention settings for the latest 100, 250, or 500 records.
- More useful default test email subject and body with setup guidance.
- Styled toggle controls for checkbox settings.

### Changed
- Replaced the custom `wp_mail()` implementation with native WordPress hooks for SMTP configuration and logging.
- Renamed the test tab from Test & Analysis to Test Email and moved analysis explanation into the screen.
- Renamed admin assets to `assets/css/admin-settings-interface.css` and `assets/js/admin-settings-controls.js`.
- Updated admin colors to a teal/sky palette while keeping the animated header style.
- Grouped SMTP, sender, reply-to, logging, and test controls into clearer sections.
- New SMTP passwords are encrypted with WordPress salts when OpenSSL is available, with legacy base64 passwords still readable.

### Removed
- Removed the SSL certificate verification bypass setting.
- Removed the brittle full mail-send override that duplicated WordPress core mail behavior.

### Security
- SMTP now defaults to TLS and port 587.
- Admin UI assets still load only on the aSMTP mailer admin settings page.
- Mail hooks run globally only to route and log WordPress email send attempts.

## [1.1.0] - 2026-06-01

### Added
- Built-in Reply-To email and name settings with an option to force Reply-To headers.
- Built-in email logging for outgoing delivery attempts, with a clear-log action.
- Test email analysis panel with configuration checks, elapsed send time, and redacted SMTP conversation output.
- aOAUTH Client SSO-inspired admin header, tabs, buttons, tables, logs, and status badge styling.

### Changed
- Reorganized tabs into SMTP, Sender & Reply-To, Test & Analysis, and Email Log.
- Moved admin CSS into `assets/css/asmtp-mailer-admin.css`.
- Split sender identity settings away from SMTP transport settings.

### Removed
- Removed the Add-ons, Advanced, and Server Info tabs.
- Removed unused add-on PHP, CSS, and image assets.

### Security
- Redacted likely credentials and tokens from captured SMTP debug output.
- Validated From and Reply-To addresses before saving sender settings.
- Kept email body logging disabled by default and exposed it as an explicit privacy choice.

## [1.0.0] - 2026-06-01

### Added
- Branded aSMTP mailer admin header, tabs, and minimal settings interface.
- External admin JavaScript for delete-options confirmation.
- Compatibility migration from legacy `smtp_mailer_options` settings.
- Developer release review in `review.txt`.

### Changed
- Renamed the plugin, entrypoint, add-on files, translation template, and image assets to the `asmtp-mailer` naming convention.
- Updated plugin metadata to version `1.0.0` and author `Awhadi`.
- Reworked the add-ons screen as a local extension-ready panel.
- Modernized the existing add-on stylesheet into the plugin admin stylesheet.

### Security
- Added capability checks before settings, test email, delete, and advanced-settings actions.
- Hardened nonce reads with `wp_unslash()` and sanitization.
- Restricted SMTP authentication and encryption values to approved options.
- Validated SMTP ports and test recipient email addresses before use.
- Removed inline JavaScript from the delete-options form.
