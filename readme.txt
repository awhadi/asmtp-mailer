=== aSMTP mailer ===
Contributors: Awhadi
Tags: email, mail, smtp, phpmailer, deliverability
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configure a secure SMTP server for WordPress email delivery.

== Description ==

aSMTP mailer routes WordPress email through an authenticated SMTP server instead of the default PHP mail function. It keeps the interface minimal while providing the controls needed for reliable transactional email delivery.

== Features ==

* SMTP host, port, authentication, username, and password settings.
* TLS, SSL, and no-encryption transport options.
* From email and From name controls.
* Force From name, email, or full address for outgoing messages.
* Reply-To address and display name controls.
* Optional logs for WordPress `wp_mail()` delivery attempts.
* Test email screen with redacted SMTP analysis for developers.
* Secure TLS defaults with automatic port suggestions.
* SMTP connection test for credentials without sending an email.
* Optional plain-text to HTML conversion with text alternative preservation.

== Security Notes ==

* Admin actions require WordPress capabilities and nonces.
* Settings inputs are sanitized and constrained before saving.
* Password values are encrypted with WordPress salts when OpenSSL is available and are never displayed after saving.
* SMTP test analysis redacts likely credentials and tokens.
* Delete confirmation is handled by an external JavaScript file.
* Reset actions are scoped per tab instead of clearing unrelated settings.

== Installation ==

1. Upload the `asmtp-mailer` folder to `/wp-content/plugins/`.
2. Activate `aSMTP mailer` from the WordPress Plugins screen.
3. Go to Settings > aSMTP mailer and enter SMTP credentials.
4. Send a test email to verify the configuration.

== Frequently Asked Questions ==

= Does this plugin keep existing SMTP Mailer settings? =

Yes. On first load, aSMTP mailer imports compatible settings from the legacy `smtp_mailer_options` option if branded settings do not already exist.

== Changelog ==

= 1.4.2 =
* Added an Enable Logs setting to turn delivery log writing on or off.
* Renamed Email Log labels to Logs.
* Removed the Reset Test Form button from the Test Email screen.
* Removed the header logo wrapper while keeping the email icon.
* Removed unused internal plugin code.

= 1.4.1 =
* Added a subtle transparent brand mark behind the plugin header icon and title.

= 1.4.0 =
* Removed open tracking and all tracking log columns.
* Kept plain-text to HTML conversion as an explicit formatting option.
* Removed duplicate header notices and now shows action feedback as toast messages only.
* SMTP Test Connection now saves the posted SMTP configuration before testing.
* Saved SMTP passwords now display as a disabled dummy value with a Change Password button.
* Added Refresh Logs.
* Replaced the global delete action with per-tab reset actions.
* Renamed SMTP action to Save SMTP Settings.
* Expanded the admin wrapper to use the available WordPress admin width.

= 1.3.0 =
* Added SMTP connection testing beside the username/password fields.
* Improved saved-password UX with a saved-password placeholder and status copy.
* Added toast-style admin notices mirrored near the plugin header.
* Added fallback From address generation as `asmtp-mailer@hostname` when From Email is empty.
* Added optional open tracking pixels and opened-state logging.
* Added open tracking settings and tracking status in the Email Log.
* Standardized action alignment across tabs.

= 1.2.0 =
* Replaced the custom `wp_mail()` implementation with native WordPress mail hooks for better compatibility.
* Configured SMTP through `phpmailer_init` and logs through `wp_mail_succeeded` / `wp_mail_failed`.
* Updated secure defaults to TLS on port 587 and added automatic port updates when encryption changes.
* Removed SSL verification bypass settings.
* Added styled toggles, clearer active tabs, grouped settings sections, and refreshed teal/sky admin colors.
* Renamed admin assets to `admin-settings-interface.css` and `admin-settings-controls.js`.
* Added log status filters and retention settings.
* Improved test email default content with setup guidance.
* Improved password storage for newly saved credentials.

= 1.1.0 =
* Added built-in Reply-To settings.
* Added built-in email logging and log clearing.
* Added redacted test email analysis for developer debugging.
* Reworked admin tabs into SMTP, Sender & Reply-To, Test & Analysis, and Email Log.
* Removed add-ons, advanced settings, and server info screens.
* Matched the admin styling direction used by aOAUTH Client SSO.

= 1.0.0 =
* Rebranded the plugin as aSMTP mailer by Awhadi.
* Renamed plugin files and assets to the asmtp-mailer naming convention.
* Modernized the WordPress admin settings interface with external CSS.
* Moved delete confirmation behavior to external JavaScript.
* Hardened admin POST handling with capability checks, nonce checks, and stricter sanitization.
* Added compatibility migration for existing SMTP Mailer settings.
